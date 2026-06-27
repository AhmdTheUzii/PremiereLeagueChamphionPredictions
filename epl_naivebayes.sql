-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 27, 2026 at 02:07 AM
-- Server version: 5.7.33
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `epl_naivebayes`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data admin';

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `username`, `password`, `full_name`, `email`, `last_login`, `created_at`) VALUES
(1, 'admin', '$2y$10$wxjxVkC2drfWMZaV5BDJXOCY.WdLB7rMV33o71TMKcDKS6D1oyduu', 'Administrator', 'admin@epl-predictions.com', '2026-06-25 12:52:37', '2026-06-23 13:14:16');

-- --------------------------------------------------------

--
-- Table structure for table `dataset`
--

CREATE TABLE `dataset` (
  `dataset_id` int(11) NOT NULL,
  `season_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `won` tinyint(4) NOT NULL COMMENT 'Jumlah kemenangan',
  `drawn` tinyint(4) NOT NULL COMMENT 'Jumlah seri',
  `lost` tinyint(4) NOT NULL COMMENT 'Jumlah kekalahan',
  `goals_for` smallint(6) NOT NULL COMMENT 'Gol dicetak',
  `goals_against` smallint(6) NOT NULL COMMENT 'Gol kemasukan',
  `goal_diff` smallint(6) NOT NULL COMMENT 'Selisih gol',
  `points` smallint(6) NOT NULL COMMENT 'Total poin',
  `win_rate` decimal(5,4) DEFAULT NULL COMMENT 'Rasio kemenangan',
  `label` enum('Juara','Tidak Juara') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label klasifikasi',
  `split_type` enum('Training','Testing') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipe split data',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dataset untuk training/testing Naive Bayes';

-- --------------------------------------------------------

--
-- Table structure for table `model_performance`
--

CREATE TABLE `model_performance` (
  `performance_id` int(11) NOT NULL,
  `model_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `training_season_start` int(11) NOT NULL,
  `training_season_end` int(11) NOT NULL,
  `testing_season` int(11) NOT NULL,
  `accuracy` decimal(5,4) NOT NULL,
  `precision` decimal(5,4) DEFAULT NULL,
  `recall` decimal(5,4) DEFAULT NULL,
  `f1_score` decimal(5,4) DEFAULT NULL,
  `true_positive` int(11) DEFAULT NULL,
  `true_negative` int(11) DEFAULT NULL,
  `false_positive` int(11) DEFAULT NULL,
  `false_negative` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Evaluasi performa model';

-- --------------------------------------------------------

--
-- Table structure for table `prediction_result`
--

CREATE TABLE `prediction_result` (
  `prediction_id` int(11) NOT NULL,
  `season_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `champion_probability` decimal(5,4) NOT NULL COMMENT 'Probabilitas menjadi juara',
  `not_champion_probability` decimal(5,4) NOT NULL COMMENT 'Probabilitas tidak juara',
  `predicted_label` enum('Juara','Tidak Juara') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Hasil prediksi',
  `actual_label` enum('Juara','Tidak Juara') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Label aktual',
  `is_correct` tinyint(1) DEFAULT NULL COMMENT 'Apakah prediksi benar',
  `prediction_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hasil prediksi Naive Bayes';

-- --------------------------------------------------------

--
-- Table structure for table `season`
--

CREATE TABLE `season` (
  `season_id` int(11) NOT NULL,
  `year_start` int(11) NOT NULL,
  `year_end` int(11) NOT NULL,
  `champion_team_id` int(11) DEFAULT NULL,
  `total_teams` int(11) NOT NULL DEFAULT '20',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Musim kompetisi Premier League';

--
-- Dumping data for table `season`
--

INSERT INTO `season` (`season_id`, `year_start`, `year_end`, `champion_team_id`, `total_teams`, `created_at`) VALUES
(1, 2024, 2025, 1, 20, '2026-06-25 12:52:46'),
(2, 2025, 2026, 2, 20, '2026-06-25 12:52:46');

-- --------------------------------------------------------

--
-- Table structure for table `team`
--

CREATE TABLE `team` (
  `team_id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `founded_year` int(11) DEFAULT NULL,
  `stadium` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data tim Premier League';

--
-- Dumping data for table `team`
--

INSERT INTO `team` (`team_id`, `name`, `full_name`, `short_name`, `founded_year`, `stadium`, `city`, `created_at`, `updated_at`) VALUES
(1, 'Liverpool', 'Liverpool', 'Liverpool', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(2, 'Arsenal', 'Arsenal', 'Arsenal', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(3, 'Manchester City', 'Manchester City', 'Man City', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(4, 'Chelsea', 'Chelsea', 'Chelsea', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(5, 'Newcastle', 'Newcastle', 'Newcastle', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(6, 'Aston Villa', 'Aston Villa', 'Aston Villa', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(7, 'Nottingham Forest', 'Nottingham Forest', 'Forest', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(8, 'Brighton & Hove Albion', 'Brighton & Hove Albion', 'Brighton', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(9, 'AFC Bournemouth', 'AFC Bournemouth', 'Bournemouth', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(10, 'Brentford', 'Brentford', 'Brentford', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(11, 'Fulham', 'Fulham', 'Fulham', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(12, 'Crystal Palace', 'Crystal Palace', 'Crystal Palace', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(13, 'Everton', 'Everton', 'Everton', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(14, 'West Ham United', 'West Ham United', 'West Ham', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(15, 'Manchester United', 'Manchester United', 'Man Utd', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(16, 'Wolverhampton Wanderers', 'Wolverhampton Wanderers', 'Wolves', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(17, 'Tottenham Hotspur', 'Tottenham Hotspur', 'Spurs', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(18, 'Leicester', 'Leicester', 'Leicester', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(19, 'Ipswich', 'Ipswich', 'Ipswich', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(20, 'Southampton', 'Southampton', 'Southampton', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(21, 'Sunderland', 'Sunderland', 'Sunderland', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(22, 'Leeds', 'Leeds', 'Leeds', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(23, 'Burnley', 'Burnley', 'Burnley', NULL, NULL, NULL, '2026-06-25 12:52:46', '2026-06-25 12:52:46');

-- --------------------------------------------------------

--
-- Table structure for table `team_season`
--

CREATE TABLE `team_season` (
  `id` int(11) NOT NULL,
  `season_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `position` int(11) NOT NULL COMMENT 'Posisi klasemen akhir musim',
  `played` int(11) NOT NULL COMMENT 'Total pertandingan',
  `won` int(11) NOT NULL COMMENT 'Jumlah kemenangan',
  `drawn` int(11) NOT NULL COMMENT 'Jumlah seri',
  `lost` int(11) NOT NULL COMMENT 'Jumlah kekalahan',
  `goals_for` int(11) NOT NULL COMMENT 'Total gol dicetak',
  `goals_against` int(11) NOT NULL COMMENT 'Total gol kemasukan',
  `goal_difference` int(11) NOT NULL COMMENT 'Selisih gol',
  `points` int(11) NOT NULL COMMENT 'Total poin',
  `is_champion` enum('Ya','Tidak') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Tidak' COMMENT 'Status juara',
  `win_rate` decimal(5,4) GENERATED ALWAYS AS ((`won` / nullif(`played`,0))) STORED COMMENT 'Rasio kemenangan',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Statistik tim per musim';

--
-- Dumping data for table `team_season`
--

INSERT INTO `team_season` (`id`, `season_id`, `team_id`, `position`, `played`, `won`, `drawn`, `lost`, `goals_for`, `goals_against`, `goal_difference`, `points`, `is_champion`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 38, 25, 9, 4, 86, 41, 45, 84, 'Ya', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(2, 1, 2, 2, 38, 20, 14, 4, 69, 34, 35, 74, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(3, 1, 3, 3, 38, 21, 8, 9, 72, 44, 28, 71, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(4, 1, 4, 4, 38, 20, 9, 9, 64, 43, 21, 69, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(5, 1, 5, 5, 38, 20, 6, 12, 68, 47, 21, 66, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(6, 1, 6, 6, 38, 19, 9, 10, 58, 51, 7, 66, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(7, 1, 7, 7, 38, 19, 8, 11, 58, 46, 12, 65, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(8, 1, 8, 8, 38, 16, 13, 9, 66, 59, 7, 61, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(9, 1, 9, 9, 38, 15, 11, 12, 58, 46, 12, 56, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(10, 1, 10, 10, 38, 16, 8, 14, 66, 57, 9, 56, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(11, 1, 11, 11, 38, 15, 9, 14, 54, 54, 0, 54, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(12, 1, 12, 12, 38, 13, 14, 11, 51, 51, 0, 53, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(13, 1, 13, 13, 38, 11, 15, 12, 42, 44, -2, 48, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(14, 1, 14, 14, 38, 11, 10, 17, 46, 62, -16, 43, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(15, 1, 15, 15, 38, 11, 9, 18, 44, 54, -10, 42, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(16, 1, 16, 16, 38, 12, 6, 20, 54, 69, -15, 42, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(17, 1, 17, 17, 38, 11, 5, 22, 64, 65, -1, 38, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(18, 1, 18, 18, 38, 6, 7, 25, 33, 80, -47, 25, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(19, 1, 19, 19, 38, 4, 10, 24, 36, 82, -46, 22, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(20, 1, 20, 20, 38, 2, 6, 30, 26, 86, -60, 12, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(21, 2, 2, 1, 38, 26, 7, 5, 71, 27, 44, 85, 'Ya', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(22, 2, 3, 2, 38, 23, 9, 6, 77, 35, 42, 78, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(23, 2, 15, 3, 38, 20, 11, 7, 69, 50, 19, 71, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(24, 2, 6, 4, 38, 19, 8, 11, 56, 49, 7, 65, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(25, 2, 1, 5, 38, 17, 9, 12, 63, 53, 10, 60, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(26, 2, 9, 6, 38, 13, 18, 7, 58, 54, 4, 57, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(27, 2, 21, 7, 38, 14, 12, 12, 42, 48, -6, 54, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(28, 2, 8, 8, 38, 14, 11, 13, 52, 46, 6, 53, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(29, 2, 10, 9, 38, 14, 11, 13, 55, 52, 3, 53, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(30, 2, 4, 10, 38, 14, 10, 14, 58, 52, 6, 52, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(31, 2, 11, 11, 38, 15, 7, 16, 47, 51, -4, 52, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(32, 2, 5, 12, 38, 14, 7, 17, 53, 55, -2, 49, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(33, 2, 13, 13, 38, 13, 10, 15, 47, 50, -3, 49, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(34, 2, 22, 14, 38, 11, 14, 13, 49, 56, -7, 47, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(35, 2, 12, 15, 38, 11, 12, 15, 41, 51, -10, 45, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(36, 2, 7, 16, 38, 11, 11, 16, 48, 51, -3, 44, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(37, 2, 17, 17, 38, 10, 11, 17, 48, 57, -9, 41, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(38, 2, 14, 18, 38, 10, 9, 19, 46, 65, -19, 39, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(39, 2, 23, 19, 38, 4, 10, 24, 38, 75, -37, 22, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46'),
(40, 2, 16, 20, 38, 3, 11, 24, 27, 68, -41, 20, 'Tidak', '2026-06-25 12:52:46', '2026-06-25 12:52:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `dataset`
--
ALTER TABLE `dataset`
  ADD PRIMARY KEY (`dataset_id`),
  ADD KEY `idx_season` (`season_id`),
  ADD KEY `idx_team` (`team_id`),
  ADD KEY `idx_dataset_split` (`split_type`,`label`);

--
-- Indexes for table `model_performance`
--
ALTER TABLE `model_performance`
  ADD PRIMARY KEY (`performance_id`),
  ADD KEY `idx_model` (`model_name`),
  ADD KEY `idx_testing` (`testing_season`);

--
-- Indexes for table `prediction_result`
--
ALTER TABLE `prediction_result`
  ADD PRIMARY KEY (`prediction_id`),
  ADD KEY `idx_season` (`season_id`),
  ADD KEY `idx_team` (`team_id`),
  ADD KEY `idx_probability` (`champion_probability`);

--
-- Indexes for table `season`
--
ALTER TABLE `season`
  ADD PRIMARY KEY (`season_id`),
  ADD UNIQUE KEY `uk_season_years` (`year_start`,`year_end`),
  ADD KEY `idx_champion` (`champion_team_id`);

--
-- Indexes for table `team`
--
ALTER TABLE `team`
  ADD PRIMARY KEY (`team_id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_city` (`city`);

--
-- Indexes for table `team_season`
--
ALTER TABLE `team_season`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_season_team` (`season_id`,`team_id`),
  ADD KEY `idx_season` (`season_id`),
  ADD KEY `idx_team` (`team_id`),
  ADD KEY `idx_position` (`position`),
  ADD KEY `idx_points` (`points`),
  ADD KEY `idx_champion` (`is_champion`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `dataset`
--
ALTER TABLE `dataset`
  MODIFY `dataset_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `model_performance`
--
ALTER TABLE `model_performance`
  MODIFY `performance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prediction_result`
--
ALTER TABLE `prediction_result`
  MODIFY `prediction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `season`
--
ALTER TABLE `season`
  MODIFY `season_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `team`
--
ALTER TABLE `team`
  MODIFY `team_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `team_season`
--
ALTER TABLE `team_season`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `dataset`
--
ALTER TABLE `dataset`
  ADD CONSTRAINT `dataset_ibfk_1` FOREIGN KEY (`season_id`) REFERENCES `season` (`season_id`),
  ADD CONSTRAINT `dataset_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `team` (`team_id`);

--
-- Constraints for table `prediction_result`
--
ALTER TABLE `prediction_result`
  ADD CONSTRAINT `fk_prediction_season` FOREIGN KEY (`season_id`) REFERENCES `season` (`season_id`),
  ADD CONSTRAINT `fk_prediction_team` FOREIGN KEY (`team_id`) REFERENCES `team` (`team_id`);

--
-- Constraints for table `season`
--
ALTER TABLE `season`
  ADD CONSTRAINT `fk_season_champion` FOREIGN KEY (`champion_team_id`) REFERENCES `team` (`team_id`) ON DELETE SET NULL;

--
-- Constraints for table `team_season`
--
ALTER TABLE `team_season`
  ADD CONSTRAINT `fk_team_season_season` FOREIGN KEY (`season_id`) REFERENCES `season` (`season_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_team_season_team` FOREIGN KEY (`team_id`) REFERENCES `team` (`team_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
