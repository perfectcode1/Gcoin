<?php
class account {
	public static function create(){
		$status = false;
		$net = new Gnet;
		if(GsonCrypt::cryptoInstalled()){
			if(!self::isCreated()){
				if(!self::configserver()){
					gio::output("Account Creation Failed! Service address and port configuration failed");
					return false;
				}
				if(!GsonCrypt::keygen(config::$accountId)){
					gio::output();
					gio::output("Account Creation Failed! You may have entered some parameters wrongly");
					self::rollback();
					return false;
				}
			}else{
				gio::output("Account is already created");
				return false;
			}
			$cert = GsonCrypt::getcert();
			$csr = gio::readrawfile($cert);
			$m=Gmsg::create(Gmsg::prepare($csr,"signcert",config::$SRA));
			$m=GsonCrypt::seal($m,config::$SRA);
			$r = $net->send($m,true);
			if(!$r){
				gio::output();
				gio::output("Account Creation Failed! Unable to connect to account creation server");
			}else{
				$r = GsonCrypt::unseal($r);
				if(!$r){
					gio::output();
					gio::output("Account Creation Failed! Ensure that the short name you entered is unique to your bank");
					self::rollback();
					return false;
				}
				$r = Gmsg::extract($r);
			}
			if($r&&$r['status']){
				$data = $r['response'];
				if($r['status']==1&&$data){
					gio::saverawfile($data,$cert);
					gio::savetofile($data,GsonCrypt::getkey());
					gio::output("Your account was created succesfully");
					if(is_readable(config::$walCfgFile)){
						$cfg = unserialize(gio::readfile(config::$walCfgFile));
						config::$networkInterface = $cfg[0];
						config::$walletPort = $cfg[1];
						config::$remoteAddress = $cfg[2];
						config::$remotePort = $cfg[3];
					}
					gio::output(sprintf("Your account address is '%s'", self::address()));
					$status = popen("service.ecsh",'r');
				}else{
					gio::output($r['response']);
				}
			}else{
				self::rollback();
				gio::output($r['response']);
			}
		}else{
			gio::output("Kindly install the cryptographic library tools.");
		}
		$net = null;
		return $status;
	}
	
	public static function destroy(){
		$status = false;
		$net = new Gnet;
		if(!self::isCreated()){
			gio::output("No account found!");
		}else{
			$m=Gmsg::create(Gmsg::prepare(config::$accountId,"revokecert",config::$SRA));
			$m=GsonCrypt::seal($m,config::$SRA);
			$r = $net->send($m,true);
			if(!$r){
				gio::output();
				gio::output("Account Destruction Failed! Unable to connect to account server");
			}else{
				$r = GsonCrypt::unseal($r);
				$r = Gmsg::extract($r);
				if($r['status']){
					self::rollback();
					gio::output("Your account has been destroyed");
				}else{
					gio::output($r['response']);
				}
			}
		}
	}

	public static function configserver(){
		$dint = config::$networkInterface;
		$dport = config::$walletPort;
		$rdint = config::$remoteAddress;
		$rdport = config::$remotePort;
		$int = 0;
		$port = 0;
		$rint = 0;
		$rport = 0;
		while(true){
			$int = gio::input("Enter the interface ip address to listen on [Default: $dint]");
			if(empty($int)) $int = "$dint";
			if(filter_var($int,FILTER_VALIDATE_IP)) break;
			$int = 0;
		}
		while($int&&true){
			$port = intval(gio::input("Enter the port address to listen on [Default: $dport]"));
			if(empty($port)) $port = $dport;
			if($port>0&&$port<65536) break;
			$port = 0;
		}
		while($port&&true){
			$rint = gio::input("Enter the interface ip address of the SRA [Default: $rdint]");
			if(empty($rint)) $rint = "$rdint";
			if(filter_var($rint,FILTER_VALIDATE_IP)) break;
			$rint = 0;
		}
		while($port&&$rint&&true){
			$rport = intval(gio::input("Enter the port of the SRA to connect to [Default: $rdport]]"));
			if(empty($rport)) $rport = $rdport;
			if($rport>0&&$rport<65536) break;
			$rport = 0;
		}		
		if($int && $port && $rint && $rport){
			if(gio::savetofile(serialize(array($int,$port,$rint,$rport)),config::$walCfgFile)) return true;
		}
		return false;
	}
	
	public static function isCreated(){
		$k = @GsonCrypt::getkey(null,true);
		return file_exists($k);
	}
	
	public static function coins($account,&$coins){
		$f = $account!=config::$accountId ? $account : null;
		$coins = storage::load($f);
		if(!$coins) $coins = array();
		$cnt = 0;
		foreach($coins as $val=>$ccs){
			$cnt += (intval(count($ccs)) * intval($val));
		}
		return $cnt;
	}
	
	public static function pullcoins($msg,$account){
		$m = GsonCrypt::verify($msg,$account);
		if(!$m) return array(0,"Bank did not verify your message as authentic");
		$m = Gmsg::extract($m);
		if(!$m) return array(0,"Bank did not extract your message correctly");
		if($m['account']==$account){
			$n = self::coins($account,$coins);
			return array(1,$coins);
		}
		return array(0,"Failed!");		
	}
	
	public static function address(){
		$address = null;
		if(self::isCreated()){
			$address = Tools::arrvtostr(array(config::$networkInterface,config::$walletPort,config::$accountId),":");
		}
		return $address;
	}
	
	public static function merckey($file=""){
		$s = GsonCrypt::sign(md5(gio::readfile(GsonCrypt::getcert())));
		if($file){
			$file .= ".txt";
			$ofile = Tools::makepath(array(getenv('USERPROFILE'),"Desktop","$file"));
			if(gio::saverawfile("MERCHANT/ACCOUNT KEY: '$s'",$ofile)){
				gio::output("The file $file was sucessfully written to your desktop");
			}else{
				gio::output("Error while writing $file to your desktop");
			}	
		}else{
			return $s;
		}
	}
	
	public static function exists($account){
		$k = @GsonCrypt::getkey($account);
		return file_exists($k);
	}
	
	public static function deposit($coins,$account){
		if(!is_array($coins)||($account!=config::$accountId&&!account::exists($account))) return false;
		if($account==config::$accountId){
			foreach($coins as $id=>$coin){
				$coins[$id]['secret'] = GsonCrypt::unseal($coin['secret']);
				if(!$coins[$id]['secret']) return false;
			}
		}
		$dest = strtolower($account)==strtolower(config::$accountId) ? config::$walfile : $account;
		$old = storage::load($dest);
		if(!$old) $old = array();
		foreach($coins as $id=>$coin){
			$val = $coin['value'];
			$old[$val][$id] = $coin;
		}
		return storage::save($old,$dest);
	}
	
	public static function spentcoins($coins,$account){
		if(!is_array($coins)||($account!=config::$accountId&&!account::exists($account))) return false;
		if(!is_array($coins)) return false;
		$dest = strtolower($account)==strtolower(config::$accountId) ? config::$walfile : $account;
		$old = storage::load($dest);
		if(!$old) return false;
		foreach($coins as $id=>$coin){
			$val = $coin['value'];
			if(array_key_exists($id,$old[$val])){
				unset($old[$val][$id]);
			}
		}
		$ret = storage::save($old,$dest);
		$ret = $ret ? 1 : 0;
		return $ret;
	}
	
	public static function getmycoins($amount){
		$bal = self::coins(config::$accountId,$coins);
		if(!$bal||$bal<$amount) return false;
		$vals = array_keys($coins);
		rsort($vals);
		foreach($vals as $l=>$val){
			if(empty($coins[$val])) continue;
			if($amount<=0) break;
			$ch[$val] = floor($amount/$val);
			$amount -= $ch[$val] * $val;
			while($amount<=$val){
				$t = count($vals);
				$rs = 0;
				for($i=$l+1;$i<$t;$i++){
					$rs += $vals[$i]*(count($coins[$i]));
				}
				if($amount<$rs) break;
				$ch[$val]++;
				$amount -= $val;
			}
		}
		foreach($ch as $v=>$n){
			foreach($coins[$v] as $id=>$coin){
				if($n<=0) break;
				$getcoins[$id] = $coin;
				$n--;
			}
		}
		return $getcoins;
	}
	
	public static function makenew(){
		do{
			$account = rand(111111,999999);
		}while(self::exists($account));
		return $account;
	}
	
	private static function rollback(){
		@unlink(GsonCrypt::getkey(null));
		@unlink(GsonCrypt::getkey(null,true));
		@unlink(GsonCrypt::getcert());
		@unlink(config::$accountIdFile);
		@unlink(config::$walCfgFile);
		@config::$accountId = null;
	}
}
?>