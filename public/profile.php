<?php
session_start();

// Giriş kontrolü
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

// ✅ BAKİYE YÜKLEME (İLK ÖNCE İŞLENMELİ - DB güncellemesinden önce)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['load_balance'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    
    if ($amount <= 0) {
        $error = 'Lütfen geçerli bir tutar girin!';
    } elseif ($amount < 10) {
        $error = 'Minimum yükleme tutarı 10 TL\'dir!';
    } elseif ($amount > 10000) {
        $error = 'Maksimum yükleme tutarı 10,000 TL\'dir!';
    } else {
        try {
            // Mevcut bakiyeyi al
            $stmt = $db->prepare("SELECT balance FROM User WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $current_balance = $stmt->fetchColumn();
            
            // Yeni bakiyeyi hesapla
            $new_balance = $current_balance + $amount;
            
            // Bakiyeyi güncelle
            $stmt = $db->prepare("UPDATE User SET balance = ? WHERE id = ?");
            $stmt->execute([$new_balance, $_SESSION['user_id']]);
            
            // Session'ı güncelle
            $_SESSION['balance'] = $new_balance;
            
            // ✅ REDIRECT (POST tekrarını önler)
            header('Location: profile.php?success=balance_loaded&amount=' . $amount);
            exit();
            
        } catch (PDOException $e) {
            error_log('BALANCE LOAD ERROR: ' . $e->getMessage());
            $error = 'Bakiye yüklenirken hata oluştu.';
        }
    }
}

// Şifre değiştirme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // Önce güncel kullanıcıyı çek
    $stmt = $db->prepare("SELECT password FROM User WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Lütfen tüm alanları doldurun!';
    } elseif ($current_password !== $current_user['password']) {
        $error = 'Mevcut şifre hatalı!';
    } elseif (strlen($new_password) < 6) {
        $error = 'Yeni şifre en az 6 karakter olmalıdır!';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Yeni şifreler eşleşmiyor!';
    } else {
        try {
            $stmt = $db->prepare("UPDATE User SET password = ? WHERE id = ?");
            $stmt->execute([$new_password, $_SESSION['user_id']]);
            
            header('Location: profile.php?success=password_changed');
            exit();
            
        } catch (PDOException $e) {
            error_log('PASSWORD CHANGE ERROR: ' . $e->getMessage());
            $error = 'Şifre değiştirilirken hata oluştu.';
        }
    }
}

// Profil güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($full_name) || empty($email)) {
        $error = 'Lütfen tüm alanları doldurun!';
    } else {
        try {
            // Email başkası tarafından kullanılıyor mu?
            $stmt = $db->prepare("SELECT id FROM User WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $error = 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor!';
            } else {
                $stmt = $db->prepare("UPDATE User SET full_name = ?, email = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $_SESSION['user_id']]);
                
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                
                header('Location: profile.php?success=profile_updated');
                exit();
            }
        } catch (PDOException $e) {
            error_log('PROFILE UPDATE ERROR: ' . $e->getMessage());
            $error = 'Profil güncellenirken hata oluştu.';
        }
    }
}

// ✅ KULLANICI BİLGİLERİNİ ÇEK (POST işlemlerinden SONRA)
try {
    $stmt = $db->prepare("SELECT * FROM User WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit();
    }
    
    // İstatistikler
    $stmt = $db->prepare("SELECT COUNT(*) FROM Tickets WHERE user_id = ? AND (status IS NULL OR status = 'active')");
    $stmt->execute([$_SESSION['user_id']]);
    $total_tickets = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT SUM(total_price) FROM Tickets WHERE user_id = ? AND (status IS NULL OR status = 'active')");
    $stmt->execute([$_SESSION['user_id']]);
    $total_spent = $stmt->fetchColumn() ?? 0;
    
    $stmt = $db->prepare("SELECT created_at FROM Tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $last_ticket_date = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log('PROFILE FETCH ERROR: ' . $e->getMessage());
    $error = 'Bir hata oluştu.';
}

// ✅ BAŞARI MESAJLARI (GET parametresinden)
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'balance_loaded':
            $loaded_amount = floatval($_GET['amount'] ?? 0);
            $success = number_format($loaded_amount, 2) . ' TL başarıyla yüklendi! Yeni bakiyeniz: ' . number_format($user['balance'], 2) . ' TL';
            break;
        case 'password_changed':
            $success = 'Şifreniz başarıyla değiştirildi!';
            break;
        case 'profile_updated':
            $success = 'Profil bilgileriniz başarıyla güncellendi!';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilim - MERİCBİLET</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-12 text-center">
                    <h1 class="display-4 fw-bold text-white mb-3">
                        <i class="bi bi-person-circle text-warning"></i>
                        Profilim
                    </h1>
                    <p class="lead text-white-50 mb-4">
                        Hesap bilgilerinizi yönetin
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="container search-panel-container">

        <!-- Hata/Başarı Mesajları -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <!-- Sol Kolon: Profil Bilgileri -->
            <div class="col-lg-4">
                
                <!-- Kullanıcı Kartı -->
                <div class="card search-panel shadow-lg mb-4">
                    <div class="card-header bg-primary-custom text-white text-center">
                        <h5 class="mb-0">
                            <i class="bi bi-person-badge"></i> Kullanıcı Bilgileri
                        </h5>
                    </div>
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #C62828 0%, #8E0000 100%); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: white; font-size: 3rem;">
                                <i class="bi bi-person-fill"></i>
                            </div>
                        </div>
                        <h4 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                        <p class="text-muted mb-3">
                            <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                        </p>
                        <div class="d-grid gap-2">
                            <div class="alert alert-success mb-0">
                                <h3 class="mb-0 fw-bold">
                                    <i class="bi bi-wallet2"></i> <?php echo number_format($user['balance'], 2); ?> TL
                                </h3>
                                <small>Mevcut Bakiye</small>
                            </div>
                        </div>
                        <hr>
                        <div class="row text-center">
                            <div class="col-6">
                                <small class="text-muted d-block">Rol</small>
                                <strong class="text-primary-custom"><?php echo strtoupper($user['role']); ?></strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Üyelik</small>
                                <strong class="text-primary-custom"><?php echo formatDate($user['created_at']); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- İstatistikler -->
                <div class="card search-panel shadow-lg">
                    <div class="card-header bg-primary-custom text-white text-center">
                        <h5 class="mb-0">
                            <i class="bi bi-graph-up"></i> İstatistikler
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <small class="text-muted d-block">Toplam Bilet</small>
                            <h4 class="text-primary-custom mb-0">
                                <i class="bi bi-ticket-perforated"></i> <?php echo $total_tickets; ?> Adet
                            </h4>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <small class="text-muted d-block">Toplam Harcama</small>
                            <h4 class="text-danger mb-0">
                                <i class="bi bi-cash-stack"></i> <?php echo number_format($total_spent, 2); ?> TL
                            </h4>
                        </div>
                        <hr>
                        <div>
                            <small class="text-muted d-block">Son Bilet</small>
                            <h5 class="text-success mb-0">
                                <i class="bi bi-calendar-check"></i> 
                                <?php echo $last_ticket_date ? formatDateTime($last_ticket_date) : 'Henüz yok'; ?>
                            </h5>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Sağ Kolon: İşlemler -->
            <div class="col-lg-8">

                <!-- ✅ BAKİYE YÜKLEME (YENİ) -->
                <div class="card search-panel shadow-lg mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-wallet2"></i> Bakiye Yükle
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Bilgi:</strong> Minimum 10 TL, maksimum 10,000 TL yükleyebilirsiniz.
                        </div>
                        <form method="POST" action="profile.php">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-cash"></i> Yüklenecek Tutar (TL)
                                    </label>
                                    <input 
                                        type="number" 
                                        class="form-control form-control-lg" 
                                        name="amount" 
                                        placeholder="Örn: 100"
                                        min="10"
                                        max="10000"
                                        step="0.01"
                                        required
                                    >
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" name="load_balance" class="btn btn-success btn-lg w-100">
                                        <i class="bi bi-plus-circle"></i> Yükle
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Hızlı Tutar Butonları -->
                            <div class="mt-3">
                                <small class="text-muted d-block mb-2">Hızlı Seçim:</small>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-outline-success" onclick="document.querySelector('input[name=amount]').value = 50">50 TL</button>
                                    <button type="button" class="btn btn-outline-success" onclick="document.querySelector('input[name=amount]').value = 100">100 TL</button>
                                    <button type="button" class="btn btn-outline-success" onclick="document.querySelector('input[name=amount]').value = 250">250 TL</button>
                                    <button type="button" class="btn btn-outline-success" onclick="document.querySelector('input[name=amount]').value = 500">500 TL</button>
                                    <button type="button" class="btn btn-outline-success" onclick="document.querySelector('input[name=amount]').value = 1000">1000 TL</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Profil Güncelleme -->
                <div class="card search-panel shadow-lg mb-4">
                    <div class="card-header bg-primary-custom text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-pencil-square"></i> Profil Bilgilerini Güncelle
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="profile.php">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-person"></i> Ad Soyad
                                    </label>
                                    <input 
                                        type="text" 
                                        class="form-control form-control-lg" 
                                        name="full_name" 
                                        value="<?php echo htmlspecialchars($user['full_name']); ?>"
                                        required
                                    >
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-envelope"></i> E-posta
                                    </label>
                                    <input 
                                        type="email" 
                                        class="form-control form-control-lg" 
                                        name="email" 
                                        value="<?php echo htmlspecialchars($user['email']); ?>"
                                        required
                                    >
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="update_profile" class="btn btn-primary-custom btn-lg">
                                        <i class="bi bi-check-circle"></i> Güncelle
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Şifre Değiştirme -->
                <div class="card search-panel shadow-lg">
                    <div class="card-header bg-primary-custom text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-key-fill"></i> Şifre Değiştir
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="profile.php">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-lock"></i> Mevcut Şifre
                                    </label>
                                    <input 
                                        type="password" 
                                        class="form-control form-control-lg" 
                                        name="current_password" 
                                        placeholder="••••••••"
                                        required
                                    >
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-lock-fill"></i> Yeni Şifre
                                    </label>
                                    <input 
                                        type="password" 
                                        class="form-control form-control-lg" 
                                        name="new_password" 
                                        placeholder="En az 6 karakter"
                                        minlength="6"
                                        required
                                    >
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-lock-fill"></i> Yeni Şifre (Tekrar)
                                    </label>
                                    <input 
                                        type="password" 
                                        class="form-control form-control-lg" 
                                        name="confirm_password" 
                                        placeholder="Tekrar girin"
                                        minlength="6"
                                        required
                                    >
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="change_password" class="btn btn-primary-custom btn-lg">
                                        <i class="bi bi-shield-check"></i> Şifreyi Değiştir
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container text-center">
            <p class="mb-0">
                <i class="bi bi-bus-front-fill"></i> MERİCBİLET &copy; 2025
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>