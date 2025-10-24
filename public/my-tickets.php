<?php
session_start();

// Giriş kontrolü
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

// Kullanıcı bilgilerini güncelle
try {
    $stmt = $db->prepare("SELECT balance FROM User WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['balance'] = floatval($user['balance']);
    }
} catch (PDOException $e) {
    error_log('USER UPDATE ERROR: ' . $e->getMessage());
}

$success = '';
$error = '';

if (isset($_GET['success'])) {
    $saved = $_GET['saved'] ?? 0;
    if ($saved > 0) {
        $success = 'Bilet başarıyla satın alındı! Kupon ile ' . $saved . ' TL tasarruf ettiniz!';
    } else {
        $success = 'Bilet başarıyla satın alındı!';
    }
}

// Bilet iptali
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_ticket'])) {
    $ticket_id = $_POST['ticket_id'] ?? null;
    
    if ($ticket_id) {
        try {
            // Bilet sahibi kontrolü
            $stmt = $db->prepare("
                SELECT t.*, tr.departure_time, t.total_price
                FROM Tickets t
                JOIN Trips tr ON t.trip_id = tr.id
                WHERE t.id = ? AND t.user_id = ?
            ");
            $stmt->execute([$ticket_id, $_SESSION['user_id']]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) {
                $error = 'Bilet bulunamadı!';
            } elseif ($ticket['status'] === 'canceled') {
                $error = 'Bu bilet zaten iptal edilmiş!';
            } else {
                // Kalkış zamanı kontrolü (1 saat)
                $departure_time = strtotime($ticket['departure_time']);
                $now = time();
                $time_diff = $departure_time - $now;
                $hours_left = $time_diff / 3600;
                
                if ($hours_left < 1) {
                    $error = 'Kalkışa 1 saatten az kaldığı için bilet iptal edilemez!';
                } else {
                    // ✅ Bileti iptal et (Transaction ile)
                    $db->beginTransaction();
                    
                    try {
                        // 1. Bilet durumunu güncelle
                        $stmt = $db->prepare("UPDATE Tickets SET status = 'canceled' WHERE id = ?");
                        $stmt->execute([$ticket_id]);
                        
                        // 2. Koltukları serbest bırak (Booked_Seats'ten sil)
                        $stmt = $db->prepare("DELETE FROM Booked_Seats WHERE ticket_id = ?");
                        $stmt->execute([$ticket_id]);
                        
                        // 3. Bakiyeyi iade et
                        $new_balance = $_SESSION['balance'] + $ticket['total_price'];
                        $stmt = $db->prepare("UPDATE User SET balance = ? WHERE id = ?");
                        $stmt->execute([$new_balance, $_SESSION['user_id']]);
                        
                        $_SESSION['balance'] = $new_balance;
                        
                        $db->commit();
                        
                        $success = 'Bilet başarıyla iptal edildi! ' . number_format($ticket['total_price'], 2) . ' TL bakiyenize iade edildi.';
                        
                    } catch (PDOException $e) {
                        $db->rollBack();
                        throw $e;
                    }
                }
            }
        } catch (PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('TICKET CANCEL ERROR: ' . $e->getMessage());
            $error = 'Bilet iptal edilirken bir hata oluştu: ' . $e->getMessage();
        }
    }
}

// Kullanıcının biletlerini al
try {
    $stmt = $db->prepare("
        SELECT 
            t.id as ticket_id,
            t.total_price,
            t.status,
            t.created_at as ticket_date,
            tr.id as trip_id,
            tr.departure_city,
            tr.destination_city,
            tr.departure_time,
            tr.arrival_time,
            tr.price as unit_price
        FROM Tickets t
        JOIN Trips tr ON t.trip_id = tr.id
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Her bilet için koltuk numaralarını al
    foreach ($tickets as &$ticket) {
        $stmt = $db->prepare("SELECT seat_number FROM Booked_Seats WHERE ticket_id = ? ORDER BY seat_number");
        $stmt->execute([$ticket['ticket_id']]);
        $seats = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $ticket['seats'] = $seats;
        $ticket['seat_count'] = count($seats);
        
        // Kalkışa kalan süre
        $departure_time = strtotime($ticket['departure_time']);
        $now = time();
        $time_diff = $departure_time - $now;
        $ticket['hours_left'] = $time_diff / 3600;
        $ticket['can_cancel'] = ($time_diff > 3600 && $ticket['status'] !== 'canceled');
        
        // Bilet durumu
        if ($ticket['status'] === 'canceled') {
            $ticket['status_text'] = 'İptal Edildi';
            $ticket['status_class'] = 'danger';
            $ticket['status_icon'] = 'x-circle-fill';
        } elseif ($departure_time < $now) {
            $ticket['status_text'] = 'Tamamlandı';
            $ticket['status_class'] = 'secondary';
            $ticket['status_icon'] = 'check-circle';
        } else {
            $ticket['status_text'] = 'Aktif';
            $ticket['status_class'] = 'success';
            $ticket['status_icon'] = 'check-circle-fill';
        }
    }
    
    // İstatistikler
    $total_tickets = count($tickets);
    
    $stmt = $db->prepare("SELECT SUM(total_price) FROM Tickets WHERE user_id = ? AND status != 'canceled'");
    $stmt->execute([$_SESSION['user_id']]);
    $total_spent = $stmt->fetchColumn() ?? 0;
    
    $active_tickets = array_filter($tickets, function($t) { 
        return $t['status'] !== 'canceled' && $t['hours_left'] > 0; 
    });
    $active_count = count($active_tickets);
    
} catch (PDOException $e) {
    error_log('MY TICKETS ERROR: ' . $e->getMessage());
    $error = 'Biletler yüklenirken hata oluştu.';
    $tickets = [];
    $total_tickets = 0;
    $total_spent = 0;
    $active_count = 0;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biletlerim - MERİCBİLET</title>
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
                        <i class="bi bi-ticket-perforated-fill text-warning"></i>
                        Biletlerim
                    </h1>
                    <p class="lead text-white-50 mb-4">
                        Satın aldığınız tüm biletler burada
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="container search-panel-container">

        <!-- Hata/Başarı Mesajları -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- İstatistikler -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card search-panel shadow-sm">
                    <div class="card-body text-center p-3">
                        <h3 class="text-primary-custom mb-1"><?php echo $total_tickets; ?></h3>
                        <small class="text-muted">Toplam Bilet</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card search-panel shadow-sm">
                    <div class="card-body text-center p-3">
                        <h3 class="text-success mb-1"><?php echo $active_count; ?></h3>
                        <small class="text-muted">Aktif Bilet</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card search-panel shadow-sm">
                    <div class="card-body text-center p-3">
                        <h3 class="text-danger mb-1"><?php echo number_format($total_spent, 2); ?> TL</h3>
                        <small class="text-muted">Toplam Harcama</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bilet Listesi -->
        <?php if (count($tickets) > 0): ?>
            <div class="row">
                <?php foreach ($tickets as $ticket): ?>
                    <div class="col-12 mb-3">
                        <div class="card trip-card shadow-sm">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-0">
                                            <i class="bi bi-geo-fill"></i>
                                            <?php echo htmlspecialchars($ticket['departure_city']); ?> 
                                            <i class="bi bi-arrow-right"></i> 
                                            <?php echo htmlspecialchars($ticket['destination_city']); ?>
                                        </h5>
                                    </div>
                                    <div class="col-md-6 text-md-end mt-2 mt-md-0">
                                        <span class="badge bg-<?php echo $ticket['status_class']; ?> fs-6">
                                            <i class="bi bi-<?php echo $ticket['status_icon']; ?>"></i>
                                            <?php echo $ticket['status_text']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <small class="text-muted d-block">Kalkış</small>
                                        <h5 class="text-primary-custom mb-0"><?php echo formatTime($ticket['departure_time']); ?></h5>
                                        <small class="text-muted"><?php echo formatDate($ticket['departure_time']); ?></small>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted d-block">Varış</small>
                                        <h5 class="text-primary-custom mb-0"><?php echo formatTime($ticket['arrival_time']); ?></h5>
                                        <small class="text-muted"><?php echo formatDate($ticket['arrival_time']); ?></small>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted d-block">Koltuk Numaraları</small>
                                        <?php if (count($ticket['seats']) > 0): ?>
                                            <h5 class="text-dark mb-0">
                                                <?php echo implode(', ', $ticket['seats']); ?>
                                            </h5>
                                            <small class="text-muted">(<?php echo $ticket['seat_count']; ?> koltuk)</small>
                                        <?php else: ?>
                                            <small class="text-danger">Koltuk bilgisi yok</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted d-block">Toplam Fiyat</small>
                                        <h4 class="text-danger fw-bold mb-0"><?php echo number_format($ticket['total_price'], 2); ?> TL</h4>
                                    </div>
                                    <div class="col-md-3 text-md-end">
                                        <small class="text-muted d-block">Satın Alma</small>
                                        <small class="text-muted"><?php echo formatDateTime($ticket['ticket_date']); ?></small>
                                    </div>
                                </div>

                                <hr class="my-3">

                                <div class="row">
                                    <div class="col-md-8">
                                        <?php if ($ticket['can_cancel']): ?>
                                            <?php 
                                                $hours = floor($ticket['hours_left']);
                                                $minutes = floor(($ticket['hours_left'] - $hours) * 60);
                                            ?>
                                            <small class="text-success">
                                                <i class="bi bi-clock"></i>
                                                İptal edilebilir (Kalkışa <?php echo $hours; ?> saat <?php echo $minutes; ?> dakika kaldı)
                                            </small>
                                        <?php elseif ($ticket['status'] !== 'canceled' && $ticket['hours_left'] > 0 && $ticket['hours_left'] < 1): ?>
                                            <small class="text-warning">
                                                <i class="bi bi-exclamation-triangle"></i>
                                                Kalkışa 1 saatten az kaldı - İptal edilemez
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <!-- PDF İndir -->
                                            <a 
                                                href="ticket-pdf.php?ticket_id=<?php echo $ticket['ticket_id']; ?>" 
                                                class="btn btn-outline-primary-custom"
                                                target="_blank"
                                            >
                                                <i class="bi bi-file-earmark-pdf"></i> PDF
                                            </a>

                                            <!-- İptal Butonu -->
                                            <?php if ($ticket['can_cancel']): ?>
                                                <form method="POST" action="my-tickets.php" style="display: inline;" onsubmit="return confirm('Bu bileti iptal etmek istediğinizden emin misiniz?\n\n✓ <?php echo number_format($ticket['total_price'], 2); ?> TL bakiyenize iade edilecek\n✓ Koltuklar serbest bırakılacak');">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                                    <button type="submit" name="cancel_ticket" class="btn btn-danger">
                                                        <i class="bi bi-x-circle"></i> İptal Et
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-ticket-perforated text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                    <h4 class="mt-3">Henüz Bilet Yok</h4>
                    <p class="text-muted">Bilet satın almak için sefer arayın!</p>
                    <a href="index.php" class="btn btn-primary-custom">
                        <i class="bi bi-search"></i> Sefer Ara
                    </a>
                </div>
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
</body>
</html>