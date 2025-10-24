<?php
session_start();

// Admin kontrolü
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Kupon silme
if (isset($_GET['delete']) && isset($_GET['coupon_id'])) {
    $coupon_id = $_GET['coupon_id'];
    
    try {
        $db->beginTransaction();
        
        // Kullanım kayıtlarını kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) FROM User_Coupons WHERE coupon_id = ?");
        $stmt->execute([$coupon_id]);
        $usage_count = $stmt->fetchColumn();
        
        if ($usage_count > 0) {
            header('Location: global-coupons.php?error=has_usage');
            exit();
        }
        
        // Kuponu sil
        $stmt = $db->prepare("DELETE FROM Coupons WHERE id = ? AND company_id IS NULL");
        $stmt->execute([$coupon_id]);
        
        $db->commit();
        
        header('Location: global-coupons.php?success=deleted');
        exit();
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('DELETE COUPON ERROR: ' . $e->getMessage());
        header('Location: global-coupons.php?error=delete_failed');
        exit();
    }
}

// Global kuponları al (company_id IS NULL)
try {
    $stmt = $db->query("
        SELECT 
            c.*,
            COUNT(DISTINCT uc.user_id) as usage_count
        FROM Coupons c
        LEFT JOIN User_Coupons uc ON c.id = uc.coupon_id
        WHERE c.company_id IS NULL
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kupon durumlarını belirle
    foreach ($coupons as &$coupon) {
        $now = new DateTime();
        $expire_date = new DateTime($coupon['expire_date']);
        
        // Süresi dolmuş mu?
        if ($expire_date < $now) {
            $coupon['status'] = 'expired';
            $coupon['status_text'] = 'Süresi Doldu';
            $coupon['status_class'] = 'danger';
        }
        // Kullanım limiti dolmuş mu?
        elseif ($coupon['usage_limit'] && $coupon['usage_count'] >= $coupon['usage_limit']) {
            $coupon['status'] = 'limit_reached';
            $coupon['status_text'] = 'Limit Doldu';
            $coupon['status_class'] = 'warning';
        }
        // Aktif
        else {
            $coupon['status'] = 'active';
            $coupon['status_text'] = 'Aktif';
            $coupon['status_class'] = 'success';
        }
        
        // Kalan gün hesapla
        $diff = $now->diff($expire_date);
        $coupon['days_left'] = $diff->invert ? 0 : $diff->days;
    }
    
    // İstatistikler
    $total_coupons = count($coupons);
    $active_coupons = count(array_filter($coupons, function($c) { return $c['status'] === 'active'; }));
    $expired_coupons = count(array_filter($coupons, function($c) { return $c['status'] === 'expired'; }));
    
} catch (PDOException $e) {
    error_log('GLOBAL COUPONS LIST ERROR: ' . $e->getMessage());
    $coupons = [];
    $total_coupons = 0;
    $active_coupons = 0;
    $expired_coupons = 0;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Kuponlar - Süper Admin</title>
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
        
        .stat-badge {
            padding: 10px 20px;
            border-radius: 10px;
            display: inline-block;
            margin-right: 10px;
        }
        
        .coupon-code {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 1.1rem;
            color: #d32f2f;
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
                    <h2 class="mb-0"><i class="bi bi-ticket-perforated text-danger"></i> Global Kuponlar</h2>
                    <small class="text-muted">Tüm firmalar için geçerli kuponlar</small>
                </div>
                <a href="add-coupon.php" class="btn btn-danger">
                    <i class="bi bi-plus-circle"></i> Yeni Kupon Ekle
                </a>
            </div>
        </div>

        <!-- İstatistikler -->
        <div class="mb-4">
            <span class="stat-badge bg-primary text-white">
                <i class="bi bi-ticket"></i> Toplam: <?php echo $total_coupons; ?>
            </span>
            <span class="stat-badge bg-success text-white">
                <i class="bi bi-check-circle"></i> Aktif: <?php echo $active_coupons; ?>
            </span>
            <span class="stat-badge bg-danger text-white">
                <i class="bi bi-x-circle"></i> Süresi Dolmuş: <?php echo $expired_coupons; ?>
            </span>
        </div>

        <!-- Mesajlar -->
        <?php if ($success === 'added'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> Kupon başarıyla eklendi!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($success === 'updated'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> Kupon başarıyla güncellendi!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($success === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> Kupon başarıyla silindi!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($error === 'has_usage'): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> Bu kupon kullanılmış! Silinemez.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($error === 'delete_failed'): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> Kupon silinirken hata oluştu!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Kupon Listesi -->
        <div class="card">
            <div class="card-body">
                <?php if (count($coupons) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Kupon Kodu</th>
                                    <th>İndirim</th>
                                    <th>Kullanım</th>
                                    <th>Son Kullanma</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($coupons as $coupon): ?>
                                    <tr>
                                        <td><?php echo $coupon['id']; ?></td>
                                        <td>
                                            <span class="coupon-code"><?php echo htmlspecialchars($coupon['code']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info text-white">
                                                <?php echo number_format($coupon['discount'], 0); ?>% İndirim
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($coupon['usage_limit']): ?>
                                                <span class="badge bg-secondary">
                                                    <?php echo $coupon['usage_count']; ?> / <?php echo $coupon['usage_limit']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    <?php echo $coupon['usage_count']; ?> / Sınırsız
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo formatDate($coupon['expire_date']); ?>
                                                <br>
                                                <?php if ($coupon['days_left'] > 0): ?>
                                                    <span class="text-muted">(<?php echo $coupon['days_left']; ?> gün kaldı)</span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $coupon['status_class']; ?>">
                                                <?php echo $coupon['status_text']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="edit-coupon.php?id=<?php echo $coupon['id']; ?>" 
                                                   class="btn btn-sm btn-warning" 
                                                   title="Düzenle">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="global-coupons.php?delete=1&coupon_id=<?php echo $coupon['id']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Bu kuponu silmek istediğinizden emin misiniz?')"
                                                   title="Sil">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-ticket-perforated" style="font-size: 4rem; opacity: 0.3;"></i>
                        <h4 class="mt-3">Henüz Global Kupon Yok</h4>
                        <p class="text-muted">İlk global kuponu oluşturun!</p>
                        <a href="add-coupon.php" class="btn btn-danger">
                            <i class="bi bi-plus-circle"></i> Kupon Ekle
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>