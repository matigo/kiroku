DELIMITER ;;
DROP PROCEDURE IF EXISTS SiteGetData;;
CREATE PROCEDURE SiteGetData( IN `site_url` varchar(160) )
BEGIN

   /** ********************************************************************** **
     *  Function collects the pertinent Site information for a given URL
     *
     *  Usage: CALL SiteGetData( 'journals.local' );
     ** ********************************************************************** **/

    SELECT su.`site_id`, si.`https`, su.`url` as `site_url`, si.`guid` as `site_guid`, si.`name` as `site_name`, si.`description`, si.`keywords`,
           si.`theme`, si.`version`, si.`is_default`, si.`updated_at`, 'N' as `do_redirect`
      FROM `Site` si INNER JOIN `SiteUrl` su ON si.`id` = su.`site_id`
                     INNER JOIN `SiteUrl` bb ON su.`site_id` = bb.`site_id`
     WHERE si.`is_deleted` = 'N' and su.`is_deleted` = 'N' and bb.`is_deleted` = 'N'
       and su.`is_active` = 'Y' and bb.`url` = `site_url`
     UNION ALL
    SELECT su.`site_id`, si.`https`, su.`url` as `site_url`, si.`guid` as `site_guid`, si.`name` as `site_name`, si.`description`, si.`keywords`,
           si.`theme`, si.`version`, si.`is_default`, si.`updated_at`, 'Y' as `do_redirect`
      FROM `Site` si INNER JOIN `SiteUrl` su ON si.`id` = su.`site_id`
     WHERE si.`is_deleted` = 'N' and su.`is_deleted` = 'N'
       and su.`is_active` = 'Y' and si.`is_default` = 'Y'
     ORDER BY `is_default`, `do_redirect`, `updated_at` DESC
     LIMIT 1;

END ;;
DELIMITER ;