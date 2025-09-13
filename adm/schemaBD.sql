/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.13-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: cashcue
-- ------------------------------------------------------
-- Server version	10.11.13-MariaDB-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `broker_account`
--

DROP TABLE IF EXISTS `broker_account`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `broker_account` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `account_type` enum('PEA','CTO','ASSURANCE_VIE','PER','OTHER') NOT NULL DEFAULT 'PEA',
  `currency` char(3) DEFAULT 'EUR',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_number` (`account_number`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `daily_price`
--

DROP TABLE IF EXISTS `daily_price`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_price` (
  `instrument_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `open_price` decimal(12,4) DEFAULT NULL,
  `high_price` decimal(12,4) DEFAULT NULL,
  `low_price` decimal(12,4) DEFAULT NULL,
  `close_price` decimal(12,4) DEFAULT NULL,
  `volume` bigint(20) DEFAULT NULL,
  `pct_change` decimal(6,2) DEFAULT NULL,
  UNIQUE KEY `instrument_id` (`instrument_id`,`date`),
  CONSTRAINT `daily_price_ibfk_1` FOREIGN KEY (`instrument_id`) REFERENCES `instrument` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dividend`
--

DROP TABLE IF EXISTS `dividend`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dividend` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `broker_id` int(11) NOT NULL,
  `instrument_id` int(11) NOT NULL,
  `amount` decimal(12,4) NOT NULL,
  `gross_amount` decimal(12,4) DEFAULT NULL,
  `currency` char(3) DEFAULT 'EUR',
  `payment_date` date NOT NULL,
  `taxes_withheld` decimal(12,4) DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `broker_id` (`broker_id`),
  KEY `instrument_id` (`instrument_id`),
  CONSTRAINT `dividend_ibfk_1` FOREIGN KEY (`broker_id`) REFERENCES `broker_account` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dividend_ibfk_2` FOREIGN KEY (`instrument_id`) REFERENCES `instrument` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `instrument`
--

DROP TABLE IF EXISTS `instrument`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `instrument` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(20) NOT NULL,
  `label` varchar(255) NOT NULL,
  `isin` varchar(20) DEFAULT NULL,
  `type` enum('STOCK','ETF','BOND','FUND','OTHER') DEFAULT 'STOCK',
  `currency` char(3) DEFAULT 'EUR',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `symbol` (`symbol`),
  UNIQUE KEY `isin` (`isin`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `order_transaction`
--

DROP TABLE IF EXISTS `order_transaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_transaction` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `broker_id` int(11) NOT NULL,
  `instrument_id` int(11) NOT NULL,
  `order_type` enum('BUY','SELL') NOT NULL,
  `quantity` decimal(12,4) NOT NULL,
  `price` decimal(12,4) NOT NULL,
  `fees` decimal(12,4) DEFAULT 0.0000,
  `total_cost` decimal(14,2) GENERATED ALWAYS AS (`quantity` * `price` + `fees`) STORED,
  `trade_date` date NOT NULL,
  `settled` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `broker_id` (`broker_id`),
  KEY `instrument_id` (`instrument_id`),
  CONSTRAINT `order_transaction_ibfk_1` FOREIGN KEY (`broker_id`) REFERENCES `broker_account` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_transaction_ibfk_2` FOREIGN KEY (`instrument_id`) REFERENCES `instrument` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `portfolio_snapshot`
--

DROP TABLE IF EXISTS `portfolio_snapshot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `portfolio_snapshot` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `broker_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `total_value` decimal(14,2) NOT NULL,
  `invested_amount` decimal(14,2) NOT NULL,
  `unrealized_pl` decimal(14,2) DEFAULT 0.00,
  `realized_pl` decimal(14,2) DEFAULT 0.00,
  `dividends_received` decimal(14,2) DEFAULT 0.00,
  `cash_balance` decimal(14,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `broker_id` (`broker_id`,`date`),
  CONSTRAINT `portfolio_snapshot_ibfk_1` FOREIGN KEY (`broker_id`) REFERENCES `broker_account` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `realtime_price`
--

DROP TABLE IF EXISTS `realtime_price`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `realtime_price` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `instrument_id` int(11) NOT NULL,
  `price` decimal(12,4) NOT NULL,
  `currency` char(3) DEFAULT 'EUR',
  `captured_at` timestamp NULL DEFAULT current_timestamp(),
  `capital_exchanged_percent` decimal(5,2) DEFAULT NULL COMMENT 'Percentage of capital exchanged (from source HTML)',
  PRIMARY KEY (`id`),
  KEY `instrument_id` (`instrument_id`),
  CONSTRAINT `realtime_price_ibfk_1` FOREIGN KEY (`instrument_id`) REFERENCES `instrument` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=371 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-13 17:20:09
