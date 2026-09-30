CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS google_tokens (
    id TINYINT UNSIGNED PRIMARY KEY,
    refresh_token TEXT NOT NULL,
    access_token TEXT NULL,
    expires_at DATETIME NULL,
    connected_email VARCHAR(190) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_name VARCHAR(100) NOT NULL,
    location_name VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    address VARCHAR(500) NULL,
    phone VARCHAR(50) NULL,
    website VARCHAR(500) NULL,
    client_name VARCHAR(255) NULL,
    contact_email VARCHAR(190) NULL,
    crm_status ENUM('lead','active','paused','churned') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    last_synced_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_id INT UNSIGNED NOT NULL,
    review_id VARCHAR(190) NOT NULL,
    reviewer_name VARCHAR(255) NULL,
    star_rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NULL,
    review_created_at DATETIME NULL,
    reply_comment TEXT NULL,
    reply_updated_at DATETIME NULL,
    UNIQUE KEY uq_review (location_id, review_id),
    KEY idx_created (review_created_at),
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_id INT UNSIGNED NOT NULL,
    summary TEXT NOT NULL,
    cta_type VARCHAR(30) NULL,
    cta_url VARCHAR(500) NULL,
    scheduled_at DATETIME NULL,
    status ENUM('draft','scheduled','published','failed') NOT NULL DEFAULT 'draft',
    google_post_name VARCHAR(255) NULL,
    error TEXT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_due (status, scheduled_at),
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS insights_daily (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_id INT UNSIGNED NOT NULL,
    day DATE NOT NULL,
    metric VARCHAR(60) NOT NULL,
    value INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_insight (location_id, day, metric),
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
