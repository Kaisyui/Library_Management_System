<?php
session_start();
include('../include/db_connect.php');
//session check
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit();
}

//fetch all borrow records
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

// Handle return action by admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_borrow'])) {
    $borrow_id = (int)$_POST['borrow_id'];

    // get book_code for this borrow
    $stmt = $conn->prepare("SELECT book_code FROM borrow_records WHERE borrow_id = ?");
    $stmt->bind_param("i", $borrow_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $book_code = $row['book_code'];
        // update record
        $stmt = $conn->prepare("UPDATE borrow_records SET return_date = CURDATE(), status = 'Returned' WHERE borrow_id = ?");
        $stmt->bind_param("i", $borrow_id);
        $stmt->execute();
        $stmt->close();

        // increment copies
        $stmt = $conn->prepare("UPDATE books SET copies = copies + 1 WHERE book_code = ?");
        $stmt->bind_param("s", $book_code);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: admin_borrowers.php");
    exit();
}

// Handle edit due date action by admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_due'])) {
    $borrow_id = isset($_POST['borrow_id']) ? (int)$_POST['borrow_id'] : 0;
    $new_due = isset($_POST['new_due_date']) ? trim($_POST['new_due_date']) : '';

    if ($borrow_id <= 0 || empty($new_due)) {
        header("Location: admin_borrowers.php?error=invalid");
        exit();
    }

    // validate date format
    try {
        $dt = new DateTime($new_due);
        $formatted_due = $dt->format('Y-m-d');
    } catch (Exception $e) {
        header("Location: admin_borrowers.php?error=bad_date");
        exit();
    }

    // fetch current borrow record to check return_date
    $stmt = $conn->prepare("SELECT return_date FROM borrow_records WHERE borrow_id = ?");
    $stmt->bind_param("i", $borrow_id);
    $stmt->execute();
    $br = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$br) {
        header("Location: admin_borrowers.php?error=notfound");
        exit();
    }

    // Determine new status only if not yet returned
    $new_status = null;
    if (empty($br['return_date'])) {
        $today = new DateTime();
        $due_dt = new DateTime($formatted_due);
        $new_status = ($today > $due_dt) ? 'Overdue' : 'Borrowed';
    }

    // update due_date (and status if applicable)
    if ($new_status !== null) {
        $stmt = $conn->prepare("UPDATE borrow_records SET due_date = ?, status = ? WHERE borrow_id = ?");
        $stmt->bind_param("ssi", $formatted_due, $new_status, $borrow_id);
    } else {
        $stmt = $conn->prepare("UPDATE borrow_records SET due_date = ? WHERE borrow_id = ?");
        $stmt->bind_param("si", $formatted_due, $borrow_id);
    }

    $stmt->execute();
    $stmt->close();

    header("Location: admin_borrowers.php?success=due_updated");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowers</title>
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
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="admin_borrowers.php" class="active">Borrowers</a></li>
                <li><a href="admin_payments.php">Payments</a></li>
                <li><a href="../index.php" class="Logout">Logout</a></li>
            </ul>
        </nav>
    </aside>
    <!-- Main Content -->
    <main class="main-content" id="main">
        <header>
            <button class="menu-toggle" id="menuToggle">&#9776;</button>    
            <h1>Borrowers</h1>
        </header>
        <section class="borrowers-section">
            <table>
                <thead>
                    <tr>
                        <th>Borrow ID</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Book Code</th>
                        <th>Title</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['borrow_id']; ?></td>
                        <td><?php echo $row['student_id']; ?></td>
                        <td><?php echo $row['student_name']; ?></td>
                        <td><?php echo $row['book_code']; ?></td>
                        <td><?php echo $row['title']; ?></td>
                        <td><?php echo $row['borrow_date']; ?></td>
                        <td><?php echo $row['due_date']; ?></td>
                        <td><?php echo $row['return_date'] ? $row['return_date'] : 'N/A'; ?></td>
                        <td><?php echo number_format($row['payment'], 2); ?></td>
                        <td><?php echo $row['status']; ?></td>
                        <td>
                            <?php if($row['status'] !== 'Returned'): ?>
                                    <!-- Edit due date form -->
                                <form method="POST" style="display:inline; margin-right:6px;">
                                    <input type="hidden" name="borrow_id" value="<?php echo $row['borrow_id']; ?>">
                                    <input type="date" name="new_due_date" value="<?php echo $row['due_date']; ?>" required>
                                    <button type="submit" name="edit_due" onclick="return confirm('Update due date to the selected value?')">Update Due</button>
                                </form>

                                <!-- Mark returned form -->
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="borrow_id" value="<?php echo $row['borrow_id']; ?>">
                                    <button type="submit" name="return_borrow" onclick="return confirm('Mark this book as returned?')">Mark Returned</button>
                                </form>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
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
