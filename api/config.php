<?php
// API için DB bağlantısı ve JSON header
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

error_reporting(E_ALL);
ini_set('display_errors', 0); // API'de hataları gizle, log'a yaz

// STANDART YOL
define('DB_PATH', __DIR__ . '/data/veritabani.db');

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec("PRAGMA foreign_keys = ON");
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    error_log('DB ERROR: ' . $e->getMessage());
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//YARDIMCI FONKSİYONLAR

// Giriş yapılmış mı kontrol et requirelogin
function requireLogin() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Giriş yapmalısınız!'
        ]);
        exit();
    }
}

/**
 * Belirli rollere yetki kontrolü
 * @param array $allowedRoles İzin verilen roller ['user', 'company', 'admin']
 */
function requireRole($allowedRoles) {
    requireLogin();
    
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Bu işlem için yetkiniz yok!'
        ]);
        exit();
    }
}

/**
 * JSON input'u al ve decode et
 * @return array|null
 */
function getInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * JSON response döndür
 * @param bool $success
 * @param string $message
 * @param array $data
 */
function sendResponse($success, $message = '', $data = []) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    echo json_encode($response);
    exit();
}

/**
 * Kullanıcının session bilgilerini al
 * @return array|null
 */
function getCurrentUser() {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        return [
            'id' => $_SESSION['user_id'],
            'full_name' => $_SESSION['full_name'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role'],
            'balance' => $_SESSION['balance'] ?? 0,
            'company_id' => $_SESSION['company_id'] ?? null
        ];
    }
    return null;
}

/**
 * Input validasyonu
 * @param array $data Kontrol edilecek veri
 * @param array $required Gerekli alanlar
 * @return array|null Hata varsa hata mesajı, yoksa null
 */
function validateInput($data, $required) {
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            return ['success' => false, 'message' => ucfirst($field) . ' alanı gereklidir!'];
        }
    }
    return null;
}

/**
 * String'i güvenli hale getir
 * @param string $str
 * @return string
 */

/**
 * Email formatı kontrol et
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ============= CONSTANTS =============

// Rol sabitleri
define('ROLE_USER', 'user');
define('ROLE_COMPANY', 'company');
define('ROLE_ADMIN', 'admin');

// Bilet durumları
define('TICKET_ACTIVE', 'active');
define('TICKET_CANCELED', 'canceled');
define('TICKET_EXPIRED', 'expired');

// Varsayılan değerler
define('DEFAULT_BALANCE', 800);
define('CANCEL_TIME_LIMIT_HOURS', 1); // Bilet iptal için minimum süre

?>