<?php
class gcrypto {
	public static function crypt($msg, $mode = MCRYPT_MODE_CBC){
		$iv = mcrypt_create_iv(mcrypt_get_iv_size(GsonCrypt::getWalEncAlgo(), $mode), MCRYPT_RAND);
		$crypt = mcrypt_encrypt(GsonCrypt::getWalEncAlgo(), config::$walkey, $msg, $mode, $iv);
		return "$iv{::::}$crypt";
	}
	
	public static function dcrypt($msg, $mode = MCRYPT_MODE_CBC){
		$msg = explode("{::::}",$msg);
		$dcrypt = mcrypt_decrypt(GsonCrypt::getWalEncAlgo(), config::$walkey, $msg[1], $mode, $msg[0]);
		return $dcrypt;
	}	
}
?>