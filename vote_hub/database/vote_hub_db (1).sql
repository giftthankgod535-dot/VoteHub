-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 05, 2026 at 05:07 PM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vote_hub_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
CREATE TABLE IF NOT EXISTS `admins` (
  `admin_id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `full_name`, `email`, `password`, `created_at`) VALUES
(1, 'ThankGod Gift Eshiofune', 'eshiofune08@gmail.com', '$2y$10$B54L2WzdoQlkBs/RZj5.u..z12trKgGAsMsImAD13FpeX6w2dG.HS', '2026-11-05 08:06:55');

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

DROP TABLE IF EXISTS `candidates`;
CREATE TABLE IF NOT EXISTS `candidates` (
  `candidate_id` int NOT NULL AUTO_INCREMENT,
  `election_id` int NOT NULL,
  `position_id` int DEFAULT NULL,
  `full_name` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb3_unicode_ci,
  `manifesto` text COLLATE utf8mb3_unicode_ci,
  `photo` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`candidate_id`),
  KEY `election_id` (`election_id`),
  KEY `fk_candidate_position` (`position_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`candidate_id`, `election_id`, `position_id`, `full_name`, `description`, `manifesto`, `photo`, `created_at`) VALUES
(1, 1, NULL, 'Oluwaseyi Fortune', 'A very respectful and good girl', NULL, 'images/candidates/864c54bec5d2ef364f607124e5c59da7.jpg', '2026-11-05 09:46:58'),
(2, 2, NULL, 'Havilah Abbah', 'A very Fashionable boy', NULL, '3a50b2393939dc48444b111720017822.png', '2026-11-05 10:11:55'),
(3, 2, 3, 'ThankGod Gift', 'My name is Gift and i love fashion and everything that  has to do with it', 'I promise that when i become your miss Congeniality you wont regret voting for me', '8447f37ffdc14e693e8c4322a515b38e.jpg', '2026-11-05 15:33:35');

-- --------------------------------------------------------

--
-- Table structure for table `candidate_applications`
--

DROP TABLE IF EXISTS `candidate_applications`;
CREATE TABLE IF NOT EXISTS `candidate_applications` (
  `application_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `election_id` int NOT NULL,
  `position_id` int DEFAULT NULL,
  `full_name` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `manifesto` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `photo` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb3_unicode_ci DEFAULT 'pending',
  `admin_feedback` text COLLATE utf8mb3_unicode_ci,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`application_id`),
  UNIQUE KEY `unique_student_application` (`user_id`,`election_id`),
  KEY `election_id` (`election_id`),
  KEY `fk_application_position` (`position_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `candidate_applications`
--

INSERT INTO `candidate_applications` (`application_id`, `user_id`, `election_id`, `position_id`, `full_name`, `description`, `manifesto`, `photo`, `status`, `admin_feedback`, `submitted_at`, `reviewed_at`) VALUES
(1, 1, 2, 3, 'ThankGod Gift', 'My name is Gift and i love fashion and everything that  has to do with it', 'I promise that when i become your miss Congeniality you wont regret voting for me', '44de83245db978b9b6b45521ac3bc4cc.jpg', 'approved', '', '2026-11-05 15:28:48', '2026-11-05 16:33:35');

-- --------------------------------------------------------

--
-- Table structure for table `elections`
--

DROP TABLE IF EXISTS `elections`;
CREATE TABLE IF NOT EXISTS `elections` (
  `election_id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(150) COLLATE utf8mb3_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb3_unicode_ci,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` enum('upcoming','ongoing','completed') COLLATE utf8mb3_unicode_ci DEFAULT 'upcoming',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`election_id`),
  KEY `created_by` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `elections`
--

INSERT INTO `elections` (`election_id`, `title`, `description`, `start_date`, `end_date`, `status`, `created_by`, `created_at`) VALUES
(1, 'Nile personality awards', 'The Nile personality award is a fun campus voting event designed to celebrate the unique personalities, talents and social lives of students within Nile university. Students get the opportunity to vote for individuals who represent different fun and and entertaining categories. Let the voting begin!!!', '2026-11-11 09:45:00', '2026-11-27 22:30:00', 'upcoming', 1, '2026-11-05 08:47:19'),
(2, 'Gift\'s Fashion show', 'This is a platform where people can share fashion ideas and win for having the best fashion sense', '2026-11-05 11:09:00', '2026-11-27 23:10:00', 'upcoming', 1, '2026-11-05 10:09:34');

-- --------------------------------------------------------

--
-- Table structure for table `election_positions`
--

DROP TABLE IF EXISTS `election_positions`;
CREATE TABLE IF NOT EXISTS `election_positions` (
  `position_id` int NOT NULL AUTO_INCREMENT,
  `election_id` int NOT NULL,
  `position_name` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb3_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`position_id`),
  UNIQUE KEY `unique_position_per_election` (`election_id`,`position_name`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `election_positions`
--

INSERT INTO `election_positions` (`position_id`, `election_id`, `position_name`, `description`, `created_at`) VALUES
(2, 2, 'Most Elegant', 'A fashion sense that speaks once you step into a room', '2026-11-05 15:18:18'),
(3, 2, 'Miss Congeniality', 'The overall best fashion girlie of all the contestants', '2026-11-05 15:19:19');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `full_name` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `student_id` (`student_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `student_id`, `full_name`, `email`, `password`, `created_at`) VALUES
(1, '242130119', 'ThankGod Gift', 'giftthankgod535@gmail.com', '$2y$10$94541xYwRABkuVTHeFapg.tMz3YuTwAl4XUuVz9Z0g5pHBykK0E6K', '2026-11-05 07:41:56');

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

DROP TABLE IF EXISTS `votes`;
CREATE TABLE IF NOT EXISTS `votes` (
  `vote_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `election_id` int NOT NULL,
  `position_id` int DEFAULT NULL,
  `candidate_id` int NOT NULL,
  `voted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`vote_id`),
  UNIQUE KEY `unique_vote_per_position` (`user_id`,`election_id`,`position_id`),
  KEY `election_id` (`election_id`),
  KEY `candidate_id` (`candidate_id`),
  KEY `fk_vote_position` (`position_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `votes`
--

INSERT INTO `votes` (`vote_id`, `user_id`, `election_id`, `position_id`, `candidate_id`, `voted_at`) VALUES
(1, 1, 2, NULL, 2, '2026-11-05 10:15:59'),
(2, 1, 2, 3, 3, '2026-11-05 15:47:44');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
