-- phpMyAdmin SQL Dump
-- version 4.0.10deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Apr 26, 2017 at 03:45 PM
-- Server version: 5.5.53-0ubuntu0.14.04.1
-- PHP Version: 5.5.9-1ubuntu4.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

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
-- Table structure for table `ext_power_log`
--

CREATE TABLE IF NOT EXISTS `ext_power_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `state` enum('on','off') NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Наличие внешнего питания' AUTO_INCREMENT=1 ;

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Сработки сигнализации' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `guard_states`
--

CREATE TABLE IF NOT EXISTS `guard_states` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `state` enum('sleep','ready') DEFAULT NULL,
  `method` enum('site','sms','remote','cli') DEFAULT NULL,
  `user_id` bigint(20) NOT NULL,
  `ignore_sensors` varchar(256) NOT NULL COMMENT 'Список ID сенсоров через запятую, которые необходимо игнорировать',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Input ports change value actions' AUTO_INCREMENT=1 ;

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `sensors`
--

CREATE TABLE IF NOT EXISTS `sensors` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `port` tinyint(4) NOT NULL,
  `normal_state` tinyint(1) NOT NULL DEFAULT '0',
  `alarm_time` int(11) NOT NULL COMMENT 'Время вопля сирены в секундах',
  `run_lighter` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=7 ;

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `phones` varchar(256) NOT NULL,
  `telegram_id` bigint(20) NOT NULL,
  `guard_switch` tinyint(1) NOT NULL DEFAULT '0',
  `guard_alarm` tinyint(1) NOT NULL DEFAULT '0',
  `sms_observer` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Получает все уведомления о изменениях в системе',
  `serv_control` tinyint(1) NOT NULL DEFAULT '0',
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=5 ;
--
-- Constraints for dumped tables
--

--
-- Dumping data for table `sensors`
--

INSERT INTO `sensors` (`id`, `name`, `port`, `normal_state`, `alarm_time`, `run_lighter`) VALUES
(1, 'Датчик объема передний', 2, 1, 30, 1),
(2, 'Корпус переднего датчика объема', 3, 0, 180, 0),
(3, 'Датчик двери кунга', 9, 1, 300, 1),
(4, 'Датчик дверцы ВРУ', 10, 1, 300, 0),
(5, 'Датчик объема задний', 4, 1, 30, 1),
(6, 'Корпус заднего датчика объема', 5, 0, 300, 0);


--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `phones`, `telegram_id`, `guard_switch`, `guard_alarm`, `sms_observer`, `serv_control`, `enabled`) VALUES
(1, 'Михаил', '+375295051024,+375296091024', 186579253, 1, 1, 1, 1, 1),
(2, 'Вероника', '+375295365072', 0, 1, 1, 0, 1, 1),
(3, 'Игорь', '+375293531402', 0, 1, 1, 0, 0, 1),
(4, 'Мама', '+375291651456', 0, 0, 1, 0, 0, 1);


--
-- Table structure for table `telegram_msg`
--

CREATE TABLE IF NOT EXISTS `telegram_msg` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `update_id` bigint(20) NOT NULL COMMENT 'ID телеграмовского запроса UPDATE',
  `msg_id` bigint(20) NOT NULL COMMENT 'ID телеграмовского сообщения',
  `date` bigint(20) DEFAULT NULL COMMENT 'Дата сообщения',
  `from_name` varchar(64) CHARACTER SET latin1 NOT NULL,
  `from_id` bigint(20) NOT NULL,
  `chat_name` varchar(128) CHARACTER SET latin1 NOT NULL,
  `chat_id` bigint(20) NOT NULL,
  `chat_type` varchar(16) CHARACTER SET latin1 NOT NULL,
  `text` text CHARACTER SET latin1 NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


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
