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
	
	public static function ecash($number=1,$coinvalue=1){
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
		return self::verifyhash($coin['token']);
	}
	
	private static function hash($in){
		$h = new ghash("$in",20);
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