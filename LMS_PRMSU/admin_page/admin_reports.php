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

// Fetch borrow records
$borrow_query = "
    SELECT br.borrow_id, s.student_id,
           CONCAT(s.last_name, ', ', s.first_name, ' ', s.middle_initial) AS student_name,
           b.book_code,
           b.title,
           br.borrow_date,
           br.due_date,
           br.return_date,
           br.payment,
           br.status
    FROM borrow_records br
    JOIN books As b ON br.book_code = b.book_code
    JOIN students As s ON br.student_id = s.student_id
    ORDER BY br.borrow_date DESC
";
$result = $conn->query($borrow_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <button class="back-btn" id="backBtn">←</button>
        <h2>Admin Dashboard</h2>
        <nav>
            <ul>
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="admin_students.php">Manage Students</a></li>
                <li><a href="admin_books.php">Manage Books</a></li>
                <li><a href="admin_reports.php" class="active">Reports</a></li>
                <li><a href="admin_borrowers.php">Borrowers</a></li>
                <li><a href="admin_payments.php">Payments</a></li>
                <li><a href="../index.php" class="Logout">Logout</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="main">
        <header>
            <button class="menu-toggle" id="menuToggle">&#9776;</button>
            <h1>Reports</h1>
        </header>

        <section class="reports-section">
            <table>
                <thead>
                    <tr>
                        <th>Borrow ID</th>
                        <th>Book Title</th>
                        <th>Student Name</th>
                        <th>Date Borrowed</th>
                        <th>Due Date</th>
                        <th>Date Returned</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['borrow_id']; ?></td>
                            <td><?php echo $row['title']; ?></td>
                            <td><?php echo $row['student_name']; ?></td>
                            <td><?php echo $row['borrow_date']; ?></td>
                            <td><?php echo $row['due_date']; ?></td>
                            <td><?php echo $row['return_date'] ? $row['return_date'] : 'Not returned'; ?></td>
                            <td><?php echo ucfirst($row['status']); ?></td>
                        </tr>
                    <?php endwhile; ?>
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
