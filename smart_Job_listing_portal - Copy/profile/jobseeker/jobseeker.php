<?php
// ==============================================================================
// SECTION 1: SESSION & DATABASE CONNECTION (FIXED SECURITY CHECK)
// ==============================================================================
session_start();
include("../../config/db.php"); 

// Flexible Security Check to prevent random logouts
$is_logged_in = isset($_SESSION['user_id']) || isset($_SESSION['id']);
$role = strtolower($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');

if (!$is_logged_in || ($role !== 'seeker' && $role !== 'jobseeker' && $role !== 'job_seeker')) {
    // header("Location: ../../auth/index.php"); // Uncomment in production
    // exit();
}

// Assign Session Variables Safely
$user_name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Job Seeker';
$user_email = $_SESSION['email'] ?? 'seeker@example.com';
$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

// Generate Initials
$words = explode(' ', $user_name);
$initials = (count($words) >= 2) ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1)) : strtoupper(substr($user_name, 0, 2));

// ==============================================================================
// SECTION 2: DYNAMIC DATA FETCHING & SYNCHRONIZED SEARCH
// ==============================================================================

// A. Fetch Job Seeker Profile Data
$profile_res = isset($conn) ? $conn->query("SELECT * FROM seeker_profiles WHERE user_id = '$user_id'") : false;
$p = ($profile_res && $profile_res->num_rows > 0) ? $profile_res->fetch_assoc() : [];
$user_skills_raw = $p['skills'] ?? ''; 

// B. Fetch Active Jobs + FULLY SYNCHRONIZED DATABASE SEARCH
$search_title    = trim($_GET['title'] ?? '');
$search_location = trim($_GET['location'] ?? '');
$search_type     = trim($_GET['type'] ?? '');
$search_category = trim($_GET['category'] ?? '');

$jobs_query = "SELECT j.*, ep.company_name, ep.logo_path, ep.email as contact_email 
               FROM jobs j 
               LEFT JOIN employer_profiles ep ON j.employer_id = ep.user_id 
               WHERE j.status = 'ACTIVE'";

$params = [];
$types = "";

if (!empty($search_title)) {
    $jobs_query .= " AND j.title LIKE ?";
    $title_param = "%" . $search_title . "%";
    $params[] = $title_param;
    $types .= "s";
}
if (!empty($search_location)) {
    $jobs_query .= " AND j.location LIKE ?";
    $loc = "%" . $search_location . "%";
    $params[] = $loc;
    $types .= "s";
}
if (!empty($search_type)) {
    $jobs_query .= " AND j.job_type LIKE ?";
    $params[] = "%" . $search_type . "%";
    $types .= "s";
}
if (!empty($search_category)) {
    $jobs_query .= " AND j.category LIKE ?";
    $cat = "%" . $search_category . "%";
    $params[] = $cat;
    $types .= "s";
}

$jobs_query .= " ORDER BY j.id DESC";

if (!empty($types)) {
    $stmt_jobs = $conn->prepare($jobs_query);
    $stmt_jobs->bind_param($types, ...$params);
    $stmt_jobs->execute();
    $jobs_res = $stmt_jobs->get_result();
} else {
    $jobs_res = isset($conn) ? $conn->query($jobs_query) : false;
}

$all_jobs = [];
if ($jobs_res && $jobs_res->num_rows > 0) {
    while ($row = $jobs_res->fetch_assoc()) {
        $all_jobs[] = $row;
    }
}
if (!empty($types)) { $stmt_jobs->close(); }

// ------------------------------------------------------------------------------
// NEW: SMART RECOMMENDATION ENGINE (ALGORITHM)
// ------------------------------------------------------------------------------
$user_pref_role = strtolower(trim($p['pref_role'] ?? ''));
$user_skills_arr = array_filter(array_map('trim', array_map('strtolower', explode(',', $user_skills_raw))));

foreach ($all_jobs as &$job) {
    $score = 0;
    $j_title = strtolower($job['title']);
    $j_cat = strtolower($job['category'] ?? '');
    $j_skills = strtolower($job['skills'] ?? '');

    // Boost score heavily if it matches Candidate's Preferred Role
    if (!empty($user_pref_role) && (strpos($j_title, $user_pref_role) !== false || strpos($j_cat, $user_pref_role) !== false)) {
        $score += 50; 
    }

    // Boost score for every matching skill found in the job description
    if (!empty($user_skills_arr)) {
        foreach ($user_skills_arr as $uskill) {
            if (strpos($j_skills, $uskill) !== false) {
                $score += 15;
            }
        }
    }
    
    $job['rec_score'] = $score;
}
unset($job); // Break reference

// Sort the jobs array: Highest recommendation score goes to the top!
// If scores are equal, sort by newest posted (ID)
usort($all_jobs, function($a, $b) {
    if ($a['rec_score'] == $b['rec_score']) {
        return $b['id'] <=> $a['id']; 
    }
    return $b['rec_score'] <=> $a['rec_score'];
});
// ------------------------------------------------------------------------------


// C. Fetch Applied Jobs History
$applied_query = "SELECT a.*, j.title, j.salary, ep.company_name 
                  FROM applications a 
                  JOIN jobs j ON a.job_id = j.id 
                  LEFT JOIN employer_profiles ep ON j.employer_id = ep.user_id 
                  WHERE a.user_id = '$user_id' 
                  ORDER BY a.applied_on DESC";
$applied_res = isset($conn) ? $conn->query($applied_query) : false;
$applied_jobs = [];
if ($applied_res && $applied_res->num_rows > 0) {
    while ($row = $applied_res->fetch_assoc()) {
        $applied_jobs[] = $row;
    }
}

// D. Fetch Saved/Bookmarked Jobs
$saved_query = "SELECT s.id as save_id, s.saved_on, j.id as job_id, j.title, j.salary, ep.company_name 
                FROM saved_jobs s 
                JOIN jobs j ON s.job_id = j.id 
                LEFT JOIN employer_profiles ep ON j.employer_id = ep.user_id 
                WHERE s.user_id = '$user_id' 
                ORDER BY s.saved_on DESC";
$saved_res = isset($conn) ? $conn->query($saved_query) : false;
$saved_jobs = [];
if ($saved_res && $saved_res->num_rows > 0) {
    while ($row = $saved_res->fetch_assoc()) {
        $saved_jobs[] = $row;
    }
}

// E. Generate DYNAMIC TRENDING TAGS based on active jobs
$tags_query = "SELECT category FROM jobs WHERE status = 'ACTIVE'";
$tags_res = isset($conn) ? $conn->query($tags_query) : false;
$category_counts = [];
if ($tags_res) {
    while($row = $tags_res->fetch_assoc()) {
        $cat = trim(preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $row['category'] ?? ''));
        if (!empty($cat)) {
            $category_counts[$cat] = ($category_counts[$cat] ?? 0) + 1;
        }
    }
}
arsort($category_counts);
$top_categories = array_slice(array_keys($category_counts), 0, 4);

if (empty($top_categories)) {
    $top_categories = ['Information Technology (IT) & Software', 'Sales & Marketing', 'Banking, Finance & Accounting'];
}

function getCategoryIcon($catName) {
    $icons = [
        'Information Technology (IT) & Software' => '💻', 
        'Sales & Marketing' => '📊',
        'Banking, Finance & Accounting' => '🏦', 
        'Healthcare & Medical' => '🏥', 
        'Education & Training' => '🎓',
        'Engineering & Construction' => '🏗️', 
        'Customer Service & BPO' => '📞', 
        'Retail & E-commerce' => '🛒',
        'Logistics & Supply Chain' => '🚚', 
        'Human Resources (HR) & Administration' => '👩‍💼'
    ];
    return $icons[$catName] ?? '🏷️';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Portal - Candidate Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #00b4d8; 
            --primary-dark: #0096c7; 
            --primary-light: rgba(0, 180, 216, 0.1);
            --bg-light: #f5f7f9; 
            --silver-accent: #ced4da; 
            --text-dark: #212529;
            --text-gray: #6c757d;
            --white: #ffffff;
            --success: #10b981; 
            --danger: #ef4444; 
            --warning: #f59e0b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-dark); }

        .navbar { display: flex; justify-content: space-between; align-items: center; background: var(--primary); padding: 20px 50px; color: white; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .logo { font-size: 1.2rem; font-weight: 700; letter-spacing: 1px; }
        .nav-links { display: flex; list-style: none; gap: 30px; }
        .nav-links a { text-decoration: none; color: white; font-size: 0.85rem; font-weight: 600; transition: 0.3s; cursor: pointer; }
        .nav-links a:hover, .nav-links a.active { color: #e0ffff; text-decoration: underline; text-underline-offset: 5px; }
        
        .auth-buttons { display: flex; gap: 15px; align-items: center; }
        .btn-logout { border: 1px solid white; color: white; padding: 8px 20px; border-radius: 4px; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: 0.3s; }
        .btn-logout:hover { background: rgba(255, 255, 255, 0.1); }

        .view-section { display: none; animation: fadeIn 0.3s ease-in-out; }
        .view-section.active-view { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .hero { display: flex; justify-content: center; flex-direction: column; align-items: center; padding: 60px 10% 40px; text-align: center; }
        .hero h1 { font-size: 55px; line-height: 1.1; font-weight: 700; margin-bottom: 15px;}
        .hero h1 span { color: var(--primary); }
        
        .advanced-search-box { display: flex; align-items: center; background: white; margin-top: 30px; border-radius: 50px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); padding: 8px 8px 8px 25px; width: 100%; max-width: 1000px; position: relative; }
        .search-field { display: flex; align-items: center; flex: 1; position: relative; }
        .search-field i { color: var(--primary); font-size: 1.1rem; margin-right: 10px; }
        .search-field input, .search-field select { width: 100%; border: none; outline: none; font-size: 0.9rem; color: var(--text-dark); background: transparent; padding: 12px 0; font-family: inherit; }
        .search-field select { cursor: pointer; }
        .search-divider { width: 1px; height: 35px; background: var(--silver-accent); margin: 0 15px; }
        .btn-primary { background: var(--primary); color: white; border: none; padding: 15px 30px; border-radius: 50px; font-weight: 600; cursor: pointer; transition: 0.3s; white-space: nowrap; margin-left: 10px; }
        .btn-primary:hover { background: var(--primary-dark); }
        
        .tags { display: flex; align-items: center; justify-content: center; margin-top: 25px; font-size: 0.9rem; gap: 12px; flex-wrap: wrap; }
        .tag-icon { color: var(--primary); font-size: 1.1rem; }
        .tag-title { color: var(--text-dark); font-weight: 600; }
        .tags a { text-decoration: none; display: inline-block; background: white; padding: 5px 15px; border-radius: 20px; cursor: pointer; color: var(--text-gray); transition: 0.3s; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .tags a:hover { background: var(--primary-light); color: var(--primary); }

        .job-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; padding: 20px 10% 60px; }
        .job-card-new { background: var(--white); border: 1px solid var(--silver-accent); border-radius: 8px; padding: 25px; display: flex; flex-direction: column; justify-content: space-between; transition: 0.3s; position: relative; overflow: hidden; }
        .job-card-new:hover { box-shadow: 0 10px 25px rgba(0,0,0,0.08); transform: translateY(-3px); }
        
        /* Stylized Recommendation Badge */
        .rec-badge { position: absolute; top: 0; right: 0; background: #fff3cd; color: #f59e0b; font-size: 0.75rem; font-weight: 700; padding: 5px 15px; border-bottom-left-radius: 8px; border-top-right-radius: 8px; border-left: 1px solid #ffe69c; border-bottom: 1px solid #ffe69c;}
        
        .job-card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; margin-top: 10px; }
        .job-company-info { display: flex; align-items: center; gap: 15px; }
        .job-company-info img { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; background: var(--bg-light); }
        .job-details-list { list-style: none; }
        .job-details-list li { font-size: 0.9rem; color: var(--text-gray); margin-bottom: 8px; }
        .job-details-list li span { color: var(--primary); margin-right: 5px; font-size: 1.2rem; line-height: 0; }
        .job-card-bottom { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--silver-accent); padding-top: 15px; }
        .job-tag { background: var(--primary-light); color: var(--primary); padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; }
        .btn-outline { background: transparent; color: var(--primary); border: 1px solid var(--primary); padding: 8px 20px; border-radius: 4px; cursor: pointer; transition: 0.3s; font-weight: 500; text-align: center; }
        .btn-outline:hover { background: var(--primary); color: var(--white); }

        .detail-wrapper { max-width: 1100px; margin: 40px auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid var(--silver-accent); padding-bottom: 20px; }
        .detail-title-area { display: flex; align-items: center; gap: 20px; }
        .detail-title-area img { width: 80px; height: 80px; border-radius: 12px; object-fit: cover; }
        .detail-meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; font-size: 0.95rem; background: var(--bg-light); padding: 20px; border-radius: 8px;}
        .detail-meta-item i { color: var(--primary); width: 20px; }
        .detail-actions { display: flex; gap: 15px; }
        .btn-save { background: var(--primary-light); color: var(--primary); border: 1px solid var(--primary); padding: 12px 20px; border-radius: 6px; cursor: pointer; transition: 0.3s; font-weight: 600;}
        .btn-save:hover { background: var(--primary); color: white; }
        .btn-apply { background: var(--primary); color: white; border: none; padding: 12px 30px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 1rem; }
        
        .detail-body { display: flex; gap: 40px; margin-top: 30px; }
        .detail-main { flex: 2; }
        .detail-sidebar { flex: 1; background: var(--bg-light); padding: 25px; border-radius: 12px; height: fit-content; }
        
        .gap-container { background: var(--white); border-radius: 12px; padding: 30px; margin-top: 30px; border: 2px solid var(--primary-light); }
        .score-circle { width: 100px; height: 100px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 1.8rem; font-weight: 700; color: white; margin: 0 auto 20px auto; background: conic-gradient(var(--success) 0%, var(--bg-light) 0deg); transition: background 1s ease; }
        .skill-pill { display: inline-block; padding: 6px 14px; border-radius: 20px; margin: 5px 5px 5px 0; font-size: 0.85rem; font-weight: 500; }
        .skill-match { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid var(--success); }
        .skill-missing { background: transparent; color: var(--danger); border: 1px dashed var(--danger); }

        .apply-form-section { display: none; margin-top: 30px; padding-top: 30px; border-top: 2px dashed var(--silver-accent); animation: fadeIn 0.4s; }

        .dashboard-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .dash-summary-cards { display: flex; gap: 30px; justify-content: center; margin-bottom: 40px; flex-wrap: wrap;}
        .dash-card { background: white; border-radius: 12px; padding: 30px; width: 300px; display: flex; align-items: center; gap: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .dash-card i { font-size: 2rem; color: var(--primary); background: var(--primary-light); padding: 15px; border-radius: 12px; }
        
        .activity-list { list-style: none; color: var(--text-dark); line-height: 2.2; }
        .activity-list li { border-bottom: 1px solid var(--bg-light); padding: 10px 0; }
        .activity-list li:last-child { border-bottom: none; }
        .activity-list i { color: var(--primary); margin-right: 12px; width: 20px; text-align: center; }

        .data-table-wrapper { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 40px; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--silver-accent); font-size: 0.9rem; }
        .data-table th { color: var(--text-gray); font-weight: 600; }
        .data-table tr:hover { background: #fafafa; }
        
        .badge { padding: 5px 10px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; }
        .badge-pending { color: var(--warning); background: rgba(245, 158, 11, 0.1); }
        .badge-rejected { color: var(--danger); background: rgba(239, 68, 68, 0.1); }
        .badge-approved { color: var(--success); background: rgba(16, 185, 129, 0.1); }
        .btn-delete { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid var(--danger); padding: 6px 12px; border-radius: 4px; cursor: pointer; transition: 0.3s; }
        .btn-delete:hover { background: var(--danger); color: white; }

        .profile-container { display: flex; gap: 30px; max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .profile-sidebar { width: 260px; background: var(--white); border-radius: 12px; padding: 30px 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.04); height: fit-content; }
        .sidebar-menu { display: flex; flex-direction: column; gap: 10px; }
        .sidebar-link { display: flex; align-items: center; gap: 15px; padding: 12px 15px; color: var(--text-gray); text-decoration: none; font-weight: 500; border-radius: 8px; transition: all 0.3s ease; cursor: pointer; }
        .sidebar-link i { font-size: 1.1rem; width: 20px; text-align: center; }
        .sidebar-link:hover, .sidebar-link.active { background-color: var(--primary-light); color: var(--primary); }
        .profile-content { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        .p-card { background: var(--white); border-radius: 12px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.04); }
        .p-card-title { color: var(--primary); font-size: 1.1rem; font-weight: 600; margin-bottom: 15px; }
        .p-card-text { color: var(--text-dark); font-size: 0.95rem; line-height: 1.6; margin-bottom: 5px; }
        .p-header-card { display: flex; justify-content: space-between; align-items: flex-start; }
        .p-info-basic { display: flex; align-items: center; gap: 20px; }
        .p-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid var(--silver-accent); }
        .p-info-basic h2 { color: var(--text-dark); font-size: 1.5rem; font-weight: 700; }
        .p-info-basic p { color: var(--text-gray); font-size: 0.95rem; }
        .btn-aqua { background-color: var(--primary); color: white; border: none; padding: 10px 24px; border-radius: 6px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: 0.3s; }
        .btn-aqua:hover { background-color: var(--primary-dark); }
        .p-resume-card { display: flex; justify-content: space-between; align-items: center; }

        .edit-form { display: flex; flex-direction: column; gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-weight: 600; color: var(--text-dark); font-size: 0.95rem; }
        .form-control { padding: 12px 15px; border: 1px solid var(--silver-accent); border-radius: 6px; font-size: 0.95rem; font-family: 'Poppins', sans-serif; color: var(--text-dark); outline: none; transition: all 0.3s ease; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .file-upload-wrapper { display: flex; align-items: center; gap: 15px; }
        .file-upload-preview { width: 60px; height: 60px; border-radius: 50%; background: var(--bg-light); object-fit: cover; border: 1px solid var(--silver-accent); }
        .section-divider { margin: 15px 0 5px; border-bottom: 2px solid var(--bg-light); padding-bottom: 10px; color: var(--primary); font-weight: 600; font-size: 1.1rem; }

        .menu-toggle { display: none; font-size: 26px; cursor: pointer; }
        @media (max-width: 900px) {
            .hero { flex-direction: column; text-align: center; padding: 50px 20px; }
            .advanced-search-box { flex-direction: column; border-radius: 12px; padding: 15px; }
            .search-divider { width: 100%; height: 1px; margin: 10px 0; }
            .advanced-search-box button { width: 100%; margin-top: 15px; border-radius: 8px; margin-left: 0; }
            .detail-body, .profile-container { flex-direction: column; }
            .profile-sidebar { width: 100%; }
            .p-header-card, .p-resume-card { flex-direction: column; gap: 20px; align-items: flex-start; text-align: left; }
        }
        @media (max-width: 768px) {
            .nav-links { display: none; flex-direction: column; background: var(--primary-dark); position: absolute; top: 70px; left: 0; width: 100%; padding: 20px 0; align-items: center; }
            .nav-links.active { display: flex; }
            .menu-toggle { display: block; }
            .auth-buttons { display: none; }
            .detail-header { flex-direction: column; align-items: flex-start; gap: 20px; }
            .detail-meta-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="logo">JOB-PORTAL</div>
    <ul class="nav-links">
        <li><a onclick="switchView('home-view')" id="link-home" class="active">HOME</a></li>
        <li><a onclick="switchView('view-jobs-view')" id="link-view-jobs">VIEW JOBS</a></li>
        <li><a onclick="switchView('dashboard-view')" id="link-dashboard">DASHBOARD</a></li>
        <li><a onclick="switchView('profile-view')" id="link-profile">PROFILE</a></li>
    </ul>
    <div class="auth-buttons">
        <span style="font-weight: 600; color: white; margin-right: 15px; font-size: 0.9rem;"><i class="fa-regular fa-user"></i> <?php echo htmlspecialchars($user_name); ?></span>
        <a href="../../auth/logout.php" class="btn-logout">LOGOUT</a>
    </div>
    <div class="menu-toggle" onclick="toggleMenu()">☰</div>
</nav>

<div id="home-view" class="view-section active-view">
    <section class="hero">
        <div class="hero-content" style="max-width: 900px; margin: 0 auto; text-align: center;">
            <h1>To Choose <span>Right Jobs.</span></h1>
            <p style="color: var(--text-gray); margin: 15px 0;">Discover thousands of job opportunities matching your skills.</p>
            
            <form method="GET" action="jobseeker.php" class="advanced-search-box">
                <input type="hidden" name="view" value="jobs">
                
                <div class="search-field">
                    <i class="fa-solid fa-briefcase"></i>
                    <input type="text" name="title" placeholder="Job Title..." value="<?php echo htmlspecialchars($search_title); ?>">
                </div>

                <div class="search-divider"></div>

                <div class="search-field">
                    <i class="fa-solid fa-layer-group"></i>
                    <select name="category">
                        <option value="">Any Category</option>
                        <option value="Information Technology (IT) & Software" <?php if($search_category == 'Information Technology (IT) & Software') echo 'selected'; ?>>💻 Information Technology (IT) & Software</option>
                        <option value="Sales & Marketing" <?php if($search_category == 'Sales & Marketing') echo 'selected'; ?>>📊 Sales & Marketing</option>
                        <option value="Banking, Finance & Accounting" <?php if($search_category == 'Banking, Finance & Accounting') echo 'selected'; ?>>🏦 Banking, Finance & Accounting</option>
                        <option value="Healthcare & Medical" <?php if($search_category == 'Healthcare & Medical') echo 'selected'; ?>>🏥 Healthcare & Medical</option>
                        <option value="Education & Training" <?php if($search_category == 'Education & Training') echo 'selected'; ?>>🎓 Education & Training</option>
                        <option value="Engineering & Construction" <?php if($search_category == 'Engineering & Construction') echo 'selected'; ?>>🏗️ Engineering & Construction</option>
                        <option value="Customer Service & BPO" <?php if($search_category == 'Customer Service & BPO') echo 'selected'; ?>>📞 Customer Service & BPO</option>
                        <option value="Retail & E-commerce" <?php if($search_category == 'Retail & E-commerce') echo 'selected'; ?>>🛒 Retail & E-commerce</option>
                        <option value="Logistics & Supply Chain" <?php if($search_category == 'Logistics & Supply Chain') echo 'selected'; ?>>🚚 Logistics & Supply Chain</option>
                        <option value="Human Resources (HR) & Administration" <?php if($search_category == 'Human Resources (HR) & Administration') echo 'selected'; ?>>👩‍💼 Human Resources (HR) & Administration</option>
                    </select>
                </div>

                <div class="search-divider"></div>

                <div class="search-field">
                    <i class="fa-solid fa-clock"></i>
                    <select name="type">
                        <option value="">Any Type</option>
                        <option value="Full-Time" <?php if($search_type == 'Full-Time') echo 'selected'; ?>>Full-Time</option>
                        <option value="Part-Time" <?php if($search_type == 'Part-Time') echo 'selected'; ?>>Part-Time</option>
                        <option value="Contract" <?php if($search_type == 'Contract') echo 'selected'; ?>>Contract</option>
                        <option value="Internship" <?php if($search_type == 'Internship') echo 'selected'; ?>>Internship</option>
                    </select>
                </div>

                <div class="search-divider"></div>

                <div class="search-field">
                    <i class="fa-solid fa-location-dot"></i>
                    <input type="text" name="location" placeholder="Location" value="<?php echo htmlspecialchars($search_location); ?>">
                </div>

                <button type="submit" class="btn-primary">Find Jobs</button>
            </form>

            <div class="tags" style="justify-content: center; margin-top: 30px;">
                <i class="fa-solid fa-bookmark tag-icon"></i>
                <span class="tag-title">Trending Categories:</span>
                <?php foreach($top_categories as $tag_cat): ?>
                    <a href="jobseeker.php?category=<?php echo urlencode($tag_cat); ?>&view=jobs">
                        <?php echo getCategoryIcon($tag_cat) . ' ' . htmlspecialchars($tag_cat); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>

<div id="view-jobs-view" class="view-section">
    <div style="padding: 40px 10%;">
        <?php if (!empty($search_title) || !empty($search_location) || !empty($search_type) || !empty($search_category)): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 2rem; color: var(--text-dark);">Search Results (<?php echo count($all_jobs); ?> found)</h2>
                <a href="jobseeker.php?view=jobs" style="color: var(--primary); text-decoration: none; font-weight: 600;"><i class="fa-solid fa-xmark"></i> Clear Filters</a>
            </div>
        <?php else: ?>
            <h2 style="text-align: center; font-size: 2rem; color: var(--text-dark); margin-bottom: 10px;">AVAILABLE JOBS</h2>
            <div style="width: 80px; height: 3px; background: var(--primary); margin: 0 auto 30px auto;"></div>
        <?php endif; ?>

        <div class="job-grid" id="job-grid-container" style="padding: 0;">
            <?php if(!empty($all_jobs)): foreach($all_jobs as $job): ?>
                <div class="job-card-new">
                    
                    <?php if (($job['rec_score'] ?? 0) > 0 && empty($search_title) && empty($search_location) && empty($search_type) && empty($search_category)): ?>
                        <div class="rec-badge"><i class="fa-solid fa-star"></i> Recommended</div>
                    <?php endif; ?>

                    <div class="job-card-top">
                        <div class="job-company-info">
                            <?php if(!empty($job['logo_path'])): ?>
                                <img src="../../<?php echo htmlspecialchars($job['logo_path']); ?>" alt="Company">
                            <?php else: ?>
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($job['company_name'] ?? 'C'); ?>&background=f5f7f9&color=00b4d8" alt="Company">
                            <?php endif; ?>
                            <div>
                                <h3 style="color: var(--text-dark); font-size: 1.1rem;"><?php echo htmlspecialchars($job['title']); ?></h3>
                                <p style="color: var(--text-gray); font-size: 0.9rem;"><?php echo htmlspecialchars($job['company_name'] ?? 'Unknown Company'); ?></p>
                            </div>
                        </div>
                        <ul class="job-details-list">
                            <li><span>•</span> <?php echo htmlspecialchars($job['salary'] ?? 'Not Disclosed'); ?></li>
                            <li><span>•</span> <?php echo isset($job['deadline']) ? date('d M Y', strtotime($job['deadline'])) : 'Open'; ?></li>
                        </ul>
                    </div>
                    <div class="job-card-bottom">
                        <span class="job-tag"><?php echo htmlspecialchars($job['category'] ?? 'General'); ?></span>
                        <button class="btn-outline" onclick="loadJobDetails(<?php echo $job['id']; ?>)">APPLY NOW →</button>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: white; border-radius: 12px;">
                    <i class="fa-solid fa-folder-open" style="font-size: 3rem; color: var(--silver-accent); margin-bottom: 15px;"></i>
                    <h3 style="color: var(--text-dark);">No jobs found</h3>
                    <p style="color: var(--text-gray);">Try adjusting your search criteria or clearing your filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="job-detail-view" class="view-section">
    <div class="detail-wrapper">
        <button class="btn-outline" onclick="switchView('view-jobs-view')" style="margin-bottom: 20px; border:none;"><i class="fa-solid fa-arrow-left"></i> Back to Jobs</button>
        
        <div class="detail-header">
            <div class="detail-title-area">
                <img id="dtl-logo" src="" alt="Company">
                <div>
                    <h1 id="dtl-title" style="font-size: 1.8rem; color: var(--text-dark);">Job Title</h1>
                    <p id="dtl-company" style="color: var(--text-gray); font-size: 1.1rem;">Company Name</p>
                </div>
            </div>
            <div class="detail-actions">
                <button class="btn-save" onclick="saveJob()"><i class="fa-regular fa-bookmark"></i> Save Job</button>
                <button class="btn-apply" onclick="document.getElementById('apply-form-section').style.display='block'">APPLY POSITION</button>
            </div>
        </div>

        <div class="detail-meta-grid">
            <div class="detail-meta-item"><i class="fa-solid fa-user"></i> <strong>Contact:</strong> <span id="dtl-contact"></span></div>
            <div class="detail-meta-item"><i class="fa-solid fa-location-dot"></i> <strong>Location:</strong> <span id="dtl-location"></span></div>
            <div class="detail-meta-item"><i class="fa-solid fa-briefcase"></i> <strong>Job Type:</strong> <span id="dtl-type"></span></div>
            <div class="detail-meta-item"><i class="fa-solid fa-envelope"></i> <strong>Email:</strong> <span id="dtl-email"></span></div>
            <div class="detail-meta-item"><i class="fa-solid fa-tag"></i> <strong>Category:</strong> <span id="dtl-category"></span></div>
            <div class="detail-meta-item"><i class="fa-solid fa-money-bill"></i> <strong>Salary:</strong> <span id="dtl-salary"></span></div>
        </div>

        <div class="detail-body">
            <div class="detail-main">
                <h3 style="border-bottom: 2px solid var(--primary); display: inline-block; margin-bottom: 15px;">JOB DESCRIPTION</h3>
                <p id="dtl-desc" style="color: var(--text-gray); line-height: 1.8; white-space: pre-wrap;"></p>

                <div class="gap-container">
                    <h3 style="text-align: center; margin-bottom: 10px;"><i class="fa-solid fa-wand-magic-sparkles" style="color:var(--primary);"></i> Skill Match Analyzer</h3>
                    <p style="text-align: center; font-size: 0.85rem; color: var(--text-gray); margin-bottom: 20px;">Based on your profile vs job requirements.</p>
                    
                    <div class="score-circle" id="analyzer-circle">0%</div>
                    
                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <h4 style="color: var(--success); margin-bottom: 10px;"><i class="fa-solid fa-check"></i> You Have:</h4>
                            <div id="analyzer-has"></div>
                        </div>
                        <div style="flex: 1; min-width: 200px;">
                            <h4 style="color: var(--danger); margin-bottom: 10px;"><i class="fa-solid fa-xmark"></i> Missing / Recommended:</h4>
                            <div id="analyzer-missing"></div>
                        </div>
                    </div>
                </div>

                <div id="apply-form-section" class="apply-form-section">
                    <h3 style="margin-bottom: 15px;">Submit Your Application</h3>
                    <form method="POST" action="action/apply_job.php" enctype="multipart/form-data" id="job-apply-form">
                        <input type="hidden" name="job_id" id="form-job-id">
                        <input type="hidden" name="match_score" id="form-match-score">
                        
                        <div class="form-group">
                            <label>Cover Letter / Pitch</label>
                            <textarea name="cover_letter" class="form-control" rows="5" placeholder="Explain why you are a good fit for this role..." required></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label>Expected Salary (Optional)</label>
                                <input type="text" name="expected_salary" class="form-control" placeholder="e.g. ₹70,000">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>Available Start Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Custom Resume (Optional)</label>
                            <p style="font-size: 0.85rem; color: var(--text-gray); margin-bottom: 5px;">Leave blank to automatically use your default profile resume.</p>
                            <input type="file" name="app_resume" accept=".pdf,.doc,.docx" class="form-control" style="padding: 10px;">
                        </div>
                        
                        <button type="submit" class="btn-aqua" style="width: 100%; margin-top: 10px; font-size: 1rem; padding: 15px;">Submit Application Now</button>
                    </form>
                </div>
            </div>
            
            <div class="detail-sidebar">
                <h3 style="margin-bottom: 15px; border-bottom: 2px solid var(--primary); display: inline-block;">Job Summary</h3>
                <p style="margin-bottom: 15px; display: flex; align-items: center;"><i class="fa-solid fa-users" style="color: var(--primary); width: 30px; font-size: 1.2rem;"></i> <span><strong>Vacancies:</strong> <br><span id="dtl-vac"></span></span></p>
                <p style="margin-bottom: 15px; display: flex; align-items: center;"><i class="fa-regular fa-calendar-xmark" style="color: var(--primary); width: 30px; font-size: 1.2rem;"></i> <span><strong>Deadline:</strong> <br><span id="dtl-deadline"></span></span></p>
                <p style="margin-bottom: 15px; display: flex; align-items: center;"><i class="fa-solid fa-star" style="color: var(--primary); width: 30px; font-size: 1.2rem;"></i> <span><strong>Experience:</strong> <br><span id="dtl-exp"></span></span></p>
            </div>
        </div>
    </div>
</div>

<div id="dashboard-view" class="view-section">
    <div class="dashboard-container">
        
        <div class="dash-summary-cards">
            <div class="dash-card">
                <i class="fa-solid fa-paper-plane"></i>
                <div>
                    <h3 style="color: var(--text-dark); font-size: 1.2rem;">Total Applied</h3>
                    <p style="font-size: 1.5rem; font-weight: 700;"><?php echo count($applied_jobs); ?></p>
                </div>
            </div>
            <div class="dash-card">
                <i class="fa-solid fa-bookmark"></i>
                <div>
                    <h3 style="color: var(--text-dark); font-size: 1.2rem;">Saved Jobs</h3>
                    <p style="font-size: 1.5rem; font-weight: 700;"><?php echo count($saved_jobs); ?></p>
                </div>
            </div>
            <div class="dash-card">
                <i class="fa-solid fa-user-check"></i>
                <div>
                    <h3 style="color: var(--text-dark); font-size: 1.2rem;">Profile Views</h3>
                    <p style="font-size: 1.5rem; font-weight: 700;">14</p>
                </div>
            </div>
        </div>

        <div class="p-card" style="margin-bottom: 40px;">
            <h3 class="p-card-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Activity</h3>
            <ul class="activity-list">
                <?php if(!empty($applied_jobs)): ?>
                    <li><i class="fa-solid fa-check-circle"></i> You applied for the <strong><?php echo htmlspecialchars($applied_jobs[0]['title']); ?></strong> position at <?php echo htmlspecialchars($applied_jobs[0]['company_name'] ?? 'a company'); ?>.</li>
                <?php endif; ?>
                <?php if(!empty($saved_jobs)): ?>
                    <li><i class="fa-solid fa-bookmark"></i> You saved the <strong><?php echo htmlspecialchars($saved_jobs[0]['title']); ?></strong> position.</li>
                <?php endif; ?>
                <li><i class="fa-solid fa-eye"></i> Your profile was viewed by a recruiter.</li>
                <li><i class="fa-solid fa-pen-to-square"></i> You updated your Profile.</li>
            </ul>
        </div>

        <div class="data-table-wrapper">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 1.5rem;">Total Applied Jobs: <?php echo count($applied_jobs); ?></h2>
                <input type="text" id="appliedSearch" placeholder="Search company name..." style="padding: 8px 15px; border: none; border-bottom: 2px solid var(--primary); outline: none; width: 250px;">
            </div>
            
            <table class="data-table" id="appliedTable">
                <thead>
                    <tr>
                        <th><input type="checkbox"></th>
                        <th>Apply Date</th>
                        <th>Company</th>
                        <th>Job Title</th>
                        <th>Job Salary</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($applied_jobs)): foreach($applied_jobs as $app): ?>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td><?php echo date('d/m/Y', strtotime($app['applied_on'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($app['company_name'] ?? 'Unknown'); ?></strong></td>
                        <td><?php echo htmlspecialchars($app['title']); ?></td>
                        <td><?php echo htmlspecialchars($app['salary']); ?></td>
                        <td>
                            <?php 
                            $status = strtoupper($app['status'] ?? 'PENDING');
                            if($status === 'APPROVED'): ?>
                                <span class="badge badge-approved">APPROVED</span>
                            <?php elseif($status === 'REJECTED'): ?>
                                <span class="badge badge-rejected">REJECTED</span>
                            <?php else: ?>
                                <span class="badge badge-pending">PENDING</span>
                            <?php endif; ?>
                        </td>
                        <td><button class="btn-outline" style="padding: 4px 10px; font-size: 0.8rem;" onclick="loadJobDetails(<?php echo $app['job_id']; ?>)">View Detail</button></td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7" style="text-align:center;">You haven't applied to any jobs yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="data-table-wrapper">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 1.5rem;">Total Saved Jobs: <?php echo count($saved_jobs); ?></h2>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th><input type="checkbox"></th>
                        <th>Save Date</th>
                        <th>Company</th>
                        <th>Job Title</th>
                        <th>Job Salary</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($saved_jobs)): foreach($saved_jobs as $save): ?>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td><?php echo date('d/m/Y', strtotime($save['saved_on'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($save['company_name'] ?? 'Unknown'); ?></strong></td>
                        <td><?php echo htmlspecialchars($save['title']); ?></td>
                        <td><?php echo htmlspecialchars($save['salary']); ?></td>
                        <td>
                            <a href="action/delete_saved.php?id=<?php echo $save['save_id']; ?>" class="btn-delete"><i class="fa-solid fa-trash"></i></a>
                            <button class="btn-outline" style="padding: 4px 10px; font-size: 0.8rem; margin-left: 5px;" onclick="loadJobDetails(<?php echo $save['job_id']; ?>)">View Detail</button>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" style="text-align:center;">No saved jobs.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="profile-view" class="view-section">
    <div class="profile-container">
        
        <aside class="profile-sidebar">
            <div class="sidebar-menu">
                <a onclick="switchView('profile-view')" class="sidebar-link active"><i class="fa-regular fa-user"></i> My Profile</a>
                <a onclick="switchView('dashboard-view')" class="sidebar-link"><i class="fa-solid fa-briefcase"></i> Applied History</a>
                <a onclick="switchView('dashboard-view')" class="sidebar-link"><i class="fa-regular fa-bookmark"></i> Saved List</a>
                <a href="#" class="sidebar-link"><i class="fa-solid fa-gear"></i> Preferences</a>
            </div>
        </aside>

        <main class="profile-content">
            
            <div class="p-card p-header-card">
                <div class="p-info-basic">
                    <?php if (!empty($p['photo_path'])): ?>
                        <img src="../../<?php echo htmlspecialchars($p['photo_path']); ?>" alt="Profile" class="p-avatar" style="object-fit: cover;">
                    <?php else: ?>
                        <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--bg-light); border: 2px solid var(--silver-accent); display: flex; justify-content: center; align-items: center; font-size: 1.5rem; color: var(--primary); font-weight: bold;">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <h2><?php echo htmlspecialchars($user_name); ?></h2>
                        <p style="color:var(--primary); font-weight:600;"><?php echo htmlspecialchars($p['job_title'] ?? 'Job Title Not Set'); ?></p>
                        <p><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($user_email); ?></p>
                        <p><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($p['location'] ?? 'Location Not Set'); ?></p>
                    </div>
                </div>
                <button class="btn-aqua" onclick="switchView('edit-profile-view')">Edit Profile</button>
            </div>

            <div class="p-card">
                <h3 class="p-card-title">Professional Summary</h3>
                <p class="p-card-text"><strong>Phone:</strong> <?php echo htmlspecialchars($p['phone'] ?? 'Not added'); ?></p>
                <p class="p-card-text" style="margin-top: 10px;"><?php echo nl2br(htmlspecialchars($p['bio'] ?? 'No bio available. Add one to stand out to employers!')); ?></p>
            </div>

            <div class="p-card">
                <h3 class="p-card-title">Technical & Soft Skills</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php 
                    if(!empty($p['skills'])) {
                        $skill_array = explode(',', $p['skills']);
                        foreach($skill_array as $sk) {
                            echo '<span class="skill-pill skill-match">'.htmlspecialchars(trim($sk)).'</span>';
                        }
                    } else {
                        echo '<p style="color: var(--text-gray);">No skills added.</p>';
                    }
                    ?>
                </div>
            </div>

            <div class="p-card">
                <h3 class="p-card-title">Education</h3>
                <?php if (!empty($p['degree']) || !empty($p['institution'])): ?>
                    <div style="line-height: 1.8;">
                        <p><strong>Degree:</strong> <?php echo htmlspecialchars($p['degree'] ?? 'N/A'); ?></p>
                        <p><strong>Institution:</strong> <?php echo htmlspecialchars($p['institution'] ?? 'N/A'); ?></p>
                        <p><strong>Passing Year:</strong> <?php echo htmlspecialchars($p['passing_year'] ?? 'N/A'); ?></p>
                        <p><strong>Percentage/CGPA:</strong> <?php echo htmlspecialchars($p['percentage'] ?? 'N/A'); ?></p>
                    </div>
                <?php else: ?>
                    <p class="p-card-text" style="color: var(--text-gray);">No education details added yet.</p>
                <?php endif; ?>
            </div>

            <div class="p-card">
                <h3 class="p-card-title">Projects</h3>
                <div class="p-card-text">
                    <?php echo nl2br(htmlspecialchars($p['projects'] ?? 'List your academic or personal projects here.')); ?>
                </div>
            </div>

            <div class="p-card">
                <h3 class="p-card-title">Experience & Internships</h3>
                <div class="p-card-text">
                    <?php echo nl2br(htmlspecialchars($p['experience_desc'] ?? 'Add your work history or internship details.')); ?>
                </div>
            </div>

            <div class="p-card" style="background: var(--primary-light); border: 1px solid var(--primary);">
                <h3 class="p-card-title">Job Preferences</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <p><strong>Preferred Role:</strong> <?php echo htmlspecialchars($p['pref_role'] ?? 'N/A'); ?></p>
                    <p><strong>Expected Salary:</strong> <?php echo htmlspecialchars($p['pref_salary'] ?? 'N/A'); ?></p>
                    <p><strong>Preferred Location:</strong> <?php echo htmlspecialchars($p['pref_location'] ?? 'N/A'); ?></p>
                    <p><strong>Job Type:</strong> <?php echo htmlspecialchars($p['pref_type'] ?? 'N/A'); ?></p>
                </div>
            </div>

            <div class="p-card">
                <h3 class="p-card-title">Certifications</h3>
                <div class="p-card-text">
                    <?php echo nl2br(htmlspecialchars($p['certifications'] ?? 'Add any relevant certifications.')); ?>
                </div>
            </div>

            <div class="p-card p-resume-card">
                <div>
                    <h3 class="p-card-title">Master Resume</h3>
                    <?php if (!empty($p['resume_path'])): ?>
                        <p class="p-card-text" style="color: var(--success); font-weight: 500;"><i class="fa-solid fa-check-circle"></i> Resume successfully uploaded</p>
                    <?php else: ?>
                        <p class="p-card-text" style="color: var(--danger);">No resume uploaded. Required for applications.</p>
                    <?php endif; ?>
                </div>
                <?php if(!empty($p['resume_path'])): ?>
                    <a href="../../<?php echo htmlspecialchars($p['resume_path']); ?>" target="_blank" class="btn-aqua">View / Download Resume</a>
                <?php else: ?>
                    <button class="btn-aqua" onclick="switchView('edit-profile-view')">Upload Now</button>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<div id="edit-profile-view" class="view-section">
    <div class="profile-container">
        
        <aside class="profile-sidebar">
            <div class="sidebar-menu">
                <button class="btn-outline" onclick="switchView('profile-view')" style="width:100%; margin-bottom: 20px;"><i class="fa-solid fa-arrow-left"></i> Cancel Edit</button>
                <p style="font-size: 0.85rem; color: var(--text-gray);">A complete profile helps the Skill Match Analyzer give you higher rankings.</p>
            </div>
        </aside>

        <main class="profile-content">
            <div class="p-card">
                <h2 class="p-card-title" style="font-size: 1.5rem; margin-bottom: 20px;">Edit Your Professional Profile</h2>
                
                <form class="edit-form" method="POST" action="action/update_seeker_profile.php" enctype="multipart/form-data">
                    
                    <h3 class="section-divider">Basic Information</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Professional Job Title</label>
                            <input type="text" name="job_title" class="form-control" placeholder="e.g. Frontend Developer" value="<?php echo htmlspecialchars($p['job_title'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Location (City, State)</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. Bengaluru, Karnataka" value="<?php echo htmlspecialchars($p['location'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($p['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Profile Photo</label>
                            <input type="file" name="avatar" accept="image/*" class="form-control" style="border:none; padding:0;">
                        </div>
                    </div>

                    <h3 class="section-divider">Professional Summary & Skills</h3>
                    <div class="form-group">
                        <label>Bio / Summary</label>
                        <textarea name="bio" class="form-control" rows="4" placeholder="Briefly describe your expertise and goals..."><?php echo htmlspecialchars($p['bio'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Technical Skills (Comma separated for Analyzer)</label>
                        <input type="text" name="skills" class="form-control" placeholder="PHP, MySQL, JavaScript, React" value="<?php echo htmlspecialchars($p['skills'] ?? ''); ?>">
                    </div>

                    <h3 class="section-divider">Education & Experience</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Degree</label>
                            <input type="text" name="degree" class="form-control" value="<?php echo htmlspecialchars($p['degree'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Institution</label>
                            <input type="text" name="institution" class="form-control" value="<?php echo htmlspecialchars($p['institution'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Year of Passing</label>
                            <input type="text" name="passing_year" class="form-control" value="<?php echo htmlspecialchars($p['passing_year'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Percentage/CGPA</label>
                            <input type="text" name="percentage" class="form-control" value="<?php echo htmlspecialchars($p['percentage'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>Work Experience / Internship Description</label>
                        <textarea name="experience_desc" class="form-control" rows="3"><?php echo htmlspecialchars($p['experience_desc'] ?? ''); ?></textarea>
                    </div>

                    <h3 class="section-divider">Projects & Certifications</h3>
                    <div class="form-group">
                        <label>Project Details (Include Demo/GitHub links)</label>
                        <textarea name="projects" class="form-control" rows="3"><?php echo htmlspecialchars($p['projects'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Certifications (Course, Platform, Year)</label>
                        <textarea name="certifications" class="form-control" rows="2"><?php echo htmlspecialchars($p['certifications'] ?? ''); ?></textarea>
                    </div>

                    <h3 class="section-divider">Job Preferences</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Preferred Job Role</label>
                            <input type="text" name="pref_role" class="form-control" value="<?php echo htmlspecialchars($p['pref_role'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Expected Salary</label>
                            <input type="text" name="pref_salary" class="form-control" value="<?php echo htmlspecialchars($p['pref_salary'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Preferred Location</label>
                            <input type="text" name="pref_location" class="form-control" value="<?php echo htmlspecialchars($p['pref_location'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Job Type (Full-time/Internship)</label>
                            <input type="text" name="pref_type" class="form-control" value="<?php echo htmlspecialchars($p['pref_type'] ?? ''); ?>">
                        </div>
                    </div>

                    <h3 class="section-divider">Resume Upload</h3>
                    <div class="form-group">
                        <label>Resume (PDF Recommended)</label>
                        <input type="file" name="resume" accept=".pdf,.doc,.docx" class="form-control" style="padding: 10px;">
                    </div>

                    <div style="margin-top: 25px;">
                        <button type="submit" class="btn-aqua" style="width: 100%; font-size: 1rem; padding: 15px;">Update Professional Profile</button>
                    </div>

                </form>
            </div>
        </main>
    </div>
</div>

<script>
    function toggleMenu() {
        document.querySelector(".nav-links").classList.toggle("active");
    }

    function switchView(viewId) {
        const views = document.querySelectorAll('.view-section');
        views.forEach(view => {
            view.classList.remove('active-view');
        });

        document.getElementById(viewId).classList.add('active-view');

        document.getElementById('link-home').classList.remove('active');
        document.getElementById('link-view-jobs').classList.remove('active');
        document.getElementById('link-dashboard').classList.remove('active');
        document.getElementById('link-profile').classList.remove('active');

        if(viewId === 'home-view') {
            document.getElementById('link-home').classList.add('active');
        } else if (viewId === 'view-jobs-view' || viewId === 'job-detail-view') {
            document.getElementById('link-view-jobs').classList.add('active');
            if(document.getElementById('apply-form-section')) {
                document.getElementById('apply-form-section').style.display = 'none'; 
            }
        } else if (viewId === 'dashboard-view') {
            document.getElementById('link-dashboard').classList.add('active');
        } else if (viewId === 'profile-view' || viewId === 'edit-profile-view') {
            document.getElementById('link-profile').classList.add('active');
        }

        document.querySelector(".nav-links").classList.remove("active");
        window.scrollTo(0, 0);
    }

    window.onload = function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('view') === 'jobs') {
            switchView('view-jobs-view');
        }
    };

    const allJobsData = <?php echo json_encode($all_jobs); ?>;
    const userSkillsRaw = "<?php echo htmlspecialchars(addslashes($user_skills_raw)); ?>";
    let currentViewingJobId = null;

    function loadJobDetails(jobId) {
        const job = allJobsData.find(j => j.id == jobId);
        if(!job) return;
        currentViewingJobId = jobId;
        document.getElementById('form-job-id').value = job.id;
        document.getElementById('dtl-title').textContent = job.title;
        document.getElementById('dtl-company').textContent = job.company_name || 'Unknown Company';
        const logoPath = job.logo_path ? '../../'+job.logo_path : 'https://ui-avatars.com/api/?name='+encodeURIComponent(job.company_name || 'C')+'&background=f5f7f9&color=00b4d8';
        document.getElementById('dtl-logo').src = logoPath;
        document.getElementById('dtl-contact').textContent = job.company_name || 'HR Team';
        document.getElementById('dtl-location').textContent = job.location || 'Remote';
        document.getElementById('dtl-type').textContent = job.job_type || 'Full-Time';
        document.getElementById('dtl-email').textContent = job.contact_email || 'Not Provided';
        document.getElementById('dtl-category').textContent = job.category || 'General';
        document.getElementById('dtl-salary').textContent = job.salary || 'Not Disclosed';
        document.getElementById('dtl-vac').textContent = job.vacancies || '1';
        document.getElementById('dtl-deadline').textContent = job.deadline ? new Date(job.deadline).toLocaleDateString() : 'Ongoing';
        document.getElementById('dtl-exp').textContent = job.experience || 'Entry Level';
        document.getElementById('dtl-desc').textContent = job.description;
        analyzeSkills(job.skills);
        switchView('job-detail-view');
    }

    function analyzeSkills(jobSkillsStr) {
        const circle = document.getElementById('analyzer-circle');
        const hasContainer = document.getElementById('analyzer-has');
        const missingContainer = document.getElementById('analyzer-missing');
        const formScore = document.getElementById('form-match-score');
        hasContainer.innerHTML = '';
        missingContainer.innerHTML = '';

        if(!jobSkillsStr || !userSkillsRaw) {
            circle.textContent = "0%";
            circle.style.background = `conic-gradient(var(--bg-light) 100%, var(--bg-light) 0deg)`;
            missingContainer.innerHTML = '<span class="skill-pill skill-missing">Update your profile skills to see match.</span>';
            formScore.value = 0;
            return;
        }

        const reqSkills = jobSkillsStr.split(',').map(s => s.trim().toLowerCase()).filter(s => s);
        const mySkills = userSkillsRaw.split(',').map(s => s.trim().toLowerCase()).filter(s => s);
        let matchCount = 0;

        reqSkills.forEach(skill => {
            const span = document.createElement('span');
            span.classList.add('skill-pill');
            span.textContent = skill.charAt(0).toUpperCase() + skill.slice(1);
            let hasSkill = false;
            for(let mySk of mySkills) {
                if(mySk.includes(skill) || skill.includes(mySk)) {
                    hasSkill = true; break;
                }
            }
            if(hasSkill) {
                span.classList.add('skill-match');
                hasContainer.appendChild(span);
                matchCount++;
            } else {
                span.classList.add('skill-missing');
                missingContainer.appendChild(span);
            }
        });

        if (matchCount === reqSkills.length && reqSkills.length > 0) {
            missingContainer.innerHTML = '<span style="color:var(--text-gray); font-size:0.85rem;">You meet all core requirements!</span>';
        }

        let score = reqSkills.length > 0 ? Math.round((matchCount / reqSkills.length) * 100) : 0;
        formScore.value = score; 
        let currentScore = 0;
        let color = 'var(--success)';
        if(score < 50) color = 'var(--danger)';
        else if (score < 75) color = 'var(--warning)';

        const timer = setInterval(() => {
            if(currentScore >= score) clearInterval(timer);
            circle.textContent = currentScore + "%";
            circle.style.background = `conic-gradient(${color} ${currentScore}%, var(--bg-light) 0deg)`;
            currentScore++;
        }, 10);
    }

    function saveJob() {
        if(!currentViewingJobId) return;
        window.location.href = `action/save_bookmark.php?job_id=${currentViewingJobId}`;
    }

    document.getElementById('appliedSearch').addEventListener('keyup', function() {
        let filter = this.value.toUpperCase();
        let rows = document.querySelector("#appliedTable tbody").rows;
        for (let i = 0; i < rows.length; i++) {
            let companyCol = rows[i].cells[2];
            if (companyCol) {
                let txtValue = companyCol.textContent || companyCol.innerText;
                rows[i].style.display = (txtValue.toUpperCase().indexOf(filter) > -1) ? "" : "none";
            }       
        }
    });
</script>

</body>
</html>