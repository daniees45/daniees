<?php
// as/api/register_public.php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    $lecturer_id = ($role === 'lecturer' && !empty($_POST['lecturer_id'])) ? $_POST['lecturer_id'] : null;

    // Security: Prevent public registration of super_admin
    if ($role === 'super_admin') {
        die(json_encode(["status" => "error", "message" => "Registration for Super Admin is restricted."]));
    }

    if (empty($username) || empty($password) || empty($fullname)) {
        die(json_encode(["status" => "error", "message" => "Please fill all required fields."]));
    }

    // Check if username exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        die(json_encode(["status" => "error", "message" => "Username already exists."]));
    }

    // Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $conn->prepare("INSERT INTO users (username, full_name, role, password_hash, lecturer_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $username, $fullname, $role, $hash, $lecturer_id);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Account created! You can now log in."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
    }
}
?>
