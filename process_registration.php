<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "event_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role = trim($_POST['role']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $organization = isset($_POST['organization']) ? trim($_POST['organization']) : null;
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $terms = isset($_POST['terms']) ? true : false;

    $errors = [];

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $errors[] = "Required fields are missing.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password does not meet requirements.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (!$terms) {
        $errors[] = "You must agree to the Terms and Conditions.";
    }

    if ($role !== 'attendee' && $role !== 'organizer') {
        $errors[] = "Invalid role selected.";
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = "Email already registered.";
    }
    $stmt->close();

    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (role, first_name, last_name, email, phone, organization, password_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssss", $role, $first_name, $last_name, $email, $phone, $organization, $password_hash);

        if ($stmt->execute()) {
            $new_user_id = $stmt->insert_id;
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['role'] = $role;
            $_SESSION['username'] = trim($first_name . ' ' . $last_name);

            if ($role === 'organizer') {
                header("Location: login.php");
                exit();
            } else {
                header("Location: login.php");
                exit();
            }
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
        $stmt->close();
    }

    if (!empty($errors)) {
        $_SESSION['registration_errors'] = $errors;
        header("Location: login.php");
        exit();
    }
}

$conn->close();
?>
