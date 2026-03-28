USE atendy;

ALTER TABLE team_members
    ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER status;

