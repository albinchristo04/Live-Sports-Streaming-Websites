-- NOTE: On panel-managed servers (AAPanel/cPanel) the database already exists.
-- Run this file while connected as the db user, or skip these two lines if the DB is pre-created.
-- CREATE DATABASE IF NOT EXISTS sql_news_evaulthub_com CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sql_news_evaulthub_com;

CREATE TABLE servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    code VARCHAR(10) NOT NULL UNIQUE,
    json_url VARCHAR(500) NOT NULL,
    timezone VARCHAR(30) NOT NULL,
    embed_pattern VARCHAR(500) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO servers (name, code, json_url, timezone, embed_pattern) VALUES
('Server 1', 's1', 'https://raw.githubusercontent.com/albinchristo04/arda/refs/heads/main/rereyano_data.json', 'CET', 'https://cartelive.club/player/{channelId}/1'),
('Server 2', 's2', 'https://raw.githubusercontent.com/albinchristo04/mayiru/refs/heads/main/sports_events.json', 'UTC', NULL),
('Server 3', 's3', 'https://raw.githubusercontent.com/albinchristo04/ptv/refs/heads/main/events.json', 'UNIX', NULL);

CREATE TABLE matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(300) NOT NULL,
    slug VARCHAR(350) NOT NULL UNIQUE,
    league VARCHAR(150) NULL,
    category VARCHAR(100) NULL,
    team_home VARCHAR(150) NULL,
    team_away VARCHAR(150) NULL,
    match_datetime DATETIME NOT NULL,
    display_datetime DATETIME NULL,
    country VARCHAR(100) NULL,
    poster_url VARCHAR(500) NULL,
    viewers VARCHAR(20) NULL,
    server_id INT NOT NULL,
    fingerprint VARCHAR(32) NOT NULL,
    is_featured TINYINT(1) DEFAULT 0,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_match_datetime (match_datetime),
    INDEX idx_server_id (server_id),
    INDEX idx_fingerprint (fingerprint),
    INDEX idx_slug (slug),
    INDEX idx_deleted (deleted_at),
    INDEX idx_league (league),
    FOREIGN KEY (server_id) REFERENCES servers(id)
);

CREATE TABLE match_streams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    channel_name VARCHAR(100) NULL,
    iframe_url VARCHAR(500) NOT NULL,
    stream_type ENUM('iframe', 'm3u8') DEFAULT 'iframe',
    lang VARCHAR(10) NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_match_id (match_id),
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
);

CREATE TABLE match_views (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NULL,
    match_title VARCHAR(300) NOT NULL,
    match_slug VARCHAR(350) NOT NULL,
    server_id INT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    referer VARCHAR(500) NULL,
    country_code VARCHAR(5) NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_viewed_at (viewed_at),
    INDEX idx_match_id (match_id),
    INDEX idx_server_id (server_id)
);

CREATE TABLE ad_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    ad_unit_path VARCHAR(200) NOT NULL,
    sizes JSON NOT NULL,
    page_type ENUM('homepage', 'match', 'all') NOT NULL,
    position VARCHAR(50) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    div_id VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO ad_slots (name, ad_unit_path, sizes, page_type, position, div_id) VALUES
('homepage_header_banner',   '/23250651813/homepage_header_banner',   '[[320,50],[320,100],[728,90],[970,90]]',  'homepage', 'header',       'div-gpt-hp-header'),
('homepage_infeed_banner',   '/23250651813/homepage_infeed_banner',   '[[300,250],[320,100],[336,280]]',         'homepage', 'infeed',       'div-gpt-hp-infeed'),
('homepage_footer_banner',   '/23250651813/homepage_footer_banner',   '[[300,250],[320,50],[728,90]]',           'homepage', 'footer',       'div-gpt-hp-footer'),
('match_header_banner',      '/23250651813/match_header_banner',      '[[320,50],[320,100],[728,90]]',           'match',    'header',       'div-gpt-match-header'),
('match_above_player_banner','/23250651813/match_above_player_banner','[[320,50],[728,90],[970,90]]',            'match',    'above_player', 'div-gpt-match-above'),
('match_below_player_banner','/23250651813/match_below_player_banner','[[300,250],[336,280],[728,90]]',          'match',    'below_player', 'div-gpt-match-below'),
('match_sidebar_banner',     '/23250651813/match_sidebar_banner',     '[[160,600],[300,250],[300,600]]',         'match',    'sidebar',      'div-gpt-match-sidebar'),
('match_footer_banner',      '/23250651813/match_footer_banner',      '[[300,250],[320,50],[728,90]]',           'match',    'footer',       'div-gpt-match-footer');

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor') DEFAULT 'admin',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password_hash, role) VALUES
('admin', '$2y$12$.zApon3YZxkhLD5BzwAJ9eTePeNxwr8Dx21nPyUsUEDoqMhQunbHG', 'admin');

CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'Live Sports Streaming'),
('site_domain', 'https://news.evaulthub.com'),
('adsterra_popup_script', '<script src="https://widthwidowzoology.com/22/9f/cc/229fcc7fac3be2c689fa4fa174ce4169.js"></script>'),
('ad_refresh_interval', '60'),
('gpt_network_id', '23250651813');
