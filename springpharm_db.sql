-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: springpharm_db
-- ------------------------------------------------------
-- Server version	8.0.45-0ubuntu0.22.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `action_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,'Tizim ishga tushdi','127.0.0.1',NULL,'2026-04-11 09:11:39','system_init','Tizim muvaffaqiyat o\'rnatildi'),(2,1,'Audit yaratildi: AUD-2026-0001','127.0.0.1',NULL,'2026-04-11 09:11:39','audit_created','PharmaTech LLC uchun audit yaratildi'),(3,1,'Foydalanuvchi kirdi: Tizim Administratori','127.0.0.1',NULL,'2026-04-11 09:11:39','user_login','Login muvaffaqiyatli'),(4,2,'Audit holati o\'zgartirildi: in_progress','127.0.0.1',NULL,'2026-04-11 09:11:39','audit_status_changed','AUD-2026-0004 holati o\'zgartirildi'),(5,1,'Bo\'lim qo\'shildi: I - Umumiy talablar','127.0.0.1',NULL,'2026-04-11 09:11:39','section_added','Yangi GMP bo\'limi qo\'shildi'),(6,1,'','92.63.205.165',NULL,'2026-04-12 03:46:14','audit_created','Audit yaratildi: AUD-2026-0009 - asd'),(7,1,'','84.54.70.221',NULL,'2026-04-14 14:18:35','audit_created','Audit yaratildi: AUD-2026-0010 - GMP-0700'),(8,1,'','84.54.70.221',NULL,'2026-04-14 14:19:12','audit_status_changed','Audit AUD-2026-0010: Draft → Jarayonda'),(9,1,'','84.54.70.221',NULL,'2026-04-14 14:24:28','audit_status_changed','Audit AUD-2026-0010: Jarayonda → Tugatilgan'),(10,1,'','84.54.70.221',NULL,'2026-04-14 14:28:38','audit_deleted','Audit o\'chirildi: AUD-2026-0001'),(11,1,'','84.54.70.221',NULL,'2026-04-14 14:42:24','audit_status_changed','Audit AUD-2026-0007: Jarayonda → Tugatilgan'),(12,1,'','84.54.70.221',NULL,'2026-04-14 14:42:28','audit_status_changed','Audit AUD-2026-0005: Jarayonda → Tugatilgan'),(13,1,'','84.54.70.221',NULL,'2026-04-14 14:42:32','audit_deleted','Audit o\'chirildi: AUD-2026-0009'),(14,1,'','84.54.70.221',NULL,'2026-04-14 14:42:35','audit_deleted','Audit o\'chirildi: AUD-2026-0008'),(15,1,'','84.54.70.221',NULL,'2026-04-14 14:44:07','audit_deleted','Audit o\'chirildi: AUD-2026-0003'),(16,1,'','84.54.70.221',NULL,'2026-04-14 14:44:21','audit_deleted','Audit o\'chirildi: AUD-2026-0004'),(17,1,'','84.54.70.221',NULL,'2026-04-14 14:44:26','audit_deleted','Audit o\'chirildi: AUD-2026-0005'),(18,1,'','84.54.70.221',NULL,'2026-04-14 14:44:42','audit_deleted','Audit o\'chirildi: AUD-2026-0006'),(25,1,'',NULL,NULL,'2026-04-27 13:45:06','login','Administrator tizimga kirdi'),(26,2,'',NULL,NULL,'2026-04-27 13:45:06','module_assigned','Sevara Rahimovaga GMP-001 moduli biriktirildi');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_answers`
--

DROP TABLE IF EXISTS `audit_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_answers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `audit_id` int NOT NULL,
  `question_id` int NOT NULL,
  `auditor_id` int NOT NULL,
  `answer` enum('ha','yoq','na') DEFAULT 'na',
  `score` decimal(5,2) DEFAULT '0.00',
  `comment` text,
  `image_path` varchar(255) DEFAULT NULL,
  `notes` text,
  `answered_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_answer` (`audit_id`,`question_id`),
  KEY `question_id` (`question_id`),
  KEY `auditor_id` (`auditor_id`),
  CONSTRAINT `audit_answers_ibfk_1` FOREIGN KEY (`audit_id`) REFERENCES `audits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `audit_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `checklist_questions` (`id`),
  CONSTRAINT `audit_answers_ibfk_3` FOREIGN KEY (`auditor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=252 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_answers`
--

LOCK TABLES `audit_answers` WRITE;
/*!40000 ALTER TABLE `audit_answers` DISABLE KEYS */;
INSERT INTO `audit_answers` VALUES (209,42,21,1,'ha',1.00,'Bu hujjat ta\'sdiqlanmagan','uploads/answers/1777018014_azitab-500-78299.jpg',NULL,'2026-04-24 08:06:54'),(212,42,1,1,'yoq',0.00,'mavjud emas',NULL,NULL,'2026-04-24 08:07:11'),(214,42,2,1,'na',0.00,'Dastuda ko\'rsatilmagan',NULL,NULL,'2026-04-24 08:07:24'),(215,42,3,1,'ha',3.00,'Samarali ishlamoqda',NULL,NULL,'2026-04-24 08:07:37'),(216,42,7,8,'ha',2.00,'Ishlab chiqarish maydoni yetarli miqdorda',NULL,NULL,'2026-04-24 08:09:14'),(217,42,8,8,'yoq',0.00,NULL,NULL,NULL,'2026-04-24 08:10:24'),(218,42,9,8,'na',0.00,'Dasturda koʻrsatilmagan',NULL,NULL,'2026-04-24 08:10:58'),(219,42,4,19,'yoq',0.00,NULL,NULL,NULL,'2026-04-24 08:14:30'),(220,42,5,19,'ha',1.50,'ta\'luqli emas',NULL,NULL,'2026-04-24 08:15:07'),(221,42,6,19,'na',0.00,'dasturda ko\'rsatilmagan',NULL,NULL,'2026-04-24 08:15:29'),(222,43,21,1,'ha',1.00,'Qo\'llanma mavjud','uploads/answers/1777060314_Bobur.jpg',NULL,'2026-04-24 19:51:54'),(224,43,1,1,'yoq',0.00,NULL,NULL,NULL,'2026-04-24 19:52:04'),(225,43,2,1,'ha',2.00,'dfso\'sdfsdo\'','uploads/answers/1777060351_photo_2024-02-05_13-47-06.jpg',NULL,'2026-04-24 19:52:31'),(227,43,3,1,'ha',3.00,'albatta ideal ishlamoqda',NULL,NULL,'2026-04-24 19:52:45'),(228,44,21,1,'ha',1.00,'Koʻrsatilgan hujjatlar bor','uploads/answers/1777061111_3013.jpg',NULL,'2026-04-24 20:05:11'),(230,44,1,1,'yoq',0.00,'GMP 5.2.1 hujjat mavjud emas',NULL,NULL,'2026-04-24 20:05:42'),(232,44,2,1,'na',0.00,'',NULL,NULL,'2026-04-24 20:05:49'),(233,44,3,1,'yoq',0.00,NULL,NULL,NULL,'2026-04-24 20:05:54'),(234,45,21,1,'yoq',0.00,'Mavjud emas',NULL,NULL,'2026-04-24 20:07:34'),(236,45,1,1,'yoq',0.00,'Bu hujjat yoʻq',NULL,NULL,'2026-04-24 20:07:48'),(238,45,2,1,'ha',2.00,'Zoʻr',NULL,NULL,'2026-04-24 20:07:54'),(239,45,3,1,'na',0.00,'',NULL,NULL,'2026-04-24 20:08:01'),(246,47,21,8,'ha',1.00,'QC da bor','uploads/answers/1777062533_3013.jpg',NULL,'2026-04-24 20:28:53'),(247,47,1,8,'yoq',0.00,'Yoʻq mavjud emas',NULL,NULL,'2026-04-24 20:29:08'),(249,47,2,8,'na',0.00,'Dasturda koʻrsatilmagan',NULL,NULL,'2026-04-24 20:29:18'),(250,47,3,8,'yoq',0.00,'GMP 4.45.5',NULL,NULL,'2026-04-24 20:29:36');
/*!40000 ALTER TABLE `audit_answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_assignments`
--

DROP TABLE IF EXISTS `audit_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `audit_id` int NOT NULL,
  `auditor_id` int NOT NULL,
  `section_id` int NOT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `assigned_by` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_id` (`audit_id`),
  KEY `auditor_id` (`auditor_id`),
  KEY `section_id` (`section_id`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `audit_assignments_ibfk_1` FOREIGN KEY (`audit_id`) REFERENCES `audits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `audit_assignments_ibfk_2` FOREIGN KEY (`auditor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `audit_assignments_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `gmp_sections` (`id`),
  CONSTRAINT `audit_assignments_ibfk_4` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=281 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_assignments`
--

LOCK TABLES `audit_assignments` WRITE;
/*!40000 ALTER TABLE `audit_assignments` DISABLE KEYS */;
INSERT INTO `audit_assignments` VALUES (222,42,19,2,'pending',1,'2026-04-24 08:06:13'),(223,42,8,3,'pending',1,'2026-04-24 08:06:13'),(271,47,8,1,'pending',1,'2026-04-24 20:26:27');
/*!40000 ALTER TABLE `audit_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_signatures`
--

DROP TABLE IF EXISTS `audit_signatures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_signatures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `audit_id` int NOT NULL,
  `user_id` int NOT NULL,
  `signed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sign` (`audit_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_signatures_ibfk_1` FOREIGN KEY (`audit_id`) REFERENCES `audits` (`id`),
  CONSTRAINT `audit_signatures_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_signatures`
--

LOCK TABLES `audit_signatures` WRITE;
/*!40000 ALTER TABLE `audit_signatures` DISABLE KEYS */;
INSERT INTO `audit_signatures` VALUES (1,42,1,'2026-04-25 00:29:21'),(2,45,1,'2026-04-25 00:30:05'),(3,47,1,'2026-04-25 00:32:10');
/*!40000 ALTER TABLE `audit_signatures` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_trail`
--

DROP TABLE IF EXISTS `audit_trail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_trail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_trail`
--

LOCK TABLES `audit_trail` WRITE;
/*!40000 ALTER TABLE `audit_trail` DISABLE KEYS */;
INSERT INTO `audit_trail` VALUES (1,1,'AUDIT_STATUS_CHANGED','Audit ID: 3 -> completed','127.0.0.1','2026-04-11 01:50:29'),(2,1,'AUDIT_CREATED','Audit yaratildi: AUD-2026-0004','127.0.0.1','2026-04-11 02:22:17'),(3,1,'AUDIT_STATUS_CHANGED','Audit ID: 4 -> in_progress','127.0.0.1','2026-04-11 02:22:37'),(4,1,'audit_status_changed','Audit AUD-2026-0004: Jarayonda → Tugatilgan','127.0.0.1','2026-04-11 09:09:05'),(5,1,'audit_created','Audit yaratildi: AUD-2026-0005 - aaaaaa','127.0.0.1','2026-04-11 10:01:07'),(6,1,'user_edited','Foydalanuvchi yangilandi: a (ID: 2)','127.0.0.1','2026-04-11 10:01:40'),(7,1,'user_added','Yangi foydalanuvchi qo\'shildi: AUDITOR (bosh_auditor)','213.230.111.91','2026-04-11 16:28:33'),(8,1,'user_added','Yangi foydalanuvchi qo\'shildi: Auditor (auditor)','213.230.111.91','2026-04-11 17:20:47'),(9,1,'user_added','Yangi foydalanuvchi qo\'shildi: test3 (viewer)','213.230.111.91','2026-04-11 17:30:32'),(10,1,'audit_created','Audit yaratildi: AUD-2026-0006 - GMP-2025-004','213.230.80.81','2026-04-11 17:58:07'),(11,1,'audit_status_changed','Audit AUD-2026-0006: Draft → Jarayonda','213.230.80.81','2026-04-11 17:58:24'),(12,1,'section_added','Yangi bo\'lim qo\'shildi: X - QA','213.230.80.81','2026-04-11 18:11:39'),(13,1,'question_added','Savol qo\'shildi (Bo\'lim ID: 10)','213.230.80.81','2026-04-11 18:12:12'),(14,1,'user_added','Yangi foydalanuvchi qo\'shildi: Najmiddinov Ahadjon (auditor)','213.230.80.81','2026-04-11 18:14:28'),(15,1,'audit_status_changed','Audit AUD-2026-0006: Jarayonda → Tugatilgan','213.230.80.81','2026-04-11 18:14:56'),(16,1,'audit_status_changed','Audit AUD-2026-0005: Draft → Jarayonda','213.230.80.81','2026-04-11 18:15:10'),(17,1,'audit_created','Audit yaratildi: AUD-2026-0007 - QC xodim uchun','213.230.80.81','2026-04-11 18:15:36'),(18,1,'audit_status_changed','Audit AUD-2026-0007: Draft → Jarayonda','213.230.80.81','2026-04-11 18:17:15'),(19,1,'audit_created','Audit yaratildi: AUD-2026-0008 - asdsad','92.63.205.165','2026-04-12 03:26:13'),(20,1,'question_deleted','Savol o\'chirildi (ID: 20, 0 marta ishlatilgan)','195.158.14.118','2026-04-14 06:46:41'),(21,1,'question_deleted','Savol o\'chirildi (ID: 20, 0 marta ishlatilgan)','195.158.14.118','2026-04-14 06:46:49'),(22,1,'user_added','Yangi foydalanuvchi qo\'shildi: aaaa (viewer)','82.215.108.71','2026-04-14 12:48:31'),(23,1,'NC_SAVED','Nomuvofiqlik saqlandi: 40','82.215.108.71','2026-04-14 14:11:24'),(24,1,'NC_SAVED','Nomuvofiqlik saqlandi: 42','84.54.70.221','2026-04-14 14:20:55'),(25,1,'NC_SAVED','Nomuvofiqlik saqlandi: 43','84.54.70.221','2026-04-14 14:22:11'),(26,1,'NC_SAVED','Nomuvofiqlik saqlandi: 44','84.54.70.221','2026-04-14 14:22:39'),(27,1,'NC_SAVED','Nomuvofiqlik saqlandi: 45','84.54.70.221','2026-04-14 14:23:36'),(28,1,'NC_SAVED','Nomuvofiqlik saqlandi: 46','84.54.70.221','2026-04-14 14:23:55'),(29,1,'user_edited','Foydalanuvchi yangilandi: Najmiddinov Ahadjon (ID: 8)','84.54.70.221','2026-04-14 14:26:29'),(30,1,'NC_SAVED','Nomuvofiqlik saqlandi: 48','82.215.108.71','2026-04-14 15:39:59'),(31,1,'user_edited','Foydalanuvchi yangilandi: Auditor (ID: 6)','82.215.108.71','2026-04-15 05:16:06'),(32,1,'NC_SAVED','Nomuvofiqlik saqlandi: 49','188.113.249.82','2026-04-15 10:09:58'),(33,1,'NC_SAVED','Nomuvofiqlik saqlandi: 49','188.113.249.82','2026-04-15 10:10:57'),(34,1,'NC_SAVED','Nomuvofiqlik saqlandi: 52','82.215.108.45','2026-04-16 19:14:57'),(35,1,'NC_SAVED','Nomuvofiqlik saqlandi: 53','82.215.108.45','2026-04-16 19:15:15'),(36,1,'user_edited','Foydalanuvchi yangilandi: Auditor (ID: 6)','82.215.108.45','2026-04-17 03:23:51'),(37,1,'NC_SAVED','Nomuvofiqlik saqlandi: 54','82.215.108.45','2026-04-17 05:34:44'),(38,1,'NC_SAVED','Nomuvofiqlik saqlandi: 55','82.215.108.45','2026-04-17 05:41:18'),(39,1,'NC_SAVED','Nomuvofiqlik saqlandi: 56','213.230.80.9','2026-04-17 06:25:48'),(40,1,'NC_SAVED','Nomuvofiqlik saqlandi: 57','213.230.80.9','2026-04-17 06:26:33'),(41,1,'NC_SAVED','Nomuvofiqlik saqlandi: 58','213.230.80.9','2026-04-17 06:39:23'),(42,1,'NC_SAVED','Nomuvofiqlik saqlandi: 59','213.230.111.91','2026-04-17 14:16:40'),(43,1,'question_added','Savol qo\'shildi (Bo\'lim ID: 1)','213.230.82.229','2026-04-21 16:38:40'),(44,8,'NC_SAVED','Nomuvofiqlik saqlandi: 63','82.215.109.73','2026-04-23 20:12:21'),(45,1,'user_deleted','Foydalanuvchi o\'chirildi: dfdssa','213.230.82.157','2026-04-24 05:29:03'),(46,1,'user_deleted','Foydalanuvchi o\'chirildi: Bobur','213.230.82.157','2026-04-24 05:29:38'),(47,1,'user_deleted','Foydalanuvchi o\'chirildi: Rahimova Zulfiya','213.230.82.157','2026-04-24 05:29:46'),(48,1,'NC_SAVED','Nomuvofiqlik saqlandi: 65','213.230.82.157','2026-04-24 05:34:30'),(49,8,'NC_SAVED','Nomuvofiqlik saqlandi: 66','213.230.82.157','2026-04-24 05:36:44'),(50,8,'NC_SAVED','Nomuvofiqlik saqlandi: 67','213.230.82.157','2026-04-24 05:37:10'),(51,1,'NC_SAVED','Nomuvofiqlik saqlandi: 68','213.230.82.157','2026-04-24 05:38:04'),(52,1,'user_edited','Foydalanuvchi yangilandi: Bek (ID: 5)','82.215.109.73','2026-04-24 06:58:59'),(53,1,'user_deleted','Foydalanuvchi o\'chirildi: test3','213.230.82.157','2026-04-24 07:04:28'),(54,1,'user_added','Yangi foydalanuvchi qo\'shildi: Boburbek (bosh_auditor)','82.215.109.73','2026-04-24 07:04:59'),(55,1,'user_added','Yangi foydalanuvchi qo\'shildi: Senior developer (auditor)','82.215.109.73','2026-04-24 07:06:04'),(56,1,'user_deleted','Foydalanuvchi o\'chirildi: a','82.215.109.73','2026-04-24 07:06:41'),(57,1,'user_added','Yangi foydalanuvchi qo\'shildi: Dasturchi Programm (bosh_auditor)','82.215.109.73','2026-04-24 07:14:04'),(58,1,'user_added','Yangi foydalanuvchi qo\'shildi: Ashurov Javoxir (bosh_auditor)','213.230.82.157','2026-04-24 07:15:36'),(59,1,'user_deleted','Foydalanuvchi o\'chirildi: Ashurov Javoxir','213.230.82.157','2026-04-24 07:16:50'),(60,1,'user_added','Yangi foydalanuvchi qo\'shildi: Ashurov Javoxir (bosh_auditor)','213.230.82.157','2026-04-24 07:17:11'),(61,1,'user_added','Yangi foydalanuvchi qo\'shildi: Jalilov Ma&#039;ruf (auditor)','213.230.82.157','2026-04-24 07:18:50'),(62,1,'user_added','Yangi foydalanuvchi qo\'shildi: Ma&#039;ruf Mamadaliyve (bosh_auditor)','82.215.109.73','2026-04-24 07:23:37'),(63,1,'user_added','Yangi foydalanuvchi qo\'shildi: Ma\'ruf ASasfasfdf (bosh_auditor)','82.215.109.73','2026-04-24 07:25:22'),(64,1,'user_deleted','Foydalanuvchi o\'chirildi: Jalilov Ma&#039;ruf','213.230.82.157','2026-04-24 08:03:02'),(65,1,'user_deleted','Foydalanuvchi o\'chirildi: Ma&#039;ruf Mamadaliyve','213.230.82.157','2026-04-24 08:03:09'),(66,1,'user_deleted','Foydalanuvchi o\'chirildi: Ma\'ruf ASasfasfdf','213.230.82.157','2026-04-24 08:03:15'),(67,1,'user_added','Yangi foydalanuvchi qo\'shildi: Jalilov Ma\'ruf (auditor)','213.230.82.157','2026-04-24 08:03:40'),(68,1,'NC_SAVED','Nomuvofiqlik saqlandi: 69','213.230.82.157','2026-04-24 08:07:01'),(69,8,'NC_SAVED','Nomuvofiqlik saqlandi: 70','213.230.82.157','2026-04-24 08:10:28'),(70,19,'NC_SAVED','Nomuvofiqlik saqlandi: 71','213.230.82.157','2026-04-24 08:14:35'),(71,1,'NC_SAVED','Nomuvofiqlik saqlandi: 72','82.215.108.59','2026-04-24 19:52:06'),(72,1,'NC_SAVED','Nomuvofiqlik saqlandi: 73','213.230.82.157','2026-04-24 20:05:20'),(73,1,'NC_SAVED','Nomuvofiqlik saqlandi: 74','213.230.82.157','2026-04-24 20:05:56'),(74,1,'NC_SAVED','Nomuvofiqlik saqlandi: 75','213.230.82.157','2026-04-24 20:07:26'),(75,1,'NC_SAVED','Nomuvofiqlik saqlandi: 76','213.230.82.157','2026-04-24 20:07:39'),(76,12,'NC_SAVED','Nomuvofiqlik saqlandi: 78','82.215.108.59','2026-04-24 20:23:28'),(77,8,'NC_SAVED','Nomuvofiqlik saqlandi: 79','213.230.82.157','2026-04-24 20:28:59'),(78,8,'NC_SAVED','Nomuvofiqlik saqlandi: 80','213.230.82.157','2026-04-24 20:29:23'),(79,1,'user_deleted','Foydalanuvchi o\'chirildi: Ashurov Javoxir','213.230.82.157','2026-04-24 21:40:34'),(80,1,'user_added','Yangi foydalanuvchi qo\'shildi: Ashurov Javoxir (super_admin)','213.230.82.157','2026-04-24 21:41:04'),(81,1,'user_deleted','Foydalanuvchi o\'chirildi: Ashurov Javoxir','213.230.82.157','2026-04-24 21:42:11'),(82,1,'user_added','Yangi foydalanuvchi qo\'shildi: Ashurov Javoxir (bosh_auditor)','213.230.82.157','2026-04-24 21:42:59'),(83,1,'question_edited','Savol tahrirlandi (ID: 21)','213.230.111.91','2026-04-25 09:51:54'),(84,1,'user_added','Yangi foydalanuvchi qo\'shildi: Uqish (reader)','213.230.111.91','2026-04-25 09:53:55'),(85,22,'login','Tizimga kirdi','213.230.111.91','2026-04-25 10:16:23'),(86,1,'user_edited','Foydalanuvchi yangilandi: aaaa (ID: 9)','82.215.106.94','2026-05-01 14:01:43');
/*!40000 ALTER TABLE `audit_trail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audits`
--

DROP TABLE IF EXISTS `audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `audit_code` varchar(50) NOT NULL,
  `site_id` int NOT NULL,
  `title` varchar(200) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `status` enum('draft','in_progress','completed','cancelled') DEFAULT 'draft',
  `total_score` decimal(10,2) DEFAULT '0.00',
  `max_score` decimal(10,2) DEFAULT '0.00',
  `progress_percent` decimal(5,2) DEFAULT '0.00',
  `percentage_score` decimal(5,2) DEFAULT '0.00',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `audit_code` (`audit_code`),
  KEY `site_id` (`site_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `audits_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `audits_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audits`
--

LOCK TABLES `audits` WRITE;
/*!40000 ALTER TABLE `audits` DISABLE KEYS */;
INSERT INTO `audits` VALUES (42,'AUD-2026-0001',4,'QC xodim uchun','2026-04-24','2026-04-26',NULL,'completed',0.00,0.00,35.00,0.00,1,'2026-04-24 08:06:13','2026-04-24 08:15:45'),(43,'AUD-2026-0002',4,'dsfdsfd','2026-04-25','2026-04-26',NULL,'completed',0.00,0.00,20.00,0.00,1,'2026-04-24 19:51:34','2026-04-24 19:52:54'),(44,'AUD-2026-0003',4,'gerrfrf','2026-04-25','2026-04-25',NULL,'completed',0.00,0.00,10.00,0.00,1,'2026-04-24 19:56:02','2026-04-24 20:06:23'),(45,'AUD-2026-0004',4,'GMP2026','2026-04-25','2026-04-26',NULL,'completed',0.00,0.00,15.00,0.00,1,'2026-04-24 20:04:44','2026-04-24 20:08:07'),(47,'AUD-2026-0006',4,'QC tekshirish','2026-04-25',NULL,NULL,'completed',0.00,0.00,15.00,0.00,1,'2026-04-24 20:26:27','2026-04-24 20:32:26');
/*!40000 ALTER TABLE `audits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `capa_actions`
--

DROP TABLE IF EXISTS `capa_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `capa_actions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nc_id` int NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nima qilinishi kerak',
  `responsible_person` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mas''ul shaxs',
  `due_date` date DEFAULT NULL COMMENT 'Muddat',
  `status` enum('pending','in_progress','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `evidence_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Dalil fayli (rasm/hujjat)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nc_id` (`nc_id`),
  CONSTRAINT `capa_actions_ibfk_1` FOREIGN KEY (`nc_id`) REFERENCES `non_conformities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nomuvofiqliklarni bartaraf etish chora-tadbirlari';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `capa_actions`
--

LOCK TABLES `capa_actions` WRITE;
/*!40000 ALTER TABLE `capa_actions` DISABLE KEYS */;
/*!40000 ALTER TABLE `capa_actions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `checklist_questions`
--

DROP TABLE IF EXISTS `checklist_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `section_id` int NOT NULL,
  `question_text` text NOT NULL,
  `score` decimal(5,2) DEFAULT '1.00',
  `is_required` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `section_id` (`section_id`),
  CONSTRAINT `checklist_questions_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `gmp_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `checklist_questions`
--

LOCK TABLES `checklist_questions` WRITE;
/*!40000 ALTER TABLE `checklist_questions` DISABLE KEYS */;
INSERT INTO `checklist_questions` VALUES (1,1,'Korxonada GMP bo\'yicha qo\'llanma mavjudmi?',2.00,1,1,1,'2026-04-10 02:32:51'),(2,1,'Sifat siyosati hujjati tasdiqlangan va e\'lon qilinganmi?',2.00,1,2,1,'2026-04-10 02:32:51'),(3,1,'Sifat boshqaruvi tizimi samarali ishlamoqdami?',3.00,1,3,1,'2026-04-10 02:32:51'),(4,2,'Xodimlar malaka oshirish dasturi mavjudmi?',2.00,1,1,1,'2026-04-10 02:32:51'),(5,2,'Har bir xodim uchun lavozim talablari belgilanganmi?',1.50,1,2,1,'2026-04-10 02:32:51'),(6,2,'Xodimlar salomatlik tekshiruvidan o\'tganmi?',2.00,1,3,1,'2026-04-10 02:32:51'),(7,3,'Ishlab chiqarish maydonlari yetarli miqdordami?',2.00,1,1,1,'2026-04-10 02:32:51'),(8,3,'Binolar toza va tartibli holatda saqlanadimi?',2.50,1,2,1,'2026-04-10 02:32:51'),(9,3,'Havo, yorug\'lik va harorat nazorati mavjudmi?',2.00,1,3,1,'2026-04-10 02:32:51'),(10,4,'Uskunalar kalibrlash sertifikatlari mavjudmi?',3.00,1,1,1,'2026-04-10 02:32:51'),(11,4,'Uskunalar tozalash protseduralari amalga oshiriladimi?',2.00,1,2,1,'2026-04-10 02:32:51'),(12,5,'Hujjatlar boshqaruvi tizimi mavjudmi?',2.50,1,1,1,'2026-04-10 02:32:51'),(13,5,'Yozuvlar saqlash muddatlari belgilanganmi?',1.50,1,2,1,'2026-04-10 02:32:51'),(14,6,'Ishlab chiqarish jarayonlari tasdiqlanganmi?',3.00,1,1,1,'2026-04-10 02:32:51'),(15,6,'Bach yozuvlari to\'g\'ri yuritiladimi?',2.00,1,2,1,'2026-04-10 02:32:51'),(16,7,'QC laboratoriyasi jihozlanganmi?',3.00,1,1,1,'2026-04-10 02:32:51'),(17,7,'Namuna olish protseduralari mavjudmi?',2.00,1,2,1,'2026-04-10 02:32:51'),(18,8,'Shikoyatlar ro\'yxati yuritiladimi?',2.00,1,1,1,'2026-04-10 02:32:51'),(19,9,'Ichki audit rejalari tuzilganmi?',2.50,1,1,1,'2026-04-10 02:32:51'),(20,10,'Vakolatli shaxs kim',1.00,1,1,0,'2026-04-11 18:12:12'),(21,1,'aaaa to\'g\'rimi ?',1.00,1,0,1,'2026-04-21 16:38:40');
/*!40000 ALTER TABLE `checklist_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (2,'sadasd','13212','2026-04-17 05:08:18'),(3,'Ishlab Chiqarisha',NULL,'2026-04-27 13:45:06'),(4,'Sifat Nazorati',NULL,'2026-04-27 13:45:06'),(5,'Ombor',NULL,'2026-04-27 13:45:06'),(6,'Laboratoriya',NULL,'2026-04-27 13:45:06');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `status` enum('active','inactive','fired') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `hired_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  KEY `position_id` (`position_id`),
  KEY `idx_emp_dept` (`department_id`),
  CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gmp_sections`
--

DROP TABLE IF EXISTS `gmp_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gmp_sections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `section_number` varchar(10) NOT NULL,
  `section_name` varchar(200) NOT NULL,
  `description` text,
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gmp_sections`
--

LOCK TABLES `gmp_sections` WRITE;
/*!40000 ALTER TABLE `gmp_sections` DISABLE KEYS */;
INSERT INTO `gmp_sections` VALUES (1,'I','Umumiy talablar','GMP asosiy talablari va umumiy qoidalar',1),(2,'II','Xodimlar','Xodimlar malakasi va javobgarligi',2),(3,'III','Binolar va jihozlar','Ishlab chiqarish binolari va jihozlari',3),(4,'IV','Uskunalar','Ishlab chiqarish uskunalari',4),(5,'V','Hujjatlashtirish','Hujjatlar va yozuvlar',5),(6,'VI','Ishlab chiqarish','Ishlab chiqarish jarayonlari',6),(7,'VII','Sifat nazorati','QC laboratoriyasi va nazorat',7),(8,'VIII','Shikoyatlar va qaytarish','Shikoyatlar va mahsulot qaytarish',8),(9,'IX','O\'z-o\'zini tekshirish','Ichki audit va o\'z-o\'zini baholash',9),(10,'X','QA','',10);
/*!40000 ALTER TABLE `gmp_sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `module_materials`
--

DROP TABLE IF EXISTS `module_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_materials` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_id` int NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `module_materials`
--

LOCK TABLES `module_materials` WRITE;
/*!40000 ALTER TABLE `module_materials` DISABLE KEYS */;
INSERT INTO `module_materials` VALUES (17,8,'75G9iUay-JfsZepVF0IiJ8_3io74WZwk.docx','mat_69f4af2edf95a4.88243884.docx','application/vnd.openxmlformats-officedocument.word','2026-05-01 13:48:30'),(18,8,'Academic-Data-312231101329.pdf','mat_69f4af35dc80e8.78135990.pdf','application/pdf','2026-05-01 13:48:37'),(19,1,'XeVDE76sGJpf-j4-H4hwEpq7kcz4M_8E.pdf','mat_69f4fba0d80d11.10737407.pdf','application/pdf','2026-05-01 19:14:40'),(20,1,'Bobur.jpg','mat_69f5911a30d4b8.88791498.jpg','image/jpeg','2026-05-02 05:52:26'),(21,1,'WEB SITE.pptx','mat_69f59139e53229.84479972.pptx','application/vnd.openxmlformats-officedocument.pres','2026-05-02 05:52:57'),(22,1,'yR_VswpPSnOCWN-rIYCHhZVHzr6lGa_k.docx','mat_69f591468588c6.39851363.docx','application/vnd.openxmlformats-officedocument.word','2026-05-02 05:53:10'),(23,1,'Thinko.uz true - PowerPoint 2025-04-08 16-32-56.mp4','mat_69f5915865f181.26967092.mp4','video/mp4','2026-05-02 05:53:28');
/*!40000 ALTER TABLE `module_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `non_conformities`
--

DROP TABLE IF EXISTS `non_conformities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `non_conformities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nc_code` varchar(50) NOT NULL,
  `audit_id` int NOT NULL,
  `question_id` int NOT NULL,
  `answer_id` int NOT NULL,
  `nc_number` int NOT NULL,
  `severity_id` int NOT NULL,
  `description` text NOT NULL,
  `status` enum('open','in_progress','in_review','closed') DEFAULT 'open',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `due_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nc_code` (`nc_code`),
  KEY `audit_id` (`audit_id`),
  KEY `question_id` (`question_id`),
  KEY `answer_id` (`answer_id`),
  KEY `severity_id` (`severity_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `non_conformities_ibfk_1` FOREIGN KEY (`audit_id`) REFERENCES `audits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `non_conformities_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `checklist_questions` (`id`),
  CONSTRAINT `non_conformities_ibfk_3` FOREIGN KEY (`answer_id`) REFERENCES `audit_answers` (`id`),
  CONSTRAINT `non_conformities_ibfk_4` FOREIGN KEY (`severity_id`) REFERENCES `severity_types` (`id`),
  CONSTRAINT `non_conformities_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `non_conformities`
--

LOCK TABLES `non_conformities` WRITE;
/*!40000 ALTER TABLE `non_conformities` DISABLE KEYS */;
INSERT INTO `non_conformities` VALUES (69,'NC-AUD-2026-0001-1',42,1,212,1,1,'Nomuvofiqlik 1','open',1,'2026-04-24 08:06:57',NULL),(70,'NC-AUD-2026-0001-2',42,8,217,2,2,'Nomuvofiqlik 2','open',8,'2026-04-24 08:10:24',NULL),(71,'NC-AUD-2026-0001-3',42,4,219,3,1,'Nomuvofiqlik 3','open',19,'2026-04-24 08:14:30',NULL),(72,'NC-AUD-2026-0002-1',43,1,224,1,3,'Nomuvofiqlik 1','open',1,'2026-04-24 19:52:04',NULL),(73,'NC-AUD-2026-0003-1',44,1,230,1,1,'Nomuvofiqlik 1','open',1,'2026-04-24 20:05:14',NULL),(74,'NC-AUD-2026-0003-2',44,3,233,2,3,'Nomuvofiqlik 2','open',1,'2026-04-24 20:05:54',NULL),(75,'NC-AUD-2026-0004-1',45,21,234,1,1,'Nomuvofiqlik 1','open',1,'2026-04-24 20:07:24',NULL),(76,'NC-AUD-2026-0004-2',45,1,236,2,3,'Nomuvofiqlik 2','open',1,'2026-04-24 20:07:38',NULL),(77,'NC-AUD-2026-0004-3',45,3,239,3,1,'Nomuvofiqlik 3','open',1,'2026-04-24 20:07:56',NULL),(79,'NC-AUD-2026-0006-1',47,1,247,1,1,'Nomuvofiqlik 1','open',8,'2026-04-24 20:28:56',NULL),(80,'NC-AUD-2026-0006-2',47,3,250,2,3,'Nomuvofiqlik 2','open',8,'2026-04-24 20:29:21',NULL);
/*!40000 ALTER TABLE `non_conformities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `positions`
--

DROP TABLE IF EXISTS `positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `positions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `department_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `positions`
--

LOCK TABLES `positions` WRITE;
/*!40000 ALTER TABLE `positions` DISABLE KEYS */;
INSERT INTO `positions` VALUES (3,'Operator',NULL,'2026-04-27 13:45:06'),(4,'Master',NULL,'2026-04-27 13:45:06'),(5,'Sifat inspektori',NULL,'2026-04-27 13:45:06'),(6,'Texnolog',NULL,'2026-04-27 13:45:06'),(7,'Laborant',NULL,'2026-04-27 13:45:06');
/*!40000 ALTER TABLE `positions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `audit_id` int NOT NULL,
  `report_type` varchar(50) DEFAULT 'full',
  `file_path` varchar(500) DEFAULT NULL,
  `generated_by` int NOT NULL,
  `generated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_id` (`audit_id`),
  KEY `generated_by` (`generated_by`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`audit_id`) REFERENCES `audits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports`
--

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `severity_types`
--

DROP TABLE IF EXISTS `severity_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `severity_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `name_en` varchar(50) NOT NULL,
  `color_code` varchar(7) NOT NULL,
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `severity_types`
--

LOCK TABLES `severity_types` WRITE;
/*!40000 ALTER TABLE `severity_types` DISABLE KEYS */;
INSERT INTO `severity_types` VALUES (1,'Jiddiy bo\'lmagan','Minor','#10B981',1),(2,'Jiddiy','Major','#F59E0B',2),(3,'O\'ta jiddiy','Critical','#EF4444',3);
/*!40000 ALTER TABLE `severity_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sites`
--

DROP TABLE IF EXISTS `sites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `address` text,
  `country` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `director_name` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sites`
--

LOCK TABLES `sites` WRITE;
/*!40000 ALTER TABLE `sites` DISABLE KEYS */;
INSERT INTO `sites` VALUES (1,'PharmaTech LLC','Toshkent sh., Sergeli tum., Pharma ko\'chasi 1','O\'zbekiston',NULL,NULL,1,'2026-04-10 02:32:51'),(2,'BioMed Innovations','Samarqand sh., Sanoat zonasi 5','O\'zbekiston',NULL,NULL,1,'2026-04-10 02:32:51'),(3,'asadas','Samarqand',NULL,NULL,NULL,1,'2026-04-15 05:08:38'),(4,'\"SPRING PHARMACEUTICAL\" MCHJ','Namangan viloyati, Kosonsoy tumani, Buloq koʻchasi, 129-uy',NULL,NULL,NULL,1,'2026-04-15 10:08:42');
/*!40000 ALTER TABLE `sites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `test_answers`
--

DROP TABLE IF EXISTS `test_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `test_answers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `question_id` int NOT NULL,
  `answer_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_correct` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `test_answers_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `test_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `test_answers`
--

LOCK TABLES `test_answers` WRITE;
/*!40000 ALTER TABLE `test_answers` DISABLE KEYS */;
/*!40000 ALTER TABLE `test_answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `test_questions`
--

DROP TABLE IF EXISTS `test_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `test_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_id` int NOT NULL,
  `question_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_index` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `module_id` (`module_id`),
  CONSTRAINT `test_questions_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `test_questions`
--

LOCK TABLES `test_questions` WRITE;
/*!40000 ALTER TABLE `test_questions` DISABLE KEYS */;
/*!40000 ALTER TABLE `test_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `test_results`
--

DROP TABLE IF EXISTS `test_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `test_results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `module_id` int NOT NULL,
  `assignment_id` int DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `status` enum('passed','failed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `module_id` (`module_id`),
  KEY `assignment_id` (`assignment_id`),
  KEY `idx_results_emp` (`employee_id`),
  CONSTRAINT `test_results_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `test_results_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `test_results_ibfk_3` FOREIGN KEY (`assignment_id`) REFERENCES `training_assignments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `test_results`
--

LOCK TABLES `test_results` WRITE;
/*!40000 ALTER TABLE `test_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `test_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_assignments`
--

DROP TABLE IF EXISTS `training_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `module_id` int NOT NULL,
  `assigned_by` int DEFAULT NULL,
  `assigned_date` date DEFAULT (curdate()),
  `due_date` date NOT NULL,
  `status` enum('pending','in_progress','completed','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `attempts` int DEFAULT '0',
  `last_attempt_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `assigned_by` (`assigned_by`),
  KEY `idx_assign_emp` (`employee_id`),
  KEY `idx_assign_mod` (`module_id`),
  KEY `idx_assign_status` (`status`,`due_date`),
  CONSTRAINT `training_assignments_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_assignments_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_assignments`
--

LOCK TABLES `training_assignments` WRITE;
/*!40000 ALTER TABLE `training_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `training_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_attempt_answers`
--

DROP TABLE IF EXISTS `training_attempt_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_attempt_answers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `attempt_id` int NOT NULL,
  `question_id` int NOT NULL,
  `selected_option` enum('a','b','c','d') DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `attempt_id` (`attempt_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `training_attempt_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `training_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_attempt_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `training_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_attempt_answers`
--

LOCK TABLES `training_attempt_answers` WRITE;
/*!40000 ALTER TABLE `training_attempt_answers` DISABLE KEYS */;
INSERT INTO `training_attempt_answers` VALUES (1,1,3,'c',0);
/*!40000 ALTER TABLE `training_attempt_answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_attempts`
--

DROP TABLE IF EXISTS `training_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token_id` int NOT NULL,
  `status` enum('passed','failed') NOT NULL,
  `score_percent` decimal(5,2) NOT NULL,
  `total_questions` int DEFAULT '0',
  `correct_answers` int DEFAULT '0',
  `time_spent_seconds` int DEFAULT '0',
  `next_attempt_date` datetime DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `token_id` (`token_id`),
  CONSTRAINT `training_attempts_ibfk_1` FOREIGN KEY (`token_id`) REFERENCES `training_tokens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_attempts`
--

LOCK TABLES `training_attempts` WRITE;
/*!40000 ALTER TABLE `training_attempts` DISABLE KEYS */;
INSERT INTO `training_attempts` VALUES (1,3,'failed',0.00,1,0,4,'2026-04-19 09:52:13','2026-04-17 04:52:13');
/*!40000 ALTER TABLE `training_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_attendees`
--

DROP TABLE IF EXISTS `training_attendees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_attendees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `training_id` int NOT NULL,
  `user_id` int NOT NULL,
  `attended_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendee` (`training_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_attendees`
--

LOCK TABLES `training_attendees` WRITE;
/*!40000 ALTER TABLE `training_attendees` DISABLE KEYS */;
/*!40000 ALTER TABLE `training_attendees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_materials`
--

DROP TABLE IF EXISTS `training_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_materials` (
  `id` int NOT NULL AUTO_INCREMENT,
  `training_id` int NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` enum('pdf','ppt','video','document','other') DEFAULT 'pdf',
  `sort_order` int DEFAULT '0',
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `training_id` (`training_id`),
  CONSTRAINT `training_materials_ibfk_1` FOREIGN KEY (`training_id`) REFERENCES `trainings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_materials`
--

LOCK TABLES `training_materials` WRITE;
/*!40000 ALTER TABLE `training_materials` DISABLE KEYS */;
/*!40000 ALTER TABLE `training_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_matrix`
--

DROP TABLE IF EXISTS `training_matrix`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_matrix` (
  `id` int NOT NULL AUTO_INCREMENT,
  `position_id` int NOT NULL,
  `module_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_matrix` (`position_id`,`module_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_matrix`
--

LOCK TABLES `training_matrix` WRITE;
/*!40000 ALTER TABLE `training_matrix` DISABLE KEYS */;
INSERT INTO `training_matrix` VALUES (25,2,1,'2026-04-28 07:12:39'),(26,3,1,'2026-05-01 10:48:51'),(27,3,8,'2026-05-01 10:48:52'),(28,3,2,'2026-05-01 10:48:53'),(29,7,1,'2026-05-01 17:28:39'),(30,7,8,'2026-05-01 17:28:40'),(31,4,1,'2026-05-01 19:12:15');
/*!40000 ALTER TABLE `training_matrix` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_modules`
--

DROP TABLE IF EXISTS `training_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_modules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `type` enum('GMP','SOP','Safety','Hygiene','Quality','Havfsizlik','Gigiyena') COLLATE utf8mb4_unicode_ci DEFAULT 'GMP',
  `content_type` enum('video','pdf','ppt','text') COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `test_duration` int DEFAULT '30',
  `passing_percent` int DEFAULT '80',
  `instructor_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `style` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Nazariy',
  `training_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'boshlangich',
  `tutor_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `instructor_id` (`instructor_id`),
  CONSTRAINT `training_modules_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_modules`
--

LOCK TABLES `training_modules` WRITE;
/*!40000 ALTER TABLE `training_modules` DISABLE KEYS */;
INSERT INTO `training_modules` VALUES (1,'GMP Kirish kursi','GMP-001','Yaxshi ishlab chiqarish amaliyoti asoslari','GMP','text',NULL,45,80,NULL,'2026-04-27 13:45:06','active','Nazariy','boshlangich',NULL,'2026-04-28 05:07:03',NULL),(2,'Shaxsiy Gigiyena','HYG-001','Oziq-ovqat sanoatida gigiyena qoidalari','Hygiene','text',NULL,30,90,NULL,'2026-04-27 13:45:06','active','Nazariy','boshlangich',NULL,'2026-04-28 05:07:03',NULL),(8,'sadsaces','efddesde','wdwedcewsdcewsd','GMP','text','',10,70,NULL,'2026-04-28 06:09:21','active','Nazariy','boshlangich','sedfwsac','2026-04-28 06:10:07',1);
/*!40000 ALTER TABLE `training_modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_questions`
--

DROP TABLE IF EXISTS `training_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `training_id` int NOT NULL,
  `question_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `option_a` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_b` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_c` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_d` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correct_option` enum('a','b','c','d') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `points` int DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_questions`
--

LOCK TABLES `training_questions` WRITE;
/*!40000 ALTER TABLE `training_questions` DISABLE KEYS */;
INSERT INTO `training_questions` VALUES (1,1,'a','a','b','c','d','c',1),(2,1,'sdgsf','a','b','c','d','a',1),(3,2,'asdcasfews','asnf','dsjof','esjf','ejosf','a',1),(5,8,'qfwedsfwedwe','dddddddd','aaaaaaa','sssssssss','ddddddd','a',1),(6,8,'adefsef','efesd','sdfes','erferf','refers','c',1);
/*!40000 ALTER TABLE `training_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_tests`
--

DROP TABLE IF EXISTS `training_tests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_tests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `attendee_id` int NOT NULL,
  `status` enum('passed','failed','in_progress') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `score_percent` decimal(5,2) DEFAULT NULL,
  `total_questions` int DEFAULT NULL,
  `correct_answers` int DEFAULT NULL,
  `time_spent_seconds` int DEFAULT NULL,
  `next_attempt_date` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_tests`
--

LOCK TABLES `training_tests` WRITE;
/*!40000 ALTER TABLE `training_tests` DISABLE KEYS */;
/*!40000 ALTER TABLE `training_tests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_tokens`
--

DROP TABLE IF EXISTS `training_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(64) NOT NULL,
  `training_id` int NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `employee_position` varchar(255) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `training_id` (`training_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `training_tokens_ibfk_1` FOREIGN KEY (`training_id`) REFERENCES `trainings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_tokens_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_tokens`
--

LOCK TABLES `training_tokens` WRITE;
/*!40000 ALTER TABLE `training_tokens` DISABLE KEYS */;
INSERT INTO `training_tokens` VALUES (1,'01fb6ce05ab9bec123a1730370908e70f83b59b2e69ccd2458c547919bdf4817',1,'asd a','asd ','2026-04-24 06:46:05',1,NULL,'2026-04-17 03:46:05'),(2,'8fc00edb1abe8071f1493e050a6ebf59f21d3651a9260b45bca8466a7aaf5424',2,'Baymatov BObur','Dasturchi','2026-04-24 07:25:04',1,NULL,'2026-04-17 04:25:04'),(3,'d9cc3901283d54c866d1a05ac45c97e804912ae36fc707c45ac78c6881fc98ae',2,'aefsd','dsfs','2026-04-24 07:44:35',1,NULL,'2026-04-17 04:44:35');
/*!40000 ALTER TABLE `training_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trainings`
--

DROP TABLE IF EXISTS `trainings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trainings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `form_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('gmp','sop','safety','hygiene','position_guide','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `style` enum('practical','theoretical') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trainer_id` int DEFAULT NULL,
  `category` enum('initial','periodic','emergency','special') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `material_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `test_time_minutes` int DEFAULT NULL,
  `passing_percent` int DEFAULT '70',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trainings`
--

LOCK TABLES `trainings` WRITE;
/*!40000 ALTER TABLE `trainings` DISABLE KEYS */;
INSERT INTO `trainings` VALUES (1,'sdfgsd','sadfsfsad','asdfas','gmp','','practical',NULL,'',NULL,30,70,'active','2026-04-17 03:45:54'),(2,'dskfmsd','Test uchun','awemawskfd','gmp','','practical',NULL,'',NULL,30,70,'active','2026-04-17 04:24:48'),(3,'ewfclejmd','kdsfcds','jcfked','gmp',NULL,'theoretical',NULL,NULL,NULL,30,70,'active','2026-04-17 05:13:44'),(4,'frgv','asdsf','dsfvrer','gmp',NULL,'theoretical',NULL,NULL,NULL,30,70,'active','2026-04-25 09:54:46');
/*!40000 ALTER TABLE `trainings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_positions`
--

DROP TABLE IF EXISTS `user_positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_positions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `position_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_position` (`user_id`,`position_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_positions`
--

LOCK TABLES `user_positions` WRITE;
/*!40000 ALTER TABLE `user_positions` DISABLE KEYS */;
INSERT INTO `user_positions` VALUES (1,1,1,1);
/*!40000 ALTER TABLE `user_positions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `role` enum('super_admin','bosh_auditor','auditor','viewer','reader') DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `department_id` int DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@gmp.uz','1','Tizim Administratori',NULL,NULL,'super_admin',1,'2026-04-10 02:32:51','2026-04-10 02:43:38',NULL,NULL,NULL),(5,'test','test@gmail.com','$2y$12$6usaya0j4p32N3p4zCcZjea6jAf974b/J1OCgD.1COQT5wSuhFF9m','Bek',NULL,NULL,'bosh_auditor',1,'2026-04-11 16:28:33','2026-04-24 06:58:59',NULL,NULL,NULL),(6,'test2','test2@gmail.com','$2y$12$hpenfCTlp5ABrzacqkygxujLGvAfJg.xsZ3DOSj8QYy3UOBvo3YTW','Auditor',NULL,NULL,'viewer',1,'2026-04-11 17:20:47','2026-04-17 03:23:51',NULL,NULL,NULL),(8,'Ahadjon','ahadjon@gmail.com','$2y$12$/LlEkF5iwDVNpXtgmdAu0efkS4pNXXw5FoeFMUgTUDxGXfAo5ntOq','Najmiddinov Ahadjon',NULL,NULL,'auditor',1,'2026-04-11 18:14:28','2026-04-14 14:26:29',NULL,NULL,NULL),(9,'aaaa','aaaa@gmail.com','$2y$12$XQD4ervjp.mdP6drSVPHsuuMJYzXr/we5Z4HstuQziQjJhrI/RLI6','aaaa',NULL,NULL,'reader',1,'2026-04-14 12:48:31','2026-05-01 14:01:43',NULL,NULL,NULL),(11,'admin123','admin123@gmail.com','$2y$12$DenytlJhjdXjtIwXQ/5SPOdgs0b2r9FfgQ5zoKlqc0VgNHaqiscPO','Boburbek',NULL,NULL,'bosh_auditor',1,'2026-04-24 07:04:59','2026-04-24 07:04:59',NULL,NULL,NULL),(12,'admin321','admin321@gmail.com','$2y$12$nqHgiPQTRhYsa4Ew0Eyg.O3fbZrLt4u5cwn3LHmwmpdvEKJrDQS7u','Senior developer',NULL,NULL,'auditor',1,'2026-04-24 07:06:04','2026-04-24 07:06:04',NULL,NULL,NULL),(13,'admin54321','admin54321@gmail.com','$2y$12$7XlwXMOIbfLNXwIiyGExyO9ScI7CZFfNSr2P5FljO8WS3Pdy01HWO','Dasturchi Programm',NULL,NULL,'bosh_auditor',1,'2026-04-24 07:14:04','2026-04-24 07:14:04',NULL,NULL,NULL),(19,'admin654321','maruf@gmail.com','$2y$12$ZUZU6hzB7aTSgmRN/3czauZ3RxGS3BjTcMTVKDdWMGdiVI6by5T16','Jalilov Ma\'ruf',NULL,NULL,'auditor',1,'2026-04-24 08:03:40','2026-04-24 08:03:40',NULL,NULL,NULL),(21,'admin123456','javohirpharma@gmail.com','$2y$12$8M0AFLQwEzxAFhxbgIjhO.ihL8BcSQmK6kFZgdUxnzYRrBYHGl7dy','Ashurov Javoxir',NULL,NULL,'bosh_auditor',1,'2026-04-24 21:42:59','2026-04-24 21:42:59',NULL,NULL,NULL),(22,'admin000','admin000@gmail.com','$2y$12$DDNTKJz/0aKYMuElB7TRQOPgoE/HQ3aD1WIgYjljazzi4hE3pxB2W','Uqish',NULL,NULL,'reader',1,'2026-04-25 09:53:55','2026-05-02 05:51:20',NULL,4,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-02 17:23:41
