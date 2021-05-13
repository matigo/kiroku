DELIMITER ;;
DROP PROCEDURE IF EXISTS AuthTokenData;;
CREATE PROCEDURE AuthTokenData( IN `in_token_id` int(11), IN `in_token_guid` varchar(64), IN `in_lifespan` int(11) )
BEGIN
    /** ********************************************************************** **
     *  Function collects the basic information associated with a given account
     *      to populate the internal underscore settings
     *
     *  Usage: CALL AuthTokenData(544, 'fa3b1e80-7879-11e9-941e-54ee758049c3-d371-a87ff679', 7200);
     ** ********************************************************************** **/

    /* If the Token ID is bad, exit */
    IF IFNULL(`in_token_id`, 0) <= 0 THEN
        SELECT 'bad_token_id' as `error`;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid Token ID Provided';
    END IF;

    /* If the Token GUID is bad, exit */
    IF LENGTH(IFNULL(`in_token_guid`, '')) < 30 THEN
        SELECT 'bad_token_guid' as `error`;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid Token GUID Provided';
    END IF;

    /* Validate the Token Lifespan Age (120 Day Default, 28 Year maximum) */
    IF IFNULL(`in_lifespan`, 0) <= 0 OR IFNULL(`in_lifespan`, 0) > 10000 THEN
        SET `in_lifespan` = 120;
    END IF;

    /* "Touch" the token record to verify whether it's still valid or not */
    IF IFNULL(`in_token_id`, 0) > 0 THEN
        UPDATE `Tokens` tt
           SET tt.`is_deleted` = CASE WHEN Now() <= DATE_ADD(tt.`updated_at`, INTERVAL `in_lifespan` DAY) THEN 'N' ELSE 'Y' END,
               tt.`updated_at` = Now()
         WHERE tt.`is_deleted` = 'N' and Now() <= DATE_ADD(tt.`updated_at`, INTERVAL `in_lifespan` DAY)
           and tt.`guid` = `in_token_guid` and tt.`id` = `in_token_id`;
    END IF;

    /* Return the Processed Data */
    SELECT acct.`id` as `account_id`, acct.`login` as `email`, acct.`type`, acct.`display_name`, acct.`last_name`, acct.`first_name`,
           acct.`language_code`, acct.`timezone`, IFNULL(acct.`avatar`, 'default.png') as `avatar`, pref.`pref_fontfamily`, pref.`pref_fontsize`,
           tt.`id` as `token_id`, tt.`guid` as `token_guid`, tt.`created_at` as `login_at`
      FROM `Account` acct INNER JOIN `Tokens` tt ON acct.`id` = tt.`account_id`
                     LEFT OUTER JOIN (SELECT tt.`account_id`,
                                             MAX(CASE WHEN am.`key` = 'preference.fontfamily' THEN am.`value` ELSE NULL END) `pref_fontfamily`,
                                             MAX(CASE WHEN am.`key` = 'preference.fontsize' THEN am.`value` ELSE NULL END) `pref_fontsize`
                                        FROM `Tokens` tt LEFT OUTER JOIN `AccountMeta` am ON tt.`account_id` = am.`account_id` AND am.`is_deleted` = 'N'
                                       WHERE tt.`is_deleted` = 'N' and tt.`id` = `in_token_id`
                                       GROUP BY tt.`account_id` LIMIT 1) pref ON tt.`account_id` = pref.`account_id`
     WHERE acct.`is_deleted` = 'N' and tt.`is_deleted` = 'N' and tt.`guid` = `in_token_guid` and tt.`id` = `in_token_id`
       and Now() <= DATE_ADD(tt.`updated_at`, INTERVAL `in_lifespan` DAY);

 END ;;
DELIMITER ;