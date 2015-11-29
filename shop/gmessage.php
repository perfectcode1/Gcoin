<?php
class Gmsg{
	public static function create($msg){
		$prepared = base64_encode(json_encode($msg));
		return $prepared;
	}
	
	public static function extract($msg){
		if(!is_string($msg)) return $msg;
		$extract = json_decode(base64_decode($msg),true);
		return $extract;
	}
	
	public static function prepare($msg,$type,$rcpt){
		global $merchantAddress;
		$a = Tools::address($merchantAddress);
		$m = array("op"=>$type,"msg"=>$msg,"sender"=>$a['account'],"recipient"=>$rcpt,"bank"=>$a['bank']);
		return $m;
	}
}
?>