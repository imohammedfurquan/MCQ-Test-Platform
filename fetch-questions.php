<?php
// Get the selected subject from the request
$subject = $_GET['subject'];

// Depending on the selected subject, include the corresponding file to fetch questions
if ($subject === 'java') {
    include 'java.php'; // Assuming java.php contains the Java questions
} elseif ($subject === 'Computer Networks') {
    include 'computer_networks.php'; // Assuming computer_networks.php contains the Computer Networks questions
} elseif ($subject === 'RDBMS') {
    include 'rdbms.php'; // Assuming rdbms.php contains the RDBMS questions
} elseif ($subject === 'DSA') {
    include 'dsa.php'; // Assuming dsa.php contains the DSA questions
} elseif ($subject === 'Operating System') {
    include 'operating_system.php'; // Assuming operating_system.php contains the Operating System questions
} else {
    // If subject is not recognized or provided, return an error
    echo json_encode(['error' => 'Invalid subject']);
}
?>
