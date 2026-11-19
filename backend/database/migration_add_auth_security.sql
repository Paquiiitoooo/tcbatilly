-- ============================================
-- Migration : sécurité d'authentification (2FA, reset password, changement forcé)
-- ============================================
-- Ajoute les colonnes requises par admin/login.php, forgot_password.php,
-- reset_password.php et settings.php sur les bases déjà installées.
--
-- À exécuter une seule fois sur une base existante (prod ou locale).
-- Syntaxe « IF NOT EXISTS » compatible MariaDB (l'hébergement IONOS et WAMP).
-- ============================================

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
    ADD COLUMN IF NOT EXISTS two_factor_code_hash VARCHAR(255) DEFAULT NULL AFTER two_factor_enabled,
    ADD COLUMN IF NOT EXISTS two_factor_expires_at DATETIME DEFAULT NULL AFTER two_factor_code_hash,
    ADD COLUMN IF NOT EXISTS password_reset_token_hash VARCHAR(255) DEFAULT NULL AFTER two_factor_expires_at,
    ADD COLUMN IF NOT EXISTS password_reset_expires_at DATETIME DEFAULT NULL AFTER password_reset_token_hash,
    ADD COLUMN IF NOT EXISTS force_password_change TINYINT(1) NOT NULL DEFAULT 0 AFTER password_reset_expires_at;
