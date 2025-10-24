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

// Firma admin silme
if (isset($_GET['delete']) && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    
    try {
        // Kullanıcıyı normal user'a çevir
        $stmt = $db->prepare("UPDATE User SET role = 'user', company_id = NULL WHERE id = ? AND role = 'company'");
        $stmt->execute([$user_id]);
        
        if ($stmt->rowCount() > 0) {
            header('Location: company-admins.php?success=deleted');
        } else {
            header('Location: company-admins.php?error=not_found');
        }
        exit();
        
    } catch (PDOException $e) {
        error_log('DELETE COMPANY ADMIN ERROR: ' . $e->getMessage());
        header('Location: company-admins.php?error=delete_failed');
        exit();
    }
}

// Firma adminlerini al
try {
    $stmt = $db->query("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.balance,
            u.created_at,
            bc.id as company_id,
            bc.name as company_name,
            COUNT(DISTINCT t.id) as trip_count
        FROM User u
        LEFT JOIN Bus_Company bc ON u.company_id = bc.id
        LEFT JOIN Trips t ON bc.id = t.company_id
        WHERE u.role = 'company'
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Toplam sayı
    $total_admins = count($admins);
    
    // Firmaya atanmamış admin sayısı
    $unassigned = array_filter($admins, function($a) { return empty($a['company_id']); });
    $unassigned_count = count($unassigned);
    
} catch (PDOException $e) {
    error_log('COMPANY ADMINS LIST ERROR: ' . $e->getMessage());
    $admins = [];
    $total_admins = 0;
    $unassigned_count = 0;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Adminleri - Süper Admin</title>
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
                    <h2 class="mb-0"><i class="bi bi-person-badge text-danger"></i> Firma Adminleri</h2>
                    <small class="text-muted">Firma yöneticilerini yönet</small>
                </div>
                <a href="add-company-admin.php" class="btn btn-danger">
                    <i class="bi bi-plus-circle"></i> Yeni Firma Admini Ekle
                </a>
            </div>
        </div>

        <!-- İstatistikler -->
        <div class="mb-4">
            <span class="stat-badge bg-primary text-white">
                <i class="bi bi-people"></i> Toplam: <?php echo $total_admins; ?>
            </span>
            <span class="stat-badge bg-warning text-dark">
                <i class="bi bi-exclamation-triangle"></i> Atanmamış: <?php echo $unassigned_count; ?>
            </span>
        </div>

        <!-- Mesajlar -->
        <?php if ($success === 'added'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> Firma admini başarıyla eklendi!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($success === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> Kullanıcı normal user'a dönüştürüldü!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($error === 'not_found'): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> Kullanıcı bulunamadı!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($error === 'delete_failed'): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> Silme işlemi başarısız!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Admin Listesi -->
        <div class="card">
            <div class="card-body">
                <?php if (count($admins) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Ad Soyad</th>
                                    <th>E-posta</th>
                                    <th>Firma</th>
                                    <th>Sefer Sayısı</th>
                                    <th>Bakiye</th>
                                    <th>Kayıt Tarihi</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td><?php echo $admin['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($admin['full_name']); ?></strong>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($admin['email']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($admin['company_name']): ?>
                                                <span class="badge bg-primary">
                                                    <i class="bi bi-building"></i>
                                                    <?php echo htmlspecialchars($admin['company_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-exclamation-triangle"></i> Atanmamış
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $admin['trip_count']; ?> sefer</span>
                                        </td>
                                        <td>
                                            <small><?php echo number_format($admin['balance'], 2); ?> TL</small>
                                        </td>
                                        <td>
                                            <small><?php echo formatDateTime($admin['created_at']); ?></small>
                                        </td>
                                        <td>
                                            <a href="company-admins.php?delete=1&user_id=<?php echo $admin['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Bu kullanıcıyı normal user\'a dönüştürmek istediğinizden emin misiniz?')"
                                               title="Normal User'a Çevir">
                                                <i class="bi bi-person-x"></i> Role Kaldır
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-person-badge" style="font-size: 4rem; opacity: 0.3;"></i>
                        <h4 class="mt-3">Henüz Firma Admini Yok</h4>
                        <p class="text-muted">İlk firma adminini oluşturun!</p>
                        <a href="add-company-admin.php" class="btn btn-danger">
                            <i class="bi bi-plus-circle"></i> Firma Admini Ekle
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>