<?php
// process_registration.php
session_start();

// Database configuration (adjust these to your phpMyAdmin settings)
$servername = "localhost"; // or your server
$username = "root"; // your db username
$password = ""; // your db password
$dbname = "event_management"; // your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate inputs
    $role = trim($_POST['role']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $organization = isset($_POST['organization']) ? trim($_POST['organization']) : null;
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $terms = isset($_POST['terms']) ? true : false;

    // Server-side validation
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

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = "Email already registered.";
    }
    $stmt->close();

    if (empty($errors)) {
        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Prepare insert statement
        $stmt = $conn->prepare("INSERT INTO users (role, first_name, last_name, email, phone, organization, password_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssss", $role, $first_name, $last_name, $email, $phone, $organization, $password_hash);

        if ($stmt->execute()) {
            // Registration successful - log the user in
            $new_user_id = $stmt->insert_id;
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['role'] = $role;
            $_SESSION['username'] = trim($first_name . ' ' . $last_name);

            // Redirect to appropriate home page
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

    // If there are errors, store them in session and redirect back to the registration page
    if (!empty($errors)) {
        // For simplicity, you can store errors in session and redirect back
        session_start();
        $_SESSION['registration_errors'] = $errors;
        header("Location: login.php"); // Back to the form
        exit();
    }
}

$conn->close();
?>
