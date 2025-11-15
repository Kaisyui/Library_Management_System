<?php
session_start();
include '../include/db_connect.php';

// Check if logged in as Admin
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit();
}

// Add new student
if(isset($_POST['add_student'])){
    $student_id = $_POST['student_id'];
    $last_name = $_POST['last_name'];
    $first_name = $_POST['first_name'];
    $middle_initial = $_POST['middle_initial'];
    $course = $_POST['course'];
    $year_level = $_POST['year_level'];
    $contact = $_POST['contact'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("INSERT INTO students (student_id,last_name,first_name,middle_initial,course,year_level,contact,password) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param("ssssssss",$student_id,$last_name,$first_name,$middle_initial,$course,$year_level,$contact,$password);
    $stmt->execute();
    header("Location: admin_students.php");
}

// Edit student
if(isset($_POST['edit_student'])){
    $student_id = $_POST['student_id'];
    $last_name = $_POST['last_name'];
    $first_name = $_POST['first_name'];
    $middle_initial = $_POST['middle_initial'];
    $course = $_POST['course'];
    $year_level = $_POST['year_level'];
    $contact = $_POST['contact'];

    $stmt = $conn->prepare("UPDATE students SET last_name=?, first_name=?, middle_initial=?, course=?, year_level=?, contact=? WHERE student_id=?");
    $stmt->bind_param("sssssss",$last_name,$first_name,$middle_initial,$course,$year_level,$contact,$student_id);
    $stmt->execute();
    header("Location: admin_students.php");
}

// Delete student
if(isset($_GET['delete'])){
    $student_id = $_GET['delete'];
    $conn->query("DELETE FROM students WHERE student_id='$student_id'");
    header("Location: admin_students.php");
}

// Fetch all students
$students = $conn->query("SELECT * FROM students ORDER BY last_name ASC");

// Fetch student to edit (if any)
$edit_student = null;
if(isset($_GET['edit'])){
    $student_id = $_GET['edit'];
    $edit_student = $conn->query("SELECT * FROM students WHERE student_id='$student_id'")->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Students</title>
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
                <li><a href="admin_students.php" class="active">Manage Students</a></li>
                <li><a href="admin_books.php">Manage Books</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="admin_borrowers">Borrowers</a></li>
                <li><a href="admin_payments.php">Payment</a></li>
                <li><a href="../index.php" class="Logout">Logout</a></li>
            </ul>
        </nav>
    </aside>

    <!--Main Content-->
    <main class="main-content" id="main">
        <header>
            <button class="menu-toggle" id="menuToggle">☰</button>
            <h1>Manage Students</h1>
        </header>

        <!--Add / Edit Form-->
        <form method="POST">
        <input type="text" name="student_id" placeholder="Student ID" value="<?php echo $edit_student['student_id'] ?? ''; ?>" required <?php echo $edit_student ? 'readonly' : ''; ?>>
        <input type="text" name="last_name" placeholder="Last Name" value="<?php echo $edit_student['last_name'] ?? ''; ?>" required>
        <input type="text" name="first_name" placeholder="First Name" value="<?php echo $edit_student['first_name'] ?? ''; ?>" required>
        <input type="text" name="middle_initial" placeholder="Middle Initial" value="<?php echo $edit_student['middle_initial'] ?? ''; ?>">
        <input type="text" name="course" placeholder="Course" value="<?php echo $edit_student['course'] ?? ''; ?>" required>
        <input type="text" name="year_level" placeholder="Year Level" value="<?php echo $edit_student['year_level'] ?? ''; ?>" required>

        <!-- Contact field (completely outside sidebar) -->
        <input type="text" id="contactField" name="contact" placeholder="Contact Number"    
            value="<?php echo $edit_student['contact'] ?? ''; ?>" required
            onkeypress="return event.charCode >= 48 && event.charCode <= 57"
            maxlength="15">

            <?php if(!$edit_student): ?>
                <input type="text" name="password" placeholder="Password" required>
            <?php endif; ?>

            <button type="submit" name="<?php echo $edit_student ? 'edit_student' : 'add_student'; ?>" >
                <?php echo $edit_student ? 'Update Student' : 'Add Student'; ?>
            </button>

            <?php if($edit_student): ?>
                <button type="button" onclick="window.location='admin_students.php'">Cancel</button>
            <?php endif; ?>
        </form>
            <!--Students Table-->
            <section class="students-table">
                <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Last Name</th>
                        <th>First Name</th>
                        <th>Middle Initial</th>
                        <th>Course</th>
                        <th>Year Level</th>
                        <th>Contact</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($student = $students->fetch_assoc()): ?>
                        <tr>
                        <td><?php echo $student['student_id']; ?></td>
                        <td><?php echo $student['last_name']; ?></td>
                        <td><?php echo $student['first_name']; ?></td>
                        <td><?php echo $student['middle_initial']; ?></td>
                        <td><?php echo $student['course']; ?></td>
                        <td><?php echo $student['year_level']; ?></td>
                        <td><?php echo $student['contact'] ?? ''; ?></td>
                    <td>
                        <a href="admin_students.php?edit=<?php echo $student['student_id']; ?>">Edit</a> |
                        <a href="admin_students.php?delete=<?php echo $student['student_id']; ?>" onclick="return confirm('Delete this student?')">Delete</a>
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
