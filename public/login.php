<?php
session_start();

// Zaten giriş yapmışsa yönlendir
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'company') {
        header('Location: company-admin/index.php');
    } elseif ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

require_once '../includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Lütfen tüm alanları doldurun!';
    } else {
        try {
            // Normal kullanıcı girişi (role = 'user')
            $stmt = $db->prepare("SELECT * FROM User WHERE email = ? AND role = 'user'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $error = 'E-posta veya şifre hatalı!';
            } elseif ($user['password'] !== $password) {
                $error = 'E-posta veya şifre hatalı!';
            } else {
                // Başarılı giriş
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['balance'] = $user['balance'];
                
                header('Location: index.php');
                exit();
            }
        } catch (PDOException $e) {
            error_log('USER LOGIN ERROR: ' . $e->getMessage());
            $error = 'Giriş yapılırken bir hata oluştu.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - MERİCBİLET</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #C62828 0%, #B71C1C 100%);
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
            background: linear-gradient(135deg, #C62828 0%, #B71C1C 100%);
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
            border-color: #C62828;
            box-shadow: 0 0 0 0.2rem rgba(198, 40, 40, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #C62828 0%, #B71C1C 100%);
            border: none;
            color: white;
            padding: 12px;
            font-size: 1.1rem;
            font-weight: bold;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #B71C1C 0%, #8B0000 100%);
            color: white;
        }
        
        .link-primary {
            color: #C62828 !important;
        }
        
        .link-primary:hover {
            color: #B71C1C !important;
        }
    </style>
</head>
<body>
    
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="bi bi-person-circle" style="font-size: 3rem;"></i>
                <h2 class="mt-3">Giriş Yap</h2>
                <p class="mb-0">MERİCBİLET Kullanıcı Girişi</p>
            </div>
            
            <div class="login-body">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill"></i>
                        <?php echo htmlspecialchars($success); ?>
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
                            placeholder="ornek@email.com"
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
                        <a href="index.php" class="text-muted text-decoration-none">
                            <i class="bi bi-arrow-left"></i> Ana Sayfaya Dön
                        </a>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <small class="text-muted">Henüz hesabınız yok mu?</small><br>
                    <a href="register.php" class="link-primary text-decoration-none fw-bold">
                        <i class="bi bi-person-plus"></i> Kayıt Ol
                    </a>
                </div>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <small class="text-muted">Firma yetkilisi misiniz?</small><br>
                    <a href="company-admin/login.php" class="link-primary text-decoration-none fw-bold">
                        <i class="bi bi-building"></i> Firma Girişi
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>