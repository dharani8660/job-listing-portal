<?php
session_start();
// Adjust this include path if necessary (e.g., "../../config/db.php" or "../config/db.php")
include("../../../config/db.php");

if (isset($_GET['job_id']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $job_id = (int)$_GET['job_id'];

    // Check if already saved
    $check = $conn->query("SELECT id FROM saved_jobs WHERE user_id = '$user_id' AND job_id = '$job_id'");
    if ($check->num_rows > 0) {
        die("<script>alert('Job is already saved in your list.'); window.location = document.referrer;</script>");
    }

    $sql = "INSERT INTO saved_jobs (user_id, job_id) VALUES ('$user_id', '$job_id')";
    
    if ($conn->query($sql) === TRUE) {
        // Log it
        $job_res = $conn->query("SELECT title FROM jobs WHERE id = '$job_id'");
        $job_title = ($job_res && $job_res->num_rows > 0) ? $job_res->fetch_assoc()['title'] : 'a job';
        $log_desc = "You saved the $job_title position to review later.";
        
        $conn->query("INSERT INTO activity_logs (employer_id, activity_type, description) VALUES ('$user_id', 'JOB_SAVED', '$log_desc')");
        
        // FIX: Using document.referrer guarantees we go back to the right page and refresh it!
        echo "<script>
                alert('Job Saved to your Dashboard!'); 
                window.location = document.referrer;
              </script>";
    } else {
        echo "<script>alert('Error saving job: " . $conn->error . "'); window.location = document.referrer;</script>";
    }
} else {
    // Fallback if accessed directly
    echo "<script>window.history.back();</script>";
}
?>