DELIMITER ;;
DROP PROCEDURE IF EXISTS AccountUpdate;;
CREATE PROCEDURE AccountUpdate( IN `in_account_id` int(11), IN `in_login` varchar(160), IN `in_aux_mail` varchar(160),
                                IN `in_lastname` varchar(80), IN `in_firstname` varchar(80), IN `in_displayname` varchar(80),
                                IN `in_avatar` varchar(160), IN `in_langcode` varchar(6), IN `in_timezone` varchar(40),
                                IN `in_password` varchar(2048), IN `in_shasalt` varchar(256) )
BEGIN
    DECLARE `x_valid`       char(1);

    /** ********************************************************************** **
     *  Function updates primary Account data and returns a summary
     *
     *  Usage: CALL AccountUpdate( {account_id}, '{login}', '{aux_email}',
                                  '{last_name}', '{first_name}', '{display_name}', '{avatar}',
                                  '{langcode}', '{timezone}', '{password}', '{shasalt}' );
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
    IF LENGTH(IFNULL(`in_password`, '')) BETWEEN 1 AND 6 THEN
        SELECT 'bad_password' as `error`;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The password is far too short';
    END IF;

    /* If the SHA Salt appears bad, exit */
    IF LENGTH(IFNULL(`in_shasalt`, '')) <= 6 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The SHA Salt is invalid';
    END IF;

    /* Ensure we have some sort of name */
    IF GREATEST(`in_displayname`, `in_firstname`, `in_lastname`) = '' THEN
        SELECT 'bad_name' as `error`;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid Name Provided';
    END IF;

    /* Update the Account data */
    UPDATE `Account` acct
       SET `login` = LOWER(LEFT(`in_login`, 160)),
           `password` = CASE WHEN IFNULL(`in_password`, '') <> '' THEN sha2(CONCAT(`in_shasalt`, `in_password`), 512) ELSE `password` END,
           `last_name` = LEFT(`in_lastname`, 80),
           `first_name` = LEFT(`in_firstname`, 80),
           `display_name` = LEFT(TRIM(CASE WHEN `in_displayname` <> '' THEN `in_displayname` ELSE `in_firstname` END), 80),
           `avatar` = LEFT(TRIM(CASE WHEN `in_avatar` <> '' THEN `in_avatar` ELSE 'default.png' END), 160),
           `language_code` = LEFT(LOWER(`in_langcode`), 6),
           `timezone` = CASE WHEN IFNULL(`in_timezone`, '') <> '' THEN LEFT(`in_timezone`, 40) ELSE 'UTC' END,
           `updated_at` = Now()
     WHERE acct.`is_deleted` = 'N' and acct.`id` = `in_account_id`;

    /* Update the AccountMeta data (if required) */
    INSERT INTO `AccountMeta` (`account_id`, `key`, `value`)
    SELECT acct.`id` as `account_id`, 'email.auxiliary' as `key`, TRIM(IFNULL(`in_aux_mail`, '')) as `value`
      FROM `Account` acct
     WHERE acct.`is_deleted` = 'N' and acct.`id` = `in_account_id`
        ON DUPLICATE KEY UPDATE `value` = TRIM(IFNULL(`in_aux_mail`, '')),
                                `updated_at` = Now();

    /* Let's Return an Error Code. Blank is ideal. */
    SELECT acct.`login`, acct.`last_name`, acct.`first_name`, acct.`display_name`, acct.`avatar`,
           (SELECT z.`value` FROM `AccountMeta` z
             WHERE z.`is_deleted` = 'N' and z.`key` = 'email.auxiliary' and z.`account_id` = acct.`id`) as `email_aux`,
           acct.`type`, acct.`guid`,
           acct.`language_code`, acct.`avatar`, acct.`timezone`, acct.`created_at`, acct.`updated_at`
      FROM `Account` acct
     WHERE acct.`is_deleted` = 'N' and acct.`id` = `in_account_id`
     LIMIT 1;

END ;;
DELIMITER ;