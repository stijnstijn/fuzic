-- phpMyAdmin SQL Dump
-- version 4.9.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Gegenereerd op: 14 nov 2020 om 12:43
-- Serverversie: 5.7.32-0ubuntu0.16.04.1
-- PHP-versie: 7.4.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fuzic`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `audience`
--

CREATE TABLE `audience` (
  `time` int(10) UNSIGNED NOT NULL,
  `game` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'sc2',
  `viewers` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `datapoints`
--

CREATE TABLE `datapoints` (
  `id` bigint(20) NOT NULL,
  `stream` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `game` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'sc2',
  `time` int(11) UNSIGNED DEFAULT NULL,
  `viewers` mediumint(11) UNSIGNED DEFAULT NULL,
  `title` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `events`
--

CREATE TABLE `events` (
  `id` mediumint(8) UNSIGNED NOT NULL,
  `tl_id` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `game` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'sc2',
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `short_name` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `franchise` int(11) UNSIGNED NOT NULL DEFAULT '458',
  `wiki` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `start` int(10) UNSIGNED NOT NULL,
  `end` int(10) UNSIGNED NOT NULL,
  `hidden` tinyint(1) UNSIGNED DEFAULT '1',
  `average` mediumint(8) UNSIGNED DEFAULT '0',
  `peak` mediumint(8) UNSIGNED DEFAULT '0',
  `vh` int(10) UNSIGNED DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `event_streams`
--

CREATE TABLE `event_streams` (
  `id` int(10) UNSIGNED NOT NULL,
  `event` mediumint(8) UNSIGNED NOT NULL,
  `stream` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `start` int(10) UNSIGNED NOT NULL,
  `end` int(10) UNSIGNED NOT NULL,
  `auto` tinyint(1) NOT NULL,
  `viewers` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `franchises`
--

CREATE TABLE `franchises` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `real_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `logo` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tag` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `url` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `twitter` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `twitter_timestamp` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `average` mediumint(8) UNSIGNED DEFAULT '0',
  `peak` mediumint(8) UNSIGNED DEFAULT '0',
  `vh` int(10) UNSIGNED DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `matches`
--

CREATE TABLE `matches` (
  `id` int(10) UNSIGNED NOT NULL,
  `event` mediumint(8) UNSIGNED NOT NULL,
  `match` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `start` int(10) UNSIGNED NOT NULL,
  `end` int(10) UNSIGNED NOT NULL,
  `player1` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `player2` varchar(32) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `notices`
--

CREATE TABLE `notices` (
  `id` int(10) UNSIGNED NOT NULL,
  `text` text COLLATE utf8_unicode_ci NOT NULL,
  `expires` int(10) UNSIGNED NOT NULL,
  `dismissable` tinyint(1) NOT NULL,
  `user` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `old_sessions`
--

CREATE TABLE `old_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `stream` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `game` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'sc2',
  `start` int(10) UNSIGNED NOT NULL,
  `end` int(10) UNSIGNED NOT NULL,
  `time` int(10) UNSIGNED NOT NULL,
  `average` mediumint(8) UNSIGNED DEFAULT '0',
  `peak` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `vh` int(10) UNSIGNED DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `old_sessions_data`
--

CREATE TABLE `old_sessions_data` (
  `sessionid` int(10) UNSIGNED NOT NULL,
  `datapoints` mediumtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `interpolated` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `overall`
--

CREATE TABLE `overall` (
  `id` int(10) UNSIGNED NOT NULL,
  `game` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `day` int(10) UNSIGNED NOT NULL,
  `month` int(10) UNSIGNED NOT NULL,
  `year` int(10) UNSIGNED NOT NULL,
  `week` int(10) UNSIGNED NOT NULL,
  `average` int(10) UNSIGNED NOT NULL,
  `peak` int(10) UNSIGNED NOT NULL,
  `vh` int(10) UNSIGNED NOT NULL,
  `average_eventless` int(10) UNSIGNED NOT NULL,
  `peak_eventless` int(10) UNSIGNED NOT NULL,
  `vh_eventless` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `ranking_alltime`
--

CREATE TABLE `ranking_alltime` (
  `id` int(10) UNSIGNED NOT NULL,
  `game` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `stream` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `average` int(10) UNSIGNED NOT NULL,
  `peak` int(10) UNSIGNED NOT NULL,
  `vh` int(10) UNSIGNED NOT NULL,
  `time` int(10) UNSIGNED NOT NULL,
  `last_session` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `ranking_month`
--

CREATE TABLE `ranking_month` (
  `id` int(10) UNSIGNED NOT NULL,
  `game` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `month` enum('1','2','3','4','5','6','7','8','9','10','11','12') COLLATE utf8_unicode_ci NOT NULL,
  `year` year(4) NOT NULL,
  `rank` int(3) UNSIGNED NOT NULL,
  `stream` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `average` int(10) UNSIGNED NOT NULL,
  `peak` int(10) UNSIGNED NOT NULL,
  `vh` int(10) UNSIGNED NOT NULL,
  `time` int(10) UNSIGNED NOT NULL,
  `delta` int(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `ranking_month_e`
--

CREATE TABLE `ranking_month_e` (
  `id` int(10) UNSIGNED NOT NULL,
  `game` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `month` enum('1','2','3','4','5','6','7','8','9','10','11','12') COLLATE utf8_unicode_ci NOT NULL,
  `year` year(4) NOT NULL,
  `rank` int(3) UNSIGNED NOT NULL,
  `stream` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `average` int(10) UNSIGNED NOT NULL,
  `peak` int(10) UNSIGNED NOT NULL,
  `vh` int(10) UNSIGNED NOT NULL,
  `time` int(10) UNSIGNED NOT NULL,
  `delta` int(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `ranking_month_event`
--

CREATE TABLE `ranking_month_event` (
  `id` int(10) UNSIGNED NOT NULL,
  `game` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `month` enum('1','2','3','4','5','6','7','8','9','10','11','12') COLLATE utf8_unicode_ci NOT NULL,
  `year` year(4) NOT NULL,
  `rank` int(3) UNSIGNED NOT NULL,
  `event` int(10) UNSIGNED NOT NULL,
  `delta` int(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `ranking_week`
--

CREATE TABLE `ranking_week` (
  `id` int(10) UNSIGNED NOT NULL,
  `game` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `week` enum('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40','41','42','43','44','45','46','47','48','49','50','51','52','53') COLLATE utf8_unicode_ci NOT NULL,
  `year` year(4) NOT NULL,
  `rank` int(3) UNSIGNED NOT NULL,
  `stream` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `average` int(10) UNSIGNED NOT NULL,
  `peak` int(10) UNSIGNED NOT NULL,
  `vh` int(10) UNSIGNED NOT NULL,
  `time` int(10) UNSIGNED NOT NULL,
  `delta` int(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `ranking_week_e`
--

CREATE TABLE `ranking_week_e` (
  `id` int(10) UNSIGNED NOT NULL,
  `game` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `week` enum('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40','41','42','43','44','45','46','47','48','49','50','51','52','53') COLLATE utf8_unicode_ci NOT NULL,
  `year` year(4) NOT NULL,
  `rank` int(3) UNSIGNED NOT NULL,
  `stream` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `average` int(10) UNSIGNED NOT NULL,
  `peak` int(10) UNSIGNED NOT NULL,
  `vh` int(10) UNSIGNED NOT NULL,
  `time` int(10) UNSIGNED NOT NULL,
  `delta` int(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `ranking_week_event`
--

CREATE TABLE `ranking_week_event` (
  `id` int(10) UNSIGNED NOT NULL,
  `game` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `week` enum('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40','41','42','43','44','45','46','47','48','49','50','51','52','53') COLLATE utf8_unicode_ci NOT NULL,
  `year` year(4) NOT NULL,
  `rank` int(3) UNSIGNED NOT NULL,
  `event` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `sessions`
--

CREATE TABLE `sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `stream` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `game` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'sc2',
  `start` int(10) UNSIGNED NOT NULL,
  `end` int(10) UNSIGNED NOT NULL,
  `time` int(10) UNSIGNED NOT NULL,
  `average` mediumint(8) UNSIGNED DEFAULT '0',
  `peak` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `vh` int(10) UNSIGNED DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `sessions_data`
--

CREATE TABLE `sessions_data` (
  `sessionid` int(10) UNSIGNED NOT NULL,
  `datapoints` mediumtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `interpolated` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `streams`
--

CREATE TABLE `streams` (
  `id` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `provider` enum('twitch','azubu','dailymotion','livestream','goodgame','hitbox','youtube','dingit','gaminglive','afreeca','dota2','douyu','streamme','mixer') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'twitch',
  `remote_id` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `last_game` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `tl_id` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `tl_featured` int(1) UNSIGNED NOT NULL DEFAULT '0',
  `real_name` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `language` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `wiki` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `twitter` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `notable` tinyint(1) NOT NULL DEFAULT '0',
  `team` int(10) UNSIGNED NOT NULL DEFAULT '74',
  `avatar` varchar(256) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `player` int(1) UNSIGNED NOT NULL DEFAULT '1',
  `last_crawl` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `last_seen` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `last_acc` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `last_status` varchar(256) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `teams`
--

CREATE TABLE `teams` (
  `id` int(10) UNSIGNED NOT NULL,
  `team` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `wiki` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `url` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `logo` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `handle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `level` int(1) UNSIGNED NOT NULL,
  `stream` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `last_seen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `usersessions`
--

CREATE TABLE `usersessions` (
  `id` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `user` int(10) UNSIGNED NOT NULL,
  `browser` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `ip` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `audience`
--
ALTER TABLE `audience`
  ADD KEY `time` (`time`),
  ADD KEY `game` (`game`);

--
-- Indexen voor tabel `datapoints`
--
ALTER TABLE `datapoints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stream` (`stream`),
  ADD KEY `time` (`time`),
  ADD KEY `stream_time` (`stream`,`time`);

--
-- Indexen voor tabel `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `brand` (`franchise`,`start`,`hidden`,`vh`),
  ADD KEY `franchise` (`franchise`);

--
-- Indexen voor tabel `event_streams`
--
ALTER TABLE `event_streams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event` (`event`),
  ADD KEY `stream` (`stream`);

--
-- Indexen voor tabel `franchises`
--
ALTER TABLE `franchises`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tag` (`tag`,`vh`);

--
-- Indexen voor tabel `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event` (`event`,`player1`,`player2`);

--
-- Indexen voor tabel `notices`
--
ALTER TABLE `notices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user` (`user`);

--
-- Indexen voor tabel `old_sessions`
--
ALTER TABLE `old_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stream` (`stream`),
  ADD KEY `start / id` (`stream`,`start`),
  ADD KEY `end / id` (`stream`,`end`),
  ADD KEY `stream / start / end` (`stream`,`start`,`end`),
  ADD KEY `start` (`start`),
  ADD KEY `end` (`end`),
  ADD KEY `game` (`game`);

--
-- Indexen voor tabel `old_sessions_data`
--
ALTER TABLE `old_sessions_data`
  ADD PRIMARY KEY (`sessionid`);

--
-- Indexen voor tabel `overall`
--
ALTER TABLE `overall`
  ADD PRIMARY KEY (`id`),
  ADD KEY `day` (`day`,`month`,`year`,`week`),
  ADD KEY `month` (`month`),
  ADD KEY `year` (`year`),
  ADD KEY `week` (`week`),
  ADD KEY `game` (`game`);

--
-- Indexen voor tabel `ranking_alltime`
--
ALTER TABLE `ranking_alltime`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stream` (`stream`),
  ADD KEY `vh` (`vh`),
  ADD KEY `average` (`average`);

--
-- Indexen voor tabel `ranking_month`
--
ALTER TABLE `ranking_month`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stream` (`stream`);

--
-- Indexen voor tabel `ranking_month_e`
--
ALTER TABLE `ranking_month_e`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stream` (`stream`);

--
-- Indexen voor tabel `ranking_month_event`
--
ALTER TABLE `ranking_month_event`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stream` (`event`);

--
-- Indexen voor tabel `ranking_week`
--
ALTER TABLE `ranking_week`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stream` (`stream`),
  ADD KEY `rank` (`rank`),
  ADD KEY `vh` (`vh`),
  ADD KEY `average` (`average`),
  ADD KEY `date` (`week`,`year`),
  ADD KEY `rank dated` (`week`,`year`,`rank`);

--
-- Indexen voor tabel `ranking_week_e`
--
ALTER TABLE `ranking_week_e`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stream` (`stream`),
  ADD KEY `rank` (`rank`),
  ADD KEY `vh` (`vh`),
  ADD KEY `average` (`average`),
  ADD KEY `date` (`week`,`year`),
  ADD KEY `rank dated` (`week`,`year`,`rank`);

--
-- Indexen voor tabel `ranking_week_event`
--
ALTER TABLE `ranking_week_event`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stream` (`event`),
  ADD KEY `rank` (`rank`),
  ADD KEY `date` (`week`,`year`),
  ADD KEY `rank dated` (`week`,`year`,`rank`);

--
-- Indexen voor tabel `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stream` (`stream`),
  ADD KEY `start / id` (`stream`,`start`),
  ADD KEY `end / id` (`stream`,`end`),
  ADD KEY `stream / start / end` (`stream`,`start`,`end`),
  ADD KEY `start` (`start`),
  ADD KEY `end` (`end`),
  ADD KEY `game` (`game`);

--
-- Indexen voor tabel `sessions_data`
--
ALTER TABLE `sessions_data`
  ADD PRIMARY KEY (`sessionid`);

--
-- Indexen voor tabel `streams`
--
ALTER TABLE `streams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `last_seen` (`last_seen`),
  ADD KEY `team` (`team`),
  ADD KEY `last_crawl` (`last_crawl`),
  ADD KEY `tl_featured` (`tl_featured`),
  ADD KEY `provider` (`provider`),
  ADD KEY `last_crawl_2` (`last_crawl`,`last_seen`),
  ADD KEY `last_game` (`last_game`),
  ADD KEY `real_name` (`real_name`);

--
-- Indexen voor tabel `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `team` (`team`);

--
-- Indexen voor tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `level` (`level`),
  ADD KEY `stream` (`stream`);

--
-- Indexen voor tabel `usersessions`
--
ALTER TABLE `usersessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user` (`user`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `datapoints`
--
ALTER TABLE `datapoints`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `events`
--
ALTER TABLE `events`
  MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `event_streams`
--
ALTER TABLE `event_streams`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `franchises`
--
ALTER TABLE `franchises`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `matches`
--
ALTER TABLE `matches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `notices`
--
ALTER TABLE `notices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `old_sessions`
--
ALTER TABLE `old_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `overall`
--
ALTER TABLE `overall`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `ranking_alltime`
--
ALTER TABLE `ranking_alltime`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `ranking_month`
--
ALTER TABLE `ranking_month`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `ranking_month_e`
--
ALTER TABLE `ranking_month_e`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `ranking_month_event`
--
ALTER TABLE `ranking_month_event`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `ranking_week`
--
ALTER TABLE `ranking_week`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `ranking_week_e`
--
ALTER TABLE `ranking_week_e`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `ranking_week_event`
--
ALTER TABLE `ranking_week_event`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Beperkingen voor geëxporteerde tabellen
--

--
-- Beperkingen voor tabel `datapoints`
--
ALTER TABLE `datapoints`
  ADD CONSTRAINT `datapoints_ibfk_1` FOREIGN KEY (`stream`) REFERENCES `streams` (`id`);

--
-- Beperkingen voor tabel `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`franchise`) REFERENCES `franchises` (`id`);

--
-- Beperkingen voor tabel `event_streams`
--
ALTER TABLE `event_streams`
  ADD CONSTRAINT `event_streams_ibfk_1` FOREIGN KEY (`stream`) REFERENCES `streams` (`id`);

--
-- Beperkingen voor tabel `notices`
--
ALTER TABLE `notices`
  ADD CONSTRAINT `notices_ibfk_1` FOREIGN KEY (`user`) REFERENCES `users` (`id`);

--
-- Beperkingen voor tabel `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`stream`) REFERENCES `streams` (`id`);

--
-- Beperkingen voor tabel `usersessions`
--
ALTER TABLE `usersessions`
  ADD CONSTRAINT `usersessions_ibfk_1` FOREIGN KEY (`user`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
