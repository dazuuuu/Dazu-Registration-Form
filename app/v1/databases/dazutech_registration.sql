-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 21, 2026 at 06:21 AM
-- Server version: 10.11.18-MariaDB-cll-lve
-- PHP Version: 8.4.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dazutech_registration`
--

-- --------------------------------------------------------

--
-- Table structure for table `blogs`
--

CREATE TABLE `blogs` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `view_count` int(11) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_categories`
--

CREATE TABLE `blog_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#667eea',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_faqs`
--

CREATE TABLE `blog_faqs` (
  `id` int(11) NOT NULL,
  `blog_id` int(11) NOT NULL,
  `question` varchar(300) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_sections`
--

CREATE TABLE `blog_sections` (
  `id` int(11) NOT NULL,
  `blog_id` int(11) NOT NULL,
  `section_type` enum('text_only','text_image_left','text_image_right','image_gallery','video','youtube','code_block','quote') DEFAULT 'text_only',
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `media_type` enum('image','video','youtube') DEFAULT 'image',
  `video_id` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_tags`
--

CREATE TABLE `blog_tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_tag_relations`
--

CREATE TABLE `blog_tag_relations` (
  `blog_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `counties`
--

CREATE TABLE `counties` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'Kenya',
  `region` varchar(120) DEFAULT NULL,
  `slug` varchar(140) NOT NULL,
  `center_lat` decimal(10,7) DEFAULT NULL,
  `center_lng` decimal(10,7) DEFAULT NULL,
  `zoom` tinyint(4) NOT NULL DEFAULT 11,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `counties`
--

INSERT INTO `counties` (`id`, `name`, `country`, `region`, `slug`, `center_lat`, `center_lng`, `zoom`, `created_at`, `updated_at`) VALUES
(1, 'Kiambu', 'Kenya', '', 'kiambu-kenya', -1.0766540, 36.8482686, 11, '2026-06-25 09:19:08', '2026-06-25 09:19:08');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `abbreviation` varchar(10) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `overview` text DEFAULT NULL,
  `duration_months` tinyint(3) UNSIGNED NOT NULL,
  `course_fee` decimal(10,2) NOT NULL,
  `last_sequence` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `abbreviation`, `category_id`, `cover_image`, `description`, `overview`, `duration_months`, `course_fee`, `last_sequence`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Generative AI', 'AI', 3, '/uploads/courses/covers/1783934350_76508629741a.jpg', 'Introduction to Generative AI & Prompt Design\r\nText Generation & Marketing Copy (ChatGPT, Gemini)\r\nAI Image Generation (Midjourney, DALL-E, Stable Diffusion)\r\nCoding Assistance with Github Copilot & cursor\r\nCreating High-Converting Ad Copy & Assets\r\nProductivity Workflows & Automations', 'Create premium copy, imagery, and software code using modern generative tools.', 3, 60000.00, 1, 1, 8, '2026-07-01 03:12:33', '2026-07-13 09:19:10'),
(2, 'Web Development', 'WEB', 8, '/uploads/courses/covers/1783931608_8316887582da.png', 'Web Design Principles & UI Layouts\r\nHTML5 Semantic Tags & CSS Layouts (Flexbox/Grid)\r\nResponsive Web Design with Tailwind CSS\r\nInteractivity with JavaScript\r\nDjango \r\nPHP\r\nSEO Best Practices & Web Performance Tuning\r\nHosting, Domains, and Deploying Static Sites', 'Create fast, responsive, and SEO-friendly websites using standard modern frameworks.', 6, 120000.00, 0, 1, 8, '2026-07-01 03:45:59', '2026-07-18 10:08:37'),
(7, 'software development', 'SE', 9, '/uploads/courses/covers/1783941927_9d8cd1c45e20.png', 'Python Programming\r\nHTML5, CSS3, Modern JavaScript (ES6+)\r\nReact.js and Tailwind CSS Frontends\r\nDjango, DRF and RESTful APIs\r\nRelational and NoSQL Databases (PostgreSQL,SQLITE3)\r\nmobile development\r\nGit, GitHub, and Cloud Deployment (Vercel, Render,Aws)\r\nMachine learning\r\nCapstone Projects & Technical Interview Prep', 'Frontend, Backend , mobile development and Full Stack Development using modern technologies.', 18, 300000.00, 0, 1, NULL, '2026-07-01 15:18:06', '2026-07-18 09:43:54'),
(8, 'Python Programming', 'PY', 9, '/uploads/courses/covers/1783930797_f16fe576b9f7.jpg', 'Python Basics, Variables & Data Types\r\nControl Flows, Loops & Functions\r\nObject-Oriented Programming (OOP) in Python\r\nWorking with Databases & File I/O]\r\nWeb Scraping & API Integrations with Requests\r\nBuilding Automation Scripts & CLI Tools', 'Master general-purpose Python programming, object-oriented concepts, and automation scripting.', 3, 60000.00, 0, 1, 8, '2026-07-11 11:30:32', '2026-07-19 18:11:40'),
(9, 'Ai automation', 'AA', 3, '/uploads/courses/covers/1783934362_173b982b2470.webp', 'Workflow Automation Concepts (triggers, actions)\r\nUsing Make.com & Zapier to connect apps\r\nWriting Python/JS connectors for API integrations\r\nAutomating Email/Slack tasks with AI agents\r\nExtracting and parsing unstructured documents\r\n', 'Automate repetitive workflows and connect platforms using AI pipelines', 1, 30000.00, 0, 1, 8, '2026-07-13 09:18:55', '2026-07-13 09:19:22'),
(10, 'web hosting', 'WH', 4, '/uploads/courses/covers/1783936898_2821de77a682.jpg', 'Learn how websites go live on the internet with our Web Hosting course. Master domain setup, cPanel, SSL certificates, email hosting, WordPress installation, website deployment, backups, security, and server management. Gain practical skills to host business websites, client projects, and web applications with confidence. This course is perfect for beginners, aspiring web developers, freelancers, and entrepreneurs who want to build, launch, and manage professional websites while creating valuable career and business opportunities.\r\n', 'Web hosting is a service that securely stores your website\'s files on a physical or virtual server, making them accessible to anyone connected to the internet', 1, 3500.00, 0, 1, 8, '2026-07-13 10:01:38', '2026-07-20 10:48:17'),
(11, 'Mpesa integration', 'MPESA', 4, '/uploads/courses/covers/1783937183_d560df89ead4.png', 'M-Pesa Integration teaches you how to connect M-Pesa payments to websites, mobile apps, and business systems. You will learn how to receive payments, confirm transactions automatically, process customer payments, generate payment requests, and manage records using the M-Pesa Daraja API. The course includes hands on projects that help you build secure payment solutions used by businesses across Kenya. It is a valuable skill for software developers, freelancers, and entrepreneurs who want to create modern applications with seamless mobile payment features.\r\n', 'learn how to do mpesa integration B2C, C2B among others with STK push.', 3, 2500.00, 0, 1, 8, '2026-07-13 10:06:23', '2026-07-20 10:48:40'),
(12, 'Data analytics', 'DT', 5, '/uploads/courses/covers/1783938040_4941a13121be.jpg', 'Data Manipulation using MS Excel & Google Sheets\r\nSQL Databases (Structuring & Querying Data)\r\nData Visualization with Tableau & Power BI\r\nIntroduction to Python for Data Science (Pandas, NumPy)\r\nStatistical Analysis & Decision Making\r\nBuilding Interactive Business Dashboards', 'Analyze complex data, build beautiful dashboards, and drive business intelligence.', 1, 120000.00, 0, 1, 8, '2026-07-13 10:20:40', '2026-07-13 10:20:40'),
(13, 'cubersecurity', 'CS', 7, '/uploads/courses/covers/1783938740_fe23d09f6971.jpg', 'Cybersecurity teaches you how to protect computers, websites, networks, and data from hackers and online threats. You will learn about ethical hacking, password security, malware, network protection, encryption, and how to identify and fix security risks. Through practical labs and real world projects, you will gain hands on experience using tools and techniques trusted by security professionals. This course is ideal for students who want to build a career in IT security, protect businesses, and stay ahead in one of the fastest growing technology fields.\r\n', 'Cybersecurity is the practice of protecting networks, devices, and data from unauthorized digital access, attacks, and breaches', 6, 120000.00, 0, 1, 8, '2026-07-13 10:32:20', '2026-07-13 10:52:33'),
(14, 'machine learning', 'ML', 5, '/uploads/courses/covers/1783938888_d484a5d50a21.png', 'Python for Machine Learning (Scikit-Learn, SciPy)\r\nSupervised Learning (Regression, Classification)\r\nUnsupervised Learning (Clustering, Dimensionality)\r\nNeural Networks & Deep Learning Basics\r\nModel Evaluation, Hyperparameter Tuning\r\nDeploying ML Models as Microservices', 'Build and deploy intelligent predictive models from scratch using modern frameworks.', 6, 120000.00, 0, 1, 8, '2026-07-13 10:34:48', '2026-07-13 10:35:34'),
(15, 'AI and digital marketing', 'AD', 3, '/uploads/courses/covers/1783939484_4e1d178145b3.jpg', 'AI in Modern Digital Marketing Channels\r\nAI Copywriting & Ad Creative Generation\r\nSEO and Keyword Research with AI Assistance\r\nSocial Media Algorithm Strategies & Calendar Planning\r\nMeta & Google Paid Ad Campaigns Optimization\r\nMarketing Analytics & AI-Driven Insights', 'Master content creation, ad targeting, and digital marketing powered by AI.', 3, 60000.00, 0, 1, 8, '2026-07-13 10:41:52', '2026-07-13 10:44:44'),
(16, 'Social media management', 'SM', 4, '/uploads/courses/covers/1783941820_3a9643633715.webp', 'Social Media Strategy & Platform Demographics\r\nContent Creation & Editorial Calendars\r\nCommunity Management & Engagement Tactics\r\nAnalytics, KPIs, and Growth Reporting\r\nPaid Social Campaigns & Audience Targeting\r\nInfluencer Marketing & Brand Partnerships', 'Learn content strategy, community engagement, brand voice, and digital ad campaigns.', 1, 20000.00, 0, 1, 8, '2026-07-13 10:43:22', '2026-07-13 11:23:40'),
(17, 'Virtual Assistance', 'VA', 4, '/uploads/courses/covers/1783939746_c77b3b841f99.webp', 'A virtual assistant (VA) is a self-employed remote professional who provides administrative, technical, or creative support to businesses and entrepreneurs from a home office. Tasks typically include managing emails and calendars, data entry, social media management, and online research', 'learn how to do remote and professional administrative, technical, or creative support services to clients. ', 1, 20000.00, 0, 1, 8, '2026-07-13 10:49:06', '2026-07-13 10:49:06'),
(18, 'software development', 'SD', 9, '/uploads/courses/covers/1783941376_5f04f882105f.jpg', 'Python programing\r\nHTML5, CSS3, Modern JavaScript (ES6+)\r\nReact.js and Tailwind CSS Frontends\r\nDjango, php, and RESTful APIs\r\nRelational and NoSQL Databases (PostgreSQL, SQL lite)\r\nGit, GitHub, and Cloud Deployment (Vercel, Render)\r\nPayment integrations\r\nCapstone Projects & Technical Interview Prep\r\nMachine learning basics', 'lean a comprehensive Frontend, Backend, machine learning basics and Full Stack Development using modern technologies.', 15, 240000.00, 0, 0, 8, '2026-07-13 11:16:16', '2026-07-13 11:25:00'),
(19, 'Diploma in IT & AI', 'IT', 9, '/uploads/courses/covers/1784368366_9d2efb611016.png', 'Networking\r\nCybersecurity\r\nHardware and Technical Support\r\nAI and Generative AI\r\nRobotics and IoT\r\nWeb and Software Development\r\nBig Data and Data Analytics\r\nProject Management', 'This equips you with practical skills in networking, cybersecurity, AI, robotics, hardware support, and software development. It prepares you for high demand technology careers through hands on training and real world projects.', 18, 300000.00, 0, 1, 8, '2026-07-18 09:52:46', '2026-07-19 16:53:15'),
(20, 'pentagon', 'PYYY', 5, NULL, 'bctrfughuiftyufdxytfgiuhjkughytdfcytrg', 'ppppppppppp', 1, 6000.00, 0, 0, 8, '2026-07-19 18:10:26', '2026-07-19 18:11:48'),
(21, 'Diploma in Cybersecurity and AI', 'CSA', 7, '/uploads/courses/covers/1784541896_b2d9e06d15a4.jpg', 'Computer Systems and Networking\r\nPython Programming and Automation\r\nLinux Administration\r\nCybersecurity Fundamentals\r\nEthical Hacking and Penetration Testing\r\nCloud Computing and Cloud Security\r\nArtificial Intelligence and Machine Learning\r\nGenerative AI and AI Automation\r\nDigital Forensics and Incident Response\r\nCapstone Project, Industrial Attachment and Professional Development', 'AI cybersecurity uses machine learning algorithms to automate threat detection, analyze vast amounts of data, and respond to security incidents in real time.', 18, 300000.00, 0, 1, 8, '2026-07-20 09:53:38', '2026-07-20 10:04:56'),
(22, 'Diploma in Data Science', 'DS', 5, '/uploads/courses/covers/1784542163_8b8b08c65177.jpg', 'Python Programming\r\nMathematics and Statistics for Data Science\r\n Data Collection and Data Management\r\nSQL and Database Systems\r\nData Analysis and Visualization\r\nMachine Learning\r\nDeep Learning and Artificial Intelligence\r\nBig Data Technologies\r\nData Engineering and Cloud Computing\r\nCapstone Project, Industry Attachment and Professional Development\r\n', 'Data Science combines statistics, programming, and business knowledge to collect, analyze, and interpret data. It helps organizations build predictive models, solve problems, and make data driven decisions.\r\n', 18, 300000.00, 0, 1, 8, '2026-07-20 10:09:23', '2026-07-20 10:09:23'),
(23, 'UI and UX Design', 'UI', 8, '/uploads/courses/covers/1784542781_a6006529b829.png', 'Figma \r\nWireframing\r\nPrototyping \r\nUser experience', 'UI/UX Design teaches you how to create websites and mobile apps that are both attractive and easy to use. Learn to design modern digital experiences that improve user satisfaction and prepare you for careers in tech and product design.\r\n', 2, 40000.00, 0, 1, 8, '2026-07-20 10:19:41', '2026-07-20 10:19:41'),
(24, 'Data Analysis with Excel & Power BI', 'DAS', 5, '/uploads/courses/covers/1784543087_4e40e0986732.jpg', 'Microsoft Excel\r\nPower BI\r\nDashboards\r\nBusiness reporting', 'Learn how to turn data into insights using Excel and Power BI. Build interactive dashboards and reports that help businesses make smarter decisions.', 2, 40000.00, 0, 1, 8, '2026-07-20 10:24:47', '2026-07-20 10:24:47'),
(25, 'Wordpress web development', 'WP', 8, '/uploads/courses/covers/1784543886_5f69c28edf60.jpg', 'Website creation \r\nElementor\r\nWooCommerce\r\nWebsite maintenance', 'Learn to build professional websites without coding using WordPress, Elementor, and WooCommerce. Create business websites, online stores, and start earning as a freelance web developer.\r\n', 1, 30000.00, 0, 1, 8, '2026-07-20 10:38:06', '2026-07-20 10:38:06'),
(26, 'Video editing & content creation', 'VC', 4, '/uploads/courses/covers/1784544232_e12d91b80783.jpg', 'CapCut\r\nPremiere Pro\r\nTikTok\r\nYouTube content\r\nMeta', 'Master video editing and content creation for TikTok, YouTube, and social media. Learn to produce engaging videos that grow your audience and open opportunities in content creation and digital marketing.\r\n', 1, 30000.00, 0, 1, 8, '2026-07-20 10:43:52', '2026-07-20 10:43:52'),
(27, 'Digital marketing', 'DM', 4, '/uploads/courses/covers/1784544440_8c3507cb70ca.png', 'SEO\r\nGoogle Ads\r\nMeta Ads\r\nEmail marketing', 'Learn how to market businesses online using social media, SEO, and paid advertising. Gain practical skills to attract customers, grow brands, and build a career in digital marketing.\r\n', 1, 30000.00, 0, 1, 8, '2026-07-20 10:47:20', '2026-07-20 10:47:20');

-- --------------------------------------------------------

--
-- Table structure for table `course_categories`
--

CREATE TABLE `course_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `icon_path` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `course_categories`
--

INSERT INTO `course_categories` (`id`, `name`, `slug`, `icon_path`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'Ai courses', 'ai', NULL, 2, 1, '2026-07-13 08:58:37', '2026-07-13 08:58:37'),
(4, 'basic skills', 'category-1783936489', NULL, 3, 1, '2026-07-13 09:54:49', '2026-07-13 09:54:49'),
(5, 'Data', 'category-1783937942', NULL, 4, 1, '2026-07-13 10:19:02', '2026-07-13 10:31:06'),
(7, 'cybersecurity', 'cs', NULL, 0, 1, '2026-07-13 10:30:28', '2026-07-13 10:30:28'),
(8, 'Web Development', 'category', NULL, 0, 1, '2026-07-13 10:58:08', '2026-07-13 10:58:08'),
(9, 'Software Development', 'category-1783940297', NULL, 0, 1, '2026-07-13 10:58:17', '2026-07-13 10:58:17');

-- --------------------------------------------------------

--
-- Table structure for table `course_tutors`
--

CREATE TABLE `course_tutors` (
  `course_id` int(11) NOT NULL,
  `tutor_id` int(11) NOT NULL,
  `assigned_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_tutors`
--

INSERT INTO `course_tutors` (`course_id`, `tutor_id`, `assigned_at`) VALUES
(1, 1, '2026-07-01 07:38:38'),
(1, 2, '2026-07-01 07:39:56');

-- --------------------------------------------------------

--
-- Table structure for table `curriculum_sub_units`
--

CREATE TABLE `curriculum_sub_units` (
  `id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `curriculum_topics`
--

CREATE TABLE `curriculum_topics` (
  `id` int(11) NOT NULL,
  `sub_unit_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `curriculum_units`
--

CREATE TABLE `curriculum_units` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enquiries`
--

CREATE TABLE `enquiries` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `service` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('new','read','contacted','closed') DEFAULT 'new',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `notes` text DEFAULT NULL,
  `contacted_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enquiries`
--

INSERT INTO `enquiries` (`id`, `name`, `email`, `phone`, `service`, `message`, `status`, `priority`, `notes`, `contacted_at`, `closed_at`, `created_at`, `updated_at`) VALUES
(1, 'Test User', 'test@example.com', '0712345678', 'Commercial Kitchen', 'This is a test enquiry', 'closed', 'medium', NULL, NULL, NULL, '2026-06-25 13:52:07', '2026-06-25 13:52:07'),
(2, 'hetrhj', 'dazuai01@gmail.com', '0728678483', 'Course Fees', 'xfdbertsjn', 'new', 'medium', NULL, NULL, NULL, '2026-07-16 09:30:32', '2026-07-16 09:30:32');

-- --------------------------------------------------------

--
-- Table structure for table `enquiry_replies`
--

CREATE TABLE `enquiry_replies` (
  `id` int(11) NOT NULL,
  `enquiry_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `reply` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hero_slides`
--

CREATE TABLE `hero_slides` (
  `id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hero_slides`
--

INSERT INTO `hero_slides` (`id`, `image_path`, `caption`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'uploads/hero/1784281570_14cd27540db1.png', NULL, 0, 1, '2026-07-17 09:46:10');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempt_time` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempt_time`) VALUES
(1, 'admin@gmail.com', '::1', '2026-07-01 02:03:01'),
(2, 'karanjav494@gmail.com', '::1', '2026-07-01 07:40:56'),
(3, 'johndoe@test.com', '105.163.1.46', '2026-07-03 22:36:07'),
(4, 'dazuai01@gmail.com', '197.254.8.98', '2026-07-12 20:10:36'),
(5, 'dazuaihub@gmail.com', '105.161.168.134', '2026-07-13 08:08:08'),
(6, 'dazuhubs@gmail.com', '197.254.8.98', '2026-07-13 10:38:18'),
(7, 'dazuhubs@gmail.com', '197.254.8.98', '2026-07-13 10:39:03'),
(8, 'dazuhubs@gmail.com', '197.254.8.98', '2026-07-13 10:40:43'),
(9, 'dazuhubs@gmail.com', '197.254.8.98', '2026-07-13 10:41:26'),
(10, 'dazuhubs@gmail.com', '197.254.8.98', '2026-07-13 10:41:49'),
(11, 'dazuai01@gmail.com', '197.254.8.98', '2026-07-13 10:41:56'),
(12, 'dazuai01@gmail.com', '197.254.8.98', '2026-07-13 10:42:01'),
(13, 'fcsondu34@gmail.com', '154.159.237.52', '2026-07-15 14:08:17'),
(14, 'timothy707muriki@gmail.com', '129.222.187.137', '2026-07-16 05:19:23'),
(15, 'timothy707muriki@gmail.com', '129.222.187.137', '2026-07-16 05:19:58'),
(16, 'timothy707muriki@gmail.com', '129.222.187.137', '2026-07-16 05:20:29'),
(17, 'timothy707muriki@gmail.com', '129.222.187.137', '2026-07-16 05:35:25'),
(18, 'timothy707muriki@gmail.com', '129.222.187.137', '2026-07-16 05:35:50'),
(19, 'dazuaihub@gmail.com', '105.164.118.162', '2026-07-16 09:42:33'),
(20, 'dazuaihub@gmail.com', '105.164.118.162', '2026-07-16 10:03:26'),
(21, 'dazuaihub@gmail.com', '105.164.118.162', '2026-07-16 10:03:43'),
(22, 'dazuaihub@gmail.com', '105.164.118.162', '2026-07-16 10:04:55'),
(23, 'dazuaihub@gmail.com', '105.164.118.162', '2026-07-16 10:05:05'),
(24, 'dazuaihub@gmail.com', '105.164.118.162', '2026-07-16 10:05:12');

-- --------------------------------------------------------

--
-- Table structure for table `page_headers`
--

CREATE TABLE `page_headers` (
  `id` int(10) UNSIGNED NOT NULL,
  `page_key` varchar(60) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `page_headers`
--

INSERT INTO `page_headers` (`id`, `page_key`, `title`, `subtitle`, `image_path`, `updated_at`) VALUES
(1, 'services', 'Our Services', 'Comprehensive digital solutions tailored to elevate your business.', NULL, '2026-06-25 06:05:47'),
(2, 'projects', 'Our Projects', 'A selection of the work we are proud of.', NULL, '2026-06-25 06:05:47'),
(3, 'blogs', 'Our Blog', 'Insights, ideas and updates from the team.', NULL, '2026-06-25 06:05:47'),
(4, 'contact', 'Get in Touch', 'We would love to hear about your project.', NULL, '2026-06-25 06:05:47');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_otps`
--

CREATE TABLE `password_reset_otps` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `password_reset_otps`
--

INSERT INTO `password_reset_otps` (`id`, `user_id`, `email`, `otp_hash`, `attempts`, `expires_at`, `used_at`, `ip_address`, `created_at`) VALUES
(1, 8, 'dazuhubs@gmail.com', '$2y$10$9.rj2ZMeG0LXr.3.tmaV/ua43wEYc1SJTQYXc7mQK92ecXmwR4p.K', 0, '2026-07-13 10:56:39', '2026-07-13 10:42:08', '197.254.8.98', '2026-07-13 10:41:39'),
(2, 8, 'dazuhubs@gmail.com', '$2y$10$lbkoYJAkC2HmJZ49k7z5SODeTDLJJaNlRO251CM.FLHtsR14qffC.', 0, '2026-07-13 10:57:08', '2026-07-13 10:55:09', '197.254.8.98', '2026-07-13 10:42:08'),
(3, 8, 'dazuhubs@gmail.com', '$2y$10$6JhNWyKd1a/gsqzad5xRS.roZubinAoxaI3FjVxCw3XSCK00NJkAy', 0, '2026-07-13 11:10:10', '2026-07-13 10:56:18', '197.254.8.98', '2026-07-13 10:55:10'),
(4, 8, 'dazuhubs@gmail.com', '$2y$10$HFGhTeDRsO0etxR7j2j0SOctCoCoMEmCgOVK2TFU905/wlqmgHZQW', 0, '2026-07-16 10:19:00', '2026-07-16 10:04:49', '105.164.118.162', '2026-07-16 10:04:00');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `registration_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `payer_reference` varchar(150) NOT NULL,
  `mpesa_code` varchar(50) DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT current_timestamp(),
  `notes` varchar(255) DEFAULT NULL,
  `receipt_no` varchar(40) DEFAULT NULL,
  `receipt_html` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `small_title` varchar(100) NOT NULL,
  `major_title` varchar(200) NOT NULL,
  `project_slug` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `view_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_categories`
--

CREATE TABLE `project_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `category_slug` varchar(100) NOT NULL,
  `category_description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_gallery`
--

CREATE TABLE `project_gallery` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_title` varchar(100) DEFAULT NULL,
  `image_description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_tags`
--

CREATE TABLE `project_tags` (
  `id` int(11) NOT NULL,
  `tag_name` varchar(50) NOT NULL,
  `tag_slug` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_tag_relations`
--

CREATE TABLE `project_tag_relations` (
  `project_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_videos`
--

CREATE TABLE `project_videos` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `video_title` varchar(200) DEFAULT NULL,
  `video_url` varchar(500) NOT NULL,
  `video_embed_code` text DEFAULT NULL,
  `video_type` enum('youtube','vimeo','local','other') DEFAULT 'youtube',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `type` enum('residential','commercial') NOT NULL DEFAULT 'residential',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_hot_offer` tinyint(1) NOT NULL DEFAULT 0,
  `cover_image` varchar(255) DEFAULT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'Kenya',
  `county_id` int(11) DEFAULT NULL,
  `location_text` varchar(200) DEFAULT NULL,
  `bedrooms` tinyint(4) DEFAULT NULL,
  `price` decimal(14,2) DEFAULT NULL,
  `cash_offer_price` decimal(14,2) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `status` enum('published','draft') NOT NULL DEFAULT 'published',
  `is_sold` tinyint(1) NOT NULL DEFAULT 0,
  `sold_at` timestamp NULL DEFAULT NULL,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `title`, `slug`, `type`, `is_featured`, `is_hot_offer`, `cover_image`, `country`, `county_id`, `location_text`, `bedrooms`, `price`, `cash_offer_price`, `description`, `latitude`, `longitude`, `status`, `is_sold`, `sold_at`, `view_count`, `created_at`, `updated_at`) VALUES
(1, '4bedroom Bangaluu', '4bedroom-bangaluu', 'residential', 1, 0, 'uploads/properties/1782379296_a0d59b68c461.jpg', 'Kenya', 1, NULL, 4, NULL, NULL, '<p>Anything available is in here</p><h3>Heading here:</h3><ol><li data-list=\"ordered\"><span class=\"ql-ui\" contenteditable=\"false\"></span>swimming pool</li><li data-list=\"ordered\"><span class=\"ql-ui\" contenteditable=\"false\"></span>bath taps</li><li data-list=\"ordered\"><span class=\"ql-ui\" contenteditable=\"false\"></span>hot springs</li></ol><p><br></p>', NULL, NULL, 'published', 0, NULL, 8, '2026-06-25 09:21:37', '2026-06-25 17:12:42'),
(2, 'Sample', 'sample', 'residential', 1, 0, 'uploads/properties/1782397940_e2f88b7635b3.webp', 'Kenya', 1, NULL, 5, 48000.00, NULL, '<p>sample</p>', NULL, NULL, 'published', 0, NULL, 3, '2026-06-25 14:32:20', '2026-06-26 07:27:58');

-- --------------------------------------------------------

--
-- Table structure for table `property_images`
--

CREATE TABLE `property_images` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_images`
--

INSERT INTO `property_images` (`id`, `property_id`, `image_path`, `sort_order`, `created_at`) VALUES
(1, 1, 'uploads/properties/1782379297_3dc350bad584.png', 0, '2026-06-25 09:21:37'),
(2, 1, 'uploads/properties/1782379297_96352498ab42.jpg', 1, '2026-06-25 09:21:37'),
(3, 1, 'uploads/properties/1782379297_de040968822a.jpg', 2, '2026-06-25 09:21:37'),
(4, 1, 'uploads/properties/1782379297_a51b8262654c.png', 3, '2026-06-25 09:21:37');

-- --------------------------------------------------------

--
-- Table structure for table `property_videos`
--

CREATE TABLE `property_videos` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `url` varchar(255) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `registration_no` varchar(30) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `course_id` int(11) NOT NULL,
  `study_mode` enum('physical','online') NOT NULL,
  `id_document_path` varchar(255) NOT NULL,
  `registration_fee` decimal(10,2) NOT NULL,
  `course_fee` decimal(10,2) NOT NULL,
  `payment_plan` enum('monthly','3months','full') NOT NULL,
  `installment_count` tinyint(3) UNSIGNED NOT NULL,
  `installment_amount` decimal(10,2) NOT NULL,
  `installments_paid` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `next_payment_due` date DEFAULT NULL,
  `portal_locked` tinyint(1) NOT NULL DEFAULT 0,
  `portal_locked_at` timestamp NULL DEFAULT NULL,
  `credentials_sent_at` timestamp NULL DEFAULT NULL,
  `payment_reference` varchar(150) NOT NULL,
  `referral_registration_no` varchar(30) DEFAULT NULL,
  `possible_returning_student` tinyint(1) NOT NULL DEFAULT 0,
  `enrollment_type` enum('new','continuing') NOT NULL DEFAULT 'new',
  `prior_registration_id` int(11) DEFAULT NULL,
  `current_learning_month` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `fee_balance_note` varchar(255) DEFAULT NULL,
  `fee_status` enum('current','partial','overdue','paid_in_full') NOT NULL DEFAULT 'current',
  `registration_fee_waived` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`id`, `registration_no`, `full_name`, `email`, `phone`, `course_id`, `study_mode`, `id_document_path`, `registration_fee`, `course_fee`, `payment_plan`, `installment_count`, `installment_amount`, `installments_paid`, `next_payment_due`, `portal_locked`, `portal_locked_at`, `credentials_sent_at`, `payment_reference`, `referral_registration_no`, `possible_returning_student`, `enrollment_type`, `prior_registration_id`, `current_learning_month`, `fee_balance_note`, `fee_status`, `registration_fee_waived`, `status`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `user_id`, `created_at`, `updated_at`) VALUES
(9, NULL, 'yes we hack', 'yeswehack288@gmail.com', '12345678', 11, 'online', '/uploads/registrations/documents/1784284983_9d4ed0e07dd1.pdf', 1000.00, 1500.00, '3months', 3, 500.00, 0, NULL, 0, NULL, NULL, '[Location: n/a] test', NULL, 0, 'new', NULL, 1, NULL, 'current', 0, 'pending', NULL, NULL, NULL, NULL, '2026-07-17 10:43:03', '2026-07-17 10:43:03'),
(10, NULL, 'James Emekui', 'jameslosike96@gmail.com', '+254703716470', 8, 'online', '/uploads/registrations/documents/1784302824_c2bc855b94a6.pdf', 1000.00, 60000.00, 'monthly', 3, 20000.00, 0, NULL, 0, NULL, NULL, '[Location: Kenya, Turkana  county] I will be sort tomorrow', NULL, 0, 'new', NULL, 1, NULL, 'current', 0, 'pending', NULL, NULL, NULL, NULL, '2026-07-17 15:40:24', '2026-07-17 15:40:24');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `created_at`) VALUES
(1, 'superadmin', '2026-06-25 06:03:23'),
(2, 'admin', '2026-06-25 06:03:23'),
(3, 'user', '2026-06-25 06:03:23'),
(4, 'tutor', '2026-07-01 02:03:54'),
(5, 'student', '2026-07-01 02:03:54');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `short_description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `view_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_faqs`
--

CREATE TABLE `service_faqs` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `question` varchar(300) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_gallery`
--

CREATE TABLE `service_gallery` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_title` varchar(100) DEFAULT NULL,
  `image_description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_sections`
--

CREATE TABLE `service_sections` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `section_type` enum('text_only','text_image_left','text_image_right','image_gallery','video') DEFAULT 'text_only',
  `title` varchar(200) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `media_type` enum('image','video','youtube','vimeo') DEFAULT 'image',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('logo_alt', 'Dazu hub', '2026-07-17 09:37:23'),
('logo_path', 'assets/images/branding/1784280987_15b9b31612d7.png', '2026-07-17 09:36:27'),
('site_name', 'Dazu Hub', '2026-07-17 09:37:36'),
('testimonials_auto_publish', '1', '2026-07-11 11:23:14'),
('testimonials_students_open', '0', '2026-07-11 11:23:14');

-- --------------------------------------------------------

--
-- Table structure for table `store_orders`
--

CREATE TABLE `store_orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(32) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `contact_phone` varchar(20) NOT NULL,
  `fulfillment_method` enum('walkin','delivery') NOT NULL DEFAULT 'walkin',
  `pickup_location` varchar(255) DEFAULT NULL,
  `delivery_notes` varchar(500) DEFAULT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'KES',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` varchar(20) NOT NULL DEFAULT 'mpesa',
  `mpesa_merchant_request_id` varchar(64) DEFAULT NULL,
  `mpesa_checkout_request_id` varchar(64) DEFAULT NULL,
  `mpesa_receipt` varchar(32) DEFAULT NULL,
  `mpesa_phone` varchar(20) DEFAULT NULL,
  `mpesa_payer_name` varchar(150) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_order_items`
--

CREATE TABLE `store_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `parcel_id` varchar(32) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `fulfillment_status` enum('processing','ready_for_pickup','out_for_delivery','picked_up','delivered','arrived','cancelled') NOT NULL DEFAULT 'processing',
  `fulfilled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_submissions`
--

CREATE TABLE `student_submissions` (
  `id` int(11) NOT NULL,
  `registration_id` int(11) NOT NULL,
  `submission_type` enum('test','project') NOT NULL,
  `test_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `answer_text` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('submitted','graded') NOT NULL DEFAULT 'submitted',
  `marks` decimal(6,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `graded_by` int(11) DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_unit_progress`
--

CREATE TABLE `student_unit_progress` (
  `id` int(11) NOT NULL,
  `registration_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `is_unlocked` tinyint(1) NOT NULL DEFAULT 0,
  `test_completed` tinyint(1) NOT NULL DEFAULT 0,
  `project_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `position` varchar(150) DEFAULT NULL,
  `biography` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_members`
--

INSERT INTO `team_members` (`id`, `name`, `position`, `biography`, `phone`, `email`, `photo`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Eunice Muturi', 'Backend Developer', 'Am a professional python and react developer with over 3 years of experience in real world projects.', '+254 792248332', 'admin@gmail.com', 'uploads/team/1784277581_a4a5444c26f0.png', 1, '2026-06-25 13:38:30', '2026-07-17 08:39:41'),
(2, 'Mwangi Ngigi', 'Founder', 'Am a software developer and Ai educator .\r\na tech enthusiast and a champion of digital skills for jobs creation.', NULL, NULL, 'uploads/team/1784277673_efe83db634a6.png', 0, '2026-07-17 08:23:10', '2026-07-17 08:41:13'),
(3, 'Muigai Kiongo', 'Administrator & Trainer', 'Am a fullstack developer skilled with python, react, kotlin, js and django.', NULL, NULL, 'uploads/team/1784277477_64b04160a5f3.png', 2, '2026-07-17 08:37:57', '2026-07-17 12:10:11'),
(4, 'Hans Juma', 'Manager & Backend Developer', 'skilled in python , java script, node js and django', NULL, NULL, 'uploads/team/1784290835_f38a9fdc1a91.png', 3, '2026-07-17 09:21:03', '2026-07-17 12:20:35'),
(5, 'Victor Karanja', 'seniour software developer', 'skilled with PHP, React, next js, node js, c++ and flutter', NULL, NULL, NULL, 4, '2026-07-17 09:24:42', '2026-07-17 09:24:42'),
(7, 'Nelix Njoki', 'Ai automations expert', 'Skilled with Ai automations and workflows . \r\nI can automate anything.', NULL, NULL, 'uploads/team/1784290439_35d32862c592.png', 5, '2026-07-17 09:32:17', '2026-07-17 12:13:59');

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_initial` varchar(5) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `rating` int(11) DEFAULT 5,
  `testimonial_text` text NOT NULL,
  `testimonial_type` enum('text','video') NOT NULL DEFAULT 'text',
  `video_url` varchar(500) DEFAULT NULL,
  `video_provider` enum('youtube','vimeo','upload','none') NOT NULL DEFAULT 'none',
  `service_tag` varchar(100) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `is_featured` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `title_tracker_page`
--

CREATE TABLE `title_tracker_page` (
  `id` int(11) NOT NULL,
  `page_title` varchar(255) DEFAULT 'Title Issuance Tracker',
  `hero_post_title` varchar(255) DEFAULT NULL,
  `hero_image` varchar(255) DEFAULT NULL,
  `section_title` varchar(255) DEFAULT 'View Title Issuing By Project',
  `intro_heading` varchar(255) DEFAULT 'Our Title Issuing Day',
  `intro_body` mediumtext DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `title_tracker_page`
--

INSERT INTO `title_tracker_page` (`id`, `page_title`, `hero_post_title`, `hero_image`, `section_title`, `intro_heading`, `intro_body`, `updated_at`) VALUES
(1, 'Title Issuance Tracker', 'Welcome To Prime Silver\'s Title Issuance Tracker', '/Realestate/public/uploads/title-tracker/1782473723_4a1846c9ea07.jpg', 'View Title Issuing By Project', 'Our Title Issuing Day', '<p>At <strong>Prime Silver Investment Limited</strong>, we believe in full transparency and timely communication with our clients. This page provides <strong>live updates on title deed issuance</strong> for all our land and property projects across Kenya.</p><p>We are proud to offer <strong>freehold title deeds</strong> for all our developments, ensuring full ownership rights for our buyers. Better yet, <strong>the title deed charges are already included in the purchase price</strong> — no hidden costs or extra fees after purchase.</p><p>Use this page to check the current status of your title deed and know when your project is ready. We\'re committed to ensuring the documentation process is smooth, secure, and stress-free.</p>', '2026-06-26 11:35:23');

-- --------------------------------------------------------

--
-- Table structure for table `title_tracker_projects`
--

CREATE TABLE `title_tracker_projects` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `project_link` varchar(255) DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `status` enum('published','draft') NOT NULL DEFAULT 'published',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `title_tracker_projects`
--

INSERT INTO `title_tracker_projects` (`id`, `title`, `slug`, `image`, `project_link`, `body`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Matuu phase 1 & 2', 'matuu-phase-1-2', '/Realestate/public/uploads/title-tracker/1782473708_bc7e270380d6.jpg', NULL, '<p>anything available</p>', 'published', 0, '2026-06-26 11:35:08', '2026-06-26 11:35:08');

-- --------------------------------------------------------

--
-- Table structure for table `tutors`
--

CREATE TABLE `tutors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bio` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `credentials_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tutors`
--

INSERT INTO `tutors` (`id`, `user_id`, `bio`, `photo_path`, `phone`, `is_active`, `credentials_sent_at`, `created_at`, `updated_at`) VALUES
(1, 10, '', '/Registration-System/public/uploads/tutors/1782891518_d88649ba41df.png', '', 1, '2026-07-01 07:38:42', '2026-07-01 07:38:38', '2026-07-01 07:38:42'),
(2, 11, '', '/Registration-System/public/uploads/tutors/1782891596_872002931840.jpg', '', 1, '2026-07-01 07:40:00', '2026-07-01 07:39:56', '2026-07-01 07:40:00');

-- --------------------------------------------------------

--
-- Table structure for table `unit_projects`
--

CREATE TABLE `unit_projects` (
  `id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `max_marks` decimal(6,2) NOT NULL DEFAULT 100.00,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `unit_tests`
--

CREATE TABLE `unit_tests` (
  `id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `max_marks` decimal(6,2) NOT NULL DEFAULT 100.00,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `account_status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role_id`, `is_active`, `account_status`, `email_verified`, `created_at`, `updated_at`) VALUES
(8, 'Administrator', 'dazuhubs@gmail.com', '$2y$10$hWsi4ow6j8/0gdVmaHpn5u0DetkKZaxwgwbYvLcCY.ZIiqjZ9W0Ri', 2, 1, 'active', 1, '2026-06-30 04:15:52', '2026-07-16 10:04:49'),
(10, 'TUT-pawatams', 'pawatams@gmail.com', '$2y$10$5RocUuBy7Y5SRCN/5k7qru46NEmjf5n/kydPGsb195nJuz8/XNEw2', 4, 1, 'active', 1, '2026-07-01 07:38:38', '2026-07-01 07:38:38'),
(11, 'TUT-karanjav494', 'karanjav494@gmail.com', '$2y$10$VZ8.as.kpvO0e6uGasN90.J3nnxyUzDxJDKD4Mz5lOadqhoWO3wrq', 4, 1, 'active', 1, '2026-07-01 07:39:56', '2026-07-01 07:39:56');

-- --------------------------------------------------------

--
-- Table structure for table `user_documents`
--

CREATE TABLE `user_documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `registration_id` int(11) DEFAULT NULL,
  `doc_type` enum('id_card','certificate') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `title` varchar(120) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_photo_path` varchar(255) DEFAULT NULL,
  `profile_photo_submitted_at` timestamp NULL DEFAULT NULL,
  `profile_photo_status` enum('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`user_id`, `first_name`, `last_name`, `phone`, `address`, `profile_photo_path`, `profile_photo_submitted_at`, `profile_photo_status`, `created_at`) VALUES
(8, '', '', '', NULL, NULL, NULL, 'none', '2026-06-30 04:15:52'),
(10, 'Simon', 'Mwangi', '', NULL, NULL, NULL, 'none', '2026-07-01 07:38:38'),
(11, 'Victor', '', '', NULL, NULL, NULL, 'none', '2026-07-01 07:39:56');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `blogs`
--
ALTER TABLE `blogs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_author` (`author_id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_published` (`published_at`),
  ADD KEY `idx_featured` (`is_featured`);

--
-- Indexes for table `blog_categories`
--
ALTER TABLE `blog_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indexes for table `blog_faqs`
--
ALTER TABLE `blog_faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blog` (`blog_id`);

--
-- Indexes for table `blog_sections`
--
ALTER TABLE `blog_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blog` (`blog_id`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `blog_tags`
--
ALTER TABLE `blog_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `blog_tag_relations`
--
ALTER TABLE `blog_tag_relations`
  ADD PRIMARY KEY (`blog_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `counties`
--
ALTER TABLE `counties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_counties_country` (`country`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `abbreviation` (`abbreviation`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_course_category` (`category_id`);

--
-- Indexes for table `course_categories`
--
ALTER TABLE `course_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_active_sort` (`is_active`,`sort_order`);

--
-- Indexes for table `course_tutors`
--
ALTER TABLE `course_tutors`
  ADD PRIMARY KEY (`course_id`,`tutor_id`),
  ADD KEY `tutor_id` (`tutor_id`);

--
-- Indexes for table `curriculum_sub_units`
--
ALTER TABLE `curriculum_sub_units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_unit_sort` (`unit_id`,`sort_order`);

--
-- Indexes for table `curriculum_topics`
--
ALTER TABLE `curriculum_topics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_subunit_sort` (`sub_unit_id`,`sort_order`);

--
-- Indexes for table `curriculum_units`
--
ALTER TABLE `curriculum_units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_course_sort` (`course_id`,`sort_order`);

--
-- Indexes for table `enquiries`
--
ALTER TABLE `enquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_created` (`created_at`);
ALTER TABLE `enquiries` ADD FULLTEXT KEY `idx_search` (`name`,`email`,`message`);

--
-- Indexes for table `enquiry_replies`
--
ALTER TABLE `enquiry_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_enquiry` (`enquiry_id`);

--
-- Indexes for table `hero_slides`
--
ALTER TABLE `hero_slides`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hero_active_order` (`is_active`,`sort_order`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_time` (`email`,`attempt_time`);

--
-- Indexes for table `page_headers`
--
ALTER TABLE `page_headers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_key` (`page_key`);

--
-- Indexes for table `password_reset_otps`
--
ALTER TABLE `password_reset_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_created` (`email`,`created_at`),
  ADD KEY `idx_user_active` (`user_id`,`used_at`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `idx_registration` (`registration_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_slug` (`project_slug`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_slug` (`project_slug`);

--
-- Indexes for table `project_categories`
--
ALTER TABLE `project_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_slug` (`category_slug`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_slug` (`category_slug`);

--
-- Indexes for table `project_gallery`
--
ALTER TABLE `project_gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `project_tags`
--
ALTER TABLE `project_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tag_name` (`tag_name`),
  ADD UNIQUE KEY `tag_slug` (`tag_slug`);

--
-- Indexes for table `project_tag_relations`
--
ALTER TABLE `project_tag_relations`
  ADD PRIMARY KEY (`project_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `project_videos`
--
ALTER TABLE `project_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_prop_county` (`county_id`),
  ADD KEY `idx_prop_type` (`type`),
  ADD KEY `idx_prop_status` (`status`),
  ADD KEY `idx_prop_featured` (`is_featured`),
  ADD KEY `idx_prop_hot` (`is_hot_offer`),
  ADD KEY `idx_prop_sold` (`is_sold`);

--
-- Indexes for table `property_images`
--
ALTER TABLE `property_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pimg_prop` (`property_id`);

--
-- Indexes for table `property_videos`
--
ALTER TABLE `property_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pvid_prop` (`property_id`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_referral_reg_no` (`referral_registration_no`),
  ADD KEY `idx_enrollment_type` (`enrollment_type`),
  ADD KEY `idx_prior_registration` (`prior_registration_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `service_faqs`
--
ALTER TABLE `service_faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service` (`service_id`);

--
-- Indexes for table `service_gallery`
--
ALTER TABLE `service_gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service` (`service_id`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `service_sections`
--
ALTER TABLE `service_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service` (`service_id`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `store_orders`
--
ALTER TABLE `store_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_orders_user` (`user_id`),
  ADD KEY `idx_orders_pay` (`payment_status`),
  ADD KEY `idx_orders_checkout` (`mpesa_checkout_request_id`);

--
-- Indexes for table `store_order_items`
--
ALTER TABLE `store_order_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `parcel_id` (`parcel_id`),
  ADD KEY `idx_items_order` (`order_id`),
  ADD KEY `idx_items_parcel` (`parcel_id`),
  ADD KEY `idx_items_status` (`fulfillment_status`);

--
-- Indexes for table `student_submissions`
--
ALTER TABLE `student_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test_id` (`test_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `graded_by` (`graded_by`),
  ADD KEY `idx_reg` (`registration_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `student_unit_progress`
--
ALTER TABLE `student_unit_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reg_unit` (`registration_id`,`unit_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_featured` (`is_featured`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `title_tracker_page`
--
ALTER TABLE `title_tracker_page`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `title_tracker_projects`
--
ALTER TABLE `title_tracker_projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_tt_slug` (`slug`);

--
-- Indexes for table `tutors`
--
ALTER TABLE `tutors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `unit_projects`
--
ALTER TABLE `unit_projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `unit_tests`
--
ALTER TABLE `unit_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `idx_user_doc` (`user_id`,`doc_type`),
  ADD KEY `idx_registration_doc` (`registration_id`,`doc_type`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `blogs`
--
ALTER TABLE `blogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `blog_categories`
--
ALTER TABLE `blog_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_faqs`
--
ALTER TABLE `blog_faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_sections`
--
ALTER TABLE `blog_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_tags`
--
ALTER TABLE `blog_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `counties`
--
ALTER TABLE `counties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `course_categories`
--
ALTER TABLE `course_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `curriculum_sub_units`
--
ALTER TABLE `curriculum_sub_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `curriculum_topics`
--
ALTER TABLE `curriculum_topics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `curriculum_units`
--
ALTER TABLE `curriculum_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enquiries`
--
ALTER TABLE `enquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `enquiry_replies`
--
ALTER TABLE `enquiry_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hero_slides`
--
ALTER TABLE `hero_slides`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `page_headers`
--
ALTER TABLE `page_headers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `password_reset_otps`
--
ALTER TABLE `password_reset_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_categories`
--
ALTER TABLE `project_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_gallery`
--
ALTER TABLE `project_gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_tags`
--
ALTER TABLE `project_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_videos`
--
ALTER TABLE `project_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `property_images`
--
ALTER TABLE `property_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `property_videos`
--
ALTER TABLE `property_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_faqs`
--
ALTER TABLE `service_faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_gallery`
--
ALTER TABLE `service_gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_sections`
--
ALTER TABLE `service_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_orders`
--
ALTER TABLE `store_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_order_items`
--
ALTER TABLE `store_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_submissions`
--
ALTER TABLE `student_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_unit_progress`
--
ALTER TABLE `student_unit_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `title_tracker_projects`
--
ALTER TABLE `title_tracker_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tutors`
--
ALTER TABLE `tutors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `unit_projects`
--
ALTER TABLE `unit_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `unit_tests`
--
ALTER TABLE `unit_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_documents`
--
ALTER TABLE `user_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `blogs`
--
ALTER TABLE `blogs`
  ADD CONSTRAINT `blogs_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `blog_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `blogs_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blog_categories`
--
ALTER TABLE `blog_categories`
  ADD CONSTRAINT `blog_categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `blog_faqs`
--
ALTER TABLE `blog_faqs`
  ADD CONSTRAINT `blog_faqs_ibfk_1` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blog_sections`
--
ALTER TABLE `blog_sections`
  ADD CONSTRAINT `blog_sections_ibfk_1` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blog_tag_relations`
--
ALTER TABLE `blog_tag_relations`
  ADD CONSTRAINT `blog_tag_relations_ibfk_1` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `blog_tag_relations_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `blog_tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `course_tutors`
--
ALTER TABLE `course_tutors`
  ADD CONSTRAINT `course_tutors_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_tutors_ibfk_2` FOREIGN KEY (`tutor_id`) REFERENCES `tutors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `curriculum_sub_units`
--
ALTER TABLE `curriculum_sub_units`
  ADD CONSTRAINT `curriculum_sub_units_ibfk_1` FOREIGN KEY (`unit_id`) REFERENCES `curriculum_units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `curriculum_topics`
--
ALTER TABLE `curriculum_topics`
  ADD CONSTRAINT `curriculum_topics_ibfk_1` FOREIGN KEY (`sub_unit_id`) REFERENCES `curriculum_sub_units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `curriculum_units`
--
ALTER TABLE `curriculum_units`
  ADD CONSTRAINT `curriculum_units_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enquiry_replies`
--
ALTER TABLE `enquiry_replies`
  ADD CONSTRAINT `enquiry_replies_ibfk_1` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enquiry_replies_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_otps`
--
ALTER TABLE `password_reset_otps`
  ADD CONSTRAINT `password_reset_otps_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `project_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `project_categories`
--
ALTER TABLE `project_categories`
  ADD CONSTRAINT `project_categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `project_gallery`
--
ALTER TABLE `project_gallery`
  ADD CONSTRAINT `project_gallery_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_tag_relations`
--
ALTER TABLE `project_tag_relations`
  ADD CONSTRAINT `project_tag_relations_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_tag_relations_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `project_tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_videos`
--
ALTER TABLE `project_videos`
  ADD CONSTRAINT `project_videos_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `fk_prop_county` FOREIGN KEY (`county_id`) REFERENCES `counties` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `property_images`
--
ALTER TABLE `property_images`
  ADD CONSTRAINT `fk_pimg_prop` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_videos`
--
ALTER TABLE `property_videos`
  ADD CONSTRAINT `fk_pvid_prop` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `registrations_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `service_faqs`
--
ALTER TABLE `service_faqs`
  ADD CONSTRAINT `service_faqs_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_gallery`
--
ALTER TABLE `service_gallery`
  ADD CONSTRAINT `service_gallery_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_sections`
--
ALTER TABLE `service_sections`
  ADD CONSTRAINT `service_sections_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_orders`
--
ALTER TABLE `store_orders`
  ADD CONSTRAINT `fk_orders_user_v2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_order_items`
--
ALTER TABLE `store_order_items`
  ADD CONSTRAINT `fk_items_order_v2` FOREIGN KEY (`order_id`) REFERENCES `store_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_submissions`
--
ALTER TABLE `student_submissions`
  ADD CONSTRAINT `student_submissions_ibfk_1` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_submissions_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `unit_tests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_submissions_ibfk_3` FOREIGN KEY (`project_id`) REFERENCES `unit_projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_submissions_ibfk_4` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_unit_progress`
--
ALTER TABLE `student_unit_progress`
  ADD CONSTRAINT `student_unit_progress_ibfk_1` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_unit_progress_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `curriculum_units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tutors`
--
ALTER TABLE `tutors`
  ADD CONSTRAINT `tutors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `unit_projects`
--
ALTER TABLE `unit_projects`
  ADD CONSTRAINT `unit_projects_ibfk_1` FOREIGN KEY (`unit_id`) REFERENCES `curriculum_units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `unit_tests`
--
ALTER TABLE `unit_tests`
  ADD CONSTRAINT `unit_tests_ibfk_1` FOREIGN KEY (`unit_id`) REFERENCES `curriculum_units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD CONSTRAINT `user_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_documents_ibfk_2` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `user_documents_ibfk_3` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
