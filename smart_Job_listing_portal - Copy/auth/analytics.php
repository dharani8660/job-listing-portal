<?php
// Connect to your real database
$conn = mysqli_connect("localhost", "root", "", "smart_job_portal");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// ======================================
// 1. TOP STAT CARDS (Real Queries)
// ======================================
$totalJobs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM jobs WHERE status = 'ACTIVE'"))['total'] ?? 0;
$totalApplications = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM applications"))['total'] ?? 0;
$totalEmployers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='employer'"))['total'] ?? 0;
$totalSeekers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='jobseeker'"))['total'] ?? 0;

$totalShortlisted = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM applications WHERE status='APPROVED'"))['total'] ?? 0;
$totalRejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM applications WHERE status='REJECTED'"))['total'] ?? 0;

// ======================================
// 2. APPLICATION STATUS (Pie Chart)
// ======================================
$statusQuery = mysqli_query($conn, "SELECT IFNULL(status, 'PENDING') as stat, COUNT(*) as total FROM applications GROUP BY stat");
$statusLabels = [];
$statusData = [];
if ($statusQuery) {
    while($row = mysqli_fetch_assoc($statusQuery)) {
        $statusLabels[] = strtoupper($row['stat']);
        $statusData[] = (int)$row['total'];
    }
}

// ======================================
// 3. JOBS POSTED PER MONTH (Bar Chart)
// ======================================
$jobsMonthQuery = mysqli_query($conn, "SELECT MONTH(created_at) AS month, COUNT(*) AS total FROM jobs GROUP BY MONTH(created_at) ORDER BY MONTH(created_at)");
$jobMonths = [];
$jobMonthlyTotals = [];
if ($jobsMonthQuery) {
    while($row = mysqli_fetch_assoc($jobsMonthQuery)) {
        $jobMonths[] = date("M", mktime(0,0,0,$row['month'],10));
        $jobMonthlyTotals[] = (int)$row['total'];
    }
}

// ======================================
// 4. USER GROWTH (Line Chart - Employers vs Seekers)
// ======================================
$userGrowthQuery = mysqli_query($conn, "SELECT MONTH(created_at) as month, role, COUNT(*) as total FROM users GROUP BY MONTH(created_at), role ORDER BY MONTH(created_at)");
$growthMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$employerGrowth = array_fill(0, 12, 0);
$seekerGrowth = array_fill(0, 12, 0);

if ($userGrowthQuery) {
    while($row = mysqli_fetch_assoc($userGrowthQuery)) {
        $mIndex = (int)$row['month'] - 1;
        if ($mIndex >= 0 && $mIndex < 12) {
            if ($row['role'] == 'employer') $employerGrowth[$mIndex] = (int)$row['total'];
            if ($row['role'] == 'jobseeker') $seekerGrowth[$mIndex] = (int)$row['total'];
        }
    }
}

// ======================================
// 5. APPLICATION HEATMAP (By Day of Week)
// ======================================
$heatmapQuery = mysqli_query($conn, "SELECT DAYNAME(created_at) as day, COUNT(*) as total FROM applications GROUP BY DAYNAME(created_at)");
$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$heatmapData = [0, 0, 0, 0, 0, 0, 0];

if ($heatmapQuery) {
    while($row = mysqli_fetch_assoc($heatmapQuery)) {
        $dayIndex = array_search($row['day'], $daysOfWeek);
        if ($dayIndex !== false) {
            $heatmapData[$dayIndex] = (int)$row['total'];
        }
    }
}

$formattedHeatmap = [];
foreach($daysOfWeek as $index => $day) {
    $formattedHeatmap[] = [
        "x" => substr($day, 0, 3), 
        "y" => $heatmapData[$index]
    ];
}

// ======================================
// 6. MONTHLY APPLICATIONS TREND (Area Chart)
// ======================================
$monthlyQuery = mysqli_query($conn, "SELECT MONTH(created_at) AS month, COUNT(*) AS total FROM applications GROUP BY MONTH(created_at) ORDER BY MONTH(created_at)");
$months = [];
$monthlyTotals = [];
if ($monthlyQuery) {
    while($row = mysqli_fetch_assoc($monthlyQuery)) {
        $months[] = date("M", mktime(0,0,0,$row['month'],10));
        $monthlyTotals[] = (int)$row['total'];
    }
}

// ======================================
// 7. EMPLOYER-WISE JOBS (Bar Chart)
// ======================================
$employerQuery = mysqli_query($conn, "
    SELECT u.name AS company, COUNT(j.id) as total 
    FROM jobs j 
    JOIN users u ON j.employer_id = u.id 
    GROUP BY j.employer_id 
    ORDER BY total DESC LIMIT 5
");
$employerNames = [];
$employerJobs = [];
if ($employerQuery) {
    while($row = mysqli_fetch_assoc($employerQuery)) {
        $employerNames[] = $row['company'];
        $employerJobs[] = (int)$row['total'];
    }
}

// ======================================
// 8. HIGH DEMAND JOBS (Horizontal Bar)
// ======================================
$demandQuery = mysqli_query($conn, "
    SELECT j.title, COUNT(a.id) as total_apps 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    GROUP BY a.job_id 
    ORDER BY total_apps DESC LIMIT 5
");
$demandJobs = [];
$demandCounts = [];
if ($demandQuery) {
    while($row = mysqli_fetch_assoc($demandQuery)) {
        $demandJobs[] = $row['title'];
        $demandCounts[] = (int)$row['total_apps'];
    }
}

// ======================================
// 9. RECENT APPLICATIONS TABLE (Using user_id)
// ======================================
$recentAppsQuery = mysqli_query($conn, "
    SELECT u.name AS candidate, j.title AS job, a.status, a.created_at AS date 
    FROM applications a
    JOIN users u ON a.user_id = u.id
    JOIN jobs j ON a.job_id = j.id
    ORDER BY a.created_at DESC LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="10">
<title>Smart Job Portal - Advanced Analytics</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
    :root {
        --bg-color: #f0f4f8; 
        --card-bg: rgba(255, 255, 255, 0.75);
        --text-main: #1e293b;
        --text-muted: #64748b;
        --border-color: rgba(255, 255, 255, 0.5);
        --sidebar-bg: linear-gradient(180deg, #0f172a, #1e293b);
        --sidebar-text: #fff;
        --shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.05);
        
        --primary: #0d6efd;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --purple: #8b5cf6;
    }

    body.dark-theme {
        --bg-color: #0f172a;
        --card-bg: rgba(30, 41, 59, 0.75);
        --text-main: #f8fafc;
        --text-muted: #94a3b8;
        --border-color: rgba(255, 255, 255, 0.08);
        --shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
    }

    * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins', sans-serif; transition: background-color 0.2s, color 0.2s; }
    body { background: var(--bg-color); color: var(--text-main); }

    /* SIDEBAR */
    .sidebar {
        position: fixed; width: 260px; height: 100vh;
        background: var(--sidebar-bg); color: var(--sidebar-text);
        padding: 30px 20px; overflow: auto; z-index: 100;
    }
    .logo { font-size: 22px; font-weight: 800; margin-bottom: 40px; color: white; display: flex; align-items: center; gap: 10px; }
    .sidebar-menu a {
        display: flex; align-items: center; gap: 15px; text-decoration: none;
        color: #94a3b8; padding: 12px 15px; margin-bottom: 10px;
        border-radius: 12px; transition: 0.3s; font-weight: 500;
    }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); color: white; }

    /* MAIN PANEL */
    .main-content { margin-left: 260px; padding: 30px; }
    
    /* TOP HEADER & LIVE LOGGING INDICATORS */
    .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .topbar h2 { font-weight: 700; margin: 0; }
    .live-indicator { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: var(--success); }
    .pulsing-dot { width: 9px; height: 9px; background: var(--success); border-radius: 50%; animation: pulse 1.6s infinite; }
    
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    .theme-toggle {
        background: var(--card-bg); border: 1px solid var(--border-color);
        color: var(--text-main); padding: 8px 16px; border-radius: 50px;
        cursor: pointer; display: flex; align-items: center; gap: 8px;
        backdrop-filter: blur(10px); box-shadow: var(--shadow); font-weight: 500;
    }

    /* PREMIUM GLASSMORPHISM SYSTEM */
    .glass-card {
        background: var(--card-bg);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 24px;
        box-shadow: var(--shadow);
        height: 100%;
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .glass-card:hover { transform: translateY(-4px); }

    /* KPI METRICS LAYOUT */
    .card-top { display: flex; justify-content: space-between; align-items: flex-start; }
    .card-title { color: var(--text-muted); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
    .card-number { font-size: 30px; font-weight: 800; color: var(--text-main); }
    .card-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; justify-content: center; align-items: center; font-size: 18px; color: white; }
    
    .kpi-trend { font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 50px; display: inline-flex; align-items: center; gap: 4px; margin-top: 12px; }
    .trend-up { background: rgba(16, 185, 129, 0.12); color: var(--success); }
    .trend-down { background: rgba(239, 68, 68, 0.12); color: var(--danger); }
    
    .box-title { font-size: 15px; font-weight: 700; margin-bottom: 20px; color: var(--text-main); display: flex; align-items: center; gap: 10px; text-transform: uppercase; letter-spacing: 0.5px; }

    /* RECENT TABLES STYLE REGULATION */
    .table th { color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 12px; border-bottom: 2px solid var(--border-color); }
    .table td { vertical-align: middle; font-size: 13.5px; font-weight: 500; color: var(--text-main); border-bottom: 1px solid var(--border-color); }
    .badge-status { padding: 5px 12px; border-radius: 50px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .badge-selected { background: rgba(16, 185, 129, 0.15); color: #10b981; }
    .badge-pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
    .badge-rejected { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

    @media(max-width:992px){
        .sidebar { width: 100%; height: auto; position: relative; padding: 15px; }
        .main-content { margin-left: 0; padding: 15px; }
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="logo"><i class="fa-solid fa-chart-line text-primary"></i> PortalAdmin</div>
    <div class="sidebar-menu">
        <a href="#" class="active"><i class="fa-solid fa-chart-pie"></i> Dashboard Analytics</a>
        <a href="#"><i class="fa-solid fa-briefcase"></i> Active Listings</a>
        <a href="#"><i class="fa-solid fa-users"></i> Platform Users</a>
    </div>
</div>

<div class="main-content">
    
    <div class="topbar">
        <div>
            <h2>Dashboard Overview</h2>
            <div class="live-indicator mt-1">
                <div class="pulsing-dot"></div> Live Data Engine (10s refresh loop)
            </div>
        </div>
        <div class="header-actions">
            <button class="theme-toggle" onclick="toggleTheme()">
                <i class="fa-solid fa-moon" id="theme-icon"></i> <span id="theme-text">Dark Mode</span>
            </button>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="glass-card">
                <div class="card-top">
                    <div>
                        <div class="card-title">Active Job Posts</div>
                        <div class="card-number"><?php echo $totalJobs; ?></div>
                    </div>
                    <div class="card-icon" style="background: var(--primary);"><i class="fa-solid fa-briefcase"></i></div>
                </div>
                <div class="kpi-trend trend-up"><i class="fa-solid fa-arrow-up"></i> +10% system index</div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="glass-card">
                <div class="card-top">
                    <div>
                        <div class="card-title">Total Applications</div>
                        <div class="card-number"><?php echo $totalApplications; ?></div>
                    </div>
                    <div class="card-icon" style="background: var(--purple);"><i class="fa-solid fa-file-lines"></i></div>
                </div>
                <div class="kpi-trend trend-up"><i class="fa-solid fa-arrow-up"></i> +25% submission rate</div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="glass-card">
                <div class="card-top">
                    <div>
                        <div class="card-title">Approved Profiles</div>
                        <div class="card-number"><?php echo $totalShortlisted; ?></div>
                    </div>
                    <div class="card-icon" style="background: var(--success);"><i class="fa-solid fa-check-double"></i></div>
                </div>
                <div class="kpi-trend trend-up"><i class="fa-solid fa-arrow-trend-up"></i> ↑ Improving velocity</div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="glass-card">
                <div class="card-top">
                    <div>
                        <div class="card-title">Rejected Folders</div>
                        <div class="card-number"><?php echo $totalRejected; ?></div>
                    </div>
                    <div class="card-icon" style="background: var(--danger);"><i class="fa-solid fa-xmark"></i></div>
                </div>
                <div class="kpi-trend trend-down"><i class="fa-solid fa-arrow-down"></i> ↓ Lower target loss</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="glass-card">
                <div class="box-title"><i class="fa-solid fa-pie-chart text-primary"></i> Application Status</div>
                <div id="statusPieChart"></div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="glass-card">
                <div class="box-title"><i class="fa-solid fa-bar-chart text-purple"></i> Jobs Posted Per Month</div>
                <div id="jobsMonthChart"></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="glass-card">
                <div class="box-title"><i class="fa-solid fa-chart-area text-blue"></i> Monthly Applications Trend</div>
                <div id="lineChartTrend"></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="glass-card">
                <div class="box-title"><i class="fa-solid fa-building-user text-success"></i> Top Employer Activity</div>
                <div id="barChartEmployers"></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="glass-card">
                <div class="box-title"><i class="fa-solid fa-user-plus text-purple"></i> User Growth Metrics</div>
                <div id="userGrowthChart"></div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="glass-card">
                <div class="box-title"><i class="fa-solid fa-fire text-warning"></i> Weekly Submission Heatmap</div>
                <div id="heatmapChart"></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="glass-card">
                <div class="box-title"><i class="fa-solid fa-clock-history text-primary"></i> Recent Incoming Applications</div>
                <div class="table-responsive">
                    <table class="table table-borderless table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Candidate Name</th>
                                <th>Position</th>
                                <th>Timestamp</th>
                                <th>Status Badge</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($recentAppsQuery && mysqli_num_rows($recentAppsQuery) > 0) {
                                while($app = mysqli_fetch_assoc($recentAppsQuery)) { 
                                    $badgeClass = 'badge-pending'; 
                                    if(strtolower($app['status']) == 'approved') $badgeClass = 'badge-selected';
                                    if(strtolower($app['status']) == 'rejected') $badgeClass = 'badge-rejected';
                            ?>
                            <tr>
                                <td><i class="fa-solid fa-circle-user text-muted me-2"></i><strong><?php echo htmlspecialchars($app['candidate']); ?></strong></td>
                                <td><?php echo htmlspecialchars($app['job']); ?></td>
                                <td class="text-muted"><?php echo date("M d, Y H:i", strtotime($app['date'])); ?></td>
                                <td>
                                    <span class="badge-status <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars($app['status'] ? $app['status'] : 'PENDING'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center text-muted py-4'>No submissions registered in the system index yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass-card">
                <div class="box-title"><i class="fa-solid fa-arrow-trend-up text-warning"></i> Highest Demand Sectors</div>
                <div id="horizontalBarDemand"></div>
            </div>
        </div>
    </div>

</div>

<script>
    // --- Responsive UI Theme Core Toggle ---
    const body = document.body;
    const themeIcon = document.getElementById('theme-icon');
    const themeText = document.getElementById('theme-text');
    
    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-theme');
        themeIcon.className = 'fa-solid fa-sun text-warning';
        themeText.innerText = 'Light Mode';
    }

    function toggleTheme() {
        body.classList.toggle('dark-theme');
        const isDark = body.classList.contains('dark-theme');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        themeIcon.className = isDark ? 'fa-solid fa-sun text-warning' : 'fa-solid fa-moon';
        themeText.innerText = isDark ? 'Light Mode' : 'Dark Mode';
        setTimeout(() => location.reload(), 250);
    }

    // --- ApexCharts Theme Mapping Profiles ---
    const isDarkMode = document.body.classList.contains('dark-theme');
    const chartFont = "'Poppins', sans-serif";
    const textColor = isDarkMode ? '#f8fafc' : '#1e293b';

    // 1. Application Status Distribution Graph
    new ApexCharts(document.querySelector("#statusPieChart"), {
        series: <?php echo json_encode(empty($statusData) ? [1] : $statusData); ?>,
        labels: <?php echo json_encode(empty($statusLabels) ? ['No Data'] : $statusLabels); ?>,
        chart: { type: 'pie', height: 280, fontFamily: chartFont, background: 'transparent', animations: { enabled: false } },
        colors: ['#f59e0b', '#10b981', '#ef4444', '#0d6efd', '#8b5cf6'],
        stroke: { show: false },
        legend: { position: 'bottom', labels: { colors: textColor } },
        dataLabels: { dropShadow: { enabled: false } }
    }).render();

    // 2. Jobs Posted Trend Metrics (Bar Matrix)
    new ApexCharts(document.querySelector("#jobsMonthChart"), {
        series: [{ name: 'Jobs Listed', data: <?php echo json_encode(empty($jobMonthlyTotals) ? [0] : $jobMonthlyTotals); ?> }],
        chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: chartFont, background: 'transparent', animations: { enabled: false } },
        colors: ['#8b5cf6'],
        plotOptions: { bar: { borderRadius: 6, columnWidth: '35%' } },
        xaxis: { categories: <?php echo json_encode(empty($jobMonths) ? ['N/A'] : $jobMonths); ?>, labels: { style: { colors: textColor } } },
        yaxis: { labels: { style: { colors: textColor } } },
        dataLabels: { enabled: false }
    }).render();

    // 3. System Applications Area Curve
    new ApexCharts(document.querySelector("#lineChartTrend"), {
        series: [{ name: "Applications Received", data: <?php echo json_encode(empty($monthlyTotals) ? [0] : $monthlyTotals); ?> }],
        chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: chartFont, background: 'transparent', animations: { enabled: false } },
        colors: ['#0d6efd'],
        fill: { type: "gradient", gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [60, 100] } },
        stroke: { curve: 'smooth', width: 3 },
        xaxis: { categories: <?php echo json_encode(empty($months) ? ['N/A'] : $months); ?>, labels: { style: { colors: textColor } } },
        yaxis: { labels: { style: { colors: textColor } } },
        dataLabels: { enabled: false }
    }).render();

    // 4. Employer Core Posting Performance
    new ApexCharts(document.querySelector("#barChartEmployers"), {
        series: [{ name: 'Total Job Openings', data: <?php echo json_encode(empty($employerJobs) ? [0] : $employerJobs); ?> }],
        chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: chartFont, background: 'transparent', animations: { enabled: false } },
        colors: ['#10b981'],
        plotOptions: { bar: { borderRadius: 5, columnWidth: '40%' } },
        xaxis: { categories: <?php echo json_encode(empty($employerNames) ? ['N/A'] : $employerNames); ?>, labels: { style: { colors: textColor } } },
        yaxis: { labels: { style: { colors: textColor } } },
        dataLabels: { enabled: false }
    }).render();

    // 5. Dual Line Growth Framework (Seekers vs Corporate)
    new ApexCharts(document.querySelector("#userGrowthChart"), {
        series: [
            { name: "Candidates Registered", data: <?php echo json_encode($seekerGrowth); ?> },
            { name: "Corporate Entities", data: <?php echo json_encode($employerGrowth); ?> }
        ],
        chart: { type: 'line', height: 280, toolbar: { show: false }, fontFamily: chartFont, background: 'transparent', animations: { enabled: false } },
        colors: ['#0d6efd', '#10b981'],
        stroke: { curve: 'smooth', width: 3 },
        xaxis: { categories: <?php echo json_encode($growthMonths); ?>, labels: { style: { colors: textColor } } },
        yaxis: { labels: { style: { colors: textColor } } },
        legend: { labels: { colors: textColor } },
        dataLabels: { enabled: false }
    }).render();

    // 6. Day-of-Week Application Volume Matrix (Heatmap)
    new ApexCharts(document.querySelector("#heatmapChart"), {
        series: [{ name: 'Submissions Rate', data: <?php echo json_encode($formattedHeatmap); ?> }],
        chart: { type: 'heatmap', height: 280, toolbar: { show: false }, fontFamily: chartFont, background: 'transparent', animations: { enabled: false } },
        plotOptions: {
            heatmap: {
                shadeIntensity: 0.5,
                radius: 6,
                useFillColorAsStroke: false,
                colorScale: { ranges: [{ from: 0, to: 1000, color: '#f59e0b', name: 'Traffic Scale' }] }
            }
        },
        dataLabels: { enabled: true, style: { colors: ['#fff'] } },
        xaxis: { labels: { style: { colors: textColor } } },
        yaxis: { labels: { style: { colors: textColor } } }
    }).render();

    // 7. Market Sectors Velocity Chart (Horizontal Bar)
    new ApexCharts(document.querySelector("#horizontalBarDemand"), {
        series: [{ name: 'Applicants Count', data: <?php echo json_encode(empty($demandCounts) ? [0] : $demandCounts); ?> }],
        chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: chartFont, background: 'transparent', animations: { enabled: false } },
        colors: ['#f59e0b'],
        plotOptions: { bar: { borderRadius: 4, horizontal: true } },
        dataLabels: { enabled: true, style: { colors: ['#fff'] } },
        xaxis: { categories: <?php echo json_encode(empty($demandJobs) ? ['N/A'] : $demandJobs); ?>, labels: { style: { colors: textColor } } },
        yaxis: { labels: { style: { colors: textColor } } }
    }).render();
</script>
</body>
</html>