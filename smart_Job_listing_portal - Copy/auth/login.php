<?php
session_start();
include("../config/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {

    // Added real_escape_string for security
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Verify the hashed password
        if (password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            // Route based on role
            if ($user['role'] == "jobseeker") {
                header("Location: ../profile/jobseeker.php");
            } else {
                header("Location: ../profile/employer.php");
            }
            exit();

        } else {
            echo "<script>alert('Wrong Password'); window.location='index.php';</script>";
        }
    } else {
        echo "<script>alert('User not found'); window.location='index.php';</script>";
    }
}
?>