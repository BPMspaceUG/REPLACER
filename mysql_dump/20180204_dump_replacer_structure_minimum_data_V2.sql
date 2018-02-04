CREATE DATABASE  IF NOT EXISTS `bpmspace_replacer_v2` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `bpmspace_replacer_v2`;
-- MySQL dump 10.13  Distrib 5.7.17, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: bpmspace_replacer_v2
-- ------------------------------------------------------
-- Server version	5.5.59-0+deb8u1

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
-- Table structure for table `replacer`
--

DROP TABLE IF EXISTS `replacer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `replacer` (
  `replacer_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `replacer_pattern` varchar(256) NOT NULL,
  `replacer_language_en` longtext,
  `replacer_language_de` longtext,
  `state_id` bigint(20) DEFAULT '1',
  PRIMARY KEY (`replacer_id`),
  KEY `state_id_810b48a6` (`state_id`),
  CONSTRAINT `state_id_810b48a6` FOREIGN KEY (`state_id`) REFERENCES `state` (`state_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=851 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;


--
-- Table structure for table `replacer_tag`
--

DROP TABLE IF EXISTS `replacer_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `replacer_tag` (
  `replacer_tag_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `replacer_tag_name` varchar(45) DEFAULT NULL,
  `state_id` bigint(20) DEFAULT '4',
  PRIMARY KEY (`replacer_tag_id`),
  KEY `state_id_e359d61e` (`state_id`),
  CONSTRAINT `state_id_e359d61e` FOREIGN KEY (`state_id`) REFERENCES `state` (`state_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `replacer_tag_id`
--

DROP TABLE IF EXISTS `replacer_tag_id`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `replacer_tag_id` (
  `replacer_tag_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `replacer_id` bigint(20) DEFAULT NULL,
  `tag_id` bigint(20) DEFAULT NULL,
  `state_id` bigint(20) DEFAULT '7',
  PRIMARY KEY (`replacer_tag_id`),
  KEY `replacer_id_fk1_idx` (`replacer_id`),
  KEY `replacer_tag_id_idx` (`tag_id`),
  KEY `state_id_635a6c15` (`state_id`),
  CONSTRAINT `replacer_id_fk1` FOREIGN KEY (`replacer_id`) REFERENCES `replacer` (`replacer_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `replacer_tag_id` FOREIGN KEY (`tag_id`) REFERENCES `replacer_tag` (`replacer_tag_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `state_id_635a6c15` FOREIGN KEY (`state_id`) REFERENCES `state` (`state_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `state`
--

DROP TABLE IF EXISTS `state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `state` (
  `state_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(45) DEFAULT NULL,
  `form_data` longtext,
  `entrypoint` tinyint(1) NOT NULL DEFAULT '0',
  `statemachine_id` bigint(20) NOT NULL DEFAULT '1',
  PRIMARY KEY (`state_id`),
  KEY `state_machine_id_fk` (`statemachine_id`),
  CONSTRAINT `state_machine_id_fk` FOREIGN KEY (`statemachine_id`) REFERENCES `state_machines` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `state`
--

LOCK TABLES `state` WRITE;
/*!40000 ALTER TABLE `state` DISABLE KEYS */;
INSERT INTO `state` VALUES (1,'new','{\"replacer_id\":\"RO\",\"replacer_pattern\":\"RO\",\"replacer_language_en\":\"RO\",\"replacer_language_de\":\"RO\"}',1,1),(2,'active','{\"replacer_id\":\"RO\",\"replacer_pattern\":\"RO\",\"replacer_language_en\":\"RO\",\"replacer_language_de\":\"RO\"}',0,1),(3,'inactive','{\"replacer_id\":\"RO\",\"replacer_pattern\":\"RO\",\"replacer_language_en\":\"RO\",\"replacer_language_de\":\"RO\"}',0,1),(4,'new','{\"replacer_tag_id\":\"RO\",\"replacer_tag_name\":\"RO\"}',1,2),(5,'active','{\"replacer_tag_id\":\"RO\",\"replacer_tag_name\":\"RO\"}',0,2),(6,'inactive','{\"replacer_tag_id\":\"RO\",\"replacer_tag_name\":\"RO\"}',0,2),(7,'new','{\"replacer_tag_id\":\"RO\",\"replacer_id\":\"RO\",\"tag_id\":\"RO\"}',1,3),(8,'active','{\"replacer_tag_id\":\"RO\",\"replacer_id\":\"RO\",\"tag_id\":\"RO\"}',0,3),(9,'inactive','{\"replacer_tag_id\":\"RO\",\"replacer_id\":\"RO\",\"tag_id\":\"RO\"}',0,3);
/*!40000 ALTER TABLE `state` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `state_machines`
--

DROP TABLE IF EXISTS `state_machines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `state_machines` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `tablename` varchar(45) DEFAULT NULL,
  `transition_script` longtext,
  `form_data` longtext,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `state_machines`
--

LOCK TABLES `state_machines` WRITE;
/*!40000 ALTER TABLE `state_machines` DISABLE KEYS */;
INSERT INTO `state_machines` VALUES (1,'replacer',NULL,'{\"replacer_id\":\"RO\",\"replacer_pattern\":\"RO\",\"replacer_language_en\":\"RO\",\"replacer_language_de\":\"RO\"}'),(2,'replacer_tag',NULL,'{\"replacer_tag_id\":\"RO\",\"replacer_tag_name\":\"RO\"}'),(3,'replacer_tag_id',NULL,'{\"replacer_tag_id\":\"RO\",\"replacer_id\":\"RO\",\"tag_id\":\"RO\"}');
/*!40000 ALTER TABLE `state_machines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `state_rules`
--

DROP TABLE IF EXISTS `state_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `state_rules` (
  `state_rules_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `state_id_FROM` bigint(20) NOT NULL,
  `state_id_TO` bigint(20) NOT NULL,
  `transition_script` longtext,
  PRIMARY KEY (`state_rules_id`),
  KEY `state_id_fk1_idx` (`state_id_FROM`),
  KEY `state_id_fk_to_idx` (`state_id_TO`),
  CONSTRAINT `state_id_fk_from` FOREIGN KEY (`state_id_FROM`) REFERENCES `state` (`state_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `state_id_fk_to` FOREIGN KEY (`state_id_TO`) REFERENCES `state` (`state_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `state_rules`
--

LOCK TABLES `state_rules` WRITE;
/*!40000 ALTER TABLE `state_rules` DISABLE KEYS */;
INSERT INTO `state_rules` VALUES (1,1,1,''),(2,2,2,''),(3,3,3,''),(4,1,2,''),(5,2,1,''),(6,2,3,''),(7,3,2,''),(8,4,4,''),(9,5,5,''),(10,6,6,''),(11,4,5,''),(12,5,4,''),(13,5,6,''),(14,6,5,''),(15,7,7,''),(16,8,8,''),(17,9,9,''),(18,7,8,''),(19,8,7,''),(20,8,9,''),(21,9,8,'');
