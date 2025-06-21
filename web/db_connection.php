<?php
$servername = "127.0.0.1"; // MySQL server address (from your Workbench screenshot)
$username = "root";       // Replace with your database username
$password = "password";           // Replace with your MySQL root password if you have one, otherwise leave empty
$dbname = "market";     // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// echo "Connected successfully"; // For testing connection
?>