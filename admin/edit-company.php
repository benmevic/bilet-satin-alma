<?php
session_start();

// Admin kontrolü
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

$company_id = $_GET['id'] ?? null;

if (!$company_id) {
    header('Location: companies.php');
    exit();
}

$error = '';

// Firma bilgilerini al
try {
    $stmt = $db->prepare("SELECT * FROM Bus_Company WHERE id = ?");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        header('Location: companies.php?error=not_found');
        exit();
    }
    
} catch (PDOException $e) {
    error_log('GET COMPANY ERROR: ' . $e->getMessage());
    header('Location: companies.php?error=fetch_failed');
    exit();
}

// Güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $logo_path = $company['logo_path']; // Mevcut logo
    $delete_logo = isset($_POST['delete_logo']);
    
    if (empty($name)) {
        $error = 'Firma adı gereklidir!';
    } else {
        // Logo silme isteği
        if ($delete_logo && $logo_path) {
            if (file_exists('../' . $logo_path)) {
                unlink('../' . $logo_path);
            }
            $logo_path = null;
        }
        
        // Yeni logo yükleme
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
                // Eski logoyu sil
                if ($logo_path && file_exists('../' . $logo_path)) {
                    unlink('../' . $logo_path);
                }
                
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
        
        // Hata yoksa güncelle
        if (empty($error)) {
            try {
                // Başka firma aynı ismi kullanıyor mu?
                $stmt = $db->prepare("SELECT COUNT(*) FROM Bus_Company WHERE LOWER(name) = LOWER(?) AND id != ?");
                $stmt->execute([$name, $company_id]);
                $exists = $stmt->fetchColumn();
                
                if ($exists > 0) {
                    $error = 'Bu isimde başka bir firma zaten mevcut!';
                } else {
                    // Güncelle
                    $stmt = $db->prepare("UPDATE Bus_Company SET name = ?, logo_path = ? WHERE id = ?");
                    $stmt->execute([$name, $logo_path, $company_id]);
                    
                    header('Location: companies.php?success=updated');
                    exit();
                }
                
            } catch (PDOException $e) {
                error_log('UPDATE COMPANY ERROR: ' . $e->getMessage());
                $error = 'Firma güncellenirken bir hata oluştu!';
            }
        }
    }
    
    // Firma bilgilerini tekrar çek (güncelleme sonrası)
    $stmt = $db->prepare("SELECT * FROM Bus_Company WHERE id = ?");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Düzenle - Süper Admin</title>
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
        
        .current-logo {
            padding: 20px;
            border: 2px solid #28a745;
            border-radius: 10px;
            background: #f8fff9;
            text-align: center;
        }
        
        .current-logo img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
                    <h2 class="mb-0"><i class="bi bi-pencil text-warning"></i> Firma Düzenle</h2>
                    <small class="text-muted">Firma bilgilerini güncelle</small>
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
                    
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-info-circle"></i>
                        <strong>Firma ID:</strong> <?php echo $company['id']; ?> | 
                        <strong>Oluşturulma:</strong> <?php echo formatDateTime($company['created_at']); ?>
                    </div>
                    
                    <form method="POST" action="edit-company.php?id=<?php echo $company_id; ?>" enctype="multipart/form-data">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-building"></i> Firma Adı *
                            </label>
                            <input 
                                type="text" 
                                class="form-control form-control-lg" 
                                name="name" 
                                value="<?php echo htmlspecialchars($company['name']); ?>"
                                required
                                autofocus
                            >
                        </div>
                        
                        <!-- Mevcut Logo -->
                        <?php if ($company['logo_path']): ?>
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-image-fill text-success"></i> Mevcut Logo
                                </label>
                                <div class="current-logo">
                                    <img src="../<?php echo htmlspecialchars($company['logo_path']); ?>" alt="<?php echo htmlspecialchars($company['name']); ?>">
                                    <div class="mt-3">
                                        <label class="form-check-label">
                                            <input type="checkbox" name="delete_logo" class="form-check-input" value="1">
                                            <span class="text-danger"><i class="bi bi-trash"></i> Logoyu Sil</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Yeni Logo Yükleme -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-image"></i> <?php echo $company['logo_path'] ? 'Yeni Logo Yükle (Mevcut Logo Değişecek)' : 'Logo Yükle'; ?>
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
                            <div id="logoPreview" class="logo-preview" style="display: none;">
                                <img id="previewImage" src="" alt="Preview">
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning btn-lg">
                                <i class="bi bi-check-circle"></i> Güncelle
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
            const previewImage = document.getElementById('previewImage');
            const file = event.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }
    </script>
</body>
</html>