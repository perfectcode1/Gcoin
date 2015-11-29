<?php
class mine {
	public static function countcoins(&$coins=null){
		$coins = storage::load();
		if(!$coins) $coins = array();
		$cnt = 0;
		foreach($coins as $val=>$ccs){
			$cnt += (intval(count($ccs)) * intval($val));
		}
		return $cnt;
	}
	
	public static function checkcoins($coins){
		if(!is_array($coins)) return false;
		foreach($coins as $coin){
			if(!self::coinisvalid($coin)) return false;
		}
		return true;
	}
	
	public static function coins($number=1,$coinvalue=1){
		$badsav = 0;
		$newcoin = array();
		$starttime = time();
		while($number){
			gio::output(sprintf("Remaining: %d",$number));
			$newcoin[$coinvalue] = self::minecoin($coinvalue);
			if(!self::savecoins($newcoin)) $badsav++;
			$number--;
		}
		if($badsav) gio::output("Failed to save $badsav mined coins!");
		$endtime = time();
		$dur = $endtime - $starttime;
		$secs = $dur%60;
		$mins = floor($dur/60);
		$hrs = floor($mins/60);
		$mins -= $hrs*60;
		return array("Hours"=>$hrs,"Minutes"=>$mins,"Seconds"=>$secs);
	}
	
	private static function minecoin($value=1){
		$id = md5(uniqid(rand(), true));
		$token = self::hash("$value:$id");
		$hash = magic::enc($token);
		$coin[$id] = array("miner"=>config::$accountId,"mined"=>time(),"token"=>"$token","value"=>"$value","secret"=>"","transactioncount"=>0,"hash"=>"$hash");
		return $coin;
	}
	
	private static function savecoins($coins){
		$old = storage::load();
		if(!$old) $old = array();
		foreach($coins as $val=>$ccs){
			foreach($ccs as $id=>$coin){
				$old[$val][$id] = $coin;
			}
		}
		if(storage::save($old)) return true;
		return false;
	}
	
	private static function coinisvalid($coin){
		if(magic::dec($coin['hash'])!=$coin['token']) return false;
		return self::verifyhash($coin['token']);
	}
	
	private static function hash($in){
		switch(config::$minestrength){
			case 1:
				$s = 22;
				break;
			case 2:
				$s = 25;
				break;
			case 3:
				$s = 28;
				break;
			case 4:
				$s = 32;
				break;
			case 5:
				$s = 35;
				break;
			case 6:
				$s = 40;
				break;
			case 7:
				$s = 50;
			default:
				$s = 20;
		}
		$h = new ghash("$in",$s);
		$token = '';
		try{
			$token = $h->hash();
		}
		catch(Exception $e){
			gio::log('ERROR: '.$e->getMessage());
		}
		unset($h);
		return $token;
	}
	
	private static function verifyhash($token){
		$h = new ghash();
		$v = $h->verify($token);
		unset($h);
		return $v;
	}
}
?>