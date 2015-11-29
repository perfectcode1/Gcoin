<?php
class account {
	public static function create(){
		$status = false;
		$net = new Gnet;
		if(GsonCrypt::cryptoInstalled()){
			if(!self::isRegistered()){
				if(!self::register()){
					gio::output("Account Creation Failed! Bank Registration Not Successful");
					return false;
				}
			}
			if(!self::isCreated()){
				if(!GsonCrypt::keygen(config::$accountId)){
					gio::output();
					gio::output("Account Creation Failed! You may have entered some parameters wrongly");
					self::rollback();
					self::deregister();
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
			$bm = Gmsg::create(Gmsg::prepare($m,"signcert",config::$bankId));
			$r = $net->send($bm);
			if(!$r){
				gio::output();
				gio::output("Account Creation Failed! Unable to connect to account creation server");
			}else{
				$s = GsonCrypt::unseal($r);
				$r = $s?Gmsg::extract($s):Gmsg::extract($r);
				if(!$r){
					gio::output();
					gio::output("Account Creation Failed! Unable to read the response");
					self::rollback();
					self::deregister();
					return false;
				}
				$r = Gmsg::extract($r);
			}
			if($r&&$r['status']){
				$data = $r['response'];
				if($r['status']==1&&$data){
					$st=Gmsg::extract($net->send(Gmsg::create(Gmsg::prepare($data,"create",config::$bankId)),config::$bankId));
					if(!$st||!$st['status']){
						gio::output("Account Creation Failed! Account creation rejected by bank");
						self::rollback();
						self::deregister();
						return false;
					}
					gio::saverawfile($data,$cert);
					config::$accountId = gio::readfile(config::$accountIdFile);
					config::$bankId = gio::readfile(config::$bankIdFile);
					gio::output("Your account was created succesfully");
					gio::output(sprintf("Your account address is '%s'", self::address()));
					$status = true;
				}else{
					gio::output($r['response']);
				}
			}else{
				self::rollback();
				self::deregister();
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
			$b=GsonCrypt::seal($m,config::$SRA);
			$bm=Gmsg::create(Gmsg::prepare($m,"revokecert",config::$bankId));
			$r = $net->send($bm);
			if(!$r){
				gio::output();
				gio::output("Account Destruction Failed! Unable to connect to account server");
			}else{
				$tr = GsonCrypt::unseal($r);
				if(!$tr){
					$ex = Gmsg::extract($r);
					if(!$ex || !is_array($ex)){
						gio::output();
						gio::output("Account Destruction Failed! Server returned an invalid message");
					}
				}
				$r = isset($ex)?$ex:($tr?(Gmsg::extract($tr)):null);
				if($r['status']){
					$m=Gmsg::create(Gmsg::prepare(config::$accountId,"remove",config::$bankId));
					$rmv = $net->send($m);
					self::rollback();
					if(gio::confirm(sprintf("Do you also want to deregister the bank '%s'",config::$bankId))){
						self::deregister();
					}
					gio::output("Your account has been destroyed");
				}else{
					gio::output($r['response']);
				}
			}
		}
	}
	
	public static function isRegistered(){
		return file_exists(config::$bankIdFile);		
	}
	
	public static function isCreated(){
		$k = @GsonCrypt::getkey(null,true);
		return file_exists($k);
	}
	
	public static function balance(&$coins){
		self::pullcoins();
		$coins = storage::load();
		if(!$coins) $coins = array();
		$cnt = 0;
		foreach($coins as $val=>$ccs){
			$cnt += (intval(count($ccs)) * intval($val));
		}
		return $cnt;
	}
	
	public static function pullcoins(){
		$m = Gmsg::create(Gmsg::prepare(GsonCrypt::sign(Gmsg::create(array('account'=>config::$accountId))),'pullcoins',config::$bankId));
		$net = new Gnet;
		$r = $net->send($m);
		$v = GsonCrypt::verify($r,config::$bankId);
		if(!$v){
			$status = 0;
			gio::log("Unable to verify response from bank while pulling coins",E_USER_WARNING);
		}else{
			$v = Gmsg::extract($v);
			if(!$v){
				$status = 0;
				gio::log("Unable to understand the response while pulling coins",E_USER_WARNING);
			}else{
				$status = $v['status'];
				$res = $v['response'];
			}
		}
		if(!$status){
			gio::output("Could not pull eCash from bank");
		}else{
			if(!is_array($res)){
				gio::output("Could not pull eCash from bank");
			}else{
				$check = 1;
				foreach($res as $val=>$ccs){
					foreach($ccs as $id=>$coin){
						$res[$val][$id]['secret'] = GsonCrypt::unseal($coin['secret']);
						if(!$res[$val][$id]['secret']) $check = 0;
					}
				}
				if(!$check) gio::output("Could not get the secret of some eCash");
			}
			if(is_array($res)&&count($res)){
				$old = array();
				foreach($res as $val=>$ccs){
					foreach($ccs as $id=>$coin){
						$val = $coin['value'];
						$old[$val][$id] = $coin;
					}
				}
				storage::save($old);
			}
		}
	}

	public static function getcoins($amount){
		$bal = self::balance($coins);
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

	public static function spentcoins($coins){
		if(!is_array($coins)) return false;
		self::balance($wal);
		if(!is_array($wal)) return false;
		foreach($coins as $id=>$coin){
			$val = $coin['value'];
			if(array_key_exists($id,$wal[$val][$id])){
				unset($wal[$val][$id]);
			}
			
		}
		$ret = storage::save($wal);
		$ret = $ret ? 1 : 0;
		return $ret;
	}
	
	public static function address(){
		$address = null;
		if(self::isCreated()){
			$address = Tools::arrvtostr(array(config::$bankAddress,config::$bankPort,config::$bankId."_".config::$accountId),":");
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
	
	private static function rollback(){
		unlink(GsonCrypt::getkey(null));
		unlink(GsonCrypt::getkey(null,true));
		unlink(GsonCrypt::getcert());
		unlink(config::$accountIdFile);
		config::$accountId = null;
	}
	
	private static function register(){
		if(self::isRegistered()){
			return true;
		}
		$ba = Tools::address(gio::input("Enter the bank's address"));
		if(!$ba) return false;
		config::$bankAddress = $ba['address'];
		config::$bankPort = intval($ba['port']);
		$net = new Gnet;
		$r=$net->send(Gmsg::create(Gmsg::prepare("","register",config::$bankId)));
		$net = null;
		if(!$r) return false;
		$m = Gmsg::extract($r);
		if(!$m['status']) return false;
		if(gio::saverawfile($m['cert'],GsonCrypt::getcert($m['name']))&&gio::savetofile($m['cert'],GsonCrypt::getkey($m['name']))){
			if(gio::savetofile($m['name'],config::$bankIdFile)&&gio::savetofile(serialize(array(config::$bankAddress,config::$bankPort)),config::$walCfgFile)){
				config::$bankId = $m['name'];
				config::$accountId = $m['account'];
				return true;
			}else{
				self::deregister();
				return false;
			}
		}
		return false;
	}

	private static function deregister(){
		@unlink(GsonCrypt::getkey(config::$bankId));
		@unlink(GsonCrypt::getcert(config::$bankId));
		@unlink(config::$bankIdFile);
		@unlink(config::$walCfgFile);
		@config::$bankId = null;
	}
}
?>