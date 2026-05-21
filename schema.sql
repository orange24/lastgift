-- LastGift schema
-- MySQL 5.6+ / MariaDB 10.x compatible
-- Run once on a fresh database

SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE TABLE IF NOT EXISTS admins (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username        VARCHAR(64) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    display_name    VARCHAR(128) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaigns (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(80) NOT NULL,
    deceased_name   VARCHAR(160) NOT NULL,
    relation        VARCHAR(120) DEFAULT NULL,        -- เช่น "คุณพ่อของเพื่อนสมชาย ห้อง A"
    hero_image      VARCHAR(255) DEFAULT NULL,
    eulogy          TEXT,
    status          ENUM('active','closed') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_campaigns_slug (slug),
    KEY ix_campaigns_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS donations (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id     INT UNSIGNED NOT NULL,
    donor_name      VARCHAR(160) NOT NULL,
    room            ENUM('A','B') NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    slip_path       VARCHAR(255) DEFAULT NULL,
    message         TEXT,
    status          ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_at     DATETIME DEFAULT NULL,
    verified_by     INT UNSIGNED DEFAULT NULL,
    ip_hash         CHAR(64) DEFAULT NULL,            -- sha256(ip + salt) สำหรับ rate-limit
    PRIMARY KEY (id),
    KEY ix_donations_campaign (campaign_id, status),
    KEY ix_donations_created (created_at),
    CONSTRAINT fk_donations_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_donations_admin    FOREIGN KEY (verified_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    name            VARCHAR(64) NOT NULL,
    value           TEXT,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เริ่มต้นค่าตั้งต้น (แก้ทีหลังได้ในหน้า admin)
INSERT INTO settings (name, value) VALUES
    ('site_title',       'ร่วมทำบุญ - รุ่นเรา'),
    ('site_subtitle',    'รวมน้ำใจให้เพื่อนผู้จากไป และครอบครัวเพื่อนๆ ในรุ่น'),
    ('bank_name',        'ธนาคารกสิกรไทย'),
    ('bank_account_no',  '000-0-00000-0'),
    ('bank_account_name','ชื่อบัญชีรับเงิน'),
    ('promptpay_id',     '0812345678'),
    ('promptpay_type',   'phone')        -- phone | nid | ewallet
ON DUPLICATE KEY UPDATE value = VALUES(value);
