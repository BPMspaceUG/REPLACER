CREATE DATABASE  IF NOT EXISTS `bpmspace_replacer_v1` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `bpmspace_replacer_v1`;
-- MySQL dump 10.13  Distrib 5.7.9, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: bpmspace_replacer_v1
-- ------------------------------------------------------
-- Server version	5.5.54-0+deb8u1

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
-- Table structure for table `language`
--

DROP TABLE IF EXISTS `replacer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `replacer` (
  `replacer_id` int(11) NOT NULL AUTO_INCREMENT,
  `replacer_pattern` varchar(256) NOT NULL,
  `replacer_language_en` longtext,
  `replacer_language_de` longtext,
  PRIMARY KEY (`replacer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Dump completed on 2017-03-18 13:04:26

INSERT INTO `bpmspace_replacer_v1`.`replacer` (`replacer_pattern`, `replacer_language_en`, `replacer_language_de`) VALUES ('TEST_REPLACE', 'englisch TEST_REPLACE successful', 'deutsch  TEST_REPLACE erfolgreich');

