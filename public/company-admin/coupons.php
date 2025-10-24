<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Giriş kontrolü
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'company') {
    header('Location: login.php');
    exit();
}

if (empty($_SESSION['company_id'])) {
    header('Location: login.php?error=no_company');
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$company_id = $_SESSION['company_id'];

// Firma bilgisi
$stmt = $db->prepare("SELECT * FROM Bus_Company WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Kupon silme
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $coupon_id = intval($_GET['delete']);
    
    try {
        // Bu kuponun bu firmaya ait olduğunu kontrol et
        $stmt = $db->prepare("SELECT id FROM Coupons WHERE id = ? AND company_id = ?");
        $stmt->execute([$coupon_id, $company_id]);
        
        if ($stmt->fetch()) {
            $stmt = $db->prepare("DELETE FROM Coupons WHERE id = ? AND company_id = ?");
            $stmt->execute([$coupon_id, $company_id]);
            
            header('Location: coupons.php?success=deleted');
            exit();
        } else {
            header('Location: coupons.php?error=unauthorized');
            exit();
        }
    } catch (PDOException $e) {
        error_log('DELETE COUPON ERROR: ' . $e->getMessage());
        header('Location: coupons.php?error=delete_failed');
        exit();
    }
}

// Yeni kupon ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discount = floatval($_POST['discount'] ?? 0);
    $usage_limit = intval($_POST['usage_limit'] ?? 0);
    $expire_date_raw = $_POST['expire_date'] ?? '';
    
    // Validasyon
    if (empty($code)) {
        $error = 'Kupon kodu boş olamaz!';
    } elseif (strlen($code) < 3 || strlen($code) > 20) {
        $error = 'Kupon kodu 3-20 karakter arasında olmalıdır!';
    } elseif ($discount <= 0 || $discount > 100) {
        $error = 'İndirim oranı 1-100 arasında olmalıdır!';
    } elseif ($usage_limit < 0) {
        $error = 'Kullanım limiti negatif olamaz!';
    } elseif (empty($expire_date_raw)) {
        $error = 'Son kullanma tarihi seçilmelidir!';
    } else {
        try {
            // Kupon kodu benzersiz mi kontrol et
            $stmt = $db->prepare("SELECT id FROM Coupons WHERE code = ? AND company_id = ?");
            $stmt->execute([$code, $company_id]);
            
            if ($stmt->fetch()) {
                $error = "'{$code}' kupon kodu zaten kullanılıyor!";
            } else {
                // Tarih formatını düzelt
                $expire_date = str_replace('T', ' ', $expire_date_raw);
                if (strlen($expire_date) === 16) {
                    $expire_date .= ':00';
                }
                
                // Kupon ekle
                $stmt = $db->prepare("
                    INSERT INTO Coupons (company_id, code, discount, usage_limit, expire_date, created_at) 
                    VALUES (?, ?, ?, ?, ?, datetime('now'))
                ");
                
                $result = $stmt->execute([
                    $company_id,
                    $code,
                    $discount,
                    $usage_limit,
                    $expire_date
                ]);
                
                if ($result) {
                    $success = "✅ Kupon başarıyla oluşturuldu! (Kod: {$code})";
                    $_POST = array();
                } else {
                    $error = '❌ Kupon eklenemedi!';
                }
            }
            
        } catch (PDOException $e) {
            error_log('ADD COUPON ERROR: ' . $e->getMessage());
            $error = '❌ Hata: ' . $e->getMessage();
        }
    }
}

// Kuponları getir
$stmt = $db->prepare("SELECT * FROM Coupons WHERE company_id = ? ORDER BY created_at DESC");
$stmt->execute([$company_id]);
$coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Her kupon için kullanım sayısını hesapla
foreach ($coupons as &$coupon) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM User_Coupons WHERE coupon_id = ?");
    $stmt->execute([$coupon['id']]);
    $coupon['used_count'] = $stmt->fetchColumn();
    
    // Aktif mi kontrol et
    $is_expired = strtotime($coupon['expire_date']) < time();
    $is_limit_reached = ($coupon['usage_limit'] > 0 && $coupon['used_count'] >= $coupon['usage_limit']);
    $coupon['is_active'] = !$is_expired && !$is_limit_reached;
}
unset($coupon);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İndirim Kuponları - <?php echo htmlspecialchars($company['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #F5F5F5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
            color: white;
            padding: 0;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h4 {
            margin: 0;
            font-weight: bold;
            font-size: 1.3rem;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .sidebar-menu li {
            margin: 5px 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
            padding-left: 16px;
        }
        
        .sidebar-menu i {
            margin-right: 12px;
            font-size: 1.2rem;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 30px;
        }
        
        .top-bar {
            background: white;
            padding: 20px 30px;
            margin: -30px -30px 30px -30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .logout-section {
            position: absolute;
            bottom: 20px;
            width: calc(100% - 40px);
            margin: 0 20px;
        }
        
        .logout-section a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .logout-section a:hover {
            background: rgba(255,82,82,0.8);
        }
        
        .coupon-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 5px solid #1976D2;
            transition: transform 0.2s;
        }
        
        .coupon-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .coupon-card.expired {
            border-left-color: #9E9E9E;
            opacity: 0.7;
        }
        
        .coupon-code {
            font-size: 1.5rem;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            background: linear-gradient(135deg, #1976D2 0%, #42A5F5 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .discount-badge {
            display: inline-block;
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 1.3rem;
            font-weight: bold;
        }
        
        .form-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .btn-admin-primary {
            background: #1976D2;
            color: white;
            border: none;
        }
        
        .btn-admin-primary:hover {
            background: #1565C0;
            color: white;
        }
        
        .status-active {
            background: #E8F5E9;
            color: #2E7D32;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.9rem;
        }
        
        .status-expired {
            background: #FFEBEE;
            color: #C62828;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.9rem;
        }
        
        .status-limit {
            background: #FFF3E0;
            color: #E65100;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="bi bi-building"></i> Firma Panel</h4>
            <small><?php echo htmlspecialchars($company['name']); ?></small>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="index.php">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="trips.php">
                    <i class="bi bi-bus-front"></i>
                    <span>Seferler</span>
                </a>
            </li>
            <li>
                <a href="add-trip.php">
                    <i class="bi bi-plus-circle"></i>
                    <span>Sefer Ekle</span>
                </a>
            </li>
            <li>
                <a href="coupons.php" class="active">
                    <i class="bi bi-ticket-perforated"></i>
                    <span>İndirim Kuponları</span>
                </a>
            </li>
            <li>
                <a href="../index.php">
                    <i class="bi bi-globe"></i>
                    <span>Ana Siteye Dön</span>
                </a>
            </li>
        </ul>
        
        <div class="logout-section">
            <a href="logout.php">
                <i class="bi bi-box-arrow-right me-2"></i>
                <span>Çıkış Yap</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Top Bar -->
        <div class="top-bar">
            <h2 class="mb-0">
                <i class="bi bi-ticket-perforated text-warning"></i> İndirim Kuponları
            </h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">İndirim Kuponları</li>
                </ol>
            </nav>
        </div>

        <!-- Mesajlar -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> Kupon başarıyla silindi!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            
            <!-- Yeni Kupon Oluştur Formu -->
            <div class="col-md-5 mb-4">
                <div class="form-card">
                    <h4 class="mb-4">
                        <i class="bi bi-plus-circle text-primary"></i> Yeni Kupon Oluştur
                    </h4>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="add_coupon" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="bi bi-tag-fill"></i> Kupon Kodu
                            </label>
                            <input 
                                type="text" 
                                class="form-control form-control-lg" 
                                name="code" 
                                placeholder="Örn: YILBASI2025"
                                maxlength="20"
                                style="text-transform: uppercase;"
                                required
                            >
                            <small class="text-muted">3-20 karakter, büyük harf kullanın</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="bi bi-percent"></i> İndirim Oranı (%)
                            </label>
                            <input 
                                type="number" 
                                class="form-control form-control-lg" 
                                name="discount" 
                                placeholder="Örn: 15"
                                min="1"
                                max="100"
                                step="0.01"
                                required
                            >
                            <small class="text-muted">1-100 arası</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="bi bi-123"></i> Kullanım Limiti
                            </label>
                            <input 
                                type="number" 
                                class="form-control form-control-lg" 
                                name="usage_limit" 
                                placeholder="Örn: 100 (0 = sınırsız)"
                                min="0"
                                value="0"
                                required
                            >
                            <small class="text-muted">0 = sınırsız kullanım</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="bi bi-calendar-x"></i> Son Kullanma Tarihi
                            </label>
                            <input 
                                type="datetime-local" 
                                class="form-control form-control-lg" 
                                name="expire_date" 
                                min="<?php echo date('Y-m-d\TH:i'); ?>"
                                required
                            >
                        </div>
                        
                        <button type="submit" class="btn btn-admin-primary w-100 btn-lg">
                            <i class="bi bi-check-circle"></i> Kupon Oluştur
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Kuponlar Listesi -->
            <div class="col-md-7">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>
                        Mevcut Kuponlar <span class="badge bg-primary"><?php echo count($coupons); ?></span>
                    </h4>
                </div>
                
                <?php if (count($coupons) > 0): ?>
                    <?php foreach ($coupons as $coupon): ?>
                        <?php
                            $is_expired = strtotime($coupon['expire_date']) < time();
                            $is_limit_reached = ($coupon['usage_limit'] > 0 && $coupon['used_count'] >= $coupon['usage_limit']);
                        ?>
                        <div class="coupon-card <?php echo $is_expired ? 'expired' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="coupon-code mb-2">
                                        <i class="bi bi-ticket-perforated-fill"></i>
                                        <?php echo htmlspecialchars($coupon['code']); ?>
                                    </div>
                                    
                                    <div class="discount-badge mb-3">
                                        %<?php echo number_format($coupon['discount'], 0); ?> İndirim
                                    </div>
                                    
                                    <div class="row g-2 mb-2">
                                        <div class="col-auto">
                                            <i class="bi bi-calendar-event text-muted"></i>
                                            <small>Son: <?php echo formatDateTime($coupon['expire_date']); ?></small>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-bar-chart text-muted"></i>
                                            <small>
                                                Kullanım: <?php echo $coupon['used_count']; ?>
                                                <?php if ($coupon['usage_limit'] > 0): ?>
                                                    / <?php echo $coupon['usage_limit']; ?>
                                                <?php else: ?>
                                                    / ∞
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <?php if ($is_expired): ?>
                                            <span class="status-expired">
                                                <i class="bi bi-x-circle"></i> Süresi Dolmuş
                                            </span>
                                        <?php elseif ($is_limit_reached): ?>
                                            <span class="status-limit">
                                                <i class="bi bi-exclamation-triangle"></i> Limit Doldu
                                            </span>
                                        <?php else: ?>
                                            <span class="status-active">
                                                <i class="bi bi-check-circle"></i> Aktif
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div>
                                    <button 
                                        class="btn btn-outline-danger btn-sm" 
                                        onclick="confirmDeleteCoupon('<?php echo $coupon['code']; ?>', <?php echo $coupon['id']; ?>)"
                                        title="Sil"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 4rem; opacity: 0.3;"></i>
                        <h4 class="mt-3 text-muted">Henüz Kupon Yok</h4>
                        <p class="text-muted">İlk kuponunuzu oluşturun!</p>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDeleteCoupon(code, id) {
            if (confirm(`"${code}" kuponunu silmek istediğinize emin misiniz?\n\nBu işlem geri alınamaz!`)) {
                window.location.href = `coupons.php?delete=${id}`;
            }
        }
        
        // Kupon kodunu otomatik büyük harfe çevir
        document.querySelector('input[name="code"]').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>