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
		$m = array("op"=>$type,"msg"=>$msg,"sender"=>config::$accountId,"recipient"=>$rcpt);
		return $m;
	}
	
	public static function process($msg){
		$status = 1;
		$sender = "";
		$res = "";
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
			$sender = $parts["sender"];
			$recipient = $parts["recipient"];
			if($recipient&&!account::exists($recipient)){
				$status = 0;
				$res = "The recipient account $recipient does not reside here";
				$rply = Gmsg::create(array("status"=>$status,"response"=>$res));
			}else{
				switch($action){
					case "mrequest":
						$r = transaction::request($mess['m'],$sender,$mess['k']);
						$rply = Gmsg::create(array("status"=>$r[0],"response"=>$r[1]));						
						break;
					case "mstatus":
						$r = transaction::mercorder($mess['m'],$sender,$mess['k']);
						$rply = Gmsg::create(array("status"=>$r[0],"response"=>$r[1]));												
						break;
					case "statusrequest":
						$r = transaction::status($mess,$sender);
						$m = Gmsg::create(array("status"=>$r[0],"response"=>$r[1]));
						$rply = GsonCrypt::sign($m);
						break;
					case "paymentrequest":
						$r = transaction::request($mess,$sender);
						$m = Gmsg::create(array("status"=>$r[0],"response"=>$r[1]));
						$rply = GsonCrypt::sign($m);
						break;
					case "pullrequests":
						$r = transaction::pullrequests($mess,$sender);
						$m = Gmsg::create(array("status"=>$r[0],"response"=>$r[1]));
						$rply = GsonCrypt::sign($m);
						break;
					case "pullcoins":
						$r = account::pullcoins($mess,$sender);
						$m = Gmsg::create(array("status"=>$r[0],"response"=>$r[1]));
						$rply = GsonCrypt::sign($m);
						break;
					case "clearrequests":
						$r = transaction::clearrequests($mess,$sender);
						$m = Gmsg::create(array("status"=>$r[0],"response"=>$r[1]));
						$rply = GsonCrypt::sign($m);
						break;
					case "notification":
						$r = transaction::notification($mess,$sender);
						$m = Gmsg::create(array("status"=>$r[0],"response"=>$r[1]));
						$rply = GsonCrypt::sign($m);
						break;
					case "acknowledgement":
						$r = transaction::acknowledgement($mess,config::$SRA);
						$m = Gmsg::create(array("status"=>$r[0],"response"=>$r[1]));
						$rply = GsonCrypt::sign($m);
						break;
					case "deposit":
						$r = account::deposit($mess,$recipient);
						if(!$r){
							$status = 0;
							$res = "Deposit failed";
						}else{
							$res = "Deposit was successful";
						}
						break;
					case "revokecert":
						$net = new Gnet;
						$rply = $net->send("$mess",true);
						$net = null;
						break;
					case "signcert":
						$net = new Gnet;
						$rply = $net->send("$mess",true);
						$net = null;
						break;
					case "register":
						$k = GsonCrypt::getcert();
						if(is_readable($k)){
							$res = gio::readfile($k);
							if(!$res) $status = 0;
						}
						$rply = Gmsg::create(array("status"=>$status,"cert"=>$res,"name"=>config::$accountId,"account"=>account::makenew()));
						break;
					case "create":
						$status = gio::savetofile($mess, GsonCrypt::getkey("$sender"));
						$res = $status ? "successful" : "failed";
						$rply = Gmsg::create(array("status"=>$status,"response"=>$res));
						break;
					case "remove":
						$res = "";
						$ret = array("status"=>$status,"response"=>$res);
						$rply = self::create($ret);
						$rply = GsonCrypt::seal("$rply","$sender");
						unlink(GsonCrypt::getkey($sender));
						break;
					case "exchangecert":
						$status = 0;
						if(!file_exists(GsonCrypt::getcert("$sender"))) $status = gio::saverawfile($mess, GsonCrypt::getcert("$sender"));
						$k = GsonCrypt::getcert();
						if($status&&is_readable($k)){
							$res = gio::readfile($k);
							if(!$res) $status = 0;
						}
						$rply = Gmsg::create(array("status"=>$status,"cert"=>$res));
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
			$ret = array("status"=>$status,"response"=>$res);
			$rply = self::create($ret);
			$rply = $sender ?(GsonCrypt::seal("$rply","$sender")):("$rply");
		}
		return $rply;
	}
}
?>