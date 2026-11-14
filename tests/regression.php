<?php
// Run with: php tests/regression.php
// A caller may supply $sources to run the same tests without copying files.
if (!isset($sources)) {
    $sources = array(
        'view' => file_get_contents(__DIR__ . '/../application/views/welcome.php'),
        'config' => file_get_contents(__DIR__ . '/../system/core/Config.php'),
    );
}
require_once __DIR__ . '/../application/helpers/monitor_helper.php';
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function expect($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
function base_url($path = '') { return '/monitor/' . ltrim($path, '/'); }
function site_url($path = '') { return '/monitor/' . ltrim($path, '/'); }
function timespan($a, $b) { return 'time'; }
class FakeConfig {
    function item($name) {
        $values = array('enable_alarm' => false, 'show_host' => false,
            'supervisor_cols' => 2, 'refresh' => 0, 'redmine_url' => '');
        return isset($values[$name]) ? $values[$name] : null;
    }
}
class FakeMonitor {
    public $config;
    public $logResult;
    public $calls = 0;
    function __construct() { $this->config = new FakeConfig(); }
    function _csrf_token() { return 'test-token'; }
    function _request($server, $method, $args) { $this->calls++; return $this->logResult; }
    function render($source, $list, $version, $cfg) {
        $muted = false;
        ob_start();
        try { eval('?>' . $source); return ob_get_clean(); }
        catch (Throwable $e) { ob_end_clean(); throw $e; }
    }
}
function &get_instance() { global $monitor; return $monitor; }
$monitor = new FakeMonitor();
$server = array('url' => 'https://user:embedded-secret@example.test/RPC2',
    'port' => '9001', 'username' => 'private-user', 'password' => 'private-pass');
$cfg = array('node' => $server);
$out = $monitor->render($sources['view'], array('node' => array('error' => 'Connection <failed>')),
    array('node' => array('error' => 'offline')), $cfg);
expect(strpos($out, 'Connection &lt;failed&gt;') !== false, 'connection errors must be rendered safely');
expect($monitor->calls === 0, 'unavailable servers must not trigger log requests');
expect(strpos($out, 'private-user') === false && strpos($out, 'private-pass') === false
    && strpos($out, 'embedded-secret') === false, 'credentials must not appear in the page');
expect(strpos($out, 'https://example.test:9001/') !== false, 'Supervisor link should preserve HTTPS');

$item = array('group' => 'service', 'name' => 'service', 'statename' => 'STOPPED',
    'description' => '', 'stderr_logfile' => '');
$monitor->logResult = array('error' => 'NO_FILE');
$out = $monitor->render($sources['view'], array('node' => array($item)), array('node' => '4.2.5'), $cfg);
expect(strpos($out, 'NO_FILE') === false, 'missing unconfigured stderr must not raise an alert');
expect(strpos($out, 'No stderr log available') !== false, 'missing stderr must remain visible');
expect(strpos($out, 'data-message=') === false, 'NO_FILE should not create an error popover');
$item['stderr_logfile'] = '/var/log/service.err';
$monitor->logResult = array('error' => 'NO_FILE: /var/log/service.err');
$out = $monitor->render($sources['view'], array('node' => array($item)), array('node' => '4.2.5'), $cfg);
expect(strpos($out, 'No stderr log available') !== false, 'missing configured stderr must remain visible');
unset($item['stderr_logfile']);
$out = $monitor->render($sources['view'], array('node' => array($item)), array('node' => '4.2.5'), $cfg);
expect(strpos($out, 'No stderr log available') !== false, 'unknown stderr configuration must remain visible');
$monitor->logResult = array('error' => 'FAILED: log read denied');
$out = $monitor->render($sources['view'], array('node' => array($item)), array('node' => '4.2.5'), $cfg);
expect(strpos($out, 'FAILED: log read denied') !== false, 'other log errors must not be suppressed');
$out = $monitor->render($sources['view'], array('node' => array('unexpected')),
    array('node' => '4.2.5'), $cfg);
expect(strpos($out, 'Invalid process information') !== false, 'malformed process data must not crash');

define('BASEPATH', '/test/');
define('APPPATH', '/test/');
function log_message($level, $message) {}
function &get_config() { global $ciConfig; return $ciConfig; }
eval('?>' . $sources['config']);
foreach (array('/index.php' => 'https://example.test/',
               '/monitor/index.php' => 'https://example.test/monitor/') as $script => $expected) {
    $ciConfig = array('base_url' => '', 'index_page' => '',
        'enable_query_strings' => false, 'url_suffix' => '');
    $_SERVER['HTTP_HOST'] = 'example.test';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SCRIPT_NAME'] = $script;
    $config = new CI_Config();
    expect($config->item('base_url') === $expected, 'base URL auto-detection failed for ' . $script);
    expect($config->base_url('/css/custom.css') === $expected . 'css/custom.css', 'asset URL is incorrect');
    expect($config->site_url('/control/start/node/worker') === $expected . 'control/start/node/worker', 'control URL is incorrect');
}
$ciConfig = array('base_url' => 'https://public.example.test/monitor/');
$_SERVER['HTTP_HOST'] = 'internal.example.test';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$config = new CI_Config();
expect($config->item('base_url') === 'https://public.example.test/monitor/', 'explicit proxy URL must be preserved');
echo "All regression checks passed.\n";
