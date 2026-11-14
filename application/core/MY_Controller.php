<?php
class MY_Controller extends CI_Controller{
    public function __construct() {
        parent::__construct();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start(array('name' => 'monitor_session', 'cookie_httponly' => true,
                'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'cookie_samesite' => 'Lax', 'use_strict_mode' => true));
        }
        if (!isset($_SESSION['monitor_csrf'])) $_SESSION['monitor_csrf'] = bin2hex(random_bytes(32));
    }
    public function _csrf_token() { return $_SESSION['monitor_csrf']; }


	public function _request($server,$method,$request=array()){
		$servers = $this->config->item('supervisor_servers');
		if (!isset($servers[$server])) {
            show_error("Unknown Supervisor server.", 404);
        }
		
		$config = $servers[$server];
		
		$this->load->library('xmlrpc',array(),$server);
		$this->{$server}->initialize();
		$this->{$server}->server($config['url'],$config['port']);
		$this->{$server}->method('supervisor.'.$method);
		$this->{$server}->timeout($this->config->item('timeout'));
		
		if(isset($config['username']) && isset($config['password'])){
			$this->{$server}->setCredentials($config['username'], $config['password']);
		}
		$this->{$server}->request($request);

		if(!$this->{$server}->send_request()){
				$response['error'] = $this->{$server}->display_error();
		}else{
				$response = $this->{$server}->display_response();
		}	
		
		return $response;
	}

}
