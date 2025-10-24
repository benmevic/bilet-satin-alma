<?php
session_start();

// Zaten giriş yapmışsa dashboard'a yönlendir
if (!empty($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: index.php');
    exit();
}

require_once '../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Lütfen tüm alanları doldurun!';
    } else {
        try {
            $stmt = $db->prepare("SELECT * FROM User WHERE email = ? AND role = 'admin'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // ✅ PLAIN TEXT ŞİFRE KONTROLÜ
            if ($user && $user['password'] === $password) {
                // Admin girişi başarılı
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['balance'] = floatval($user['balance'] ?? 0);
                
                header('Location: index.php');
                exit();
            } else {
                $error = 'Geçersiz admin girişi! E-posta veya şifre hatalı.';
            }
            
        } catch (PDOException $e) {
            error_log('ADMIN LOGIN ERROR: ' . $e->getMessage());
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
    <title>Süper Admin Girişi - MERİCBİLET</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #d32f2f 0%, #c62828 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #d32f2f 0%, #c62828 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header i {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        
        .login-header h2 {
            margin: 0;
            font-weight: bold;
        }
        
        .login-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-control {
            padding: 12px 15px;
            border-radius: 10px;
            border: 2px solid #ddd;
        }
        
        .form-control:focus {
            border-color: #d32f2f;
            box-shadow: 0 0 0 0.2rem rgba(211, 47, 47, 0.25);
        }
        
        .btn-admin {
            background: linear-gradient(135deg, #d32f2f 0%, #c62828 100%);
            border: none;
            color: white;
            padding: 12px;
            border-radius: 10px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(211, 47, 47, 0.4);
            color: white;
        }
        
        .alert {
            border-radius: 10px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #d32f2f;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    
    <div class="login-container">
        <div class="login-header">
            <i class="bi bi-shield-fill-check"></i>
            <h2>Süper Admin Girişi</h2>
            <p>Yönetim paneline hoş geldiniz</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-envelope"></i> E-posta Adresi
                    </label>
                    <input 
                        type="email" 
                        class="form-control" 
                        name="email" 
                        placeholder="admin@mericbilet.com"
                        required
                        autofocus
                    >
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-lock"></i> Şifre
                    </label>
                    <input 
                        type="password" 
                        class="form-control" 
                        name="password" 
                        placeholder="••••••••"
                        required
                    >
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-admin btn-lg">
                        <i class="bi bi-shield-check"></i> Admin Girişi
                    </button>
                </div>
            </form>
            
            <div class="back-link">
                <a href="../public/index.php">
                    <i class="bi bi-arrow-left"></i> Ana Sayfaya Dön
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>