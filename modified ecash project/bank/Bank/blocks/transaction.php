<?php
class transaction {
	public static function request($msg,$account,$merc = false){
		$status = 0;
		if(!$merc){
			$m = GsonCrypt::verify($msg,$account);
			if(!$m) return array(0,"Bank did not verify your message as authentic");
		}else{
			$v = GsonCrypt::verify($merc,$account);
			if(!$v) return array(0,"Unable to verify the account key");
			if($v!=md5(gio::readfile(GsonCrypt::getkey($account)))) return array(0,"Incorrect Key");
			$m = $msg;
		}
		$m = Gmsg::extract($m);
		if(!$m) return array(0,"Bank did not extract your message correctly");
		$dest = Tools::address($m['client']);
		if(!$dest) return array(0,"Bank did not understand the merchant address");
		if(!self::certexchange($m['client'])) return array(0,"Bank can not communicate with the destination bank");
		if($dest['bank']!=config::$accountId){
			$pm = Gmsg::create(Gmsg::prepare(GsonCrypt::sign(Gmsg::create($m)),'paymentrequest',$dest['bank']));
			if(!$pm) return array(0,"Bank internal processing error!");
			$net = new Gnet;
			$net->connect($dest['address'],intval($dest['port']));
			$r = $net->send($pm);
			if(!$r) return array(0,"Invalid response from merchant");
			$vm = Gmsg::extract(GsonCrypt::verify($r,$dest['bank']));
			if(!$vm) return array(0,"Invalid response from merchant");
			return array($vm['status'],$vm['response']);
		}else{
			if(!account::exists($dest['account'])&&$dest['account']!=config::$accountId) return array(0,"The wallet address is not valid");
			$oid = $m["order id"];
			$acc = $dest['account'];
			$s = storage::load("$acc.request");
			$s = $s?$s:array();
			$s[$oid] = $m;
			$r = storage::save($s,"$acc.request");
			$res = $r ? "Transaction request was successful" : "Transaction request failed";
			$r = $r ? 1 : 0;
			return array($r,$res);
		}
	}

	public static function pullrequests($msg,$account){
		$m = GsonCrypt::verify($msg,$account);
		if(!$m) return array(0,"Bank did not verify your message as authentic");
		$m = Gmsg::extract($m);
		if(!$m) return array(0,"Bank did not extract your message correctly");
		if($m['account']==$account){
			$s = storage::load("$account.request");
			if(!$s) $s = array();
			$m = Gmsg::create($s);
			return array(1,$m);
		}
		return array(0,"Failed!");
	}
	
	public static function clearrequests($msg,$account){
		if(empty($account)) $account = config::$accountId;
		if($account!=config::$accountId){
			$m = GsonCrypt::verify($msg,$account);
			if(!$m) return array(0,"Bank did not verify your message as authentic");
			$m = Gmsg::extract($m);
			if(!$m) return array(0,"Bank did not extract your message correctly");
		}else{
			if(!account::exists($account)&&$account!=config::$accountId) return array(0,"Invalid wallet account");
			$m = $msg;
			if(is_string($m)) $m['request'] = $m;
			if(!isset($m['account'])||empty($m['account'])) $m['account'] =& $account;
		}
		if($m['account']==$account){
			$s = self::clearrequest($m['request'],$account);
			$res = $s ? "Successfully cleared" : "Error while clearing";
			$s = $s ? 1 : 0;
			return array($s,$res);
		}
		return array(0,"Failed!");
	}
	
	public static function notification($msg,$account){
		if($account!=config::$accountId){
			$m = GsonCrypt::verify($msg,$account);
			if(!$m) return array(0,"Bank did not verify your message as authentic");
			$m = Gmsg::extract($m);
			if(!$m||!is_array($m)) return array(0,"Bank did not extract your message correctly");
		}else{
			$m = $msg;
		}
		$tr = $m;
		$pm = Gmsg::create(Gmsg::prepare(GsonCrypt::sign(Gmsg::create($m)),'notification',config::$SRA));
		if(!$pm) return array(0,"Error while preparing message to SRA");
		$net = new Gnet;
		$r = $net->send($pm, true);
		if(!$r) return array(0,"Invalid response from SRA");
		$r = GsonCrypt::verify($r,config::$SRA);
		if(!$r) return array(0,"Error verifying response from SRA");
		$mp = Gmsg::extract($r);
		if(!$mp) return array(0,"Error extracting response from SRA");
		if($mp['status']){
			if(isset($mp['response']['coins'])){
				$md = Tools::address($mp['response']['merchant']);
				account::deposit($mp['response']['coins'],$md['account']);
			}
			if(isset($mp['response']['change'])){
				account::deposit($mp['response']['change'],$account);
			}
			foreach($tr as $o){
				account::spentcoins($o['coins'],$account);
				self::clearrequest($o['order id'],$account);
				$oid = $o['order id'];
			}
			$tr[$oid]['acknowledgement'] = isset($mp['response']['acknowledgement'])?$mp['response']['acknowledgement']:$mp['response'];
			self::log($tr);
		}else{
			if(isset($mp['response']['acknowledgement'])) self::log($mp['response']);
		}
		$mp['response'] = isset($mp['response']['acknowledgement'])?$mp['response']['acknowledgement']['message']:(isset($mp['response']['message'])?$mp['response']['message']:$mp['response']);
		return array($mp["status"],$mp['response']);
	}
	
	public static function acknowledgement($msg,$account){
		$m = GsonCrypt::verify($msg,$account);
		if(!$m) return array(0,"Acknowledgement message to destination bank is not authentic");
		$m = Gmsg::extract($m);
		if(!$m||!is_array($m)) return array(0,"Destination bank did not extract the acknowledgement message correctly");
		$acc = Tools::address($m['merchant']);
		if($m['acknowledgement']['status']==0){
			$r = account::deposit($m['coins'],$acc['account']);
			$msg = $r ? "Payment was successful" : "Deposit to merchant's account failed";
			$m['acknowledgement']['message'] = $msg;
			$status = $r ? 1: 0;
		}else{
			$status = 0;
			$msg = $m['acknowledgement']['message'];
		}
		self::log($m);
		return array($status,"$msg");
	}
	
	private static function clearrequest($request,$account){
		$r = storage::load("$account.request");
		if(!$r||empty($request)) $r = array();
		if(array_key_exists($request,$r)){
			unset($r[$request]);
		}
		$s = storage::save($r,"$account.request");
		return $s;
	}
	
	
	
	public static function prequest($params=array()){
		if(empty($params)){
			$params['description'] = gio::input('Transaction Description');
			$params['amount'] = gio::input("Amount","integer");
			$params['merchant'] = account::address();
			$params['client'] = gio::input("Enter the client's account address");
			$params['order id'] = md5(uniqid(rand(),true));
			$params['time'] = time();
		}
		if(!self::certexchange($params['client'])) return array(0,"Can not communicate with the destination bank");
		$dest = Tools::address($params['client']);
		if(!$dest){
			$status = 0;
			$res = "The client address is invalid";
		}else{
			$m = GsonCrypt::sign(Gmsg::create($params));
			if(!$m){
				$status = 0;
				$res = "Unable to sign message to ".$params['client'];
			}else{
				$m = Gmsg::create(Gmsg::Prepare($m,"paymentrequest",$dest['bank']));
				$net = new Gnet;
				$r = $net->connect($dest['address'],intval($dest['port']));
				if(!$r) $cerr = 1;
				if($r) $r = $net->send($m);
				unset($net);
				if(!$r){
					$status = 0;
					$res = isset($cerr)?"Unable to connect to the merchank's bank":"Unable to send the message";
				}else{
					$v = GsonCrypt::verify($r,$dest['bank']);
					if(!$v){
						$status = 0;
						$res = "Unable to verify response from bank";
					}else{
						$v = Gmsg::extract($v);
						if(!$v){
							$status = 0;
							$res = "Unable to understand the response";
						}else{
							$status = $v['status'];
							$res = $v['response'];
						}
					}
				}
			}
		}
		return array($status,$res);
	}
	
	public static function pgrant(){
		$acc = config::$accountId;
		$res = storage::load("$acc.request");
		if(!$res||count($res)<1){
			gio::output("No pending payment requests");
		}else{
			foreach($res as $id=>$req){
				gio::output();
				$disp = $req;
				$disp['time'] = date(config::$timeFormat,$disp['time']);
				gio::display($disp);
				gio::output();
				if(gio::confirm("Do you want to grant this payment")){
					while(true){
						$amount=gio::input("Enter amount to confirm or c to cancel");
						if($amount=='c'||$amount=='C') break;
						if($req['amount']==$amount){
							while(true){
								$coins = account::getmycoins($amount);
								if(!$coins){
									gio::output("Isufficient balance");
									break;
								}
								$req['coins'] = $coins;
								foreach($req['coins'] as $k=>$v){
									$req['coins'][$k]['secret'] = GsonCrypt::seal($req['coins'][$k]['secret'],config::$SRA);
								}
								$r = self::notification(array("$id"=>$req),config::$accountId);
								$status = $r[0];
								gio::output($r[1]);
								if($status) account::spentcoins($coins,config::$accountId);
								if($status||(!$status&&!gio::confirm("Do you want to try again"))) break;
							}
							gio::output();
							break;
						}
					}
				}
			}
		}
	}

	public static function reports($oid=0,$account=null,$file=""){
		if(empty($account)) $account = config::$accountId;
		$r = self::status(array("oid"=>$oid),$account,true);
		if(!$r[0]){
			gio::output($r[1]);
		}else{
			foreach($r[1] as $oid=>$o){
				$w['order id'] = $oid;
				$w['amount requested'] = $o['amount'];
				$w['description'] = $o['description'];
				$w['from'] = $o['client'];
				$w['to'] = $o['merchant'];
				$w['amount tendered'] = $o['acknowledgement']['amount'];
				$w['transaction time'] = date(config::$timeFormat,$o['acknowledgement']['completed']);
				$w['transaction reference'] = $o['acknowledgement']['id'];
				$w['response code'] = $o['acknowledgement']['status'];
				$w['response message'] = $o['acknowledgement']['message'];
				$w['ecash tendered'] = $o['acknowledgement']['ecashids'];
				if(isset($o['acknowledgement']['rejectedecash'])) $w['rejected ecash'] = $o['acknowledgement']['rejectedecash'];
				if($file){
					gio::display($w,$wr[]);
				}else{
					gio::output();
					gio::display($w);
					gio::output();		
				}
			}
			if($file){
				foreach($wr as $k=>$v){
					$wr[$k] = join("\r\n",$wr[$k]);
				}
				$file .= ".txt";
				$ofile = Tools::makepath(array(getenv('USERPROFILE'),"Desktop","$file"));
				if(gio::saverawfile(join("\r\n\r\n\r\n",$wr),$ofile)){
					gio::output("The file $file was sucessfully written to your desktop");
				}else{
					gio::output("Error while writing $file to your desktop");
				}
			}
		}
	}
	
	public static function status($oid,$account,$loc=false){
		if($account!=config::$accountId&&!$loc){
			$m = GsonCrypt::verify($oid,$account);
			if(!$m) return array(0,"Bank did not verify your message as authentic");
			$m = Gmsg::extract($m);
			if(!$m||!is_array($m)) return array(0,"Bank did not extract your message correctly");
		}else{
			if(!account::exists($account)&&$account!=config::$accountId) return array(0,"Invalid wallet number");
			$m = $oid;
		}
		$m = $m['oid'];
		$t = storage::load("$account.transaction");
		if(!$t) return array(0,"No transaction found");
		if($m) $at[$m] = $t[$m];
		if($m&&!isset($t[$m])) return array(0,"The requested transaction with order id '$m' was not found");
		if($m&&isset($at[$m])) return array(1,$at);
		return array(1,$t);
	}
	
	public static function log(&$in){
		list($oid,$t) = each($in);
		if(!is_array($t)||!isset($t['order id'])){
			$t = $in;
			$oid = $t['order id'];
		}
		if(isset($t['coins'])) unset($t['coins']);
		if(isset($t['change'])) unset($t['change']);
		$acc = Tools::address($t['client']);
		$acm = Tools::address($t['merchant']);
		$ac = $acc['account'];
		$am = $acm['account'];
		$tr[$oid] = $t;
		if(account::exists($ac)||$ac==config::$accountId){
			$oldc = storage::load("$ac.transaction");
			if(!$oldc) $oldc = array();
			$oldc[$oid] = $tr[$oid];
			storage::save($oldc,"$ac.transaction");
		}
		if(account::exists($am)||$am==config::$accountId){
			$oldm = storage::load("$am.transaction");		
			if(!$oldm) $oldm = array();		
			$oldm[$oid] = $tr[$oid];		
			storage::save($oldm,"$am.transaction");
		}
	}
	
	public static function mercorder($oid,$account,$merc){
			$v = GsonCrypt::verify($merc,$account);
			if(!$v) return array(0,"Unable to verify the account key");
			if($v!=md5(gio::readfile(GsonCrypt::getkey($account)))) return array(0,"Incorrect Key");
			$r = self::status($oid,$account,true);	
			return $r;
	}
	
	public static function certexchange($addr){
		$d = Tools::address($addr);
		if(file_exists(GsonCrypt::getcert($d['bank']))) return true;
		$c = GsonCrypt::getcert(null,true);
		$m = Gmsg::create(Gmsg::prepare($c,'exchangecert',$d['bank']));
		$net = new Gnet;
		$r = $net->connect($d['address'],intval($d['port']));
		if(!$r) return false;
		$r = $net->send($m);
		if(!$r) return false;
		$r = Gmsg::extract($r);
		if(!$r) return false;
		if($r['status']) $resp = gio::saverawfile($r['cert'],GsonCrypt::getcert($d['bank']));
		return isset($resp)?$resp:false;
	}
}
?>