-- Add token_validated column to users table
ALTER TABLE users ADD COLUMN token_validated TINYINT(1) DEFAULT 0;
