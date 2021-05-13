SELECT col.`table_name` as `table_name`, LOWER(col.`column_name`) as `column_name`,
       IFNULL(col.`column_default`, '') as `default`, LOWER(col.`is_nullable`) as `is_nullable`, LOWER(col.`column_type`) as `column_type`,
       LOWER(col.`character_set_name`) as `charset`, LOWER(col.`collation_name`) as `collation`, LOWER(col.`extra`) as `extra`
  FROM `Information_Schema`.`Columns` col
 WHERE col.`table_schema` = '[DB_NAME]'
 ORDER BY col.`table_name`, col.`column_name`;