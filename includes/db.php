<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// STANDART YOL: Her zaman api/data/veritabani.db kullan
$db_path = __DIR__ . '/../api/data/veritabani.db';

// Dosya kontrolü
if (!file_exists($db_path)) {
    die("
        <div style='background: #FFEBEE; padding: 20px; margin: 20px; border-left: 5px solid #C62828;'>
            <h2 style='color: #C62828;'>❌ VERİTABANI BULUNAMADI!</h2>
            <p><strong>Aranan yol:</strong> $db_path</p>
            <p><strong>__DIR__:</strong> " . __DIR__ . "</p>
            <p><strong>Dosya var mı:</strong> " . (file_exists($db_path) ? '✅ EVET' : '❌ HAYIR') . "</p>
        </div>
    ");
}

try {
    $db = new PDO('sqlite:' . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec("PRAGMA foreign_keys = ON");
    
} catch (PDOException $e) {
    die("
        <div style='background: #FFEBEE; padding: 20px; margin: 20px; border-left: 5px solid #C62828;'>
            <h2 style='color: #C62828;'>❌ VERİTABANI BAĞLANTI HATASI!</h2>
            <p><strong>Hata:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        </div>
    ");
}

// Session başlat (eğer başlamamışsa)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<?php
// SQLite dosyan: database/veritabani.db (api/data'dan kopyaladık)
$db_path = __DIR__ . '/../database/veritabani.db';

try {
    $db = new PDO('sqlite:' . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('DB ERROR: ' . $e->getMessage());
    die('Veritabanı bağlantı hatası.');
}