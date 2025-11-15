<?php
session_start();
include '../include/db_connect.php';

// Check if logged in as Admin
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit();
}

// Fetch admin info
$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT first_name, last_name FROM admin WHERE admin_id = ?");
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// If admin not found, logout
if (!$admin) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ADMIN DASHBOARD</title>
<link rel="stylesheet" href="../style.css">
</head>
<body>

<div class="dashboard-container">
    <!--Sidebar-->
    <aside class="sidebar" id="sidebar">
        <button class="back-btn" id="backBtn">←</button>
        <h2>Admin Dashboard</h2>
        <ul>
            <li><a href="admin_dashboard.php" class="active">Dashboard</a></li>
            <li><a href="admin_students.php">Manage Students</a></li>
            <li><a href="admin_books.php">Manage Books</a></li>
            <li><a href="admin_reports.php">Reports</a></li>
            <li><a href="admin_borrowers.php">Borrowers</a></li>
            <li><a href="admin_payments.php">Payments</a></li>
            <li><a href="../index.php">Logout</a></li>
        </ul>
    </aside>

    <!--Main Content-->
    <main class="main-content" id="main">
        <header>
            <button class="menu-toggle" id="menuToggle">&#9776;</button>    
            <div>
                <h1>Welcome, <?php echo $admin['first_name'] . ' ' . $admin['last_name']; ?>!</h1>
                <p>Library Management System Dashboard</p>
            </div>
        </header>

        <section class="stats">
            <?php
            $total_books = $conn->query("SELECT COUNT(*) as total FROM books")->fetch_assoc()['total'];
            $borrowed_books = $conn->query("SELECT COUNT(*) as total FROM borrow_records WHERE status='Borrowed'")->fetch_assoc()['total'];
            $total_students = $conn->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'];
            $overdue_books = $conn->query("SELECT COUNT(*) as total FROM borrow_records WHERE status='Overdue'")->fetch_assoc()['total'];
            ?>
            <div class="cards">
                <h3>Total Books</h3>
                <p><?php echo $total_books; ?></p>
            </div>
            <div class="cards">
                <h3>Borrowed Books</h3>
                <p><?php echo $borrowed_books; ?></p>
            </div>
            <div class="cards">
                <h3>Registered Students</h3>
                <p><?php echo $total_students; ?></p>
            </div>
            <div class="cards">
                <h3>Overdue Books</h3>
                <p><?php echo $overdue_books; ?></p>
            </div>
        </section>

        <section class="recent-activity">
            <h2>Recent Activities</h2>
            <table>
                <thead>
                    <tr>
                        <th>Book Title</th>
                        <th>Borrower</th>
                        <th>Date Borrowed</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $activities = $conn->query("
                        SELECT b.book_code, b.title, s.first_name, s.last_name, br.borrow_date, br.status 
                        FROM borrow_records br
                        JOIN books b ON br.book_code = b.book_code
                        JOIN students s ON br.student_id = s.student_id
                        ORDER BY br.borrow_date DESC LIMIT 5
                    ");
                    while($row = $activities->fetch_assoc()){
                        echo "<tr>
                            <td>{$row['title']}</td>
                            <td>{$row['first_name']} {$row['last_name']}</td>
                            <td>{$row['borrow_date']}</td>
                            <td>{$row['status']}</td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const main = document.getElementById('main');
const backBtn = document.getElementById('backBtn');

menuToggle.addEventListener('click', () => {
  sidebar.classList.toggle('open');
  main.classList.toggle('shifted');
});

backBtn.addEventListener('click', () => {
  sidebar.classList.remove('open');
  main.classList.remove('shifted');
});
});
</script>

</body>
</html>
