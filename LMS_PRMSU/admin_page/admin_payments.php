<?php
session_start();
include '../include/db_connect.php';
// session check
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit();
}
// Handle admin actions: verify or reject payments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify payment
    if (isset($_POST['verify_payment'])) {
        $payment_id = (int)($_POST['payment_id'] ?? 0);
        $admin_note = trim($_POST['admin_note'] ?? '');

        if ($payment_id > 0) {
            // load payment
            $stmt = $conn->prepare("SELECT payment_id, student_id, borrow_id, amount, method FROM payments WHERE payment_id = ?");
            $stmt->bind_param('i', $payment_id);
            $stmt->execute();
            $payment = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($payment) {
                $borrow_id = (int)$payment['borrow_id'];
                $amount = (float)$payment['amount'];

                $conn->begin_transaction();
                try {
                    // update borrow_records payment total
                    $stmt = $conn->prepare("SELECT payment FROM borrow_records WHERE borrow_id = ? FOR UPDATE");
                    $stmt->bind_param('i', $borrow_id);
                    $stmt->execute();
                    $br = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    $current = $br ? (float)$br['payment'] : 0.0;
                    $new_total = $current + $amount;

                    $new_status = ($new_total > 0) ? 'Paid' : 'Unpaid';
                    $stmt = $conn->prepare("UPDATE borrow_records SET payment = ?, payment_status = ? WHERE borrow_id = ?");
                    $stmt->bind_param('dsi', $new_total, $new_status, $borrow_id);
                    $stmt->execute();
                    $stmt->close();

                    // update payments row: set status to Completed and paid_at if exists
                    $has_paid_at = false;
                    $schemaStmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'paid_at'");
                    $schemaStmt->execute();
                    $colsRes = $schemaStmt->get_result();
                    while ($c = $colsRes->fetch_assoc()) { if ($c['COLUMN_NAME'] === 'paid_at') $has_paid_at = true; }
                    $schemaStmt->close();

                    if ($has_paid_at) {
                        $stmt = $conn->prepare("UPDATE payments SET status = 'Completed', paid_at = NOW() WHERE payment_id = ?");
                        $stmt->bind_param('i', $payment_id);
                    } else {
                        $stmt = $conn->prepare("UPDATE payments SET status = 'Completed' WHERE payment_id = ?");
                        $stmt->bind_param('i', $payment_id);
                    }
                    $stmt->execute();
                    $stmt->close();

                    // optionally record admin note if column exists
                    $has_note = false;
                    $schemaStmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'admin_note'");
                    $schemaStmt->execute();
                    $colsRes = $schemaStmt->get_result();
                    while ($c = $colsRes->fetch_assoc()) { if ($c['COLUMN_NAME'] === 'admin_note') $has_note = true; }
                    $schemaStmt->close();
                    if ($has_note && $admin_note !== '') {
                        $stmt = $conn->prepare("UPDATE payments SET admin_note = ? WHERE payment_id = ?");
                        $stmt->bind_param('si', $admin_note, $payment_id);
                        $stmt->execute();
                        $stmt->close();
                    }

                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollback();
                    // fall through and show error via redirect
                }
            }
        }

        header("Location: admin_payments.php");
        exit();
    }

    // Reject payment
    if (isset($_POST['reject_payment'])) {
        $payment_id = (int)($_POST['payment_id'] ?? 0);
        $reason = trim($_POST['reject_reason'] ?? '');
        if ($payment_id > 0) {
            // mark payment as Rejected if column exists
            $stmt = $conn->prepare("UPDATE payments SET status = 'Rejected' WHERE payment_id = ?");
            $stmt->bind_param('i', $payment_id);
            $stmt->execute();
            $stmt->close();

            // optional store reject reason if column exists
            $has_reason = false;
            $schemaStmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'reject_reason'");
            $schemaStmt->execute();
            $colsRes = $schemaStmt->get_result();
            while ($c = $colsRes->fetch_assoc()) { if ($c['COLUMN_NAME'] === 'reject_reason') $has_reason = true; }
            $schemaStmt->close();
            if ($has_reason && $reason !== '') {
                $stmt = $conn->prepare("UPDATE payments SET reject_reason = ? WHERE payment_id = ?");
                $stmt->bind_param('si', $reason, $payment_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        header("Location: admin_payments.php");
        exit();
    }
}

// Fetch payments (show latest first)
$payments_query = "
    SELECT p.payment_id, p.student_id, p.borrow_id, p.amount, p.method, p.transaction_ref, p.paid_at, p.status,
           s.first_name, s.last_name, s.middle_initial,
           b.book_code, b.title, br.borrow_date, br.due_date, br.return_date
    FROM payments p
    LEFT JOIN borrow_records br ON p.borrow_id = br.borrow_id
    LEFT JOIN books b ON br.book_code = b.book_code
    LEFT JOIN students s ON p.student_id = s.student_id
    ORDER BY p.payment_id DESC
";
$result = $conn->query($payments_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments</title>
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
                <li><a href="admin_borrowers.php">Borrowers</a></li>
                <li><a href="admin_payments.php"class="active">Payments</a></li>
                <li><a href="../index.php" class="Logout">Logout</a></li>
            </ul>
        </nav>
    </aside>
    <!-- Main Content -->
    <main class="main-content" id="main">
        <header>
            <button class="menu-toggle" id="menuToggle">&#9776;</button> 
            <h1>Payments</h1>   
        </header>
    
     <section class= "payments-section">
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
                        <td><?php echo $row['return_date'] ?? 'Not Returned'; ?></td>
                        <td><?php echo number_format($row['payment'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['status'] ?? ''); ?></td>
                                <td>
                                    <?php if (isset($row['status']) && strtolower($row['status']) === 'pending'): ?>
                                        <form method="POST" style="display:inline-block; margin-right:6px;">
                                            <input type="hidden" name="payment_id" value="<?php echo (int)$row['payment_id']; ?>">
                                            <label style="display:block;">Admin Note (optional):<br>
                                                <input type="text" name="admin_note" placeholder="Note">
                                            </label>
                                            <button type="submit" name="verify_payment" onclick="return confirm('Verify and mark as paid?')">Verify</button>
                                        </form>

                                        <form method="POST" style="display:inline-block;">
                                            <input type="hidden" name="payment_id" value="<?php echo (int)$row['payment_id']; ?>">
                                            <label style="display:block;">Reject reason (optional):<br>
                                                <input type="text" name="reject_reason" placeholder="Reason">
                                            </label>
                                            <button type="submit" name="reject_payment" onclick="return confirm('Reject this payment?')">Reject</button>
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