<?php
session_start();

// Admin kontrolü
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

$coupon_id = $_GET['id'] ?? null;

if (!$coupon_id) {
    header('Location: global-coupons.php');
    exit();
}

$error = '';

// Kupon bilgilerini al
try {
    $stmt = $db->prepare("
        SELECT c.*, COUNT(DISTINCT uc.user_id) as usage_count 
        FROM Coupons c
        LEFT JOIN User_Coupons uc ON c.id = uc.coupon_id
        WHERE c.id = ? AND c.company_id IS NULL
        GROUP BY c.id
    ");
    $stmt->execute([$coupon_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coupon) {
        header('Location: global-coupons.php?error=not_found');
        exit();
    }
    
} catch (PDOException $e) {
    error_log('GET COUPON ERROR: ' . $e->getMessage());
    header('Location: global-coupons.php?error=fetch_failed');
    exit();
}

// Güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discount = floatval($_POST['discount'] ?? 0);
    $usage_limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
    $expire_date = $_POST['expire_date'] ?? '';
    
    if (empty($code)) {
        $error = 'Kupon kodu gereklidir!';
    } elseif ($discount <= 0 || $discount > 100) {
        $error = 'İndirim oranı 1-100 arasında olmalıdır!';
    } elseif (empty($expire_date)) {
        $error = 'Son kullanma tarihi gereklidir!';
    } else {
        try {
            // Başka kupon aynı kodu kullanıyor mu?
            $stmt = $db->prepare("SELECT COUNT(*) FROM Coupons WHERE UPPER(code) = ? AND id != ?");
            $stmt->execute([$code, $coupon_id]);
            $exists = $stmt->fetchColumn();
            
            if ($exists > 0) {
                $error = 'Bu kupon kodu başka bir kupon tarafından kullanılıyor!';
            } else {
                // Güncelle
                $stmt = $db->prepare("
                    UPDATE Coupons 
                    SET code = ?, discount = ?, usage_limit = ?, expire_date = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$code, $discount, $usage_limit, $expire_date, $coupon_id]);
                
                header('Location: global-coupons.php?success=updated');
                exit();
            }
            
        } catch (PDOException $e) {
            error_log('UPDATE COUPON ERROR: ' . $e->getMessage());
            $error = 'Kupon güncellenirken bir hata oluştu!';
        }
    }
    
    // Kupon bilgilerini tekrar çek
    $stmt = $db->prepare("
        SELECT c.*, COUNT(DISTINCT uc.user_id) as usage_count 
        FROM Coupons c
        LEFT JOIN User_Coupons uc ON c.id = uc.coupon_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$coupon_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Durum kontrolü
$now = new DateTime();
$expire_date = new DateTime($coupon['expire_date']);
$is_expired = $expire_date < $now;
$is_used = $coupon['usage_count'] > 0;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kupon Düzenle - Süper Admin</title>
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
            <li><a href="company-admins.php"><i class="bi bi-person-badge"></i> Firma Adminleri</a></li>
            <li><a href="global-coupons.php" class="active"><i class="bi bi-ticket-perforated"></i> Global Kuponlar</a></li>
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
                    <h2 class="mb-0"><i class="bi bi-pencil text-warning"></i> Kupon Düzenle</h2>
                    <small class="text-muted">Global kupon bilgilerini güncelle</small>
                </div>
                <a href="global-coupons.php" class="btn btn-outline-secondary">
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
                    
                    <!-- Kupon Bilgileri -->
                    <div class="alert alert-info mb-4">
                        <div class="row">
                            <div class="col-md-4">
                                <i class="bi bi-info-circle"></i>
                                <strong>Kupon ID:</strong> <?php echo $coupon['id']; ?>
                            </div>
                            <div class="col-md-4">
                                <i class="bi bi-people"></i>
                                <strong>Kullanım:</strong> <?php echo $coupon['usage_count']; ?> kez
                            </div>
                            <div class="col-md-4">
                                <i class="bi bi-calendar"></i>
                                <strong>Oluşturma:</strong> <?php echo formatDate($coupon['created_at']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($is_expired): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>UYARI:</strong> Bu kuponun süresi dolmuş!
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($is_used): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-circle"></i>
                            <strong>DİKKAT:</strong> Bu kupon <?php echo $coupon['usage_count']; ?> kez kullanılmış. Değişiklikler mevcut kullanımları etkilemeyecek.
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="edit-coupon.php?id=<?php echo $coupon_id; ?>">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-ticket-perforated"></i> Kupon Kodu *
                            </label>
                            <input 
                                type="text" 
                                class="form-control form-control-lg" 
                                name="code" 
                                value="<?php echo htmlspecialchars($coupon['code']); ?>"
                                style="text-transform: uppercase;"
                                maxlength="20"
                                required
                                autofocus
                            >
                            <small class="text-muted">Büyük harflerle, benzersiz olmalıdır.</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-percent"></i> İndirim Oranı (%) *
                            </label>
                            <input 
                                type="number" 
                                class="form-control form-control-lg" 
                                name="discount" 
                                value="<?php echo $coupon['discount']; ?>"
                                min="1"
                                max="100"
                                required
                            >
                            <small class="text-muted">1-100 arasında bir değer girin.</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-123"></i> Kullanım Limiti (Opsiyonel)
                            </label>
                            <input 
                                type="number" 
                                class="form-control form-control-lg" 
                                name="usage_limit" 
                                value="<?php echo $coupon['usage_limit'] ?? ''; ?>"
                                placeholder="Boş bırakırsanız sınırsız"
                                min="<?php echo $coupon['usage_count']; ?>"
                            >
                            <?php if ($coupon['usage_count'] > 0): ?>
                                <small class="text-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    Zaten <?php echo $coupon['usage_count']; ?> kez kullanılmış! Minimum bu değer olmalı.
                                </small>
                            <?php else: ?>
                                <small class="text-muted">Boş bırakırsanız sınırsız kullanım olur.</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-calendar-event"></i> Son Kullanma Tarihi *
                            </label>
                            <input 
                                type="date" 
                                class="form-control form-control-lg" 
                                name="expire_date" 
                                value="<?php echo date('Y-m-d', strtotime($coupon['expire_date'])); ?>"
                                min="<?php echo date('Y-m-d'); ?>"
                                required
                            >
                            <?php if ($is_expired): ?>
                                <small class="text-danger">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    Mevcut tarih geçmiş! Yeni bir tarih belirleyin.
                                </small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning btn-lg">
                                <i class="bi bi-check-circle"></i> Kuponu Güncelle
                            </button>
                            <a href="global-coupons.php" class="btn btn-outline-secondary">
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
        // Kupon kodunu otomatik büyük harfe çevir
        document.querySelector('input[name="code"]').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
    </script>
</body>
</html>