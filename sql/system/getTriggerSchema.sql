SELECT trg.`trigger_name` as `name`, trg.`event_object_table` as `table_name`, LOWER(trg.`event_manipulation`) as `event`, LOWER(trg.`action_timing`) as `timing`,
       trg.`character_set_client` as `charset`, trg.`database_collation` as `collation`, sha2(trg.`action_statement`, 512) as `sha512`
  FROM `Information_Schema`.`Triggers` trg
 WHERE trg.`trigger_schema` = '[DB_NAME]'
 ORDER BY trg.`event_object_table`, trg.`action_timing`, trg.`action_order`, trg.`event_manipulation`;