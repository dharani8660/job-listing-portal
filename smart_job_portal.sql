-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 28, 2026 at 07:08 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `smart_job_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `employer_id`, `activity_type`, `description`, `created_at`) VALUES
(1, 4, 'JOB_SAVED', 'You saved the java full stack developer  position to review later.', '2026-04-11 10:01:10'),
(2, 4, 'JOB_APPLIED', 'You submitted an application for the java full stack developer  position.', '2026-04-11 10:07:14'),
(3, 4, 'JOB_SAVED', 'You saved the senior react developer  position to review later.', '2026-04-11 10:19:27'),
(4, 4, 'JOB_SAVED', 'You saved the product manager  position to review later.', '2026-04-11 10:24:05'),
(5, 4, 'PROFILE_UPDATE', 'You updated your profile and skills.', '2026-04-11 10:26:18'),
(6, 4, 'PROFILE_UPDATE', 'You updated your profile and skills.', '2026-04-11 10:28:22'),
(7, 4, 'PROFILE_UPDATE', 'You updated your profile and skills.', '2026-04-16 05:55:40'),
(8, 4, 'PROFILE_UPDATE', 'You updated your profile and skills.', '2026-04-16 06:35:16'),
(9, 4, 'JOB_APPLIED', 'You submitted an application for the web developer  position.', '2026-04-16 06:37:42'),
(10, 4, 'JOB_SAVED', 'You saved the web developer  position to review later.', '2026-04-19 19:05:15'),
(11, 5, 'JOB_SAVED', 'You saved the java full stack developer  position to review later.', '2026-04-22 15:44:21'),
(12, 5, 'PROFILE_UPDATE', 'You updated your profile and skills.', '2026-04-22 15:45:20'),
(13, 5, 'JOB_APPLIED', 'You submitted an application for the web developer  position.', '2026-04-22 15:48:17'),
(14, 5, 'PROFILE_UPDATE', 'You updated your profile and skills.', '2026-04-22 15:49:26'),
(15, 4, 'PROFILE_UPDATE', 'You updated your profile and skills.', '2026-04-22 16:38:46'),
(16, 4, 'PROFILE_UPDATE', 'You updated your profile and skills.', '2026-04-22 18:07:38'),
(17, 4, 'JOB_APPLIED', 'You submitted an application for the Sales Manager  position.', '2026-04-22 20:12:23'),
(18, 4, 'JOB_SAVED', 'You saved the Sales Manager  position to review later.', '2026-04-28 06:16:37'),
(19, 2, 'JOB_SAVED', 'You saved the Sales Manager  position to review later.', '2026-04-28 07:03:56'),
(20, 6, 'JOB_SAVED', 'You saved the Sales Manager  position to review later.', '2026-04-28 07:15:56'),
(21, 6, 'JOB_APPLIED', 'You submitted an application for the web developer  position.', '2026-04-28 07:31:02'),
(22, 6, 'JOB_SAVED', 'You saved the web developer  position to review later.', '2026-05-25 04:56:31');

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `cover_letter` text NOT NULL,
  `expected_salary` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `app_resume` varchar(255) DEFAULT NULL,
  `match_score` int(3) DEFAULT 0,
  `status` varchar(20) DEFAULT 'PENDING',
  `applied_on` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `user_id`, `job_id`, `cover_letter`, `expected_salary`, `start_date`, `app_resume`, `match_score`, `status`, `applied_on`) VALUES
(1, 4, 4, 'I am a good fit for a Java Full Stack Developer role because of my knowledge in both frontend and backend technologies, including Java, Spring Boot, JavaScript, and MySQL. I have experience building dynamic applications and strong problem-solving skills, making me capable of delivering efficient and scalable solutions.', '8.3 LPA/per annum ', '2026-03-20', '', 0, 'APPROVED', '2026-04-11 10:07:14'),
(2, 4, 1, 'I am a good fit for web development because I have a strong foundation in technologies like HTML, CSS, JavaScript, PHP, and MySQL, along with hands-on experience building a complete job portal project. I understand both frontend and backend development, including user authentication, database management, and dynamic web applications. I also possess strong problem-solving skills, attention to security, and a continuous learning mindset, which are essential for a successful web developer.', '8.3 LPA/per annum ', '2026-04-17', 'uploads/resumes/resume_4_1776318939.pdf', 100, 'PENDING', '2026-04-16 06:37:42'),
(3, 5, 1, 'I believe I’m a great fit for this web development position because I combine solid technical skills with a strong problem-solving mindset. I have a good understanding of core web technologies like HTML, CSS, and JavaScript, and I’m comfortable building responsive, user-friendly interfaces.\r\n\r\nWhat sets me apart is my willingness to learn and adapt. Web development is constantly evolving, and I actively keep improving my skills by working on projects and exploring new tools and frameworks. I also pay attention to writing clean, maintainable code and ensuring good performance and usability.\r\n\r\nAdditionally, I work well both independently and in a team. I can communicate ideas clearly, understand requirements, and turn them into functional solutions. I’m genuinely interested in creating websites that not only work well but also provide a great user experience.\r\n\r\nOverall, I’m motivated, reliable, and eager to contribute to your team while continuing to grow as a developer.', '40,000', '2026-04-23', 'uploads/resumes/resume_5_1775826229.pdf', 100, 'REJECTED', '2026-04-22 15:48:17'),
(4, 4, 5, 'aspering for a to work as a sales manager to get work on hands experinece ', '80,000', '2026-04-25', 'uploads/resumes/resume_4_1776888651.pdf', 100, 'APPROVED', '2026-04-22 20:12:23'),
(5, 6, 1, 'Dear Hiring Manager,\r\n\r\nI am writing to express my interest in the Web Developer position at your organization. I have a strong foundation in web development technologies, including HTML, CSS, JavaScript, PHP, and MySQL, and I am eager to apply my skills to build efficient, user-friendly, and scalable web applications.\r\n\r\nDuring my academic projects and internship experience, I developed dynamic websites and worked on both front-end and back-end development. I have hands-on experience in creating responsive designs, managing databases, and implementing functional features that enhance user experience. I also worked on a job portal project, where I handled user authentication, job listings, and application modules, which strengthened my problem-solving and development skills.\r\n\r\nI am highly motivated, detail-oriented, and always eager to learn new technologies. I enjoy working in collaborative environments and am committed to delivering high-quality work within deadlines. I believe my technical skills and passion for web development make me a strong candidate for this role.\r\n\r\nI would welcome the opportunity to contribute to your team and further develop my skills. Thank you for considering my application. I look forward to the possibility of discussing this opportunity with you.\r\n\r\nSincerely,\r\nDharani.k\r\n', '6 LPA', '2026-04-30', 'uploads/resumes/app_6_1777361462_Dharani_K_Web_Developer_Internship_Resume (1).pdf', 100, 'APPROVED', '2026-04-28 07:31:02');

-- --------------------------------------------------------

--
-- Table structure for table `employer_profiles`
--

CREATE TABLE `employer_profiles` (
  `user_id` int(11) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `company_size` varchar(50) DEFAULT NULL,
  `founded_year` int(11) DEFAULT NULL,
  `about_company` text DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employer_profiles`
--

INSERT INTO `employer_profiles` (`user_id`, `company_name`, `industry`, `website`, `email`, `phone`, `location`, `company_size`, `founded_year`, `about_company`, `logo_path`) VALUES
(4, 'TechNova Solutions Pvt Ltd', 'IT Services & Software Development', 'https://www.technovasolutions.com', 'dharanik7141@gmail.com', '9876543210', 'Bangalore, India', '200–500 employees', 2004, 'tech solution giver for best product building ', 'uploads/logos/logo_4_1775820048.png');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `job_type` varchar(50) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `salary` varchar(100) NOT NULL,
  `experience` varchar(50) DEFAULT NULL,
  `vacancies` int(11) DEFAULT 1,
  `deadline` date DEFAULT NULL,
  `description` text NOT NULL,
  `skills` varchar(255) NOT NULL,
  `status` enum('ACTIVE','CLOSED') DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `employer_id`, `title`, `category`, `job_type`, `location`, `salary`, `experience`, `vacancies`, `deadline`, `description`, `skills`, `status`, `created_at`) VALUES
(1, 4, 'web developer ', 'Software', NULL, NULL, '20,000', NULL, 1, NULL, 'experince with 3 years ', 'HTML, CSS, PHP, MySQL', 'ACTIVE', '2026-04-09 18:54:34'),
(2, 4, 'product manager ', 'Marketing', NULL, NULL, '20,000', NULL, 1, NULL, 'a gud product builder with 4 years of experience ', 'publick speking ', 'ACTIVE', '2026-04-09 19:55:49'),
(3, 4, 'senior react developer ', 'Software', NULL, NULL, '80,000/Month', NULL, 1, NULL, 'A well experineced senior developer for the web developer for the product building ', 'react,nodejs', 'CLOSED', '2026-04-10 10:55:46'),
(4, 4, 'java full stack developer ', 'IT & Software', 'Full-Time', 'Bangalore, India', '80,000/Month', '3 years ', 2, '2026-02-05', 'We are looking for a highly skilled Java Full Stack Developer to join our dynamic engineering team. In this role, you will be responsible for designing, developing, and maintaining scalable web applications from end to end. You will work closely with cross-functional teams to deliver high-quality software solutions that meet our business needs.', 'Java, Spring Boot, React.js, Angular, JavaScript, MySQL, PostgreSQL, REST APIs, Microservices, Git, Docker, HTML/CSS', 'ACTIVE', '2026-04-11 08:36:58'),
(5, 4, 'Sales Manager ', 'Sales & Marketing', 'Full-Time', 'Remote,Bengaluru', '80,000', '1-3 ', 14, '2026-04-30', '🎯 Job Overview\r\n\r\nWe are looking for a dynamic and results-driven Sales & Marketing Executive to drive business growth by identifying new opportunities, building client relationships, and executing effective marketing strategies. The ideal candidate should be passionate about sales, customer engagement, and brand promotion.\r\n\r\n🛠️ Key Responsibilities\r\nIdentify and develop new business opportunities through networking and lead generation\r\nBuild and maintain strong relationships with clients and customers\r\nPromote products/services through online and offline marketing campaigns\r\nConduct market research to identify trends and competitor strategies\r\nAchieve monthly and quarterly sales targets\r\nCollaborate with the marketing team to create promotional strategies\r\nPrepare and deliver sales presentations to clients\r\nMaintain records of sales, revenue, and client interactions', '💡 Required Skills Strong communication and interpersonal skills Negotiation and persuasion abilities Basic understanding of digital marketing (SEO, social media, ads) Customer-focused mindset Ability to work under targets and deadlines MS Office / CRM too', 'ACTIVE', '2026-04-22 20:01:47'),
(6, 4, 'HR & Administration Executive', 'HR & Administration', 'Full-Time', 'Bengaluru', '₹35,000 – ₹50,000', '1-3 years ', 1, '2026-05-31', 'Job Description\r\n\r\nWe are looking for a dedicated and organized HR & Administration Executive to manage recruitment activities, employee coordination, office administration, and HR operations. The candidate should possess excellent communication, organizational, and multitasking skills to support smooth \r\nbusiness operations.\r\n\r\nThe ideal candidate will assist in:\r\n\r\nRecruitment and onboarding\r\nEmployee record management\r\nAttendance and leave tracking\r\nOffice administration\r\nHR documentation\r\nEmployee engagement activities\r\n\r\nKey Responsibilities :\r\nHandle recruitment and interview scheduling\r\nMaintain employee records and HR databases\r\nManage attendance and leave reports\r\nCoordinate onboarding and documentation\r\nSupport payroll and HR operations\r\nMaintain office administration activities\r\nOrganize employee engagement programs\r\nHandle internal communication and reporting', 'Human Resource Management, Communication Skills, MS Excel & MS Office, Employee Coordination ,Recruitment Process Administration Management, Time Management, Documentation Skills', 'ACTIVE', '2026-05-25 09:00:40');

-- --------------------------------------------------------

--
-- Table structure for table `saved_jobs`
--

CREATE TABLE `saved_jobs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `saved_on` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `saved_jobs`
--

INSERT INTO `saved_jobs` (`id`, `user_id`, `job_id`, `saved_on`) VALUES
(1, 4, 4, '2026-04-11 10:01:10'),
(2, 4, 3, '2026-04-11 10:19:27'),
(3, 4, 2, '2026-04-11 10:24:05'),
(4, 4, 1, '2026-04-19 19:05:15'),
(5, 5, 4, '2026-04-22 15:44:21'),
(6, 4, 5, '2026-04-28 06:16:37'),
(7, 2, 5, '2026-04-28 07:03:56'),
(8, 6, 5, '2026-04-28 07:15:56'),
(9, 6, 1, '2026-05-25 04:56:31');

-- --------------------------------------------------------

--
-- Table structure for table `seeker_profiles`
--

CREATE TABLE `seeker_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `degree` varchar(255) DEFAULT NULL,
  `institution` varchar(255) DEFAULT NULL,
  `passing_year` varchar(50) DEFAULT NULL,
  `percentage` varchar(50) DEFAULT NULL,
  `experience_desc` text DEFAULT NULL,
  `projects` text DEFAULT NULL,
  `certifications` text DEFAULT NULL,
  `pref_role` varchar(255) DEFAULT NULL,
  `pref_salary` varchar(100) DEFAULT NULL,
  `pref_location` varchar(255) DEFAULT NULL,
  `pref_type` varchar(100) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `resume_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seeker_profiles`
--

INSERT INTO `seeker_profiles` (`id`, `user_id`, `job_title`, `phone`, `location`, `bio`, `skills`, `degree`, `institution`, `passing_year`, `percentage`, `experience_desc`, `projects`, `certifications`, `pref_role`, `pref_salary`, `pref_location`, `pref_type`, `photo_path`, `resume_path`, `created_at`, `updated_at`) VALUES
(1, 5, NULL, '9876543210', NULL, 'gud developer', 'HTML, CSS, PHP, MySQL, UI,Ux', 'Bachlore in Computer Application ', 'nmkrv collage ', '2023-2026', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'uploads/avatars/avatar_5_1776872720.jpg', 'uploads/resumes/resume_5_1775826229.pdf', '2026-04-10 13:03:49', '2026-04-22 15:49:26'),
(2, 1, NULL, '8660217141', NULL, 'aspering web developer ', 'HTML, CSS, PHP, MySQL', 'Bachlore in Computer Application ', 'nmkrv collage ', '2023-2026', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'uploads/resumes/resume_1_1775892735.pdf', '2026-04-11 07:32:15', '2026-04-11 07:32:15'),
(3, 4, 'front end developer ', '8660217141', '', 'aspering web developer ', 'HTML, CSS, PHP, MySQL', 'Bachlore in Computer Application ', 'nmkrv collage ', '2023-2026', '', '', '', '', '', '', '', '', '', 'uploads/resumes/resume_4_1776891836.pdf', '2026-04-11 10:26:18', '2026-04-28 06:16:19'),
(4, 6, 'web developer', '8134098765', 'Chennai', 'A passionate and detail-oriented Web Developer with a strong foundation in front-end and back-end technologies. Skilled in building responsive, user-friendly, and dynamic web applications using modern tools and frameworks. Proficient in languages such as HTML, CSS, JavaScript, PHP, and MySQL. Adept at problem-solving, debugging, and optimizing performance to deliver high-quality solutions. Eager to learn new technologies and contribute to innovative projects in a collaborative environment.', 'HTML, CSS, JavaScript, PHP, MySQL, Bootstrap, Responsive Web Design, Front-End Development, Back-End Development, Database Management, Git & GitHub, Debugging, Problem Solving, API Integration, UI/UX Design Basics, Cross-Browser Compatibility', 'BCA', 'KSIT Collage of Engenering', '2022-2026', '8.2 CGPA', 'Worked as a Web Developer Intern where I gained hands-on experience in designing and developing dynamic and responsive web applications. Assisted in building user-friendly interfaces using HTML, CSS, and JavaScript, and contributed to backend development using PHP and MySQL. Collaborated with the team to implement new features, fix bugs, and improve website performance. Participated in testing, debugging, and optimizing code to ensure smooth functionality across different browsers and devices. Gained practical exposure to real-world project development, version control using Git, and best coding practices.', '', '', 'web developer', '6 LPA', 'remote', 'Full time', '', 'uploads/resumes/resume_6_1777361313.pdf', '2026-04-28 07:21:18', '2026-04-28 07:28:33');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('jobseeker','employer') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Dharani k', 'dharani7141@gmail.com', '$2y$10$cppRTMZgJRkRqjyeOnNZwOX3AR09jgbliCuYLuToHAmCbEDbia94.', 'jobseeker', '2026-04-09 08:32:32'),
(2, 'Dharani.k', 'dharanik7141@gmail.com', '$2y$10$451KHKfsGMIwWTKqb4CivuiBTGsVvQyYa/Boi4JnN3tcBqAq5dOr2', 'jobseeker', '2026-04-09 17:09:01'),
(3, 'Dharani.k', 'ABC@gmail.com', '$2y$10$nr1pubL.xZbBo4ZYr/fmReafTFs/oQd0qhIUBplQ/ap9L8lYiVvra', 'employer', '2026-04-09 17:12:00'),
(4, 'Dharani.k', 'admin@example.com', '$2y$10$RJvyfECFPXn0fMdq0eHVbuTYC.DbO81G2o0l/l5e5ATRqJktSiINS', 'employer', '2026-04-09 17:19:36'),
(5, 'Dharani.k', 'dharani8660@gmail.com', '$2y$10$kBHlx8yMiamq8hGjszW5sehMA4FuwrAbhG8fAhLYRB658Dew8B5R.', 'jobseeker', '2026-04-10 12:47:00'),
(6, 'priya', 'dharu8696@gmail.com', '$2y$10$RPE00EnCQvqtd6LHR7aqNeQA/MftZwlOWKZd/oD3yssERh8pQlYyO', 'jobseeker', '2026-04-28 07:13:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employer_profiles`
--
ALTER TABLE `employer_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employer_id` (`employer_id`);

--
-- Indexes for table `saved_jobs`
--
ALTER TABLE `saved_jobs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `seeker_profiles`
--
ALTER TABLE `seeker_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `saved_jobs`
--
ALTER TABLE `saved_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `seeker_profiles`
--
ALTER TABLE `seeker_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employer_profiles`
--
ALTER TABLE `employer_profiles`
  ADD CONSTRAINT `employer_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seeker_profiles`
--
ALTER TABLE `seeker_profiles`
  ADD CONSTRAINT `seeker_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
