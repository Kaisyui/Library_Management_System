<?php
session_start();
include('../include/db_connect.php');

// Redirect if not logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Fetch student info
$stmt = $conn->prepare("SELECT first_name, last_name FROM students WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

// Fetch available books
$books_query = "SELECT book_code, title, author, publisher, copies FROM books";
$books_result = $conn->query($books_query);

// Handle borrow action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrow_book'])) {
    $book_code = $_POST['book_code'];
    $student_id = $_SESSION['user_id'];

    // Check available copies
    $stmt = $conn->prepare("SELECT copies FROM books WHERE book_code = ?");
    $stmt->bind_param("s", $book_code);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res || (int)$res['copies'] < 1) {
        // no copies available
        header("Location: student_dashboard.php?error=no_copies");
        exit();
    }

    // Prevent borrowing same book if already borrowed and not returned
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM borrow_records WHERE student_id = ? AND book_code = ? AND status IN ('Borrowed','Overdue')");
    $stmt->bind_param("ss", $student_id, $book_code);
    $stmt->execute();
    $cnt = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    if ($cnt > 0) {
        header("Location: student_dashboard.php?error=already_borrowed");
        exit();
    }

    // Insert borrow record (14-day due date)
    $borrow_date = date('Y-m-d');
    $due_date = date('Y-m-d', strtotime('+14 days'));
    $status = 'Borrowed';

    $stmt = $conn->prepare("INSERT INTO borrow_records (student_id, book_code, borrow_date, due_date, status) VALUES (?,?,?,?,?)");
    $stmt->bind_param("sssss", $student_id, $book_code, $borrow_date, $due_date, $status);
    $stmt->execute();
    $stmt->close();

    // Decrement copies
    $stmt = $conn->prepare("UPDATE books SET copies = copies - 1 WHERE book_code = ?");
    $stmt->bind_param("s", $book_code);
    $stmt->execute();
    $stmt->close();

    header("Location: student_dashboard.php?success=borrowed");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
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
                    <li><a href="student_dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="student_borrowed_books.php">My Borrowed Books</a></li>
                    <li><a href="student_balance.php">Statement of Account</a></li>
                    <li><a href="../index.php" class="Logout">Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content" id="main">
            <button class="menu-toggle" id="menuToggle">&#9776;</button>    
            <header>
                <h1>Welcome, <?php echo $student['first_name'] . ' ' . $student['last_name']; ?>!</h1>
                <h2>Available Books</h2>
            </header>

            <!-- Search Bar -->
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search by Book, Author, Publisher...">
            </div>

            <!-- Books Table -->
            <section class="books-table">
                <table>
                    <thead>
                        <tr>
                            <th>Book Code</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Publisher</th>
                            <th>Copies</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="booksTableBody">
                        <?php if ($books_result->num_rows > 0): ?>
                            <?php while ($book = $books_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($book['book_code']); ?></td>
                                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td><?php echo htmlspecialchars($book['publisher']); ?></td>
                                    <td><?php echo (int)$book['copies']; ?></td>
                                    <td class="status-cell">
                                        <?php if ((int)$book['copies'] > 0): ?>
                                            <span class="available">Available</span>
                                        <?php else: ?>
                                            <span class="unavailable">Unavailable</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$book['copies'] > 0): ?>
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="book_code" value="<?php echo htmlspecialchars($book['book_code']); ?>">
                                                <button type="submit" name="borrow_book">Borrow</button>
                                            </form>
                                        <?php else: ?>
                                            <button disabled>Unavailable</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No books available.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <script>
        // Live search filter
        const searchInput = document.getElementById('searchInput');
        const booksTable = document.getElementById('booksTableBody');

        searchInput.addEventListener('keyup', function() {
            const filter = searchInput.value.toLowerCase();
            const rows = booksTable.getElementsByTagName('tr');

            Array.from(rows).forEach(row => {
                const title = row.cells[1].textContent.toLowerCase();
                const author = row.cells[2].textContent.toLowerCase();
                const publisher = row.cells[3].textContent.toLowerCase();

                if (title.includes(filter) || author.includes(filter) || publisher.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none'; 
                }
            });
        });
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

x`