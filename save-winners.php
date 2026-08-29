<?php
/**
 * save-winners.php
 * Receives updated winner names from admin.html and writes them to winners.json.
 * No database — this file IS the storage layer.
 *
 * SETUP: change ADMIN_PASSWORD below before uploading this file anywhere public.
 */

define('ADMIN_PASSWORD', 'change-me-2026');

$dataFile = __DIR__ . '/winners.json';

header('Content-Type: application/json');

function respond($httpCode, $ok, $message) {
    http_response_code($httpCode);
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Use POST.');
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    respond(400, false, 'Could not read the submitted data.');
}

if (!isset($input['password']) || !hash_equals(ADMIN_PASSWORD, (string) $input['password'])) {
    respond(401, false, 'Wrong password.');
}

if (!isset($input['categories']) || !is_array($input['categories'])) {
    respond(400, false, 'Missing game data.');
}

// Rebuild the structure, keeping only the fields we expect and trimming winner text.
$cleanCategories = [];
foreach ($input['categories'] as $cat) {
    if (!is_array($cat) || !isset($cat['name']) || !isset($cat['games']) || !is_array($cat['games'])) {
        continue;
    }
    $cleanGames = [];
    foreach ($cat['games'] as $game) {
        if (!is_array($game) || !isset($game['id']) || !isset($game['name'])) {
            continue;
        }
        $cleanGames[] = [
            'id'     => (string) $game['id'],
            'name'   => (string) $game['name'],
            'winner' => isset($game['winner']) ? trim(strip_tags((string) $game['winner'])) : '',
        ];
    }
    $cleanCategories[] = [
        'name'  => (string) $cat['name'],
        'games' => $cleanGames,
    ];
}

if (empty($cleanCategories)) {
    respond(400, false, 'No valid game data found.');
}

$payload = [
    'categories'  => $cleanCategories,
    'updated_at'  => date('c'),
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if ($json === false) {
    respond(500, false, 'Could not encode the data.');
}

$written = @file_put_contents($dataFile, $json, LOCK_EX);

if ($written === false) {
    respond(500, false, 'Could not write winners.json — check that the file/folder is writable by the web server.');
}

respond(200, true, 'Winners saved.');
