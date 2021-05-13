DELIMITER ;;
DROP PROCEDURE IF EXISTS AccountSetMeta;;
CREATE PROCEDURE AccountSetMeta( IN `in_account_id` int(11), IN `in_key` varchar(64), IN `in_value` varchar(2048) )
BEGIN

    /** ********************************************************************** **
     *  Function records a Meta record for a given account and returns a confirmation
     *
     *  Usage: CALL AccountSetMeta(1, 'subscription.renew-reminder', 'Y');
     ** ********************************************************************** **/

    /* If the Account.id is Wrong, Exit */
    IF IFNULL(`in_account_id`, 0) <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid Account.ID Supplied';
    END IF;

    /* If the Key is Bad, Exit */
    IF LENGTH(IFNULL(`in_key`, '')) < 3 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid Meta Key Supplied';
    END IF;

    /* Set the Meta Records */
    INSERT INTO `AccountMeta` (`account_id`, `key`, `value`)
    SELECT acct.`id`, LEFT(LOWER(`in_key`), 64) as `key`, LEFT(`in_value`, 2048) as `value`
      FROM `Account` acct
     WHERE acct.`is_deleted` = 'N' and acct.`type` NOT IN ('account.expired', 'account.guest') and acct.`id` = `in_account_id`
        ON DUPLICATE KEY UPDATE `value` = LEFT(`in_value`, 2048);

    /* Return a List of All Meta Records for the Account */
    SELECT am.`key`, CASE WHEN am.`is_deleted` = 'N' THEN am.`value` ELSE NULL END as `value`,
           am.`created_at`, am.`updated_at`
      FROM `Account` acct INNER JOIN `AccountMeta` am ON acct.`id` = am.`account_id`
     WHERE acct.`is_deleted` = 'N' and am.`is_deleted` = 'N'
       and acct.`id` = `in_account_id`
     ORDER BY am.`key`;

END;;
DELIMITER ;