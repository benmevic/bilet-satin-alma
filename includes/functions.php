<?php
// includes/functions.php
// Yardımcı fonksiyonlar (SQLite uyumlu)

function formatDate($dateStr) {
    if (empty($dateStr)) return '-';
    try {
        $d = new DateTime($dateStr);
        return $d->format('d.m.Y');
    } catch (Exception $e) {
        return $dateStr;
    }
}

function formatTime($dateStr) {
    if (empty($dateStr)) return '-';
    try {
        $d = new DateTime($dateStr);
        return $d->format('H:i');
    } catch (Exception $e) {
        return $dateStr;
    }
}

function formatDateTime($dateStr) {
    if (empty($dateStr)) return '-';
    try {
        $d = new DateTime($dateStr);
        return $d->format('d.m.Y H:i');
    } catch (Exception $e) {
        return $dateStr;
    }
}

function calculateSeatsAvailable($db, $trip_id, $capacity) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM Booked_Seats 
            WHERE ticket_id IN (
                SELECT id FROM Tickets WHERE trip_id = ? AND (status IS NULL OR status = 'active')
            )");
        $stmt->execute([$trip_id]);
        $booked = (int)$stmt->fetchColumn();
        return $capacity - $booked;
    } catch (Exception $e) {
        error_log('calculateSeatsAvailable error: ' . $e->getMessage());
        return $capacity;
    }
}

function getOccupiedSeats($db, $trip_id) {
    try {
        $stmt = $db->prepare("SELECT seat_number FROM Booked_Seats 
            WHERE ticket_id IN (
                SELECT id FROM Tickets WHERE trip_id = ? AND (status IS NULL OR status = 'active')
            ) ORDER BY seat_number");
        $stmt->execute([$trip_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log('getOccupiedSeats error: ' . $e->getMessage());
        return [];
    }
}

function sanitize($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>