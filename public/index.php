<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

$trips = [];
$search_done = false;
$departure_city = '';
$destination_city = '';
$date = '';

// Tüm seferleri göster butonu
if (isset($_GET['show_all'])) {
    try {
        $sql = "
            SELECT 
                t.*,
                bc.name as company_name
            FROM Trips t
            LEFT JOIN Bus_Company bc ON t.company_id = bc.id
            ORDER BY t.departure_time ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $search_done = true;
        
        foreach ($trips as &$trip) {
            $trip['seats_available'] = calculateSeatsAvailable($db, $trip['id'], $trip['capacity']);
        }
    } catch (PDOException $e) {
        error_log('SHOW ALL ERROR: ' . $e->getMessage());
    }
}

// Form ile arama
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $departure_city = trim($_POST['departure_city'] ?? '');
    $destination_city = trim($_POST['destination_city'] ?? '');
    $date = $_POST['date'] ?? '';
    
    $sql = "
        SELECT 
            t.*,
            bc.name as company_name
        FROM Trips t
        LEFT JOIN Bus_Company bc ON t.company_id = bc.id
        WHERE 1=1
    ";
    $params = [];
    
    if (!empty($departure_city)) {
        $sql .= " AND LOWER(t.departure_city) LIKE LOWER(?)";
        $params[] = "%$departure_city%";
    }
    
    if (!empty($destination_city)) {
        $sql .= " AND LOWER(t.destination_city) LIKE LOWER(?)";
        $params[] = "%$destination_city%";
    }
    
    if (!empty($date)) {
        $sql .= " AND date(t.departure_time) = ?";
        $params[] = $date;
    }
    
    $sql .= " ORDER BY t.departure_time ASC";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $search_done = true;
        
        foreach ($trips as &$trip) {
            $trip['seats_available'] = calculateSeatsAvailable($db, $trip['id'], $trip['capacity']);
        }
    } catch (PDOException $e) {
        error_log('SEARCH ERROR: ' . $e->getMessage());
    }
}

// Türkiye'nin tüm illeri
$turkiye_illeri = [
    'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Aksaray', 'Amasya', 'Ankara', 'Antalya', 
    'Ardahan', 'Artvin', 'Aydın', 'Balıkesir', 'Bartın', 'Batman', 'Bayburt', 'Bilecik', 
    'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa', 'Çanakkale', 'Çankırı', 'Çorum', 
    'Denizli', 'Diyarbakır', 'Düzce', 'Edirne', 'Elazığ', 'Erzincan', 'Erzurum', 'Eskişehir', 
    'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay', 'Iğdır', 'Isparta', 'İstanbul', 
    'İzmir', 'Kahramanmaraş', 'Karabük', 'Karaman', 'Kars', 'Kastamonu', 'Kayseri', 'Kilis', 
    'Kırıkkale', 'Kırklareli', 'Kırşehir', 'Kocaeli', 'Konya', 'Kütahya', 'Malatya', 'Manisa', 
    'Mardin', 'Mersin', 'Muğla', 'Muş', 'Nevşehir', 'Niğde', 'Ordu', 'Osmaniye', 
    'Rize', 'Sakarya', 'Samsun', 'Şanlıurfa', 'Siirt', 'Sinop', 'Şırnak', 'Sivas', 
    'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Uşak', 'Van', 'Yalova', 'Yozgat', 'Zonguldak'
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Otobüs Bileti - MERİCBİLET</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-12 text-center">
                    <h1 class="display-4 fw-bold text-white mb-3">
                        <i class="bi bi-geo-alt-fill text-warning"></i>
                        Türkiye ve Sibervatan'ın En Büyük Bilet Satış Platformu
                    </h1>
                    <p class="lead text-white-50 mb-4">
                        Güvenli, hızlı ve kolay otobüs bileti alın. Yüzlerce sefer, tek tıkla!
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="container search-panel-container">
        <div class="card search-panel shadow-lg">
            <div class="card-header bg-primary-custom text-white text-center">
                <h4 class="mb-0">
                    <i class="bi bi-search"></i> Otobüs Bileti Ara
                </h4>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="index.php">
                    <div class="row g-3">
                        <!-- Nereden -->
                        <div class="col-md-5">
                            <label class="form-label fw-bold">
                                <i class="bi bi-pin-map"></i> Nereden
                            </label>
                            <select 
                                class="form-select form-select-lg" 
                                name="departure_city"
                            >
                                <option value="">Şehir Seçin</option>
                                <?php foreach ($turkiye_illeri as $il): ?>
                                    <option value="<?php echo $il; ?>" <?php echo ($departure_city === $il) ? 'selected' : ''; ?>>
                                        <?php echo $il; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-1 d-flex align-items-end justify-content-center">
                            <button 
                                type="button" 
                                class="btn btn-outline-secondary" 
                                onclick="swapCities()"
                                title="Şehirleri değiştir"
                            >
                                <i class="bi bi-arrow-left-right"></i>
                            </button>
                        </div>

                        <!-- Nereye -->
                        <div class="col-md-5">
                            <label class="form-label fw-bold">
                                <i class="bi bi-geo-alt"></i> Nereye
                            </label>
                            <select 
                                class="form-select form-select-lg" 
                                name="destination_city"
                            >
                                <option value="">Şehir Seçin</option>
                                <?php foreach ($turkiye_illeri as $il): ?>
                                    <option value="<?php echo $il; ?>" <?php echo ($destination_city === $il) ? 'selected' : ''; ?>>
                                        <?php echo $il; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-1"></div>

                        <!-- Tarih -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-calendar-event"></i> Tarih
                            </label>
                            <input 
                                type="date" 
                                class="form-control form-control-lg" 
                                name="date" 
                                value="<?php echo htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?>"
                                min="<?php echo date('Y-m-d'); ?>"
                            >
                        </div>

                        <!-- Ara Butonu -->
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary-custom btn-lg w-100">
                                <i class="bi bi-search"></i> Sefer Ara
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="container mt-5">
        <?php if ($search_done): ?>
            <?php if (count($trips) > 0): ?>
                <h4 class="mb-4">
                    <i class="bi bi-list-ul"></i> 
                    <?php echo count($trips); ?> Sefer Bulundu
                </h4>
                
                <?php foreach ($trips as $trip): ?>
                    <div class="card trip-card shadow-sm mb-3">
                        <div class="card-header bg-light">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <div class="mb-1">
                                        <span class="badge bg-primary">
                                            <i class="bi bi-building"></i>
                                            <?php echo htmlspecialchars($trip['company_name'] ?? 'Firma Bilinmiyor', ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                    <h5 class="mb-0">
                                        <i class="bi bi-geo-fill text-success"></i>
                                        <?php echo htmlspecialchars($trip['departure_city'], ENT_QUOTES, 'UTF-8'); ?> 
                                        <i class="bi bi-arrow-right mx-2"></i> 
                                        <i class="bi bi-flag-fill text-danger"></i>
                                        <?php echo htmlspecialchars($trip['destination_city'], ENT_QUOTES, 'UTF-8'); ?>
                                    </h5>
                                </div>
                                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-calendar3"></i> 
                                        <?php echo formatDate($trip['departure_time']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <small class="text-muted d-block">Kalkış</small>
                                        <h4 class="mb-0 text-primary-custom"><?php echo formatTime($trip['departure_time']); ?></h4>
                                    </div>
                                </div>
                                <div class="col-md-2 text-center">
                                    <i class="bi bi-arrow-right-circle-fill text-muted" style="font-size: 2rem;"></i>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <small class="text-muted d-block">Varış</small>
                                        <h4 class="mb-0 text-primary-custom"><?php echo formatTime($trip['arrival_time']); ?></h4>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <small class="text-muted d-block">Kalan Koltuk</small>
                                        <h5 class="mb-0">
                                            <span class="badge <?php echo $trip['seats_available'] > 10 ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo $trip['seats_available']; ?> / <?php echo $trip['capacity']; ?>
                                            </span>
                                        </h5>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <small class="text-muted d-block">Fiyat</small>
                                        <h4 class="mb-0 text-success fw-bold"><?php echo number_format($trip['price'], 2); ?> TL</h4>
                                    </div>
                                </div>
                            </div>
                            <hr class="my-3">
                            <div class="row">
                                <div class="col-12 text-end">
                                    <?php if ($trip['seats_available'] > 0): ?>
                                        <?php 
                                        // Kullanıcı giriş yapmış mı ve ROLE kontrolü
                                        $is_logged_in = !empty($_SESSION['user_id']);
                                        $user_role = $_SESSION['role'] ?? null;
                                        $is_normal_user = ($user_role === 'user' || $user_role === null);
                                        ?>
                                        
                                        <!-- ✅ DETAYLARI GÖR BUTONU (HERKES GÖREBİLİR) -->
                                        <a href="trip-details.php?trip_id=<?php echo $trip['id']; ?>" class="btn btn-outline-info me-2">
                                            <i class="bi bi-info-circle"></i> Detayları Gör
                                        </a>
                                        
                                        <?php if ($is_logged_in && $is_normal_user): ?>
                                            <!-- Normal kullanıcı - Bilet alabilir -->
                                            <a href="buy-ticket.php?trip_id=<?php echo $trip['id']; ?>" class="btn btn-primary-custom">
                                                <i class="bi bi-cart-check-fill"></i> Bilet Al
                                            </a>
                                            
                                        <?php elseif ($is_logged_in && !$is_normal_user): ?>
                                            <!-- Admin/Company - Bilet alamaz -->
                                            <button class="btn btn-outline-secondary" disabled title="Sadece normal kullanıcılar bilet alabilir">
                                                <i class="bi bi-slash-circle"></i> 
                                                <?php 
                                                if ($user_role === 'admin') {
                                                    echo 'Admin Bilet Alamaz';
                                                } elseif ($user_role === 'company') {
                                                    echo 'Firma Bilet Alamaz';
                                                } else {
                                                    echo 'Bilet Alınamaz';
                                                }
                                                ?>
                                            </button>
                                            
                                        <?php else: ?>
                                            <!-- Giriş yapılmamış -->
                                            <a href="login.php" class="btn btn-outline-primary-custom">
                                                <i class="bi bi-box-arrow-in-right"></i> Giriş Yapın
                                            </a>
                                        <?php endif; ?>
                                        
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled>
                                            <i class="bi bi-x-circle"></i> Koltuk Yok
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-exclamation-circle text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                        <h4 class="mt-3">Sefer Bulunamadı</h4>
                        <p class="text-muted">Aradığınız kriterlere uygun sefer bulunamadı.</p>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-bus-front" style="font-size: 4rem; opacity: 0.3;"></i>
                <p class="mt-3 fs-5">Sefer aramak için yukarıdaki formu kullanın</p>
            </div>
        <?php endif; ?>
    </div>

    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container text-center">
            <p class="mb-0">
                <i class="bi bi-bus-front-fill"></i> MERİCBİLET &copy; 2025
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Şehirleri değiştir butonu
        function swapCities() {
            const departure = document.querySelector('select[name="departure_city"]');
            const destination = document.querySelector('select[name="destination_city"]');
            
            const temp = departure.value;
            departure.value = destination.value;
            destination.value = temp;
        }
    </script>
</body>
</html>