<?php
session_start();
// Adjust this path if your db.php is located differently
include("../../../config/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // 1. Collect all form inputs (Safely trim whitespace)
    $job_title      = trim($_POST['job_title'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $location       = trim($_POST['location'] ?? '');
    $bio            = trim($_POST['bio'] ?? '');
    $skills         = trim($_POST['skills'] ?? '');
    $degree         = trim($_POST['degree'] ?? '');
    $institution    = trim($_POST['institution'] ?? '');
    $passing_year   = trim($_POST['passing_year'] ?? '');
    $percentage     = trim($_POST['percentage'] ?? '');
    $experience_desc= trim($_POST['experience_desc'] ?? '');
    $projects       = trim($_POST['projects'] ?? '');
    $certifications = trim($_POST['certifications'] ?? '');
    $pref_role      = trim($_POST['pref_role'] ?? '');
    $pref_salary    = trim($_POST['pref_salary'] ?? '');
    $pref_location  = trim($_POST['pref_location'] ?? '');
    $pref_type      = trim($_POST['pref_type'] ?? '');

    // 2. Safely Fetch Existing Files (So we don't erase them if user doesn't upload a new one)
    $photo_path = '';
    $resume_path = '';
    
    $existing = $conn->prepare("SELECT photo_path, resume_path FROM seeker_profiles WHERE user_id = ?");
    if ($existing) {
        $existing->bind_param("i", $user_id);
        $existing->execute();
        $res = $existing->get_result()->fetch_assoc();
        $photo_path = $res['photo_path'] ?? '';
        $resume_path = $res['resume_path'] ?? '';
        $existing->close();
    }

    // 3. Handle File Uploads (Fixed Paths to match frontend ../../ expectations)
    // Assuming this file is in project_root/pages/action/update_seeker_profile.php
    $upload_base_dir = "../../../uploads/"; 
    
    if (!empty($_FILES['avatar']['name'])) {
        $target_dir = $upload_base_dir . "avatars/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $photo_name = "avatar_" . $user_id . "_" . time() . ".jpg";
        if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_dir . $photo_name)) {
            // Save relative path to DB so frontend can append ../../ easily
            $photo_path = "uploads/avatars/" . $photo_name;
        }
    }

    if (!empty($_FILES['resume']['name'])) {
        $target_dir = $upload_base_dir . "resumes/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $resume_name = "resume_" . $user_id . "_" . time() . ".pdf";
        if (move_uploaded_file($_FILES["resume"]["tmp_name"], $target_dir . $resume_name)) {
            $resume_path = "uploads/resumes/" . $resume_name;
        }
    }

    // 4. Secure Database Insert/Update Logic
    // Upgraded to Prepared Statement for security
    $check_stmt = $conn->prepare("SELECT id FROM seeker_profiles WHERE user_id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        // UPDATE QUERY
        $sql = "UPDATE seeker_profiles SET 
                job_title=?, phone=?, location=?, bio=?, skills=?, degree=?, 
                institution=?, passing_year=?, percentage=?, experience_desc=?, 
                projects=?, certifications=?, pref_role=?, pref_salary=?, 
                pref_location=?, pref_type=?, photo_path=?, resume_path=? 
                WHERE user_id=?";
        
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("<div style='color:red; padding:20px; font-family:sans-serif;'>
                    <h2>Database Mismatch Error</h2>
                    <p>Your code is trying to update a column that does not exist in your phpMyAdmin table.</p>
                    <p><b>MySQL Error:</b> " . $conn->error . "</p>
                 </div>");
        }

        $stmt->bind_param("ssssssssssssssssssi", 
            $job_title, $phone, $location, $bio, $skills, $degree, 
            $institution, $passing_year, $percentage, $experience_desc, 
            $projects, $certifications, $pref_role, $pref_salary, 
            $pref_location, $pref_type, $photo_path, $resume_path, $user_id
        );
    } else {
        // INSERT QUERY
        $sql = "INSERT INTO seeker_profiles (
                user_id, job_title, phone, location, bio, skills, degree, 
                institution, passing_year, percentage, experience_desc, 
                projects, certifications, pref_role, pref_salary, 
                pref_location, pref_type, photo_path, resume_path
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("<div style='color:red; padding:20px; font-family:sans-serif;'>
                    <h2>Database Mismatch Error</h2>
                    <p>Your code is trying to insert into a column that does not exist.</p>
                    <p><b>MySQL Error:</b> " . $conn->error . "</p>
                 </div>");
        }

        $stmt->bind_param("issssssssssssssssss", 
            $user_id, $job_title, $phone, $location, $bio, $skills, $degree, 
            $institution, $passing_year, $percentage, $experience_desc, 
            $projects, $certifications, $pref_role, $pref_salary, 
            $pref_location, $pref_type, $photo_path, $resume_path
        );
    }

    // 5. Final Execution & Fixed Redirect
    if ($stmt->execute()) {
        // Fixed typo: Redirects to jobseeker.php instead of job_seeker.php
        header("Location: ../jobseeker.php?status=updated#profile-view");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    
    $stmt->close();
    $check_stmt->close();
    $conn->close();
}
?>