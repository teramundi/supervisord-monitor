<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$prefix = strpos($path, '/monitor/') === 0 ? '/monitor' : '';
$relative = substr($path, strlen($prefix));
$root = dirname(__DIR__, 2) . '/public_html';
if (is_file($root . $relative) && pathinfo($relative, PATHINFO_EXTENSION) !== 'php') {
    $types = array('css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png');
    $ext = pathinfo($relative, PATHINFO_EXTENSION);
    header('Content-Type: ' . (isset($types[$ext]) ? $types[$ext] : 'application/octet-stream'));
    readfile($root . $relative);
    return;
}
define('ENVIRONMENT', 'development');
$_SERVER['SCRIPT_NAME'] = $prefix . '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
chdir($root);
require $root . '/index.php';
