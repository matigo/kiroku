DELIMITER ;;
DROP PROCEDURE IF EXISTS SiteSetMeta;;
CREATE PROCEDURE SiteSetMeta( IN `in_account_id` int(11), IN `in_site_id` int(11), IN `in_key` varchar(64), IN `in_value` varchar(2048) )
BEGIN

    /** ********************************************************************** **
     *  Function records a Meta record for a given Site and returns a confirmation
     *
     *  Usage: CALL SiteSetMeta(1, 1, 'social.banner', 'banner.jpg');
     ** ********************************************************************** **/

    /* If the Account.id is Wrong, Exit */
    IF IFNULL(`in_account_id`, 0) <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid Account.ID Supplied';
    END IF;

    /* If the Site.id is Wrong, Exit */
    IF IFNULL(`in_site_id`, 0) <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid Site.ID Supplied';
    END IF;

    /* If the Key is Bad, Exit */
    IF LENGTH(IFNULL(`in_key`, '')) < 3 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid Meta Key Supplied';
    END IF;

    /* Set the Meta Records */
    INSERT INTO `SiteMeta` (`site_id`, `key`, `value`)
    SELECT si.`id` as `site_id`, LEFT(LOWER(`in_key`), 64) as `key`, LEFT(`in_value`, 2048) as `value`
      FROM `Account` acct INNER JOIN `Site` si ON acct.`id` = si.`account_id`
     WHERE acct.`is_deleted` = 'N' and si.`is_deleted` = 'N'
       and acct.`type` NOT IN ('account.expired', 'account.guest') and acct.`id` = `in_account_id`
       and si.`id` = `in_site_id`
        ON DUPLICATE KEY UPDATE `value` = LEFT(`in_value`, 2048);

    /* Update the Site Record so the Version Changes */
    UPDATE `Account` acct INNER JOIN `Site` si ON acct.`id` = si.`account_id`
       SET si.`updated_at` = Now()
     WHERE acct.`is_deleted` = 'N' and si.`is_deleted` = 'N'
       and acct.`id` = `in_account_id` and si.`id` = `in_site_id`;

    /* Return a List of All Meta Records for the Account */
    SELECT sm.`key`, CASE WHEN sm.`is_deleted` = 'N' THEN sm.`value` ELSE NULL END as `value`,
           sm.`created_at`, sm.`updated_at`
      FROM `Account` acct INNER JOIN `Site` si ON acct.`id` = si.`account_id`
                          INNER JOIN `SiteMeta` sm ON si.`id` = sm.`site_id`
     WHERE acct.`is_deleted` = 'N' and si.`is_deleted` = 'N' and sm.`is_deleted` = 'N'
       and acct.`id` = `in_account_id` and si.`id` = `in_site_id`
     ORDER BY sm.`key`;

END;;
DELIMITER ;