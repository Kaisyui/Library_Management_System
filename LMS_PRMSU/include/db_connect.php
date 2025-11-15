<?php // Database connection settings
$servername = "localhost";
$username = "root";       // default XAMPP username
$password = "";           // leave blank (default)
$dbname = "PRMSU_LIB";    // your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
