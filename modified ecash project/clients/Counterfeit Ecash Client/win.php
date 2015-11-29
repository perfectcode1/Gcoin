<?php
$mainoptions[1] = "Display Wallet Address";
$mainoptions[2] = "Payment Request";
$mainoptions[3] = "Grant payment";
$mainoptions[4] = "Check Balance";
$mainoptions[5] = "Reports";
$mainoptions[6] = "Clear payment requests";
$mainoptions[7] = "Export Wallet Key";
$mainoptions[8] = "Mine eCash";

function main($mainmsg){
	gio::output(config::$walletMessage);
	$net = new Gnet;
	$inerr = 0;
	while(true){
		$inmsg = array();
		if(account::isCreated()){
			$inmsg["d"] = "Destroy Account";
			$dmsg = $mainmsg + $inmsg;
		}else{
			$inmsg["c"] = "Create Wallet Account [Merchant/Client]";
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
				gio::output("Your wallet address is: $a");
				break;
			case 2:
				$m = transaction::request();
				gio::output($m[1]);
				break;
			case 3:
				transaction::grant();
				break;
			case 4:
				$n = account::balance($coins);
				gio::output("Total value of eCash units: $n");
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
			case 5:
				$o = gio::input("Enter the order id [EMPTY for all]");
				$f = null;
				if(gio::confirm("Do you want to create a file on your desktop")){
					do{
						$f = gio::input("Enter the file name");
					}while(!$f);
				}
				transaction::reports($o,$f);
				break;
			case 6:
				transaction::clearallrequests();
				break;
			case 7:
				account::merckey(gio::input("Enter the name of the file to write to your desktop"));
				break;
			case 8:
				$maxallowed = 1000;
				$v = gio::input("What value of eCash do you wish to mine","integer");
				$n = gio::input("How many eCashes do you wish to mine","integer");
				$c = mine::countcoins($null);unset($null);
				if($n>$maxallowed||($c+$n)>$maxallowed){
					$rem = $maxallowed-$c;
					gio::output("You can not mine above $maxallowed eCashes!");
					gio::output("You can mine $rem more eCashes!");
				}else{
					$res = mine::ecash($n,$v);
					if($res){
						gio::output("You have successfully mined $n eCashes.");
						gio::output("Mining process took ".Tools::arrtostr($res,", "));
					}else{
						gio::output("Mining operation failed!");
					}
				}
				break;
			case "c":
				account::create();
				break;
			case "d":
				gio::output("This action will irreversibly destroy your wallet and its contents");
				if(gio::confirm("Are you sure you want to destroy your account")) account::destroy();
				break;
			case "x":
				$net = null;
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
	config::$bankAddress = $cfg[0];
	config::$bankPort = $cfg[1];
}
main($mainoptions);
gio::output();
?>