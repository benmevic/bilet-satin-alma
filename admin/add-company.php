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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $logo_path = null;
    
    if (empty($name)) {
        $error = 'Firma adı gereklidir!';
    } else {
        // Logo yükleme (opsiyonel)
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['logo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            // Dosya tipi kontrolü
            if (!in_array($file['type'], $allowed_types)) {
                $error = 'Sadece JPG, PNG ve GIF formatları kabul edilir!';
            }
            // Boyut kontrolü
            elseif ($file['size'] > $max_size) {
                $error = 'Logo boyutu maksimum 2MB olabilir!';
            }
            else {
                // Klasör yoksa oluştur
                $upload_dir = '../uploads/company-logos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Benzersiz dosya adı
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'company_' . time() . '_' . uniqid() . '.' . $extension;
                $upload_path = $upload_dir . $filename;
                
                // Dosyayı taşı
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $logo_path = 'uploads/company-logos/' . $filename;
                } else {
                    $error = 'Logo yüklenirken bir hata oluştu!';
                }
            }
        }
        
        // Hata yoksa firmayı ekle
        if (empty($error)) {
            try {
                // Aynı isimde firma var mı?
                $stmt = $db->prepare("SELECT COUNT(*) FROM Bus_Company WHERE LOWER(name) = LOWER(?)");
                $stmt->execute([$name]);
                $exists = $stmt->fetchColumn();
                
                if ($exists > 0) {
                    $error = 'Bu isimde bir firma zaten mevcut!';
                } else {
                    // Firmayı ekle
                    $stmt = $db->prepare("
                        INSERT INTO Bus_Company (name, logo_path, created_at) 
                        VALUES (?, ?, datetime('now', 'localtime'))
                    ");
                    $stmt->execute([$name, $logo_path]);
                    
                    header('Location: companies.php?success=added');
                    exit();
                }
                
            } catch (PDOException $e) {
                error_log('ADD COMPANY ERROR: ' . $e->getMessage());
                $error = 'Firma eklenirken bir hata oluştu!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Firma Ekle - Süper Admin</title>
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
        
        .logo-preview {
            margin-top: 15px;
            padding: 20px;
            border: 2px dashed #ddd;
            border-radius: 10px;
            text-align: center;
            background: #f8f9fa;
        }
        
        .logo-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .logo-preview.empty {
            padding: 40px;
            color: #999;
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
            <li><a href="companies.php" class="active"><i class="bi bi-building"></i> Firmalar</a></li>
            <li><a href="company-admins.php"><i class="bi bi-person-badge"></i> Firma Adminleri</a></li>
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
                    <h2 class="mb-0"><i class="bi bi-plus-circle text-danger"></i> Yeni Firma Ekle</h2>
                    <small class="text-muted">Otobüs firması oluştur</small>
                </div>
                <a href="companies.php" class="btn btn-outline-secondary">
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
                    
                    <form method="POST" action="add-company.php" enctype="multipart/form-data">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-building"></i> Firma Adı *
                            </label>
                            <input 
                                type="text" 
                                class="form-control form-control-lg" 
                                name="name" 
                                placeholder="Örn: Metro Turizm"
                                required
                                autofocus
                            >
                            <small class="text-muted">Bu isim kullanıcılara gösterilecektir.</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-image"></i> Firma Logosu (Opsiyonel)
                            </label>
                            <input 
                                type="file" 
                                class="form-control" 
                                name="logo" 
                                accept="image/jpeg,image/png,image/gif"
                                onchange="previewLogo(event)"
                            >
                            <small class="text-muted">Maksimum 2MB - JPG, PNG veya GIF formatında</small>
                            
                            <!-- Logo Önizleme -->
                            <div id="logoPreview" class="logo-preview empty">
                                <i class="bi bi-image" style="font-size: 3rem;"></i>
                                <p class="mb-0 mt-2">Logo seçilmedi</p>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-danger btn-lg">
                                <i class="bi bi-check-circle"></i> Firmayı Oluştur
                            </button>
                            <a href="companies.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> İptal
                            </a>
                        </div>
                        
                    </form>
                    
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Logo önizleme
        function previewLogo(event) {
            const preview = document.getElementById('logoPreview');
            const file = event.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Logo Preview">';
                    preview.classList.remove('empty');
                }
                
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '<i class="bi bi-image" style="font-size: 3rem;"></i><p class="mb-0 mt-2">Logo seçilmedi</p>';
                preview.classList.add('empty');
            }
        }
    </script>
</body>
</html>