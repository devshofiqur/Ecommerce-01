# Dunrovin Group — Editorial Publishing Platform

> Clarity. Depth. Authority.

A production-grade, high-performance article publishing platform built with pure PHP, MySQL, HTML, and CSS. Zero framework overhead. Designed for speed, security, and editorial excellence.

## Quick Start

```bash
# 1. Configure web server to serve public/ as document root
# 2. Create database and import schema
mysql -u root -p < database/schema.sql

# 3. Set environment variables (see config/config.php)
export APP_URL="https://yourdomain.com"
export DB_HOST="127.0.0.1"
export DB_NAME="dunrovin"
export DB_USER="dunrovin_user"
export DB_PASS="your_password"

# 4. Set file permissions
chmod 755 public/uploads

# 5. Create admin account
php -r "echo password_hash('YourPassword!', PASSWORD_ARGON2ID);"
# Update database with generated hash
```

## Documentation

Full engineering documentation: `docs/ENGINEERING_DOCUMENTATION.html`

## Requirements

- PHP 8.2+ (pdo_mysql, gd, mbstring, fileinfo)
- MySQL 8.0+ or MariaDB 10.6+
- Apache 2.4+ with mod_rewrite OR Nginx

## Security

- CSRF protection on all forms
- Argon2id password hashing
- PDO prepared statements
- Session hardening
- XSS output escaping
- MIME-validated file uploads
- Rate limiting on login

## Architecture

```
public/         ← webroot only
├── index.php   ← front controller
├── .htaccess   ← routing + headers + caching
├── css/        ← app.css + admin.css
├── js/         ← app.js + admin.js
└── uploads/    ← media uploads

app/            ← outside webroot
├── Controllers/
├── Models/
├── Views/
├── Helpers/
└── Router.php

config/config.php  ← all configuration
database/schema.sql ← MySQL schema
```

## License

Proprietary — Dunrovin Group © 2026
