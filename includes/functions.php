<?php

/**
 * slugify($text)
 * Returns a lowercase hyphenated slug, removes special characters.
 */
function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

/**
 * formatMatchTime($datetime, $timezone)
 * Returns "Today 15:00" if today, otherwise "15:00 UTC".
 */
function formatMatchTime(string $datetime, string $timezone = 'UTC'): string {
    try {
        $tz = new DateTimeZone($timezone);
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
        $dt->setTimezone($tz);

        $today = new DateTime('now', $tz);
        if ($dt->format('Y-m-d') === $today->format('Y-m-d')) {
            return 'Today ' . $dt->format('H:i');
        }
        return $dt->format('H:i') . ' ' . $timezone;
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * isMatchLive($matchDatetime, $durationHours)
 * Returns true if the match is currently in progress.
 */
function isMatchLive(string $matchDatetime, float $durationHours = 3): bool {
    try {
        $now   = new DateTime('now', new DateTimeZone('UTC'));
        $start = new DateTime($matchDatetime, new DateTimeZone('UTC'));
        $end   = clone $start;
        $end->modify('+' . (int)($durationHours * 60) . ' minutes');
        return $now >= $start && $now <= $end;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * getRelativeTime($datetime)
 * Returns human-readable relative time: "2 hours ago", "in 3 hours", "Yesterday".
 */
function getRelativeTime(string $datetime): string {
    try {
        $now  = new DateTime('now', new DateTimeZone('UTC'));
        $then = new DateTime($datetime, new DateTimeZone('UTC'));
        $diff = $now->getTimestamp() - $then->getTimestamp();
        $abs  = abs($diff);
        $past = $diff >= 0;

        if ($abs < 60) {
            return $past ? 'just now' : 'in a moment';
        } elseif ($abs < 3600) {
            $mins = (int)round($abs / 60);
            return $past ? $mins . ' minute' . ($mins !== 1 ? 's' : '') . ' ago'
                         : 'in ' . $mins . ' minute' . ($mins !== 1 ? 's' : '');
        } elseif ($abs < 86400) {
            $hours = (int)round($abs / 3600);
            return $past ? $hours . ' hour' . ($hours !== 1 ? 's' : '') . ' ago'
                         : 'in ' . $hours . ' hour' . ($hours !== 1 ? 's' : '');
        } elseif ($abs < 172800) {
            return $past ? 'Yesterday' : 'Tomorrow';
        } else {
            $days = (int)round($abs / 86400);
            return $past ? $days . ' days ago' : 'in ' . $days . ' days';
        }
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * excerpt($text, $length)
 * Truncates text to $length characters with ellipsis.
 */
function excerpt(string $text, int $length = 150): string {
    $text = strip_tags($text);
    $text = trim($text);
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    $truncated = mb_substr($text, 0, $length);
    $lastSpace = mb_strrpos($truncated, ' ');
    if ($lastSpace !== false) {
        $truncated = mb_substr($truncated, 0, $lastSpace);
    }
    return rtrim($truncated) . '...';
}

/**
 * getSetting(PDO $pdo, string $key, string $default)
 * Fetches a value from the settings table by key.
 */
function getSetting(PDO $pdo, string $key, string $default = ''): string {
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row !== false && isset($row['setting_value'])) ? (string)$row['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * generateMatchSlug(string $title, string $date)
 * Returns a slug with a date suffix for uniqueness.
 */
function generateMatchSlug(string $title, string $date): string {
    $slug       = slugify($title);
    $dateSuffix = slugify($date);
    if ($dateSuffix !== '') {
        return $slug . '-' . $dateSuffix;
    }
    return $slug;
}

/**
 * sanitizeInput($value)
 * Applies htmlspecialchars and trim to prevent XSS.
 */
function sanitizeInput(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * getClientIP()
 * Returns the real client IP address, handling common proxy headers.
 */
function getClientIP(): string {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR',
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip  = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    // Fallback: return REMOTE_ADDR even if private
    return isset($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : '0.0.0.0';
}
