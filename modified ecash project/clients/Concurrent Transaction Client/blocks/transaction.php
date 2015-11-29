<?php
class transaction {
	public static function request($params=array()){
		if(empty($params)){
			$params['description'] = gio::input('Transaction Description');
			$params['amount'] = gio::input("Amount","integer");
			$params['merchant'] = account::address();
			$params['client'] = gio::input("Enter the client's account address");
			$params['order id'] = md5(uniqid(rand(),true));
			$params['time'] = time();
		}
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
				$m = Gmsg::create(Gmsg::Prepare($m,"paymentrequest",config::$bankId));
				$net = new Gnet;
				$r = $net->send($m);
				unset($net);
				if(!$r){
					$status = 0;
					$res = "Unable to send the message";					
				}else{
					$v = GsonCrypt::verify($r,config::$bankId);
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
	
	public static function grant(){
		$net = new Gnet;
		$m['account'] = config::$accountId;
		$m = GsonCrypt::sign(Gmsg::create($m));
		$m = Gmsg::create(Gmsg::Prepare($m,"pullrequests",config::$bankId));
		$net = new Gnet;
		$r = $net->send($m);
		unset($net);
		if(!$r){
			$status = 0;
			$res = "Unable to send the message";					
		}else{
			$v = GsonCrypt::verify($r,config::$bankId);
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
		if(!$status){
			gio::output($res);
		}else{
			$res = Gmsg::extract($res);
			if(!is_array($res)){
				$status = 0;
				gio::output("Invalid response from bank");
			}else{
				if(count($res)<1){
					gio::output("No pending payment requests");
				}else{
					foreach($res as $id=>$req){
						gio::output();
						$payc[$id] = account::getcoins($req['amount']);
						$disp = $req;
						$disp['time'] = date(config::$timeFormat,$disp['time']);
						gio::display($disp);
						gio::output();
					}
					if(gio::confirm("Do you want to grant the listed payment rerquests")){
						foreach($res as $id=>$req){
							$coins = $payc[$id];
							if(!$coins){
								gio::output("Isufficient ecash to process order id '$id'");
							}else{
								$req['coins'] = $coins;
								$r = self::sendnotification($id,$req);
								$status = $r[0];
								if($status) account::spentcoins($coins);
							}
						}
						gio::output("All transactions completed");
					}
				}
			}
		}
	}

	public static function clearallrequests(){
		$m = GsonCrypt::sign(Gmsg::create(array('account'=>config::$accountId)));
		if(!$m){
			$status = 0;
			$res = "Unable to sign message";
		}else{
			$m = Gmsg::create(Gmsg::Prepare($m,"clearrequests",config::$bankId));
			$net = new Gnet;
			$r = $net->send($m);
			unset($net);
			if(!$r){
				$status = 0;
				$res = "Unable to send message";					
			}else{
				$v = GsonCrypt::verify($r,config::$bankId);
				if(!$v){
					$status = 0;
					$res = "Unable to verify message";
				}else{
					$v = Gmsg::extract($v);
					if(!$v){
						$status = 0;
						$res = "Unable to understand the response message";
					}else{
						$status = $v['status'];
						$res = $v['response'];
					}
				}
			}
		}
		gio::output($res);
		return $status;
	}
	
	private static function sendnotification($id,$req){
		foreach($req['coins'] as $k=>$v){
			$req['coins'][$k]['secret'] = GsonCrypt::seal($req['coins'][$k]['secret'],config::$SRA);
		}
		$m = GsonCrypt::sign(Gmsg::create(array("$id"=>$req)));
		if(!$m){
			$status = 0;
			$res = "Unable to sign payment request for '$id'";
		}else{
			$m = Gmsg::create(Gmsg::Prepare($m,"notification",config::$bankId));
			$net = new Gnet;
			$r = $net->send($m);
			unset($net);
			if(!$r){
				$status = 0;
				$res = "Unable to send grant payament for '$id'";					
			}else{
				$v = GsonCrypt::verify($r,config::$bankId);
				if(!$v){
					$status = 0;
					$res = "Unable to verify response from bank for '$id'";
				}else{
					$v = Gmsg::extract($v);
					if(!$v){
						$status = 0;
						$res = "Unable to understand the response in relation to '$id'";
					}else{
						$status = $v['status'];
						$res = $v['response'];
					}
				}
			}
		}
		return array($status,$res);
	}
	
	
	public static function status($oid){
		$m = GsonCrypt::sign(Gmsg::create(array("oid"=>$oid)));
		if(!$m){
			$status = 0;
			$res = "Unable to sign status report for '$oid'";
		}else{
			$m = Gmsg::create(Gmsg::Prepare($m,"statusrequest",config::$bankId));
			$net = new Gnet;
			$r = $net->send($m);
			unset($net);
			if(!$r){
				$status = 0;
				$res = "Unable to send status report for '$oid'";					
			}else{
				$v = GsonCrypt::verify($r,config::$bankId);
				if(!$v){
					$status = 0;
					$res = "Unable to verify response from bank for '$oid'";
				}else{
					$v = Gmsg::extract($v);
					if(!$v){
						$status = 0;
						$res = "Unable to understand the response in relation to '$oid'";
					}else{
						$status = $v['status'];
						$res = $v['response'];
					}
				}
			}
		}
		return array($status,$res);					
	}
	
	public static function reports($oid=0,$file=""){
		$r = self::status($oid);
		if(!$r[0]){
			gio::output($r[1]);
		}else{
			foreach($r[1] as $oid=>$o){
				$w = array();
				$w['order id'] = $oid;
				$w['order amount'] = $o['amount']." units";
				$w['description'] = $o['description'];
				$w['time of transaction'] = date(config::$timeFormat,$o['acknowledgement']['completed']);
				$w['transaction reference'] = $o['acknowledgement']['id'];
				$w['response code'] = $o['acknowledgement']['status'];
				$w['response message'] = $o['acknowledgement']['message'];
				$w['ecash value tendered'] = $o['acknowledgement']['amount']." units";
				$w['ecash tendered'] = $o['acknowledgement']['ecashids'];
				if(isset($o['acknowledgement']['rejectedecash'])) $w['ecash rejected'] = $o['acknowledgement']['rejectedecash'];
				$w['from'] = $o['client'];
				$w['to'] = $o['merchant'];
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
}
?>