-- Drawn e-signature image path (safe to re-run)
SET @db := DATABASE();
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=@db AND TABLE_NAME='contracts' AND COLUMN_NAME='signature_path')=0,
  'ALTER TABLE contracts ADD COLUMN signature_path VARCHAR(500) NULL AFTER signed_ip',
  'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
