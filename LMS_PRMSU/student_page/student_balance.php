<?php
session_start();
include '../include/db_connect.php';
// redirect if not logged in
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'student' || !isset($_SESSION['user_id'])){
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Payment handler - pay for a specific borrow record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {
    $borrow_id = isset($_POST['borrow_id']) ? (int)$_POST['borrow_id'] : 0;
    $fee_per_day = 10; // keep consistent
    $allowed_methods = ['cash', 'gcash', 'maya', 'paypal'];
    $method = isset($_POST['method']) ? trim($_POST['method']) : 'cash';
    if (!in_array($method, $allowed_methods, true)) {
        header('Location: student_balance.php?error=bad_method');
        exit();
    }

    // load borrow record and ensure it belongs to current student
    $stmt = $conn->prepare("SELECT borrow_id, due_date, return_date, payment FROM borrow_records WHERE borrow_id = ? AND student_id = ?");
    $stmt->bind_param("is", $borrow_id, $student_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        header('Location: student_balance.php?error=notfound');
        exit();
    }

    // compute overdue days
    $overdue_days = 0;
    $due = new DateTime($row['due_date']);
    if ($row['return_date']) {
        $ret = new DateTime($row['return_date']);
        if ($ret > $due) $overdue_days = $due->diff($ret)->days;
    } else {
        $today = new DateTime();
        if ($today > $due) $overdue_days = $due->diff($today)->days;
    }

    $amount_due = $overdue_days * $fee_per_day;
    $already_paid = (float)$row['payment'];
    $amount_to_pay = max(0, $amount_due - $already_paid);

    if ($amount_to_pay <= 0) {
        header('Location: student_balance.php?info=nopayment');
        exit();
    }

    // If method is 'cash' do immediate payment insertion; otherwise create pending payment and redirect to checkout
    if ($method === 'cash') {
        // Transaction: insert payment and update borrow_records
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO payments (student_id, borrow_id, amount, method) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sids", $student_id, $borrow_id, $amount_to_pay, $method);
            $stmt->execute();
            $stmt->close();

            $new_payment = $already_paid + $amount_to_pay;
            $new_status = ($new_payment > 0) ? 'Paid' : 'Unpaid';
            $stmt = $conn->prepare("UPDATE borrow_records SET payment = ?, payment_status = ? WHERE borrow_id = ?");
            $stmt->bind_param("dsi", $new_payment, $new_status, $borrow_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            header('Location: student_balance.php?success=paid');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            header('Location: student_balance.php?error=fail');
            exit();
        }
    }

    // For online methods (gcash, maya, paypal): create pending payment and redirect to local checkout page
    $conn->begin_transaction();
    try {
        $status = 'Pending';
        $stmt = $conn->prepare("INSERT INTO payments (student_id, borrow_id, amount, method) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sids", $student_id, $borrow_id, $amount_to_pay, $method);
        $stmt->execute();
        $payment_id = $stmt->insert_id;
        $stmt->close();

        // mark borrow record as having a pending payment
        $stmt = $conn->prepare("UPDATE borrow_records SET payment_status = ? WHERE borrow_id = ?");
        $stmt->bind_param("si", $status, $borrow_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        // redirect to a local checkout page (simulate or provide instructions). Replace this with real gateway redirect later.
        header('Location: payment_checkout.php?payment_id=' . (int)$payment_id);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        header('Location: student_balance.php?error=fail');
        exit();
    }

}

//fetch borrowed books & payment status
$stmt = $conn->prepare(
    "SELECT br.borrow_id, b.book_code, b.title, br.borrow_date, br.due_date, br.return_date, br.status, br.payment, br.payment_status
    FROM borrow_records br
    JOIN books b ON br.book_code = b.book_code
    WHERE br.student_id = ?
    ORDER BY br.due_date ASC"
);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$borrowed_books = [];
while ($r = $result->fetch_assoc()) {
    $borrowed_books[] = $r;
}
$stmt->close();

//fee settings
$daily_late_fee = 10; //fee per day
$total_due = 0.0;
// We'll compute totals later when rendering rows
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statement of Account</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <button class="back-btn" id="backBtn">←</button>
            <h2>My Account</h2>
            <nav>
                <ul>
                    <li><a href="student_dashboard.php">Dashboard</a></li>
                    <li><a href="student_borrowed_books.php">My Borrowed Books</a></li>
                    <li><a href="student_balance.php" class="active">Statement Of Account</a></li>
                    <li><a href="../index.php" class="Logout">Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <button class="menu-toggle" id="menuToggle">&#9776;</button>    
            <header>
                <h1>Statement of Account</h1>
            </header>

            <section class="balance-section">
                <h2>Account Balance</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Book Code</th>
                            <th>Title</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Payment</th>
                            <th>Balance</th>
                            <th>Total</th>
                            <th>Status</th>

                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $today = new DateTime();
                        foreach ($borrowed_books as $book):
                            $due_date = new DateTime($book['due_date']);
                            $return_date = $book['return_date'] ? new DateTime($book['return_date']) : null;

                            // Determine overdue days
                            if ($return_date) {
                                $interval = $due_date->diff($return_date);
                                $overdue_days = ($return_date > $due_date) ? $interval->days : 0;
                            } else {
                                $interval = $due_date->diff($today);
                                $overdue_days = ($today > $due_date) ? $interval->days : 0;
                            }

                            $amount_due = $overdue_days * $daily_late_fee;
                            $already_paid = (float)$book['payment'];
                            $amount_to_pay = max(0, $amount_due - $already_paid);
                            $total_due += $amount_to_pay;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($book['book_code']); ?></td>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['borrow_date']); ?></td>
                            <td><?php echo htmlspecialchars($book['due_date']); ?></td>
                            <td><?php echo $book['return_date'] ? htmlspecialchars($book['return_date']) : 'Not Returned'; ?></td>
                            <td><?php echo $overdue_days > 0 ? 'Overdue by ' . $overdue_days . ' days' : 'On Time'; ?></td>
                            <td><?php echo number_format($amount_to_pay, 2); ?></td>
                            <td><?php echo number_format($already_paid, 2); ?></td>
                            <td class="<?php echo ($book['payment_status']=== 'Paid') ? 'paid' : 'unpaid'; ?>">
                                <?php echo htmlspecialchars($book['payment_status'] ?? 'Unpaid'); ?>
                                <?php if ($amount_to_pay > 0): ?>
                                        <form method="POST" action="student_balance.php" onsubmit="return confirm('Confirm payment for this book?');">
                                            <input type="hidden" name="borrow_id" value="<?php echo (int)$book['borrow_id']; ?>">
                                            <label style="display:inline-block; margin-right:8px;">
                                                <select name="method" required>
                                                    <option value="cash">Cash (on-site)</option>
                                                    <option value="gcash">GCash</option>
                                                    <option value="maya">Maya</option>
                                                    <option value="paypal">PayPal</option>
                                                </select>
                                            </label>
                                            <button type="submit" name="pay_now">Pay Now</button>
                                        </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="total-due">
                <h3>Total Amount Due: <?php echo number_format($total_due, 2); ?></h3>
                </div>
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
<?php


                        



                        