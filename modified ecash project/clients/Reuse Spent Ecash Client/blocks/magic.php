<?php
class magic {	
	public static function proc(&$v,$enc=true){
		if(is_array($v)) array_walk_recursive($v,'magic',($enc?'enc':'dec'));
	}
	
	public static function enc($v){
		$enc = base64_encode(gcrypto::crypt($v));
		return $enc;
	}
	
	public static function dec($v){
		$dec = trim(gcrypto::dcrypt(base64_decode($v)));
		return $dec;
	}
	
	public static function wal(){
		$wal = sqlite_open(config::$walfile);
		return $wal;
	}

}
function magic(&$v,$k,$m){
	switch($m){
		case "dec":
			$v = magic::dec($v);
			break;
		default:
			$v = magic::enc($v);
	}
}
?>

