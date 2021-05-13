SELECT prs.`specific_name` as `name`, LOWER(prs.`routine_type`) as `type`, sha2(TRIM(prs.`routine_definition`), 512) as `sha512`
  FROM `Information_Schema`.`Routines` prs
 WHERE prs.`routine_schema` = 'kirin'
 ORDER BY prs.`routine_type`, prs.`specific_name`;