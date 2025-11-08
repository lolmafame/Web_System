<?php
session_start();
if(!isset($_SESSION['username'])) {
    $_SESSION['username'] = "Admin Organizer";
}
$username = $_SESSION['username'];

// remove hard-coded samples and load from DB (fallback to session if DB unavailable)
$events = [];
$attendees = [];
try {
    $dsn = 'mysql:host=localhost;dbname=event_management;charset=utf8mb4';
    $dbUser = 'root';
    $dbPass = '';
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Load events with registered/available counts
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

    // Load recent attendee registrations (join to users)
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
        /* (Styles unchanged; omitted here for brevity in this snippet – keep your existing CSS) */
    </style>
</head>
<body>
    <!-- (Layout / sidebar / main content unchanged) -->

    <!-- Modal -->
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

                <!-- CHANGED: Date and Time selectors -->
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
                            <!-- JS will populate options from 7:00 AM to 9:00 PM -->
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
        let editingEventId = null;

        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.mobile-overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
        function closeMobileMenu() {
            document.getElementById('sidebar').classList.remove('active');
            document.querySelector('.mobile-overlay').classList.remove('active');
        }

        function openCreateModal() {
            editingEventId = null;
            document.getElementById('modalTitle').textContent = 'Create New Event';
            document.getElementById('eventForm').reset();
            document.getElementById('eventId').value = '';
            populateTimes(); // ensure times are loaded
            document.getElementById('eventModal').classList.add('active');
        }

        function editEvent(eventId) {
            editingEventId = eventId;
            document.getElementById('modalTitle').textContent = 'Edit Event';
            // TODO: Populate form with existing event data (fetch or inline)
            alert('Edit event #' + eventId + '\\n\\nThis would populate the form with event data.');
            populateTimes();
            document.getElementById('eventModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('eventModal').classList.remove('active');
            document.getElementById('eventForm').reset();
            editingEventId = null;
        }

        function handleEventSubmit(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            formData.append('action', editingEventId ? 'update_event' : 'create_event');
            if (editingEventId) {
                formData.append('eventId', editingEventId);
            }
            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        closeModal();
                        location.reload();
                    } else {
                        alert('Operation failed: ' + data.message);
                    }
                })
                .catch(err => {
                    alert('An error occurred. Please try again.');
                    console.error(err);
                });
            return false;
        }

        function toggleStatus(eventId) {
            if (confirm('Are you sure you want to toggle the status of this event?')) {
                const formData = new FormData();
                formData.append('action', 'toggle_status');
                formData.append('eventId', eventId);
                fetch('', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            location.reload();
                        } else {
                            alert('Operation failed');
                        }
                    });
            }
        }

        function deleteEvent(eventId) {
            if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('action', 'delete_event');
                formData.append('eventId', eventId);
                fetch('', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            location.reload();
                        } else {
                            alert('Deletion failed');
                        }
                    });
            }
        }

        // NEW: Populate time select with hourly slots 7 AM to 9 PM
        function populateTimes() {
            const select = document.getElementById('eventTime');
            if (!select) return;
            select.innerHTML = '<option value="">Select Time</option>';
            const startHour = 7;
            const endHour = 21;
            for (let h = startHour; h <= endHour; h++) {
                const hour12 = ((h + 11) % 12) + 1;
                const ampm = h < 12 ? 'AM' : 'PM';
                const label = hour12 + ':00 ' + ampm; // e.g., 7:00 AM
                // Store the label; backend keeps using free-form time_text
                select.insertAdjacentHTML('beforeend', `<option value="${label}">${label}</option>`);
            }
        }

        // Populate immediately in case modal starts open (optional)
        populateTimes();

        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeMobileMenu();
            }
        });
    </script>
</body>
</html>