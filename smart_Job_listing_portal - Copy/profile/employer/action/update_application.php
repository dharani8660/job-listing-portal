<?php
session_start();
include("../../../config/db.php");

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    die("Unauthorized access");
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    $app_id = (int)$_GET['id'];
    // Sanitize and format the status (e.g., 'approved' becomes 'APPROVED')
    $new_status = strtoupper($conn->real_escape_string($_GET['status'])); 
    $employer_id = $_SESSION['user_id'];

    // Security Check: Verify that this application belongs to a job posted by THIS employer
    $check_query = "SELECT a.id FROM applications a 
                    JOIN jobs j ON a.job_id = j.id 
                    WHERE a.id = '$app_id' AND j.employer_id = '$employer_id'";
    $check_result = $conn->query($check_query);

    if ($check_result && $check_result->num_rows > 0) {
        // Employer owns the job, proceed with update
        $sql = "UPDATE applications SET status = '$new_status' WHERE id = '$app_id'";
        
        if ($conn->query($sql) === TRUE) {
            echo "<script>
                    alert('Candidate application marked as $new_status.'); 
                    window.location='../employer.php';
                  </script>";
        } else {
            echo "<script>
                    alert('Error updating application.'); 
                    window.location='../employer.php';
                  </script>";
        }
    } else {
        // Stop unauthorized attempts to modify other employers' applicants
        echo "<script>
                alert('Unauthorized action. You do not own this job posting.'); 
                window.location='../employer.php';
              </script>";
    }
} else {
    header("Location: ../employer.php");
}
?>