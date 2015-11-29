<?php
class account {
	public static function create(){
		$status = false;
		$net = new Gnet;
		if(GsonCrypt::cryptoInstalled()){
			if(!self::isCreated()) GsonCrypt::keygen(config::$accountId);
			$cert = GsonCrypt::getcert();
			$csr = gio::readrawfile($cert);
			$m=Gmsg::create(Gmsg::prepare($csr,"signcert",config::$SRA));
			$m=GsonCrypt::seal($m,config::$SRA);
			$r = $net->send($m,true);
			$r = GsonCrypt::unseal($r);
			$r = Gmsg::extract($r);
			if($r['status']){
				$data = $r['response'];
				if($r['status']==1&&$data){
					gio::saverawfile($data,$cert);
					gio::savetofile($data,GsonCrypt::getkey());
					gio::output(sprintf("The account '%s' was created succesfully"));
					$status = true;
				}else{
					gio::output($r['response']);
				}
			}else{
				unlink(GsonCrypt::getkey(null,true));
				gio::output($r['response']);
			}
		}else{
			gio::output("Kindly install the cryptographic library tools.");
		}
		$net = null;
		return $status;
	}
	
	public static function isCreated(){
		$k = GsonCrypt::getkey(null,true);
		return file_exists($k);
	}
	
	public static function give($to,$amt){
		$ramt = $amt;
		$rem = mine::countcoins($coins);
		if($rem<$amt){
			gio::output("Not enough coins");
			return false;
		}
		$paths = Tools::address($to);
		if(!$paths){
			gio::output("The destination account is invalid");
			return false;
		}
		$getcoins = array();
		$c = 1;
		if(!is_array($coins)) return false;
		$vals = array_keys($coins);
		rsort($vals);
		foreach($vals as $val){
			if($amt<$val) continue;
			if($amt<=0) break;
			$ch[$val] = floor($amt/$val);
			if($ch[$val] > count($coins[$val])) $ch[$val] = count($coins[$val]);
			$amt -= $ch[$val] * $val;
		}
		foreach($ch as $v=>$n){
			while($n>0&&list($id,$coin)=each($coins[$v])){
				$getcoins[$id] = $coin;
				unset($coins[$v][$id]);
				$n--;
			}
		}
		foreach($getcoins as $k=>$v){
			$secret[$k] = Tools::makesecrets();
			$getcoins[$k]['secret'] = GsonCrypt::seal($secret[$k],$paths['srakey']);
		}
		$net = new Gnet;
		if(!$net->connect($paths['address'],intval($paths['port']))){
			gio::output("Unable to connect to the destination account");
			return false;			
		}else{
			$m = Gmsg::create(Gmsg::prepare($getcoins,"deposit",$paths['account']));
			$m = GsonCrypt::seal($m,$paths['bank']);
			if(!$m){
				gio::output("Unable to send!");
				gio::output("POSSIBLE CAUSES:");
				gio::output("The destination bank's certificate is not avaiable!");
				gio::output("Account may not be registered with sra!");
				gio::output("Account may have been deregistered with sra!");
				return false;
			}
			$r = $net->send($m);
			$s = GsonCrypt::unseal($r);
			$r = $s?Gmsg::extract($s):Gmsg::extract($r);
			unset($net);
			if(!$r||!$r['status']){
				gio::output("Deposit of $ramt coins to $to Failed!");
				gio::output($r['response']);
				return false;
			}else{
				$old = storage::load($paths['srakey']);
				foreach($getcoins as $id=>$coin){
					$getcoins[$id]['secret'] = $secret[$id];
					$val = $coin['value'];
					$old[$val][$id] =& $getcoins[$id];
				}
				storage::save($old,$paths['srakey']);
				storage::save($coins);
				gio::output("Deposit of $ramt coins to $to was successful");
				return true;			
			}
		}
	}
	
	public static function spentcoins($coins,$account){
		if(!is_array($coins)) return false;
		$old = storage::load($account);
		if(!$old) return false;
		foreach($coins as $id=>$coin){
			$val = $coin['value'];
			if(array_key_exists($id,$old[$val])){
				unset($old[$val][$id]);
			}
			
		}
		$ret = storage::save($old,$account);
		$ret = $ret ? 1 : 0;
		return $ret;
	}
}
?>