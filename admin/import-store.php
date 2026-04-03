<?php
/**
 * AJAX endpoint: import selected matches.
 * Accepts JSON body: { server_id: N, fingerprints: ["abc123", ...] }
 * Returns JSON { success: true, imported: X, skipped: Y }
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../importers/ImporterBase.php';
require_once __DIR__ . '/../importers/S1Importer.php';
require_once __DIR__ . '/../importers/S2Importer.php';
require_once __DIR__ . '/../importers/S3Importer.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Decode JSON body
$raw  = file_get_contents('php://input');
$body = json_decode($raw ?: '', true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$serverId     = (int) ($body['server_id'] ?? 0);
$fingerprints = $body['fingerprints'] ?? [];

if ($serverId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid server_id']);
    exit;
}

if (!is_array($fingerprints) || empty($fingerprints)) {
    echo json_encode(['success' => true, 'imported' => 0, 'skipped' => 0]);
    exit;
}

// Sanitise fingerprints — only keep hex strings
$fingerprints = array_filter(
    array_map('strval', $fingerprints),
    fn($fp) => preg_match('/^[a-f0-9]{32}$/i', $fp)
);
$fpSet = array_flip($fingerprints);

try {
    $pdo = getPDO();

    // Load server
    $stmt = $pdo->prepare('SELECT id, name, code, json_url FROM servers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $serverId]);
    $server = $stmt->fetch();

    if (!$server) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Server not found']);
        exit;
    }

    $code    = strtoupper(trim($server['code']));
    $jsonUrl = $server['json_url'];

    $importer = match ($code) {
        'S1'    => new S1Importer($pdo, $jsonUrl),
        'S2'    => new S2Importer($pdo, $jsonUrl),
        'S3'    => new S3Importer($pdo, $jsonUrl),
        default => throw new RuntimeException("Unknown server code: {$code}"),
    };

    $parsed   = $importer->parseMatches();
    $imported = 0;
    $skipped  = 0;

    foreach ($parsed as $match) {
        $fp = $match['fingerprint'] ?? '';

        if (!isset($fpSet[$fp])) {
            // Not selected — skip
            continue;
        }

        $result = $importer->saveMatch($match, $serverId);
        if ($result) {
            $imported++;
        } else {
            $skipped++;
        }
    }

    echo json_encode([
        'success'  => true,
        'imported' => $imported,
        'skipped'  => $skipped,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
