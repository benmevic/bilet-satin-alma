<?php
session_start();

// Admin kontrolü
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

$error = '';

// Firmaları al
try {
    $stmt = $db->query("SELECT id, name FROM Bus_Company ORDER BY name ASC");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('GET COMPANIES ERROR: ' . $e->getMessage());
    $companies = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $company_id = $_POST['company_id'] ?? null;
    
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Tüm alanlar zorunludur!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi girin!';
    } elseif (strlen($password) < 4) {
        $error = 'Şifre en az 4 karakter olmalıdır!';
    } else {
        try {
            // E-posta kontrolü
            $stmt = $db->prepare("SELECT COUNT(*) FROM User WHERE email = ?");
            $stmt->execute([$email]);
            $exists = $stmt->fetchColumn();
            
            if ($exists > 0) {
                $error = 'Bu e-posta adresi zaten kullanılıyor!';
            } else {
                // Kullanıcıyı ekle
                $stmt = $db->prepare("
                    INSERT INTO User (full_name, email, password, role, company_id, balance, created_at) 
                    VALUES (?, ?, ?, 'company', ?, 0, datetime('now', 'localtime'))
                ");
                $stmt->execute([$full_name, $email, $password, $company_id]);
                
                header('Location: company-admins.php?success=added');
                exit();
            }
            
        } catch (PDOException $e) {
            error_log('ADD COMPANY ADMIN ERROR: ' . $e->getMessage());
            $error = 'Kullanıcı eklenirken bir hata oluştu!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Admini Ekle - Süper Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(135deg, #d32f2f 0%, #c62828 100%);
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
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 20px 0;
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
        }
        
        .logout-section a:hover {
            background: rgba(255,82,82,0.8);
        }
        
        .form-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="bi bi-shield-fill-check"></i> Süper Admin</h4>
            <small><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></small>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="companies.php"><i class="bi bi-building"></i> Firmalar</a></li>
            <li><a href="company-admins.php" class="active"><i class="bi bi-person-badge"></i> Firma Adminleri</a></li>
            <li><a href="global-coupons.php"><i class="bi bi-ticket-perforated"></i> Global Kuponlar</a></li>
            <li><a href="../public/index.php"><i class="bi bi-globe"></i> Ana Siteye Dön</a></li>
        </ul>
        
        <div class="logout-section">
            <a href="logout.php">
                <i class="bi bi-box-arrow-right me-2"></i> Çıkış Yap
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        
        <div class="top-bar">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0"><i class="bi bi-person-plus text-danger"></i> Firma Admini Ekle</h2>
                    <small class="text-muted">Yeni firma yöneticisi oluştur</small>
                </div>
                <a href="company-admins.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Geri Dön
                </a>
            </div>
        </div>

        <!-- Form -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="form-card">
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Not:</strong> Firma ataması opsiyoneldir. Daha sonra atayabilirsiniz.
                    </div>
                    
                    <form method="POST" action="add-company-admin.php">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-person"></i> Ad Soyad *
                            </label>
                            <input 
                                type="text" 
                                class="form-control form-control-lg" 
                                name="full_name" 
                                placeholder="Örn: Ahmet Yılmaz"
                                required
                                autofocus
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-envelope"></i> E-posta Adresi *
                            </label>
                            <input 
                                type="email" 
                                class="form-control form-control-lg" 
                                name="email" 
                                placeholder="ornek@firma.com"
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-lock"></i> Şifre *
                            </label>
                            <input 
                                type="password" 
                                class="form-control form-control-lg" 
                                name="password" 
                                placeholder="En az 4 karakter"
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-building"></i> Firma (Opsiyonel)
                            </label>
                            <select class="form-select form-select-lg" name="company_id">
                                <option value="">-- Firma Seçin (Atanmamış) --</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>">
                                        <?php echo htmlspecialchars($company['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Boş bırakabilirsiniz, sonra atayabilirsiniz.</small>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-danger btn-lg">
                                <i class="bi bi-check-circle"></i> Firma Adminini Oluştur
                            </button>
                            <a href="company-admins.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> İptal
                            </a>
                        </div>
                        
                    </form>
                    
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>