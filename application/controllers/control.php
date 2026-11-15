<?php
class Control extends MY_Controller{
    public function __construct() {
        parent::__construct();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: POST');
            show_error('Control actions require POST.', 405);
        }
        $token = $this->input->post('monitor_csrf');
        if (!is_string($token) || !hash_equals($this->_csrf_token(), $token)) {
            show_error('Invalid or expired action token. Refresh the dashboard and retry.', 403);
        }
    }

    private function failed($response) {
        if (is_array($response) && isset($response['error'])) return (string) $response['error'];
        // The legacy XML-RPC decoder may normalize booleans to strings.
        if ($response === true || $response === 1 || $response === '1') return null;
        if (is_array($response)) {
            foreach ($response as $item) {
                if (!is_array($item) || !isset($item['status']) || (string) $item['status'] !== '80') {
                    return 'One or more processes rejected the command.';
                }
            }
            return null;
        }
        return 'Unexpected response from Supervisor.';
    }
    private function finish($server, $action, $response) {
        $error = $this->failed($response);
        $_SESSION['monitor_notice'] = array('type' => $error === null ? 'success' : 'error',
            'message' => $action . ' on ' . $server . ($error === null ? ' completed.' : ' failed: ' . $error));
        Redirect('/');
    }
    function Start($server, $worker) {
        $this->finish($server, 'Start ' . $worker, $this->_request($server, 'startProcess', array($worker, true)));
    }
    function Stop($server, $worker) {
        $this->finish($server, 'Stop ' . $worker, $this->_request($server, 'stopProcess', array($worker, true)));
    }
    function Clear($server, $worker) {
        $response = $this->_request($server, 'clearProcessLogs', array($worker));
        if (is_array($response) && isset($response['error']) && strpos($response['error'], 'FAILED:') === 0) {
            $response['error'] = 'Supervisor could not remove or reopen the logs. Check the service account permissions on the log directory and files. Server response: ' . $response['error'];
        }
        $this->finish($server, 'Clear logs for ' . $worker, $response);
    }
    function Startall($server) {
        $this->finish($server, 'Start all', $this->_request($server, 'startAllProcesses', array(true)));
    }
    function Stopall($server) {
        $this->finish($server, 'Stop all', $this->_request($server, 'stopAllProcesses', array(true)));
    }
    function Restart($server, $worker) {
        $stopped = $this->_request($server, 'stopProcess', array($worker, true));
        if ($this->failed($stopped) !== null) $this->finish($server, 'Restart (stop phase)', $stopped);
        $this->finish($server, 'Restart ' . $worker, $this->_request($server, 'startProcess', array($worker, true)));
    }
    function Restartall($server) {
        $stopped = $this->_request($server, 'stopAllProcesses', array(true));
        if ($this->failed($stopped) !== null) $this->finish($server, 'Restart all (stop phase)', $stopped);
        $this->finish($server, 'Restart all', $this->_request($server, 'startAllProcesses', array(true)));
    }
}
