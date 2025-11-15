<?php
session_start();
include '../include/db_connect.php';

// Check if logged in as Admin
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit();
}

// Handle Add / Edit / Delete actions
if(isset($_POST['add_book'])){
    $book_code = $_POST['book_code'];
    $title = $_POST['title'];
    $author = $_POST['author'];
    $publisher = $_POST['publisher'];
    $copies = $_POST['copies'];

    $stmt = $conn->prepare("INSERT INTO books (book_code, title, author, publisher, copies) VALUES (?,?,?,?,?)");
    $stmt->bind_param("ssssi",$book_code, $title, $author, $publisher, $copies);
    $stmt->execute();
    header("Location: admin_books.php");
}


if(isset($_GET['edit'])){
    $book_code = $_GET['edit'];
    $edit_book = $conn->query("SELECT * FROM books WHERE book_code='$book_code'")->fetch_assoc();
}
if(isset($_GET['delete'])){
    $book_code = $_GET['delete'];
    $conn->query("DELETE FROM books WHERE book_code='$book_code'");
    header("Location: admin_books.php");
}

// Fetch all books
$books = $conn->query("SELECT * FROM books ORDER BY title ASC");
// Ensure $edit_book is defined to avoid undefined variable notices
$edit_book = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Books</title>
<link rel="stylesheet" href="../style.css">
</head>
<body>

<div class="dashboard-container">
    <!--Sidebar-->
    <aside class="sidebar" id="sidebar">
        <button class="back-btn" id="backBtn">←</button>
        <h2>Admin Dashboard</h2>
        <nav>
            <ul>
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="admin_students.php">Manage Students</a></li>
                <li><a href="admin_books.php" class="active">Manage Books</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="admin_borrowers.php">Borrowers</a></li>
                <li><a href="admin_payments.php">Payments</a></li>
                <li><a href="../index.php">Logout</a></li>
            </ul>
        </nav>
    </aside>

<!--Main Content-->
<main class="main-content" id="main">
    <header>
        <button class="menu-toggle" id="menuToggle">☰</button>
        <h1>Manage Students</h1>
    </header>
    <!-- Add Book Form -->
    <h2>Add New Book</h2>
    <form method="POST">
        <input type="text" name="book_code" placeholder="Book Code" required>
        <input type="text" name="title" placeholder="Title" required>
        <input type="text" name="author" placeholder="Author" required>
        <input type="text" name="publisher" placeholder="Publisher" required>
        <input type="number" name="copies" placeholder="Copies" required>
        <button type="submit" name="add_book">Add Book</button>
    </form>

    <!-- Books Table -->
    <h2>Book List</h2>
    <table>
        <thead>
            <tr>
                <th>Book Code</th>
                <th>Title</th>
                <th>Author</th>
                <th>Publisher</th>
                <th>Copies</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while($book = $books->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $book['book_code']; ?></td>
                    <td><?php echo $book['title']; ?></td>
                    <td><?php echo $book['author']; ?></td>
                    <td><?php echo $book['publisher']; ?></td>
                    <td><?php echo $book['copies']; ?></td>
                    <td>
                        <a href="admin_books.php?edit=<?php echo $book['book_code']; ?>">Edit</a> |
                        <a href="admin_books.php?delete=<?php echo $book['book_code']; ?>" onclick="return confirm('Delete this book?')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <?php
        // Edit form
        if(isset($_POST['edit_book'])){
            $book_code = $_POST['book_code'];
            $original_code = isset($_POST['original_book_code']) ? $_POST['original_book_code'] : $book_code;
            $title = $_POST['title'];
            $author = $_POST['author'];
            $publisher = $_POST['publisher'];
            $copies = (int)$_POST['copies'];

            // Use prepared statement to safely update (supports changing book_code)
            $stmt = $conn->prepare("UPDATE books SET book_code=?, title=?, author=?, publisher=?, copies=? WHERE book_code=?");
            if ($stmt) {
                $stmt->bind_param("ssssis", $book_code, $title, $author, $publisher, $copies, $original_code);
                $stmt->execute();
                $stmt->close();
            } else {
                // fallback to simple query on prepare failure
                $conn->query("UPDATE books SET title='". $conn->real_escape_string($title) ."', author='". $conn->real_escape_string($author) ."', publisher='". $conn->real_escape_string($publisher) ."', copies=". $copies ." WHERE book_code='". $conn->real_escape_string($original_code) ."'");
            }

            header("Location: admin_books.php");
            exit();
        }

        if(isset($_GET['edit'])){
            $book_code = $_GET['edit'];
            $edit_book = $conn->query("SELECT * FROM books WHERE book_code='$book_code'")->fetch_assoc();
            ?>
                <h2>Edit Book</h2>
                    <form method="POST">
                    <input type="hidden" name="original_book_code" value="<?php echo $edit_book['book_code']; ?>">
                    <input type="text" name="book_code" value="<?php echo $edit_book['book_code']; ?>" required>
                    <input type="text" name="title" value="<?php echo $edit_book['title']; ?>" required>
                    <input type="text" name="author" value="<?php echo $edit_book['author']; ?>" required>
                    <input type="text" name="publisher" value="<?php echo $edit_book['publisher']; ?>" required>
                    <input type="number" name="copies" value="<?php echo $edit_book['copies']; ?>" required>
                    <button type="submit" name="edit_book">Update Book</button>
                    <button type="button" onclick="window.location='admin_books.php'">Cancel</button>
                </form>
            <?php 
        } 
    ?>  
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
