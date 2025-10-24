<?php
session_start();

// Giriş kontrolü
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

$ticket_id = $_GET['ticket_id'] ?? null;

if (!$ticket_id) {
    header('Location: my-tickets.php');
    exit();
}

// Bilet bilgilerini al
try {
    $stmt = $db->prepare("
        SELECT 
            t.*,
            tr.departure_city,
            tr.destination_city,
            tr.departure_time,
            tr.arrival_time,
            bc.name as company_name,
            u.full_name,
            u.email
        FROM Tickets t
        JOIN Trips tr ON t.trip_id = tr.id
        LEFT JOIN Bus_Company bc ON tr.company_id = bc.id
        JOIN User u ON t.user_id = u.id
        WHERE t.id = ? AND t.user_id = ?
    ");
    $stmt->execute([$ticket_id, $_SESSION['user_id']]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        header('Location: my-tickets.php?error=ticket_not_found');
        exit();
    }
    
    // Koltuk numaralarını al
    $stmt = $db->prepare("SELECT seat_number FROM Booked_Seats WHERE ticket_id = ? ORDER BY seat_number");
    $stmt->execute([$ticket_id]);
    $seats = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Bilet durumu
    if ($ticket['status'] === 'canceled') {
        $status_text = 'İPTAL EDİLDİ';
        $status_color = '#dc3545';
    } else {
        $status_text = 'AKTİF';
        $status_color = '#28a745';
    }
    
} catch (PDOException $e) {
    error_log('TICKET PDF ERROR: ' . $e->getMessage());
    header('Location: my-tickets.php?error=pdf_error');
    exit();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilet #<?php echo $ticket_id; ?> - MERİCBİLET</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .ticket-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .ticket-header {
            background: linear-gradient(135deg, #d32f2f 0%, #c62828 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .ticket-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .ticket-header .ticket-number {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .ticket-status {
            display: inline-block;
            padding: 8px 20px;
            background: <?php echo $status_color; ?>;
            color: white;
            border-radius: 20px;
            margin-top: 10px;
            font-weight: bold;
        }
        
        .route-section {
            padding: 40px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .city-box {
            text-align: center;
            flex: 1;
        }
        
        .city-name {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .city-time {
            font-size: 1.5rem;
            color: #d32f2f;
            font-weight: bold;
        }
        
        .city-date {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }
        
        .arrow {
            font-size: 3rem;
            color: #d32f2f;
            padding: 0 30px;
        }
        
        .details-section {
            padding: 30px 40px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px dashed #ddd;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #666;
            font-size: 0.95rem;
        }
        
        .detail-value {
            font-weight: bold;
            color: #333;
            font-size: 1.05rem;
        }
        
        .seats-box {
            background: #ffebee;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
            border: 2px solid #d32f2f;
        }
        
        .seats-box .seat-numbers {
            font-size: 1.5rem;
            font-weight: bold;
            color: #d32f2f;
            margin-top: 10px;
        }
        
        .price-box {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        
        .price-box .amount {
            font-size: 2.5rem;
            font-weight: bold;
            color: #28a745;
        }
        
        .footer-section {
            background: #f8f9fa;
            padding: 30px 40px;
            text-align: center;
            border-top: 2px dashed #ddd;
        }
        
        .footer-section p {
            color: #666;
            margin: 5px 0;
            font-size: 0.9rem;
        }
        
        .barcode {
            margin-top: 20px;
            padding: 10px;
            background: white;
            border: 2px dashed #333;
            display: inline-block;
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            letter-spacing: 3px;
        }
        
        .download-button {
            text-align: center;
            margin: 30px 0;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 40px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-danger {
            background: #d32f2f;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c62828;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(211, 47, 47, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger:disabled {
            background: #999;
            cursor: wait;
        }
        
        #loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 30px 50px;
            border-radius: 10px;
            font-size: 1.2rem;
            z-index: 9999;
        }
    </style>
</head>
<body>
    
    <!-- Loading -->
    <div id="loading">
        📥 PDF oluşturuluyor, lütfen bekleyin...
    </div>

    <!-- Download Butonu -->
    <div class="download-button">
        <button onclick="downloadPDF()" class="btn btn-danger" id="downloadBtn">
            📥 PDF Olarak İndir
        </button>
        <a href="my-tickets.php" class="btn btn-secondary">
            ← Biletlerime Dön
        </a>
    </div>

    <!-- Bilet -->
    <div class="ticket-container" id="ticket">
        
        <!-- Header (KIRMIZI TEMA) -->
        <div class="ticket-header">
            <h1>🚌 MERİCBİLET</h1>
            <p class="ticket-number">Bilet No: #<?php echo str_pad($ticket_id, 8, '0', STR_PAD_LEFT); ?></p>
            <span class="ticket-status"><?php echo $status_text; ?></span>
        </div>
        
        <!-- Rota -->
        <div class="route-section">
            <div class="city-box">
                <div class="city-name"><?php echo htmlspecialchars($ticket['departure_city']); ?></div>
                <div class="city-time"><?php echo formatTime($ticket['departure_time']); ?></div>
                <div class="city-date"><?php echo formatDate($ticket['departure_time']); ?></div>
                <p style="margin-top: 10px; color: #666;">Kalkış</p>
            </div>
            
            <div class="arrow">→</div>
            
            <div class="city-box">
                <div class="city-name"><?php echo htmlspecialchars($ticket['destination_city']); ?></div>
                <div class="city-time"><?php echo formatTime($ticket['arrival_time']); ?></div>
                <div class="city-date"><?php echo formatDate($ticket['arrival_time']); ?></div>
                <p style="margin-top: 10px; color: #666;">Varış</p>
            </div>
        </div>
        
        <!-- Detaylar -->
        <div class="details-section">
            
            <div class="detail-row">
                <span class="detail-label">Yolcu Adı</span>
                <span class="detail-value"><?php echo htmlspecialchars($ticket['full_name']); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">E-posta</span>
                <span class="detail-value"><?php echo htmlspecialchars($ticket['email']); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Otobüs Firması</span>
                <span class="detail-value"><?php echo htmlspecialchars($ticket['company_name']); ?></span>
            </div>
            
            <!-- Koltuklar (KIRMIZI) -->
            <div class="seats-box">
                <div class="detail-label">Koltuk Numaraları</div>
                <div class="seat-numbers"><?php echo implode(', ', $seats); ?></div>
            </div>
            
            <!-- Fiyat -->
            <div class="price-box">
                <div class="detail-label">Toplam Ücret</div>
                <div class="amount"><?php echo number_format($ticket['total_price'], 2); ?> TL</div>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Satın Alma Tarihi</span>
                <span class="detail-value"><?php echo formatDateTime($ticket['created_at']); ?></span>
            </div>
            
        </div>
        
        <!-- Footer -->
        <div class="footer-section">
            <div class="barcode">
                MERIC-<?php echo strtoupper(substr(md5($ticket_id), 0, 12)); ?>
            </div>
            <p style="margin-top: 20px;">
                <strong>ÖNEMLİ BİLGİLER:</strong>
            </p>
            <p>✓ Lütfen yolculuk sırasında bu bileti yanınızda bulundurun.</p>
            <p>✓ Kalkıştan en az 30 dakika önce terminalde olunuz.</p>
            <p>✓ Kimlik ibrazı zorunludur.</p>
            <p style="margin-top: 15px; font-size: 0.8rem; color: #999;">
                Bu bilet MERİCBİLET tarafından elektronik olarak oluşturulmuştur.
            </p>
        </div>
        
    </div>
    
    <!-- jsPDF + html2canvas CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    
    <script>
        async function downloadPDF() {
            const btn = document.getElementById('downloadBtn');
            const loading = document.getElementById('loading');
            const ticket = document.getElementById('ticket');
            
            // Buton devre dışı
            btn.disabled = true;
            btn.textContent = '⏳ PDF Oluşturuluyor...';
            loading.style.display = 'block';
            
            try {
                // HTML'i canvas'a çevir
                const canvas = await html2canvas(ticket, {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    backgroundColor: '#ffffff'
                });
                
                // Canvas'ı PDF'e çevir
                const imgData = canvas.toDataURL('image/png');
                const { jsPDF } = window.jspdf;
                
                // PDF boyutları
                const pdf = new jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: 'a4'
                });
                
                const imgWidth = 210; // A4 genişliği (mm)
                const pageHeight = 297; // A4 yüksekliği (mm)
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                
                pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
                
                // PDF'i indir
                pdf.save('Bilet_<?php echo $ticket_id; ?>_MericBilet.pdf');
                
                // Başarı mesajı
                btn.textContent = '✅ PDF İndirildi!';
                btn.style.background = '#28a745';
                
                setTimeout(() => {
                    btn.disabled = false;
                    btn.textContent = '📥 PDF Olarak İndir';
                    btn.style.background = '#d32f2f';
                }, 2000);
                
            } catch (error) {
                console.error('PDF Error:', error);
                alert('PDF oluşturulurken hata oluştu!');
                btn.disabled = false;
                btn.textContent = '📥 PDF Olarak İndir';
            }
            
            loading.style.display = 'none';
        }
    </script>
</body>
</html>