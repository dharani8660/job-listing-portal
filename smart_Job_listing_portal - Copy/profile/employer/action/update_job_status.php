<?php
session_start();
// Adjust path to your db.php as needed
include("../../../config/db.php");

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    die("Unauthorized access");
}

if (isset($_GET['id']) && isset($_GET['action'])) {
    $job_id = (int)$_GET['id'];
    $employer_id = $_SESSION['user_id'];
    $action = $_GET['action'];

    if ($action === 'close') {
        // Update status to CLOSED, ensuring the job belongs to the logged-in employer
        $sql = "UPDATE jobs SET status = 'CLOSED' WHERE id = '$job_id' AND employer_id = '$employer_id'";
        
        if ($conn->query($sql) === TRUE) {
            echo "<script>
                    alert('Job closed successfully.'); 
                    window.location='../employer.php';
                  </script>";
        } else {
            echo "<script>
                    alert('Database Error: " . $conn->error . "'); 
                    window.location='../employer.php';
                  </script>";
        }
    }
} else {
    header("Location: ../employer.php");
}
?>