<?php
// action/download_resume.php
session_start();

// Connect to the database using an absolute path
require_once __DIR__ . '/../../../config/db.php'; 

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    die("Access Denied. Please log in as an employer.");
}

if (isset($_GET['app_id'])) {
    $app_id = intval($_GET['app_id']);

    // Fetch the resume path from the database
    $stmt = $conn->prepare("SELECT resume_path FROM applications WHERE id = ?");
    
    if ($stmt === false) {
        die("Database Error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row && !empty($row['resume_path'])) {
        
        // This finds your exact XAMPP htdocs folder and appends the project name
        $base_dir = $_SERVER['DOCUMENT_ROOT'] . '/smart_Job_listing_portal/';
        
        // Remove any leading slashes from the database path just in case, then combine them
        $clean_db_path = ltrim($row['resume_path'], '/');
        $filepath = $base_dir . $clean_db_path;

        // Execute the Download
        if (file_exists($filepath)) {
            // Prevent PDF corruption
            if (ob_get_level()) { ob_end_clean(); }

            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf'); // Assuming it's a PDF
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));
            
            readfile($filepath);
            exit;
        } else {
            // If it fails, print exact debug info so we know exactly why
            echo "<div style='font-family: sans-serif; padding: 20px;'>";
            echo "<h2 style='color: red;'>File Missing</h2>";
            echo "<p>The database says the resume is at: <strong>" . htmlspecialchars($row['resume_path']) . "</strong></p>";
            echo "<p>But the server could not find the file at the physical location: <br><strong style='color: blue;'>" . htmlspecialchars($filepath) . "</strong></p>";
            echo "</div>";
        }
    } else {
        echo "Error: No resume path found in the database for this application.";
    }
} else {
    echo "Error: No Application ID was provided.";
}
?>