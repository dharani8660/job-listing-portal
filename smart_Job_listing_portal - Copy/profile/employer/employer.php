<?php
// ==============================================================================
// 1. SESSION & DATABASE SETUP
// ==============================================================================
session_start();

// Include the database connection file. Adjust the path if your directory structure changes.
include("../../config/db.php"); 

// Security Check: Ensure the user is logged in AND has the 'employer' role.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../../auth/index.php");
    exit();
}

// Store session variables locally for easy access
$user_name = $_SESSION['name'];
$user_id = $_SESSION['user_id'];

// ==============================================================================
// 2. DYNAMIC DATA FETCHING (SQL QUERIES - OPTIMIZED WITH PREPARED STATEMENTS)
// ==============================================================================

// A. Fetch Company Profile (Secured against SQL Injection)
$stmt_profile = $conn->prepare("SELECT * FROM employer_profiles WHERE user_id = ?");
$stmt_profile->bind_param("s", $user_id);
$stmt_profile->execute();
$profile_res = $stmt_profile->get_result();
$p = ($profile_res && $profile_res->num_rows > 0) ? $profile_res->fetch_assoc() : [];
$stmt_profile->close();

// B. Fetch Jobs Posted by this Employer (Optimized: Removed N+1 Query issue)
// Uses LEFT JOIN to get the applicant count in one single query instead of looping queries
$job_query = "SELECT j.*, COUNT(a.id) as applicant_count 
              FROM jobs j 
              LEFT JOIN applications a ON j.id = a.job_id 
              WHERE j.employer_id = ? 
              GROUP BY j.id 
              ORDER BY j.id DESC";
$stmt_jobs = $conn->prepare($job_query);
$stmt_jobs->bind_param("s", $user_id);
$stmt_jobs->execute();
$jobs_res = $stmt_jobs->get_result();

$jobs = [];
$active_jobs_count = 0;

if ($jobs_res && $jobs_res->num_rows > 0) {
    while ($row = $jobs_res->fetch_assoc()) {
        $jobs[] = $row;
        // Count how many of these jobs are currently ACTIVE for the dashboard metric
        if (isset($row['status']) && strtoupper($row['status']) === 'ACTIVE') {
            $active_jobs_count++;
        }
    }
}
$stmt_jobs->close();

// C. Fetch Applicants (Secured against SQL Injection)
$app_query = "SELECT a.*, j.title as job_title, u.name as candidate_name 
              FROM applications a 
              JOIN jobs j ON a.job_id = j.id 
              JOIN users u ON a.user_id = u.id 
              WHERE j.employer_id = ? 
              ORDER BY a.applied_on DESC";
$stmt_app = $conn->prepare($app_query);
$stmt_app->bind_param("s", $user_id);
$stmt_app->execute();
$applicants_res = $stmt_app->get_result();

$applicants = [];
$total_applicants = 0;

if ($applicants_res && $applicants_res->num_rows > 0) {
    while ($row = $applicants_res->fetch_assoc()) {
        $applicants[] = $row;
        $total_applicants++;
    }
}
$stmt_app->close();

// D. Mock Profile Views (Placeholder metric)
$profile_views = rand(50, 300); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Portal - Employer Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ==========================================
           CSS VARIABLES & RESET
           ========================================== */
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

        /* ==========================================
           GLOBAL NAVIGATION (Feature 1)
           ========================================== */
        .navbar { display: flex; justify-content: space-between; align-items: center; background: var(--primary); padding: 20px 50px; color: white; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .logo { font-size: 1.2rem; font-weight: 700; letter-spacing: 1px; }
        .nav-links { display: flex; list-style: none; gap: 30px; }
        .nav-links a { text-decoration: none; color: white; font-size: 0.85rem; font-weight: 600; transition: 0.3s; cursor: pointer; }
        .nav-links a:hover, .nav-links a.active { color: #e0ffff; text-decoration: underline; text-underline-offset: 5px; }
        
        .auth-buttons { display: flex; gap: 15px; align-items: center; }
        .btn { padding: 8px 20px; border-radius: 4px; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: 0.3s; cursor: pointer; border: none; }
        .login { border: 1px solid white; color: white; background: transparent; }
        .login:hover { background: rgba(255, 255, 255, 0.1); }

        /* ==========================================
           VIEW TOGGLING ANIMATIONS
           ========================================== */
        .view-section { display: none; animation: fadeIn 0.3s ease-in-out; }
        .view-section.active-view { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* ==========================================
           HERO & ADVANCED SEARCH (Feature 2)
           ========================================== */
        .hero { display: flex; justify-content: space-between; align-items: center; padding: 60px 10% 40px; }
        .hero h1 { font-size: 55px; line-height: 1.1; font-weight: 700; margin-bottom: 15px;}
        .hero h1 span { color: var(--primary); }
        
        .advanced-search-box { display: flex; align-items: center; background: white; margin-top: 30px; border-radius: 50px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); padding: 8px 8px 8px 25px; position: relative; }
        .search-field { display: flex; align-items: center; flex: 1; }
        .search-field i { color: var(--primary); font-size: 1.1rem; margin-right: 12px; }
        .search-field input, .search-field select { width: 100%; border: none; outline: none; font-size: 0.95rem; color: var(--text-dark); background: transparent; padding: 12px 0; }
        .search-divider { width: 1px; height: 35px; background: var(--silver-accent); margin: 0 20px; }
        .advanced-search-box button { background: var(--primary); color: white; border: none; padding: 15px 35px; border-radius: 50px; font-weight: 600; cursor: pointer; transition: 0.3s; white-space: nowrap; }
        .advanced-search-box button:hover { background: var(--primary-dark); }
        
        /* ==========================================
           DATA TABLES (Features 4 & 5)
           ========================================== */
        .dashboard-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .data-table-wrapper { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 40px; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--silver-accent); font-size: 0.9rem; }
        .data-table th { color: var(--text-gray); font-weight: 600; }
        .data-table tr:hover { background: #fafafa; }
        
        /* Badges & Buttons */
        .badge { padding: 5px 10px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; }
        .badge-pending { color: var(--warning); background: rgba(245, 158, 11, 0.1); }
        .badge-rejected { color: var(--danger); background: rgba(239, 68, 68, 0.1); }
        .badge-approved { color: var(--success); background: rgba(16, 185, 129, 0.1); }
        .badge-active { color: var(--primary); background: var(--primary-light); }
        .badge-closed { color: var(--text-gray); background: var(--silver-accent); }

        .btn-outline { background: transparent; color: var(--primary); border: 1px solid var(--primary); padding: 6px 12px; border-radius: 4px; cursor: pointer; transition: 0.3s; font-weight: 500; font-size: 0.85rem; text-decoration: none; display: inline-block; }
        .btn-outline:hover { background: var(--primary); color: var(--white); }
        .btn-action { background: transparent; border: none; font-size: 1.2rem; cursor: pointer; transition: 0.2s; padding: 5px; margin: 0 5px; text-decoration: none; }
        .btn-accept { color: var(--success); }
        .btn-reject { color: var(--danger); }
        .btn-accept:hover, .btn-reject:hover { transform: scale(1.1); }

        /* ==========================================
           DASHBOARD SUMMARY CARDS (Feature 3)
           ========================================== */
        .dash-summary-cards { display: flex; gap: 30px; justify-content: center; margin-bottom: 40px; flex-wrap: wrap;}
        .dash-card { background: white; border-radius: 12px; padding: 30px; width: 300px; display: flex; align-items: center; gap: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .dash-card i { font-size: 2rem; color: var(--primary); background: var(--primary-light); padding: 15px; border-radius: 12px; }

        /* ==========================================
           PROFILE & FORM LAYOUT (Features 6 & 7)
           ========================================== */
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
        .btn-aqua { background-color: var(--primary); color: white; border: none; padding: 10px 24px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .btn-aqua:hover { background-color: var(--primary-dark); }

        .edit-form { display: flex; flex-direction: column; gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-weight: 600; color: var(--text-dark); font-size: 0.95rem; }
        .form-control { padding: 12px 15px; border: 1px solid var(--silver-accent); border-radius: 6px; font-size: 0.95rem; font-family: 'Poppins', sans-serif; color: var(--text-dark); outline: none; transition: all 0.3s ease; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .file-upload-wrapper { display: flex; align-items: center; gap: 15px; }
        .file-upload-preview { width: 60px; height: 60px; border-radius: 50%; background: var(--bg-light); object-fit: cover; border: 1px solid var(--silver-accent); }
        .section-divider { margin: 15px 0 5px; border-bottom: 2px solid var(--bg-light); padding-bottom: 10px; color: var(--primary); font-weight: 600; font-size: 1.1rem; }

        /* ==========================================
           MOBILE RESPONSIVENESS
           ========================================== */
        .menu-toggle { display: none; font-size: 26px; cursor: pointer; }
        @media (max-width: 900px) {
            .hero { flex-direction: column; text-align: center; padding: 50px 20px; }
            .advanced-search-box { flex-direction: column; border-radius: 12px; padding: 15px; }
            .search-divider { width: 100%; height: 1px; margin: 10px 0; }
            .advanced-search-box button { width: 100%; margin-top: 15px; border-radius: 8px; }
            .profile-container { flex-direction: column; }
            .profile-sidebar { width: 100%; }
            .p-header-card { flex-direction: column; gap: 20px; align-items: center; text-align: center; }
            .p-info-basic { flex-direction: column; }
        }
        @media (max-width: 768px) {
            .nav-links { display: none; flex-direction: column; background: var(--primary-dark); position: absolute; top: 70px; left: 0; width: 100%; padding: 20px 0; align-items: center; }
            .nav-links.active { display: flex; }
            .menu-toggle { display: block; }
            .auth-buttons { display: none; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="logo">JOB-PORTAL</div>
    <ul class="nav-links">
        <li><a onclick="switchView('home-view')" id="link-home" class="active">HOME</a></li>
        <li><a onclick="switchView('overview-view')" id="link-overview">OVERVIEW</a></li>
        <li><a onclick="switchView('manage-jobs-view')" id="link-manage">MANAGE JOBS</a></li>
        <li><a onclick="switchView('applicants-view')" id="link-applicants">APPLICANTS</a></li>
        <li><a onclick="switchView('employer-profile-view')" id="link-profile">COMPANY PROFILE</a></li>
    </ul>
    <div class="auth-buttons">
        <span style="font-weight: 600; color: white; font-size: 0.9rem;"><i class="fa-solid fa-building"></i> <?php echo htmlspecialchars($user_name); ?></span>
        <a href="../../auth/logout.php" class="btn login">LOGOUT</a>
    </div>
    <div class="menu-toggle" onclick="toggleMenu()">☰</div>
</nav>

<div id="home-view" class="view-section active-view">
    <section class="hero">
        <div class="hero-content" style="max-width: 800px; margin: 0 auto; text-align: center;">
            <h1>Find the <span>Right Talent.</span></h1>
            <p style="color: var(--text-gray); margin: 15px 0;">Search the resume database to find candidates that match your requirements perfectly.</p>
            
            <form action="search_candidates.php" method="GET" class="advanced-search-box">
                <div class="search-field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="keywords" placeholder="Skills, keywords...">
                </div>
                <div class="search-divider"></div>
                <div class="search-field">
                    <i class="fa-solid fa-location-dot"></i>
                    <input type="text" name="location" placeholder="Location">
                </div>
                <div class="search-divider"></div>
                <div class="search-field">
                    <i class="fa-solid fa-layer-group"></i>
                    <select name="category">
                        <option value="" disabled selected>Category</option>
                        <option value="IT & Software">💻 IT & Software</option>
                        <option value="Sales & Marketing">📊 Sales & Marketing</option>
                        <option value="Finance & Accounting">🏦 Finance & Accounting</option>
                        <option value="Healthcare & Medical">🏥 Healthcare & Medical</option>
                        <option value="Education & Training">🎓 Education & Training</option>
                        <option value="Engineering & Construction">🏗️ Engineering & Construction</option>
                        <option value="Customer Service & BPO">📞 Customer Service & BPO</option>
                        <option value="Retail & E-commerce">🛒 Retail & E-commerce</option>
                        <option value="Logistics & Supply Chain">🚚 Logistics & Supply Chain</option>
                        <option value="HR & Administration">👩‍💼 HR & Administration</option>
                    </select>
                </div>
                <div class="search-divider"></div>
                <div class="search-field">
                    <i class="fa-solid fa-briefcase"></i>
                    <select name="experience">
                        <option value="" disabled selected>Experience</option>
                        <option value="entry">Entry Level</option>
                        <option value="mid">Mid Level</option>
                        <option value="senior">Senior Level</option>
                    </select>
                </div>
                <button type="submit">Find Candidates</button>
            </form>
        </div>
    </section>
</div>

<div id="overview-view" class="view-section">
    <div class="dashboard-container">
        <h2 style="color: var(--text-dark); margin-bottom: 25px; font-size: 1.8rem;">Employer Overview</h2>
        
        <div class="dash-summary-cards">
            <div class="dash-card">
                <i class="fa-solid fa-bullhorn"></i>
                <div>
                    <h3 style="color: var(--text-dark); font-size: 1.2rem;">Active Jobs</h3>
                    <p style="font-size: 1.5rem; font-weight: 700;"><?php echo $active_jobs_count; ?></p>
                </div>
            </div>
            <div class="dash-card">
                <i class="fa-solid fa-users"></i>
                <div>
                    <h3 style="color: var(--text-dark); font-size: 1.2rem;">Total Applicants</h3>
                    <p style="font-size: 1.5rem; font-weight: 700;"><?php echo $total_applicants; ?></p>
                </div>
            </div>
            <div class="dash-card">
                <i class="fa-solid fa-eye"></i>
                <div>
                    <h3 style="color: var(--text-dark); font-size: 1.2rem;">Profile Views</h3>
                    <p style="font-size: 1.5rem; font-weight: 700;"><?php echo $profile_views; ?></p>
                </div>
            </div>
        </div>

        <div class="p-card">
            <h3 class="p-card-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Activity Feed</h3>
            <ul style="list-style: none; line-height: 2.2; color: var(--text-dark);">
                <?php 
                // Loop through the 5 most recent applicants to populate the activity feed
                if(!empty($applicants)): 
                    foreach(array_slice($applicants, 0, 5) as $app): 
                        $status = strtoupper($app['status'] ?? 'PENDING');
                ?>
                    <li style="border-bottom: 1px solid var(--bg-light); padding: 10px 0;">
                        <?php if($status === 'APPROVED'): ?>
                            <i class="fa-solid fa-check" style="color:var(--success); margin-right:10px;"></i> You approved <strong><?php echo htmlspecialchars($app['candidate_name']); ?>'s</strong> application.
                        <?php elseif($status === 'REJECTED'): ?>
                            <i class="fa-solid fa-xmark" style="color:var(--danger); margin-right:10px;"></i> You rejected <strong><?php echo htmlspecialchars($app['candidate_name']); ?>'s</strong> application.
                        <?php else: ?>
                            <i class="fa-solid fa-user-plus" style="color:var(--primary); margin-right:10px;"></i> <strong><?php echo htmlspecialchars($app['candidate_name']); ?></strong> applied for <strong><?php echo htmlspecialchars($app['job_title']); ?></strong>.
                        <?php endif; ?>
                    </li>
                <?php 
                    endforeach; 
                else: 
                ?>
                    <li style="padding: 10px 0; color: var(--text-gray);">No recent activity to display.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<div id="manage-jobs-view" class="view-section">
    <div class="dashboard-container">
        <div class="data-table-wrapper">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 1.5rem;">Manage Posted Jobs</h2>
                <button class="btn-aqua" onclick="switchView('post-job-view')"><i class="fa-solid fa-plus"></i> Post New Job</button>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Job Title</th>
                        <th>Category</th>
                        <th>Posted Date</th>
                        <th>Applicants</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Loop through the fetched jobs and display them in the table
                    if(!empty($jobs)): foreach($jobs as $job): 
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($job['title']); ?></strong></td>
                        <td><?php echo htmlspecialchars($job['category'] ?? 'N/A'); ?></td>
                        <td><?php echo isset($job['created_at']) ? date('d/m/Y', strtotime($job['created_at'])) : 'N/A'; ?></td>
                        
                        <td><span style="background:var(--bg-light); padding:4px 10px; border-radius:20px; font-weight:600;">
                            <?php echo htmlspecialchars($job['applicant_count']); ?>
                        </span></td>
                        
                        <td>
                            <?php if(isset($job['status']) && strtoupper($job['status']) == 'ACTIVE'): ?>
                                <span class="badge badge-active">ACTIVE</span>
                            <?php else: ?>
                                <span class="badge badge-closed">CLOSED</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-outline" onclick="switchView('applicants-view')">View Applicants</button>
                            <a href="action/update_job_status.php?id=<?php echo $job['id']; ?>&action=close" class="btn-action btn-reject" title="Close Job" onclick="return confirm('Close this job posting?')"><i class="fa-solid fa-xmark"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" style="text-align:center; padding:20px; color:var(--text-gray);">You haven't posted any jobs yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="applicants-view" class="view-section">
    <div class="dashboard-container">
        <div class="data-table-wrapper">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 1.5rem;">Review Applicants</h2>
                <select id="applicant-job-filter" class="form-control" style="width: auto;" onchange="filterApplicants()">
                    <option value="all">Filter by Job: All Jobs</option>
                    <?php foreach($jobs as $job): ?>
                        <option value="<?php echo htmlspecialchars($job['title']); ?>">Filter by Job: <?php echo htmlspecialchars($job['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Candidate Name</th>
                        <th>Applied For</th>
                        <th>Match Score</th>
                        <th>Applied On</th>
                        <th>Resume</th>
                        <th>Status</th>
                        <th>Decision</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Loop through the fetched applicants to display review rows
                    if(!empty($applicants)): foreach($applicants as $app): 
                    ?>
                    <tr class="applicant-row" data-job="<?php echo htmlspecialchars($app['job_title']); ?>">
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($app['candidate_name']); ?>&background=random" style="width:35px; border-radius:50%;">
                                <strong><?php echo htmlspecialchars($app['candidate_name']); ?></strong>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                        
                        <td>
                            <?php 
                                $score = $app['match_score'] ?? rand(50, 98); // Fallback if score isn't calculated yet
                                $color = $score >= 80 ? 'var(--success)' : ($score >= 60 ? 'var(--warning)' : 'var(--danger)');
                            ?>
                            <span style="color:<?php echo $color; ?>; font-weight:700;"><?php echo $score; ?>%</span>
                        </td>
                        
                        <td><?php echo isset($app['applied_on']) ? date('d/m/Y', strtotime($app['applied_on'])) : 'N/A'; ?></td>
                        
                        <td>
                            <?php if(!empty($app['resume_path'])): ?>
                                <a href="../../<?php echo htmlspecialchars($app['resume_path']); ?>" target="_blank" class="btn-outline" title="Download Document"><i class="fa-solid fa-download"></i> Resume</a>
                            <?php else: ?>
                                <span style="color:var(--text-gray); font-size:0.8rem;">Profile Resume</span>
                            <?php endif; ?>
                        </td>
                        
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
                        <td>
     <a href="/smart_Job_listing_portal/profile/employer/view_seeker_profile.php?user_id=<?php echo $app['user_id']; ?>" class="btn-action" style="color: var(--primary);" title="View Full Profile">
    <i class="fa-solid fa-eye"></i>
</a>

                     <a href="action/update_application.php?id=<?php echo $app['id']; ?>&status=approved" class="btn-action btn-accept" title="Accept Candidate"><i class="fa-solid fa-check-circle"></i></a>
                            <a href="action/update_application.php?id=<?php echo $app['id']; ?>&status=rejected" class="btn-action btn-reject" title="Reject Candidate"><i class="fa-solid fa-times-circle"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                         <tr><td colspan="7" style="text-align:center; padding:20px; color:var(--text-gray);">No applications received yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="employer-profile-view" class="view-section">
    <div class="profile-container">
        <aside class="profile-sidebar">
            <div class="sidebar-menu">
                <a onclick="switchView('employer-profile-view')" class="sidebar-link active">
                    <i class="fa-regular fa-building"></i> Company Profile
                </a>
                <a onclick="switchView('overview-view')" class="sidebar-link">
                    <i class="fa-solid fa-chart-line"></i> Dashboard
                </a>
                <a onclick="switchView('post-job-view')" class="sidebar-link">
                    <i class="fa-solid fa-plus"></i> Post a Job
                </a>
                <a onclick="switchView('applicants-view')" class="sidebar-link">
                    <i class="fa-solid fa-users"></i> Manage Applicants
                </a>
            </div>
        </aside>

        <main class="profile-content">
            <div class="p-card p-header-card">
                <div class="p-info-basic">
                    <?php if (!empty($p['logo_path'])): ?>
                        <img src="../../<?php echo htmlspecialchars($p['logo_path']); ?>" alt="Company Logo" class="p-avatar" style="object-fit: cover;">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($p['company_name'] ?? 'Company'); ?>&background=f0f0f0&color=00b4d8&size=100" alt="Company Logo" class="p-avatar">
                    <?php endif; ?>
                    <div>
                        <h2><?php echo htmlspecialchars($p['company_name'] ?? 'Company Name Not Set'); ?></h2>
                        <p><?php echo htmlspecialchars($p['industry'] ?? 'Industry'); ?> • <?php echo htmlspecialchars($p['location'] ?? 'Location'); ?></p>
                        <a href="<?php echo htmlspecialchars($p['website'] ?? '#'); ?>" target="_blank" style="color: var(--primary); font-size: 0.85rem; text-decoration: none;"><?php echo htmlspecialchars($p['website'] ?? 'Add Website URL'); ?></a>
                    </div>
                </div>
                <button class="btn-aqua" onclick="switchView('employer-edit-view')">Edit Company</button>
            </div>

            <div class="p-card">
                <h3 class="p-card-title">About Company</h3>
                <p class="p-card-text"><?php echo nl2br(htmlspecialchars($p['about_company'] ?? 'Please update your profile to add a company description.')); ?></p>
            </div>

            <div class="p-card">
                <h3 class="p-card-title">Company Details</h3>
                <p class="p-card-text"><strong>Email:</strong> <?php echo htmlspecialchars($p['email'] ?? 'Not provided'); ?></p>
                <p class="p-card-text"><strong>Phone:</strong> <?php echo htmlspecialchars($p['phone'] ?? 'Not provided'); ?></p>
                <p class="p-card-text"><strong>Company Size:</strong> <?php echo htmlspecialchars($p['company_size'] ?? 'Not provided'); ?></p>
                <p class="p-card-text"><strong>Founded:</strong> <?php echo htmlspecialchars($p['founded_year'] ?? 'Not provided'); ?></p>
            </div>

            <div class="p-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 class="p-card-title" style="margin-bottom: 0;">Active Jobs Widget</h3>
                    <button class="btn-aqua" onclick="switchView('post-job-view')" style="padding: 6px 15px; font-size: 0.8rem;"><i class="fa-solid fa-plus"></i> Post Job</button>
                </div>
                
                <?php if(!empty($jobs)): foreach($jobs as $job): if(strtoupper($job['status'] ?? '') == 'ACTIVE'): ?>
                <div style="border: 1px solid var(--bg-light); padding: 15px; border-radius: 8px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h4 style="color: var(--text-dark);"><?php echo htmlspecialchars($job['title']); ?></h4>
                        <p style="font-size: 0.85rem; color: var(--text-gray);"><?php echo htmlspecialchars($job['salary']); ?> • <?php echo htmlspecialchars($job['job_type'] ?? 'Full-Time'); ?></p>
                    </div>
                    <button class="btn-aqua" style="background: var(--bg-light); color: var(--primary);" onclick="switchView('applicants-view')">View Applicants</button>
                </div>
                <?php endif; endforeach; else: ?>
                    <p style="font-size: 0.9rem; color: var(--text-gray);">No active jobs currently posted.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<div id="employer-edit-view" class="view-section">
    <div class="profile-container">
        <aside class="profile-sidebar">
            <div class="sidebar-menu">
                <a onclick="switchView('employer-profile-view')" class="sidebar-link active">
                    <i class="fa-regular fa-building"></i> Company Profile
                </a>
                <a onclick="switchView('post-job-view')" class="sidebar-link">
                    <i class="fa-solid fa-plus"></i> Post a Job
                </a>
            </div>
        </aside>

        <main class="profile-content">
            <div class="p-card">
                <h2 class="p-card-title" style="font-size: 1.5rem; margin-bottom: 20px;">Edit Company Profile</h2>
                
                <form class="edit-form" method="POST" action="action/update_profile.php" enctype="multipart/form-data">
                    <h3 class="section-divider">Company Info</h3>
                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($p['company_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Industry</label>
                        <input type="text" name="industry" class="form-control" value="<?php echo htmlspecialchars($p['industry'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Website</label>
                        <input type="url" name="website" class="form-control" value="<?php echo htmlspecialchars($p['website'] ?? ''); ?>">
                    </div>

                    <h3 class="section-divider">Contact Info</h3>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($p['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($p['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($p['location'] ?? ''); ?>">
                    </div>

                    <h3 class="section-divider">Details</h3>
                    <div class="form-group">
                        <label>Company Size</label>
                        <input type="text" name="company_size" class="form-control" value="<?php echo htmlspecialchars($p['company_size'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Founded Year</label>
                        <input type="number" name="founded_year" class="form-control" value="<?php echo htmlspecialchars($p['founded_year'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>About Company</label>
                        <textarea name="about_company" class="form-control" rows="4"><?php echo htmlspecialchars($p['about_company'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Company Logo</label>
                        <div class="file-upload-wrapper">
                            <?php if (!empty($p['logo_path'])): ?>
                                <img src="../../<?php echo htmlspecialchars($p['logo_path']); ?>" alt="Preview" class="file-upload-preview" style="object-fit: cover;">
                            <?php else: ?>
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($p['company_name'] ?? 'C'); ?>&background=f0f0f0&color=00b4d8" alt="Preview" class="file-upload-preview">
                            <?php endif; ?>
                            <input type="file" name="logo" accept="image/*" class="form-control" style="border: none; padding: 0; background: transparent;">
                        </div>
                    </div>

                    <div style="margin-top: 15px; display: flex; gap: 15px;">
                        <button type="submit" class="btn-aqua" style="flex: 1;">Save Changes</button>
                        <button type="button" class="btn-aqua" style="background: var(--bg-light); color: var(--text-dark);" onclick="switchView('employer-profile-view')">Cancel</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</div>

<div id="post-job-view" class="view-section">
    <div class="profile-container">
        <aside class="profile-sidebar">
            <div class="sidebar-menu">
                <a onclick="switchView('employer-profile-view')" class="sidebar-link">
                    <i class="fa-regular fa-building"></i> Company Profile
                </a>
                <a onclick="switchView('post-job-view')" class="sidebar-link active">
                    <i class="fa-solid fa-plus"></i> Post a Job
                </a>
            </div>
        </aside>

        <main class="profile-content">
            <div class="p-card">
                <h2 class="p-card-title" style="font-size: 1.5rem; margin-bottom: 20px;"><i class="fa-solid fa-bullhorn"></i> Create a New Job Listing</h2>
                
                <form class="edit-form" method="POST" action="action/save_job.php" enctype="multipart/form-data">
                    
                    <div class="form-group">
                        <label>Job Title</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Senior Graphic Designer" required>
                    </div>

                    <div style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label>Category</label>
                            <select name="category" class="form-control" required>
                                <option value="" disabled selected>Select Category</option>
                                <option value="IT & Software">💻 Information Technology (IT) & Software</option>
                                <option value="Sales & Marketing">📊 Sales & Marketing</option>
                                <option value="Finance & Accounting">🏦 Banking, Finance & Accounting</option>
                                <option value="Healthcare & Medical">🏥 Healthcare & Medical</option>
                                <option value="Education & Training">🎓 Education & Training</option>
                                <option value="Engineering & Construction">🏗️ Engineering & Construction</option>
                                <option value="Customer Service & BPO">📞 Customer Service & BPO</option>
                                <option value="Retail & E-commerce">🛒 Retail & E-commerce</option>
                                <option value="Logistics & Supply Chain">🚚 Logistics & Supply Chain</option>
                                <option value="HR & Administration">👩‍💼 Human Resources (HR) & Administration</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Job Type</label>
                            <select name="job_type" class="form-control" required>
                                <option value="" disabled selected>Select Type</option>
                                <option value="Full-Time">Full-Time</option>
                                <option value="Part-Time">Part-Time</option>
                                <option value="Contract">Contract</option>
                                <option value="Internship">Internship</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label>Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. Remote, Bengaluru" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Salary / Compensation</label>
                            <input type="text" name="salary" class="form-control" placeholder="e.g. ₹80,000/month" required>
                        </div>
                    </div>

                    <div style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label>Experience Required</label>
                            <input type="text" name="experience" class="form-control" placeholder="e.g. 1-3 years" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Total Vacancies</label>
                            <input type="number" name="vacancies" class="form-control" placeholder="e.g. 2" min="1" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Application Deadline</label>
                            <input type="date" name="deadline" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Job Description & Requirements</label>
                        <textarea name="description" class="form-control" rows="6" placeholder="Describe the responsibilities, required skills, and benefits..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Required Skills (Comma separated for Skill Match Analyzer)</label>
                        <input type="text" name="skills" class="form-control" placeholder="e.g. React, Node.js, MySQL" required>
                    </div>

                    <div class="form-group">
                        <label>Upload Reference Document / Resume</label>
                        <p style="font-size: 0.85rem; color: var(--text-gray); margin-bottom: 5px;">Attach a detailed job description PDF or reference resume.</p>
                        <input type="file" name="document" accept=".pdf,.doc,.docx" class="form-control" style="padding: 10px;">
                    </div>

                    <div style="margin-top: 15px; display: flex; gap: 15px;">
                        <button type="submit" class="btn-aqua" style="flex: 1; font-size: 1rem; padding: 15px;">Post Job Now</button>
                    </div>

                </form>
            </div>
        </main>
    </div>
</div>

<script>
    // Handles opening the mobile navigation menu
    function toggleMenu() {
        document.querySelector(".nav-links").classList.toggle("active");
    }

    // Handles switching between different sections (views) of the dashboard
    function switchView(viewId) {
        // 1. Hide all sections by removing the 'active-view' class
        const views = document.querySelectorAll('.view-section');
        views.forEach(view => {
            view.classList.remove('active-view');
        });

        // 2. Show the targeted section
        document.getElementById(viewId).classList.add('active-view');

        // 3. Manage Navbar Active States (highlighting the correct link)
        const navLinks = ['link-home', 'link-overview', 'link-manage', 'link-applicants', 'link-profile'];
        navLinks.forEach(id => {
            if(document.getElementById(id)) document.getElementById(id).classList.remove('active');
        });

        // Map the view ID to the corresponding nav link ID
        if(viewId === 'home-view') {
            document.getElementById('link-home').classList.add('active');
        } else if (viewId === 'overview-view') {
            document.getElementById('link-overview').classList.add('active');
        } else if (viewId === 'manage-jobs-view') {
            document.getElementById('link-manage').classList.add('active');
        } else if (viewId === 'applicants-view') {
            document.getElementById('link-applicants').classList.add('active');
        } else if (viewId === 'employer-profile-view' || viewId === 'employer-edit-view' || viewId === 'post-job-view') {
            document.getElementById('link-profile').classList.add('active');
        }

        // Close mobile menu if open, and scroll to top smoothly
        document.querySelector(".nav-links").classList.remove("active");
        window.scrollTo(0, 0);
    }

    // Function to filter applicants dynamically (Review Section Fix)
    function filterApplicants() {
        const selectedJob = document.getElementById("applicant-job-filter").value;
        const rows = document.querySelectorAll(".applicant-row");

        rows.forEach(row => {
            const rowJob = row.getAttribute("data-job");
            // Show row if "all" is selected OR if the row's job matches the dropdown
            if (selectedJob === "all" || rowJob === selectedJob) {
                row.style.display = ""; // Show
            } else {
                row.style.display = "none"; // Hide
            }
        });
    }
</script>

</body>
</html>