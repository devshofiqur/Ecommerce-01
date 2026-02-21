-- ============================================================
-- Dunrovin Group — Database Schema
-- Engine: MySQL 8.0+ | Charset: utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS dunrovin
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dunrovin;

-- -------------------------------------------------------
-- Table: admins
-- -------------------------------------------------------
CREATE TABLE admins (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(60)     NOT NULL UNIQUE,
    email       VARCHAR(180)    NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,
    role        ENUM('super','editor') NOT NULL DEFAULT 'editor',
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Table: categories
-- -------------------------------------------------------
CREATE TABLE categories (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80)     NOT NULL,
    slug        VARCHAR(100)    NOT NULL UNIQUE,
    description TEXT            NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Table: tags
-- -------------------------------------------------------
CREATE TABLE tags (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(60)     NOT NULL,
    slug        VARCHAR(80)     NOT NULL UNIQUE,
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Table: articles
-- -------------------------------------------------------
CREATE TABLE articles (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    admin_id        INT UNSIGNED    NOT NULL,
    category_id     SMALLINT UNSIGNED NULL,
    title           VARCHAR(250)    NOT NULL,
    slug            VARCHAR(280)    NOT NULL UNIQUE,
    excerpt         VARCHAR(400)    NULL,
    body            LONGTEXT        NOT NULL,
    featured_image  VARCHAR(300)    NULL,
    status          ENUM('draft','published','scheduled') NOT NULL DEFAULT 'draft',
    published_at    DATETIME        NULL,
    scheduled_at    DATETIME        NULL,
    meta_title      VARCHAR(120)    NULL,
    meta_description VARCHAR(200)   NULL,
    view_count      INT UNSIGNED    DEFAULT 0,
    reading_time    TINYINT UNSIGNED DEFAULT 1,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id)    REFERENCES admins(id)     ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_slug        (slug),
    INDEX idx_status      (status),
    INDEX idx_published   (published_at),
    INDEX idx_category    (category_id),
    FULLTEXT idx_search   (title, excerpt, body)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Table: article_tags (pivot)
-- -------------------------------------------------------
CREATE TABLE article_tags (
    article_id  INT UNSIGNED      NOT NULL,
    tag_id      SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (article_id, tag_id),
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)     REFERENCES tags(id)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Table: sessions (server-side session store, optional)
-- -------------------------------------------------------
CREATE TABLE admin_sessions (
    id          VARCHAR(128)    PRIMARY KEY,
    admin_id    INT UNSIGNED    NOT NULL,
    ip_address  VARCHAR(45)     NULL,
    user_agent  VARCHAR(255)    NULL,
    payload     TEXT            NULL,
    last_active TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id),
    INDEX idx_active (last_active)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Seed: default super admin (password: Admin@1234 — CHANGE IMMEDIATELY)
-- Hash generated with: password_hash('Admin@1234', PASSWORD_ARGON2ID)
-- -------------------------------------------------------
INSERT INTO admins (username, email, password, role) VALUES (
    'superadmin',
    'admin@dunrovingroup.com',
    '$argon2id$v=19$m=65536,t=4,p=1$Y2hhbmdlbWVub3dk$placeholder_hash_change_immediately',
    'super'
);

-- Seed default categories
INSERT INTO categories (name, slug, description) VALUES
    ('Analysis',   'analysis',   'In-depth analytical pieces'),
    ('Opinion',    'opinion',    'Editorial and opinion writing'),
    ('Reports',    'reports',    'Research and field reports'),
    ('Features',   'features',   'Long-form feature journalism');
