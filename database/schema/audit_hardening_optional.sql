-- ============================================================================
-- DTCS HIMS — OPTIONAL audit-log hardening (database level)  — NOT AUTO-APPLIED
-- ----------------------------------------------------------------------------
-- The application already refuses to update/delete ActivityLog rows (model-level
-- guard). That protects against application bugs and ordinary users, but NOT
-- against someone with direct database access.
--
-- These triggers add a second, database-level barrier. They are:
--   * OPTIONAL      — apply only if your hospital's policy requires it.
--   * REQUIRE       — the TRIGGER privilege (and, with binary logging,
--                     log_bin_trust_function_creators or SUPER).
--   * NOT A GUARANTEE OF IMMUTABILITY — a DBA can still drop the triggers.
--     For stronger tamper resistance ship the logs to an external write-once
--     destination (WORM storage / SIEM) as well.
--
-- SIDE EFFECT: `activity_logs.user_id` has ON DELETE SET NULL. Once these
-- triggers exist, hard-deleting a user who has audit rows will be blocked
-- (users are soft-deleted by the application, so normal use is unaffected).
--
-- To remove:  DROP TRIGGER activity_logs_block_update;
--             DROP TRIGGER activity_logs_block_delete;
-- ============================================================================

DROP TRIGGER IF EXISTS activity_logs_block_update;
DROP TRIGGER IF EXISTS activity_logs_block_delete;

DELIMITER $$

CREATE TRIGGER activity_logs_block_update BEFORE UPDATE ON activity_logs
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'activity_logs is append-only: UPDATE is not permitted';
END$$

CREATE TRIGGER activity_logs_block_delete BEFORE DELETE ON activity_logs
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'activity_logs is append-only: DELETE is not permitted';
END$$

DELIMITER ;
