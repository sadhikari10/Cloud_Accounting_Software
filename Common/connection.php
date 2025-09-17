<?php
$servername = "localhost";
$username = "root";      // default XAMPP username
$password = "";          // default XAMPP password
$dbname = "star_accounting"; // replace with your DB name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
