<?php
// Run with: php tests/config_reference.php
if (!isset($commonSource)) {
    $commonSource = file_get_contents(__DIR__ . '/../system/core/Common.php');
}
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});
class TestConfigStream {
    public $context;
    private $position = 0;
    private $data = '<?php $config = array("setting" => "original");';
    function stream_open($path, $mode, $options, &$openedPath) { return true; }
    function stream_read($length) {
        $chunk = substr($this->data, $this->position, $length);
        $this->position += strlen($chunk);
        return $chunk;
    }
    function stream_eof() { return $this->position >= strlen($this->data); }
    function stream_set_option($option, $arg1, $arg2) { return false; }
    function stream_stat() { return array('mode' => 0100444, 'size' => strlen($this->data)); }
    function url_stat($path, $flags) { return array('mode' => 0100444, 'size' => strlen($this->data)); }
}
stream_wrapper_register('monitorconfig', 'TestConfigStream');
define('BASEPATH', '/test/');
define('APPPATH', 'monitorconfig:///');
eval('?>' . $commonSource);
$config =& get_config();
if ($config['setting'] !== 'original') throw new RuntimeException('Configuration did not load');
$config['setting'] = 'changed';
$again =& get_config();
if ($again['setting'] !== 'changed') throw new RuntimeException('Configuration reference was not preserved');
echo "Configuration reference check passed.\n";
