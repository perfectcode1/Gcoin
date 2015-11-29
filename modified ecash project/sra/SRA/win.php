<?php
$mainoptions[1] = "Transaction Report";
$mainoptions[2] = "Mine eCash";
$mainoptions[3] = "Count Total eCash";
$mainoptions[4] = "Give eCash";
$mainoptions["x"] = "Exit";

function main($mainmsg){
	gio::output(config::$walletMessage);
	$serv = popen("service.ecsh",'r');
	$net = new Gnet;
	$inerr = 0;
	while(true){
		if(!$inerr){
			gio::output();
			foreach($mainmsg as $k=>$v) gio::output("$k - $v");
			gio::output();
			$msg = "Enter your choice and press enter";	
		}
		$inerr = 0;
		$c = gio::input("$msg");
		switch($c){
			case 1:
				$tid = gio::input("Enter the transaction Id");
				$st = transaction::status($tid);
				if(!$st){
					gio::output("Transaction reference '$tid' not found");
				}else{
					$rpt['order id'] = $st['order id'];
					$rpt['order amount'] = $st['amount'];
					$rpt['from'] = $st['client'];
					$rpt['to'] = $st['merchant'];
					$rpt['transaction time'] = date(config::$timeFormat,$st['acknowledgement']['completed']);
					$rpt['description'] = $st['description'];
					$rpt['transaction reference'] = $st['acknowledgement']['id'];
					$rpt['response code'] = $st['acknowledgement']['status'];
					$rpt['response message'] = $st['acknowledgement']['message'];
					$rpt['ecash value tendered'] = $st['acknowledgement']['amount'];
					$rpt['ecash tendered'] = $st['acknowledgement']['ecashids'];
					gio::display($rpt);
					if(gio::confirm("Do you want to save the report to a file")){
						$dest = gio::input("Enter full path to the file");
						gio::display($rpt,$x);
						if(!gio::saverawfile(join("\r\n",$x),$dest)){
							gio::output("Could not save the report to $dest");
						}else{
							gio::output("Report successfully saved to $dest");
						}
						unset($x);
					}
				}
				break;
			case 2:
				$maxallowed = 1000;
				$v = gio::input("What value of eCash do you wish to mine","integer");
				$n = gio::input("How many eCashes do you wish to mine","integer");
				$c = mine::countcoins($null);unset($null);
				if($n>$maxallowed||($c+$n)>$maxallowed){
					$rem = $maxallowed-$c;
					gio::output("You can not mine above $maxallowed eCashes!");
					gio::output("You can mine $rem more eCashes!");
				}else{
					$res = mine::coins($n,$v);
					if($res){
						gio::output("You have successfully mined $n eCashes.");
						gio::output("Mining process took ".Tools::arrtostr($res,", "));
					}else{
						gio::output("Mining operation failed!");
					}
				}
				break;
			case 3:
				$n = mine::countcoins($coins);
				gio::output("A total of $n eCash value is available");
				if($n&&gio::confirm("Do you wish to see their Id's")){
					foreach($coins as $val=>$ccs){
						foreach($ccs as $i=>$coin){
							gio::output("Ecash ID: $i");
							foreach($coin as $id=>$c){
								if($id=="token") $c = md5($c);
								if($id=="hash") $c = sha1($c);
								if($id=="mined") $c = date(config::$timeFormat,$c);
								gio::output("$id=>$c");
							}
							gio::output();
						}
					}
				}
				break;
			case 4:
				$to = gio::input("Enter the wallet address");
				$amt = gio::input("Enter the amount to give");
				$res = account::give($to,$amt);
				break;
			case "x":
				$net->connect(config::$serviceAddress,intval(config::$servicePort));
				$net->send("shutdown");
				if($serv) $ret = pclose($serv);
				break 2;
			default:
				$inerr = 1;
				$msg = "Retry";
		}
		if(!$inerr) gio::output("\n\n\n");
	}
	if(isset($ret)&&$ret!=0) gio::output("An error occured while exiting...");
	gio::output(config::$exitMessage);
	sleep(3);
}

main($mainoptions);
gio::output();
exit;
?>