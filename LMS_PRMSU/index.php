<?php
session_start();
include('include/db_connect.php'); // database connection

// Handle login form submission
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role']; // 'admin' or 'student'
    $id = $_POST['id'];
    $password = $_POST['password'];

    if ($role === 'admin') {
        $sql = "SELECT * FROM admin WHERE admin_id=? AND password=?";
    } else {
        $sql = "SELECT * FROM students WHERE student_id=? AND password=?";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $id, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // Login success
        $_SESSION['role'] = $role;
        $_SESSION['user_id'] = $id;
        
        if ($role === 'admin') {
            header("Location: admin_page/admin_dashboard.php");
        } else {
            $_SESSION['student_id'] = $id;
            header("Location: student_page/student_dashboard.php");
        }
        exit();
    } else {
        echo "<script>alert('Invalid ID or Password'); window.location.href='index.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PRMSU LIBRARY MANAGEMENT SYSTEM</title>
        <link rel="stylesheet" href="style.css">
</head>
<body>

    <h1 class="h1" id="h1">PRESIDENT RAMON MAGSAYSAY STATE UNIVERSITY</h1>
    <p class="p" id="p">IBA MAIN CAMPUS</p>

    <?php if(isset($error)) { echo "<div class='error'>{$error}</div>"; } ?>

    <!-- Role Selection Buttons -->
    <div class="role-container">
        <h2 class="library">LIBRARY MANAGEMENT SYSTEM</h2>

        <div class="role-selection">
        <button onclick="showLogin('admin')">Admin</button>
        <button onclick="showLogin('student')">Student</button>
        </div>
    </div>
    <!-- Admin Login Form -->
    <div class="login-form" id="adminForm">
        <h2>ADMIN LOGIN</h2>
        <form method="POST">
            <input type="hidden" name="role" value="admin">
            <input type="text" name="id" placeholder="Admin ID" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <button onclick="hideLogin()">Back</button>
    </div>

    <!-- Student Login Form -->
    <div class="login-form" id="studentForm">
        <h2>STUDENT LOGIN</h2>
        <form method="POST">
            <input type="hidden" name="role" value="student">
            <input type="text" name="id" placeholder="Student ID" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <button onclick="hideLogin()">Back</button>
    </div>

    <script>
function showLogin(role){
    document.getElementById('adminForm').style.display = (role==='admin') ? 'block' : 'none';
    document.getElementById('studentForm').style.display = (role==='student') ? 'block' : 'none';
    // hide the role buttons
    document.querySelector('.role-selection').style.display = 'none';
}

function hideLogin(){
    document.getElementById('adminForm').style.display = 'none';
    document.getElementById('studentForm').style.display = 'none';
    // show the role buttons again
    document.querySelector('.role-selection').style.display = 'flex';
}

// hide forms initially
hideLogin();
</script>


</body>
</html>
