<?php
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});
define('BASEPATH', __DIR__ . '/../system/');
function log_message($level, $message) {}
require BASEPATH . 'libraries/Xmlrpc.php';
require __DIR__ . '/../application/libraries/MY_Xmlrpc.php';
foreach (array('unauthorized' => 'authentication', 'forbidden' => 'authentication',
    'malformed' => 'invalid_xml', 'timeout' => 'timeout', 'empty' => 'empty_response',
    'not-found' => 'http', 'refused' => 'connection') as $path => $category) {
    $rpc = new MY_Xmlrpc();
    $rpc->server('http://rpc/' . $path, $path === 'refused' ? 9002 : 9001);
    $rpc->timeout(1);
    $rpc->method('supervisor.getAllProcessInfo');
    if ($rpc->send_request()) throw new RuntimeException('Expected failure for ' . $path);
    $diagnostic = $rpc->diagnostic();
    if ($diagnostic['category'] !== $category) throw new RuntimeException($path . ': ' . json_encode($diagnostic));
}
$rpc = new MY_Xmlrpc();
$rpc->server('http://rpc/RPC2', 9001);
$rpc->method('supervisor.startProcess');
$rpc->request(array(array('failing', 'string')));
if ($rpc->send_request()) throw new RuntimeException('Expected Supervisor fault');
$d = $rpc->diagnostic();
if ($d['category'] !== 'supervisor' || strpos($d['error'], 'SPAWN_ERROR') === false)
    throw new RuntimeException('Supervisor faults must retain their original message');
echo "Seven transport diagnostic scenarios passed.\n";
