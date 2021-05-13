DELIMITER ;;
DROP PROCEDURE IF EXISTS AuthLogout;;
CREATE PROCEDURE AuthLogout( IN `in_token_id` int(1), IN `in_token_guid` varchar(64) )
BEGIN

    /** ********************************************************************** **
     *  Function marks an active Token as expired.
     *
     *  Usage: CALL AuthLogout(541, 'caf0f594-9660-11e9-af41-92a1745f8169-f8d1-a87ff679');
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

    /* Update the Token Record */
    UPDATE `Tokens` tt
       SET tt.`is_deleted` = 'Y',
           tt.`updated_at` = Now()
     WHERE tt.`is_deleted` = 'N' and tt.`guid` = `in_token_guid` and tt.`id` = `in_token_id`;

    /* Return the Token Status */
    SELECT tt.`id` as `token_id`, tt.`guid` as `token_guid`, tt.`is_deleted`
      FROM `Tokens` tt
     WHERE tt.`guid` = `in_token_guid` and tt.`id` = `in_token_id`
     LIMIT 1;

END ;;
DELIMITER ;