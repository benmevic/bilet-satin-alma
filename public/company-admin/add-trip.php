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

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $departure_city = trim($_POST['departure_city'] ?? '');
    $destination_city = trim($_POST['destination_city'] ?? '');
    $departure_time_raw = $_POST['departure_time'] ?? '';
    $arrival_time_raw = $_POST['arrival_time'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $capacity = intval($_POST['capacity'] ?? 0);
    
    // Validasyon
    if (empty($departure_city) || empty($destination_city) || empty($departure_time_raw) || empty($arrival_time_raw)) {
        $error = 'Lütfen tüm alanları doldurun!';
    } elseif ($departure_city === $destination_city) {
        $error = 'Kalkış ve varış şehri aynı olamaz!';
    } elseif ($price <= 0) {
        $error = 'Fiyat 0\'dan büyük olmalıdır!';
    } elseif ($capacity <= 0 || $capacity > 60) {
        $error = 'Kapasite 1-60 arasında olmalıdır!';
    } else {
        try {
            // ✅ Tarih formatını düzelt (HTML5 datetime-local → SQLite)
            $departure_time = str_replace('T', ' ', $departure_time_raw);
            if (strlen($departure_time) === 16) {
                $departure_time .= ':00';
            }
            
            $arrival_time = str_replace('T', ' ', $arrival_time_raw);
            if (strlen($arrival_time) === 16) {
                $arrival_time .= ':00';
            }
            
            // Varış zamanı kontrolü
            if (strtotime($arrival_time) <= strtotime($departure_time)) {
                $error = 'Varış zamanı, kalkış zamanından sonra olmalıdır!';
            } else {
                // ✅ Sefer ekle (created_date otomatik)
                $stmt = $db->prepare("
                    INSERT INTO Trips (
                        company_id, 
                        departure_city, 
                        destination_city, 
                        departure_time, 
                        arrival_time, 
                        price, 
                        capacity
                    ) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $company_id,
                    $departure_city,
                    $destination_city,
                    $departure_time,
                    $arrival_time,
                    $price,
                    $capacity
                ]);
                
                if ($result) {
                    $trip_id = $db->lastInsertId();
                    $success = "✅ Sefer başarıyla eklendi! (ID: #$trip_id)";
                    
                    // Formu temizle
                    $_POST = array();
                } else {
                    $error = '❌ Sefer eklenemedi!';
                }
            }
            
        } catch (PDOException $e) {
            error_log('ADD TRIP ERROR: ' . $e->getMessage());
            $error = '❌ Hata: ' . $e->getMessage();
        }
    }
}

// 🇹🇷 Türkiye'nin 81 ili (alfabetik sıralı)
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
    <title>Sefer Ekle - <?php echo htmlspecialchars($company['name']); ?></title>
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
        
        .form-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .btn-admin-primary {
            background: #1976D2;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
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
        
        .required {
            color: red;
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
                <a href="trips.php">
                    <i class="bi bi-bus-front"></i>
                    <span>Seferler</span>
                </a>
            </li>
            <li>
                <a href="add-trip.php" class="active">
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
            <h2 class="mb-0">
                <i class="bi bi-plus-circle text-primary"></i> Yeni Sefer Ekle
            </h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Sefer Ekle</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-md-10 mx-auto">
                
                <!-- Başarı/Hata Mesajları -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <div class="form-card">
                    <form method="POST" action="add-trip.php">
                        
                        <div class="row">
                            
                            <!-- Kalkış Şehri -->
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-geo-alt-fill text-primary"></i> 
                                    Kalkış Şehri <span class="required">*</span>
                                </label>
                                <select class="form-select form-select-lg" name="departure_city" required>
                                    <option value="">Şehir Seçin</option>
                                    <?php foreach ($turkiye_illeri as $il): ?>
                                        <option value="<?php echo $il; ?>" <?php echo (isset($_POST['departure_city']) && $_POST['departure_city'] === $il) ? 'selected' : ''; ?>>
                                            <?php echo $il; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">81 il arasından seçin</small>
                            </div>
                            
                            <!-- Varış Şehri -->
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-flag-fill text-danger"></i> 
                                    Varış Şehri <span class="required">*</span>
                                </label>
                                <select class="form-select form-select-lg" name="destination_city" required>
                                    <option value="">Şehir Seçin</option>
                                    <?php foreach ($turkiye_illeri as $il): ?>
                                        <option value="<?php echo $il; ?>" <?php echo (isset($_POST['destination_city']) && $_POST['destination_city'] === $il) ? 'selected' : ''; ?>>
                                            <?php echo $il; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">81 il arasından seçin</small>
                            </div>
                            
                            <!-- Kalkış Zamanı -->
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-calendar-event text-success"></i> 
                                    Kalkış Zamanı <span class="required">*</span>
                                </label>
                                <input 
                                    type="datetime-local" 
                                    class="form-control form-control-lg" 
                                    name="departure_time" 
                                    value="<?php echo $_POST['departure_time'] ?? ''; ?>"
                                    min="<?php echo date('Y-m-d\TH:i'); ?>"
                                    required
                                >
                                <small class="text-muted">Örnek: <?php echo date('d.m.Y H:i'); ?></small>
                            </div>
                            
                            <!-- Varış Zamanı -->
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-calendar-check text-info"></i> 
                                    Varış Zamanı <span class="required">*</span>
                                </label>
                                <input 
                                    type="datetime-local" 
                                    class="form-control form-control-lg" 
                                    name="arrival_time" 
                                    value="<?php echo $_POST['arrival_time'] ?? ''; ?>"
                                    min="<?php echo date('Y-m-d\TH:i'); ?>"
                                    required
                                >
                                <small class="text-muted">Kalkıştan sonra olmalı</small>
                            </div>
                            
                            <!-- Fiyat -->
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-cash-stack text-warning"></i> 
                                    Fiyat (TL) <span class="required">*</span>
                                </label>
                                <input 
                                    type="number" 
                                    class="form-control form-control-lg" 
                                    name="price" 
                                    placeholder="Örn: 250.00"
                                    step="0.01"
                                    min="1"
                                    value="<?php echo $_POST['price'] ?? ''; ?>"
                                    required
                                >
                            </div>
                            
                            <!-- Kapasite -->
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-people-fill text-secondary"></i> 
                                    Kapasite (Koltuk Sayısı) <span class="required">*</span>
                                </label>
                                <input 
                                    type="number" 
                                    class="form-control form-control-lg" 
                                    name="capacity" 
                                    placeholder="Örn: 45"
                                    min="1"
                                    max="60"
                                    value="<?php echo $_POST['capacity'] ?? '45'; ?>"
                                    required
                                >
                                <small class="text-muted">1-60 arası</small>
                            </div>
                            
                        </div>

                        <!-- Butonlar -->
                        <div class="d-flex gap-3 mt-4">
                            <button type="submit" class="btn btn-admin-primary">
                                <i class="bi bi-check-circle"></i> Sefer Ekle
                            </button>
                            <a href="trips.php" class="btn btn-outline-secondary">
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
        // Varış zamanının kalkıştan sonra olmasını sağla
        document.querySelector('input[name="departure_time"]').addEventListener('change', function() {
            const arrivalInput = document.querySelector('input[name="arrival_time"]');
            arrivalInput.min = this.value;
        });
    </script>
</body>
</html>