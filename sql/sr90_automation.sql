-- phpMyAdmin SQL Dump
-- version 4.0.10deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Mar 05, 2017 at 02:57 PM
-- Server version: 5.5.49-0ubuntu0.14.04.1
-- PHP Version: 5.5.9-1ubuntu4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `sr90_automation`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_logs`
--

CREATE TABLE IF NOT EXISTS `app_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `text` text NOT NULL,
  `type` enum('urgent','error','warning','notice') NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='События в приложении' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `blocking_sensors`
--

CREATE TABLE IF NOT EXISTS `blocking_sensors` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `sense_id` bigint(20) NOT NULL,
  `mode` enum('lock','unlock') NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sense_id` (`sense_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `day_night`
--

CREATE TABLE IF NOT EXISTS `day_night` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `state` enum('day','night') NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `guard_alarms`
--

CREATE TABLE IF NOT EXISTS `guard_alarms` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `action_id` bigint(20) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `action_id` (`action_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Сработки сигнализации' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `guard_states`
--

CREATE TABLE IF NOT EXISTS `guard_states` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `state` enum('sleep','ready') DEFAULT NULL,
  `method` enum('site','sms','remote','cli') DEFAULT NULL,
  `ignore_sensors` varchar(256) NOT NULL COMMENT 'Список ID сенсоров через запятую, которые необходимо игнорировать',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `incomming_sms`
--

CREATE TABLE IF NOT EXISTS `incomming_sms` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `text` text NOT NULL,
  `received_date` datetime NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `io_input_actions`
--

CREATE TABLE IF NOT EXISTS `io_input_actions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `port` tinyint(4) NOT NULL,
  `state` tinyint(4) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Input ports change value actions' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `io_output_actions`
--

CREATE TABLE IF NOT EXISTS `io_output_actions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `port` tinyint(4) NOT NULL,
  `state` tinyint(4) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `sensors`
--

CREATE TABLE IF NOT EXISTS `sensors` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `port` tinyint(4) NOT NULL,
  `normal_state` tinyint(1) NOT NULL DEFAULT '0',
  `run_lighter` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `sensors`
--

INSERT INTO `sensors` (`id`, `name`, `port`, `normal_state`, `run_lighter`) VALUES
(1, 'Датчик объема 1', 2, 1, 1),
(2, 'Корпус датчика объема 1', 3, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `sensor_actions`
--

CREATE TABLE IF NOT EXISTS `sensor_actions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `sense_id` bigint(20) NOT NULL,
  `state` enum('normal','action') DEFAULT NULL,
  `guard_state` enum('sleep','ready') DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sense_id` (`sense_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `blocking_sensors`
--
ALTER TABLE `blocking_sensors`
  ADD CONSTRAINT `blocking_sensors_ibfk_1` FOREIGN KEY (`sense_id`) REFERENCES `sensors` (`id`) ON UPDATE NO ACTION;

--
-- Constraints for table `guard_alarms`
--
ALTER TABLE `guard_alarms`
  ADD CONSTRAINT `guard_alarms_ibfk_1` FOREIGN KEY (`action_id`) REFERENCES `sensor_actions` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table `sensor_actions`
--
ALTER TABLE `sensor_actions`
  ADD CONSTRAINT `sensor_actions_ibfk_1` FOREIGN KEY (`sense_id`) REFERENCES `sensors` (`id`) ON UPDATE NO ACTION;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
