<?php
class server {
	public $starttime = null;
	public $restarttime = null;
	public $stoptime = null;
	private $stop = false;
	private $restart = false;
	private $restartcount = 0;
	private $connectioncount = 0;
	private $socketbuffer = "";
	public function server($address="",$port="",$autostart=false){
		set_time_limit(0);
		ob_implicit_flush();
		if($autostart) return $this->start($address, $port);		
	}
	public function start($address="",$port=""){
		if(empty($port)||!is_integer($port)) $port = config::$walletPort;
		if(empty($address)) $address = config::$networkInterface;
		if($port>(255*255)||$port<1):gio::log("Invalid port number: $port");$port=10001;gio::log("Using port number: $port");endif;
		$this->address = $address;
		$this->port = $port;
		gio::log("Starting the server!",E_USER_NOTICE);
		if (($this->sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
			gio::log("Unable to create socket: reason: " . socket_strerror(socket_last_error()),E_USER_ERROR);
			return false;
		}
		if (@socket_bind($this->sock, $this->address, $this->port) === false) {
			if(socket_last_error($this->sock)==10048){
				gio::log("Another program is running on port $port.",E_USER_ERROR);			
			}else{
				gio::log("Unable to bind socket: reason: " . socket_strerror(socket_last_error($this->sock)),E_USER_ERROR);
			}
			return false;
		}
		if (@socket_listen($this->sock, 5) === false) {
			gio::log("Unable to listen on socket: reason: " . socket_strerror(socket_last_error($this->sock)),E_USER_ERROR);
			return false;
		}else{
			$this->msgsock = array();
		}
		gio::log("Server is started!",E_USER_NOTICE);
		gio::output("\n\n\t\t::INFO::\n \n\n\tInterface:\t".$this->address." \n\n\tPort:\t\t".$this->port."\n\n");
		$this->restarttime = time();
		$this->starttime = !empty($this->starttime)?$this->starttime:time();
		do {
			$this->_processrequest();
			if($this->stop){
				break;
			}
		} while (true);
		socket_close($this->sock);
		gio::log("Server is stopped!",E_USER_NOTICE);
		if($this->restart){
			$this->stop = false;
			$this->restart = false;
			$this->restartcount++;
			$this->start($this->address,$this->port);
		}
		$this->stoptime = time();
	}
	
	public function restarts(){
		return $this->restartcount;
	}

	public function connections(){
		return $this->connectioncount;
	}
	
	public function servertime(){
		$t = $this->stoptime - $this->starttime;
		return $t;
	}
	
	private function _processrequest(){
			if (($this->msgsock = @socket_accept($this->sock)) === false){
				gio::log("socket_accept() failed: reason: " . socket_strerror(socket_last_error($this->sock)),E_USER_ERROR);
				break;
			}
			gio::log("\nSocket: CONNECTION RECEIVED", VERBOSE);
			$this->_welcome();
			do {
				if (false === ($buf = @socket_read($this->msgsock, config::$maximumTransmissionLength, PHP_NORMAL_READ))){
					gio::log("socket_read() failed: reason: " . socket_strerror(socket_last_error($this->msgsock)),E_USER_WARNING);
					break;
				}
				if (!$buf = trim($buf)) {
					continue;
				}
				gio::log("NEW REQUEST RECEIVED", VERBOSE);
				if ($buf == 'quit'){
					gio::log("Socket: Connection terminated by client!", VERBOSE);
					break;
				}
				if ($buf == 'restart'){
					$this->restart = true;
					gio::log("Socket: Restarting the server !!!", VERBOSE);
					$buf = 'shutdown';
				}
				if ($buf == 'shutdown'){
					gio::output("Processing request to stop the server ...");
					self::_out("Shuting down...");
					self::_flush();
					$this->stop = true;
					break;
				}
				if(!$this->stop){
					$sockreply = Gmsg::process($buf);
					$this->_out($sockreply);
					$this->_flush();
					gio::log("Socket: REPLY SENT\n", VERBOSE);
				}else{
					$this->_out("...Terminating Server Process...");
					break;
				}
			} while (true);
			socket_close($this->msgsock);
			unset($this->msgsock);
			gio::log("\nSocket: CONNECTION ENDED", VERBOSE);
	}
	
	private function _welcome(){
			$this->connectioncount++;
			if(is_array(config::$walletMessage)){
				foreach(config::$walletMessage as $wmsg){
					$this->_out("$wmsg");
				}
			}else{
				$this->_out(config::$walletMessage);
			}
			$this->_flush();
	}
	
	private function _out($message){
		if(!is_string($message)){
			$message = Gmsg::create($message);
		}
		$this->socketbuffer .= $message;
	}
	
	private function _flush($msg="\n"){
		$this->socketbuffer .= $msg;
		@socket_write($this->msgsock, $this->socketbuffer, strlen($this->socketbuffer));
		$this->socketbuffer = "";
	}
}
?>