-- phpMyAdmin SQL Dump
-- version 4.0.10deb1
-- http://www.phpmyadmin.net
--
-- Хост: localhost
-- Время создания: Ноя 06 2017 г., 04:14
-- Версия сервера: 5.5.53-0ubuntu0.14.04.1
-- Версия PHP: 5.5.9-1ubuntu4.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- База данных: `sr90_automation`
--

-- --------------------------------------------------------

--
-- Структура таблицы `blocking_zones`
--

CREATE TABLE IF NOT EXISTS `blocking_zones` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `zone_id` bigint(20) NOT NULL,
  `mode` enum('lock','unlock') NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=7 ;

-- --------------------------------------------------------

--
-- Структура таблицы `ext_power_log`
--

CREATE TABLE IF NOT EXISTS `ext_power_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `state` enum('on','off') NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Наличие внешнего питания' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Структура таблицы `guard_actions`
--

CREATE TABLE IF NOT EXISTS `guard_actions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `zone_id` bigint(20) NOT NULL,
  `guard_state` enum('sleep','ready') DEFAULT NULL,
  `alarm` tinyint(1) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Структура таблицы `guard_states`
--

CREATE TABLE IF NOT EXISTS `guard_states` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `state` enum('sleep','ready') DEFAULT NULL,
  `method` enum('site','sms','remote','cli','telegram') DEFAULT NULL,
  `user_id` bigint(20) NOT NULL,
  `ignore_zones` varchar(256) NOT NULL COMMENT 'Список ID зон через запятую, которые необходимо игнорировать',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Структура таблицы `incomming_sms`
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
-- Структура таблицы `io_input_actions`
--

CREATE TABLE IF NOT EXISTS `io_input_actions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `io_name` varchar(64) NOT NULL,
  `port` tinyint(4) NOT NULL,
  `state` tinyint(4) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Input ports change value actions' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Структура таблицы `io_output_actions`
--

CREATE TABLE IF NOT EXISTS `io_output_actions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `io_name` varchar(64) NOT NULL,
  `port` tinyint(4) NOT NULL,
  `state` tinyint(4) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Структура таблицы `telegram_chats`
--

CREATE TABLE IF NOT EXISTS `telegram_chats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) NOT NULL,
  `name` varchar(256) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Список чатов куда рассылать уведомления' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Структура таблицы `telegram_msg`
--

CREATE TABLE IF NOT EXISTS `telegram_msg` (
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Структура таблицы `http_server_log`
--

CREATE TABLE IF NOT EXISTS `http_server_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `remote_host` varchar(24) NOT NULL,
  `query` varchar(255) NOT NULL,
  `script` varchar(32) NOT NULL,
  `return` text NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Структура таблицы `termo_sensors_log`
--

CREATE TABLE IF NOT EXISTS `termo_sensors_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `io_name` varchar(8) NOT NULL,
  `sensor_name` varchar(20) NOT NULL,
  `temperaure` float NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
