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

// Firma silme
if (isset($_GET['delete']) && isset($_GET['company_id'])) {
    $company_id = $_GET['company_id'];
    
    try {
        $db->beginTransaction();
        
        // Firmaya ait seferleri kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) FROM Trips WHERE company_id = ?");
        $stmt->execute([$company_id]);
        $trip_count = $stmt->fetchColumn();
        
        if ($trip_count > 0) {
            header('Location: companies.php?error=has_trips');
            exit();
        }
        
        // Firma logosunu sil
        $stmt = $db->prepare("SELECT logo_path FROM Bus_Company WHERE id = ?");
        $stmt->execute([$company_id]);
        $logo_path = $stmt->fetchColumn();
        
        if ($logo_path && file_exists('../' . $logo_path)) {
            unlink('../' . $logo_path);
        }
        
        // Firmayı sil
        $stmt = $db->prepare("DELETE FROM Bus_Company WHERE id = ?");
        $stmt->execute([$company_id]);
        
        // Firma adminlerini normal user yap
        $stmt = $db->prepare("UPDATE User SET role = 'user', company_id = NULL WHERE company_id = ?");
        $stmt->execute([$company_id]);
        
        $db->commit();
        
        header('Location: companies.php?success=deleted');
        exit();
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('DELETE COMPANY ERROR: ' . $e->getMessage());
        header('Location: companies.php?error=delete_failed');
        exit();
    }
}

// Firmaları al
try {
    $stmt = $db->query("
        SELECT 
            bc.*,
            COUNT(DISTINCT t.id) as trip_count,
            COUNT(DISTINCT u.id) as admin_count
        FROM Bus_Company bc
        LEFT JOIN Trips t ON bc.id = t.company_id
        LEFT JOIN User u ON bc.id = u.company_id AND u.role = 'company'
        GROUP BY bc.id
        ORDER BY bc.created_at DESC
    ");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('COMPANIES LIST ERROR: ' . $e->getMessage());
    $companies = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firmalar - Süper Admin</title>
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
        
        .company-logo {
            max-width: 60px;
            max-height: 60px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            object-fit: contain;
        }
        
        .no-logo {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
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
                    <h2 class="mb-0"><i class="bi bi-building text-danger"></i> Firmalar</h2>
                    <small class="text-muted">Tüm otobüs firmaları</small>
                </div>
                <a href="add-company.php" class="btn btn-danger">
                    <i class="bi bi-plus-circle"></i> Yeni Firma Ekle
                </a>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php if ($success === 'added'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> Firma başarıyla eklendi!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($success === 'updated'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> Firma başarıyla güncellendi!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($success === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> Firma başarıyla silindi!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($error === 'has_trips'): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> Bu firmaya ait seferler var! Önce seferleri silin.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($error === 'delete_failed'): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> Firma silinirken hata oluştu!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Firma Listesi -->
        <div class="card">
            <div class="card-body">
                <?php if (count($companies) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Logo</th>
                                    <th>Firma Adı</th>
                                    <th>Sefer Sayısı</th>
                                    <th>Admin Sayısı</th>
                                    <th>Oluşturulma</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($companies as $company): ?>
                                    <tr>
                                        <td><?php echo $company['id']; ?></td>
                                        <td>
                                            <?php if ($company['logo_path']): ?>
                                                <img src="../<?php echo htmlspecialchars($company['logo_path']); ?>" 
                                                     alt="<?php echo htmlspecialchars($company['name']); ?>" 
                                                     class="company-logo">
                                            <?php else: ?>
                                                <div class="no-logo">
                                                    <i class="bi bi-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($company['name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $company['trip_count']; ?> sefer</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $company['admin_count']; ?> admin</span>
                                        </td>
                                        <td>
                                            <small><?php echo formatDateTime($company['created_at']); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="edit-company.php?id=<?php echo $company['id']; ?>" 
                                                   class="btn btn-sm btn-warning" 
                                                   title="Düzenle">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="companies.php?delete=1&company_id=<?php echo $company['id']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Bu firmayı silmek istediğinizden emin misiniz?\n\n⚠️ Firma adminleri normal kullanıcıya dönüştürülecek!\n⚠️ Logo silinecek!')"
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
                        <i class="bi bi-building" style="font-size: 4rem; opacity: 0.3;"></i>
                        <h4 class="mt-3">Henüz Firma Yok</h4>
                        <p class="text-muted">İlk firmayı oluşturun!</p>
                        <a href="add-company.php" class="btn btn-danger">
                            <i class="bi bi-plus-circle"></i> Firma Ekle
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>