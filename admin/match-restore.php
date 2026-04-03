<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/matches.php');
    exit;
}

// Resolve ID from query string or URI path segment (/admin/matches/123/restore)
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    $segments = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
    foreach ($segments as $i => $seg) {
        if ($seg === 'restore' && isset($segments[$i - 1]) && is_numeric($segments[$i - 1])) {
            $id = (int)$segments[$i - 1];
            break;
        }
    }
}

if ($id > 0) {
    $pdo = getPDO();
    $pdo->prepare("UPDATE matches SET deleted_at = NULL WHERE id = ?")->execute([$id]);
}

header('Location: /admin/matches.php?deleted=1&msg=Match+restored');
exit;
