DELIMITER ;;
DROP PROCEDURE IF EXISTS AuthLogin;;
CREATE PROCEDURE AuthLogin( IN `in_login` varchar(140), IN `in_password` varchar(2048), IN `in_shasalt` varchar(64), IN `in_lifespan` int(11) )
BEGIN
    DECLARE `x_account_id`  int(11);
    DECLARE `x_token_id`    int(11);

    /** ********************************************************************** **
     *  Function attempts to perform a login and, so long as everything is valid,
     *      returns a Token.id and Token.guid value
     *
     *  Usage: CALL AuthLogin( '{email/login}', '{password}', '{shasalt}', {lifespan} );
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

    /* If the SHA Salt is bad, Exit */
    IF LENGTH(IFNULL(`in_shasalt`, '')) < 6 THEN
        SELECT 'no_salt' as `error`;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid Password Provided';
    END IF;

    /* Validate the Token Lifespan Age (120 Day Default, 28 Year maximum) */
    IF IFNULL(`in_lifespan`, 0) <= 0 OR IFNULL(`in_lifespan`, 0) > 10000 THEN
        SET `in_lifespan` = 120;
    END IF;

    /* Determine if the Credentials are any good */
    DROP TEMPORARY TABLE IF EXISTS tmp;
    CREATE TEMPORARY TABLE tmp AS
    SELECT acct.`id` as `account_id`, acct.`type`, acct.`display_name`, acct.`last_name`, acct.`first_name`, acct.`language_code`,
           CASE WHEN acct.`type` IN ('account.admin', 'account.global') THEN 0
                ELSE DATEDIFF(Now(), IFNULL((SELECT max(tt.`updated_at`) FROM `Tokens` tt WHERE tt.`account_id` = acct.`id`), Now())) END as `last_activity`
      FROM `Account` acct
     WHERE acct.`is_deleted` = 'N' and acct.`type` IN ('account.admin', 'account.normal')
       and acct.`password` = sha2(CONCAT(IFNULL(`in_shasalt`, ''), `in_password`), 512)
       and acct.`login` = `in_login`
     LIMIT 1;

    /* Confirm that we have an Account.id value */
    IF (SELECT COUNT(`account_id`) FROM `tmp`) <> 1 THEN
        SELECT 'bad_creds' as `error`;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Bad credentials provided';
    END IF;

    /* Get the Account.id (This is required to get around concurrency issues) */
    SELECT `account_id` INTO `x_account_id` FROM tmp;

    /* Do we have an existing, recently-created Token.id for the account? */
    SELECT tt.`id` INTO `x_token_id`
      FROM `Tokens` tt
     WHERE tt.`is_deleted` = 'N' and Now() <= DATE_ADD(tt.`created_at`, INTERVAL 1 MINUTE)
       and tt.`account_id` = `x_account_id`
     ORDER BY tt.`id`
     LIMIT 1;

    /* Create the Token Record (if required) */
    IF IFNULL(`x_token_id`, 0) <= 0 THEN
        INSERT INTO `Tokens` (`guid`, `account_id`)
        SELECT CONCAT(gg.`guid`, '-', LEFT(md5(Now()), 4), '-', LEFT(MD5(acct.`created_at`), 8)) as `guid`, acct.`id` as `account_id`
          FROM `Account` acct INNER JOIN (SELECT CONCAT(SUBSTRING(tmp.`md5`, 1, 8), '-',
                                                        SUBSTRING(tmp.`md5`, 9, 4), '-',
                                                        SUBSTRING(tmp.`md5`, 13, 4), '-',
                                                        SUBSTRING(tmp.`md5`, 17, 4), '-',
                                                        SUBSTRING(tmp.`md5`, 21, 12)) as `guid`
                                            FROM (SELECT MD5(CONCAT(Now(), '-', uuid(), '-', RAND() * RAND() * RAND())) as `md5`) tmp) gg ON gg.`guid` != ''
         WHERE acct.`is_deleted` = 'N' and acct.`type` IN ('account.admin', 'account.global', 'account.normal')
           and acct.`id` = `x_account_id`
         LIMIT 1;
        SELECT LAST_INSERT_ID() INTO `x_token_id`;
    END IF;

    /* Return the Token Information */
    SELECT tt.`id` as `token_id`, tt.`guid` as `token_guid`, tt.`account_id`, tmp.`type` as `account_type`,
           tmp.`last_name`, tmp.`first_name`, tmp.`display_name`, tmp.`language_code`, tmp.`last_activity`
      FROM `Tokens` tt INNER JOIN tmp ON tt.`account_id` = tmp.`account_id`
     WHERE tt.`is_deleted` = 'N' and tt.`id` = `x_token_id`
     LIMIT 1;

END ;;
DELIMITER ;
