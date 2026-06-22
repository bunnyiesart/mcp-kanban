<?php
require_once __DIR__ . '/db.php';

$db = db();

$cards = [
    // To Do (column 1)
    [1, 'Add rate limiting to /login endpoint',      'claude',   'https://github.com/user/repo/issues/14', "Brute force attempts spiked last week.\nLimit to 5 attempts / 10 min per IP."],
    [1, 'Write unit tests for auth middleware',      'unknown',  null,                                      null],
    [1, 'Update README with setup instructions',    'unknown',  null,                                      null],
    [1, 'Migrate user table to new schema',          'claude',   'https://github.com/user/repo/pull/22',   "Adds `last_login` and `mfa_enabled` columns.\nNeeds data backfill script."],

    // In Progress (column 2)
    [2, 'Refactor API error handling',               'claude',   'https://github.com/user/repo/pull/19',   "Standardising all errors to {error, code} shape.\nHalf done — still need to cover /upload routes."],
    [2, 'Fix session token expiry bug',              'claude',   'https://github.com/user/repo/issues/11', "Tokens never expire in Safari.\nRoot cause: SameSite cookie flag missing on refresh endpoint."],
    [2, 'Add pagination to /api/cards',             'unknown',  null,                                      null],

    // Done (column 3)
    [3, 'Set up CI pipeline',                        'claude',   'https://github.com/user/repo/pull/5',    "GitHub Actions — runs lint + tests on every PR.\nDeploys to staging on merge to main."],
    [3, 'Fix XSS in card title rendering',           'claude',   'https://github.com/user/repo/pull/17',   "Was using innerHTML directly. Switched to esc() everywhere.\nVerified with OWASP test strings."],
    [3, 'Add notes field to cards',                  'claude',   null,                                      "DB migration + API + UI all done.\nAI agents can now annotate cards via update_card."],
];

$stmt = $db->prepare('
    INSERT INTO cards (title, column_id, agent, url, notes, position)
    VALUES (?, ?, ?, ?, ?, ?)
');

$pos = [1 => 0, 2 => 0, 3 => 0];
foreach ($cards as [$col, $title, $agent, $url, $notes]) {
    $stmt->execute([$title, $col, $agent, $url, $notes, $pos[$col]++]);
}

echo "seeded " . count($cards) . " cards across 3 columns\n";
