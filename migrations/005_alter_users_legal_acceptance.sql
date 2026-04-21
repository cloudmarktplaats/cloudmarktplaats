ALTER TABLE users
    ADD COLUMN tos_version INT UNSIGNED NULL AFTER role,
    ADD COLUMN tos_accepted_at DATETIME NULL AFTER tos_version,
    ADD COLUMN privacy_version INT UNSIGNED NULL AFTER tos_accepted_at,
    ADD COLUMN privacy_accepted_at DATETIME NULL AFTER privacy_version;
