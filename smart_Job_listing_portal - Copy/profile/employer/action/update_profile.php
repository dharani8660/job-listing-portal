<?php
session_start();
include("../../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Capture and sanitize all form inputs
    $company_name = $conn->real_escape_string($_POST['company_name']);
    $industry     = $conn->real_escape_string($_POST['industry']);
    $website      = $conn->real_escape_string($_POST['website']);
    $email        = $conn->real_escape_string($_POST['email']);
    $phone        = $conn->real_escape_string($_POST['phone']);
    $location     = $conn->real_escape_string($_POST['location']);
    $size         = $conn->real_escape_string($_POST['company_size']);
    $founded      = $conn->real_escape_string($_POST['founded_year']);
    $about        = $conn->real_escape_string($_POST['about_company']);

    // Handle Logo Upload
    $logo_query = "";
    $logo_path = ""; // Fix: Initialize to prevent error if no file uploaded
    
    if (!empty($_FILES['logo']['name'])) {
        // Path should be relative to the root for display purposes
        $target_dir = "../../../uploads/logos/";
        
        // Create directory if it doesn't exist
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES["logo"]["name"], PATHINFO_EXTENSION);
        $file_name = "logo_" . $user_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
            // Save this path to store in DB
            $logo_path = "uploads/logos/" . $file_name;
            $logo_query = ", logo_path='$logo_path'";
        }
    }

    // Check if profile exists
    $check = $conn->query("SELECT user_id FROM employer_profiles WHERE user_id = '$user_id'");

    if ($check->num_rows > 0) {
        // UPDATE
        $sql = "UPDATE employer_profiles SET 
                company_name='$company_name', industry='$industry', website='$website', 
                email='$email', phone='$phone', location='$location', 
                company_size='$size', founded_year='$founded', about_company='$about' $logo_query
                WHERE user_id='$user_id'";
    } else {
        // INSERT
        $sql = "INSERT INTO employer_profiles (user_id, company_name, industry, website, email, phone, location, company_size, founded_year, about_company, logo_path) 
                VALUES ('$user_id', '$company_name', '$industry', '$website', '$email', '$phone', '$location', '$size', '$founded', '$about', '$logo_path')";
    }

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Profile Updated Successfully!'); window.location='../employer.php';</script>";
    } else {
        echo "Database Error: " . $conn->error;
    }
} else {
    echo "Access Denied.";
}
?>