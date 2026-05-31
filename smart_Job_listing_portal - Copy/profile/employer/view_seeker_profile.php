<?php
session_start();
include("../../config/db.php");

// ✅ SECURITY CHECK
if(!isset($_SESSION['user_id'])){
    die("Access Denied");
}

// ✅ SAFE user_id
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if($user_id <= 0){
    die("Invalid User");
}

// Fetch data (REAL-TIME)
$stmt = $conn->prepare("
    SELECT u.name, u.email, sp.*
    FROM users u
    LEFT JOIN seeker_profiles sp ON u.id = sp.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$data = $res->fetch_assoc() ?? [];

if(empty($data)){
    die("User not found");
}

// Safe values
$user_name = $data['name'] ?? 'Unknown';
$user_email = $data['email'] ?? 'N/A';

// Avatar initials
$words = explode(' ', $user_name);
$initials = (count($words) >= 2)
    ? strtoupper($words[0][0] . $words[1][0])
    : strtoupper(substr($user_name, 0, 2));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($user_name); ?> - Candidate Profile</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --primary:#00b4d8;
    --bg:#f5f7f9;
    --text:#212529;
    --gray:#6c757d;
}

body{
    margin:0;
    font-family:'Poppins',sans-serif;
    background:var(--bg);
}

.container{
    max-width:1100px;
    margin:40px auto;
    padding:0 20px;
}

.card{
    background:white;
    border-radius:12px;
    padding:25px;
    margin-bottom:20px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}

.title{
    color:var(--primary);
    font-weight:600;
    margin-bottom:10px;
}

.avatar{
    width:100px;
    height:100px;
    border-radius:50%;
    object-fit:cover;
}

.avatar-box{
    width:100px;
    height:100px;
    border-radius:50%;
    background:#e0f7fa;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:30px;
    color:var(--primary);
    font-weight:bold;
}

.skill{
    display:inline-block;
    padding:6px 14px;
    margin:5px;
    border-radius:20px;
    background:#e0f7fa;
    color:var(--primary);
    font-size:14px;
}

.btn{
    background:var(--primary);
    color:white;
    padding:10px 20px;
    border-radius:6px;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    gap:6px;
}

.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}
</style>
</head>

<body>

<div class="container">

<!-- HEADER -->
<div class="card" style="display:flex; justify-content:space-between; align-items:center;">
    <div style="display:flex; gap:20px; align-items:center;">

        <?php if(!empty($data['photo_path'])): ?>
            <img src="../../<?php echo htmlspecialchars($data['photo_path']); ?>" class="avatar">
        <?php else: ?>
            <div class="avatar-box"><?php echo $initials; ?></div>
        <?php endif; ?>

        <div>
            <h2><?php echo htmlspecialchars($user_name); ?></h2>
            <p style="color:var(--primary); font-weight:600;">
                <?php echo htmlspecialchars($data['job_title'] ?? 'No Title'); ?>
            </p>
            <p><?php echo htmlspecialchars($user_email); ?></p>
            <p><?php echo htmlspecialchars($data['location'] ?? 'No Location'); ?></p>
        </div>
    </div>

    <a href="employer.php" class="btn">
        <i class="fa-solid fa-arrow-left"></i> Back
    </a>
</div>

<!-- SUMMARY -->
<div class="card">
    <h3 class="title">Professional Summary</h3>
    <p><?php echo nl2br(htmlspecialchars($data['bio'] ?? 'No summary')); ?></p>
</div>

<!-- SKILLS -->
<div class="card">
    <h3 class="title">Skills</h3>
    <?php
    $skills = explode(',', $data['skills'] ?? '');
    foreach($skills as $s){
        if(trim($s) !== ''){
            echo "<span class='skill'>".htmlspecialchars(trim($s))."</span>";
        }
    }
    ?>
</div>

<!-- EDUCATION + CERTIFICATIONS -->
<div class="grid">

    <div class="card">
        <h3 class="title">Education</h3>
        <p><strong><?php echo htmlspecialchars($data['degree'] ?? ''); ?></strong></p>
        <p><?php echo htmlspecialchars($data['institution'] ?? ''); ?></p>
        <p>Year: <?php echo htmlspecialchars($data['passing_year'] ?? ''); ?></p>
        <p>CGPA: <?php echo htmlspecialchars($data['percentage'] ?? ''); ?></p>
    </div>

    <div class="card">
        <h3 class="title">Certifications</h3>
        <p><?php echo nl2br(htmlspecialchars($data['certifications'] ?? 'None')); ?></p>
    </div>

</div>

<!-- EXPERIENCE -->
<div class="card">
    <h3 class="title">Experience / Internships</h3>
    <p><?php echo nl2br(htmlspecialchars($data['experience_desc'] ?? 'No experience')); ?></p>
</div>

<!-- PROJECTS -->
<div class="card">
    <h3 class="title">Projects</h3>
    <p><?php echo nl2br(htmlspecialchars($data['projects'] ?? 'No projects')); ?></p>
</div>

<!-- JOB PREFERENCES -->
<div class="card">
    <h3 class="title">Job Preferences</h3>
    <p><strong>Role:</strong> <?php echo htmlspecialchars($data['pref_role'] ?? ''); ?></p>
    <p><strong>Salary:</strong> <?php echo htmlspecialchars($data['pref_salary'] ?? ''); ?></p>
    <p><strong>Location:</strong> <?php echo htmlspecialchars($data['pref_location'] ?? ''); ?></p>
    <p><strong>Type:</strong> <?php echo htmlspecialchars($data['pref_type'] ?? ''); ?></p>
</div>

<!-- RESUME -->
<div class="card" style="display:flex; justify-content:space-between; align-items:center;">
    <div>
        <h3 class="title">Resume</h3>
        <p>Candidate Resume</p>
    </div>

    <?php if(!empty($data['resume_path'])): ?>
        <div style="display:flex; gap:10px;">
            <a href="../../<?php echo htmlspecialchars($data['resume_path']); ?>" target="_blank" class="btn">
                <i class="fa-solid fa-eye"></i> View
            </a>

            <a href="../../<?php echo htmlspecialchars($data['resume_path']); ?>" download class="btn">
                <i class="fa-solid fa-download"></i> Download
            </a>
        </div>
    <?php else: ?>
        <p>No resume uploaded</p>
    <?php endif; ?>
</div>

</div>

</body>
</html>