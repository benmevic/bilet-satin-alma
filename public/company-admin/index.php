<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DEBUG: Firma bilgilerini kontrol et
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'company') {
    header('Location: login.php');
    exit();
}

if (empty($_SESSION['company_id'])) {
    header('Location: login.php?error=no_company');
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$company_id = $_SESSION['company_id'];

// Firma bilgilerini al
$stmt = $db->prepare("SELECT * FROM Bus_Company WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// Devamı aynı...
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Session kontrolü
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Giriş kontrolü
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'company') {
    header('Location: login.php');
    exit();
}

if (empty($_SESSION['company_id'])) {
    header('Location: login.php?error=no_company');
    exit();
}

// ✅ DOĞRU YOL: 2 seviye yukarı
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$company_id = $_SESSION['company_id'];

// Firma bilgilerini al
$stmt = $db->prepare("SELECT * FROM Bus_Company WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    session_destroy();
    header('Location: login.php?error=company_not_found');
    exit();
}

// İstatistikler
$stmt = $db->prepare("SELECT COUNT(*) FROM Trips WHERE company_id = ?");
$stmt->execute([$company_id]);
$total_trips = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM Trips WHERE company_id = ? AND date(departure_time) >= date('now')");
$stmt->execute([$company_id]);
$active_trips = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(DISTINCT t.id) FROM Tickets t JOIN Trips tr ON t.trip_id = tr.id WHERE tr.company_id = ?");
$stmt->execute([$company_id]);
$total_tickets = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT SUM(t.total_price) FROM Tickets t JOIN Trips tr ON t.trip_id = tr.id WHERE tr.company_id = ?");
$stmt->execute([$company_id]);
$total_revenue = $stmt->fetchColumn() ?? 0;

// Son eklenen seferler
$stmt = $db->prepare("SELECT * FROM Trips WHERE company_id = ? ORDER BY created_date DESC LIMIT 5");
$stmt->execute([$company_id]);
$recent_trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Admin Panel - <?php echo htmlspecialchars($company['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --admin-primary: #1976D2;
            --admin-secondary: #424242;
            --admin-success: #388E3C;
            --admin-danger: #D32F2F;
            --admin-warning: #F57C00;
        }
        
        body {
            background: #F5F5F5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
            color: white;
            padding: 0;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .company-logo-container {
            width: 100px;
            height: 100px;
            margin: 0 auto 15px;
            background: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .company-logo-container img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }
        
        .company-logo-placeholder {
            font-size: 3rem;
            color: #1976D2;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 15px;
        }
        
        .btn-admin-primary {
            background: var(--admin-primary);
            color: white;
            border: none;
        }
        
        .btn-admin-primary:hover {
            background: #1565C0;
            color: white;
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
            <!-- Firma Logosu -->
            <div class="company-logo-container">
                <?php if (!empty($company['logo_path'])): ?>
                    <?php
                    // ✅ DOĞRU: public/company-admin → yavuzlar_bilet_uygulamasi (2 seviye yukarı)
                    // DB'de: uploads/company-logos/logo.png şeklinde kayıtlı
                    // HTML'de: ../../uploads/company-logos/logo.png olmalı
                    ?>
                    <img src="../../<?php echo htmlspecialchars($company['logo_path']); ?>" 
                         alt="<?php echo htmlspecialchars($company['name']); ?>"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <i class="bi bi-building company-logo-placeholder" style="display: none;"></i>
                <?php else: ?>
                    <i class="bi bi-building company-logo-placeholder"></i>
                <?php endif; ?>
            </div>
            
            <h4><?php echo htmlspecialchars($company['name']); ?></h4>
            <small style="opacity: 0.8;">Firma Yönetim Paneli</small>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="index.php" class="active">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="trips.php">
                    <i class="bi bi-bus-front"></i>
                    <span>Seferler</span>
                </a>
            </li>
            <li>
                <a href="add-trip.php">
                    <i class="bi bi-plus-circle"></i>
                    <span>Sefer Ekle</span>
                </a>
            </li>
            <li>
                <a href="coupons.php">
                    <i class="bi bi-ticket-perforated"></i>
                    <span>İndirim Kuponları</span>
                </a>
            </li>
            <li>
                <a href="../../public/index.php">
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
            <div>
                <h2 class="mb-0">Dashboard</h2>
                <small class="text-muted">Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['full_name']); ?></small>
            </div>
            <div>
                <span class="text-muted">
                    <i class="bi bi-calendar"></i> 
                    <?php echo date('d.m.Y H:i'); ?>
                </span>
            </div>
        </div>

        <!-- İstatistikler -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); color: white;">
                        <i class="bi bi-bus-front-fill"></i>
                    </div>
                    <h3 class="mb-0"><?php echo $total_trips; ?></h3>
                    <p class="text-muted mb-0">Toplam Sefer</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4CAF50 0%, #388E3C 100%); color: white;">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h3 class="mb-0"><?php echo $active_trips; ?></h3>
                    <p class="text-muted mb-0">Aktif Sefer</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); color: white;">
                        <i class="bi bi-ticket-perforated-fill"></i>
                    </div>
                    <h3 class="mb-0"><?php echo $total_tickets; ?></h3>
                    <p class="text-muted mb-0">Satılan Bilet</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%); color: white;">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <h3 class="mb-0"><?php echo number_format($total_revenue, 0); ?> TL</h3>
                    <p class="text-muted mb-0">Toplam Gelir</p>
                </div>
            </div>
        </div>

        <!-- Hızlı Eylemler -->
        <div class="row g-4 mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-lightning-fill text-warning"></i> Hızlı Eylemler
                        </h5>
                        <div class="d-flex gap-3">
                            <a href="add-trip.php" class="btn btn-admin-primary">
                                <i class="bi bi-plus-circle"></i> Yeni Sefer Ekle
                            </a>
                            <a href="trips.php" class="btn btn-outline-primary">
                                <i class="bi bi-list-ul"></i> Tüm Seferleri Görüntüle
                            </a>
                            <a href="coupons.php" class="btn btn-outline-secondary">
                                <i class="bi bi-ticket-perforated"></i> İndirim Kuponları
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Son Eklenen Seferler -->
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history"></i> Son Eklenen Seferler
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($recent_trips) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Güzergah</th>
                                            <th>Kalkış</th>
                                            <th>Varış</th>
                                            <th>Fiyat</th>
                                            <th>Kapasite</th>
                                            <th>İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_trips as $trip): ?>
                                            <tr>
                                                <td>#<?php echo $trip['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($trip['departure_city']); ?></strong>
                                                    <i class="bi bi-arrow-right text-muted"></i>
                                                    <strong><?php echo htmlspecialchars($trip['destination_city']); ?></strong>
                                                </td>
                                                <td><?php echo formatDateTime($trip['departure_time']); ?></td>
                                                <td><?php echo formatDateTime($trip['arrival_time']); ?></td>
                                                <td><?php echo number_format($trip['price'], 2); ?> TL</td>
                                                <td><?php echo $trip['capacity']; ?> koltuk</td>
                                                <td>
                                                    <a href="edit-trip.php?id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p class="text-muted mt-3">Henüz sefer eklenmemiş</p>
                                <a href="add-trip.php" class="btn btn-admin-primary">
                                    <i class="bi bi-plus-circle"></i> İlk Seferi Ekle
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>