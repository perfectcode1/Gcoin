<?php
class transaction {
	
	public static function notification($msg,$account){
		$m = GsonCrypt::verify($msg,$account);
		if(!$m) return array(0,"SRA did not verify bank's message as authentic");
		$m = Gmsg::extract($m);
		if(!$m||!is_array($m)) return array(0,"SRA did not extract bank's message correctly");
		list($id,$req) = each($m);
		$rtime = time();
		$client = Tools::address($req['client']);
		$merchant = Tools::address($req['merchant']);
		$coins = storage::load($client['srakey']);
		$pcoins = $req['coins'];
		$errorcode = 0;
		$amount = 0;
		$least = 0;
		$most = 0;
		$leastcoin = array();
		$mostcoin = array();
		$ecashrejected = array();
		if(mine::checkcoins($pcoins)){
			foreach($pcoins as $id=>$coin){
				$pcval = $coin['value'];
				if(!array_key_exists($id,$coins[$pcval])){
					$errorcode = $errorcode?$errorcode:2;
					$tmsg = "Already spent eCash detected";
					$ecids[] = $id;
					if($errorcode==2) $rejectedecash[] = $id;
				}else{
					$secret = GsonCrypt::unseal($coin['secret']);
					if($coins[$pcval][$id]['secret']!=$secret){
						$errorcode = $errorcode?$errorcode:3;
						$tmsg = "Invalid secret detected";
						$ecids[] = $id;
						if($errorcode==3) $rejectedecash[] = $id;
					}else{
						$secrets[$id] = Tools::makesecrets();
						$pcoins[$id]['secret'] = GsonCrypt::seal($secrets[$id],$merchant['srakey']);
						$pcoins[$id]['transactioncount'] = intval($pcoins[$id]['transactioncount']) + 1;
						$amount += $pcoins[$id]['value'];
						if($pcoins[$id]['value']>$most){
							$most = $pcoins[$id]['value'];
							$mostcoin['id'] = $id;
							$mostcoin['coin'] = $pcoins[$id];
						}
						if($pcoins[$id]['value']<$least||$least==0){
							$least = $pcoins[$id]['value'];
							$leastcoin['id'] = $id;
							$leastcoin['coin'] = $pcoins[$id];
						}
						$ecids[] = $id;
						unset($coins[$pcval][$id]);
					}
				}
			}
			$ecids = join(", ",array_values($ecids));
		}else{
			$ecids = join(", ",array_keys($pcoins));
			$errorcode = 1;
			$tmsg = "Invalid/Counterfeit eCash detected";
		}
		if(!$errorcode && $amount < $req['amount']){
			$errorcode = 4;
			$tmsg = sprintf("Additional %d value is required to complete the transaction",($req['amount'] - $amount));
		}
		if(!$errorcode && $amount>$req['amount']){
			$diff = $amount - $req['amount'];
			$rem = $leastcoin['coin']['value'] - $diff;
			$chg = self::splitvalue($diff,false);
			$dval = self::splitvalue($rem,false);
			if(!$chg||!$dval){
				$errorcode = 5;
				$tmsg = "SRA Internal Error while splitting tokens.";
			}else{
				$mycoin = storage::load();
				$chgcoins = self::getcoins($chg,$client['srakey'],$secrets,$mycoin);
				$dvalcoins = self::getcoins($dval,$merchant['srakey'],$secrets,$mycoin);
				if(!$chgcoins || !$dvalcoins){
					$errorcode = 6;
					$tmsg = "SRA Internal Error while splitting tokens";
				}else{
					$leastcoin['coin']['secret'] = "";
					$mycoin[$leastcoin['coin']['value']][$leastcoin['id']] = $leastcoin['coin'];
					foreach($chgcoins as $id=>$coin){
						$cval = $coin['value'];
						$coin['secret'] = $secrets[$id];
						$coins[$cval][$id] = $coin;
					}
					unset($pcoins[$leastcoin['id']]);
				}
			}
		}
		foreach($pcoins as $id=>$coin){
			$val = $coin['value'];
			if(isset($coins[$val][$id])) unset($coins[$val][$id]);
		}
		if(isset($dvalcoins)) $pcoins += $dvalcoins;
		$req['coins'] = $pcoins;
		$tmsg = $errorcode ? $tmsg : "pending";
		$tid = md5(uniqid(rand(),true));
		$dtime = time();
		$req['acknowledgement'] = array("id"=>"$tid","completed"=>$dtime,"status"=>"$errorcode","message"=>"$tmsg","amount"=>$amount,"ecashids"=>"$ecids");
		//if(isset($rejectedecash)&&!empty($ecashrejected)) $req['acknowledgement']['rejectedecash'] = join(", ",$rejectedecash);
		if(strtolower($client['bank'])!=strtolower($merchant['bank'])){
			$m = GsonCrypt::sign(Gmsg::create($req));
			$merchant['response'] = Gmsg::create(Gmsg::Prepare($m,"acknowledgement",$merchant['bank']));
			$net = new Gnet;
			$c = $net->connect($merchant['address'],intval($merchant['port']));
			if(!$c) return array(0,"Merchant's bank is unreachable");
			$r = $net->send($merchant['response']);
			if(!$r){
				$req['acknowledgement']['status'] = 10;
				$req['acknowledgement']['message'] = "Invalid response from merchant's bank";
			}
			$r = Gmsg::extract(GsonCrypt::verify($r,$merchant['bank']));
			if(!$r){
				$req['acknowledgement']['status'] = 11;
				$req['acknowledgement']['message'] = "Invalid response from merchant's bank";
			}else{
				if($r['status']){
					$old = storage::load($merchant['srakey']);
					if(!$old) $old = array();
					foreach($pcoins as $id=>$coin){
						$val = $coin['value'];
						$old[$val][$id] = $coin;
					}
					storage::save($old,$merchant['srakey']);
					storage::save($coins,$client['srakey']);
					if(isset($mycoin)) storage::save($mycoin);
					$req['acknowledgement']['message'] =& $r['response'];
				}else{
					$req['acknowledgement']['status'] = 12;
					$req['acknowledgement']['message'] = $r['response'];
				}
			}
		}else{
			if(!$errorcode){
				$old = storage::load($merchant['srakey']);
				if(!$old) $old = array();
				foreach($pcoins as $id=>$coin){
					$coin['secret'] = $secrets[$id];
					$val = $coin['value'];
					$old[$val][$id] = $coin;
				}
				storage::save($old,$merchant['srakey']);
				storage::save($coins,$client['srakey']);
				if(isset($mycoin)) storage::save($mycoin);
			}
			$status = $req['acknowledgement']['status'] ? 0 : 1;
			if($status) $req['acknowledgement']['message'] = "Payment was successful";
		}
		$status = $req['acknowledgement']['status'] ? 0 : 1;
		if(!$status){
			unset($req['coins']);
			unset($req['change']);
			if($errorcode<=3) $req['acknowledgement']['amount'] = 0;
		}
		if($status&&isset($chgcoins)) $req['change'] = $chgcoins;
		$resp = $req;
		self::log($resp);
		return array($status,$req);
	}
	
	public static function status($tid){
		$t = storage::load('__Transactions__.lg');
		if(!$t) return false;
		return $t[$tid];
	}
	
	public static function log(&$t){
		if(isset($t['coins'])) unset($t['coins']);
		if(isset($t['change'])) unset($t['change']);
		$tid = $t['acknowledgement']['id'];
		$tr[$tid] = $t;
		$old = storage::load('__Transactions__.lg');
		if(!$old) $old = array();
		$new = array_merge($old,$tr);
		storage::save($new,'__Transactions__.lg');
	}
	
	public static function splitvalue($amt,$truesplit=true){
		$bal = mine::countcoins($coins);
		if($bal < $amt) return false;
		$vals = array_keys($coins);
		rsort($vals);
		$ch = array();
		foreach($vals as $val){
			if($amt<=0) break;
			if($val>$amt) continue;
			if($truesplit && $val>=$amt) continue;
			$ch[$val] = floor($amt/$val);
			if($ch[$val] > count($coins[$val])) $ch[$val] = count($coins[$val]);
			$amt -= $ch[$val] * $val;
		}
		if($amt>0) return false;
		return $ch;
	}
	
	public static function getcoins($vals,$owner,&$secrets,&$mycoin){
		foreach($vals as $v=>$n){
			foreach($mycoin[$v] as $id=>$coin){
				if(!$n) break;
				if(empty($mycoin[$v])) return false;
				$getcoins[$id] = $coin;
				$secrets[$id] = Tools::makesecrets();
				$getcoins[$id]['secret'] = GsonCrypt::seal($secrets[$id],$owner);
				$getcoins[$id]['transactioncount'] += 1;
				unset($mycoin[$v][$id]);
				$n--;
			}
		}
		return $getcoins;
	}
}
?>