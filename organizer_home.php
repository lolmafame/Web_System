<?php
session_start();
if(!isset($_SESSION['username'])) {
    $_SESSION['username'] = "Admin Organizer";
}
$username = $_SESSION['username'];

$events = [];
$attendees = [];
try {
    $dsn = 'mysql:host=localhost;dbname=event_management;charset=utf8mb4';
    $dbUser = 'root';
    $dbPass = '';
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmt = $pdo->query("
        SELECT
            e.id,
            e.title,
            e.type,
            e.icon,
            COALESCE(e.date_text, '') AS date,
            COALESCE(e.time_text, '') AS time,
            e.location,
            e.capacity,
            e.status,
            e.description,
            COALESCE(r.registered, 0) AS registered,
            (e.capacity - COALESCE(r.registered, 0)) AS available
        FROM events e
        LEFT JOIN (
            SELECT event_id, COUNT(*) AS registered
            FROM registrations
            WHERE status = 'confirmed'
            GROUP BY event_id
        ) r ON e.id = r.event_id
        ORDER BY e.created_at DESC
    ");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT r.id, u.first_name, u.last_name, u.email, r.event_id, r.status
        FROM registrations r
        JOIN users u ON r.user_id = u.id
        ORDER BY r.created_at DESC
        LIMIT 200
    ");
    $regs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $attendees = array_map(function($r){
        return [
            'id' => $r['id'],
            'name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
            'email' => $r['email'],
            'event_id' => $r['event_id'],
            'status' => $r['status'],
        ];
    }, $regs);

} catch (PDOException $e) {
    $events = $_SESSION['organizer_events'] ?? [];
    $attendees = $_SESSION['attendees'] ?? [];
}

try {
    $stmt = $pdo->query("
        SELECT
            e.id,
            e.title,
            e.capacity,
            COALESCE(r.registered, 0) AS registered
        FROM events e
        LEFT JOIN (
            SELECT event_id, COUNT(*) AS registered
            FROM registrations
            WHERE status = 'confirmed'
            GROUP BY event_id
        ) r ON e.id = r.event_id
        ORDER BY e.created_at DESC
    ");
    $attendance_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    $attendance_stats = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $dsn = 'mysql:host=localhost;dbname=event_management;charset=utf8mb4';
    $dbUser = 'root';
    $dbPass = '';
    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'DB connection failed']);
        exit();
    }

    switch ($_POST['action']) {
        case 'get_event':
            $eventId = intval($_POST['eventId'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, title, type, icon, date_text AS date, time_text AS time, location, capacity, description, status FROM events WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $eventId]);
            $ev = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ev) {
                echo json_encode(['success' => true, 'event' => $ev]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Event not found']);
            }
            exit();
        case 'create_event':
            $stmt = $pdo->prepare("INSERT INTO events (title, type, icon, date_text, time_text, location, capacity, status, description)
                                   VALUES (:title,:type,:icon,:date,:time,:location,:capacity,'active',:description)");
            $stmt->execute([
                ':title' => $_POST['title'],
                ':type' => $_POST['type'],
                ':icon' => $_POST['icon'],
                ':date' => $_POST['date'],
                ':time' => $_POST['time'],
                ':location' => $_POST['location'],
                ':capacity' => intval($_POST['capacity']),
                ':description' => $_POST['description']
            ]);
            echo json_encode(['success' => true, 'message' => 'Event created successfully!']);
            exit();

        case 'update_event':
            $eventId = intval($_POST['eventId']);
            $stmt = $pdo->prepare("UPDATE events SET title=:title, type=:type, date_text=:date, time_text=:time, location=:location, capacity=:capacity, description=:description WHERE id=:id");
            $stmt->execute([
                ':title'=>$_POST['title'],
                ':type'=>$_POST['type'],
                ':date'=>$_POST['date'],
                ':time'=>$_POST['time'],
                ':location'=>$_POST['location'],
                ':capacity'=>intval($_POST['capacity']),
                ':description'=>$_POST['description'],
                ':id'=>$eventId
            ]);
            echo json_encode(['success'=>true,'message'=>'Event updated successfully!']);
            exit();

        case 'delete_event':
            $eventId = intval($_POST['eventId']);
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = :id");
            $stmt->execute([':id'=>$eventId]);
            echo json_encode(['success'=>true,'message'=>'Event deleted successfully!']);
            exit();

        case 'toggle_status':
            $eventId = intval($_POST['eventId']);
            $stmt = $pdo->prepare("UPDATE events SET status = IF(status='active','inactive','active') WHERE id = :id");
            $stmt->execute([':id'=>$eventId]);
            echo json_encode(['success'=>true,'message'=>'Event status updated!']);
            exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_action']) && $_POST['profile_action'] === 'save_profile') {
    $userId = intval($_SESSION['user_id'] ?? 0);
    if (!$userId) {
        $_SESSION['profile_error'] = 'Not authenticated';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit();
    }

    if (!isset($pdo) || !$pdo) {
        try {
            $dsn = 'mysql:host=localhost;dbname=event_management;charset=utf8mb4';
            $dbUser = 'root'; $dbPass = '';
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) {
            $_SESSION['profile_error'] = 'Database unavailable';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit();
        }
    }

    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $organization= trim($_POST['organization'] ?? '');
    $bio         = trim($_POST['bio'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone, organization = :organization, bio = :bio WHERE id = :uid");
        $stmt->execute([
            ':first_name' => $first_name,
            ':last_name'  => $last_name,
            ':email'      => $email,
            ':phone'      => $phone,
            ':organization'=> $organization,
            ':bio'        => $bio,
            ':uid'        => $userId
        ]);

        $_SESSION['first_name']   = $first_name;
        $_SESSION['last_name']    = $last_name;
        $_SESSION['email']        = $email;
        $_SESSION['phone']        = $phone;
        $_SESSION['organization'] = $organization;
        $_SESSION['bio']          = $bio;
        $_SESSION['username']     = trim($first_name . ' ' . $last_name) ?: ($_SESSION['username'] ?? '');

        $_SESSION['profile_success'] = 'Profile updated successfully';
    } catch (Exception $e) {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        file_put_contents($logDir . '/profile_update.log', date('c') . " | " . $e->getMessage() . PHP_EOL, FILE_APPEND | LOCK_EX);

        $_SESSION['profile_error'] = 'Profile update failed. Please try again.';
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}

$user = [];
$userId = intval($_SESSION['user_id'] ?? 0);
if ($userId) {
    try {
        if (!isset($pdo) || !$pdo) {
            $dsn = 'mysql:host=localhost;dbname=event_management;charset=utf8mb4';
            $dbUser = 'root'; $dbPass = '';
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }
        $uStmt = $pdo->prepare("SELECT first_name, last_name, email, phone, organization, bio FROM users WHERE id = :id LIMIT 1");
        $uStmt->execute([':id' => $userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if (empty($_SESSION['username'])) {
            $_SESSION['username'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($_SESSION['username'] ?? 'Organizer');
        }
        if (!isset($_SESSION['email']) && isset($user['email'])) $_SESSION['email'] = $user['email'];
        if (!isset($_SESSION['phone']) && isset($user['phone'])) $_SESSION['phone'] = $user['phone'];
        if (!isset($_SESSION['organization']) && isset($user['organization'])) $_SESSION['organization'] = $user['organization'];
        if (!isset($_SESSION['bio']) && isset($user['bio'])) $_SESSION['bio'] = $user['bio'];
    } catch (Exception $e) {
        $user = [];
    }
}

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
$events = $events ?? [];
$attendees = $attendees ?? [];

$totalEvents = count($events);
$activeEvents = count(array_filter($events, function($e) { return (($e['status'] ?? '') === 'active'); }));
$totalAttendees = array_sum(array_map(function($e){ return intval($e['registered'] ?? 0); }, $events));

function format_ticket_id($registrationId, $eventId) {
    $registrationId = intval($registrationId);
    $eventId = intval($eventId);
    $hash = strtoupper(substr(md5($eventId . '-' . $registrationId), 0, 6));
    return "TKT-{$eventId}-{$registrationId}-{$hash}";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Dashboard - Event Management System</title>
    <style>
        * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f5f6fa;
    min-height: 100vh;
}

/* ===========================
   LAYOUT
   =========================== */
.dashboard-container {
    display: flex;
    min-height: 100vh;
}

.main-content {
    flex: 1;
    margin-left: 280px;
    padding: 2rem;
    width: calc(100% - 280px);
}

/* ===========================
   MOBILE MENU
   =========================== */
.mobile-menu-toggle {
    display: none;
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 1001;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    width: 45px;
    height: 45px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 1
    cursor: pointer;
    font-size: 1.5rem;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    align-items: center;
    justify-content: center;
}

.mobile-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 998;
}

.mobile-overlay.active {
    display: block;
}

/* ===========================
   SIDEBAR
   =========================== */
.sidebar {
    width: 280px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 1.5rem;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 999;
    transition: transform 0.3s ease;
}

.sidebar-header {
    text-align: center;
    margin-bottom: 2rem;
}

.sidebar-header h2 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.sidebar-header p {
    font-size: 0.9rem;
    opacity: 0.9;
}

/* Navigation */
.nav-menu {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
    color: white;
    text-decoration: none;
}

.nav-item:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateX(5px);
}

.nav-item.active {
    background: rgba(255, 255, 255, 0.25);
}

/* Logout Section */
.logout-section {
    margin-top: 2rem;
    padding-top: 2rem;  
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.logout-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.875rem;
    background: rgba(231, 76, 60, 0.9);
    border: none;
    border-radius: 10px;
    color: white;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 1rem;
}

.logout-btn:hover {
    background: rgba(192, 57, 43, 0.9);
    transform: translateY(-2px);
}

/* ===========================
   TOP BAR
   =========================== */
.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e0e0e0;
    flex-wrap: wrap;
    gap: 1rem;
}

.top-bar h1 {
    font-size: 1.8rem;
    color: #333;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    flex-shrink: 0;
}

/* ===========================
   WELCOME BANNER
   =========================== */
.welcome-banner {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.welcome-banner h2 {
    font-size: 1.8rem;
    margin-bottom: 0.5rem;
}

/* ===========================
   STATS GRID
   =========================== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card.purple {
    border-left: 4px solid #667eea;
}

.stat-card.blue {
    border-left: 4px solid #4facfe;
}

.stat-card.green {
    border-left: 4px solid #43e97b;
}

.stat-card.orange {
    border-left: 4px solid #fa709a;
}

.stat-icon {
    font-size: 2.5rem;
}

.stat-info h3 {
    font-size: 2rem;
    color: #333;
    margin-bottom: 0.25rem;
}

.stat-info p {
    color: #666;
    font-size: 0.9rem;
}

/* ===========================
   PAGE HEADER
   =========================== */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.page-header-content h2 {
    font-size: 1.8rem;
    color: #333;
    margin-bottom: 0.5rem;
}

.page-header-content p {
    color: #666;
}

.btn-create {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 0.875rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.btn-create:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

/* ===========================
   EVENTS TABLE
   =========================== */
.events-table-container {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    overflow-x: auto;
}

.events-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

.events-table th {
    text-align: left;
    padding: 1rem;
    background: #f8f8f8;
    color: #333;
    font-weight: 600;
    border-bottom: 2px solid #e0e0e0;
}

.events-table td {
    padding: 1rem;
    border-bottom: 1px solid #f0f0f0;
    color: #666;
}

.events-table tr:hover {
    background: #f8f8f8;
}

.event-title-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.event-icon-small {
    font-size: 1.5rem;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 8px;
    flex-shrink: 0;
}

.event-title-info h4 {
    color: #333;
    margin-bottom: 0.25rem;
}

.event-type-badge {
    display: inline-block;
    padding: 0.25rem 0.65rem;
    background: #f0f0f0;
    color: #667eea;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Status Badge */
.status-badge {
    padding: 0.35rem 0.85rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap;
}

.status-badge.active {
    background: #d4edda;
    color: #155724;
}

.status-badge.inactive {
    background: #f8d7da;
    color: #721c24;
}

/* Progress Bar */
.progress-mini {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.progress-bar-mini {
    flex: 1;
    height: 6px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
    min-width: 80px;
}

.progress-fill-mini {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    border-radius: 10px;
}

/* ===========================
   ACTION BUTTONS
   =========================== */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-icon {
    width: 35px;
    height: 35px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.btn-edit {
    background: #e3f2fd;
    color: #1976d2;
}

.btn-edit:hover {
    background: #1976d2;
    color: white;
}

.btn-toggle {
    background: #fff3cd;
    color: #856404;
}

.btn-toggle:hover {
    background: #856404;
    color: white;
}

.btn-delete {
    background: #f8d7da;
    color: #721c24;
}

.btn-delete:hover {
    background: #721c24;
    color: white;
}

/* ===========================
   MODAL
   =========================== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f0f0f0;
}

.modal-header h3 {
    font-size: 1.5rem;
    color: #333;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    transition: all 0.3s;
    flex-shrink: 0;
}

.btn-close:hover {
    background: #f0f0f0;
    color: #333;
}

/* ===========================
   FORMS
   =========================== */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    color: #333;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.875rem;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 1rem;
    font-family: inherit;
    transition: border-color 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #667eea;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.modal-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.btn-submit {
    flex: 1;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 0.875rem;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-cancel {
    flex: 1;
    background: white;
    color: #666;
    padding: 0.875rem;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-cancel:hover {
    background: #f8f8f8;
    border-color: #666;
    color: #333;
}

.attendees-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}

.attendee-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    transition: transform 0.3s;
}

.attendee-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
}

.attendee-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.attendee-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.attendee-info h4 {
    color: #333;
    margin-bottom: 0.25rem;
    word-break: break-word;
}

.attendee-info p {
    color: #666;
    font-size: 0.85rem;
    word-break: break-word;
}

.attendee-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding-top: 1rem;
    border-top: 1px solid #f0f0f0;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    gap: 0.5rem;
}

.detail-row .label {
    color: #666;
}

.detail-row .value {
    color: #333;
    font-weight: 600;
    text-align: right;
}

.reports-container {
    display: grid;
    gap: 2rem;
}

.report-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
}

.report-card h3 {
    font-size: 1.3rem;
    color: #333;
    margin-bottom: 1.5rem;
}

.chart-placeholder {
    height: 300px;
    background: #f8f8f8;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #666;
    font-size: 1.1rem;
    text-align: center;
    padding: 1rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
}

.empty-state-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.empty-state h3 {
    color: #333;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #666;
    margin-bottom: 1.5rem;
}

/* ===========================
   RESPONSIVE DESIGN
   =========================== */

/* Tablet (1024px and below) */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .top-bar h1 {
        font-size: 1.5rem;
    }

    .welcome-banner h2 {
        font-size: 1.5rem;
    }
}

/* Mobile (768px and below) */
@media (max-width: 768px) {
    .mobile-menu-toggle {
        display: flex;
    }

    .sidebar {
        transform: translateX(-100%);
    }

    .sidebar.active {
        transform: translateX(0);
    }

    .main-content {
        margin-left: 0;
        padding: 1rem;
        padding-top: 5rem;
        width: 100%;
    }

    .top-bar {
        flex-direction: column;
        align-items: flex-start;
    }

    .top-bar h1 {
        font-size: 1.3rem;
    }

    .user-info {
        width: 100%;
        justify-content: flex-start;
    }

    .welcome-banner {
        padding: 1.5rem;
    }

    .welcome-banner h2 {
        font-size: 1.3rem;
    }

    .stats-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .stat-card {
        padding: 1.25rem;
    }

    .stat-icon {
        font-size: 2rem;
    }

    .stat-info h3 {
        font-size: 1.5rem;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .page-header-content h2 {
        font-size: 1.3rem;
    }

    .btn-create {
        width: 100%;
        justify-content: center;
    }

    .events-table-container {
        padding: 1rem;
    }

    .events-table {
        font-size: 0.9rem;
    }

    .events-table th,
    .events-table td {
        padding: 0.75rem 0.5rem;
    }

    .event-icon-small {
        width: 35px;
        height: 35px;
        font-size: 1.2rem;
    }

    .attendees-grid {
        grid-template-columns: 1fr;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .modal-content {
        padding: 1.5rem;
        margin: 1rem;
    }

    .modal-header h3 {
        font-size: 1.2rem;
    }

    .modal-actions {
        flex-direction: column;
    }

    .report-card {
        padding: 1.5rem;
    }

    .report-card h3 {
        font-size: 1.1rem;
    }

    .chart-placeholder {
        height: 200px;
        font-size: 0.9rem;
    }

    .empty-state {
        padding: 3rem 1.5rem;
    }

    .empty-state-icon {
        font-size: 3rem;
    }
}

/* Small Mobile (480px and below) */
@media (max-width: 480px) {
    .main-content {
        padding: 0.75rem;
        padding-top: 4.5rem;
    }

    .top-bar h1 {
        font-size: 1.1rem;
    }

    .welcome-banner {
        padding: 1.25rem;
    }

    .welcome-banner h2 {
        font-size: 1.1rem;
    }

    .welcome-banner p {
        font-size: 0.85rem;
    }

    .stat-card {
        padding: 1rem;
    }

    .stat-icon {
        font-size: 1.75rem;
    }

    .stat-info h3 {
        font-size: 1.3rem;
    }

    .stat-info p {
        font-size: 0.8rem;
    }

    .page-header-content h2 {
        font-size: 1.1rem;
    }

    .page-header-content p {
        font-size: 0.85rem;
    }

    .btn-create {
        padding: 0.75rem 1.25rem;
        font-size: 0.9rem;
    }

    .events-table-container h3 {
        font-size: 1rem;
    }

    .modal-content {
        padding: 1.25rem;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 0.75rem;
        font-size: 0.9rem;
    }

    .btn-submit,
    .btn-cancel {
        padding: 0.75rem;
        font-size: 0.9rem;
    }

    .attendee-card {
        padding: 1.25rem;
    }

    .attendee-avatar {
        width: 45px;
        height: 45px;
        font-size: 1.1rem;
    }

    .attendee-info h4 {
        font-size: 0.95rem;
    }

    .attendee-info p {
        font-size: 0.8rem;
    }

    .detail-row {
        font-size: 0.85rem;
    }

    .report-card {
        padding: 1.25rem;
    }

    .report-card h3 {
        font-size: 1rem;
    }
}

/* Extra Small Mobile (360px and below) */
@media (max-width: 360px) {
    .mobile-menu-toggle {
        width: 40px;
        height: 40px;
        font-size: 1.3rem;
    }

    .avatar {
        width: 35px;
        height: 35px;
        font-size: 0.9rem;
    }

    .user-info span {
        font-size: 0.9rem;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        font-size: 1rem;
    }

    .action-buttons {
        gap: 0.35rem;
    }
}
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">☰</button>
    <div class="mobile-overlay" onclick="toggleMobileMenu()"></div>
    <div class="dashboard-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>EventHub</h2>
                <p>Organizer Portal</p>
            </div>
            <nav class="nav-menu">
                <a href="?tab=dashboard" class="nav-item <?php echo $activeTab == 'dashboard' ? 'active' : ''; ?>" onclick="closeMobileMenu()"><span></span><span>Dashboard</span></a>
                <a href="?tab=events" class="nav-item <?php echo $activeTab == 'events' ? 'active' : ''; ?>" onclick="closeMobileMenu()"><span></span><span>Manage Events</span></a>
                <a href="?tab=attendees" class="nav-item <?php echo $activeTab == 'attendees' ? 'active' : ''; ?>" onclick="closeMobileMenu()"><span></span><span>Attendees</span></a>
                <a href="?tab=reports" class="nav-item <?php echo $activeTab == 'reports' ? 'active' : ''; ?>" onclick="closeMobileMenu()"><span></span><span>Reports</span></a>
                <a href="?tab=profile" class="nav-item <?php echo $activeTab == 'profile' ? 'active' : ''; ?>" onclick="closeMobileMenu()"><span></span><span>Edit Profile</span></a>
            </nav>
            <div class="logout-section">
                <form method="POST" action="logout.php">
                    <button class="logout-btn" type="submit">Logout</button>
                </form>
            </div>
        </aside>
        <main class="main-content">
            <div class="top-bar">
                <h1><?php switch($activeTab) { case 'dashboard': echo 'Dashboard Overview'; break; case 'events': echo 'Manage Events'; break; case 'attendees': echo 'Attendee Management'; break; case 'reports': echo 'Reports'; break; case 'profile': echo 'Profile'; break; default: echo 'Dashboard'; } ?></h1>
                <div class="user-info">
                    <div class="avatar"><?php echo substr($username, 0, 1); ?></div>
                    <span><?php echo htmlspecialchars($username); ?></span>
                </div>
            </div>
            <?php if ($activeTab == 'dashboard'): ?>
                <div class="welcome-banner">
                    <h2>Welcome back, <?php echo htmlspecialchars($username); ?>! </h2>
                    <p>Here's an overview of your event management activities</p>
                </div>
                <div class="stats-grid">
                    <div class="stat-card purple"><div class="stat-icon"></div><div class="stat-info"><h3><?php echo $totalEvents; ?></h3><p>Total Events</p></div></div>
                    <div class="stat-card blue"><div class="stat-icon"></div><div class="stat-info"><h3><?php echo $activeEvents; ?></h3><p>Active Events</p></div></div>
                    <div class="stat-card green"><div class="stat-icon"></div><div class="stat-info"><h3><?php echo $totalAttendees; ?></h3><p>Total Attendees</p></div></div>
                </div>
                <div class="events-table-container">
                    <h3 style="margin-bottom: 1rem; color: #333;">Recent Events</h3>
                    <table class="events-table">
                        <thead><tr><th>Event</th><th>Date & Time</th><th>Attendance</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach (array_slice($events, 0, 5) as $event): ?>
                                <tr>
                                    <td><div class="event-title-cell"><div class="event-icon-small"><?php echo $event['icon']; ?></div><div class="event-title-info"><h4><?php echo htmlspecialchars($event['title']); ?></h4><span class="event-type-badge"><?php echo $event['type']; ?></span></div></div></td>
                                    <td><?php echo $event['date']; ?><br><small style="color: #999;"><?php echo $event['time']; ?></small></td>
                                    <td><div class="progress-mini"><div class="progress-bar-mini"><div class="progress-fill-mini" style="width: <?php echo ($event['registered'] / $event['capacity']) * 100; ?>%"></div></div><span style="white-space: nowrap;"><?php echo $event['registered']; ?>/<?php echo $event['capacity']; ?></span></div></td>
                                    <td><span class="status-badge <?php echo $event['status']; ?>"><?php echo ucfirst($event['status']); ?></span></td>
                                    <td><div class="action-buttons"><button class="btn-icon btn-edit" onclick="editEvent(<?php echo $event['id']; ?>)" title="Edit">✏️</button><button class="btn-icon btn-delete" onclick="deleteEvent(<?php echo $event['id']; ?>)" title="Delete">🗑️</button></div></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($activeTab == 'events'): ?>
                <div class="page-header">
                    <div class="page-header-content"><h2>Event Management</h2><p>Create, edit, and manage your events</p></div>
                    <button class="btn-create" onclick="openCreateModal()"><span>➕</span><span>Create Event</span></button>
                </div>
                <?php if (count($events) == 0): ?>
                    <div class="empty-state"><div class="empty-state-icon">📅</div><h3>No Events Yet</h3><p>Start by creating your first event</p><button class="btn-create" onclick="openCreateModal()">Create Event</button></div>
                <?php else: ?>
                    <div class="events-table-container">
                        <table class="events-table">
                            <thead><tr><th>Event</th><th>Location</th><th>Date & Time</th><th>Attendance</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                    <tr>
                                        <td><div class="event-title-cell"><div class="event-icon-small"><?php echo $event['icon']; ?></div><div class="event-title-info"><h4><?php echo htmlspecialchars($event['title']); ?></h4><span class="event-type-badge"><?php echo $event['type']; ?></span></div></div></td>
                                        <td><?php echo htmlspecialchars($event['location']); ?></td>
                                        <td><?php echo $event['date']; ?><br><small style="color: #999;"><?php echo $event['time']; ?></small></td>
                                        <td><div class="progress-mini"><div class="progress-bar-mini"><div class="progress-fill-mini" style="width: <?php echo ($event['registered'] / $event['capacity']) * 100; ?>%"></div></div><span style="white-space: nowrap;"><?php echo $event['registered']; ?>/<?php echo $event['capacity']; ?></span></div></td>
                                        <td><span class="status-badge <?php echo $event['status']; ?>"><?php echo ucfirst($event['status']); ?></span></td>
                                        <td><div class="action-buttons"><button class="btn-icon btn-edit" onclick="editEvent(<?php echo $event['id']; ?>)" title="Edit">✏️</button><button class="btn-icon btn-toggle" onclick="toggleStatus(<?php echo $event['id']; ?>)" title="Toggle Status">🔄</button><button class="btn-icon btn-delete" onclick="deleteEvent(<?php echo $event['id']; ?>)" title="Delete">🗑️</button></div></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php elseif ($activeTab == 'attendees'): ?>
                <div class="page-header">
                    <div class="page-header-content"><h2>Attendee Management</h2><p>View and manage event attendees</p></div>
                </div>
                <?php if (count($attendees) == 0): ?>
                    <div class="empty-state"><div class="empty-state-icon">👥</div><h3>No Attendees Yet</h3><p>Attendees will appear here once they register for your events</p></div>
                <?php else: ?>
                    <div class="attendees-grid">
                        <?php foreach ($attendees as $attendee): 
                            $event = null;
                            foreach ($events as $e) {
                                if ($e['id'] == $attendee['event_id']) {
                                    $event = $e;
                                    break;
                                }
                            }
                        ?>
                            <div class="attendee-card">
                                <div class="attendee-header">
                                    <div class="attendee-avatar"><?php echo substr($attendee['name'], 0, 1); ?></div>
                                    <div class="attendee-info"><h4><?php echo htmlspecialchars($attendee['name']); ?></h4><p><?php echo htmlspecialchars($attendee['email']); ?></p></div>
                                </div>
                                <div class="attendee-details">
                                    <div class="detail-row"><span class="label">Event:</span><span class="value"><?php echo $event ? htmlspecialchars($event['title']) : 'N/A'; ?></span></div>
                                    <div class="detail-row"><span class="label">Status:</span><span class="value"><span class="status-badge active"><?php echo ucfirst($attendee['status']); ?></span></span></div>
                                    <div class="detail-row"><span class="label">Ticket ID:</span><span class="value"><?php echo format_ticket_id($attendee['id'] ?? 0, $attendee['event_id'] ?? 0); ?></span></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php elseif ($activeTab == 'reports'): ?>
                <div class="page-header">
                    <div class="page-header-content"><h2>Reports</h2><p>Track your event performance</p></div>
                </div>
                <div class="reports-container">
                    <div class="report-card">
                        <h3>📊 Event Attendance Overview</h3>
                        <?php if (empty($attendance_stats)): ?>
                            <div class="empty-state" style="padding:1.5rem;margin-top:1rem;">
                                <div class="empty-state-icon">📉</div>
                                <h3>No attendance data</h3> 
                                <p>There are no registrations yet or the database is unavailable.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x:auto;margin-top:1rem;">
                                <table class="events-table" style="min-width:700px;">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Registered</th>
                                            <th>Capacity</th>
                                            <th>Available</th>
                                            <th>% Full</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_stats as $row):
                                            $registered = intval($row['registered'] ?? 0);
                                            $capacity = intval($row['capacity'] ?? 0);
                                            $available = max(0, $capacity - $registered);
                                            $percent = $capacity > 0 ? round(($registered / $capacity) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['title'] ?? 'Untitled'); ?></td>
                                            <td><?php echo $registered; ?></td>
                                            <td><?php echo $capacity; ?></td>
                                            <td><?php echo $available; ?></td>
                                            <td><?php echo $percent; ?>%</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($activeTab == 'profile'): ?>
                <div class="page-header">
                    <div class="page-header-content"><h2>Profile</h2><p>Manage your account and preferences</p></div>
                </div>
                <div class="report-card">
                    <h3>Account Information</h3>
                    <?php if (!empty($_SESSION['profile_success'])): ?>
                        <div style="background:#d4edda;color:#155724;padding:0.75rem;border-radius:8px;margin:0.75rem 0;">
                            <?php echo htmlspecialchars($_SESSION['profile_success']); unset($_SESSION['profile_success']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['profile_error'])): ?>
                        <div style="background:#f8d7da;color:#721c24;padding:0.75rem;border-radius:8px;margin:0.75rem 0;">
                            <?php echo htmlspecialchars($_SESSION['profile_error']); unset($_SESSION['profile_error']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="report-card" style="padding:1.5rem;">
                        <form class="profile-form" method="POST" action="">
                            <input type="hidden" name="profile_action" value="save_profile">

                            <div class="form-row">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? $_SESSION['first_name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? $_SESSION['last_name'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? $_SESSION['email'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? $_SESSION['phone'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label>Organization</label>
                                <input type="text" name="organization" value="<?php echo htmlspecialchars($user['organization'] ?? $_SESSION['organization'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label>Bio</label>
                                <textarea name="bio" rows="4"><?php echo htmlspecialchars($user['bio'] ?? $_SESSION['bio'] ?? ''); ?></textarea>
                            </div>

                            <div style="display:flex;gap:1rem;">
                                <button type="submit" class="btn-submit">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Create New Event</h3>
                <button class="btn-close" onclick="closeModal()">✖</button>
            </div>
            <form id="eventForm" onsubmit="return handleEventSubmit(event)">
                <input type="hidden" id="eventId" name="eventId">
                <div class="form-group">
                    <label>Event Title *</label>
                    <input type="text" id="eventTitle" name="title" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Event Type *</label>
                        <select id="eventType" name="type" required>
                            <option value="">Select Type</option>
                            <option value="Conference">Conference</option>
                            <option value="Workshop">Workshop</option>
                            <option value="Seminar">Seminar</option>
                            <option value="Webinar">Webinar</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Event Icon *</label>
                        <select id="eventIcon" name="icon" required>
                            <option value="">Select Icon</option>
                            <option value="📊">📊 Analytics</option>
                            <option value="💻">💻 Technology</option>
                            <option value="🎨">🎨 Design</option>
                            <option value="🤖">🤖 AI/ML</option>
                            <option value="📱">📱 Mobile</option>
                            <option value="🎓">🎓 Education</option>
                            <option value="💼">💼 Business</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date"
                               id="eventDate"
                               name="date"
                               required
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Time *</label>
                        <select id="eventTime" name="time" required>
                            <option value="">Select Time</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Location *</label>
                    <input type="text" id="eventLocation" name="location" required>
                </div>
                <div class="form-group">
                    <label>Capacity *</label>
                    <input type="number" id="eventCapacity" name="capacity" min="1" required>
                </div>
                <div class="form-group">
                    <label>Description *</label>
                    <textarea id="eventDescription" name="description" rows="4" required></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Save Event</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.editingEventId = null;

        window.toggleMobileMenu = function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.mobile-overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        };

        window.closeMobileMenu = function () {
            document.getElementById('sidebar').classList.remove('active');
            document.querySelector('.mobile-overlay').classList.remove('active');
        };

        window.openCreateModal = function () {
            window.editingEventId = null;
            document.getElementById('modalTitle').textContent = 'Create New Event';
            document.getElementById('eventForm').reset();
            document.getElementById('eventId').value = '';
            window.populateTimes();
            document.getElementById('eventModal').classList.add('active');
        };

        window.editEvent = function (eventId) {
            window.editingEventId = eventId;
            document.getElementById('modalTitle').textContent = 'Edit Event';
            const formData = new FormData();
            formData.append('action', 'get_event');
            formData.append('eventId', eventId);
            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.event) {
                        const e = res.event;
                        document.getElementById('eventId').value = e.id || '';
                        document.getElementById('eventTitle').value = e.title || '';
                        document.getElementById('eventType').value = e.type || '';
                        document.getElementById('eventIcon').value = e.icon || '';
                        document.getElementById('eventDate').value = e.date || '';
                        window.populateTimes();
                        document.getElementById('eventTime').value = e.time || '';
                        document.getElementById('eventLocation').value = e.location || '';
                        document.getElementById('eventCapacity').value = e.capacity || '';
                        document.getElementById('eventDescription').value = e.description || '';
                        document.getElementById('eventModal').classList.add('active');
                    } else {
                        alert(res.message || 'Failed to load event data');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error fetching event');
                });
        };

        window.closeModal = function () {
            document.getElementById('eventModal').classList.remove('active');
            document.getElementById('eventForm').reset();
            window.editingEventId = null;
        };

        window.handleEventSubmit = function (event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            formData.append('action', window.editingEventId ? 'update_event' : 'create_event');
            if (window.editingEventId) formData.append('eventId', window.editingEventId);
            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Saved');
                        window.closeModal();
                        window.location.href = 'index.php';
                    } else {
                        alert('Operation failed: ' + (data.message || 'unknown'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('An error occurred. Please try again.');
                });
            return false;
        };

        window.toggleStatus = function (eventId) {
            if (!confirm('Are you sure you want to toggle the status of this event?')) return;
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('eventId', eventId);
            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Status updated');
                        location.reload();
                    } else {
                        alert('Operation failed');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error toggling status');
                });
        };

        window.deleteEvent = function (eventId) {
            if (!confirm('Are you sure you want to delete this event? This action cannot be undone.')) return;
            const formData = new FormData();
            formData.append('action', 'delete_event');
            formData.append('eventId', eventId);
            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Deleted');
                        location.reload();
                    } else {
                        alert('Deletion failed');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error deleting event');
                });
        };

        window.populateTimes = function () {
            const select = document.getElementById('eventTime');
            if (!select) return;
            select.innerHTML = '<option value="">Select Time</option>';
            const startHour = 7;
            const endHour = 21;
            for (let h = startHour; h <= endHour; h++) {
                const hour12 = ((h + 11) % 12) + 1;
                const ampm = h < 12 ? 'AM' : 'PM';
                const label = hour12 + ':00 ' + ampm;
                const opt = document.createElement('option');
                opt.value = label;
                opt.textContent = label;
                select.appendChild(opt);
            }
        };

        document.addEventListener('DOMContentLoaded', function () {
            window.populateTimes();
            const modal = document.getElementById('eventModal');
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) window.closeModal();
                });
            }
            window.addEventListener('resize', function () {
                if (window.innerWidth > 768) window.closeMobileMenu();
            });
            const form = document.getElementById('eventForm');
            if (form) form.addEventListener('submit', window.handleEventSubmit);
        });
    </script>
</body>
</html>