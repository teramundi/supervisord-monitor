<?php
class MY_Xmlrpc extends CI_Xmlrpc {
    // Keep transport diagnostics separate from Supervisor application faults.
    function diagnostic() {
        $category = $this->client->transport_error;
        $code = $this->result->faultCode();
        $error = $this->display_error();
        if (!$category && (string)$code === (string)$this->xmlrpcerr['http_error']) {
            $category = preg_match('/HTTP\/[0-9.]+ (401|403)\b/', $error) ? 'authentication' : 'http';
        }
        if (!$category && (string)$code === (string)$this->xmlrpcerr['invalid_return']) $category = 'invalid_xml';
        if (!$category && (string)$code === (string)$this->xmlrpcerr['no_data']) $category = 'empty_response';
        $messages = array(
            'connection' => 'Connection failed: check the Supervisor address, port and network access.',
            'timeout' => 'Supervisor request timed out. The command may have completed; check process state before retrying.',
            'authentication' => 'Supervisor authentication failed (HTTP 401/403). Check credentials and access rules.',
            'http' => 'Supervisor returned an unexpected HTTP status. Check the RPC endpoint.',
            'invalid_xml' => 'Supervisor returned an invalid XML-RPC response.',
            'empty_response' => 'Supervisor closed the connection without a response.'
        );
        $category = $category ?: 'supervisor';
        return array('category' => $category,
            'error' => isset($messages[$category]) ? $messages[$category] : $error);
    }

	function setCredentials($username, $password) {
		$this->client->setCredentials($username, $password);
	}
	function server($url, $port=80){
		if (substr($url, 0, 4) != "http"){
			$url = "http://".$url;
		}

		$parts = parse_url($url);

		$path = ( ! isset($parts['path'])) ? '/' : $parts['path'];

		if (isset($parts['query']) && $parts['query'] != ''){
			$path .= '?'.$parts['query'];
		}

		$this->client = new MY_XML_RPC_Client($path, $parts['host'], $port);
        }

}

class MY_XML_RPC_Client extends XML_RPC_Client {
	public $transport_error = null;
	var $username		= "";
	var $password		= "";
	
	function setCredentials($username, $password) {
		$this->username = $username;
		$this->password = $password;
	}
	
	function sendPayload($msg){
		$this->transport_error = null;
        $started = microtime(true);
        $fp = @fsockopen($this->server, $this->port, $this->errno, $this->errstring, $this->timeout);

		if ( ! is_resource($fp)){
			$this->transport_error = microtime(true) - $started >= $this->timeout ? 'timeout' : 'connection';
			$r = new XML_RPC_Response(0, $this->xmlrpcerr['http_error'],$this->xmlrpcstr['http_error']);
			return $r;
		}
	
        stream_set_timeout($fp, (int)$this->timeout);
		if (empty($msg->payload)){
			// $msg = XML_RPC_Messages
			$msg->createPayload();
		}

		$r = "\r\n";
		$op  = "POST {$this->path} HTTP/1.0$r";
		$op .= "Host: {$this->server}$r";
		$op .= "Content-Type: text/xml$r";
		$op .= "User-Agent: {$this->xmlrpcName}$r";
		$op .= "Content-Length: ".strlen($msg->payload). "$r";
		if ($this->username != '' && $this->password != '') {
			$op .= "Authorization: Basic ".base64_encode($this->username.':'.$this->password).$r;
		}
		
		$op .= "$r";		

		$op .= $msg->payload;


        $offset = 0;
        while ($offset < strlen($op)) {
            $written = @fwrite($fp, substr($op, $offset));
            if (!$written) {
                $meta = stream_get_meta_data($fp);
                $this->transport_error = $meta['timed_out'] ? 'timeout' : 'connection';
                fclose($fp);
                return new XML_RPC_Response(0, $this->xmlrpcerr['http_error'], $this->xmlrpcstr['http_error']);
            }
            $offset += $written;
        }
		$resp = $msg->parseResponse($fp);
        $meta = stream_get_meta_data($fp);
        fclose($fp);
        if ($meta['timed_out']) {
            $this->transport_error = 'timeout';
            return new XML_RPC_Response(0, $this->xmlrpcerr['http_error'], $this->xmlrpcstr['http_error']);
        }
        return $resp;
	}
}
