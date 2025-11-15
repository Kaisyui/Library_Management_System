<?php
session_start();
include('../include/db_connect.php');

//check if logged in as Student
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'student' || !isset($_SESSION['user_id'])){
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Handle return action from student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_book'])) {
    $book_code = $_POST['book_code'];

    // find latest active borrow record for this student and book
    $stmt = $conn->prepare("SELECT borrow_id FROM borrow_records WHERE student_id = ? AND book_code = ? AND status IN ('Borrowed','Overdue') ORDER BY borrow_date DESC LIMIT 1");
    $stmt->bind_param("ss", $student_id, $book_code);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res) {
        $borrow_id = $res['borrow_id'];
        // update borrow record
        $stmt = $conn->prepare("UPDATE borrow_records SET return_date = CURDATE(), status = 'Returned' WHERE borrow_id = ?");
        $stmt->bind_param("i", $borrow_id);
        $stmt->execute();
        $stmt->close();

        // increment book copies
        $stmt = $conn->prepare("UPDATE books SET copies = copies + 1 WHERE book_code = ?");
        $stmt->bind_param("s", $book_code);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: student_borrowed_books.php");
    exit();
}

//query borrowed books of this student
$borrow_query = "
    SELECT 
    b.book_code, 
    b.title, 
    br.borrow_date, 
    br.due_date, 
    br.status
    FROM borrow_records br
    INNER JOIN books b ON br.book_code = b.book_code
    WHERE br.student_id = ?
    ORDER BY br.borrow_date DESC
";
$stmt = $conn->prepare($borrow_query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$borrowed_books = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Borrowed Books</title>
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
                    <li><a href="student_borrowed_books.php" class="active">My Borrowed Books</a></li>
                    <li><a href="student_balance.php">Statement Of Account</a></li>
                    <li><a href="../index.php" class="Logout">Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <button class="menu-toggle" id="menuToggle">&#9776;</button>    
            <header>
                <h1>My Borrowed Books</h1>
            </header>

            <section class="borrowed-books-section">
                <h2>Borrowed Books</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Book Code</th>
                            <th>Title</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($borrowed_books && $borrowed_books->num_rows > 0): ?>
                            <?php while($row = $borrowed_books->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['book_code']); ?></td>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo htmlspecialchars($row['borrow_date']); ?></td>
                                <td><?php echo htmlspecialchars($row['due_date'] ?? 'Not Returned'); ?></td>
                                <td><?php echo isset($row['balance']) ? htmlspecialchars($row['balance']) : '0'; ?></td>
                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                <td>
                                    <?php if(in_array($row['status'], ['Borrowed','Overdue'])): ?>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="book_code" value="<?php echo htmlspecialchars($row['book_code']); ?>">
                                            <button type="submit" name="return_book" onclick="return confirm('Confirm return of this book?')">Return</button>
                                        </form>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">No borrowed books found.</td>
                            </tr>
                        <?php endif; ?>
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