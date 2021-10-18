-- MySQL dump 10.13  Distrib 5.7.21, for Linux (x86_64)
--
-- Host: localhost    Database: sr90_automation
-- ------------------------------------------------------
-- Server version	5.7.21-0ubuntu0.16.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audio_broadcast_messages`
--

DROP TABLE IF EXISTS `audio_broadcast_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audio_broadcast_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `voice` varchar(64) CHARACTER SET utf8 NOT NULL,
  `speed` varchar(4) CHARACTER SET utf8 NOT NULL,
  `message` varchar(255) CHARACTER SET utf8 NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COMMENT='История голосовых сообщений';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `boiler_statistics`
--

DROP TABLE IF EXISTS `boiler_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `boiler_statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `burning_time` bigint(20) NOT NULL COMMENT 'время работы в секундах',
  `fuel_consumption` int(11) NOT NULL COMMENT 'потраченное топливо в милилитрах',
  `ignition_counter` int(11) NOT NULL COMMENT 'Количество включений',
  `return_water_t` float NOT NULL COMMENT 'Средняя температура в радиаторах за сутки',
  `room_t` float NOT NULL COMMENT 'Средняя температура в помещении за сутки',
  `outside_t` float DEFAULT NULL COMMENT 'Средняя температура на улице за сутки',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Статистика по котлу';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ext_power_log`
--

DROP TABLE IF EXISTS `ext_power_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ext_power_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('input','ups') NOT NULL,
  `state` tinyint(1) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guard_alarms`
--

DROP TABLE IF EXISTS `guard_alarms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guard_alarms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zone` varchar(64) CHARACTER SET utf8 NOT NULL,
  `state_id` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=latin1 COMMENT='Таблица сработок сигнализации';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guard_states`
--

DROP TABLE IF EXISTS `guard_states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guard_states` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `state` enum('sleep','ready') DEFAULT NULL,
  `method` enum('site','sms','remote','cli','telegram') DEFAULT NULL,
  `user_id` bigint(20) NOT NULL,
  `ignore_zones` varchar(512) DEFAULT NULL COMMENT 'Список ID зон через запятую, которые необходимо игнорировать',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=391 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `http_server_log`
--

DROP TABLE IF EXISTS `http_server_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `http_server_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `remote_host` varchar(24) NOT NULL,
  `query` varchar(255) NOT NULL,
  `script` varchar(32) NOT NULL,
  `return` text NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `incomming_sms`
--

DROP TABLE IF EXISTS `incomming_sms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incomming_sms` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `text` text NOT NULL,
  `received_date` datetime NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=441 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `io_events`
--

DROP TABLE IF EXISTS `io_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `io_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `port_name` varchar(64) CHARACTER SET utf8 NOT NULL,
  `io_name` varchar(32) CHARACTER SET utf8 NOT NULL,
  `mode` enum('in','out') CHARACTER SET utf8 NOT NULL,
  `port` int(11) NOT NULL,
  `state` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=460 DEFAULT CHARSET=latin1 COMMENT='события от платы ввода вывода';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `locked_zones`
--

DROP TABLE IF EXISTS `locked_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `locked_zones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zone` varchar(64) CHARACTER SET utf8 NOT NULL,
  `mode` enum('lock','unlock') CHARACTER SET utf8 NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `telegram_chats`
--

DROP TABLE IF EXISTS `telegram_chats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `telegram_chats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) NOT NULL,
  `name` varchar(256) NOT NULL,
  `type` enum('admin','messages','alarm') NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COMMENT='Список чатов куда рассылать уведомления';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `telegram_msg`
--

DROP TABLE IF EXISTS `telegram_msg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `telegram_msg` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `update_id` bigint(20) NOT NULL COMMENT 'ID телеграмовского запроса UPDATE',
  `msg_id` bigint(20) NOT NULL COMMENT 'ID телеграмовского сообщения',
  `date` bigint(20) DEFAULT NULL COMMENT 'Дата сообщения',
  `from_name` varchar(64) NOT NULL,
  `from_id` bigint(20) NOT NULL,
  `chat_name` varchar(128) NOT NULL,
  `chat_id` bigint(20) NOT NULL,
  `chat_type` varchar(16) NOT NULL,
  `text` text NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=185 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `termo_sensors_log`
--

DROP TABLE IF EXISTS `termo_sensors_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `termo_sensors_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `io_name` varchar(8) NOT NULL,
  `sensor_name` varchar(20) NOT NULL,
  `temperature` float NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sensor_name_plus_temperature` (`sensor_name`,`temperature`),
  KEY `temperature` (`temperature`),
  KEY `created_plus_sensor_name` (`created`,`sensor_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ups_actions`
--

DROP TABLE IF EXISTS `ups_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ups_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stage` enum('charge1','charge2','charge3','idle','recharging','discarge') NOT NULL,
  `reason` varchar(128) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ups_battery`
--

DROP TABLE IF EXISTS `ups_battery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ups_battery` (
  `voltage` float NOT NULL,
  `current` float NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2021-10-18 18:41:09
