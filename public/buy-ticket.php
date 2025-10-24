<?php
session_start();

// Giriş kontrolü
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

// Kullanıcı bilgilerini veritabanından al
try {
    $stmt = $db->prepare("SELECT id, full_name, email, balance, role FROM User WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header('Location: login.php?error=user_not_found');
        exit();
    }
    
    // Session'ı güncelle
    $_SESSION['balance'] = floatval($user['balance'] ?? 0);
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    
} catch (PDOException $e) {
    error_log('USER FETCH ERROR: ' . $e->getMessage());
    $_SESSION['balance'] = 0;
}

$trip_id = $_GET['trip_id'] ?? null;
$error = '';
$success = '';
$selected_seats = [];

if (!$trip_id) {
    header('Location: index.php');
    exit();
}

// Sefer bilgilerini al
try {
    $stmt = $db->prepare("
        SELECT t.*, bc.name as company_name 
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
    
} catch (PDOException $e) {
    error_log('TRIP FETCH ERROR: ' . $e->getMessage());
    header('Location: index.php');
    exit();
}

// KUPON AJAX KONTROLÜ
if (isset($_POST['check_coupon']) && isset($_POST['coupon_code'])) {
    header('Content-Type: application/json');
    
    $code = strtoupper(trim($_POST['coupon_code']));
    $user_id = $_SESSION['user_id'];
    $company_id = $trip['company_id'];
    
    try {
        // Kupon var mı?
        $stmt = $db->prepare("
            SELECT * FROM Coupons 
            WHERE UPPER(code) = ? 
            AND (company_id = ? OR company_id IS NULL)
            AND expire_date > datetime('now', 'localtime')
        ");
        $stmt->execute([$code, $company_id]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$coupon) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz veya süresi dolmuş kupon!']);
            exit();
        }
        
        // Kullanıcı daha önce kullanmış mı?
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM User_Coupons 
            WHERE user_id = ? AND coupon_id = ?
        ");
        $stmt->execute([$user_id, $coupon['id']]);
        $already_used = $stmt->fetchColumn();
        
        if ($already_used > 0) {
            echo json_encode(['success' => false, 'message' => 'Bu kuponu daha önce kullandınız!']);
            exit();
        }
        
        // Kullanım limiti kontrolü
        if ($coupon['usage_limit'] > 0) {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM User_Coupons 
                WHERE coupon_id = ?
            ");
            $stmt->execute([$coupon['id']]);
            $usage_count = $stmt->fetchColumn();
            
            if ($usage_count >= $coupon['usage_limit']) {
                echo json_encode(['success' => false, 'message' => 'Kupon kullanım limiti dolmuş!']);
                exit();
            }
        }
        
        // Kupon geçerli
        echo json_encode([
            'success' => true, 
            'message' => '✅ Kupon uygulandı!',
            'discount' => $coupon['discount'],
            'coupon_id' => $coupon['id'],
            'code' => $coupon['code']
        ]);
        exit();
        
    } catch (PDOException $e) {
        error_log('COUPON CHECK ERROR: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Kupon kontrol hatası!']);
        exit();
    }
}

// Koltuk seçimi ve satın alma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seats'])) {
    $selected_seats = $_POST['seats'] ?? [];
    $applied_coupon_id = $_POST['coupon_id'] ?? null;
    $applied_discount = floatval($_POST['coupon_discount'] ?? 0);
    
    if (empty($selected_seats)) {
        $error = 'Lütfen en az bir koltuk seçin!';
    } else {
        // Koltuklar müsait mi kontrol et
        $is_available = true;
        foreach ($selected_seats as $seat) {
            if (in_array($seat, $occupied_seats)) {
                $error = "Koltuk $seat zaten dolu!";
                $is_available = false;
                break;
            }
        }
        
        if ($is_available) {
            // Fiyat hesaplama
            $base_price = count($selected_seats) * $trip['price'];
            $discount_amount = 0;
            
            if ($applied_coupon_id && $applied_discount > 0) {
                $discount_amount = $base_price * ($applied_discount / 100);
            }
            
            $total_price = $base_price - $discount_amount;
            
            // Bakiye kontrolü
            if ($_SESSION['balance'] < $total_price) {
                $error = 'Yetersiz bakiye! Bakiyeniz: ' . number_format($_SESSION['balance'], 2) . ' TL, Gerekli: ' . number_format($total_price, 2) . ' TL';
            } else {
                // Satın alma işlemi
                try {
                    $db->beginTransaction();
                    
                    // Bilet oluştur
                    $stmt = $db->prepare("
                        INSERT INTO Tickets (trip_id, user_id, status, total_price, created_at) 
                        VALUES (?, ?, 'active', ?, datetime('now', 'localtime'))
                    ");
                    $stmt->execute([$trip_id, $_SESSION['user_id'], $total_price]);
                    $ticket_id = $db->lastInsertId();
                    
                    // Koltukları rezerve et
                    $stmt = $db->prepare("
                        INSERT INTO Booked_Seats (ticket_id, seat_number, created_at) 
                        VALUES (?, ?, datetime('now', 'localtime'))
                    ");
                    foreach ($selected_seats as $seat) {
                        $stmt->execute([$ticket_id, $seat]);
                    }
                    
                    // Kupon kullanımını kaydet
                    if ($applied_coupon_id) {
                        $stmt = $db->prepare("
                            INSERT INTO User_Coupons (coupon_id, user_id, created_at) 
                            VALUES (?, ?, datetime('now', 'localtime'))
                        ");
                        $stmt->execute([$applied_coupon_id, $_SESSION['user_id']]);
                    }
                    
                    // Bakiyeyi güncelle
                    $new_balance = $_SESSION['balance'] - $total_price;
                    $stmt = $db->prepare("UPDATE User SET balance = ? WHERE id = ?");
                    $stmt->execute([$new_balance, $_SESSION['user_id']]);
                    
                    $_SESSION['balance'] = $new_balance;
                    
                    $db->commit();
                    
                    // Başarılı
                    $saved_param = $discount_amount > 0 ? '&saved=' . number_format($discount_amount, 2) : '';
                    header('Location: my-tickets.php?success=1' . $saved_param);
                    exit();
                    
                } catch (PDOException $e) {
                    $db->rollBack();
                    error_log('TICKET PURCHASE ERROR: ' . $e->getMessage());
                    $error = 'Bilet alırken bir hata oluştu. Lütfen tekrar deneyin.';
                }
            }
        }
    }
}

// Koltuk düzeni oluştur
$capacity = (int)$trip['capacity'];
$seat_layout = [];
$rows = ceil($capacity / 4);

for ($row = 1; $row <= $rows; $row++) {
    $row_seats = [];
    
    // Sol taraf (2 koltuk)
    for ($i = 0; $i < 2; $i++) {
        $seat_num = ($row - 1) * 4 + $i + 1;
        if ($seat_num <= $capacity) {
            $row_seats[] = $seat_num;
        }
    }
    
    // Koridor
    $row_seats[] = null;
    
    // Sağ taraf (2 koltuk)
    for ($i = 2; $i < 4; $i++) {
        $seat_num = ($row - 1) * 4 + $i + 1;
        if ($seat_num <= $capacity) {
            $row_seats[] = $seat_num;
        }
    }
    
    $seat_layout[] = $row_seats;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilet Satın Al - MERİCBİLET</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .seat-map {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-top: 20px;
        }
        .seat-row {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .seat {
            width: 50px;
            height: 50px;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: #4CAF50;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 13px;
            transition: all 0.2s;
        }
        .seat:hover:not(.occupied):not(.aisle) {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .seat.occupied {
            background: #f44336;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .seat.selected {
            background: #2196F3;
            transform: scale(1.1);
            box-shadow: 0 5px 20px rgba(33, 150, 243, 0.5);
        }
        .seat.aisle {
            background: transparent;
            border: none;
            cursor: default;
            width: 20px;
        }
        .seat input[type="checkbox"] {
            display: none;
        }
        .legend {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .legend-box {
            width: 30px;
            height: 30px;
            border-radius: 5px;
        }
        .coupon-section {
            background: linear-gradient(135deg, #FFF9C4 0%, #FFF59D 100%);
            border: 2px dashed #F57F17;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .coupon-applied {
            background: linear-gradient(135deg, #C8E6C9 0%, #A5D6A7 100%);
            border-color: #388E3C;
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
                        <i class="bi bi-ticket-perforated-fill text-warning"></i>
                        Bilet Satın Al
                    </h1>
                    <p class="lead text-white-50 mb-4">
                        Koltuk seçin ve güvenle satın alın
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="container search-panel-container">
        
        <!-- Sefer Bilgileri -->
        <div class="card search-panel shadow-lg mb-4">
            <div class="card-header bg-primary-custom text-white text-center">
                <h4 class="mb-0">
                    <i class="bi bi-bus-front-fill"></i> Sefer Bilgileri
                </h4>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <h5>
                            <span class="badge bg-primary me-2">
                                <i class="bi bi-building"></i>
                                <?php echo htmlspecialchars($trip['company_name'] ?? 'N/A'); ?>
                            </span>
                        </h5>
                        <h5>
                            <i class="bi bi-geo-fill text-primary-custom"></i>
                            <?php echo htmlspecialchars($trip['departure_city']); ?> 
                            <i class="bi bi-arrow-right"></i> 
                            <?php echo htmlspecialchars($trip['destination_city']); ?>
                        </h5>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="badge bg-light text-dark fs-6">
                            <i class="bi bi-calendar3"></i> 
                            <?php echo formatDate($trip['departure_time']); ?>
                        </span>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Kalkış</small>
                        <h4 class="text-primary-custom"><?php echo formatTime($trip['departure_time']); ?></h4>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Varış</small>
                        <h4 class="text-primary-custom"><?php echo formatTime($trip['arrival_time']); ?></h4>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Kalan Koltuk</small>
                        <h4 class="text-success"><?php echo $seats_available; ?> / <?php echo $trip['capacity']; ?></h4>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Bilet Fiyatı</small>
                        <h4 class="text-danger fw-bold"><?php echo number_format($trip['price'], 2); ?> TL</h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hata Mesajı -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Kupon Bölümü -->
        <div class="coupon-section" id="couponSection">
            <h5 class="mb-3">
                <i class="bi bi-ticket-perforated"></i> İndirim Kuponu Var mı?
            </h5>
            <div class="row g-2">
                <div class="col-md-8">
                    <input 
                        type="text" 
                        class="form-control form-control-lg" 
                        id="couponInput"
                        placeholder="Kupon kodunu girin (örn: YILBASI2025)"
                        style="text-transform: uppercase;"
                    >
                </div>
                <div class="col-md-4">
                    <button 
                        type="button" 
                        class="btn btn-warning btn-lg w-100" 
                        onclick="checkCoupon()"
                        id="checkCouponBtn"
                    >
                        <i class="bi bi-check-circle"></i> Kupon Uygula
                    </button>
                </div>
            </div>
            <div id="couponMessage"></div>
        </div>

        <!-- Koltuk Seçimi -->
        <div class="card search-panel shadow-lg mb-4">
            <div class="card-header bg-primary-custom text-white text-center">
                <h4 class="mb-0">
                    <i class="bi bi-grid-3x3-gap-fill"></i> Koltuk Seçimi (<?php echo $capacity; ?> Koltuk)
                </h4>
            </div>
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h6 class="text-primary-custom">
                        <i class="bi bi-arrow-up-circle-fill"></i> Otobüs Ön Taraf
                    </h6>
                </div>

                <form method="POST" action="buy-ticket.php?trip_id=<?php echo $trip_id; ?>" id="seatForm">
                    <!-- Hidden inputs -->
                    <input type="hidden" name="coupon_id" id="couponIdInput" value="">
                    <input type="hidden" name="coupon_code" id="couponCodeInput" value="">
                    <input type="hidden" name="coupon_discount" id="couponDiscountInput" value="0">
                    
                    <div class="seat-map">
                        <?php foreach ($seat_layout as $row): ?>
                            <div class="seat-row">
                                <?php foreach ($row as $seat_num): ?>
                                    <?php if ($seat_num === null): ?>
                                        <div class="seat aisle"></div>
                                    <?php else: ?>
                                        <?php $is_occupied = in_array($seat_num, $occupied_seats); ?>
                                        <?php $is_selected = in_array($seat_num, $selected_seats); ?>
                                        <label class="seat <?php echo $is_occupied ? 'occupied' : ''; ?> <?php echo $is_selected ? 'selected' : ''; ?>">
                                            <input 
                                                type="checkbox" 
                                                name="seats[]" 
                                                value="<?php echo $seat_num; ?>"
                                                <?php echo $is_occupied ? 'disabled' : ''; ?>
                                                <?php echo $is_selected ? 'checked' : ''; ?>
                                                onchange="this.parentElement.classList.toggle('selected', this.checked); updateTotal();"
                                            >
                                            <?php echo $seat_num; ?>
                                        </label>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-box" style="background: #4CAF50;"></div>
                            <span>Boş</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-box" style="background: #2196F3;"></div>
                            <span>Seçili</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-box" style="background: #f44336;"></div>
                            <span>Dolu</span>
                        </div>
                    </div>

                    <!-- Ödeme Özeti -->
                    <div class="alert alert-info mt-4">
                        <div class="row">
                            <div class="col-md-6">
                                <strong><i class="bi bi-check2-square"></i> Seçilen Koltuk:</strong> <span id="seatCount">0</span> adet
                            </div>
                            <div class="col-md-6 text-md-end">
                                <strong><i class="bi bi-cash-stack"></i> Ara Toplam:</strong> <span id="basePrice" class="fw-bold">0.00</span> TL
                            </div>
                        </div>
                        <div class="row mt-2" id="discountRow" style="display: none;">
                            <div class="col-md-6">
                                <strong class="text-success"><i class="bi bi-tag-fill"></i> İndirim:</strong>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <span class="text-success fw-bold">-<span id="discountAmount">0.00</span> TL (<span id="discountPercent">0</span>%)</span>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-md-6">
                                <strong class="fs-5"><i class="bi bi-wallet2"></i> TOPLAM TUTAR:</strong>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <span id="totalPrice" class="text-danger fw-bold fs-4">0.00</span> TL
                            </div>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-8">
                            <button type="submit" class="btn btn-primary-custom btn-lg w-100">
                                <i class="bi bi-cart-check-fill"></i> Satın Al (Bakiyeniz: <?php echo number_format($_SESSION['balance'], 2); ?> TL)
                            </button>
                        </div>
                        <div class="col-md-4">
                            <a href="index.php" class="btn btn-outline-secondary btn-lg w-100">
                                <i class="bi bi-arrow-left"></i> Geri Dön
                            </a>
                        </div>
                    </div>
                </form>
            </div>
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
    <script>
        const pricePerSeat = <?php echo $trip['price']; ?>;
        let appliedDiscount = 0;
        let appliedCouponId = null;
        let appliedCouponCode = '';
        
        function checkCoupon() {
            const couponInput = document.getElementById('couponInput');
            const code = couponInput.value.trim().toUpperCase();
            
            if (!code) {
                showCouponMessage('Lütfen kupon kodu girin!', 'danger');
                return;
            }
            
            const btn = document.getElementById('checkCouponBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Kontrol ediliyor...';
            
            fetch('buy-ticket.php?trip_id=<?php echo $trip_id; ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'check_coupon=1&coupon_code=' + encodeURIComponent(code)
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle"></i> Kupon Uygula';
                
                if (data.success) {
                    appliedDiscount = parseFloat(data.discount);
                    appliedCouponId = data.coupon_id;
                    appliedCouponCode = data.code;
                    
                    document.getElementById('couponIdInput').value = appliedCouponId;
                    document.getElementById('couponCodeInput').value = appliedCouponCode;
                    document.getElementById('couponDiscountInput').value = appliedDiscount;
                    
                    showCouponMessage(data.message + ' (' + appliedDiscount + '% indirim)', 'success');
                    
                    document.getElementById('couponSection').classList.add('coupon-applied');
                    couponInput.disabled = true;
                    btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Kupon Uygulandı';
                    btn.classList.remove('btn-warning');
                    btn.classList.add('btn-success');
                    
                    updateTotal();
                } else {
                    showCouponMessage(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle"></i> Kupon Uygula';
                showCouponMessage('Bir hata oluştu!', 'danger');
            });
        }
        
        function showCouponMessage(message, type) {
            const msgDiv = document.getElementById('couponMessage');
            msgDiv.innerHTML = '<div class="alert alert-' + type + ' mt-2 mb-0">' + message + '</div>';
        }
        
        function updateTotal() {
            const checkedSeats = document.querySelectorAll('input[name="seats[]"]:checked');
            const count = checkedSeats.length;
            const baseTotal = count * pricePerSeat;
            
            const discountAmount = baseTotal * (appliedDiscount / 100);
            const finalTotal = baseTotal - discountAmount;
            
            document.getElementById('seatCount').textContent = count;
            document.getElementById('basePrice').textContent = baseTotal.toFixed(2);
            document.getElementById('totalPrice').textContent = finalTotal.toFixed(2);
            
            if (appliedDiscount > 0 && count > 0) {
                document.getElementById('discountRow').style.display = '';
                document.getElementById('discountAmount').textContent = discountAmount.toFixed(2);
                document.getElementById('discountPercent').textContent = appliedDiscount.toFixed(0);
            } else {
                document.getElementById('discountRow').style.display = 'none';
            }
        }
        
        updateTotal();
        
        document.getElementById('couponInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                checkCoupon();
            }
        });
    </script>
</body>
</html>