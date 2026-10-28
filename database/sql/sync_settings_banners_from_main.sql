-- ============================================================================
-- Copy `settings` and `banners` rows from the MAIN (production) database into
-- the DEVELOP database. DB rows only — banner image FILES are not copied.
--
-- Upsert semantics: rows are matched by unique keys (settings.key,
-- banners.position, or primary id) and updated in place; rows that exist only
-- on develop are left untouched.
--
-- HOW TO RUN — both databases on the same MySQL server:
--   1) Edit the two USE lines below (source = main DB, target = develop DB).
--   2) mysql -h <host> -u <user> -p < this_file.sql
--
-- HOW TO RUN — databases on DIFFERENT hosts: skip to the mysqldump block at
-- the bottom of this file.
-- ============================================================================

-- ── SOURCE: main / production database ──────────────────────────────────────
USE `hitee_card`;            -- ← replace with the MAIN database name

CREATE TEMPORARY TABLE _sync_settings AS
    SELECT id, `key`, `value`, created_at, updated_at, deleted_at
    FROM settings;

CREATE TEMPORARY TABLE _sync_banners AS
    SELECT id, position, `type`, title, image_path, images, link, is_active,
           created_at, updated_at, deleted_at
    FROM banners;

-- ── TARGET: develop database ────────────────────────────────────────────────
USE `hitee_card_develop`;    -- ← replace with the DEVELOP database name

INSERT INTO settings (id, `key`, `value`, created_at, updated_at, deleted_at)
SELECT id, `key`, `value`, created_at, updated_at, deleted_at
FROM _sync_settings
ON DUPLICATE KEY UPDATE
    `value`    = VALUES(`value`),
    updated_at = VALUES(updated_at),
    deleted_at = VALUES(deleted_at);

INSERT INTO banners (id, position, `type`, title, image_path, images, link,
                     is_active, created_at, updated_at, deleted_at)
SELECT id, position, `type`, title, image_path, images, link, is_active,
       created_at, updated_at, deleted_at
FROM _sync_banners
ON DUPLICATE KEY UPDATE
    `type`      = VALUES(`type`),
    title       = VALUES(title),
    image_path  = VALUES(image_path),
    images      = VALUES(images),
    link        = VALUES(link),
    is_active   = VALUES(is_active),
    updated_at  = VALUES(updated_at),
    deleted_at  = VALUES(deleted_at);

-- Optional: make develop an EXACT mirror (removes rows that exist only on
-- develop). Uncomment if wanted.
-- DELETE FROM settings WHERE `key` NOT IN (SELECT `key` FROM _sync_settings);
-- DELETE FROM banners  WHERE position NOT IN (SELECT position FROM _sync_banners);

-- ============================================================================
-- DIFFERENT-HOSTS VARIANT — run from a shell, not in mysql:
--
--   mysqldump -h <main-host> -u <user> -p <main_db> \
--       --no-create-info --replace --tables settings banners \
--     | mysql -h <dev-host> -u <user> -p <dev_db>
--
-- Notes:
--   * --replace emits REPLACE INTO, giving the same upsert behaviour.
--   * On GTID-enabled managed MySQL add --set-gtid-purged=OFF to mysqldump.
--   * Banner image_path/images files live in storage/app/public on the main
--     server and are NOT covered — only DB rows are synced.
-- ============================================================================
