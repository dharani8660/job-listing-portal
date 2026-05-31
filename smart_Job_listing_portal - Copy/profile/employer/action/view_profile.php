<?php
session_start();

// 1. Secure Database Connection
$base_dir = $_SERVER['DOCUMENT_ROOT'] . '/smart_Job_listing_portal';
$db_path = $base_dir . '/config/db.php';

if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("<h2 style='color:red; text-align:center; padding: 50px;'>Path Error: Cannot find db.php</h2>");
}

// 2. Security Check: Only logged-in employers can view this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    die("<h2 style='text-align:center; padding: 50px;'>Access Denied. Please log in as an employer.</h2>");
}

$profile = null;
$resume_path = null;

// 3. Fetch the Seeker's Data
if (isset($_GET['seeker_id'])) {
    $seeker_id = intval($_GET['seeker_id']);

    // Grab User Details + Profile Details
    $sql = "
        SELECT u.name as full_name, u.email, sp.phone, sp.bio, sp.skills, sp.degree, sp.institution, sp.passing_year, sp.photo_path, sp.resume_path 
        FROM users u 
        LEFT JOIN seeker_profiles sp ON u.id = sp.user_id 
        WHERE u.id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $seeker_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile = $result->fetch_assoc();
    }

    // Attempt to grab the latest resume from their applications if it's not in the main profile
    $app_sql = "SELECT resume_path FROM applications WHERE user_id = ? AND resume_path IS NOT NULL ORDER BY applied_on DESC LIMIT 1";
    $app_stmt = $conn->prepare($app_sql);
    if ($app_stmt) {
        $app_stmt->bind_param("i", $seeker_id);
        $app_stmt->execute();
        $app_res = $app_stmt->get_result();
        if ($app_row = $app_res->fetch_assoc()) {
            $resume_path = $app_row['resume_path'];
        }
    }
    
    // Fallback: If no application resume, use the main profile resume
    if (empty($resume_path) && !empty($profile['resume_path'])) {
        $resume_path = $profile['resume_path'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Profile - <?php echo htmlspecialchars($profile['full_name'] ?? 'Unknown'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #00b4d8; 
            --primary-dark: #0096c7; 
            --bg-light: #f5f7f9; 
            --text-dark: #212529;
            --text-gray: #6c757d;
            --white: #ffffff;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-light); color: var(--text-dark); margin: 0; padding: 40px 20px; display: flex; justify-content: center; }
        .profile-card { background: var(--white); width: 100%; max-width: 850px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; }
        
        .profile-header { background: var(--primary); padding: 40px; color: white; display: flex; align-items: center; gap: 30px; }
        .avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid rgba(255,255,255,0.3); background: white; }
        .header-info h1 { margin: 0 0 10px 0; font-size: 2.2rem; font-weight: 700; }
        .header-info p { margin: 5px 0; font-size: 1rem; opacity: 0.9; display: flex; align-items: center; gap: 10px; }
        
        .profile-body { padding: 40px; }
        .section-title { color: var(--primary); font-size: 1.2rem; font-weight: 600; border-bottom: 2px solid var(--bg-light); padding-bottom: 10px; margin-bottom: 20px; margin-top: 30px; display: flex; align-items: center; gap: 10px; }
        .section-title:first-child { margin-top: 0; }
        
        .bio-box { background: var(--bg-light); padding: 20px; border-radius: 10px; color: var(--text-gray); line-height: 1.7; font-size: 0.95rem; }
        
        .skills-container { display: flex; flex-wrap: wrap; gap: 10px; }
        .skill-tag { background: rgba(0, 180, 216, 0.1); color: var(--primary-dark); padding: 8px 16px; border-radius: 50px; font-size: 0.9rem; font-weight: 600; border: 1px solid rgba(0, 180, 216, 0.2); }
        
        .edu-item { margin-bottom: 15px; }
        .edu-item h4 { margin: 0 0 5px 0; color: var(--text-dark); font-size: 1.1rem; }
        .edu-item p { margin: 0; color: var(--text-gray); font-size: 0.95rem; }
        
        .resume-banner { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 1px dashed #ced4da; padding: 30px; text-align: center; border-radius: 10px; margin-top: 40px; }
        .resume-banner h3 { margin-top: 0; color: var(--text-dark); }
        .btn-resume { display: inline-block; background: var(--primary); color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; margin-top: 15px; transition: 0.3s; box-shadow: 0 4px 15px rgba(0, 180, 216, 0.3); }
        .btn-resume:hover { background: var(--primary-dark); transform: translateY(-2px); }
        
        .close-bar { text-align: center; margin-top: 30px; }
        .btn-close { background: transparent; color: var(--text-gray); border: 2px solid var(--text-gray); padding: 8px 25px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: 0.3s; }
        .btn-close:hover { background: var(--text-gray); color: white; }
    </style>
</head>
<body>

<?php if ($profile): ?>
    <div class="profile-card">
        <div class="profile-header">
            <?php 
            if (!empty($profile['photo_path'])) {
                $web_img_path = '/smart_Job_listing_portal/' . ltrim($profile['photo_path'], '/');
                echo '<img src="' . htmlspecialchars($web_img_path) . '" class="avatar" alt="Candidate Avatar">';
            } else {
                echo '<img src="https://ui-avatars.com/api/?name=' . urlencode($profile['full_name'] ?? 'C') . '&background=ffffff&color=00b4d8&size=150" class="avatar" alt="Default Avatar">';
            }
            ?>
            <div class="header-info">
                <h1><?php echo htmlspecialchars($profile['full_name'] ?? 'Candidate Name Not Provided'); ?></h1>
                <p><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($profile['email'] ?? 'No email on file'); ?></p>
                <p><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($profile['phone'] ?? 'No phone on file'); ?></p>
            </div>
        </div>

        <div class="profile-body">
            
            <h3 class="section-title"><i class="fa-solid fa-user"></i> Professional Summary</h3>
            <div class="bio-box">
                <?php echo !empty($profile['bio']) ? nl2br(htmlspecialchars($profile['bio'])) : 'This candidate has not provided a professional summary yet.'; ?>
            </div>

            <h3 class="section-title"><i class="fa-solid fa-bolt"></i> Core Skills</h3>
            <div class="skills-container">
                <?php 
                if (!empty($profile['skills'])) {
                    $skills_array = explode(',', $profile['skills']);
                    foreach($skills_array as $skill) {
                        echo '<span class="skill-tag">' . htmlspecialchars(trim($skill)) . '</span>';
                    }
                } else {
                    echo '<p style="color: var(--text-gray); font-size: 0.95rem;">No skills listed.</p>';
                }
                ?>
            </div>
            
            <h3 class="section-title"><i class="fa-solid fa-graduation-cap"></i> Education</h3>
            <div class="edu-item">
                <?php if (!empty($profile['degree']) || !empty($profile['institution'])): ?>
                    <h4><?php echo htmlspecialchars($profile['degree'] ?? 'Degree Not Specified'); ?></h4>
                    <p><?php echo htmlspecialchars($profile['institution'] ?? 'Institution Not Specified'); ?> • Class of <?php echo htmlspecialchars($profile['passing_year'] ?? 'N/A'); ?></p>
                <?php else: ?>
                    <p style="color: var(--text-gray); font-size: 0.95rem;">No formal education details provided.</p>
                <?php endif; ?>
            </div>

            <div class="resume-banner">
                <h3><i class="fa-solid fa-file-pdf" style="color: #e63946;"></i> Candidate Resume</h3>
                <?php if (!empty($resume_path)): ?>
                    <p style="color: var(--text-gray); font-size: 0.95rem; margin-bottom: 0;">This candidate has uploaded a resume document.</p>
                    <a href="/smart_Job_listing_portal/<?php echo ltrim(htmlspecialchars($resume_path), '/'); ?>" target="_blank" class="btn-resume">
                        <i class="fa-solid fa-download"></i> Download / View Resume
                    </a>
                <?php else: ?>
                    <p style="color: var(--text-gray); font-size: 0.95rem; margin-bottom: 0;">No resume file has been attached by this candidate.</p>
                    <button class="btn-resume" style="background: #ced4da; cursor: not-allowed; box-shadow: none;" disabled>
                        <i class="fa-solid fa-file-circle-xmark"></i> Not Available
                    </button>
                <?php endif; ?>
            </div>

            <div class="close-bar">
                <button onclick="window.close();" class="btn-close">Close Tab</button>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="profile-card" style="text-align: center; padding: 50px;">
        <i class="fa-solid fa-user-slash" style="font-size: 4rem; color: var(--silver-accent); margin-bottom: 20px;"></i>
        <h2 style="color: var(--danger);">Profile Not Found</h2>
        <p style="color: var(--text-gray);">The requested candidate profile does not exist, or the ID is invalid.</p>
        <button onclick="window.close();" class="btn-close" style="margin-top: 20px;">Close Tab</button>
    </div>
<?php endif; ?>

</body>
</html>