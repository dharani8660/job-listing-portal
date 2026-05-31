<?php
// ==============================================================================
// DELETE SAVED JOB ACTION
// ==============================================================================
session_start();
include("../../../config/db.php");

// Security Check: Ensure user is logged in
if (isset($_GET['id']) && isset($_SESSION['user_id'])) {
    $save_id = (int)$_GET['id']; // This is the ID from the 'saved_jobs' table
    $user_id = $_SESSION['user_id'];

    // SQL: Delete the record only if it belongs to the logged-in user
    $sql = "DELETE FROM saved_jobs WHERE id = '$save_id' AND user_id = '$user_id'";
    
    if ($conn->query($sql) === TRUE) {
        // Success: Alert the user and refresh the Dashboard dynamically
        echo "<script>
                alert('Removed from saved jobs.'); 
                window.location = document.referrer;
              </script>";
    } else {
        // Error handling
        echo "<script>
                alert('Error removing job: " . $conn->error . "'); 
                window.location = document.referrer;
              </script>";
    }
} else {
    // Fallback if the script is accessed directly without an ID
    echo "<script>window.history.back();</script>";
}
?>