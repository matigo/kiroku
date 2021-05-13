DELIMITER ;;
DROP PROCEDURE IF EXISTS AccountCreate;;
CREATE PROCEDURE AccountCreate( IN `in_login` varchar(160), IN `in_password` varchar(2048),
                                IN `in_lastname` varchar(80), IN `in_firstname` varchar(80), IN `in_displayname` varchar(80),
                                IN `in_type` varchar(64), IN `in_langcode` varchar(6), IN `in_timezone` varchar(40),
                                IN `in_shasalt` varchar(256) )
BEGIN
    DECLARE `x_account_id`  int(11);

    /** ********************************************************************** **
     *  Function creates an Account record so long as the criteria passed is
     *      sufficiently unique.
     *
     *  Usage: CALL AccountCreate('{login}', '{password}', '{lastname}', '{firstname}', '{displayname}', '{type}', '{langcode}', '{timezone}', '{shasalt}');
     ** ********************************************************************** **/

    /* If the login does not appear to be valid, exit */
    IF LENGTH(IFNULL(`in_login`, '')) < 6 THEN
        SELECT 'login_short' as `error`;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The login is too short';
    END IF;
    IF (SELECT LOCATE('@', IFNULL(`in_login`, ''))) <= 0 THEN
        SELECT 'no_atsign' as `error`;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The login is invalid';
    END IF;

    /* If the password appears bad, exit */
    IF LENGTH(IFNULL(`in_password`, '')) <= 6 THEN
        SELECT 'bad_password' as `error`;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The password is far too short';
    END IF;

    /* If the SHA Salt appears bad, exit */
    IF LENGTH(IFNULL(`in_shasalt`, '')) <= 6 THEN
        SELECT 'no_salt' as `error`;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The SHA Salt is invalid';
    END IF;

    /* If the login is already used, exit */
    IF (SELECT COUNT(acct.`id`) FROM `Account` acct WHERE acct.`is_deleted` = 'N' and acct.`login` = IFNULL(`in_login`, '')) > 0 THEN
        SELECT 'login_used' as `error`;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'An account already exists for this address';
    END IF;

    /* If we're here, we can create the account */
    IF IFNULL(`x_account_id`, 0) <= 0 THEN
        INSERT INTO `Account` (`login`, `password`, `last_name`, `first_name`, `display_name`, `type`, `language_code`, `timezone`)
        SELECT `in_login` as `login`, sha2(CONCAT(IFNULL(`in_shasalt`, ''), `in_password`), 512) as `password`,
               `in_lastname` as `last_name`, `in_firstname` as `first_name`,
               TRIM(CASE WHEN `in_displayname` <> '' THEN `in_displayname` ELSE `in_firstname` END) as `display_name`,
               TRIM(LOWER(`in_type`)) as `type`, TRIM(LOWER(`in_langcode`)) as `langcode`, `in_timezone` as `timezone`;
        SELECT LAST_INSERT_ID() INTO `x_account_id`;
    END IF;

    /* Let's Try to Return some basic Account information as validation */
    SELECT acct.`login`, acct.`last_name`, acct.`first_name`, acct.`display_name`, acct.`avatar`,
           (SELECT z.`value` FROM `AccountMeta` z
             WHERE z.`is_deleted` = 'N' and z.`key` = 'email.auxiliary' and z.`account_id` = acct.`id`) as `email_aux`,
           acct.`type`, acct.`guid`,
           acct.`language_code`, acct.`avatar`, acct.`timezone`, acct.`created_at`, acct.`updated_at`
      FROM `Account` acct
     WHERE acct.`is_deleted` = 'N' and acct.`id` = IFNULL(`x_account_id`, 0)
     LIMIT 1;

END ;;
DELIMITER ;