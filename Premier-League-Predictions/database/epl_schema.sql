-- =====================================================
-- Database Schema untuk Premier League Predictions
-- English Premier League Naive Bayes Prediction System
-- =====================================================
-- Author: Saddam Davi Awali
-- Date: 2026-06-23
-- Description: Normalized database schema for EPL data
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: epl_naivebayes
--

CREATE DATABASE IF NOT EXISTS `epl_naivebayes` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `epl_naivebayes`;

-- --------------------------------------------------------

--
-- Table structure for table `team`
--

CREATE TABLE `team` (
  `team_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `full_name` VARCHAR(100) DEFAULT NULL,
  `short_name` VARCHAR(50) DEFAULT NULL,
  `founded_year` INT DEFAULT NULL,
  `stadium` VARCHAR(100) DEFAULT NULL,
  `city` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_name` (`name`),
  KEY `idx_city` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data tim Premier League';

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data admin';

-- --------------------------------------------------------

--
-- Table structure for table `season`
--

CREATE TABLE `season` (
  `season_id` INT AUTO_INCREMENT PRIMARY KEY,
  `year_start` INT NOT NULL,
  `year_end` INT NOT NULL,
  `champion_team_id` INT NULL,
  `total_teams` INT NOT NULL DEFAULT 20,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_season_years` (`year_start`, `year_end`),
  KEY `idx_champion` (`champion_team_id`),
  CONSTRAINT `fk_season_champion` FOREIGN KEY (`champion_team_id`) REFERENCES `team` (`team_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Musim kompetisi Premier League';

-- --------------------------------------------------------

--
-- Table structure for table `team_season`
--

CREATE TABLE `team_season` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `season_id` INT NOT NULL,
  `team_id` INT NOT NULL,
  `position` INT NOT NULL COMMENT 'Posisi klasemen akhir musim',
  `played` INT NOT NULL COMMENT 'Total pertandingan',
  `won` INT NOT NULL COMMENT 'Jumlah kemenangan',
  `drawn` INT NOT NULL COMMENT 'Jumlah seri',
  `lost` INT NOT NULL COMMENT 'Jumlah kekalahan',
  `goals_for` INT NOT NULL COMMENT 'Total gol dicetak',
  `goals_against` INT NOT NULL COMMENT 'Total gol kemasukan',
  `goal_difference` INT NOT NULL COMMENT 'Selisih gol',
  `points` INT NOT NULL COMMENT 'Total poin',
  `is_champion` ENUM('Ya', 'Tidak') NOT NULL DEFAULT 'Tidak' COMMENT 'Status juara',
  `win_rate` DECIMAL(5,4) GENERATED ALWAYS AS (won / NULLIF(played, 0)) STORED COMMENT 'Rasio kemenangan',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_season_team` (`season_id`, `team_id`),
  KEY `idx_season` (`season_id`),
  KEY `idx_team` (`team_id`),
  KEY `idx_position` (`position`),
  KEY `idx_points` (`points`),
  KEY `idx_champion` (`is_champion`),
  CONSTRAINT `fk_team_season_season` FOREIGN KEY (`season_id`) REFERENCES `season` (`season_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_team_season_team` FOREIGN KEY (`team_id`) REFERENCES `team` (`team_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Statistik tim per musim';

-- --------------------------------------------------------

--
-- Table structure for table `dataset` (Untuk Naive Bayes)
--

CREATE TABLE `dataset` (
  `dataset_id` INT AUTO_INCREMENT PRIMARY KEY,
  `season_id` INT NOT NULL,
  `team_id` INT NOT NULL,
  `won` TINYINT NOT NULL COMMENT 'Jumlah kemenangan',
  `drawn` TINYINT NOT NULL COMMENT 'Jumlah seri',
  `lost` TINYINT NOT NULL COMMENT 'Jumlah kekalahan',
  `goals_for` SMALLINT NOT NULL COMMENT 'Gol dicetak',
  `goals_against` SMALLINT NOT NULL COMMENT 'Gol kemasukan',
  `goal_diff` SMALLINT NOT NULL COMMENT 'Selisih gol',
  `points` SMALLINT NOT NULL COMMENT 'Total poin',
  `win_rate` DECIMAL(5,4) DEFAULT NULL COMMENT 'Rasio kemenangan',
  `label` ENUM('Juara', 'Tidak Juara') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label klasifikasi',
  `split_type` ENUM('Training', 'Testing') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipe split data',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_season` (`season_id`),
  KEY `idx_team` (`team_id`),
  KEY `idx_dataset_split` (`split_type`, `label`),
  CONSTRAINT `dataset_ibfk_1` FOREIGN KEY (`season_id`) REFERENCES `season` (`season_id`),
  CONSTRAINT `dataset_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `team` (`team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dataset untuk training/testing Naive Bayes';

-- --------------------------------------------------------

--
-- Table structure for table `prediction_result` (Hasil Prediksi)
--

CREATE TABLE `prediction_result` (
  `prediction_id` INT AUTO_INCREMENT PRIMARY KEY,
  `season_id` INT NOT NULL,
  `team_id` INT NOT NULL,
  `champion_probability` DECIMAL(5,4) NOT NULL COMMENT 'Probabilitas menjadi juara',
  `not_champion_probability` DECIMAL(5,4) NOT NULL COMMENT 'Probabilitas tidak juara',
  `predicted_label` ENUM('Juara', 'Tidak Juara') NOT NULL COMMENT 'Hasil prediksi',
  `actual_label` ENUM('Juara', 'Tidak Juara') DEFAULT NULL COMMENT 'Label aktual',
  `is_correct` TINYINT(1) DEFAULT NULL COMMENT 'Apakah prediksi benar',
  `prediction_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_prediction_season_team` (`season_id`, `team_id`),
  KEY `idx_season` (`season_id`),
  KEY `idx_team` (`team_id`),
  KEY `idx_probability` (`champion_probability`),
  CONSTRAINT `fk_prediction_season` FOREIGN KEY (`season_id`) REFERENCES `season` (`season_id`),
  CONSTRAINT `fk_prediction_team` FOREIGN KEY (`team_id`) REFERENCES `team` (`team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hasil prediksi Naive Bayes';

-- --------------------------------------------------------

--
-- Table structure for table `model_performance` (Evaluasi Model)
--

CREATE TABLE `model_performance` (
  `performance_id` INT AUTO_INCREMENT PRIMARY KEY,
  `model_name` VARCHAR(100) NOT NULL,
  `training_season_start` INT NOT NULL,
  `training_season_end` INT NOT NULL,
  `testing_season` INT NOT NULL,
  `accuracy` DECIMAL(5,4) NOT NULL,
  `precision` DECIMAL(5,4) DEFAULT NULL,
  `recall` DECIMAL(5,4) DEFAULT NULL,
  `f1_score` DECIMAL(5,4) DEFAULT NULL,
  `true_positive` INT DEFAULT NULL,
  `true_negative` INT DEFAULT NULL,
  `false_positive` INT DEFAULT NULL,
  `false_negative` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_model` (`model_name`),
  KEY `idx_testing` (`testing_season`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Evaluasi performa model';

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
