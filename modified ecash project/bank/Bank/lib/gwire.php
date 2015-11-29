<?php

class Gnet {
	private $connected = false;
	private $socketbuffer = "";
	
	public function connected(){
		return $this->connected;
	}
	
	public function connect($address="",$port=""){
		if($this->connected) $this->close();
		if(empty($port)||!is_integer($port)) $port = config::$walletPort;
		if(empty($address)) $address = config::$networkInterface;
		$address = gethostbyname($address);
		$this->address = $address;
		$this->port = $port;
		$this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if($this->socket === false){
			gio::log("" . socket_strerror(socket_last_error()),E_USER_WARNING);
			return false;
		}
		$result = @socket_connect($this->socket, $this->address, $this->port);
		if($result === false){
			gio::log("" . socket_strerror(socket_last_error($this->socket)),E_USER_WARNING);
			return false;
		}
		$this->connectmessage = @socket_read($this->socket, config::$maximumTransmissionLength, PHP_NORMAL_READ);
		$this->connected = true;
		return true;
	}
	
	public function send($message, $remote=false){
		if(!$this->connected){
			if($remote){
				$this->connect(config::$remoteAddress,config::$remotePort);
			}else{
				$this->connect();
			}
		}
		if(!$this->connected) return false;
		$recv = "";
		$this->_out($message);
		$this->_flush();
		if(false === ($buf = @socket_read($this->socket, config::$maximumTransmissionLength, PHP_NORMAL_READ))){
			echo "" . socket_strerror(socket_last_error($this->msgsock)) . "\n";
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