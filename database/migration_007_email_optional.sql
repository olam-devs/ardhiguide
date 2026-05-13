-- migration_007_email_optional.sql
-- Make email optional and phone the required, unique identifier.
-- Safe to run once on an existing database (e.g. via `mysql < migration_007_email_optional.sql`).

SET NAMES utf8mb4;

-- 1) Backfill any existing users who do not have a phone yet, so the
--    NOT NULL and UNIQUE constraints below cannot fail. The placeholder
--    is obviously fake so admins can spot and update it later.
UPDATE users
   SET phone = CONCAT('PENDING-', id)
 WHERE phone IS NULL OR phone = '';

-- 2) Give the seeded admin / seller accounts a recognisable phone
--    (only if they are still on the PENDING placeholder).
UPDATE users
   SET phone = '255657925368'
 WHERE email = 'admin@ardhiguide.local'
   AND phone LIKE 'PENDING-%';

UPDATE users
   SET phone = '255700000001'
 WHERE email = 'seller@ardhiguide.local'
   AND phone LIKE 'PENDING-%';

-- 3) Allow NULL emails. (MODIFY is a no-op if it is already nullable.)
ALTER TABLE users MODIFY email VARCHAR(190) NULL;

-- 4) Make phone NOT NULL.
ALTER TABLE users MODIFY phone VARCHAR(32) NOT NULL;

-- 5) Add UNIQUE index on phone, but only if it does not already exist.
SET @phone_unique_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'users'
     AND INDEX_NAME   = 'uniq_users_phone'
);
SET @sql := IF(@phone_unique_exists = 0,
  'ALTER TABLE users ADD UNIQUE KEY uniq_users_phone (phone)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
