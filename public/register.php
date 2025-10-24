<?php
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once '../includes/db.php';

function sanitize($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Lütfen tüm alanları doldurun.';
    } elseif (strlen($password) < 6) {
        $error = 'Şifre en az 6 karakter olmalıdır.';
    } else {
        try {
            // Email zaten var mı?
            $stmt = $db->prepare("SELECT id FROM User WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Bu e-posta adresi zaten kayıtlı!';
            } else {
                // Kullanıcı ekle (DÜZ TEXT ŞİFRE - password_hash YOK!)
                $stmt = $db->prepare("INSERT INTO User (full_name, email, password, role, balance, created_at) VALUES (?, ?, ?, 'user', 800.00, datetime('now'))");
                $stmt->execute([$full_name, $email, $password]);
                
                $success = 'Kayıt başarılı! 800 TL hediye bakiye hesabınıza tanımlandı. Giriş yapabilirsiniz.';
                
                error_log('REGISTER SUCCESS: ' . $email);
            }
        } catch (PDOException $e) {
            error_log('REGISTER ERROR: ' . $e->getMessage());
            $error = 'Bir hata oluştu: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - MERİCBİLET</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">
    
    <nav class="navbar navbar-dark bg-primary-custom">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-bus-front-fill"></i> MERİCBİLET
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100 py-5">
            <div class="col-md-6 col-lg-5">
                
                <div class="text-center mb-4">
                    <div class="auth-icon">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                    <h2 class="fw-bold mt-3">Hesap Oluştur</h2>
                    <p class="text-muted">Hemen üye ol, seyahate başla!</p>
                </div>

                <div class="card auth-card shadow-lg">
                    <div class="card-body p-4">
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <?php echo sanitize($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="bi bi-check-circle-fill"></i>
                                <?php echo sanitize($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="register.php">
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-person"></i> Ad Soyad
                                </label>
                                <input 
                                    type="text" 
                                    class="form-control form-control-lg" 
                                    name="full_name" 
                                    value="<?php echo isset($_POST['full_name']) ? sanitize($_POST['full_name']) : ''; ?>"
                                    placeholder="Adınız ve Soyadınız"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-envelope"></i> Email
                                </label>
                                <input 
                                    type="email" 
                                    class="form-control form-control-lg" 
                                    name="email" 
                                    value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>"
                                    placeholder="ornek@email.com"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-lock"></i> Şifre
                                </label>
                                <input 
                                    type="password" 
                                    class="form-control form-control-lg" 
                                    name="password" 
                                    placeholder="En az 6 karakter"
                                    minlength="6"
                                    required
                                >
                                <small class="text-muted">En az 6 karakter olmalıdır</small>
                            </div>

                            <button type="submit" class="btn btn-primary-custom btn-lg w-100 mb-3">
                                <i class="bi bi-person-plus"></i> Kayıt Ol
                            </button>
                        </form>

                        <div class="divider">
                            <span>VEYA</span>
                        </div>

                        <div class="text-center mt-3">
                            <p class="mb-2">Zaten hesabınız var mı?</p>
                            <a href="login.php" class="btn btn-outline-primary-custom w-100">
                                <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
                            </a>
                        </div>

                        <hr class="my-3">

                        <div class="text-center">
                            <a href="index.php" class="text-muted text-decoration-none">
                                <i class="bi bi-arrow-left"></i> Ana Sayfaya Dön
                            </a>
                        </div>

                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-4 text-center">
                        <i class="bi bi-shield-check text-primary-custom" style="font-size: 2rem;"></i>
                        <small class="d-block mt-2">Güvenli</small>
                    </div>
                    <div class="col-4 text-center">
                        <i class="bi bi-lightning-charge text-primary-custom" style="font-size: 2rem;"></i>
                        <small class="d-block mt-2">Hızlı</small>
                    </div>
                    <div class="col-4 text-center">
                        <i class="bi bi-gift text-primary-custom" style="font-size: 2rem;"></i>
                        <small class="d-block mt-2">800 TL Hediye</small>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>