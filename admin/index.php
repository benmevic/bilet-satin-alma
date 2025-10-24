<?php
session_start();

// Admin kontrolü
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

// İstatistikler
try {
    // Toplam firma sayısı
    $stmt = $db->query("SELECT COUNT(*) FROM Bus_Company");
    $total_companies = $stmt->fetchColumn();
    
    // Toplam kullanıcı sayısı
    $stmt = $db->query("SELECT COUNT(*) FROM User");
    $total_users = $stmt->fetchColumn();
    
    // Toplam sefer sayısı
    $stmt = $db->query("SELECT COUNT(*) FROM Trips");
    $total_trips = $stmt->fetchColumn();
    
    // Toplam bilet sayısı
    $stmt = $db->query("SELECT COUNT(*) FROM Tickets");
    $total_tickets = $stmt->fetchColumn();
    
    // Aktif bilet sayısı
    $stmt = $db->query("SELECT COUNT(*) FROM Tickets WHERE status != 'canceled'");
    $active_tickets = $stmt->fetchColumn();
    
    // Toplam gelir
    $stmt = $db->query("SELECT SUM(total_price) FROM Tickets WHERE status != 'canceled'");
    $total_revenue = $stmt->fetchColumn() ?? 0;
    
    // Firma admin sayısı
    $stmt = $db->query("SELECT COUNT(*) FROM User WHERE role = 'company'");
    $company_admins = $stmt->fetchColumn();
    
    // Normal kullanıcı sayısı
    $stmt = $db->query("SELECT COUNT(*) FROM User WHERE role = 'user' OR role IS NULL");
    $normal_users = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log('ADMIN STATS ERROR: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Süper Admin Panel - MERİCBİLET</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        
        .sidebar-header h4 {
            margin: 0;
            font-weight: bold;
            font-size: 1.3rem;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .sidebar-menu li {
            margin: 5px 0;
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
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 15px;
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
            transition: all 0.3s;
        }
        
        .logout-section a:hover {
            background: rgba(255,82,82,0.8);
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
            <li>
                <a href="index.php" class="active">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="companies.php">
                    <i class="bi bi-building"></i>
                    <span>Firmalar</span>
                </a>
            </li>
            <li>
                <a href="company-admins.php">
                    <i class="bi bi-person-badge"></i>
                    <span>Firma Adminleri</span>
                </a>
            </li>
            <li>
                <a href="global-coupons.php">
                    <i class="bi bi-ticket-perforated"></i>
                    <span>Global Kuponlar</span>
                </a>
            </li>
            <li>
                <a href="../public/index.php">
                    <i class="bi bi-globe"></i>
                    <span>Ana Siteye Dön</span>
                </a>
            </li>
        </ul>
        
        <div class="logout-section">
            <a href="logout.php">
                <i class="bi bi-box-arrow-right me-2"></i>
                <span>Çıkış Yap</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Top Bar -->
        <div class="top-bar">
            <h2 class="mb-0">
                <i class="bi bi-speedometer2 text-danger"></i> Sistem Dashboard
            </h2>
            <small class="text-muted">Sistem genelindeki istatistikler</small>
        </div>

        <!-- İstatistikler -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <i class="bi bi-building stat-icon text-primary"></i>
                    <h3 class="mb-0"><?php echo $total_companies; ?></h3>
                    <p class="text-muted mb-0">Toplam Firma</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <i class="bi bi-people stat-icon text-success"></i>
                    <h3 class="mb-0"><?php echo $total_users; ?></h3>
                    <p class="text-muted mb-0">Toplam Kullanıcı</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <i class="bi bi-bus-front stat-icon text-warning"></i>
                    <h3 class="mb-0"><?php echo $total_trips; ?></h3>
                    <p class="text-muted mb-0">Toplam Sefer</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <i class="bi bi-ticket-perforated stat-icon text-danger"></i>
                    <h3 class="mb-0"><?php echo $total_tickets; ?></h3>
                    <p class="text-muted mb-0">Toplam Bilet</p>
                </div>
            </div>
        </div>

        <!-- İkinci Satır İstatistikler -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <i class="bi bi-check-circle stat-icon text-success"></i>
                    <h3 class="mb-0"><?php echo $active_tickets; ?></h3>
                    <p class="text-muted mb-0">Aktif Bilet</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <i class="bi bi-cash-stack stat-icon" style="color: #28a745;"></i>
                    <h3 class="mb-0"><?php echo number_format($total_revenue, 2); ?> TL</h3>
                    <p class="text-muted mb-0">Toplam Gelir</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <i class="bi bi-person-badge stat-icon text-primary"></i>
                    <h3 class="mb-0"><?php echo $company_admins; ?></h3>
                    <p class="text-muted mb-0">Firma Admini</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <i class="bi bi-person stat-icon text-info"></i>
                    <h3 class="mb-0"><?php echo $normal_users; ?></h3>
                    <p class="text-muted mb-0">Normal Kullanıcı</p>
                </div>
            </div>
        </div>

        <!-- Hızlı Erişim -->
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-building stat-icon text-primary"></i>
                        <h5>Firma Yönetimi</h5>
                        <p class="text-muted">Firmalar oluştur, düzenle, sil</p>
                        <a href="companies.php" class="btn btn-primary">
                            <i class="bi bi-arrow-right-circle"></i> Git
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-person-badge stat-icon text-success"></i>
                        <h5>Firma Adminleri</h5>
                        <p class="text-muted">Firma yöneticileri oluştur</p>
                        <a href="company-admins.php" class="btn btn-success">
                            <i class="bi bi-arrow-right-circle"></i> Git
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-ticket-perforated stat-icon text-danger"></i>
                        <h5>Global Kuponlar</h5>
                        <p class="text-muted">Tüm firmalar için kupon oluştur</p>
                        <a href="global-coupons.php" class="btn btn-danger">
                            <i class="bi bi-arrow-right-circle"></i> Git
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>