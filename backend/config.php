<?php
define('DB_PATH', getenv('KANBAN_DB_PATH') ?: __DIR__ . '/../kanban.db');

// Load .env if present (key=value, # comments)
$_ef = __DIR__ . '/../.env';
if (is_file($_ef)) {
    foreach (file($_ef, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (!isset($_line[0]) || $_line[0] === '#') continue;
        $pos = strpos($_line, '=');
        if ($pos === false) continue;
        $k = trim(substr($_line, 0, $pos));
        $v = trim(substr($_line, $pos + 1));
        putenv("$k=$v");
        $_ENV[$k] = $v;
    }
}
unset($_ef, $_line, $pos, $k, $v);

define('API_KEY', getenv('KANBAN_API_KEY') ?: '');
