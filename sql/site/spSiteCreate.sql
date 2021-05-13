DELIMITER ;;
DROP PROCEDURE IF EXISTS SiteCreate;;
CREATE PROCEDURE SiteCreate( IN `in_account_id` int(11), IN `in_site_url` varchar(140),
                             IN `in_site_name` varchar(128), IN `in_site_descr` varchar(255), IN `in_site_keys` varchar(255) )
BEGIN
    DECLARE `x_site_id` int(11);

   /** ********************************************************************** **
     *  Function creates a Site and all the requisite data for it to function.
     *
     *  Usage: CALL SiteCreate( {account_id}, '{siteurl}', '{sitename}', '{description}', '{keys}' );
     ** ********************************************************************** **/

    /* If the Requested URL is Used, Exit */
    IF (SELECT COUNT(su.`id`) as `sites`
          FROM `Account` acct INNER JOIN `Site` s ON acct.`id` = s.`account_id`
                              INNER JOIN `SiteUrl` su ON s.`id` = su.`site_id`
         WHERE acct.`is_deleted` = 'N' and su.`is_deleted` = 'N' and s.`is_deleted` = 'N' and su.`url` = `in_site_url`) <> 0 THEN
        SELECT 'url_used' as `error`;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Requested URL Is In Use';
    END IF;

    /* Create the Site Record */
    INSERT INTO `Site` (`account_id`, `name`, `description`, `keywords`, `https`, `theme`)
    SELECT acct.`id` as `account_id`, LEFT(TRIM(`in_site_name`), 80) as `name`, LEFT(TRIM(`in_site_descr`), 255) as `description`,
           LEFT(TRIM(`in_site_keys`), 255) as `keywords`, 'Y' as `https`, 'anri' as `theme`
      FROM `Account` acct
     WHERE acct.`is_deleted` = 'N' and acct.`id` = `in_account_id`
     LIMIT 1;
    SELECT LAST_INSERT_ID() INTO `x_site_id`;

    /* Ensure We Have a Site ID */
    IF IFNULL(`x_site_id`, 0) <= 0 THEN
        SELECT 'bad_siteid' as `error`;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Could Not Create Site Record';
    END IF;

    /* Set the Site's URL */
    INSERT INTO `SiteUrl` (`site_id`, `url`, `is_active`)
    SELECT si.`id` as `site_id`, LEFT(TRIM(`in_site_url`), 140) as `url`, 'Y' as `is_active`
      FROM `Site` si
     WHERE si.`is_deleted` = 'N' and si.`id` = `x_site_id`
     LIMIT 1;

    /* If We're Here, It's Good. Return the Basic Info */
    SELECT si.`id` as `site_id`, si.`name` as `site_name`, si.`description` as `site_description`, si.`keywords` as `site_keys`, si.`guid` as `site_guid`,
           si.`https`, su.`url`, si.`theme`, si.`version`,
           si.`account_id`, acct.`login` as `account_login`,
           acct.`last_name` as `account_lastname`, acct.`first_name` as `account_firstname`, acct.`display_name` as `account_displayname`,
           acct.`type` as `account_type`, acct.`guid` as `account_guid`, acct.`avatar` as `account_avatar`,
           si.`created_at`, si.`updated_at`
      FROM `Account` acct INNER JOIN `Site` si ON acct.`id` = si.`account_id`
                          INNER JOIN `SiteUrl` su ON si.`id` = su.`site_id`
     WHERE acct.`is_deleted` = 'N' and si.`is_deleted` = 'N' and su.`is_deleted` = 'N'
       and su.`is_active` = 'Y' and si.`id` = `x_site_id`
     ORDER BY su.`updated_at` DESC
     LIMIT 1;

END ;;
DELIMITER ;