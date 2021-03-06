<?php	
class GsonCrypt{
	public static $keypass = "";
	public static $keyOpts = array('digest_alg' => "sha1", 'private_key_bits' => 1024,'private_key_type' => OPENSSL_KEYTYPE_RSA,'encrypt_key' => true,'config'=>'');
	
	public static function getkey($userid="",$type=0,$data=false){ // $type => 0|false = public, 1|true = private
		if(empty($userid)) $userid = config::$accountId;
		$ext = $type?'pem':'pem';
		$subdir = $type?'private':'public';
		$kfile = Tools::makepath(array(HOME_DIR,"keys",$subdir,"$userid.$ext"));
		gio::log("Fetching $userid's $subdir key ...",VERBOSE);
		$tfile = config::$encryptLocalStorage?"$kfile.".(config::$encrypedLocalStorageExtention):"$kfile";
		if($data&&!is_readable($tfile)){
				gio::log("Key file '$subdir::$tfile' does not exist or is not readable",E_USER_WARNING);
				return false;
		}
		$ret=!$data?$kfile:($subdir=='private'?openssl_get_privatekey(gio::readfile($kfile),config::$privateKeyPassword):openssl_get_publickey(gio::readfile($kfile)));
		gio::log("... $userid's $subdir key OK.",VERBOSE);
		return $ret;
		
	}

	public static function getcert($userid="",$data=false){
		if(empty($userid)) $userid = config::$accountId;
		gio::log("Getting certificate for $userid ...", VERBOSE);
		$cfile = Tools::makepath(array(HOME_DIR,"keys","certificate","$userid.crt"));
		$ret = $data ? (gio::readrawfile($cfile)) : $cfile;
		gio::log("Done getting certificate for $userid ...", VERBOSE);
		return $ret;
	}
	
	public static function hascert($userid=""){
		if(empty($userid)) $userid = config::$accountId;
		$cert = self::getcert($userid);
		if(!file_exists($cert)){
			$m = Gmsg::create(Gmsg::prepare("","register",$userid));
			$net = new Gnet;
			$r = $net->send($m);
			unset($net);
			if(!$r); return false;
			$r = extract($r);
			if(!$r||!$r['status']) return false;
			if(!gio::saverawfile($r['cert'],$cert)) return false;
		}
		return true;
	}
	
	public static function sign($data, $senderid="", $signatureOnly=false){
		gio::log("Signing message ...",VERBOSE);
		if(empty($senderid)) $senderid = config::$accountId;
		$pkeyid = self::getkey($senderid,true,true);
		$ok = openssl_sign($data, $signature, $pkeyid);
		openssl_free_key($pkeyid);
		if(!$ok){
			gio::log("Error while signing data ...",E_USER_WARNING);
			return false;
		}
		$data = "$data::SIGNATURE::".base64_encode($signature);
		gio::log("... Done signing message",VERBOSE);
		return $signatureOnly?$signature:$data;
	}

	public static function verify($data, $senderid){
		gio::log("Verifying message ...",VERBOSE);
		$pubkeyid = self::getkey($senderid,false,true);
		if(!$pubkeyid) $pubkeyid = openssl_get_publickey(self::getcert($senderid,true));
		if(!$pubkeyid) return false;
		$data = explode("::SIGNATURE::",$data);
		$signature = base64_decode($data[1]);
		$data = $data[0];
		$ok = openssl_verify($data, $signature, $pubkeyid);
		if($ok<1){
			if($ok<0){
				gio::log("Error while verifying data from $senderid ...",E_USER_WARNING);
			}else{
				gio::log("Invalid signature detected while verifying data from $senderid ...",E_USER_WARNING);
			}
			return false;
		}
		gio::log("... Done verifying message",VERBOSE);
		return $data;
	}
	
	public static function seal($data,$rcptids=array()){
		gio::log("Sealing message ...",VERBOSE);
		if(is_string($rcptids)||is_numeric($rcptids)) $rcptids = array("$rcptids");
		if(!is_array($rcptids)){return false;}else{$pk=array();}
		foreach($rcptids as $rcptid){
			$pk[$rcptid] = self::getkey($rcptid,false,true);
		}
		$rcpts = join(",",$rcptids);
		if(@!openssl_seal($data, $sealed, $ekeys, $pk)){
			gio::log("Unable to seal message to $rcpts ...",E_USER_WARNING);
			return false;			
		}
		foreach($pk as $k=>$v){openssl_free_key($pk[$k]);}
		$enc = base64_encode($sealed);
		foreach($ekeys as $ekey):$enc.="::".base64_encode($ekey);endforeach;
		gio::log("... Done sealing message to $rcpts",VERBOSE);
		return $enc;
	}

	public static function unseal($data,$rcptid=""){
		gio::log("Unsealing message ...",VERBOSE);
		if(empty($rcptid)) $rcptid = config::$accountId;
		if(is_array($rcptid)){return false;}
		$open = "";
		$data = explode("::",$data);
		$dlen = count($data);
		foreach($data as $k=>$v):$data[$k]=base64_decode($v);endforeach;
		$pkeyid = self::getkey($rcptid,true,true);
		for($i=1;$i<$dlen;$i++){
			if (@!openssl_open($data[0], $open, $data[$i], $pkeyid)){
				if($i < $dlen - 1) continue;
				gio::log("Unable to open sealed data ...",E_USER_WARNING);
				return false;
			}
		}
		@openssl_free_key($pkeyid);
		gio::log("... Done unsealing message",VERBOSE);
		return $open;
	}
	
	public static function keygen(&$userid,$info=false){
		$dn = is_array($info)?$info:array("countryName" => strtoupper(gio::input("Country code","string")), "stateOrProvinceName" => strtoupper(gio::input("State code","string")), "localityName" => gio::input("City","string"), "organizationName" =>gio::input("Bank's Full Name","string"), "organizationalUnitName" => 'Digicoin', "commonName" => gio::input("Bank's Short Name","string"), "emailAddress" => gio::input("Help/Contact Email Address","string"));
		$userid = empty($userid)?$dn['commonName']:$userid;
		$privkeypass = config::$privateKeyPassword;
		if(!self::cryptoInstalled()){
			gio::log("... Could not generate cryptographic keys for $userid ...",E_USER_ERROR);
			return false;
		}
		gio::log("Generating cryptographic keys for $userid...",VERBOSE);
		try{
			$privkey = @openssl_pkey_new(self::$keyOpts);
			$privateKey = "";
			$csr = @openssl_csr_new($dn, $privkey, self::$keyOpts);
			if($csr){
				openssl_csr_export_to_file($csr, self::getcert($userid));
				openssl_pkey_export($privkey, $privatekey, $privkeypass, self::$keyOpts);
				gio::savetofile($privatekey,(self::getkey($userid,true)),config::$privateKeyFileMode);
				gio::savetofile($userid,config::$accountIdFile);config::$accountId = $userid;
			}else{
				return false;
			}
		}catch(Exception $e){
			gio::log("Error while generating cryptographic keys for $userid: ".($e->message),E_USER_ERROR);
			return false;
		}
		gio::log("... Done generating cryptographic keys for $userid");
		return true;
	}
	
	public static function cryptoInstalled(){
		gio::log("Checking Cryptographic library Installation ...");
		if(function_exists("openssl_pkey_new")){
			gio::log("... Cryptographic library Installation OK");
			return true;
		}
		gio::log("... Cryptographic library Not Installed ...",E_USER_WARNING);
		return false;
	}
	
	public static function getLocalEncKeys($pass=""){
		$passphrase = $pass?$pass:(config::$localEncryptionStorageKey);
		$iv = substr(md5('iv'.$passphrase, true), 0, 8);
		$key = substr(md5('pass1'.$passphrase, true).(md5('pass2'.$passphrase, true)), 0, 24);
		return array('iv'=>$iv, 'key'=>$key);
	}
	
	public static function getLocalEncAlgo($encOrDec){
		$algo = config::$localEncryptionAlgorithm;
		return "$encOrDec.$algo";
	}
	
	public static function getWalEncAlgo(){
		$algo = strtoupper("mcrypt_".config::$walalgo);
		return constant("$algo");
	}
}
?>