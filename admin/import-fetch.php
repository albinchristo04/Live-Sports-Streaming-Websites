<?php
/**
 * AJAX endpoint: fetch & preview matches from a server.
 * POST { server_id: N }
 * Returns JSON { success: true, matches: [...] }
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

$serverId = (int) ($_POST['server_id'] ?? 0);
if ($serverId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid server_id']);
    exit;
}

try {
    $pdo = getPDO();

    // Load server record
    $stmt = $pdo->prepare('SELECT id, name, code, json_url FROM servers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $serverId]);
    $server = $stmt->fetch();

    if (!$server) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Server not found']);
        exit;
    }

    // Instantiate correct importer based on server code
    $code     = strtoupper(trim($server['code']));
    $jsonUrl  = $server['json_url'];

    $importer = match ($code) {
        'S1'    => new S1Importer($pdo, $jsonUrl),
        'S2'    => new S2Importer($pdo, $jsonUrl),
        'S3'    => new S3Importer($pdo, $jsonUrl),
        default => throw new RuntimeException("Unknown server code: {$code}"),
    };

    // Fetch and parse
    $parsed = $importer->parseMatches();

    // Check each match's fingerprint status
    $fpStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM matches WHERE fingerprint = :fp AND deleted_at IS NULL'
    );

    $matches = [];
    foreach ($parsed as $match) {
        $fp = $match['fingerprint'] ?? '';
        $fpStmt->execute([':fp' => $fp]);
        $exists = (int) $fpStmt->fetchColumn() > 0;

        // Normalise datetime
        $dt = $match['match_datetime'];
        if ($dt instanceof DateTimeInterface) {
            $dtStr = $dt->format('Y-m-d H:i:s');
        } else {
            $dtStr = (string) $dt;
        }

        $matches[] = [
            'title'          => (string) ($match['title'] ?? ''),
            'league'         => (string) ($match['league'] ?? ''),
            'match_datetime' => $dtStr,
            'streams_count'  => count($match['streams'] ?? []),
            'fingerprint'    => $fp,
            'status'         => $exists ? 'exists' : 'new',
        ];
    }

    echo json_encode(['success' => true, 'matches' => $matches]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
