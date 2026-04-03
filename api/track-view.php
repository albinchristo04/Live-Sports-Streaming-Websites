<?php
/**
 * Match View Tracking API Endpoint
 *
 * Tracks match views with IP, user agent, referer, and country code data.
 * Includes rate limiting to prevent duplicate entries within 5 minutes.
 */

require_once __DIR__ . '/../config/database.php';

// Set response headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

try {
    // Read and decode JSON body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    $match_id = isset($input['match_id']) ? $input['match_id'] : null;
    $match_slug = $input['match_slug'] ?? null;
    $server_id = $input['server_id'] ?? null;

    // Validate match_id (must be int or null)
    if ($match_id !== null && !is_int($match_id)) {
        throw new Exception('Invalid match_id: must be integer or null');
    }

    // Validate match_slug (must be non-empty string)
    if (!is_string($match_slug) || trim($match_slug) === '') {
        throw new Exception('Invalid match_slug: must be non-empty string');
    }

    // Validate server_id (must be int)
    if (!is_int($server_id)) {
        throw new Exception('Invalid server_id: must be integer');
    }

    $pdo = getPDO();

    // If match_id provided, verify it exists in DB
    $match_title = null;
    if ($match_id !== null) {
        $stmt = $pdo->prepare('SELECT title FROM matches WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $match_id]);
        $match = $stmt->fetch();

        if ($match) {
            $match_title = $match['title'];
        } else {
            // Match not found or deleted - set match_id to NULL and use slug as fallback for title
            $match_id = null;
            $match_title = $match_slug;
        }
    } else {
        // No match_id provided, use slug as title
        $match_title = $match_slug;
    }

    // Extract client IP address (with Cloudflare support)
    $ip_address = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? null;

    // Truncate IP to 45 chars
    if ($ip_address !== null) {
        $ip_address = substr($ip_address, 0, 45);
    }

    // Extract user agent
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    if ($user_agent !== null) {
        $user_agent = substr($user_agent, 0, 500);
    }

    // Extract referer
    $referer = $_SERVER['HTTP_REFERER'] ?? null;
    if ($referer !== null) {
        $referer = substr($referer, 0, 500);
    }

    // Extract country code (Cloudflare header)
    $country_code = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null;
    if ($country_code !== null) {
        $country_code = substr($country_code, 0, 5);
    }

    // Rate limiting: check if same IP + slug has been recorded in last 5 minutes
    if ($ip_address !== null) {
        $stmt = $pdo->prepare(
            'SELECT id FROM match_views
             WHERE ip_address = :ip AND match_slug = :slug AND viewed_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             LIMIT 1'
        );
        $stmt->execute([
            ':ip' => $ip_address,
            ':slug' => $match_slug
        ]);

        if ($stmt->fetch()) {
            // Rate limited - skip insertion silently
            echo json_encode(['success' => true, 'skipped' => true]);
            exit;
        }
    }

    // Insert view record
    $stmt = $pdo->prepare(
        'INSERT INTO match_views
         (match_id, match_title, match_slug, server_id, ip_address, user_agent, referer, country_code)
         VALUES (:match_id, :match_title, :match_slug, :server_id, :ip_address, :user_agent, :referer, :country_code)'
    );

    $stmt->execute([
        ':match_id' => $match_id,
        ':match_title' => $match_title,
        ':match_slug' => $match_slug,
        ':server_id' => $server_id,
        ':ip_address' => $ip_address,
        ':user_agent' => $user_agent,
        ':referer' => $referer,
        ':country_code' => $country_code
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
