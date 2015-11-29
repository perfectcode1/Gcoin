<?php
class Gmsg{
	public static function encrypt($msg,$rcptid){
		gio::log("Encrypting message ...");
		$pub_key = GsonCrypt::getkey($rcptid,false,true);
		openssl_get_publickey($pub_key); 
		$j=0; 
		$x=floor((strlen($msg)/10));
		for($i=0;$i<$x;$i++){ 
			$crypttext='';   
			openssl_public_encrypt(substr($msg,$j,10),$crypttext,$pub_key);$j=$j+10; 
			@$enc.=$crypttext; 
			$enc.=":::"; 
		}
		if((strlen($msg)%10)>0){ 
			openssl_public_encrypt(substr($msg,$j),$crypttext,$pub_key); 
			$enc.=$crypttext; 
		}
		gio::log("... Done encrypting message");
		return($enc);
	}
	
	public static function decrypt($msg,$rcptid){
		gio::log("Decrypting message ...");
		$priv_key = GsonCrypt::getkey($rcptid,true,true);
		$res1= openssl_get_privatekey($priv_key,config::$privateKeyPassword);
		$tt=explode(":::",$msg);
		$cnt=count($tt); 
		$i=0; 
		while($i<$cnt){ 
			openssl_private_decrypt($tt[$i],$str1,$res1); 
			@$str.=$str1; 
			$i++; 
		} 
		gio::log("... Done decrypting message");
		return $str;      		
	}

	public static function create($msg){
		gio::log("Creating message ...");
		$prepared = base64_encode(json_encode($msg));
		gio::log("... Done creating message");
		return $prepared;
	}
	
	public static function extract($msg){
		if(!is_string($msg)) return $msg;
		gio::log("Extracting message ...");
		$extract = json_decode(base64_decode($msg),true);
		gio::log("... Done Extracting message");
		return $extract;
	}
	
	public static function prepare($msg,$type,$rcpt){
		$m = array("op"=>$type,"msg"=>$msg,"sender"=>config::$accountId,"recipient"=>$rcpt,"bank"=>config::$bankId);
		return $m;
	}
}
?>