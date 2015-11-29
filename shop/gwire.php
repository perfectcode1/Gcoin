<?php

class Gnet {
	private $connected = false;
	private $socketbuffer = "";
	
	public function connect($address="",$port=""){
		global $merchantAddress;
		if($this->connected) $this->close();
		$a = Tools::address($merchantAddress);
		if(empty($port)||!is_integer($port)) $port = intval($a['port']);
		if(empty($address)) $address = $a['address'];
		$address = gethostbyname($address);
		$this->address = $address;
		$this->port = $port;
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if($this->socket === false){
			return false;
		}
		$result = socket_connect($this->socket, $this->address, $this->port);
		if($result === false){
			return false;
		}
		$this->connectmessage = @socket_read($this->socket, (1024*1024), PHP_NORMAL_READ);
		$this->connected = true;
		return true;
	}
	
	public function send($message){
		if(!$this->connected) $this->connect();
		if(!$this->connected) return false;
		$this->_out($message);
		$this->_flush();
		if(false === ($buf = socket_read($this->socket, (1024*1024), PHP_NORMAL_READ))){
		}
		$this->close();
		return $buf;
	}
	
	public function close(){
		$this->_out("quit");
		$this->_flush();
		socket_close($this->socket);
		$this->connected = false;
	}
	
	private function _out($message){
		if(!is_string($message)){
			$message = Gmsg::create($message);
		}
		$this->socketbuffer .= $message;
	}
	
	private function _flush($msg="\n"){
		if(!empty($msg)) $this->socketbuffer .= $msg;
		@socket_write($this->socket, $this->socketbuffer, strlen($this->socketbuffer));
		$this->socketbuffer = "";
	}
}
?>