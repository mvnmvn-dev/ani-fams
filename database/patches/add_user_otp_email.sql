-- Add Gmail address used for login OTP delivery (optional per user).
-- PostgreSQL:
ALTER TABLE users ADD COLUMN IF NOT EXISTS otp_email VARCHAR(150) NULL;

-- MySQL/MariaDB (run separately if needed):
-- ALTER TABLE users ADD COLUMN otp_email VARCHAR(150) NULL;
