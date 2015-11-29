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
			$enc.="{:::}"; 
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
		$tt=explode("{:::}",$msg);
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
		$m = array("op"=>$type,"msg"=>$msg,"sender"=>config::$accountId,"recipient"=>$rcpt);
		return $m;
	}
	
	public static function process($msg){
		$res = "";
		$status = 1;
		$sender = "";
		$umsg = GsonCrypt::unseal($msg);
		if(!$umsg){
			$ex = Gmsg::extract($msg);
			if($ex && is_array($ex)){
				$umsg = $msg;
			}else{
				$status = 0;
				$res = "Unable to decode the message";
			}
		}
		if($umsg){
			$parts = self::extract($umsg);
			$action = $parts["op"];
			$mess = $parts["msg"];
			$recipient = $parts["recipient"];
			$sender = $parts["sender"];
			if(isset($parts["bank"])) $sender = $parts["bank"]."_$sender";
			if(strtolower($recipient) != strtolower(config::$accountId)){
				$status = 0;
				$res = config::$accountId." is not the intended recipient [$recipient]";
				$rply = Gmsg::create(array("status"=>$status,"response"=>$res));
			}else{
				switch($action){
					case "notification":
						$r = transaction::notification($mess, $sender);
						$m = Gmsg::create(array("status"=>$r[0],"response"=>$r[1]));
						$rply = GsonCrypt::sign($m);
						break;
					case "revokecert":
						if(!$sender){
							$status = 0;
							$res = "The sender is unknown";
						}else{
							$res = "";
							$ret = array("status"=>$status,"response"=>$res,"account"=>$sender);
							$rply = self::create($ret);
							$rply = GsonCrypt::seal("$rply","$sender");
							@unlink(GsonCrypt::getkey($sender)); /* Buggy: Verify the sender first*/
						}
						break;
					case "signcert":
						$k = GsonCrypt::getkey("$sender");
						if(file_exists($k)){
							$status = 2;
							$res = "This account already exist!";
						}else{
							$res = GsonCrypt::signcert($sender,$mess);
							if(!$res){
								$status = 0;
								$res = "An error occured while signing the certificate.";
							}
						}
						break;
					case "reverb":
						$res = $mess;
						break;
					default:
						$status = 0;
						$res = "Invalid Operation!";
				}
			}
		}
		if(!isset($rply)){
			$ret = array("status"=>$status,"response"=>$res,"account"=>$sender);
			$rply = self::create($ret);
			$rply = $sender?(GsonCrypt::seal("$rply","$sender")):("$rply");
		}
		return $rply;
	}
}
?>