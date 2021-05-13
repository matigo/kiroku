/* *************************************************************************
 * @author Jason F. Irwin
 *
 * This is the main SQL DataTable Definition for the system
 * ************************************************************************* */
CREATE DATABASE `journals` DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE USER 'kapi'@'localhost' IDENTIFIED WITH mysql_native_password BY 'superSecretPassword!123';
GRANT ALL ON `journals`.* TO 'kapi'@'localhost';

/** ************************************************************************* *
 *  Create Sequence (Preliminaries)
 ** ************************************************************************* */
DROP TABLE IF EXISTS `Type`;
CREATE TABLE IF NOT EXISTS `Type` (
    `code`          varchar(64)                                 NOT NULL    ,
    `description`   varchar(80)                                 NOT NULL    DEFAULT '',

    `is_deleted`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `created_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_type_main` ON `Type` (`is_deleted`, `code`);

INSERT INTO `Type` (`code`, `description`)
VALUES ('system.invalid', 'Invalid Type'), ('system.unknown', 'Unknown Type'),
       ('pin.red', 'Pin (Red Colour)'), ('pin.blue', 'Pin (Blue Colour)'), ('pin.yellow', 'Pin (Yellow)'), ('pin.black', 'Pin (Black)'),
       ('pin.green', 'Pin (Green)'), ('pin.orange', 'Pin (Orange)'), ('pin.none', 'No Pin Assignment'),
       ('account.admin', 'Administrator Account'), ('account.normal', 'Standard Account'), ('account.anonymous', 'Anonymous Account'), ('account.expired', 'Expired Account'),
       ('journal.default', 'A basic Journal'), ('journal.public', 'A publicly-viewable Journal'), ('journal.password', 'A password-protected Journal'),
       ('visibility.none', 'An Invisible Item'), ('visibility.password', 'A Password-Protected Item'), ('visibility.private', 'A Private Item'), ('visibility.public', 'A Public Item')
    ON DUPLICATE KEY UPDATE `is_deleted` = 'N';

UPDATE `Type`
   SET `created_at` = DATE_FORMAT(Now(), '%Y-%m-01 00:00:00'),
       `updated_at` = DATE_FORMAT(Now(), '%Y-%m-01 00:00:00');

DELIMITER ;;
DROP TRIGGER IF EXISTS `before_update_type`;;
CREATE TRIGGER `before_update_type`
 BEFORE UPDATE ON `Type`
   FOR EACH ROW
 BEGIN
    IF new.`updated_at` <= DATE_SUB(Now(), INTERVAL 5 SECOND) THEN
        SET new.`updated_at` = Now();
    END IF;
   END
;;
DELIMITER ;

DROP TABLE IF EXISTS `Language`;
CREATE TABLE IF NOT EXISTS `Language` (
    `code`          varchar(6)                                  NOT NULL    ,
    `name`          varchar(80)                                 NOT NULL    ,
    `iso-639-1`     char(2)                                     NOT NULL    ,
    `iso-639-2`     char(3)                                         NULL    ,
    `iso-639-3`     char(3)                                         NULL    ,
    `rtl`           enum('N','Y')                               NOT NULL    DEFAULT 'N',

    `is_deleted`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `created_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_lang_iso` ON `Language` (`iso-639-1`);

INSERT INTO `Language` (`code`, `name`, `iso-639-1`, `iso-639-2`)
VALUES ('en-uk', 'English (UK)', 'en', 'eng'),
       ('en-us', 'English (US)', 'en', 'eng'),
       ('eo-us', 'Esperanto', 'eo', 'epo'),
       ('es-es', 'Spanish', 'es', 'spa'),
       ('ja-jp', 'Japanese', 'ja', 'jpn'),
       ('ko-kr', 'Korean', 'ko', 'kor'),
       ('ru-ru', 'Russian', 'ru', 'rus'),
       ('si-si', 'Sinhala', 'si', 'sin'),
       ('ta-ta', 'Tamil', 'ta', 'tam');

UPDATE `Language`
   SET `created_at` = DATE_FORMAT(Now(), '%Y-%m-01 00:00:00'),
       `updated_at` = DATE_FORMAT(Now(), '%Y-%m-01 00:00:00');

DELIMITER ;;
DROP TRIGGER IF EXISTS `before_update_language`;;
CREATE TRIGGER `before_update_language`
 BEFORE UPDATE ON `Language`
   FOR EACH ROW
 BEGIN
    IF new.`updated_at` <= DATE_SUB(Now(), INTERVAL 5 SECOND) THEN
        SET new.`updated_at` = Now();
    END IF;
   END
;;
DELIMITER ;

/** ************************************************************************* *
 *  Create Sequence (Authentication)
 ** ************************************************************************* */
DROP TABLE IF EXISTS `Account`;
CREATE TABLE IF NOT EXISTS `Account` (
    `id`            int(11)        UNSIGNED                     NOT NULL    AUTO_INCREMENT,
    `login`         varchar(160)                                NOT NULL    ,
    `password`      varchar(128)                                NOT NULL    DEFAULT '',

    `last_name`     varchar(80)                                 NOT NULL    DEFAULT '',
    `first_name`    varchar(80)                                 NOT NULL    DEFAULT '',
    `display_name`  varchar(80)                                 NOT NULL    DEFAULT '',

    `type`          varchar(64)                                 NOT NULL    DEFAULT 'account.expired',
    `guid`          char(36)                                    NOT NULL    ,

    `language_code` varchar(6)                                  NOT NULL    DEFAULT 'en-us',
    `avatar`        varchar(160)                                NOT NULL    DEFAULT 'default.png',
    `timezone`      varchar(40)                                 NOT NULL    DEFAULT 'UTC',

    `is_deleted`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `created_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`type`) REFERENCES `Type` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_account_main` ON `Account` (`login`);
CREATE INDEX `idx_account_guid` ON `Account` (`guid`);

DROP TABLE IF EXISTS `AccountMeta`;
CREATE TABLE IF NOT EXISTS `AccountMeta` (
    `account_id`    int(11)        UNSIGNED                     NOT NULL    ,
    `key`           varchar(64)                                 NOT NULL    ,
    `value`         varchar(2048)                               NOT NULL    DEFAULT '',

    `is_deleted`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `created_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`account_id`, `key`),
    FOREIGN KEY (`account_id`) REFERENCES `Account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_ameta_main` ON `AccountMeta` (`account_id`, `key`);

DROP TABLE IF EXISTS `AccountPass`;
CREATE TABLE IF NOT EXISTS `AccountPass` (
    `id`            int(11)        UNSIGNED                     NOT NULL    AUTO_INCREMENT,
    `account_id`    int(11)        UNSIGNED                     NOT NULL    ,
    `password`      varchar(128)                                NOT NULL    DEFAULT '',

    `is_deleted`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `created_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`account_id`) REFERENCES `Account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_acctpass_main` ON `AccountPass` (`account_id`);

DELIMITER ;;
DROP TRIGGER IF EXISTS `before_account`;;
CREATE TRIGGER `before_account`
BEFORE INSERT ON `Account`
   FOR EACH ROW
 BEGIN
    IF new.`guid` IS NULL THEN
        SET new.`guid` = (SELECT CONCAT(SUBSTRING(tmp.`hash`, 1, 8), '-',
                                        SUBSTRING(tmp.`hash`, 9, 4), '-',
                                        SUBSTRING(tmp.`hash`, 13, 4), '-',
                                        SUBSTRING(tmp.`hash`, 17, 4), '-',
                                        SUBSTRING(tmp.`hash`, 21, 12)) as `guid`
                            FROM (SELECT MD5(CONCAT(UNIX_TIMESTAMP(Now()), '-', ROUND(RAND() * (RAND() + RAND()), 6), '-', uuid())) as `hash`) tmp);
    END IF;
   END
;;
DROP TRIGGER IF EXISTS `before_update_account`;;
CREATE TRIGGER `before_update_account`
BEFORE UPDATE ON `Account`
   FOR EACH ROW
 BEGIN
    IF new.`guid` <> old.`guid` THEN
        SET new.`guid` = old.`guid`;
    END IF;
    SET new.`updated_at` = Now();
   END
;;
DROP TRIGGER IF EXISTS `before_accountmeta`;;
CREATE TRIGGER `before_accountmeta`
BEFORE INSERT ON `AccountMeta`
   FOR EACH ROW
 BEGIN
    IF IFNULL(new.`value`, '') = '' THEN
        SET new.`is_deleted` = 'Y';
    END IF;
   END
;;
DROP TRIGGER IF EXISTS `before_update_accountmeta`;;
CREATE TRIGGER `before_update_accountmeta`
 BEFORE UPDATE ON `AccountMeta`
   FOR EACH ROW
 BEGIN
    IF IFNULL(new.`value`, '') = '' THEN
        SET new.`is_deleted` = 'Y';
    END IF;
    SET new.`updated_at` = Now();
   END
;;
DROP TRIGGER IF EXISTS `after_insert_account`;;
CREATE TRIGGER `after_insert_account`
 AFTER INSERT ON `Account`
   FOR EACH ROW
 BEGIN
    INSERT INTO `AccountPass` (`account_id`, `password`)
    SELECT new.`id` as `account_id`, new.`password`
     WHERE new.`is_deleted` = 'N' and new.`password` <> IFNULL((SELECT z.`password` FROM `AccountPass` z
                                                                 WHERE z.`is_deleted` = 'N' and z.`account_id` = new.`id`
                                                                 ORDER BY z.`id` DESC LIMIT 1), '');
   END
;;
DROP TRIGGER IF EXISTS `after_update_account`;;
CREATE TRIGGER `after_update_account`
 AFTER UPDATE ON `Account`
   FOR EACH ROW
 BEGIN
    INSERT INTO `AccountPass` (`account_id`, `password`)
    SELECT new.`id` as `account_id`, new.`password`
     WHERE new.`is_deleted` = 'N' and new.`password` <> IFNULL((SELECT z.`password` FROM `AccountPass` z
                                                                 WHERE z.`is_deleted` = 'N' and z.`account_id` = new.`id`
                                                                 ORDER BY z.`id` DESC LIMIT 1), '');
   END
;;
DELIMITER ;

/** ************************************************************************* *
 *  Create Sequence (Tokens)
 ** ************************************************************************* */
DROP TABLE IF EXISTS `Tokens`;
CREATE TABLE IF NOT EXISTS `Tokens` (
    `id`            int(11)        UNSIGNED                     NOT NULL    AUTO_INCREMENT,
    `guid`          varchar(50)                                 NOT NULL    ,
    `account_id`    int(11)        UNSIGNED                     NOT NULL    ,

    `expires_at`    timestamp                                       NULL    ,
    `req_count`     int(11)        UNSIGNED                     NOT NULL    DEFAULT 1,

    `is_deleted`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `created_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`account_id`) REFERENCES `Account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_token_main` ON `Tokens` (`id`, `guid`, `is_deleted`);
CREATE INDEX `idx_token_acct` ON `Tokens` (`account_id`, `is_deleted`);

DELIMITER ;;
DROP TRIGGER IF EXISTS `before_update_tokens`;;
CREATE TRIGGER `before_update_tokens`
 BEFORE UPDATE ON `Tokens`
   FOR EACH ROW
 BEGIN
    IF new.`guid` <> old.`guid` THEN
        SET new.`guid` = old.`guid`;
    END IF;
    IF new.`updated_at` <= DATE_SUB(Now(), INTERVAL 5 SECOND) THEN
        SET new.`updated_at` = Now();
    END IF;
    IF new.`is_deleted` = 'N' THEN
        SET new.`req_count` = IFNULL(old.`req_count`, 1) + 1;
        IF new.`expires_at` IS NOT NULL AND new.`expires_at` <= Now() THEN
            SET new.`is_deleted` = 'Y';
        END IF;
    END IF;
   END
;;
DELIMITER ;

/** ************************************************************************* *
 *  Create Sequence (Sites)
 ** ************************************************************************* */
DROP TABLE IF EXISTS `Site`;
CREATE TABLE IF NOT EXISTS `Site` (
    `id`            int(11)        UNSIGNED                     NOT NULL    AUTO_INCREMENT,
    `account_id`    int(11)        UNSIGNED                     NOT NULL    ,
    `name`          varchar(128)                                NOT NULL    ,
    `description`   varchar(255)                                NOT NULL    DEFAULT '',
    `keywords`      varchar(255)                                NOT NULL    DEFAULT '',

    `https`         enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `theme`         varchar(20)                                 NOT NULL    DEFAULT 'author',
    `guid`          char(36)                                    NOT NULL    ,
    `version`       varchar(64)                                 NOT NULL    ,

    `is_default`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `is_deleted`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `created_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`account_id`) REFERENCES `Account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_site_main` ON `Site` (`guid`, `is_deleted`);
CREATE INDEX `idx_site_defs` ON `Site` (`is_default` DESC, `is_deleted`);
CREATE INDEX `idx_site_acct` ON `Site` (`account_id`, `is_deleted`);

DROP TABLE IF EXISTS `SiteUrl`;
CREATE TABLE IF NOT EXISTS `SiteUrl` (
    `id`            int(11)        UNSIGNED                     NOT NULL    AUTO_INCREMENT,
    `site_id`       int(11)        UNSIGNED                     NOT NULL    ,
    `url`           varchar(140)                                NOT NULL    ,
    `is_active`     enum('N','Y')                               NOT NULL    DEFAULT 'Y',

    `is_deleted`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `created_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`site_id`) REFERENCES `Site` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_surl_main` ON `SiteUrl` (`url`);
CREATE INDEX `idx_surl_actv` ON `SiteUrl` (`is_active`, `site_id`);

DROP TABLE IF EXISTS `SiteMeta`;
CREATE TABLE IF NOT EXISTS `SiteMeta` (
    `site_id`       int(11)        UNSIGNED                     NOT NULL    ,
    `key`           varchar(64)                                 NOT NULL    ,
    `value`         varchar(2048)                               NOT NULL    DEFAULT '',

    `is_deleted`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `created_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`site_id`, `key`),
    FOREIGN KEY (`site_id`) REFERENCES `Site` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_smeta_main` ON `SiteMeta` (`site_id`, `is_deleted`);

DELIMITER ;;
DROP TRIGGER IF EXISTS `before_site`;;
CREATE TRIGGER `before_site`
BEFORE INSERT ON `Site`
   FOR EACH ROW
 BEGIN
    IF new.`guid` IS NULL THEN
        SET new.`guid` = (SELECT CONCAT(SUBSTRING(tmp.`hash`, 1, 8), '-',
                                        SUBSTRING(tmp.`hash`, 9, 4), '-',
                                        SUBSTRING(tmp.`hash`, 13, 4), '-',
                                        SUBSTRING(tmp.`hash`, 17, 4), '-',
                                        SUBSTRING(tmp.`hash`, 21, 12)) as `guid`
                            FROM (SELECT MD5(CONCAT(UNIX_TIMESTAMP(Now()), '-', ROUND(RAND() * (RAND() + RAND()), 6), '-', uuid())) as `hash`) tmp);
    END IF;
    SET new.`version` = CONCAT(UNIX_TIMESTAMP(Now()), '-', RIGHT(ROUND(RAND() * (RAND() + RAND()), 6), 4));
   END
;;
DROP TRIGGER IF EXISTS `before_update_site`;;
CREATE TRIGGER `before_update_site`
 BEFORE UPDATE ON `Site`
   FOR EACH ROW
 BEGIN
    IF new.`guid` <> old.`guid` THEN
        SET new.`guid` = old.`guid`;
    END IF;
    SET new.`version` = CONCAT(UNIX_TIMESTAMP(Now()), '-', RIGHT(ROUND(RAND() * (RAND() + RAND()), 6), 4));
    SET new.`updated_at` = Now();
   END
;;
DROP TRIGGER IF EXISTS `before_sitemeta`;;
CREATE TRIGGER `before_sitemeta`
BEFORE INSERT ON `SiteMeta`
   FOR EACH ROW
 BEGIN
    IF IFNULL(new.`value`, '') = '' THEN
        SET new.`is_deleted` = 'Y';
    END IF;
   END
;;
DROP TRIGGER IF EXISTS `before_update_sitemeta`;;
CREATE TRIGGER `before_update_sitemeta`
 BEFORE UPDATE ON `SiteMeta`
   FOR EACH ROW
 BEGIN
    IF IFNULL(new.`value`, '') = '' THEN
        SET new.`is_deleted` = 'Y';
    END IF;
    SET new.`updated_at` = Now();
   END
;;
DROP TRIGGER IF EXISTS `before_update_siteurl`;;
CREATE TRIGGER `before_update_siteurl`
 BEFORE UPDATE ON `SiteUrl`
   FOR EACH ROW
 BEGIN
    SET new.`updated_at` = Now();
   END
;;
DELIMITER ;

/** ************************************************************************* *
 *  Create Sequence (Statistics)
 ** ************************************************************************* */
DROP TABLE IF EXISTS `UsageStats`;
CREATE TABLE IF NOT EXISTS `UsageStats` (
    `id`            int(11)        UNSIGNED                     NOT NULL    AUTO_INCREMENT,
    `site_id`       int(11)        UNSIGNED                         NULL    ,
    `token_id`      int(11)        UNSIGNED                         NULL    ,

    `http_code`     smallint       UNSIGNED                     NOT NULL    DEFAULT 200,
    `request_type`  varchar(8)                                  NOT NULL    DEFAULT 'GET',
    `request_uri`   varchar(512)                                NOT NULL    ,
    `referrer`      varchar(1024)                                   NULL    ,

    `event_at`      timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `event_on`      varchar(10)                                 NOT NULL    ,
    `from_ip`       varchar(64)                                 NOT NULL    ,

    `agent`         varchar(2048)                                   NULL    ,
    `platform`      varchar(64)                                     NULL    ,
    `browser`       varchar(64)                                 NOT NULL    DEFAULT 'unknown',
    `version`       varchar(64)                                     NULL    ,

    `seconds`       decimal(16,8)                               NOT NULL    DEFAULT 0,
    `sqlops`        smallint       UNSIGNED                     NOT NULL    DEFAULT 0,
    `message`       varchar(512)                                    NULL    ,

    `is_deleted`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `created_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    FOREIGN KEY (`site_id`) REFERENCES `Site` (`id`),
    FOREIGN KEY (`token_id`) REFERENCES `Tokens` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_stats_main` ON `UsageStats` (`event_on`);

DELIMITER ;;
DROP TRIGGER IF EXISTS `before_usagestats`;;
CREATE TRIGGER `before_usagestats`
BEFORE INSERT ON `UsageStats`
   FOR EACH ROW
 BEGIN
    IF IFNULL(new.`event_on`, '') = '' THEN
        SET new.`event_on` = DATE_FORMAT(new.`event_at`, '%Y-%m-%d');
    END IF;
   END
;;
DROP TRIGGER IF EXISTS `before_update_usagestats`;;
CREATE TRIGGER `before_update_usagestats`
 BEFORE UPDATE ON `UsageStats`
   FOR EACH ROW
 BEGIN
    IF IFNULL(new.`event_on`, '') = '' THEN
        SET new.`event_on` = DATE_FORMAT(new.`event_at`, '%Y-%m-%d');
    END IF;
    SET new.`updated_at` = Now();
   END
;;
DELIMITER ;

/** ************************************************************************* *
 *  File Resources
 ** ************************************************************************* */
DROP TABLE IF EXISTS `File`;
CREATE TABLE IF NOT EXISTS `File` (
    `id`            int(11)        UNSIGNED                     NOT NULL    AUTO_INCREMENT,
    `account_id`    int(11)        UNSIGNED                     NOT NULL    ,

    `name`          varchar(256)                                NOT NULL    ,
    `local_name`    varchar(80)                                 NOT NULL    ,
    `hash`          varchar(64)                                 NOT NULL    ,
    `bytes`         int(11)        UNSIGNED                     NOT NULL    DEFAULT 0,
    `location`      varchar(1024)                               NOT NULL    ,
    `mime`          varchar(64)                                 NOT NULL    ,
    `guid`          char(36)                                    NOT NULL    ,

    `expires_at`    timestamp                                       NULL    ,

    `is_deleted`    enum('N','Y')           NOT NULL    DEFAULT 'N',
    `created_at`    timestamp               NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `created_by`    int(11)        UNSIGNED NOT NULL    ,
    `updated_at`    timestamp               NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_by`    int(11)        UNSIGNED NOT NULL    ,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`account_id`) REFERENCES `Account` (`id`),
    FOREIGN KEY (`created_by`) REFERENCES `Account` (`id`),
    FOREIGN KEY (`updated_by`) REFERENCES `Account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_file_acct` ON `File` (`account_id`);
CREATE INDEX `idx_file_guid` ON `File` (`guid`);

DROP TABLE IF EXISTS `FileMeta`;
CREATE TABLE IF NOT EXISTS `FileMeta` (
    `file_id`       int(11)        UNSIGNED                     NOT NULL    ,
    `key`           varchar(64)                                 NOT NULL    ,
    `value`         varchar(2048)                               NOT NULL    DEFAULT '',

    `is_deleted`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `created_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`file_id`, `key`),
    FOREIGN KEY (`file_id`) REFERENCES `File` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_fmeta_main` ON `FileMeta` (`is_deleted`, `file_id`);

DROP TABLE IF EXISTS `FileUrl`;
CREATE TABLE IF NOT EXISTS `FileUrl` (
    `file_id`       int(11)        UNSIGNED                     NOT NULL    ,
    `guid`          char(36)                                    NOT NULL    ,

    `password`      varchar(128)                                    NULL    ,
    `expires_at`    timestamp                                       NULL    ,

    `is_deleted`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `created_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`file_id`, `key`),
    FOREIGN KEY (`file_id`) REFERENCES `File` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_furl_main` ON `FileMeta` (`guid`);

DELIMITER ;;
DROP TRIGGER IF EXISTS `before_file`;;
CREATE TRIGGER `before_file`
BEFORE INSERT ON `File`
   FOR EACH ROW
 BEGIN
    IF new.`guid` IS NULL THEN
        SET new.`guid` = (SELECT CONCAT(SUBSTRING(tmp.`hash`, 1, 8), '-',
                                        SUBSTRING(tmp.`hash`, 9, 4), '-',
                                        SUBSTRING(tmp.`hash`, 13, 4), '-',
                                        SUBSTRING(tmp.`hash`, 17, 4), '-',
                                        SUBSTRING(tmp.`hash`, 21, 12)) as `guid`
                            FROM (SELECT MD5(CONCAT(UNIX_TIMESTAMP(Now()), '-', ROUND(RAND() * (RAND() + RAND()), 6), '-', uuid())) as `hash`) tmp);
    END IF;
   END
;;
DROP TRIGGER IF EXISTS `before_update_file`;;
CREATE TRIGGER `before_update_file`
 BEFORE UPDATE ON `File`
   FOR EACH ROW
 BEGIN
    IF old.`updated_at` <= DATE_SUB(Now(), INTERVAL 5 SECOND) THEN
       SET new.`updated_at` = Now();
    END IF;
    IF old.`guid` <> new.`guid` THEN
        SET new.`guid` = old.`guid`;
    END IF;
   END
;;
DROP TRIGGER IF EXISTS `before_filemeta`;;
CREATE TRIGGER `before_filemeta`
BEFORE INSERT ON `FileMeta`
   FOR EACH ROW
 BEGIN
    IF IFNULL(new.`value`, '') = '' THEN
        SET new.`is_deleted` = 'Y';
    END IF;
   END
;;
DROP TRIGGER IF EXISTS `before_update_filemeta`;;
CREATE TRIGGER `before_update_filemeta`
 BEFORE UPDATE ON `FileMeta`
   FOR EACH ROW
 BEGIN
    IF IFNULL(new.`value`, '') = '' THEN
        SET new.`is_deleted` = 'Y';
    END IF;
    SET new.`updated_at` = Now();
   END
;;
DELIMITER ;

/** ************************************************************************* *
 *  Create Sequence (Note)
 ** ************************************************************************* */
DROP TABLE IF EXISTS `Note`;
CREATE TABLE IF NOT EXISTS `Note` (
    `id`            int(11)        UNSIGNED NOT NULL    AUTO_INCREMENT,
    `account_id`    int(11)        UNSIGNED     NULL    ,
    `parent_id`     int(11)        UNSIGNED     NULL    ,

    `content`       text                    NOT NULL    ,
    `guid`          char(36)                NOT NULL    ,
    `hash`          varchar(512)            NOT NULL    ,
    `language_code` varchar(6)                  NULL    ,

    `is_deleted`    enum('N','Y')           NOT NULL    DEFAULT 'N',
    `created_at`    timestamp               NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `created_by`    int(11)        UNSIGNED NOT NULL    ,
    `updated_at`    timestamp               NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_by`    int(11)        UNSIGNED NOT NULL    ,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`parent_id`) REFERENCES `Note` (`id`),
    FOREIGN KEY (`account_id`) REFERENCES `Account` (`id`),
    FOREIGN KEY (`created_by`) REFERENCES `Account` (`id`),
    FOREIGN KEY (`updated_by`) REFERENCES `Account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_note_main` ON `Note` (`parent_id`);

DROP TABLE IF EXISTS `NoteMeta`;
CREATE TABLE IF NOT EXISTS `NoteMeta` (
    `note_id`       int(11)        UNSIGNED                     NOT NULL    ,
    `key`           varchar(64)                                 NOT NULL    ,
    `value`         varchar(2048)                               NOT NULL    DEFAULT '',

    `is_deleted`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `created_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`note_id`, `key`),
    FOREIGN KEY (`note_id`) REFERENCES `Note` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_notemeta_main` ON `NoteMeta` (`note_id`, `key`);

DROP TABLE IF EXISTS `NoteTags`;
CREATE TABLE IF NOT EXISTS `NoteTags` (
    `note_id`       int(11)        UNSIGNED                     NOT NULL    ,
    `tag`           varchar(64)                                 NOT NULL    ,
    `name`          varchar(64)                                 NOT NULL    DEFAULT '',

    `is_deleted`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `created_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`note_id`, `tag`),
    FOREIGN KEY (`note_id`) REFERENCES `Note` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_notetags_main` ON `NoteTags` (`note_id`, `tag`);

DROP TABLE IF EXISTS `NoteWord`;
CREATE TABLE IF NOT EXISTS `NoteWord` (
    `note_id`       int(11)        UNSIGNED                     NOT NULL    ,
    `word`          varchar(256)                                NOT NULL    ,

    `is_deleted`    enum('N','Y')                               NOT NULL    DEFAULT 'N',
    `created_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    timestamp                                   NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`note_id`, `word`),
    FOREIGN KEY (`note_id`) REFERENCES `Note` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_notetags_main` ON `NoteTags` (`note_id`, `word`);

DELIMITER ;;
DROP TRIGGER IF EXISTS `before_note`;;
CREATE TRIGGER `before_note`
BEFORE INSERT ON `Note`
   FOR EACH ROW
 BEGIN
    IF new.`updated_by` IS NULL THEN
        SET new.`updated_by` = new.`created_by`;
    END IF;
    IF new.`guid` IS NULL THEN
        SET new.`guid` = (SELECT CONCAT(SUBSTRING(tmp.`hash`, 1, 8), '-',
                                        SUBSTRING(tmp.`hash`, 9, 4), '-',
                                        SUBSTRING(tmp.`hash`, 13, 4), '-',
                                        SUBSTRING(tmp.`hash`, 17, 4), '-',
                                        SUBSTRING(tmp.`hash`, 21, 12)) as `guid`
                            FROM (SELECT MD5(CONCAT(UNIX_TIMESTAMP(Now()), '-', ROUND(RAND() * (RAND() + RAND()), 6), '-', uuid())) as `hash`) tmp);
    END IF;
    SET new.`hash` = sha2(CONCAT(MD5(DATE_FORMAT(new.`created_at`, '%Y-%m-%d %H:%i:00')), '::', new.`content`), 512);
   END
;;
DROP TRIGGER IF EXISTS `before_update_note`;;
CREATE TRIGGER `before_update_note`
 BEFORE UPDATE ON `Note`
   FOR EACH ROW
 BEGIN
    IF old.`updated_at` <= DATE_SUB(Now(), INTERVAL 5 SECOND) THEN
       SET new.`updated_at` = Now();
    END IF;
    IF old.`guid` <> new.`guid` THEN
        SET new.`guid` = old.`guid`;
    END IF;
    SET new.`hash` = sha2(CONCAT(MD5(DATE_FORMAT(new.`created_at`, '%Y-%m-%d %H:%i:00')), '::', new.`content`), 512);
   END
;;
DROP TRIGGER IF EXISTS `before_notemeta`;;
CREATE TRIGGER `before_notemeta`
BEFORE INSERT ON `NoteMeta`
   FOR EACH ROW
 BEGIN
    IF IFNULL(new.`value`, '') = '' THEN
        SET new.`is_deleted` = 'Y';
    END IF;
   END
;;
DROP TRIGGER IF EXISTS `before_update_notemeta`;;
CREATE TRIGGER `before_update_notemeta`
 BEFORE UPDATE ON `NoteMeta`
   FOR EACH ROW
 BEGIN
    IF IFNULL(new.`value`, '') = '' THEN
        SET new.`is_deleted` = 'Y';
    END IF;
    SET new.`updated_at` = Now();
   END
;;
DROP TRIGGER IF EXISTS `before_notetags`;;
CREATE TRIGGER `before_notetags`
BEFORE INSERT ON `NoteTags`
   FOR EACH ROW
 BEGIN
    SET new.`tags` = TRIM(LOWER(IFNULL(new.`tags`, '')));
    IF IFNULL(new.`tags`, '') = '' THEN
        SET new.`is_deleted` = 'Y';
    END IF;
   END
;;
DROP TRIGGER IF EXISTS `before_update_notetags`;;
CREATE TRIGGER `before_update_notetags`
 BEFORE UPDATE ON `NoteTags`
   FOR EACH ROW
 BEGIN
    SET new.`tags` = TRIM(LOWER(IFNULL(new.`tags`, '')));
    IF old.`tags` <> new.`tags` THEN
        SET new.`tags` = old.`tags`;
    END IF;
    IF IFNULL(new.`tags`, '') = '' THEN
        SET new.`is_deleted` = 'Y';
    END IF;
    SET new.`updated_at` = Now();
   END
;;
DROP TRIGGER IF EXISTS `before_noteword`;;
CREATE TRIGGER `before_noteword`
BEFORE INSERT ON `NoteWord`
   FOR EACH ROW
 BEGIN
    SET new.`word` = TRIM(LOWER(IFNULL(new.`word`, '')));
    IF IFNULL(new.`word`, '') = '' THEN
        SET new.`is_deleted` = 'Y';
    END IF;
   END
;;
DROP TRIGGER IF EXISTS `before_update_notetags`;;
CREATE TRIGGER `before_update_notetags`
 BEFORE UPDATE ON `NoteTags`
   FOR EACH ROW
 BEGIN
    SET new.`word` = TRIM(LOWER(IFNULL(new.`word`, '')));
    IF old.`word` <> new.`word` THEN
        SET new.`word` = old.`word`;
    END IF;
    IF IFNULL(new.`word`, '') = '' THEN
        SET new.`is_deleted` = 'Y';
    END IF;
    SET new.`updated_at` = Now();
   END
;;
DELIMITER ;

/** ************************************************************************* *
 *  Create Sequence (Journal)
 ** ************************************************************************* */
DROP TABLE IF EXISTS `Journal`;
CREATE TABLE IF NOT EXISTS `Journal` (
    `id`            int(11)        UNSIGNED NOT NULL    AUTO_INCREMENT,
    `account_id`    int(11)        UNSIGNED NOT NULL    ,
    `name`          varchar(256)            NOT NULL    ,

    `guid`          char(36)                NOT NULL    ,
    `type`          varchar(64)             NOT NULL    DEFAULT 'journal.default',
    `version`       varchar(64)             NOT NULL    ,

    `is_deleted`    enum('N','Y')           NOT NULL    DEFAULT 'N',
    `created_at`    timestamp               NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `created_by`    int(11)        UNSIGNED NOT NULL    ,
    `updated_at`    timestamp               NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_by`    int(11)        UNSIGNED NOT NULL    ,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`type`) REFERENCES `Type` (`code`),
    FOREIGN KEY (`account_id`) REFERENCES `Account` (`id`),
    FOREIGN KEY (`created_by`) REFERENCES `Account` (`id`),
    FOREIGN KEY (`updated_by`) REFERENCES `Account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_jrnl_main` ON `Journal` (`is_deleted`, `account_id`);
CREATE INDEX `idx_jrnl_guid` ON `Journal` (`is_deleted`, `guid`);

DROP TABLE IF EXISTS `JournalNote`;
CREATE TABLE IF NOT EXISTS `JournalNote` (
    `id`            int(11)        UNSIGNED NOT NULL    AUTO_INCREMENT,
    `journal_id`    int(11)        UNSIGNED NOT NULL    ,
    `thread_id`     int(11)        UNSIGNED NOT NULL    ,
    `current_id`    int(11)        UNSIGNED NOT NULL    ,

    `guid`          char(36)                NOT NULL    ,

    `is_deleted`    enum('N','Y')           NOT NULL    DEFAULT 'N',
    `created_at`    timestamp               NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `created_by`    int(11)        UNSIGNED NOT NULL    ,
    `updated_at`    timestamp               NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_by`    int(11)        UNSIGNED NOT NULL    ,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`type`) REFERENCES `Type` (`code`),
    FOREIGN KEY (`journal_id`) REFERENCES `Journal` (`id`),
    FOREIGN KEY (`thread_id`)  REFERENCES `Note` (`id`),
    FOREIGN KEY (`current_id`) REFERENCES `Note` (`id`),
    FOREIGN KEY (`created_by`) REFERENCES `Account` (`id`),
    FOREIGN KEY (`updated_by`) REFERENCES `Account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_jnno_main` ON `Note` (`is_deleted`, `journal_id`);

DROP TABLE IF EXISTS `JournalAccess`;
CREATE TABLE IF NOT EXISTS `JournalAccess` (
    `id`                int(11)        UNSIGNED NOT NULL    AUTO_INCREMENT,
    `journal_id`        int(11)        UNSIGNED NOT NULL    ,
    `journal_note_id`   int(11)        UNSIGNED     NULL    ,
    `account_id`        int(11)        UNSIGNED NOT NULL    ,

    `password`          varchar(128)                NULL    ,
    `expires_at`        timestamp                   NULL    ,
    `read_only`         enum('N','Y')           NOT NULL    DEFAULT 'Y',

    `is_deleted`        enum('N','Y')           NOT NULL    DEFAULT 'N',
    `created_at`        timestamp               NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `created_by`        int(11)        UNSIGNED NOT NULL    ,
    `updated_at`        timestamp               NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_by`        int(11)        UNSIGNED NOT NULL    ,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`type`) REFERENCES `Type` (`code`),
    FOREIGN KEY (`journal_id`) REFERENCES `Journal` (`id`),
    FOREIGN KEY (`journal_note_id`) REFERENCES `JournalNote` (`id`),
    FOREIGN KEY (`account_id`) REFERENCES `Account` (`id`),
    FOREIGN KEY (`created_by`) REFERENCES `Account` (`id`),
    FOREIGN KEY (`updated_by`) REFERENCES `Account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_jnac_main` ON `JournalAccess` (`journal_id`);
CREATE INDEX `idx_jnac_acc` ON `JournalAccess` (`account_id`);

DELIMITER ;;
DROP TRIGGER IF EXISTS `before_journal`;;
CREATE TRIGGER `before_journal`
BEFORE INSERT ON `Journal`
   FOR EACH ROW
 BEGIN
    IF new.`updated_by` IS NULL THEN
        SET new.`updated_by` = new.`created_by`;
    END IF;
    IF new.`guid` IS NULL THEN
        SET new.`guid` = (SELECT CONCAT(SUBSTRING(tmp.`hash`, 1, 8), '-',
                                        SUBSTRING(tmp.`hash`, 9, 4), '-',
                                        SUBSTRING(tmp.`hash`, 13, 4), '-',
                                        SUBSTRING(tmp.`hash`, 17, 4), '-',
                                        SUBSTRING(tmp.`hash`, 21, 12)) as `guid`
                            FROM (SELECT MD5(CONCAT(UNIX_TIMESTAMP(Now()), '-', ROUND(RAND() * (RAND() + RAND()), 6), '-', uuid())) as `hash`) tmp);
    END IF;
    SET new.`version` = CONCAT(UNIX_TIMESTAMP(Now()), '-', ROUND(RAND() * (RAND() + RAND()), 6));
   END
;;
DROP TRIGGER IF EXISTS `before_update_journal`;;
CREATE TRIGGER `before_update_journal`
 BEFORE UPDATE ON `Journal`
   FOR EACH ROW
 BEGIN
    IF old.`updated_at` <= DATE_SUB(Now(), INTERVAL 5 SECOND) THEN
       SET new.`updated_at` = Now();
    END IF;
    IF old.`guid` <> new.`guid` THEN
        SET new.`guid` = old.`guid`;
    END IF;
    SET new.`version` = CONCAT(UNIX_TIMESTAMP(Now()), '-', ROUND(RAND() * (RAND() + RAND()), 6));
   END
;;
DROP TRIGGER IF EXISTS `before_journalnote`;;
CREATE TRIGGER `before_journalnote`
BEFORE INSERT ON `JournalNote`
   FOR EACH ROW
 BEGIN
    IF new.`account_id` IS NULL THEN
        SET new.`account_id` = new.`created_by`;
    END IF;
    IF new.`updated_by` IS NULL THEN
        SET new.`updated_by` = new.`created_by`;
    END IF;
    IF new.`guid` IS NULL THEN
        SET new.`guid` = (SELECT CONCAT(SUBSTRING(tmp.`hash`, 1, 8), '-',
                                        SUBSTRING(tmp.`hash`, 9, 4), '-',
                                        SUBSTRING(tmp.`hash`, 13, 4), '-',
                                        SUBSTRING(tmp.`hash`, 17, 4), '-',
                                        SUBSTRING(tmp.`hash`, 21, 12)) as `guid`
                            FROM (SELECT MD5(CONCAT(UNIX_TIMESTAMP(Now()), '-', ROUND(RAND() * (RAND() + RAND()), 6), '-', uuid())) as `hash`) tmp);
    END IF;
   END
;;
DROP TRIGGER IF EXISTS `before_update_journalnote`;;
CREATE TRIGGER `before_update_journalnote`
 BEFORE UPDATE ON `JournalNote`
   FOR EACH ROW
 BEGIN
    IF old.`account_id` <> new.`account_id` THEN
        SET new.`account_id` = old.`account_id`;
    END IF;
    IF old.`thread_id` <> new.`thread_id` THEN
        SET new.`thread_id` = old.`thread_id`;
    END IF;
    IF old.`updated_at` <= DATE_SUB(Now(), INTERVAL 5 SECOND) THEN
       SET new.`updated_at` = Now();
    END IF;
    IF old.`guid` <> new.`guid` THEN
        SET new.`guid` = old.`guid`;
    END IF;
   END
;;
DELIMITER ;


