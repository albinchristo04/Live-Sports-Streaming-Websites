<?php
/*
 * Database Configuration
 *
 * IMPORTANT: Update DB_USER and DB_PASS with your database credentials
 * on your dedicated server. Default values are for local development only.
 */

define('DB_HOST', '127.0.0.1');                    // Use IP, not 'localhost' (avoids socket issues on panel servers)
define('DB_NAME', 'sql_news_evaulthub_com');
define('DB_USER', 'sql_news_evaulthub_com');
define('DB_PASS', 'cxEAKPxRHfBcYkck');
define('DB_CHARSET', 'utf8mb4');

define('SITE_DOMAIN', 'https://news.evaulthub.com');
define('SITE_NAME', 'Live Sports Streaming');
define('GPT_NETWORK_ID', '23250651813');
define('AD_REFRESH_INTERVAL', 60);

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}
