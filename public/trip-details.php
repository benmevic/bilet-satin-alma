<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

$trip_id = $_GET['trip_id'] ?? null;

if (!$trip_id) {
    header('Location: index.php');
    exit();
}

// Sefer bilgilerini al
try {
    $stmt = $db->prepare("
        SELECT t.*, bc.name as company_name, bc.logo_path
        FROM Trips t 
        LEFT JOIN Bus_Company bc ON t.company_id = bc.id 
        WHERE t.id = ?
    ");
    $stmt->execute([$trip_id]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$trip) {
        header('Location: index.php');
        exit();
    }
    
    // Dolu koltukları al
    $occupied_seats = getOccupiedSeats($db, $trip_id);
    $seats_available = calculateSeatsAvailable($db, $trip_id, $trip['capacity']);
    
    // Süre hesapla
    $departure = new DateTime($trip['departure_time']);
    $arrival = new DateTime($trip['arrival_time']);
    $duration = $departure->diff($arrival);
    
    // Kalkışa kalan süre
    $now = new DateTime();
    $time_left = $now->diff($departure);
    
} catch (PDOException $e) {
    error_log('TRIP DETAILS ERROR: ' . $e->getMessage());
    header('Location: index.php');
    exit();
}

// Kullanıcı durumu
$is_logged_in = !empty($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? null;
$is_normal_user = ($user_role === 'user' || $user_role === null);
$can_buy = $is_logged_in && $is_normal_user;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sefer Detayları - MERİCBİLET</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .detail-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .feature-icon {
            font-size: 2rem;
            color: #1976D2;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-12 text-center">
                    <h1 class="display-4 fw-bold text-white mb-3">
                        <i class="bi bi-info-circle-fill text-warning"></i>
                        Sefer Detayları
                    </h1>
                    <p class="lead text-white-50 mb-4">
                        Sefer hakkında detaylı bilgiler
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="container search-panel-container">
        
        <!-- Firma ve Rota Bilgisi -->
        <div class="card search-panel shadow-lg mb-4">
            <div class="card-header bg-primary-custom text-white">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h4 class="mb-0">
                            <i class="bi bi-building"></i>
                            <?php echo htmlspecialchars($trip['company_name'] ?? 'Firma Bilinmiyor'); ?>
                        </h4>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="badge bg-warning text-dark fs-6">
                            <i class="bi bi-calendar3"></i> 
                            <?php echo formatDate($trip['departure_time']); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row text-center">
                    <div class="col-md-5">
                        <div class="detail-box">
                            <i class="bi bi-geo-alt-fill feature-icon text-success"></i>
                            <h3 class="mt-2"><?php echo htmlspecialchars($trip['departure_city']); ?></h3>
                            <h4 class="text-primary-custom"><?php echo formatTime($trip['departure_time']); ?></h4>
                            <small class="text-muted">Kalkış</small>
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-center justify-content-center">
                        <div class="text-center">
                            <i class="bi bi-arrow-right-circle-fill" style="font-size: 3rem; color: #1976D2;"></i>
                            <p class="mt-2 mb-0">
                                <strong><?php echo $duration->h; ?> saat <?php echo $duration->i; ?> dk</strong>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="detail-box">
                            <i class="bi bi-flag-fill feature-icon text-danger"></i>
                            <h3 class="mt-2"><?php echo htmlspecialchars($trip['destination_city']); ?></h3>
                            <h4 class="text-primary-custom"><?php echo formatTime($trip['arrival_time']); ?></h4>
                            <small class="text-muted">Varış</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sefer Detayları -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card search-panel shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-cash-stack feature-icon text-success"></i>
                        <h3 class="mt-3 mb-1"><?php echo number_format($trip['price'], 2); ?> TL</h3>
                        <small class="text-muted">Bilet Fiyatı</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card search-panel shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-people-fill feature-icon text-primary"></i>
                        <h3 class="mt-3 mb-1"><?php echo $trip['capacity']; ?></h3>
                        <small class="text-muted">Toplam Koltuk</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card search-panel shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle-fill feature-icon <?php echo $seats_available > 10 ? 'text-success' : 'text-warning'; ?>"></i>
                        <h3 class="mt-3 mb-1"><?php echo $seats_available; ?></h3>
                        <small class="text-muted">Boş Koltuk</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card search-panel shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-clock-fill feature-icon text-info"></i>
                        <h3 class="mt-3 mb-1"><?php echo $duration->h; ?>s <?php echo $duration->i; ?>dk</h3>
                        <small class="text-muted">Yolculuk Süresi</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Özellikler -->
        <div class="card search-panel shadow-lg mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="bi bi-star-fill text-warning"></i> Sefer Özellikleri
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <i class="bi bi-wifi text-primary"></i> Ücretsiz Wi-Fi
                    </div>
                    <div class="col-md-3 mb-3">
                        <i class="bi bi-cup-hot text-primary"></i> İkram
                    </div>
                    <div class="col-md-3 mb-3">
                        <i class="bi bi-plug text-primary"></i> Elektrik Prizi
                    </div>
                    <div class="col-md-3 mb-3">
                        <i class="bi bi-tv text-primary"></i> Koltuk Arkası Ekran
                    </div>
                    <div class="col-md-3 mb-3">
                        <i class="bi bi-droplet text-primary"></i> Su İkramı
                    </div>
                    <div class="col-md-3 mb-3">
                        <i class="bi bi-snow text-primary"></i> Klima
                    </div>
                    <div class="col-md-3 mb-3">
                        <i class="bi bi-volume-up text-primary"></i> Müzik Sistemi
                    </div>
                    <div class="col-md-3 mb-3">
                        <i class="bi bi-shield-check text-primary"></i> Sigorta
                    </div>
                </div>
            </div>
        </div>

        <!-- Satın Al Bölümü -->
        <div class="card search-panel shadow-lg mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-2">
                            <i class="bi bi-ticket-perforated-fill text-warning"></i>
                            Hemen Bilet Alın!
                        </h4>
                        <p class="text-muted mb-0">
                            <?php if ($seats_available > 0): ?>
                                Sadece <strong class="text-danger"><?php echo $seats_available; ?></strong> koltuk kaldı! 
                                Hemen yerini ayırt.
                            <?php else: ?>
                                <span class="text-danger">Tüm koltuklar dolu!</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <?php if ($seats_available > 0): ?>
                            <?php if ($can_buy): ?>
                                <!-- Normal user - Bilet alabilir -->
                                <a href="buy-ticket.php?trip_id=<?php echo $trip['id']; ?>" class="btn btn-primary-custom btn-lg">
                                    <i class="bi bi-cart-check-fill"></i> Bilet Al
                                </a>
                            <?php elseif ($is_logged_in && !$is_normal_user): ?>
                                <!-- Admin/Company - Bilet alamaz -->
                                <button class="btn btn-outline-secondary btn-lg" disabled title="Sadece normal kullanıcılar bilet alabilir">
                                    <i class="bi bi-slash-circle"></i>
                                    <?php 
                                    if ($user_role === 'admin') {
                                        echo 'Admin Bilet Alamaz';
                                    } elseif ($user_role === 'company') {
                                        echo 'Firma Bilet Alamaz';
                                    } else {
                                        echo 'Yetkiniz Yok';
                                    }
                                    ?>
                                </button>
                            <?php else: ?>
                                <!-- Giriş yapılmamış -->
                                <a href="login.php" class="btn btn-outline-primary-custom btn-lg">
                                    <i class="bi bi-box-arrow-in-right"></i> Giriş Yapın
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-lg" disabled>
                                <i class="bi bi-x-circle"></i> Koltuk Yok
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Geri Dön -->
        <div class="text-center mb-4">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Sefer Listesine Dön
            </a>
        </div>

    </div>

    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container text-center">
            <p class="mb-0">
                <i class="bi bi-bus-front-fill"></i> MERİCBİLET &copy; 2025
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>