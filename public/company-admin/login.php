<?php
session_start();

// Zaten giriş yapmışsa dashboard'a yönlendir
if (!empty($_SESSION['user_id']) && $_SESSION['role'] === 'company') {
    header('Location: index.php');
    exit();
}

// ✅ DOĞRU YOL: 2 seviye yukarı (public/company-admin/ → yavuzlar_bilet_uygulamasi/)
require_once '../../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Lütfen tüm alanları doldurun!';
    } else {
        try {
            // User tablosundan firma admin ara (role = 'company')
            $stmt = $db->prepare("SELECT * FROM User WHERE email = ? AND role = 'company'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $error = 'Bu e-posta ile kayıtlı firma yetkilisi bulunamadı!';
            } elseif ($user['password'] !== $password) {
                $error = 'Şifre hatalı!';
            } elseif (empty($user['company_id'])) {
                $error = 'Bu hesap bir firmaya atanmamış! Lütfen sistem yöneticisiyle iletişime geçin.';
            } else {
                // Firma bilgilerini al
                $stmt = $db->prepare("SELECT * FROM Bus_Company WHERE id = ?");
                $stmt->execute([$user['company_id']]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$company) {
                    $error = 'Firma bilgileri bulunamadı!';
                } else {
                    // Başarılı giriş
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['company_id'] = $user['company_id'];
                    $_SESSION['company_name'] = $company['name'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = 'company';
                    
                    header('Location: index.php');
                    exit();
                }
            }
        } catch (PDOException $e) {
            error_log('COMPANY ADMIN LOGIN ERROR: ' . $e->getMessage());
            $error = 'Giriş yapılırken bir hata oluştu: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Yönetici Girişi - MERİCBİLET</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header h2 {
            margin: 0;
            font-weight: bold;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-control:focus {
            border-color: #1976D2;
            box-shadow: 0 0 0 0.2rem rgba(25, 118, 210, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
            border: none;
            color: white;
            padding: 12px;
            font-size: 1.1rem;
            font-weight: bold;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #1565C0 0%, #0D47A1 100%);
            color: white;
        }
    </style>
</head>
<body>
    
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="bi bi-building" style="font-size: 3rem;"></i>
                <h2 class="mt-3">Firma Yönetici Girişi</h2>
                <p class="mb-0">MERİCBİLET Firma Yönetim Paneli</p>
            </div>
            
            <div class="login-body">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="login.php">
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-envelope"></i> E-posta
                        </label>
                        <input 
                            type="email" 
                            class="form-control form-control-lg" 
                            name="email" 
                            placeholder="yonetici@firma.com"
                            required
                            autofocus
                        >
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-lock"></i> Şifre
                        </label>
                        <input 
                            type="password" 
                            class="form-control form-control-lg" 
                            name="password" 
                            placeholder="••••••••"
                            required
                        >
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-login btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <a href="../index.php" class="text-muted text-decoration-none">
                            <i class="bi bi-arrow-left"></i> Ana Sayfaya Dön
                        </a>
                    </div>
                </form>
                
                
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>