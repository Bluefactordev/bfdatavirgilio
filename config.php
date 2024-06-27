<?php
$servername = "localhost";
$username = "root";
$password = "tsz912!vA";
$dbname = "file_tmp";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
