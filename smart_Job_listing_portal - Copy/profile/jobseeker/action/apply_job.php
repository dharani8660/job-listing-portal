<?php
session_start();
include("../../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $job_id = (int)$_POST['job_id'];
    $match_score = (int)$_POST['match_score'];
    
    $cover_letter = $conn->real_escape_string($_POST['cover_letter']);
    $expected_salary = $conn->real_escape_string($_POST['expected_salary']);
    $start_date = $conn->real_escape_string($_POST['start_date']);

    // Prevent duplicate applications
    $check = $conn->query("SELECT id FROM applications WHERE user_id = '$user_id' AND job_id = '$job_id'");
    if ($check->num_rows > 0) {
        // FIX: Redirect back and refresh
        die("<script>alert('You have already applied for this job!'); window.location = document.referrer;</script>");
    }

    $app_resume_path = "";

    // If they uploaded a specific resume for THIS job
    if (!empty($_FILES['app_resume']['name'])) {
        $target_dir = "../uploads/resumes/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_name = "app_" . $user_id . "_" . time() . "_" . basename($_FILES["app_resume"]["name"]);
        if (move_uploaded_file($_FILES["app_resume"]["tmp_name"], $target_dir . $file_name)) {
            $app_resume_path = "uploads/resumes/" . $file_name;
        }
    } else {
        // Fallback to their Master Profile Resume
        $prof_res = $conn->query("SELECT resume_path FROM seeker_profiles WHERE user_id = '$user_id'");
        if ($prof_res && $prof_res->num_rows > 0) {
            $app_resume_path = $prof_res->fetch_assoc()['resume_path'];
        }
    }

    $sql = "INSERT INTO applications (user_id, job_id, cover_letter, expected_salary, start_date, app_resume, match_score, status) 
            VALUES ('$user_id', '$job_id', '$cover_letter', '$expected_salary', '$start_date', '$app_resume_path', '$match_score', 'PENDING')";

    if ($conn->query($sql) === TRUE) {
        // Fetch Job Title to log the specific job name
        $job_res = $conn->query("SELECT title FROM jobs WHERE id = '$job_id'");
        $job_title = ($job_res && $job_res->num_rows > 0) ? $job_res->fetch_assoc()['title'] : 'a job';
        
        $log_desc = "You submitted an application for the $job_title position.";
        $conn->query("INSERT INTO activity_logs (employer_id, activity_type, description) VALUES ('$user_id', 'JOB_APPLIED', '$log_desc')");

        // FIX: Redirect back and refresh
        echo "<script>alert('Application Submitted Successfully!'); window.location = document.referrer;</script>";
    } else {
        // FIX: Redirect back and refresh
        echo "<script>alert('Database Error: " . $conn->error . "'); window.location = document.referrer;</script>";
    }
} else {
    // Fallback if accessed without POST data
    echo "<script>window.history.back();</script>";
}
?>