-- 根據目前 PHP 程式碼推導出的資料庫初始化腳本
-- 對應 config.php: file_storage_db

CREATE DATABASE IF NOT EXISTS `file_storage_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `file_storage_db`;

-- 1) 使用者
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'user',
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('online','idle','away','hidden','offline') NOT NULL DEFAULT 'offline',
  `last_active` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) 資料夾
CREATE TABLE IF NOT EXISTS `folders` (
  `folder_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `parent_folder` INT UNSIGNED NOT NULL DEFAULT 0,
  `folder_name` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`folder_id`),
  KEY `idx_folders_user_parent` (`user_id`, `parent_folder`),
  CONSTRAINT `fk_folders_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) 檔案
CREATE TABLE IF NOT EXISTS `files` (
  `file_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `folder_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `original_filename` VARCHAR(255) NOT NULL,
  `stored_filename` VARCHAR(255) NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`file_id`),
  KEY `idx_files_user_folder_created` (`user_id`, `folder_id`, `created_at`),
  KEY `idx_files_folder_id` (`folder_id`),
  CONSTRAINT `fk_files_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 注意：程式把 folder_id=0 視為根目錄，因此不對 folder_id 建立外鍵限制。

-- 4) 檔案分享連結
CREATE TABLE IF NOT EXISTS `file_shares` (
  `share_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_id` INT UNSIGNED NOT NULL,
  `share_token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NULL DEFAULT NULL,
  `share_password` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`share_id`),
  UNIQUE KEY `uk_file_shares_token` (`share_token`),
  KEY `idx_file_shares_file` (`file_id`),
  KEY `idx_file_shares_expires` (`expires_at`),
  CONSTRAINT `fk_file_shares_file`
    FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) 點對點傳送檔案
CREATE TABLE IF NOT EXISTS `shared_files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` INT UNSIGNED NOT NULL,
  `recipient_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `original_filename` VARCHAR(255) NULL DEFAULT NULL,
  `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_shared_files_recipient_read` (`recipient_id`, `is_read`),
  KEY `idx_shared_files_sender` (`sender_id`),
  KEY `idx_shared_files_sent_at` (`sent_at`),
  CONSTRAINT `fk_shared_files_sender`
    FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shared_files_recipient`
    FOREIGN KEY (`recipient_id`) REFERENCES `users` (`user_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) 通知
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` INT UNSIGNED NULL DEFAULT NULL,
  `recipient_id` INT UNSIGNED NOT NULL,
  `type` ENUM('message','file','system') NOT NULL DEFAULT 'system',
  `message` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_recipient_read_created` (`recipient_id`, `is_read`, `created_at`),
  KEY `idx_notifications_sender` (`sender_id`),
  CONSTRAINT `fk_notifications_sender`
    FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_notifications_recipient`
    FOREIGN KEY (`recipient_id`) REFERENCES `users` (`user_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7) 聊天訊息
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` INT UNSIGNED NOT NULL,
  `recipient_id` INT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_pair_time` (`sender_id`, `recipient_id`, `sent_at`),
  KEY `idx_chat_recipient` (`recipient_id`),
  CONSTRAINT `fk_chat_sender`
    FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_chat_recipient`
    FOREIGN KEY (`recipient_id`) REFERENCES `users` (`user_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8) 公告
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(100) NOT NULL,
  `content` TEXT NOT NULL,
  `start_date` DATETIME NULL DEFAULT NULL,
  `end_date` DATETIME NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_announcements_active_date` (`is_active`, `start_date`, `end_date`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 預設管理員（可選，密碼請自行修改）
-- INSERT INTO users (username, password, email, role, is_admin, status, last_active)
-- VALUES ('admin', 'admin123', 'admin@example.com', 'admin', 1, 'online', NOW());
