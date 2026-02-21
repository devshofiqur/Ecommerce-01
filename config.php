<?php
// ============================================================
// config/config.php — Dunrovin Group Application Configuration
// ============================================================
// NEVER commit this file with real credentials to version control.
// Use environment variables in production (getenv / $_ENV).
// ============================================================

defined('DUNROVIN') or die('Direct access forbidden.');

// ------------------------------------------------------------
// Environment
// ------------------------------------------------------------
define('APP_ENV',   getenv('APP_ENV')   ?: 'production'); // 'development' | 'production'
define('APP_DEBUG', APP_ENV === 'development');
define('APP_NAME',  'Dunrovin Group');
define('APP_URL',   rtrim(getenv('APP_URL') ?: 'https://dunrovingroup.com', '/'));
define('APP_TAGLINE', 'Clarity. Depth. Authority.');

// ------------------------------------------------------------
// Paths
// ------------------------------------------------------------
define('ROOT_PATH',    dirname(__DIR__));
define('PUBLIC_PATH',  ROOT_PATH . '/public');
define('UPLOAD_PATH',  PUBLIC_PATH . '/uploads');
define('VIEW_PATH',    ROOT_PATH . '/app/Views');
define('UPLOAD_URL',   APP_URL . '/uploads');

// ------------------------------------------------------------
// Database
// ------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'dunrovin');
define('DB_USER', getenv('DB_USER') ?: 'dunrovin_user');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ------------------------------------------------------------
// Security
// ------------------------------------------------------------
define('SESSION_NAME',      'dg_sess');
define('CSRF_TOKEN_LENGTH', 32);
define('BCRYPT_COST',       12);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES',   15);
define('ADMIN_PATH',        'admin'); // URL segment for admin

// ------------------------------------------------------------
// Pagination
// ------------------------------------------------------------
define('ARTICLES_PER_PAGE', 10);
define('ADMIN_PER_PAGE',    20);

// ------------------------------------------------------------
// Media
// ------------------------------------------------------------
define('MAX_UPLOAD_MB',     5);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg','image/png','image/webp']);
define('IMAGE_MAX_WIDTH',   1600);
define('IMAGE_MAX_HEIGHT',  900);

// ------------------------------------------------------------
// SEO
// ------------------------------------------------------------
define('SEO_SEPARATOR',     ' | ');
define('DEFAULT_META_DESC', 'Dunrovin Group — authoritative long-form journalism, analysis, and editorial reporting.');
