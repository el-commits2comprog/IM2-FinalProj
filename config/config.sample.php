<?php

declare(strict_types=1);

/**
 * ByteBack local configuration.
 * Copy to config.php, never commit config.php.
 *
 * BASE_URL must be the public/ URL with no trailing slash, for example
 * http://localhost/IM2-FinalProj/public
 * The session cookie and handler URLs are derived from that path.
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'byteback');
define('DB_PORT', 3306);
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('BASE_URL', 'http://localhost/byteback/public');
