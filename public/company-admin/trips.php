<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$company_id = $_SESSION['company_id'];

// Firma bilgisi
$stmt = $db->prepare("SELECT * FROM Bus_Company WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// Sefer silme işlemi
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $trip_id = intval($_GET['delete']);
    
    try {
        // Bu seferin bu firmaya ait olduğunu kontrol et
        $stmt = $db->prepare("SELECT id FROM Trips WHERE id = ? AND company_id = ?");
        $stmt->execute([$trip_id, $company_id]);
        
        if ($stmt->fetch()) {
            // Seferi sil
            $stmt = $db->prepare("DELETE FROM Trips WHERE id = ? AND company_id = ?");
            $stmt->execute([$trip_id, $company_id]);
            
            header('Location: trips.php?success=deleted');
            exit();
        } else {
            header('Location: trips.php?error=unauthorized');
            exit();
        }
    } catch (PDOException $e) {
        error_log('DELETE TRIP ERROR: ' . $e->getMessage());
        header('Location: trips.php?error=delete_failed');
        exit();
    }
}

// Arama ve filtreleme
$search_departure = $_GET['departure'] ?? '';
$search_destination = $_GET['destination'] ?? '';
$search_date = $_GET['date'] ?? '';

// SQL sorgusu
$sql = "SELECT * FROM Trips WHERE company_id = ?";
$params = [$company_id];

if (!empty($search_departure)) {
    $sql .= " AND departure_city LIKE ?";
    $params[] = "%$search_departure%";
}

if (!empty($search_destination)) {
    $sql .= " AND destination_city LIKE ?";
    $params[] = "%$search_destination%";
}

if (!empty($search_date)) {
    $sql .= " AND date(departure_time) = ?";
    $params[] = $search_date;
}

$sql .= " ORDER BY departure_time DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seferler - <?php echo htmlspecialchars($company['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
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
        
        .trip-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .trip-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .trip-route {
            font-size: 1.3rem;
            font-weight: bold;
            color: #1976D2;
        }
        
        .trip-info {
            display: flex;
            gap: 30px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        
        .trip-info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .trip-info-item i {
            color: #1976D2;
        }
        
        .badge-status {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .status-active {
            background: #E8F5E9;
            color: #2E7D32;
        }
        
        .status-past {
            background: #FFEBEE;
            color: #C62828;
        }
    </style>
</head>
<body>
    
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="bi bi-building"></i> Firma Panel</h4>
            <small><?php echo htmlspecialchars($company['name']); ?></small>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="index.php">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="trips.php" class="active">
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
                <a href="../index.php">
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">
                        <i class="bi bi-bus-front text-primary"></i> Seferlerim
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Seferler</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="add-trip.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Yeni Sefer Ekle
                    </a>
                </div>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i>
                <?php 
                    if ($_GET['success'] === 'deleted') echo 'Sefer başarıyla silindi!';
                    if ($_GET['success'] === 'updated') echo 'Sefer başarıyla güncellendi!';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php 
                    if ($_GET['error'] === 'unauthorized') echo 'Bu işlem için yetkiniz yok!';
                    if ($_GET['error'] === 'delete_failed') echo 'Sefer silinirken bir hata oluştu!';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Arama Formu -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <i class="bi bi-search"></i> Sefer Ara
                </h5>
                <form method="GET" action="trips.php">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <input 
                                type="text" 
                                class="form-control" 
                                name="departure" 
                                placeholder="Kalkış Şehri"
                                value="<?php echo htmlspecialchars($search_departure); ?>"
                            >
                        </div>
                        <div class="col-md-3">
                            <input 
                                type="text" 
                                class="form-control" 
                                name="destination" 
                                placeholder="Varış Şehri"
                                value="<?php echo htmlspecialchars($search_destination); ?>"
                            >
                        </div>
                        <div class="col-md-3">
                            <input 
                                type="date" 
                                class="form-control" 
                                name="date" 
                                value="<?php echo htmlspecialchars($search_date); ?>"
                            >
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Ara
                            </button>
                            <a href="trips.php" class="btn btn-outline-secondary w-100 mt-2">
                                <i class="bi bi-x-circle"></i> Temizle
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Seferler Listesi -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>
                        Toplam <strong><?php echo count($trips); ?></strong> sefer bulundu
                    </h5>
                </div>

                <?php if (count($trips) > 0): ?>
                    <?php foreach ($trips as $trip): ?>
                        <?php
                            $is_active = strtotime($trip['departure_time']) > time();
                            $departure_dt = new DateTime($trip['departure_time']);
                            $arrival_dt = new DateTime($trip['arrival_time']);
                            $duration = $departure_dt->diff($arrival_dt);
                        ?>
                        <div class="trip-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="trip-route mb-2">
                                        <i class="bi bi-geo-alt-fill text-success"></i>
                                        <?php echo htmlspecialchars($trip['departure_city']); ?>
                                        <i class="bi bi-arrow-right mx-2"></i>
                                        <i class="bi bi-flag-fill text-danger"></i>
                                        <?php echo htmlspecialchars($trip['destination_city']); ?>
                                    </div>
                                    
                                    <div class="trip-info">
                                        <div class="trip-info-item">
                                            <i class="bi bi-calendar-event"></i>
                                            <span><strong>Kalkış:</strong> <?php echo formatDateTime($trip['departure_time']); ?></span>
                                        </div>
                                        <div class="trip-info-item">
                                            <i class="bi bi-calendar-check"></i>
                                            <span><strong>Varış:</strong> <?php echo formatDateTime($trip['arrival_time']); ?></span>
                                        </div>
                                        <div class="trip-info-item">
                                            <i class="bi bi-clock"></i>
                                            <span><strong>Süre:</strong> <?php echo $duration->h; ?> saat <?php echo $duration->i; ?> dakika</span>
                                        </div>
                                    </div>
                                    
                                    <div class="trip-info">
                                        <div class="trip-info-item">
                                            <i class="bi bi-cash-stack"></i>
                                            <span><strong>Fiyat:</strong> <?php echo number_format($trip['price'], 2); ?> TL</span>
                                        </div>
                                        <div class="trip-info-item">
                                            <i class="bi bi-people"></i>
                                            <span><strong>Kapasite:</strong> <?php echo $trip['capacity']; ?> koltuk</span>
                                        </div>
                                        <div class="trip-info-item">
                                            <span class="badge-status <?php echo $is_active ? 'status-active' : 'status-past'; ?>">
                                                <?php echo $is_active ? '✓ Aktif' : '✗ Geçmiş'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <a href="edit-trip.php?id=<?php echo $trip['id']; ?>" class="btn btn-outline-primary" title="Düzenle">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button 
                                        class="btn btn-outline-danger" 
                                        onclick="confirmDelete(<?php echo $trip['id']; ?>, '<?php echo htmlspecialchars($trip['departure_city']); ?>', '<?php echo htmlspecialchars($trip['destination_city']); ?>')"
                                        title="Sil"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 4rem; opacity: 0.3;"></i>
                        <h4 class="mt-3 text-muted">Sefer Bulunamadı</h4>
                        <p class="text-muted">
                            <?php if (!empty($search_departure) || !empty($search_destination) || !empty($search_date)): ?>
                                Arama kriterlerinize uygun sefer bulunamadı.
                            <?php else: ?>
                                Henüz hiç sefer eklenmemiş.
                            <?php endif; ?>
                        </p>
                        <a href="add-trip.php" class="btn btn-primary mt-3">
                            <i class="bi bi-plus-circle"></i> İlk Seferi Ekle
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(tripId, departure, destination) {
            if (confirm(`"${departure} → ${destination}" seferini silmek istediğinize emin misiniz?\n\nBu işlem geri alınamaz!`)) {
                window.location.href = `trips.php?delete=${tripId}`;
            }
        }
    </script>
</body>
</html>