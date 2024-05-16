<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "users";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userScore = $_POST["userScore"];
$email = $_POST["email"];

$updateScoreSQL = "UPDATE users SET user_score = $userScore WHERE email_id = '$email'";

if ($conn->query($updateScoreSQL) === FALSE) {
    echo "Error updating score: " . $conn->error;
}

$conn->close();
?>
