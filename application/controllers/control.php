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

	function Start($server,$worker){
		$this->_request($server,'startProcess',array($worker,1));
		Redirect('/');
	}
	function Startall($server){
		$this->_request($server,'startAllProcesses',array(1));
		Redirect('/');
	}
	function Stop($server,$worker){
		$this->_request($server,'stopProcess',array($worker,1));
		Redirect('/');
	}
	function Stopall($server){
		$this->_request($server,'stopAllProcesses',array(1));
		Redirect('/');
	}
	function Restart($server,$worker){
		$this->_request($server,'stopProcess',array($worker,1));
		sleep(2);
		$this->_request($server,'startProcess',array($worker,1));
		Redirect('/');
	}
	function Restartall($server){
		$this->_request($server,'stopAllProcesses',array(1));
		sleep(2);
		$this->_request($server,'startAllProcesses',array(1));
		Redirect('/');
	}
	function Clear($server,$worker){
		$this->_request($server,'clearProcessLogs',array($worker));
		Redirect('/');
	}
}
