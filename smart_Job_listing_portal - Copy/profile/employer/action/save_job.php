<?php
session_start();
// Go up THREE levels to find config/db.php
include("../../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['role']) && $_SESSION['role'] == 'employer') {
    
    $employer_id = $_SESSION['user_id'];
    
    // 1. Secure input data (Existing Fields)
    $title       = $conn->real_escape_string($_POST['title']);
    $category    = $conn->real_escape_string($_POST['category']);
    $salary      = $conn->real_escape_string($_POST['salary']);
    $description = $conn->real_escape_string($_POST['description']);
    $skills      = $conn->real_escape_string($_POST['skills']);

    // 2. Secure input data (NEW Fields)
    // Using real_escape_string for text, and casting vacancies to an integer for safety
    $job_type    = $conn->real_escape_string($_POST['job_type']);
    $location    = $conn->real_escape_string($_POST['location']);
    $experience  = $conn->real_escape_string($_POST['experience']);
    $vacancies   = (int)$_POST['vacancies']; 
    $deadline    = $conn->real_escape_string($_POST['deadline']);

    // 3. Updated SQL Query
    $sql = "INSERT INTO jobs (employer_id, title, category, job_type, location, salary, experience, vacancies, deadline, description, skills, status) 
            VALUES ('$employer_id', '$title', '$category', '$job_type', '$location', '$salary', '$experience', '$vacancies', '$deadline', '$description', '$skills', 'ACTIVE')";

    if ($conn->query($sql) === TRUE) {
        // Success redirect to UI in parent's parent folder
        echo "<script>
                alert('Success! Job posted to database.'); 
                window.location='../employer.php';
              </script>";
    } else {
        echo "<script>
                alert('Database Error: " . $conn->error . "'); 
                window.location='../employer.php';
              </script>";
    }
} else {
    echo "Unauthorized access.";
}
?>