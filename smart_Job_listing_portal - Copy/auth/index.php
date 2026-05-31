<?php
session_start();
include("../config/db.php");

// ==========================================
// 1. HANDLE REGISTRATION LOGIC
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    
    // Secure the inputs
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $conn->real_escape_string($_POST['role']);

    // Check if email exists safely
    $check = $conn->query("SELECT * FROM users WHERE email='$email'");

    if ($check && $check->num_rows > 0) {
        echo "<script>alert('Email already exists! Please login.');</script>";
    } elseif (!$check) {
        echo "<script>alert('Database Error. Please try again.');</script>";
    } else {
        $sql = "INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$password', '$role')";
        if ($conn->query($sql) === TRUE) {
            echo "<script>alert('Registration Successful! Please login below.');</script>";
        } else {
            echo "<script>alert('Error: " . $conn->error . "');</script>";
        }
    }
}

// ==========================================
// 2. HANDLE LOGIN LOGIC
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];  
            $_SESSION['email'] = $user['email'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            // Route based on role
            if ($user['role'] == "jobseeker") {
                header("Location: ../profile/jobseeker/jobseeker.php");
            } else {
                header("Location: ../profile/employer/employer.php");
            }
            exit();
        } else {
            echo "<script>alert('Wrong Password! Please try again.');</script>";
        }
    } else {
        echo "<script>alert('User not found! Please register first.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Smart Job Listing Portal - Discover Opportunities</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    :root {
        --primary: #00b4d8;
        --primary-dark: #0096c7;
        --bg-white: #ffffff;
        --bg-light: #f8f9fa;
        --text-dark: #1a1a1a;
        --text-gray: #6c757d;
        --border-color: #e9ecef;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; scroll-behavior: smooth; }
    body { background-color: var(--bg-white); color: var(--text-dark); overflow-x: hidden; }

    .navbar { display: flex; justify-content: space-between; align-items: center; padding: 20px 5%; background: var(--bg-white); position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
    .logo { display: flex; align-items: center; gap: 10px; font-size: 1.5rem; font-weight: 800; color: var(--text-dark); text-decoration: none; letter-spacing: -0.5px; }
    .logo i { color: var(--primary); font-size: 1.8rem; }
    
    .nav-links { display: flex; list-style: none; gap: 30px; }
    .nav-links a { text-decoration: none; color: var(--text-gray); font-weight: 500; font-size: 0.95rem; transition: color 0.3s; }
    .nav-links a:hover { color: var(--primary); }
    
    .nav-buttons { display: flex; gap: 15px; align-items: center; }
    .btn { padding: 10px 24px; border-radius: 50px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.3s ease; text-decoration: none; text-align: center; border: none;}
    .btn-ghost { background: transparent; color: var(--text-dark); }
    .btn-ghost:hover { color: var(--primary); }
    .btn-primary { background: var(--primary); color: white; box-shadow: 0 4px 15px rgba(0, 180, 216, 0.3); }
    .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }
    .btn-outline { background: transparent; color: var(--primary); border: 2px solid var(--primary); }

    .hero-section {
        display: flex; justify-content: space-between; align-items: center; padding: 60px 5%; min-height: 85vh;
        background: radial-gradient(circle at 80% 50%, rgba(0, 180, 216, 0.15) 0%, rgba(255,255,255,0) 50%);
        position: relative;
    }

    .hero-text { max-width: 600px; }
    .hero-text h1 { font-size: 3.8rem; font-weight: 800; line-height: 1.1; margin-bottom: 20px; color: var(--text-dark); letter-spacing: -1px; }
    .hero-text p { font-size: 1.1rem; color: var(--text-gray); margin-bottom: 35px; line-height: 1.6; }
    .hero-action-buttons { display: flex; gap: 20px; }

    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 1);
        border-radius: 20px;
        padding: 40px;
        width: 420px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08);
    }

    .form-header { text-align: center; margin-bottom: 25px; }
    .form-header h2 { font-size: 1.8rem; font-weight: 700; color: var(--text-dark); }
    
    .input-group { margin-bottom: 15px; }
    .input-group input, .input-group select {
        width: 100%; padding: 14px 20px; border-radius: 10px; border: 1px solid var(--border-color);
        background: var(--bg-white); font-size: 0.95rem; color: var(--text-dark); outline: none;
    }
    .input-group input:focus, .input-group select:focus { border-color: var(--primary); }
    
    .submit-btn { width: 100%; border-radius: 10px; padding: 14px; font-size: 1rem; margin-top: 10px; margin-bottom: 20px;}
    .form-toggle { text-align: center; color: var(--text-gray); font-size: 0.9rem; cursor: pointer; font-weight: 500;}
    .form-toggle span { color: var(--text-dark); font-weight: 700; border-bottom: 1px solid var(--text-dark); }

    #login-form { display: none; }

    /* About Section Styles */
    .about-section { padding: 100px 5%; background: var(--bg-white); }
    .section-title { text-align: center; margin-bottom: 60px; }
    .section-title h2 { font-size: 2.5rem; font-weight: 800; margin-bottom: 15px; }
    .section-title p { color: var(--text-gray); max-width: 700px; margin: 0 auto; line-height: 1.6; }

    .about-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 40px; max-width: 1200px; margin: 0 auto; }
    .offering-card { padding: 40px; border-radius: 20px; background: var(--bg-light); border: 1px solid var(--border-color); transition: 0.3s; }
    .offering-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    .offering-card.employer-card { border-left: 5px solid var(--primary); }
    .offering-card.seeker-card { border-left: 5px solid #2d3436; }

    .offering-header { display: flex; align-items: center; gap: 15px; margin-bottom: 25px; }
    .offering-header i { font-size: 2rem; color: var(--primary); }
    .offering-header h3 { font-size: 1.5rem; font-weight: 700; }

    .offering-list { list-style: none; }
    .offering-list li { margin-bottom: 15px; display: flex; gap: 12px; font-size: 0.95rem; line-height: 1.5; color: #444; }
    .offering-list li i { color: var(--primary); margin-top: 5px; }
    .offering-list li strong { color: var(--text-dark); }

    /* Features/Icon Section */
    .features-section { background: var(--bg-light); padding: 80px 5%; border-top: 1px solid var(--border-color); }
    .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; max-width: 1200px; margin: 0 auto; }
    .feature-card { text-align: center; }
    .feature-icon { width: 70px; height: 70px; border-radius: 20px; background: white; display: flex; justify-content: center; align-items: center; font-size: 2rem; color: var(--primary); margin: 0 auto 20px auto; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
    .feature-card h3 { margin-bottom: 10px; font-weight: 700; }

    @media (max-width: 1024px) {
        .hero-text h1 { font-size: 3rem; }
        .hero-section { flex-direction: column; text-align: center; }
        .hero-action-buttons { justify-content: center; margin-bottom: 40px; }
        .about-grid { grid-template-columns: 1fr; }
        .features-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>

<body>
  <nav class="navbar">
      <a href="#" class="logo"><i class="fa-solid fa-briefcase"></i> JOB-PORTAL</a>
      <ul class="nav-links">
          <li><a href="index.php">Home</a></li>
          <li><a href="#about">About</a></li>
          <li><a href="analytics.php">Analytics Dashboard</a></li>
      </ul>
      <div class="nav-buttons">
          <button class="btn btn-ghost" onclick="toggleToLogin()">Log In</button>
          <button class="btn btn-primary" onclick="toggleToSignup()">Sign Up</button>
      </div>
  </nav>

  <section class="hero-section">
      <div class="hero-text">
          <h1>Discover opportunities, hire exceptional talent, and unlock growth.</h1>
          <p>Explore careers, connect with skilled professionals, and achieve your goals — all in one platform that brings everything together.</p>
          <div class="hero-action-buttons">
              <button onclick="toggleToSignup()" class="btn btn-primary" style="padding: 15px 35px; font-size: 1.1rem;">Get Started</button>
              <a href="#about" class="btn btn-outline" style="padding: 15px 35px; font-size: 1.1rem; border-radius: 50px; color: var(--text-dark); border-color: var(--border-color); text-decoration: none;">Learn More</a>
          </div>
      </div>

      <div class="glass-card">
          <div id="signup-form">
              <div class="form-header"><h2>Create Account</h2></div>
              <form method="POST" action="">
                  <div class="input-group"><input type="text" name="name" placeholder="Full name" required /></div>
                  <div class="input-group"><input type="email" name="email" placeholder="Email address" required /></div>
                  <div class="input-group"><input type="password" name="password" placeholder="Password" required /></div>
                  <div class="input-group">
                      <select name="role" required>
                          <option value="" disabled selected>Select Role</option>
                          <option value="jobseeker">Job Seeker</option>
                          <option value="employer">Employer</option>
                      </select>
                  </div>
                  <input type="submit" name="register" value="Sign Up Now" class="btn btn-primary submit-btn" />
                  <div class="form-toggle" onclick="toggleToLogin()">Already have an account? <span>Log In</span></div>
              </form>
          </div>

          <div id="login-form">
              <div class="form-header"><h2>Welcome Back</h2></div>
              <form method="POST" action="">
                  <div class="input-group"><input type="email" name="email" placeholder="Email address" required /></div>
                  <div class="input-group"><input type="password" name="password" placeholder="Password" required /></div>
                  <input type="submit" name="login" value="Log In Securely" class="btn btn-primary submit-btn" />
                  <div class="form-toggle" onclick="toggleToSignup()">New to Job-Portal? <span>Create Account</span></div>
              </form>
          </div>
      </div>
  </section>

  <section class="about-section" id="about">
      <div class="section-title">
          <h2>About Smart Job Listing Portal</h2>
          <p>We leverage dynamic matching algorithms and a secure backend to bridge the gap between world-class talent and industry-leading employers.</p>
      </div>

      <div class="about-grid">
          
          <div class="offering-card seeker-card">
              <div class="offering-header">
                  <i class="fa-solid fa-user-tie"></i>
                  <h3>For Job Seekers</h3>
              </div>
              <ul class="offering-list">
                  <li><i class="fa-solid fa-circle-check"></i> <span><strong>Advanced Job Search:</strong> Filter listings by title, location, and employment types like Full-Time or Internship.</span></li>
                  <li><i class="fa-solid fa-circle-check"></i> <span><strong>Recommendation Engine:</strong> Get personalized job matches based on your unique skills and preferences.</span></li>
                  <li><i class="fa-solid fa-circle-check"></i> <span><strong>Skill Match Analyzer:</strong> Visualize your compatibility with specific roles and identify missing skills.</span></li>
                  <li><i class="fa-solid fa-circle-check"></i> <span><strong>Profile Management:</strong> Maintain a digital portfolio including summaries, education, and projects.</span></li>
                  <li><i class="fa-solid fa-circle-check"></i> <span><strong>Application Tracking:</strong> Monitor your application status in real-time through a dedicated dashboard.</span></li>
              </ul>
          </div>

          <div class="offering-card employer-card">
              <div class="offering-header">
                  <i class="fa-solid fa-building-user"></i>
                  <h3>For Employers</h3>
              </div>
              <ul class="offering-list">
                  <li><i class="fa-solid fa-circle-check"></i> <span><strong>Candidate Discovery:</strong> Proactively find talent by searching our extensive candidate database.</span></li>
                  <li><i class="fa-solid fa-circle-check"></i> <span><strong>Seamless Posting:</strong> Create detailed job listings with specific skill requirements in minutes.</span></li>
                  <li><i class="fa-solid fa-circle-check"></i> <span><strong>Applicant Tracking:</strong> Manage your entire hiring pipeline with our built-in ATS tabular view.</span></li>
                  <li><i class="fa-solid fa-circle-check"></i> <span><strong>Instant Decisions:</strong> View full profiles, download resumes, and update statuses with a single click.</span></li>
                  <li><i class="fa-solid fa-circle-check"></i> <span><strong>Employer Branding:</strong> Customize your company profile and logo to attract the best talent.</span></li>
              </ul>
          </div>

      </div>

      <div style="margin-top: 80px; text-align: center; border-top: 1px solid var(--border-color); padding-top: 60px;">
          <h3 style="margin-bottom: 40px; font-weight: 700;">Powered by a Modern Technology Stack</h3>
          <div class="features-grid">
              <div class="feature-card">
                  <div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                  <h3>Secure Backend</h3>
                  <p>Built with PHP & MySQL using Prepared Statements for maximum data protection.</p>
              </div>
              <div class="feature-card">
                  <div class="feature-icon"><i class="fa-solid fa-mobile-screen-button"></i></div>
                  <h3>Responsive UI</h3>
                  <p>A minimal Aqua Blue theme using CSS3 and JS for a fast, seamless experience.</p>
              </div>
              <div class="feature-card">
                  <div class="feature-icon"><i class="fa-solid fa-gears"></i></div>
                  <h3>Dynamic Matching</h3>
                  <p>Custom algorithms for skill-gap analysis and recommendation scoring.</p>
              </div>
          </div>
      </div>
  </section>

  <script>
      const signupForm = document.getElementById('signup-form');
      const loginForm = document.getElementById('login-form');

      function toggleToLogin() {
          signupForm.style.display = 'none';
          loginForm.style.display = 'block';
      }

      function toggleToSignup() {
          loginForm.style.display = 'none';
          signupForm.style.display = 'block';
      }
  </script>
</body>
</html>