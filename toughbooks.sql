-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Gegenereerd op: 07 jan 2026 om 15:30
-- Serverversie: 10.4.32-MariaDB
-- PHP-versie: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `toughbooks`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `admins`
--

INSERT INTO `admins` (`id`, `username`, `password_hash`, `is_active`, `last_login`, `created_at`) VALUES
(1, 'admin', '$2y$10$5Kc9H2xY3z8F9HnJYJrKQe7A0z7mZpPq4xwA5m3eU7Gxv2Bqz0e5K', 1, NULL, '2025-12-18 10:04:24'),
(2, 'wassim', '$2y$10$E8eQ7U4yP7Y3gYFQxXK3vOeGQx1Fh0zK8b6N1c4UjFzZcKX2ZbC6K', 1, NULL, '2026-01-07 12:22:08');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `created_at`) VALUES
(1, 'wassim', '$2y$10$aL2s.ImsKbVG9TEe.vCJguC8DQq030OQ699r97HaNyG.qDDBWg9Rm', '2026-01-07 12:30:48');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `action` varchar(50) DEFAULT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `user_info` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `laptops`
--

CREATE TABLE `laptops` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `model_code` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `price_eur` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `laptop_categories`
--

CREATE TABLE `laptop_categories` (
  `laptop_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `laptop_specs`
--

CREATE TABLE `laptop_specs` (
  `id` int(11) NOT NULL,
  `laptop_id` int(11) NOT NULL,
  `spec_key` varchar(100) NOT NULL,
  `spec_value` text NOT NULL,
  `spec_type` varchar(50) DEFAULT 'text',
  `display_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `laptop_config_fields`
--

CREATE TABLE `laptop_config_fields` (
  `id` int(11) NOT NULL,
  `laptop_id` int(11) NOT NULL,
  `field_key` varchar(64) NOT NULL,
  `field_label` varchar(255) NOT NULL,
  `field_type` varchar(20) NOT NULL,
  `default_value` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `laptop_config_field_options`
--

CREATE TABLE `laptop_config_field_options` (
  `id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `option_label` varchar(255) NOT NULL,
  `option_value` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `options`
--

CREATE TABLE `options` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `label` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `display_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `options`
--

INSERT INTO `options` (`id`, `question_id`, `label`, `value`, `display_order`) VALUES
(1, 1, 'Ja', 'yes', 1),
(2, 1, 'Nee', 'no', 2);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `text` text NOT NULL,
  `type` varchar(50) DEFAULT 'boolean',
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT 1.00,
  `is_required` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `questions`
--

INSERT INTO `questions` (`id`, `text`, `type`, `description`, `category_id`, `weight`, `is_required`, `display_order`, `created_at`) VALUES
(1, 'Waterdicht', 'boolean', '123', NULL, 1.10, 1, 1, '2026-01-07 14:04:26');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `quiz_answers`
--

CREATE TABLE `quiz_answers` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `question_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `quiz_sessions`
--

CREATE TABLE `quiz_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `result_laptop_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `scores`
--

CREATE TABLE `scores` (
  `id` int(11) NOT NULL,
  `laptop_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structuur voor view `vw_laptop_details`
-- (Zie onder voor de actuele view)
--
CREATE TABLE `vw_laptop_details` (
`id` int(11)
,`name` varchar(255)
,`model_code` varchar(100)
,`description` text
,`weight_kg` decimal(5,2)
,`price_eur` decimal(10,2)
,`categories` mediumtext
,`spec_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structuur voor view `vw_question_stats`
-- (Zie onder voor de actuele view)
--
CREATE TABLE `vw_question_stats` (
`id` int(11)
,`text` text
,`option_count` bigint(21)
,`answer_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structuur voor view `vw_score_overview`
-- (Zie onder voor de actuele view)
--
CREATE TABLE `vw_score_overview` (
`laptop` varchar(255)
,`question` text
,`answer` varchar(255)
,`points` int(11)
,`reason` text
);

-- --------------------------------------------------------

--
-- Structuur voor de view `vw_laptop_details`
--
DROP TABLE IF EXISTS `vw_laptop_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_laptop_details`  AS SELECT `l`.`id` AS `id`, `l`.`name` AS `name`, `l`.`model_code` AS `model_code`, `l`.`description` AS `description`, `l`.`weight_kg` AS `weight_kg`, `l`.`price_eur` AS `price_eur`, group_concat(distinct `c`.`name` separator ', ') AS `categories`, count(distinct `ls`.`id`) AS `spec_count` FROM (((`laptops` `l` left join `laptop_categories` `lc` on(`l`.`id` = `lc`.`laptop_id`)) left join `categories` `c` on(`lc`.`category_id` = `c`.`id`)) left join `laptop_specs` `ls` on(`l`.`id` = `ls`.`laptop_id`)) GROUP BY `l`.`id` ;

-- --------------------------------------------------------

--
-- Structuur voor de view `vw_question_stats`
--
DROP TABLE IF EXISTS `vw_question_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_question_stats`  AS SELECT `q`.`id` AS `id`, `q`.`text` AS `text`, count(distinct `o`.`id`) AS `option_count`, count(distinct `qa`.`id`) AS `answer_count` FROM ((`questions` `q` left join `options` `o` on(`q`.`id` = `o`.`question_id`)) left join `quiz_answers` `qa` on(`q`.`id` = `qa`.`question_id`)) GROUP BY `q`.`id` ;

-- --------------------------------------------------------

--
-- Structuur voor de view `vw_score_overview`
--
DROP TABLE IF EXISTS `vw_score_overview`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_score_overview`  AS SELECT `l`.`name` AS `laptop`, `q`.`text` AS `question`, `o`.`label` AS `answer`, `s`.`points` AS `points`, `s`.`reason` AS `reason` FROM (((`scores` `s` join `laptops` `l` on(`s`.`laptop_id` = `l`.`id`)) join `options` `o` on(`s`.`option_id` = `o`.`id`)) join `questions` `q` on(`o`.`question_id` = `q`.`id`)) ;

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexen voor tabel `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexen voor tabel `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexen voor tabel `laptops`
--
ALTER TABLE `laptops`
  ADD PRIMARY KEY (`id`),
   ADD UNIQUE KEY `unique_name_model` (`name`,`model_code`);

--
-- Indexen voor tabel `laptop_categories`
--
ALTER TABLE `laptop_categories`
  ADD PRIMARY KEY (`laptop_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexen voor tabel `laptop_specs`
--
ALTER TABLE `laptop_specs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_laptop_spec` (`laptop_id`,`spec_key`);

--
-- Indexen voor tabel `laptop_config_fields`
--
ALTER TABLE `laptop_config_fields`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_laptop_field` (`laptop_id`,`field_key`),
  ADD KEY `idx_laptop` (`laptop_id`);

--
-- Indexen voor tabel `laptop_config_field_options`
--
ALTER TABLE `laptop_config_field_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_field` (`field_id`);

--
-- Indexen voor tabel `options`
--
ALTER TABLE `options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexen voor tabel `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexen voor tabel `quiz_answers`
--
ALTER TABLE `quiz_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `option_id` (`option_id`);

--
-- Indexen voor tabel `quiz_sessions`
--
ALTER TABLE `quiz_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `result_laptop_id` (`result_laptop_id`);

--
-- Indexen voor tabel `scores`
--
ALTER TABLE `scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_score` (`laptop_id`,`option_id`),
  ADD KEY `option_id` (`option_id`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT voor een tabel `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT voor een tabel `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `laptops`
--
ALTER TABLE `laptops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `laptop_specs`
--
ALTER TABLE `laptop_specs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `laptop_config_fields`
--
ALTER TABLE `laptop_config_fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `laptop_config_field_options`
--
ALTER TABLE `laptop_config_field_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `options`
--
ALTER TABLE `options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT voor een tabel `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT voor een tabel `quiz_answers`
--
ALTER TABLE `quiz_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `quiz_sessions`
--
ALTER TABLE `quiz_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `scores`
--
ALTER TABLE `scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Beperkingen voor geëxporteerde tabellen
--

--
-- Beperkingen voor tabel `laptop_categories`
--
ALTER TABLE `laptop_categories`
  ADD CONSTRAINT `laptop_categories_ibfk_1` FOREIGN KEY (`laptop_id`) REFERENCES `laptops` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `laptop_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `laptop_specs`
--
ALTER TABLE `laptop_specs`
  ADD CONSTRAINT `laptop_specs_ibfk_1` FOREIGN KEY (`laptop_id`) REFERENCES `laptops` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `laptop_config_fields`
--
ALTER TABLE `laptop_config_fields`
  ADD CONSTRAINT `laptop_config_fields_ibfk_1` FOREIGN KEY (`laptop_id`) REFERENCES `laptops` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `laptop_config_field_options`
--
ALTER TABLE `laptop_config_field_options`
  ADD CONSTRAINT `laptop_config_field_options_ibfk_1` FOREIGN KEY (`field_id`) REFERENCES `laptop_config_fields` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `options`
--
ALTER TABLE `options`
  ADD CONSTRAINT `options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Beperkingen voor tabel `quiz_answers`
--
ALTER TABLE `quiz_answers`
  ADD CONSTRAINT `quiz_answers_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  ADD CONSTRAINT `quiz_answers_ibfk_2` FOREIGN KEY (`option_id`) REFERENCES `options` (`id`);

--
-- Beperkingen voor tabel `quiz_sessions`
--
ALTER TABLE `quiz_sessions`
  ADD CONSTRAINT `quiz_sessions_ibfk_1` FOREIGN KEY (`result_laptop_id`) REFERENCES `laptops` (`id`) ON DELETE SET NULL;

--
-- Beperkingen voor tabel `scores`
--
ALTER TABLE `scores`
  ADD CONSTRAINT `scores_ibfk_1` FOREIGN KEY (`laptop_id`) REFERENCES `laptops` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scores_ibfk_2` FOREIGN KEY (`option_id`) REFERENCES `options` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
