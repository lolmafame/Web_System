<?php
session_start();
// Temporarily disable login restriction for testing
// if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'attendee'){
//     header("Location: login.php");
//     exit();
// }

// You can hardcode temporary test values
if(!isset($_SESSION['username'])) {
    $_SESSION['username'] = "Test Attendee";
}

$username = $_SESSION['username'];

// Sample events data (testing with 2 events only)
$events = [
    [
        'id' => 1,
        'title' => 'Digital Marketing Summit 2025',
        'type' => 'Conference',
        'icon' => '📊',
        'date' => 'November 15, 2025',
        'time' => '9:00 AM - 5:00 PM',
        'location' => 'Grand Convention Center',
        'available' => 45,
        'capacity' => 100,
        'description' => 'Join industry leaders to explore the latest trends in digital marketing and social media strategies.'
    ],
    [
        'id' => 2,
        'title' => 'Web Development Bootcamp',
        'type' => 'Workshop',
        'icon' => '💻',
        'date' => 'November 20, 2025',
        'time' => '10:00 AM - 4:00 PM',
        'location' => 'Tech Hub Innovation Center',
        'available' => 12,
        'capacity' => 30,
        'description' => 'Hands-on workshop covering modern web development technologies including React, Node.js, and more.'
    ]
];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['registrations'])) {
        $_SESSION['registrations'] = [];
    }
    
    switch ($_POST['action']) {
        case 'register':
            $eventId = intval($_POST['eventId']);
            $event = null;
            foreach ($events as $e) {
                if ($e['id'] == $eventId) {
                    $event = $e;
                    break;
                }
            }
            
            if ($event && !in_array($eventId, $_SESSION['registrations'])) {
                $_SESSION['registrations'][] = $eventId;
                echo json_encode(['success' => true, 'message' => 'Successfully registered!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Registration failed']);
            }
            exit();
            
        case 'cancel':
            $eventId = intval($_POST['eventId']);
            $_SESSION['registrations'] = array_filter($_SESSION['registrations'], function($id) use ($eventId) {
                return $id != $eventId;
            });
            echo json_encode(['success' => true, 'message' => 'Registration cancelled']);
            exit();
    }
}

// Get current tab
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// Initialize registrations if not set
if (!isset($_SESSION['registrations'])) {
    $_SESSION['registrations'] = [];
}

// Get registered events
$registeredEvents = array_filter($events, function($event) {
    return in_array($event['id'], $_SESSION['registrations']);
});
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

        /* Mobile Menu Toggle */
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

        /* Overlay for mobile */
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

        /* Tablet Responsive */
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

        /* Mobile Responsive */
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

        /* Small Mobile */
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

        /* Landscape Mobile */
        @media (max-width: 768px) and (orientation: landscape) {
            .sidebar {
                width: 240px;
            }

            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }

            .action-cards {
                grid-template-columns: repeat(4, 1fr);
            }

            .events-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .tickets-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Extra Small Devices */
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

            .stat-info h3 {
                font-size: 1.3rem;
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

        /* Print Styles */
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
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" id="mobileMenuBtn">
        ☰
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="toggleMobileMenu()" id="sidebarOverlay"></div>

    <div class="dashboard-container">
        <!-- Sidebar -->
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
                <a href="?tab=announcements" class="nav-item <?php echo $activeTab == 'announcements' ? 'active' : ''; ?>">
                    <span>Announcements</span>
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

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <h1>
                    <?php
                    switch($activeTab) {
                        case 'dashboard': echo 'Dashboard'; break;
                        case 'events': echo 'Browse Events'; break;
                        case 'registrations': echo 'My Registrations'; break;
                        case 'tickets': echo 'My Tickets'; break;
                        case 'announcements': echo 'Announcements'; break;
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
                <!-- Dashboard -->
                <div class="welcome-banner">
                    <div>
                        <h2>Welcome back, <?php echo htmlspecialchars($username); ?>! 👋</h2>
                        <p>Here's what's happening with your events</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card purple">
                        <div class="stat-icon"></div>
                        <div class="stat-info">
                            <h3><?php echo count(array_filter($events, function($e) { return $e['available'] > 0; })); ?></h3>
                            <p>Available Events</p>
                        </div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-icon"></div>
                        <div class="stat-info">
                            <h3><?php echo count($_SESSION['registrations']); ?></h3>
                            <p>My Registrations</p>
                        </div>
                    </div>
                    <div class="stat-card green">
                        <div class="stat-icon"></div>
                        <div class="stat-info">
                            <h3><?php echo count($_SESSION['registrations']); ?></h3>
                            <p>Confirmed Tickets</p>
                        </div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-icon"></div>
                        <div class="stat-info">
                            <h3>3</h3>
                            <p>New Announcements</p>
                        </div>
                    </div>
                </div>

                <div class="quick-actions">
                    <h3>Quick Actions</h3>
                    <div class="action-cards">
                        <a href="?tab=events" class="action-card">
                            <div class="action-card-icon"></div>
                            <h4>Browse Events</h4>
                            <p>Explore upcoming events</p>
                        </a>
                        <a href="?tab=registrations" class="action-card">
                            <div class="action-card-icon"></div>
                            <h4>My Registrations</h4>
                            <p>View registered events</p>
                        </a>
                        <a href="?tab=tickets" class="action-card">
                            <div class="action-card-icon"></div>
                            <h4>My Tickets</h4>
                            <p>Access your tickets</p>
                        </a>
                        <a href="?tab=announcements" class="action-card">
                            <div class="action-card-icon"></div>
                            <h4>Announcements</h4>
                            <p>Stay updated</p>
                        </a>
                    </div>
                </div>

            <?php elseif ($activeTab == 'events'): ?>
                <!-- Browse Events -->
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

                <div class="events-grid" id="eventsGrid">
                    <?php foreach ($events as $event): 
                        $isRegistered = in_array($event['id'], $_SESSION['registrations']);
                        $isFull = $event['available'] == 0;
                        $gradientColor = $event['type'] == 'Conference' ? 'linear-gradient(135deg, #667eea, #764ba2)' :
                                        ($event['type'] == 'Workshop' ? 'linear-gradient(135deg, #f093fb, #f5576c)' :
                                        'linear-gradient(135deg, #4facfe, #00f2fe)');
                    ?>
                        <div class="event-card" data-title="<?php echo strtolower($event['title']); ?>" data-type="<?php echo $event['type']; ?>">
                            <div class="event-image" style="background: <?php echo $gradientColor; ?>">
                                <span class="event-icon"><?php echo $event['icon']; ?></span>
                            </div>
                            <div class="event-content">
                                <span class="event-type"><?php echo $event['type']; ?></span>
                                <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                <p class="event-description"><?php echo htmlspecialchars($event['description']); ?></p>
                                <div class="event-details">
                                    <div class="detail-item">
                                        <span>📅</span>
                                        <span><?php echo $event['date']; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span>🕐</span>
                                        <span><?php echo $event['time']; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span>📍</span>
                                        <span><?php echo htmlspecialchars($event['location']); ?></span>
                                    </div>
                                </div>
                                <div class="capacity-bar">
                                    <div class="capacity-info">
                                        <span>Available Slots</span>
                                        <strong><?php echo $event['available']; ?>/<?php echo $event['capacity']; ?></strong>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo ($event['available'] / $event['capacity']) * 100; ?>%"></div>
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

            <?php elseif ($activeTab == 'registrations'): ?>
                <!-- My Registrations -->
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
                                <div class="registration-icon"><?php echo $event['icon']; ?></div>
                                <div class="registration-info">
                                    <div class="registration-header">
                                        <div>
                                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                            <span class="event-type"><?php echo $event['type']; ?></span>
                                        </div>
                                        <span class="status-badge">Confirmed</span>
                                    </div>
                                    <div class="registration-details">
                                        <div class="detail-item">
                                            <span>📅</span>
                                            <span><?php echo $event['date']; ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span>🕐</span>
                                            <span><?php echo $event['time']; ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span>📍</span>
                                            <span><?php echo htmlspecialchars($event['location']); ?></span>
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
                <!-- My Tickets -->
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
                                    <div class="ticket-icon"><?php echo $event['icon']; ?></div>
                                    <div>
                                        <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                        <span class="ticket-type"><?php echo $event['type']; ?></span>
                                    </div>
                                </div>
                                <div class="ticket-qr">
                                    <div class="qr-placeholder">QR CODE</div>
                                </div>
                                <div class="ticket-info">
                                    <div class="ticket-detail">
                                        <span class="label">Date</span>
                                        <span class="value"><?php echo $event['date']; ?></span>
                                    </div>
                                    <div class="ticket-detail">
                                        <span class="label">Time</span>
                                        <span class="value"><?php echo $event['time']; ?></span>
                                    </div>
                                    <div class="ticket-detail">
                                        <span class="label">Venue</span>
                                        <span class="value"><?php echo htmlspecialchars($event['location']); ?></span>
                                    </div>
                                    <div class="ticket-detail">
                                        <span class="label">Ticket ID</span>
                                        <span class="value">TKT-<?php echo $event['id']; ?>0<?php echo rand(100, 999); ?></span>
                                    </div>
                                </div>
                                <button class="btn-download" onclick="alert('Ticket download feature coming soon!')">Download Ticket</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($activeTab == 'announcements'): ?>
                <!-- Announcements -->
                <div class="page-header">
                    <h2>Announcements</h2>
                    <p>Stay updated with the latest news and updates</p>
                </div>

                <div class="announcements-list">
                    <div class="announcement-card">
                        <div class="announcement-icon new">🎉</div>
                        <div class="announcement-content">
                            <div class="announcement-header">
                                <h3>New Event: Digital Marketing Summit 2025</h3>
                                <span class="announcement-date">2 days ago</span>
                            </div>
                            <p>We're excited to announce our upcoming Digital Marketing Summit! Register now to secure your spot.</p>
                        </div>
                    </div>

                    <div class="announcement-card">
                        <div class="announcement-icon new">⚠️</div>
                        <div class="announcement-content">
                            <div class="announcement-header">
                                <h3>Venue Change: Web Development Bootcamp</h3>
                                <span class="announcement-date">5 days ago</span>
                            </div>
                            <p>Please note that the Web Development Bootcamp venue has been changed to Tech Hub Innovation Center.</p>
                        </div>
                    </div>

                    <div class="announcement-card">
                        <div class="announcement-icon">ℹ️</div>
                        <div class="announcement-content">
                            <div class="announcement-header">
                                <h3>Registration Reminder</h3>
                                <span class="announcement-date">1 week ago</span>
                            </div>
                            <p>Don't forget to complete your registration for upcoming events. Limited slots available!</p>
                        </div>
                    </div>
                </div>

            <?php elseif ($activeTab == 'profile'): ?>
                <!-- Edit Profile -->
                <div class="page-header">
                    <h2>Edit Profile</h2>
                    <p>Update your personal information</p>
                </div>

                <div class="profile-container">
                    <div class="profile-avatar">
                        <div class="avatar-circle">
                            <?php echo substr($username, 0, 1); ?>
                        </div>
                        <button class="btn-change-avatar" onclick="alert('Photo upload feature coming soon!')">Change Photo</button>
                    </div>

                    <form class="profile-form" method="POST" action="">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="fullname" value="<?php echo htmlspecialchars($username); ?>">
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="attendee@example.com">
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="+63 912 345 6789">
                        </div>
                        <div class="form-group">
                            <label>Organization</label>
                            <input type="text" name="organization" value="University of Caloocan City">
                        </div>
                        <div class="form-group">
                            <label>Bio</label>
                            <textarea name="bio" rows="4">Event enthusiast and lifelong learner</textarea>
                        </div>
                        <button type="submit" class="btn-save" onclick="alert('Profile updated successfully!'); return false;">Save Changes</button>
                    </form>
                </div>

            <?php endif; ?>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuBtn = document.getElementById('mobileMenuBtn');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            menuBtn.classList.toggle('active');
            
            // Update button icon
            if (sidebar.classList.contains('active')) {
                menuBtn.innerHTML = '✕';
            } else {
                menuBtn.innerHTML = '☰';
            }
        }

        // Close mobile menu when clicking on nav items
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    toggleMobileMenu();
                }
            });
        });

        // Handle window resize
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

        // Filter events by search and type
        function filterEvents() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const typeFilter = document.getElementById('typeFilter').value;
            const eventCards = document.querySelectorAll('.event-card');

            eventCards.forEach(card => {
                const title = card.getAttribute('data-title');
                const type = card.getAttribute('data-type');
                
                const matchesSearch = title.includes(searchTerm);
                const matchesType = typeFilter === '' || type === typeFilter;

                if (matchesSearch && matchesType) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Register for event
        function registerEvent(eventId) {
            if (confirm('Are you sure you want to register for this event?')) {
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
                        alert(data.message);
                    }
                })
                .catch(error => {
                    alert('An error occurred. Please try again.');
                    console.error('Error:', error);
                });
            }
        }

        // Cancel registration
        function cancelRegistration(eventId) {
            if (confirm('Are you sure you want to cancel this registration?')) {
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
                        alert('Cancellation failed');
                    }
                })
                .catch(error => {
                    alert('An error occurred. Please try again.');
                    console.error('Error:', error);
                });
            }
        }
    </script>
</body>
</html>