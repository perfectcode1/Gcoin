<?php
$mainoptions[1] = "Bank's Account Address";
$mainoptions[2] = "Check Balance";
$mainoptions[3] = "Payment Request";
$mainoptions[4] = "Grant Payments";
$mainoptions[5] = "Reports";
$mainoptions[6] = "Clear Payment Requests";
$mainoptions[7] = "Export Wallet Key";

function main($mainmsg){
	gio::output(config::$walletMessage);
	if(account::isCreated()) $serv = popen("service.ecsh",'r');
	$net = new Gnet;
	$inerr = 0;
	while(true){
		$inmsg = array();
		if(account::isCreated()){
			$inmsg["d"] = "Destroy Account";
			$dmsg = $mainmsg + $inmsg;
		}else{
			$inmsg["c"] = "Create Bank Wallet";
			$dmsg = $inmsg;
		}
		$dmsg["x"] = "Exit";
		if(!$inerr){
			gio::output();
			foreach($dmsg as $k=>$v){
				$k = strtoupper($k);
				gio::output("$k - $v");
			}
			gio::output();
			$msg = "Enter your choice and press enter";	
		}
		$inerr = 0;
		$c = gio::input("$msg");
		if(!array_key_exists($c,$dmsg)) $c = null;
		switch($c){
			case 1:
				$a = account::address();
				gio::output("Bank's account address is: $a");
				break;
			case 2:
				$n = account::coins(config::$accountId,$coins);
				gio::output("You have $n coins");
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
			case 3:
				$m = transaction::prequest();
				gio::output($m[1]);
				break;
			case 4:
				transaction::pgrant();
				break;
			case 5:
				$acc = gio::input("Enter the wallet number [EMPTY for bank's self]");
				$o = gio::input("Enter the order id [EMPTY for all]");
				$f = null;
				if(gio::confirm("Do you want to create a file on your desktop")){
					do{
						$f = gio::input("Enter the file name");
					}while(!$f);
				}
				transaction::reports($o,$acc,$f);
				break;
			case 6:
				$m = transaction::clearrequests(null,null);
				gio::output($m[1]);
				break;
			case 7:
				account::merckey(gio::input("Enter the name of the file to write to your desktop"));
				break;
			case "c":
				$serv = account::create();
				break;
			case "d":
				gio::output("This action will irreversibly destroy your wallet and its contents");
				if(gio::confirm("Are you sure you want to destroy your account")) account::destroy();
				break;
			case "x":
				@$net->send('shutdown');
				$net = null;
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
if(is_readable(config::$walCfgFile)){
	$cfg = unserialize(gio::readfile(config::$walCfgFile));
	config::$networkInterface = $cfg[0];
	config::$walletPort = $cfg[1];
	config::$remoteAddress = $cfg[2];
	config::$remotePort = $cfg[3];
}
main($mainoptions);
gio::output();
?>