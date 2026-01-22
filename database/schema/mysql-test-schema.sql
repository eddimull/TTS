/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `causer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `batch_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `band_api_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `band_api_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `band_api_tokens_token_unique` (`token`),
  KEY `band_api_tokens_band_id_is_active_index` (`band_id`,`is_active`),
  CONSTRAINT `band_api_tokens_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `band_calendars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `band_calendars` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `calendar_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('booking','event','public') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'booking',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `band_calendars_band_id_foreign` (`band_id`),
  CONSTRAINT `band_calendars_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `band_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `band_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned DEFAULT '0',
  `event_name` char(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No Name',
  `venue_name` char(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No Venue Name',
  `first_dance` char(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_daughter` char(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `money_dance` char(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bouquet_garter` char(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_street` char(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` char(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `event_time` datetime DEFAULT NULL,
  `band_loadin_time` datetime DEFAULT NULL,
  `finish_time` datetime DEFAULT NULL,
  `rhythm_loadin_time` datetime DEFAULT NULL,
  `production_loadin_time` datetime DEFAULT NULL,
  `pay` double DEFAULT NULL,
  `depositReceived` tinyint(1) DEFAULT NULL,
  `event_key` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `public` tinyint(1) DEFAULT '0',
  `event_type_id` bigint unsigned NOT NULL DEFAULT '1',
  `lodging` tinyint(1) DEFAULT '0',
  `state_id` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `colorway_id` bigint unsigned DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outside` tinyint(1) NOT NULL DEFAULT '0',
  `second_line` tinyint(1) NOT NULL DEFAULT '0',
  `onsite` tinyint(1) DEFAULT '0',
  `quiet_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `ceremony_time` datetime DEFAULT NULL,
  `google_calendar_event_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_groom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `production_needed` tinyint(1) DEFAULT '1',
  `backline_provided` tinyint(1) DEFAULT '0',
  `colorway_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `event_time` (`band_id`,`event_time`),
  KEY `event_time_index` (`band_id`,`event_time`) USING BTREE,
  CONSTRAINT `band_events_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `band_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `band_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `band_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `band_members_user_id_foreign` (`user_id`),
  KEY `band_members_band_id_foreign` (`band_id`),
  CONSTRAINT `band_members_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`),
  CONSTRAINT `band_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `band_owners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `band_owners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `band_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `band_owners_user_id_foreign` (`user_id`),
  KEY `band_owners_band_id_foreign` (`band_id`),
  CONSTRAINT `band_owners_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`),
  CONSTRAINT `band_owners_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `band_payment_group_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `band_payment_group_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_payment_group_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `payout_type` enum('percentage','fixed','equal_split') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payout_value` decimal(10,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `band_payment_group_members_band_payment_group_id_user_id_unique` (`band_payment_group_id`,`user_id`),
  KEY `band_payment_group_members_band_payment_group_id_index` (`band_payment_group_id`),
  KEY `band_payment_group_members_user_id_index` (`user_id`),
  CONSTRAINT `band_payment_group_members_band_payment_group_id_foreign` FOREIGN KEY (`band_payment_group_id`) REFERENCES `band_payment_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `band_payment_group_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `band_payment_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `band_payment_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `default_payout_type` enum('percentage','fixed','equal_split') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'equal_split',
  `default_payout_value` decimal(10,2) DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `band_payment_groups_band_id_name_unique` (`band_id`,`name`),
  KEY `band_payment_groups_band_id_index` (`band_id`),
  KEY `band_payment_groups_band_id_is_active_index` (`band_id`,`is_active`),
  CONSTRAINT `band_payment_groups_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `band_payout_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `band_payout_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Default Configuration',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `band_cut_type` enum('percentage','fixed','tiered','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `band_cut_value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `band_cut_tier_config` json DEFAULT NULL,
  `member_payout_type` enum('equal_split','percentage','fixed','tiered') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'equal_split',
  `tier_config` json DEFAULT NULL,
  `regular_member_count` int NOT NULL DEFAULT '0',
  `production_member_count` int NOT NULL DEFAULT '0',
  `production_member_types` json DEFAULT NULL,
  `member_specific_config` json DEFAULT NULL,
  `include_owners` tinyint(1) NOT NULL DEFAULT '1',
  `include_members` tinyint(1) NOT NULL DEFAULT '1',
  `minimum_payout` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `flow_diagram` json DEFAULT NULL COMMENT 'Visual flow editor node/edge configuration',
  `use_payment_groups` tinyint(1) NOT NULL DEFAULT '0',
  `payment_group_config` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `band_payout_configs_band_id_index` (`band_id`),
  KEY `band_payout_configs_band_id_is_active_index` (`band_id`,`is_active`),
  CONSTRAINT `band_payout_configs_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `band_playlist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `band_playlist` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `song_id` bigint unsigned NOT NULL,
  `name` char(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `band_playlist_band_id_foreign` (`band_id`),
  KEY `band_playlist_song_id_foreign` (`song_id`),
  CONSTRAINT `band_playlist_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`),
  CONSTRAINT `band_playlist_song_id_foreign` FOREIGN KEY (`song_id`) REFERENCES `band_songs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `band_songs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `band_songs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` char(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `artist` char(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `year` year NOT NULL,
  `style_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `band_storage_quotas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `band_storage_quotas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `quota_limit` bigint unsigned NOT NULL DEFAULT '5368709120' COMMENT 'Default 5GB',
  `quota_used` bigint unsigned NOT NULL DEFAULT '0',
  `last_calculated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `band_storage_quotas_band_id_unique` (`band_id`),
  CONSTRAINT `band_storage_quotas_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bands` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `site_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '/images/default.png',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `booking_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `contact_id` bigint unsigned NOT NULL,
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `additional_info` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_contacts_booking_id_contact_id_unique` (`booking_id`,`contact_id`),
  KEY `booking_contacts_contact_id_foreign` (`contact_id`),
  CONSTRAINT `booking_contacts_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `booking_contacts_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `venue_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TBD',
  `venue_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `price` int NOT NULL DEFAULT '0',
  `status` enum('draft','pending','confirmed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `contract_option` enum('default','none','external') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `notes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `author_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `google_calendar_event_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bookings_band_id_foreign` (`band_id`),
  KEY `bookings_event_type_id_foreign` (`event_type_id`),
  KEY `bookings_author_id_foreign` (`author_id`),
  CONSTRAINT `bookings_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  CONSTRAINT `bookings_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`),
  CONSTRAINT `bookings_event_type_id_foreign` FOREIGN KEY (`event_type_id`) REFERENCES `event_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `calendar_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `calendar_access` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `band_calendar_id` bigint unsigned NOT NULL,
  `role` enum('reader','writer','owner') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `calendar_access_user_id_foreign` (`user_id`),
  KEY `calendar_access_band_calendar_id_foreign` (`band_calendar_id`),
  CONSTRAINT `calendar_access_band_calendar_id_foreign` FOREIGN KEY (`band_calendar_id`) REFERENCES `band_calendars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `calendar_access_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chart_uploads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chart_uploads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `chart_id` bigint unsigned NOT NULL,
  `upload_type_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fileType` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `displayName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Untitled',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Untitled',
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `charts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `charts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Untitled',
  `composer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No Composer',
  `arranger` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `public` tinyint(1) NOT NULL DEFAULT '0',
  `price` bigint NOT NULL DEFAULT '50',
  `band_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `colorway_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `colorway_photos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `colorway_id` bigint unsigned NOT NULL,
  `photo_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `colorway_photos_colorway_id_foreign` (`colorway_id`),
  CONSTRAINT `colorway_photos_colorway_id_foreign` FOREIGN KEY (`colorway_id`) REFERENCES `colorways` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `colorways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `colorways` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `color_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `color_tags` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `colorway_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `colorways_band_id_foreign` (`band_id`),
  CONSTRAINT `colorways_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_change_required` tinyint(1) NOT NULL DEFAULT '0',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `can_login` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contacts_band_id_email_unique` (`band_id`,`email`),
  CONSTRAINT `contacts_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contracts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contractable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contractable_id` bigint unsigned NOT NULL,
  `envelope_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_id` bigint unsigned NOT NULL,
  `status` enum('pending','sent','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `asset_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_terms` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contracts_contractable_type_contractable_id_index` (`contractable_type`,`contractable_id`),
  KEY `contracts_author_id_foreign` (`author_id`),
  CONSTRAINT `contracts_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sort` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phoneCode` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `editable_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `editable_contracts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `band_id` bigint unsigned NOT NULL,
  `default` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint unsigned NOT NULL,
  `disk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 's3-private',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_attachments_event_id_foreign` (`event_id`),
  CONSTRAINT `event_attachments_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL,
  `name` char(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `phonenumber` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_contacts_event_id_desc_index` (`event_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_distance_for_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_distance_for_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL DEFAULT '0',
  `user_id` bigint unsigned NOT NULL DEFAULT '0',
  `miles` decimal(8,2) unsigned NOT NULL DEFAULT '0.00',
  `minutes` decimal(8,2) unsigned NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL,
  `band_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `roster_member_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attendance_status` enum('confirmed','attended','absent','excused') COLLATE utf8mb4_unicode_ci DEFAULT 'confirmed',
  `payout_amount` bigint DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_members_event_id_user_id_unique` (`event_id`,`user_id`),
  KEY `event_members_band_id_foreign` (`band_id`),
  KEY `event_members_user_id_foreign` (`user_id`),
  KEY `event_members_roster_member_id_foreign` (`roster_member_id`),
  CONSTRAINT `event_members_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_members_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_members_roster_member_id_foreign` FOREIGN KEY (`roster_member_id`) REFERENCES `roster_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
INSERT INTO `event_types` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'Wedding', NOW(), NOW()),
(2, 'Bar Gig', NOW(), NOW()),
(3, 'Casino', NOW(), NOW()),
(4, 'Special Event', NOW(), NOW()),
(5, 'Charity', NOW(), NOW()),
(6, 'Festival', NOW(), NOW()),
(7, 'Private Party', NOW(), NOW()),
(8, 'Mardi Gras Ball', NOW(), NOW()),
(9, 'Other', NOW(), NOW()),
(10, 'Rehearsal', NOW(), NOW());
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `date` date NOT NULL,
  `event_type_id` bigint unsigned NOT NULL,
  `roster_id` bigint unsigned DEFAULT NULL,
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `additional_data` json DEFAULT NULL,
  `notes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `eventable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `eventable_id` bigint unsigned NOT NULL,
  `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `time` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `key` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `events_key_unique` (`key`),
  KEY `events_event_type_id_foreign` (`event_type_id`),
  KEY `events_eventable_type_eventable_id_index` (`eventable_type`,`eventable_id`),
  KEY `events_roster_id_foreign` (`roster_id`),
  CONSTRAINT `events_event_type_id_foreign` FOREIGN KEY (`event_type_id`) REFERENCES `event_types` (`id`),
  CONSTRAINT `events_roster_id_foreign` FOREIGN KEY (`roster_id`) REFERENCES `rosters` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `google_drive_connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `google_drive_connections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `band_id` bigint unsigned NOT NULL,
  `access_token` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `refresh_token` text COLLATE utf8mb4_unicode_ci,
  `token_expires_at` timestamp NULL DEFAULT NULL,
  `google_account_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `drive_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `sync_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `last_sync_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_band_email` (`user_id`,`band_id`,`google_account_email`),
  KEY `google_drive_connections_band_id_is_active_index` (`band_id`,`is_active`),
  KEY `google_drive_connections_user_id_band_id_index` (`user_id`,`band_id`),
  CONSTRAINT `google_drive_connections_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `google_drive_connections_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `google_drive_folders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `google_drive_folders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `connection_id` bigint unsigned NOT NULL,
  `google_folder_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `google_folder_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `google_folder_path` text COLLATE utf8mb4_unicode_ci,
  `local_folder_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_sync` tinyint(1) NOT NULL DEFAULT '1',
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `sync_cursor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_connection_folder` (`connection_id`,`google_folder_id`),
  KEY `google_drive_folders_connection_id_google_folder_id_index` (`connection_id`,`google_folder_id`),
  CONSTRAINT `google_drive_folders_connection_id_foreign` FOREIGN KEY (`connection_id`) REFERENCES `google_drive_connections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `google_drive_sync_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `google_drive_sync_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `connection_id` bigint unsigned NOT NULL,
  `folder_id` bigint unsigned DEFAULT NULL,
  `sync_type` enum('manual','scheduled','webhook') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `files_checked` int NOT NULL DEFAULT '0',
  `files_downloaded` int NOT NULL DEFAULT '0',
  `files_updated` int NOT NULL DEFAULT '0',
  `files_deleted` int NOT NULL DEFAULT '0',
  `files_skipped` int NOT NULL DEFAULT '0',
  `bytes_transferred` bigint NOT NULL DEFAULT '0',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `error_details` json DEFAULT NULL,
  `started_at` timestamp NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `google_drive_sync_logs_folder_id_foreign` (`folder_id`),
  KEY `google_drive_sync_logs_connection_id_created_at_index` (`connection_id`,`created_at`),
  CONSTRAINT `google_drive_sync_logs_connection_id_foreign` FOREIGN KEY (`connection_id`) REFERENCES `google_drive_connections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `google_drive_sync_logs_folder_id_foreign` FOREIGN KEY (`folder_id`) REFERENCES `google_drive_folders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `google_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `google_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `google_event_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `google_eventable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `google_eventable_id` bigint unsigned NOT NULL,
  `band_calendar_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `google_events_google_event_id_unique` (`google_event_id`),
  KEY `google_events_google_eventable_type_google_eventable_id_index` (`google_eventable_type`,`google_eventable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invitations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `band_id` bigint unsigned NOT NULL,
  `invite_type_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `pending` tinyint(1) NOT NULL DEFAULT '1',
  `key` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `amount` int NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `stripe_id` varchar(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `convenience_fee` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `media_associations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `media_associations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `media_file_id` bigint unsigned NOT NULL,
  `associable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'App\\Models\\Events or App\\Models\\Bookings',
  `associable_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `media_associations_associable_type_associable_id_index` (`associable_type`,`associable_id`),
  KEY `media_associations_media_file_id_index` (`media_file_id`),
  CONSTRAINT `media_associations_media_file_id_foreign` FOREIGN KEY (`media_file_id`) REFERENCES `media_files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `media_file_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `media_file_tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `media_file_id` bigint unsigned NOT NULL,
  `media_tag_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_file_tags_media_file_id_media_tag_id_unique` (`media_file_id`,`media_tag_id`),
  KEY `media_file_tags_media_tag_id_index` (`media_tag_id`),
  CONSTRAINT `media_file_tags_media_file_id_foreign` FOREIGN KEY (`media_file_id`) REFERENCES `media_files` (`id`) ON DELETE CASCADE,
  CONSTRAINT `media_file_tags_media_tag_id_foreign` FOREIGN KEY (`media_tag_id`) REFERENCES `media_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `media_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `media_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Original filename',
  `stored_filename` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Path in S3 with UUID',
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint unsigned NOT NULL COMMENT 'Bytes',
  `disk` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 's3',
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'upload',
  `google_drive_file_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `drive_connection_id` bigint unsigned DEFAULT NULL,
  `drive_last_modified` timestamp NULL DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User-friendly title',
  `description` text COLLATE utf8mb4_unicode_ci,
  `media_type` enum('image','video','audio','document','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `folder_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Virtual folder path like "Photos/2024"',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `media_files_user_id_foreign` (`user_id`),
  KEY `media_files_band_id_media_type_index` (`band_id`,`media_type`),
  KEY `media_files_band_id_created_at_index` (`band_id`,`created_at`),
  KEY `media_files_band_id_folder_path_index` (`band_id`,`folder_path`),
  KEY `media_files_drive_connection_id_foreign` (`drive_connection_id`),
  KEY `media_files_source_band_id_index` (`source`,`band_id`),
  KEY `media_files_google_drive_file_id_index` (`google_drive_file_id`),
  FULLTEXT KEY `media_files_filename_title_description_fulltext` (`filename`,`title`,`description`),
  CONSTRAINT `media_files_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `media_files_drive_connection_id_foreign` FOREIGN KEY (`drive_connection_id`) REFERENCES `google_drive_connections` (`id`) ON DELETE SET NULL,
  CONSTRAINT `media_files_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `media_folders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `media_folders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full folder path like "Photos/2024"',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_folders_band_id_path_unique` (`band_id`,`path`),
  KEY `media_folders_created_by_foreign` (`created_by`),
  KEY `media_folders_band_id_index` (`band_id`),
  CONSTRAINT `media_folders_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `media_folders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `media_shares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `media_shares` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `media_file_id` bigint unsigned NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Random token for URL',
  `created_by` bigint unsigned NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'NULL = permanent',
  `download_limit` int unsigned DEFAULT NULL COMMENT 'NULL = unlimited',
  `download_count` int unsigned NOT NULL DEFAULT '0',
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional password protection',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_shares_token_unique` (`token`),
  KEY `media_shares_media_file_id_foreign` (`media_file_id`),
  KEY `media_shares_created_by_foreign` (`created_by`),
  KEY `media_shares_token_is_active_index` (`token`,`is_active`),
  CONSTRAINT `media_shares_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `media_shares_media_file_id_foreign` FOREIGN KEY (`media_file_id`) REFERENCES `media_files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `media_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `media_tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hex color for UI',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_tags_band_id_slug_unique` (`band_id`,`slug`),
  KEY `media_tags_band_id_name_index` (`band_id`,`name`),
  CONSTRAINT `media_tags_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `seen_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_statuses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payable_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` int NOT NULL,
  `date` datetime DEFAULT NULL,
  `band_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `payer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payer_id` bigint unsigned DEFAULT NULL,
  `payment_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paid',
  `invoices_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_payable_type_payable_id_index` (`payable_type`,`payable_id`),
  KEY `payments_band_id_foreign` (`band_id`),
  KEY `payments_payer_type_payer_id_index` (`payer_type`,`payer_id`),
  CONSTRAINT `payments_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payout_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payout_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payout_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `amount` bigint NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payout_adjustments_created_by_foreign` (`created_by`),
  KEY `payout_adjustments_payout_id_deleted_at_index` (`payout_id`,`deleted_at`),
  CONSTRAINT `payout_adjustments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payout_adjustments_payout_id_foreign` FOREIGN KEY (`payout_id`) REFERENCES `payouts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payouts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payable_id` bigint unsigned NOT NULL,
  `band_id` bigint unsigned NOT NULL,
  `payout_config_id` bigint unsigned DEFAULT NULL,
  `base_amount` bigint NOT NULL,
  `adjusted_amount` bigint NOT NULL,
  `calculation_result` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payouts_payable_type_payable_id_index` (`payable_type`,`payable_id`),
  KEY `payouts_payout_config_id_foreign` (`payout_config_id`),
  KEY `payouts_payable_type_payable_id_deleted_at_index` (`payable_type`,`payable_id`,`deleted_at`),
  KEY `payouts_band_id_deleted_at_index` (`band_id`,`deleted_at`),
  CONSTRAINT `payouts_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payouts_payout_config_id_foreign` FOREIGN KEY (`payout_config_id`) REFERENCES `band_payout_configs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proposal_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proposal_id` bigint unsigned NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phonenumber` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proposal_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_contracts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proposal_id` bigint unsigned NOT NULL,
  `envelope_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'sent',
  `image_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proposal_id_index` (`proposal_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proposal_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `amount` int NOT NULL DEFAULT '0',
  `proposal_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `paymentDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payments_proposal_id_foreign` (`proposal_id`),
  CONSTRAINT `payments_proposal_id_foreign` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proposal_phases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_phases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `icon` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `proposal_phases` (`id`, `name`, `created_at`, `updated_at`, `icon`) VALUES
(1, 'Draft', NOW(), NOW(), 'fas fa-star'),
(2, 'Finalized', NOW(), NOW(), 'fas fa-phone'),
(3, 'proposal sent', NOW(), NOW(), 'fas fa-calendar-alt'),
(4, 'Approved', NOW(), NOW(), 'fas fa-file-contract'),
(5, 'contract sent', NOW(), NOW(), 'fas fa-redo'),
(6, 'contract signed', NOW(), NOW(), 'fas fa-handshake');
DROP TABLE IF EXISTS `proposals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `phase_id` bigint unsigned NOT NULL,
  `author_id` bigint unsigned NOT NULL,
  `date` datetime NOT NULL,
  `hours` int NOT NULL,
  `price` decimal(9,3) NOT NULL,
  `color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `locked` tinyint(1) NOT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `key` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `event_type_id` bigint unsigned NOT NULL DEFAULT '9',
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'TBD',
  `client_notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_id` bigint unsigned DEFAULT NULL,
  `paid` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `proposals_band_id_foreign` (`band_id`),
  KEY `proposals_phase_id_foreign` (`phase_id`),
  KEY `proposals_author_id_foreign` (`author_id`),
  CONSTRAINT `proposals_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  CONSTRAINT `proposals_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`),
  CONSTRAINT `proposals_phase_id_foreign` FOREIGN KEY (`phase_id`) REFERENCES `proposal_phases` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `questionnaire_components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `questionnaire_components` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `questionnaire_id` bigint unsigned NOT NULL,
  `data` json NOT NULL,
  `order` int NOT NULL DEFAULT '-1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `questionnairres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `questionnairres` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `band_id` bigint unsigned NOT NULL,
  `name` varchar(70) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recurring_proposal_dates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recurring_proposal_dates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proposal_id` bigint unsigned NOT NULL,
  `date` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rehearsal_associations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rehearsal_associations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rehearsal_id` bigint unsigned NOT NULL,
  `associable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `associable_id` bigint unsigned NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rehearsal_associations_unique` (`rehearsal_id`,`associable_type`,`associable_id`),
  KEY `rehearsal_associations_associable_type_associable_id_index` (`associable_type`,`associable_id`),
  CONSTRAINT `rehearsal_associations_rehearsal_id_foreign` FOREIGN KEY (`rehearsal_id`) REFERENCES `rehearsals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rehearsal_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rehearsal_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `frequency` enum('daily','weekly','monthly','weekday','custom') COLLATE utf8mb4_unicode_ci DEFAULT 'weekly',
  `day_of_week` enum('monday','tuesday','wednesday','thursday','friday','saturday','sunday') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `selected_days` json DEFAULT NULL,
  `day_of_month` tinyint unsigned DEFAULT NULL,
  `monthly_pattern` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_weekday` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_time` time DEFAULT NULL,
  `location_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_address` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rehearsal_schedules_band_id_foreign` (`band_id`),
  CONSTRAINT `rehearsal_schedules_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rehearsals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rehearsals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rehearsal_schedule_id` bigint unsigned NOT NULL,
  `band_id` bigint unsigned NOT NULL,
  `venue_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `venue_address` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_cancelled` tinyint(1) NOT NULL DEFAULT '0',
  `additional_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rehearsals_rehearsal_schedule_id_foreign` (`rehearsal_schedule_id`),
  KEY `rehearsals_band_id_index` (`band_id`),
  CONSTRAINT `rehearsals_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rehearsals_rehearsal_schedule_id_foreign` FOREIGN KEY (`rehearsal_schedule_id`) REFERENCES `rehearsal_schedules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roster_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roster_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `roster_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_payout_type` enum('equal_split','fixed','percentage') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'equal_split',
  `default_payout_amount` bigint DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roster_members_roster_id_user_id_unique` (`roster_id`,`user_id`),
  KEY `roster_members_user_id_foreign` (`user_id`),
  CONSTRAINT `roster_members_roster_id_foreign` FOREIGN KEY (`roster_id`) REFERENCES `rosters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `roster_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rosters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rosters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rosters_band_id_is_default_index` (`band_id`,`is_default`),
  CONSTRAINT `rosters_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sent_proposal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sent_proposal` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proposal_id` bigint unsigned NOT NULL,
  `proposal_contact_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `states` (
  `state_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `state_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`state_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stripe_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stripe_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `stripe_account_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stripe_customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stripe_customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stripe_customer_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_account_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `contact_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `stripe_customers_contact_id_stripe_account_id_index` (`contact_id`,`stripe_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stripe_invoice_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stripe_invoice_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proposal_id` bigint unsigned NOT NULL,
  `stripe_price_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stripe_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stripe_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_product_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `substitute_call_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `substitute_call_lists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `instrument` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `roster_member_id` bigint unsigned DEFAULT NULL,
  `custom_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` int NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `substitute_call_lists_roster_member_id_foreign` (`roster_member_id`),
  KEY `substitute_call_lists_band_id_instrument_priority_index` (`band_id`,`instrument`,`priority`),
  CONSTRAINT `substitute_call_lists_band_id_foreign` FOREIGN KEY (`band_id`) REFERENCES `bands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `substitute_call_lists_roster_member_id_foreign` FOREIGN KEY (`roster_member_id`) REFERENCES `roster_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `upload_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `upload_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `band_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `read_events` tinyint(1) NOT NULL DEFAULT '0',
  `write_events` tinyint(1) NOT NULL DEFAULT '0',
  `read_proposals` tinyint(1) NOT NULL DEFAULT '0',
  `write_proposals` tinyint(1) NOT NULL DEFAULT '0',
  `read_invoices` tinyint(1) NOT NULL DEFAULT '0',
  `write_invoices` tinyint(1) NOT NULL DEFAULT '0',
  `read_colors` tinyint(1) NOT NULL DEFAULT '0',
  `write_colors` tinyint(1) NOT NULL DEFAULT '0',
  `read_charts` tinyint(1) NOT NULL DEFAULT '1',
  `write_charts` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `read_bookings` tinyint(1) NOT NULL DEFAULT '0',
  `write_bookings` tinyint(1) NOT NULL DEFAULT '0',
  `read_rehearsals` tinyint(1) NOT NULL DEFAULT '1',
  `write_rehearsals` tinyint(1) NOT NULL DEFAULT '1',
  `read_media` tinyint(1) NOT NULL DEFAULT '1',
  `write_media` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `Zip` char(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `City` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `StateID` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CountryID` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Address1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Address2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Address3` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emailNotifications` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `venue_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `venue_cache` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `place_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `formatted_address` text COLLATE utf8mb4_unicode_ci,
  `street_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `raw_data` json DEFAULT NULL,
  `usage_count` int NOT NULL DEFAULT '1',
  `last_used_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `venue_cache_place_id_unique` (`place_id`),
  KEY `venue_cache_name_index` (`name`),
  KEY `venue_cache_place_id_index` (`place_id`),
  KEY `venue_cache_address_index` (`address`),
  KEY `venue_cache_usage_count_last_used_at_index` (`usage_count`,`last_used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webhook_calls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_calls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `headers` json DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `exception` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

/*M!999999\- enable the sandbox mode */ 
set autocommit=0;
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2014_10_12_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2014_10_12_100000_create_password_resets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2019_08_19_000000_create_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2019_12_14_000001_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2021_04_17_222140_create_bands_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2021_04_17_222456_create_band_members_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2021_04_17_222606_create_band_events',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2021_04_17_224900_create_songs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2021_04_17_224905_create_playlist',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2021_04_17_231127_create_event_contacts',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2021_04_20_021558_create_band_owners',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2021_04_24_232713_adding_address_to_user',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2021_04_24_233522_create_states_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2021_04_25_001543_create_countries',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2021_04_25_055343_adding_address_to_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2021_04_25_061628_make_address_nullable',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2021_04_25_160231_make_fields_nullable_for_events',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2021_04_25_162357_make_event_types',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2021_04_25_165831_adding_lodging_required',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2021_04_25_172930_adding_state_id_to_events',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2021_04_25_183833_adding_nullable_to_band_id_because_laravel_is_stupid',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2021_04_25_220707_add_soft_deletes_to_events',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2021_04_25_222846_setting_default_for_lodging',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2021_04_25_230328_create_event_distance_for_members',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2021_04_30_003942_add_url_to_band',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2021_04_30_013253_create_colorways',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2021_04_30_013930_create_colorway_photos',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2021_04_30_033626_add_description_to_colorways',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2021_05_01_145710_add_foreign_keys_to_colorways',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2021_05_01_145747_add_foreign_keys_to_photos',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2021_05_01_163112_create_proposal_phases_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2021_05_01_164906_create_proposals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2021_05_02_040519_add_city_and_color_to_events',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2021_05_02_063022_add_second_line_option_to_events',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2021_05_02_070319_add_ceremony_time_and_end_time',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2021_05_04_202405_make_onsite_default_to_no',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2021_05_07_081407_enable_soft_delete_on_colors',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2021_05_08_135307_add_calendar_id_to_band',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2021_05_08_142925_add_google_event_id_to_events',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2021_05_09_113758_create_invitations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2021_05_11_172449_add_pending_to_invitations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2021_05_13_190348_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2021_05_14_181046_add_seen_to_notifications',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2021_05_15_084926_make_tags_nullable_on_colorways',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2021_05_16_145055_create_proposal_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2021_05_19_195918_change_proposal_contact_phonenumber_field_type',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2021_05_20_212028_adding_event_type_to_proposal',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2021_05_22_121241_alter_names_of_dances_and_add_production_backline',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2021_05_22_223415_add_icon_to_event_phases',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2021_05_24_225621_add_location_to_proposal',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2021_05_29_115301_add_email_notifications_to_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2021_06_01_214924_add_logo_to_bands',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2021_06_02_000535_create_contracts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2021_06_15_165158_add_status_and_image_to_contracts',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2021_06_16_212130_create_recurring_proposal_dates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2021_06_19_102832_create_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2021_06_19_120252_stripe_accounts',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2021_06_20_175200_stripe_customers',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2021_06_23_074314_update_event_contacts',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2021_06_23_213510_add_client_notes_to_proposals',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2021_06_24_220726_stripe_invoice_prices',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2021_06_24_221712_create_stripe_products',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2021_06_25_161037_create_payment_statuses_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2021_06_29_223101_make_notes_nullable_in_proposals',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2021_06_30_215934_add_event_id_to_proposal',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2021_08_21_083208_create_charts_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2021_10_10_173534_create_user_permissions_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2021_10_17_133713_chart_uploads',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2021_10_17_134158_upload_types',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2021_11_03_165135_add_paid_to_proposal',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2021_11_03_180500_make_payments',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2021_11_05_121114_add_timestamps_to_payments',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2021_11_08_173605_add_received_date_to_payments',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2021_11_23_222251_create_questionairres',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2021_11_25_233700_questionnaire_components',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2022_03_22_220446_addcolorwaytext_to_band_events',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2022_05_08_223555_add_stripe_id_to_invoices',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2022_05_11_150652_adding_stripe_customers_again',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2022_05_12_000516_add_convenience_fee_field_to_invoices',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2022_05_12_010548_add_proposal_contact_id_to_stripe_customers',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2022_05_21_113915_create_editable_contracts_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2022_05_25_215422_add_key_to_invitations',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2024_08_22_155252_add_event_id_index_to_event_contacts',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2024_08_22_160523_add_proposal_id_index_to_contracts',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2024_08_22_172925_add_event_time_index_to_band_events',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2024_08_27_123033_add_read_write_bookings_permissions_to_user_permissions',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2024_08_27_131417_create_bookings_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2024_08_27_160802_contacts',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2024_08_27_162125_booking_contacts',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2024_09_09_210356_create_events',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2024_09_10_133744_create_payments_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2024_09_10_162345_rename_contracts_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2024_09_10_163841_create_contracts_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2024_10_07_103650_create_webhook_calls_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2024_11_26_133212_update_stripe_customers_column_names',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2024_11_26_140358_update_proposal_id_to_booking_id_on_invoices',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2024_11_27_051115_update_columns_for_bands_on_stripe_products',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2024_11_27_061055_add_status_and_invoice_id_to_payments',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2025_08_20_153257_add_google_calendar_id_to_bookings',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2025_08_20_215435_make_calendars_for_bands',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2025_08_20_220307_remove_calendar_id_from_band',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2025_08_22_095626_calendar_access',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2025_08_23_121426_create_google_events',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2025_08_24_125529_create_jobs_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2025_10_21_000001_create_rehearsal_schedules_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2025_10_21_000002_create_rehearsals_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2025_10_21_000003_create_rehearsal_associations_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2025_10_21_000004_add_rehearsal_permissions_to_user_permissions',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2025_10_21_175342_add_day_and_time_to_rehearsal_schedules',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2025_10_21_180011_add_is_cancelled_to_rehearsals',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2025_10_22_095748_add_day_of_month_to_rehearsal_schedules_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2025_10_22_100335_add_monthly_pattern_to_rehearsal_schedules_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2025_10_22_100836_update_rehearsal_schedules_for_google_calendar_pattern',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2025_10_27_211329_add_band_id_to_rehearsals_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2025_10_28_100850_create_activity_log_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2025_10_28_100851_add_event_column_to_activity_log_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2025_10_28_100852_add_batch_uuid_column_to_activity_log_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2025_10_29_141103_create_band_payout_configs_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2025_10_29_165308_add_member_payout_details_to_band_payout_configs',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2025_10_29_200000_create_band_payment_groups_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2025_10_29_200001_create_band_payment_group_members_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2025_10_29_200002_add_payment_groups_to_band_payout_configs',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2025_11_10_000001_add_auth_fields_to_contacts_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2025_11_10_000002_add_stripe_account_id_to_stripe_customers_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2025_11_12_000001_add_payer_to_payments_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2025_11_12_115644_add_stripe_url_to_invoices_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2025_11_13_212325_add_password_change_required_to_contacts_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2025_11_15_122744_create_band_api_tokens_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2025_11_15_123853_create_permission_tables',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2025_11_22_000001_create_payouts_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2025_11_22_000002_create_payout_adjustments_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2025_11_22_000003_fix_payout_amount_column_types',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2025_12_13_000001_create_event_attachments_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2025_12_16_114146_create_venue_cache_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2025_12_17_120000_create_media_library_tables',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2025_12_17_120001_add_media_permissions_to_user_permissions',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2025_12_17_164541_add_folder_support_to_media_files',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2025_12_17_180000_create_media_folders_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2025_12_27_000001_create_google_drive_connections_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2025_12_27_000002_create_google_drive_folders_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2025_12_27_000003_add_drive_metadata_to_media_files',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2025_12_27_000004_create_google_drive_sync_logs_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2026_01_10_000001_create_event_members_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2026_01_10_000002_create_rosters_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2026_01_10_000003_create_roster_members_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2026_01_10_000004_add_roster_id_to_events',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2026_01_10_000005_update_event_members_for_roster_system',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2026_01_10_133806_remove_unique_constraint_from_rosters_table',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2026_01_10_135346_add_pending_to_attendance_status_enum',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2026_01_10_140258_add_role_to_event_members_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2026_01_11_180403_create_substitute_call_lists_table',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2026_01_11_213439_add_custom_player_fields_to_substitute_call_lists_table',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2026_01_11_233746_add_flow_diagram_to_band_payout_configs_table',40);
commit;
