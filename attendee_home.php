<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'attendee') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'] ?? 'Guest';

$dsn = 'mysql:host=localhost;dbname=event_management;charset=utf8mb4';
$dbUser = 'root';
$dbPass = '';

function format_ticket_id($registrationId, $eventId) {
    $registrationId = intval($registrationId);
    $eventId = intval($eventId);
    $hash = strtoupper(substr(md5($eventId . '-' . $registrationId), 0, 6));
    return "TKT-{$eventId}-{$registrationId}-{$hash}";
}

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$stmtCols = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events'");
$stmtCols->execute();
$eventCols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

function coalesceCols(array $candidates, array $existing, $alias, $tablePrefix = 'e') {
    $found = [];
    foreach ($candidates as $c) {
        if (in_array($c, $existing, true)) $found[] = "{$tablePrefix}.{$c}";
    }
    if (empty($found)) return "'' AS {$alias}";
    return "COALESCE(" . implode(', ', $found) . ", '') AS {$alias}";
}

$dateExpr = coalesceCols(['date_text','event_date','date'], $eventCols, 'date');
$timeExpr = coalesceCols(['time_text','event_time','time'], $eventCols, 'time');
$typeExpr = coalesceCols(['type','event_type'], $eventCols, 'type');
$titleExpr = coalesceCols(['title','event_title','name'], $eventCols, 'title');

$sql = "
SELECT e.*,
       {$titleExpr},
       {$typeExpr},
       {$dateExpr},
       {$timeExpr},
       COALESCE(r.registered,0) AS registered,
       (COALESCE(e.capacity,0) - COALESCE(r.registered,0)) AS available
FROM events e
LEFT JOIN (
    SELECT event_id, COUNT(*) AS registered
    FROM registrations
    WHERE status = 'confirmed'
    GROUP BY event_id
) r ON e.id = r.event_id
WHERE COALESCE(e.status, 'active') = 'active'
ORDER BY COALESCE(e.created_at, e.id) ASC
";
$events = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $userId = intval($_SESSION['user_id'] ?? 0);
    if (!$userId) { echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit(); }

    switch ($_POST['action']) {
        case 'register':
            $eventId = intval($_POST['eventId']);
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT e.capacity, COALESCE(r.registered,0) AS registered, (e.capacity - COALESCE(r.registered,0)) AS available
                                       FROM events e
                                       LEFT JOIN (
                                         SELECT event_id, COUNT(*) AS registered FROM registrations WHERE status='confirmed' GROUP BY event_id
                                       ) r ON e.id = r.event_id
                                       WHERE e.id = :id FOR UPDATE");
                $stmt->execute([':id'=>$eventId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row || intval($row['available']) <= 0) {
                    $pdo->rollBack();
                    echo json_encode(['success'=>false,'message'=>'No available slots']);
                    exit();
                }

                $chk = $pdo->prepare("SELECT id FROM registrations WHERE user_id = :uid AND event_id = :eid AND status = 'confirmed' LIMIT 1");
                $chk->execute([':uid'=>$userId, ':eid'=>$eventId]);
                if ($chk->fetch()) {
                    $pdo->rollBack();
                    echo json_encode(['success'=>false,'message'=>'Already registered for this event']);
                    exit();
                }

                $ins = $pdo->prepare("INSERT INTO registrations (user_id,event_id,status) VALUES (:uid,:eid,'confirmed')");
                $ins->execute([':uid'=>$userId,':eid'=>$eventId]);
                $pdo->commit();
                echo json_encode(['success'=>true,'message'=>'Successfully registered!']);
            } catch (Exception $ex) {
                $pdo->rollBack();
                echo json_encode(['success'=>false,'message'=>'Registration failed']);
            }
            exit();

        case 'cancel':
            $eventId = intval($_POST['eventId']);
            $stmt = $pdo->prepare("UPDATE registrations SET status='cancelled' WHERE user_id=:uid AND event_id=:eid AND status='confirmed' LIMIT 1");
            $stmt->execute([':uid'=>$userId,':eid'=>$eventId]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success'=>true,'message'=>'Registration cancelled']);
            } else {
                echo json_encode(['success'=>false,'message'=>'No active registration found']);
            }
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

    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    $parts = preg_split('/\s+/', $fullname, 2, PREG_SPLIT_NO_EMPTY);
    $first_name = $parts[0] ?? '';
    $last_name = $parts[1] ?? '';

    try {
        $stmt = $pdo->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone, organization = :organization, bio = :bio WHERE id = :uid");
        $stmt->execute([
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':email' => $email,
            ':phone' => $phone,
            ':organization' => $organization,
            ':bio' => $bio,
            ':uid' => $userId
        ]);

        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name;
        $_SESSION['email'] = $email;
        $_SESSION['phone'] = $phone;
        $_SESSION['organization'] = $organization;
        $_SESSION['bio'] = $bio;
        $_SESSION['username'] = trim(($first_name . ' ' . $last_name)) ?: ($_SESSION['username'] ?? '');

        $_SESSION['profile_success'] = 'Profile updated successfully';
    } catch (Exception $e) {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        file_put_contents($logDir . '/profile_update.log', date('Y-m-d H:i:s') . " | Profile update error: " . $e->getMessage() . PHP_EOL, FILE_APPEND | LOCK_EX);

        $_SESSION['profile_error'] = 'Profile update failed. Please try again.';
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

$userId = intval($_SESSION['user_id'] ?? 0);
if ($userId) {
    $regSql = "
    SELECT e.*, r.id AS registration_id, r.created_at AS reg_created,
           {$titleExpr},
           {$typeExpr},
           {$dateExpr},
           {$timeExpr}
    FROM events e
    INNER JOIN registrations r ON e.id = r.event_id
    WHERE r.user_id = :uid AND r.status = 'confirmed'
    ORDER BY r.created_at DESC
    ";
    $stmt = $pdo->prepare($regSql);
    $stmt->execute([':uid' => $userId]);
    $registeredEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $registeredEventIds = array_map(function($r){ return intval($r['id']); }, $registeredEvents);
} else {
    $registeredEvents = [];
    $registeredEventIds = [];
}

$user = [];
$userId = intval($_SESSION['user_id'] ?? 0);
if ($userId) {
    try {
        $uStmt = $pdo->prepare("SELECT id, role, first_name, last_name, email, phone, organization, bio FROM users WHERE id = :id LIMIT 1");
        $uStmt->execute([':id' => $userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if (empty($_SESSION['username'])) {
            $_SESSION['username'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($_SESSION['username'] ?? 'Guest');
        }
        if (!isset($_SESSION['email']) && isset($user['email'])) $_SESSION['email'] = $user['email'];
        if (!isset($_SESSION['phone']) && isset($user['phone'])) $_SESSION['phone'] = $user['phone'];
        if (!isset($_SESSION['organization']) && isset($user['organization'])) $_SESSION['organization'] = $user['organization'];
        if (!isset($_SESSION['bio']) && isset($user['bio'])) $_SESSION['bio'] = $user['bio'];
    } catch (Exception $e) {
        $user = [];
    }
}

$myRegistrationsCount = count($registeredEvents);

$availableEventsCount = 0;
foreach ($events as $ev) {
    $evId = intval($ev['id'] ?? 0);
    $available = intval($ev['available'] ?? (intval($ev['capacity'] ?? 0) - intval($ev['registered'] ?? 0)));
    $isRegistered = in_array($evId, $registeredEventIds, true);
    if (($ev['status'] ?? 'active') === 'active' && !$isRegistered && $available > 0) {
        $availableEventsCount++;
    }
}

$newAnnouncementsCount = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendee Dashboard - Event Management System</title>
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

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 12px;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: all 0.3s;
        }

        .mobile-menu-toggle:hover {
            transform: scale(1.05);
        }

        .mobile-menu-toggle.active {
            background: #e74c3c;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 1.5rem;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
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

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            width: calc(100% - 280px);
        }

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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.purple { border-left: 4px solid #667eea; }
        .stat-card.blue { border-left: 4px solid #4facfe; }
        .stat-card.green { border-left: 4px solid #43e97b; }
        .stat-card.orange { border-left: 4px solid #fa709a; }

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

        .quick-actions h3 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: #333;
        }

        .action-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
        }

        .action-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.2);
        }

        .action-card-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }

        .action-card h4 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .action-card p {
            color: #666;
            font-size: 0.85rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h2 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #666;
        }

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 200px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
        }

        .search-box input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 1rem;
        }

        .filters select {
            padding: 0.75rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
            outline: none;
            min-width: 150px;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .event-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .event-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .event-image {
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .event-icon {
            font-size: 4rem;
        }

        .event-content {
            padding: 1.5rem;
        }

        .event-type {
            display: inline-block;
            padding: 0.35rem 0.85rem;
            background: #f0f0f0;
            color: #667eea;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .event-content h3 {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 0.75rem;
            line-height: 1.3;
        }

        .event-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .event-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
            font-size: 0.9rem;
        }

        .capacity-bar {
            margin-bottom: 1rem;
        }

        .capacity-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .capacity-info strong {
            color: #667eea;
        }

        .progress-bar {
            height: 8px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 10px;
            transition: width 0.3s;
        }

        .btn-register, .btn-registered, .btn-full, .btn-primary {
            width: 100%;
            padding: 0.875rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-register {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-registered {
            background: #43e97b;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-full {
            background: #e74c3c;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
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

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.875rem 2rem;
            display: inline-block;
            text-decoration: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .registrations-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .registration-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            gap: 1.5rem;
            transition: transform 0.3s;
        }

        .registration-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }

        .registration-icon {
            font-size: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 15px;
            flex-shrink: 0;
        }

        .registration-info {
            flex: 1;
            min-width: 0;
        }

        .registration-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .registration-header h3 {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .status-badge {
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: #d4edda;
            color: #155724;
            white-space: nowrap;
        }

        .registration-details {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .registration-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-view-ticket, .btn-cancel {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-view-ticket {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-view-ticket:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }

        .btn-cancel {
            background: #fff;
            border: 2px solid #e74c3c;
            color: #e74c3c;
        }

        .btn-cancel:hover {
            background: #e74c3c;
            color: white;
        }

        .tickets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .ticket-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .ticket-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .ticket-icon {
            font-size: 2.5rem;
        }

        .ticket-header h3 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .ticket-type {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .ticket-qr {
            padding: 2rem;
            background: #f8f8f8;
            display: flex;
            justify-content: center;
        }

        .qr-placeholder {
            width: 150px;
            height: 150px;
            background: white;
            border: 3px dashed #667eea;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #667eea;
        }

        .ticket-info {
            padding: 1.5rem;
        }

        .ticket-detail {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
            gap: 1rem;
        }

        .ticket-detail:last-child {
            border-bottom: none;
        }

        .ticket-detail .label {
            color: #666;
            font-size: 0.9rem;
        }

        .ticket-detail .value {
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
            text-align: right;
            word-break: break-word;
        }

        .btn-download {
            width: calc(100% - 3rem);
            margin: 0 1.5rem 1.5rem;
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

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }

        .announcements-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .announcement-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            gap: 1.5rem;
            transition: transform 0.3s;
        }

        .announcement-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }

        .announcement-icon {
            font-size: 2.5rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f8f8;
            border-radius: 12px;
            flex-shrink: 0;
        }

        .announcement-icon.new {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .announcement-content {
            flex: 1;
            min-width: 0;
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .announcement-header h3 {
            font-size: 1.1rem;
            color: #333;
        }

        .announcement-date {
            font-size: 0.85rem;
            color: #999;
            white-space: nowrap;
        }

        .announcement-content p {
            color: #666;
            line-height: 1.6;
        }

        .profile-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .profile-avatar {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .avatar-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 3rem;
        }

        .btn-change-avatar {
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-change-avatar:hover {
            background: #667eea;
            color: white;
        }

        .profile-form {
            max-width: 600px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
            width: 100%;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 240px;
            }

            .main-content {
                margin-left: 240px;
                width: calc(100% - 240px);
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .events-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
                padding-top: 5rem;
            }

            .top-bar {
                flex-direction: column;
                align-items: flex-start;
            }

            .top-bar h1 {
                font-size: 1.5rem;
            }

            .welcome-banner {
                padding: 1.5rem;
            }

            .welcome-banner h2 {
                font-size: 1.4rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-icon {
                font-size: 2rem;
            }

            .stat-info h3 {
                font-size: 1.5rem;
            }

            .stat-info p {
                font-size: 0.8rem;
            }

            .action-cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .action-card {
                padding: 1rem;
            }

            .action-card-icon {
                font-size: 1.5rem;
            }

            .action-card h4 {
                font-size: 0.9rem;
            }

            .action-card p {
                font-size: 0.75rem;
            }

            .events-grid {
                grid-template-columns: 1fr;
            }

            .registration-card {
                flex-direction: column;
                padding: 1rem;
            }

            .registration-icon {
                width: 60px;
                height: 60px;
                font-size: 2rem;
            }

            .registration-header h3 {
                font-size: 1.1rem;
            }

            .registration-details {
                gap: 0.75rem;
            }

            .registration-actions {
                flex-direction: column;
            }

            .btn-view-ticket, .btn-cancel {
                width: 100%;
            }

            .tickets-grid {
                grid-template-columns: 1fr;
            }

            .announcement-card {
                padding: 1rem;
                gap: 1rem;
            }

            .announcement-icon {
                width: 50px;
                height: 50px;
                font-size: 2rem;
            }

            .announcement-header h3 {
                font-size: 1rem;
            }

            .profile-container {
                padding: 1.5rem;
            }

            .avatar-circle {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }

            .filters {
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }

            .filters select {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem;
                padding-top: 4.5rem;
            }

            .top-bar h1 {
                font-size: 1.3rem;
            }

            .welcome-banner {
                padding: 1.25rem;
            }

            .welcome-banner h2 {
                font-size: 1.2rem;
            }

            .welcome-banner p {
                font-size: 0.85rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                flex-direction: column;
                text-align: center;
            }

            .action-cards {
                grid-template-columns: 1fr;
            }

            .page-header h2 {
                font-size: 1.4rem;
            }

            .event-content {
                padding: 1rem;
            }

            .event-content h3 {
                font-size: 1.1rem;
            }

            .event-description {
                font-size: 0.85rem;
            }

            .registration-icon {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
                align-self: center;
            }

            .registration-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .ticket-header {
                padding: 1rem;
            }

            .ticket-icon {
                font-size: 2rem;
            }

            .ticket-header h3 {
                font-size: 1rem;
            }

            .ticket-qr {
                padding: 1.5rem;
            }

            .qr-placeholder {
                width: 120px;
                height: 120px;
            }

            .ticket-info {
                padding: 1rem;
            }

            .btn-download {
                width: calc(100% - 2rem);
                margin: 0 1rem 1rem;
            }

            .announcement-card {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .announcement-header {
                flex-direction: column;
                align-items: center;
            }

            .profile-avatar {
                padding-bottom: 1.5rem;
            }

            .avatar-circle {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
        }

        @media (max-width: 320px) {
            .mobile-menu-toggle {
                width: 45px;
                height: 45px;
                font-size: 1.3rem;
            }

            .main-content {
                padding: 0.5rem;
                padding-top: 4rem;
            }

            .top-bar {
                margin-bottom: 1rem;
            }

            .welcome-banner {
                padding: 1rem;
            }

            .welcome-banner h2 {
                font-size: 1.1rem;
            }

            .stat-card {
                padding: 0.75rem;
            }

            .stat-icon {
                font-size: 1.75rem;
            }

            .action-card {
                padding: 0.875rem;
            }

            .event-icon {
                font-size: 3rem;
            }

            .event-image {
                height: 150px;
            }
        }

        @media print {
            .sidebar,
            .mobile-menu-toggle,
            .sidebar-overlay,
            .logout-section,
            .btn-register,
            .btn-cancel,
            .filters {
                display: none !important;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .event-card,
            .registration-card,
            .ticket-card,
            .announcement-card {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" id="mobileMenuBtn">
        ☰
    </button>

    <div class="sidebar-overlay" onclick="toggleMobileMenu()" id="sidebarOverlay"></div>

    <div class="dashboard-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>EventHub</h2>
                <p>Attendee Portal</p>
            </div>

            <nav class="nav-menu">
                <a href="?tab=dashboard" class="nav-item <?php echo $activeTab == 'dashboard' ? 'active' : ''; ?>">
                    <span>Dashboard</span>
                </a>
                <a href="?tab=events" class="nav-item <?php echo $activeTab == 'events' ? 'active' : ''; ?>">
                    <span>Browse Events</span>
                </a>
                <a href="?tab=registrations" class="nav-item <?php echo $activeTab == 'registrations' ? 'active' : ''; ?>">
                    <span>My Registrations</span>
                </a>
                <a href="?tab=tickets" class="nav-item <?php echo $activeTab == 'tickets' ? 'active' : ''; ?>">
                    <span>My Tickets</span>
                </a>
                <a href="?tab=profile" class="nav-item <?php echo $activeTab == 'profile' ? 'active' : ''; ?>">
                    <span>Edit Profile</span>
                </a>
            </nav>

            <div class="logout-section">
                <form method="POST" action="logout.php">
                    <button class="logout-btn" type="submit">
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>
                    <?php
                    switch($activeTab) {
                        case 'dashboard': echo 'Dashboard'; break;
                        case 'events': echo 'Browse Events'; break;
                        case 'registrations': echo 'My Registrations'; break;
                        case 'tickets': echo 'My Tickets'; break;
                        case 'profile': echo 'Edit Profile'; break;
                        default: echo 'Dashboard';
                    }
                    ?>
                </h1>
                <div class="user-info">
                    <div class="avatar">
                        <?php echo substr($username, 0, 1); ?>
                    </div>
                    <span><?php echo htmlspecialchars($username); ?></span>
                </div>
            </div>

            <?php if ($activeTab == 'dashboard'): ?>
                <div class="welcome-banner">
                    <div>
                        <h2>Welcome back, <?php echo htmlspecialchars($username); ?>! 👋</h2>
                        <p>Here's what's happening with your events</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card purple">
                        <div class="stat-icon">📅</div>
                        <div class="stat-info">
                            <h3><?php echo $availableEventsCount; ?></h3>
                            <p>Available Events</p>
                        </div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-icon">✅</div>
                        <div class="stat-info">
                            <h3><?php echo $myRegistrationsCount; ?></h3>
                            <p>My Registrations</p>
                        </div>
                    </div>
                    <div class="stat-card green">
                        <div class="stat-icon">🎫</div>
                        <div class="stat-info">
                            <h3><?php echo $myRegistrationsCount; ?></h3>
                            <p>Confirmed Tickets</p>
                        </div>
                    </div>
                </div>

                <div class="quick-actions">
                    <h3>Quick Actions</h3>
                    <div class="action-cards">
                        <a href="?tab=events" class="action-card">
                            <div class="action-card-icon">🔍</div>
                            <h4>Browse Events</h4>
                            <p>Explore upcoming events</p>
                        </a>
                        <a href="?tab=registrations" class="action-card">
                            <div class="action-card-icon">📝</div>
                            <h4>My Registrations</h4>
                            <p>View registered events</p>
                        </a>
                        <a href="?tab=tickets" class="action-card">
                            <div class="action-card-icon">🎟️</div>
                            <h4>My Tickets</h4>
                            <p>Access your tickets</p>
                        </a>
                    </div>
                </div>

            <?php elseif ($activeTab == 'events'): ?>
                <div class="page-header">
                    <h2>Upcoming Events</h2>
                    <p>Discover and register for events that interest you</p>
                </div>

                <div class="filters">
                    <div class="search-box">
                        <span>🔍</span>
                        <input type="text" id="searchInput" placeholder="Search events..." onkeyup="filterEvents()">
                    </div>
                    <select id="typeFilter" onchange="filterEvents()">
                        <option value="">All Types</option>
                        <option value="Conference">Conference</option>
                        <option value="Workshop">Workshop</option>
                        <option value="Seminar">Seminar</option>
                    </select>
                </div>

                <?php if (count($events) == 0): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📅</div>
                        <h3>No Events Available</h3>
                        <p>There are currently no events to display. Check back later!</p>
                    </div>
                <?php else: ?>
                    <div class="events-grid" id="eventsGrid">
                        <?php foreach ($events as $event): 
                            $isRegistered = in_array($event['id'], $registeredEventIds);
                            $isFull = $event['available'] == 0;
                            $eventType = $event['type'] ?? 'Event';
                            $gradientColor = $eventType == 'Conference' ? 'linear-gradient(135deg, #667eea, #764ba2)' :
                                            ($eventType == 'Workshop' ? 'linear-gradient(135deg, #f093fb, #f5576c)' :
                                            'linear-gradient(135deg, #4facfe, #00f2fe)');
                        ?>
                            <div class="event-card" data-title="<?php echo strtolower($event['title'] ?? ''); ?>" data-type="<?php echo $eventType; ?>">
                                <div class="event-image" style="background: <?php echo $gradientColor; ?>">
                                    <span class="event-icon"><?php echo $event['icon'] ?? '📅'; ?></span>
                                </div>
                                <div class="event-content">
                                    <span class="event-type"><?php echo htmlspecialchars($eventType); ?></span>
                                    <h3><?php echo htmlspecialchars($event['title'] ?? 'Untitled Event'); ?></h3>
                                    <p class="event-description"><?php echo htmlspecialchars($event['description'] ?? ''); ?></p>
                                    <div class="event-details">
                                        <div class="detail-item">
                                            <span>📅</span>
                                            <span><?php echo htmlspecialchars($event['date'] ?? 'TBD'); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span>🕐</span>
                                            <span><?php echo htmlspecialchars($event['time'] ?? 'TBD'); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span>📍</span>
                                            <span><?php echo htmlspecialchars($event['location'] ?? 'TBD'); ?></span>
                                        </div>
                                    </div>
                                    <div class="capacity-bar">
                                        <div class="capacity-info">
                                            <span>Available Slots</span>
                                            <strong><?php echo $event['available'] ?? 0; ?>/<?php echo $event['capacity'] ?? 0; ?></strong>
                                        </div>
                                        <div class="progress-bar">
                                            <?php 
                                            $capacity = $event['capacity'] ?? 1;
                                            $available = $event['available'] ?? 0;
                                            $percentage = $capacity > 0 ? ($available / $capacity) * 100 : 0;
                                            ?>
                                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </div>
                                    <?php if ($isRegistered): ?>
                                        <button class="btn-registered" disabled>
                                            ✅ Already Registered
                                        </button>
                                    <?php elseif ($isFull): ?>
                                        <button class="btn-full" disabled>
                                            ❌ Fully Booked
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-register" onclick="registerEvent(<?php echo $event['id']; ?>)">
                                            Register Now
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($activeTab == 'registrations'): ?>
                <div class="page-header">
                    <h2>My Registrations</h2>
                    <p>Events you've registered for</p>
                </div>

                <?php if (count($registeredEvents) == 0): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📅</div>
                        <h3>No Registrations Yet</h3>
                        <p>You haven't registered for any events. Browse available events to get started!</p>
                        <a href="?tab=events" class="btn-primary">Browse Events</a>
                    </div>
                <?php else: ?>
                    <div class="registrations-list">
                        <?php foreach ($registeredEvents as $event): ?>
                            <div class="registration-card">
                                <div class="registration-icon"><?php echo $event['icon'] ?? '📅'; ?></div>
                                <div class="registration-info">
                                    <div class="registration-header">
                                        <div>
                                            <h3><?php echo htmlspecialchars($event['title'] ?? 'Untitled Event'); ?></h3>
                                            <span class="event-type"><?php echo htmlspecialchars($event['type'] ?? 'Event'); ?></span>
                                        </div>
                                        <span class="status-badge">Confirmed</span>
                                    </div>
                                    <div class="registration-details">
                                        <div class="detail-item">
                                            <span>📅</span>
                                            <span><?php echo htmlspecialchars($event['date'] ?? 'TBD'); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span>🕐</span>
                                            <span><?php echo htmlspecialchars($event['time'] ?? 'TBD'); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span>📍</span>
                                            <span><?php echo htmlspecialchars($event['location'] ?? 'TBD'); ?></span>
                                        </div>
                                    </div>
                                    <div class="registration-actions">
                                        <a href="?tab=tickets" class="btn-view-ticket">
                                            🎫 View Ticket
                                        </a>
                                        <button class="btn-cancel" onclick="cancelRegistration(<?php echo $event['id']; ?>)">
                                            Cancel Registration
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($activeTab == 'tickets'): ?>
                <div class="page-header">
                    <h2>My Tickets</h2>
                    <p>Access your event passes and QR codes</p>
                </div>

                <?php if (count($registeredEvents) == 0): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🎫</div>
                        <h3>No Tickets Available</h3>
                        <p>Register for events to receive your tickets</p>
                        <a href="?tab=events" class="btn-primary">Browse Events</a>
                    </div>
                <?php else: ?>
                    <div class="tickets-grid">
                        <?php foreach ($registeredEvents as $event): ?>
                            <div class="ticket-card">
                                <div class="ticket-header">
                                    <div class="ticket-icon"><?php echo $event['icon'] ?? '🎫'; ?></div>
                                    <div>
                                        <h3><?php echo htmlspecialchars($event['title'] ?? 'Untitled Event'); ?></h3>
                                        <span class="ticket-type"><?php echo htmlspecialchars($event['type'] ?? 'Event'); ?></span>
                                    </div>
                                </div>
                                <div class="ticket-qr">
                                    <div class="qr-placeholder">QR CODE</div>
                                </div>
                                <div class="ticket-info">
                                    <div class="ticket-detail">
                                        <span class="label">Date</span>
                                        <span class="value"><?php echo htmlspecialchars($event['date'] ?? 'TBD'); ?></span>
                                    </div>
                                    <div class="ticket-detail">
                                        <span class="label">Time</span>
                                        <span class="value"><?php echo htmlspecialchars($event['time'] ?? 'TBD'); ?></span>
                                    </div>
                                    <div class="ticket-detail">
                                        <span class="label">Venue</span>
                                        <span class="value"><?php echo htmlspecialchars($event['location'] ?? 'TBD'); ?></span>
                                    </div>
                                    <div class="ticket-detail">
                                        <span class="label">Ticket ID</span>
                                        <span class="value"><?php echo format_ticket_id($event['registration_id'] ?? $event['id'], $event['id']); ?></span>
                                    </div>
                                </div>
                                <button class="btn-download" onclick="alert('Ticket download feature coming soon!')">Download Ticket</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($activeTab == 'profile'): ?>
                <div class="page-header">
                    <h2>Edit Profile</h2>
                    <p>Update your personal information</p>
                </div>

                <div class="profile-container">
                    <form class="profile-form" method="POST" action="">
                        <input type="hidden" name="profile_action" value="save_profile">
 
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="fullname" value="<?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($_SESSION['username'] ?? '')); ?>">
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
                         <button type="submit" class="btn-save">Save Changes</button>
                     </form>
                 </div>
             <?php endif; ?>
        </main>
    </div>

    <script>
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuBtn = document.getElementById('mobileMenuBtn');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            menuBtn.classList.toggle('active');
            
            if (sidebar.classList.contains('active')) {
                menuBtn.innerHTML = '✕';
            } else {
                menuBtn.innerHTML = '☰';
            }
        }

        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    toggleMobileMenu();
                }
            });
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                const menuBtn = document.getElementById('mobileMenuBtn');
                
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                menuBtn.classList.remove('active');
                menuBtn.innerHTML = '☰';
            }
        });

        function filterEvents() {
            const searchInput = document.getElementById('searchInput');
            const typeFilter = document.getElementById('typeFilter');
            if (!searchInput || !typeFilter) return;
            const searchTerm = searchInput.value.toLowerCase();
            const typeValue = typeFilter.value;
            const eventCards = document.querySelectorAll('.event-card');

            eventCards.forEach(card => {
                const title = card.getAttribute('data-title') || '';
                const type = card.getAttribute('data-type') || '';
                const matchesSearch = title.includes(searchTerm);
                const matchesType = typeValue === '' || type === typeValue;
                card.style.display = (matchesSearch && matchesType) ? 'block' : 'none';
            });
        }

        function registerEvent(eventId) {
            if (!confirm('Are you sure you want to register for this event?')) return;

            const formData = new FormData();
            formData.append('action', 'register');
            formData.append('eventId', eventId);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Registration failed: ' + (data.message || 'Please try again.'));
                }
            })
            .catch(error => {
                alert('An error occurred. Please try again.');
                console.error('Error:', error);
            });
        }

        function cancelRegistration(eventId) {
            if (!confirm('Are you sure you want to cancel this registration?')) return;

            const formData = new FormData();
            formData.append('action', 'cancel');
            formData.append('eventId', eventId);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Cancellation failed: ' + (data.message || 'Please try again.'));
                }
            })
            .catch(error => {
                alert('An error occurred. Please try again.');
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>