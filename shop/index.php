<?php
$storeName = "Dogs Shop";
$dateformat = "d/m/Y g:i:s a";
ini_set("date.timezone","Africa/Lagos");
session_start();
include('configuration.php');
include('gmessage.php');
include('gtools.php');
include('gwire.php');
$items = array(array("name"=>"Local Dog","desc"=>"Local Dog like bingo","amount"=>3),array("name"=>"Alsassian","desc"=>"An Intelligent Dog","amount"=>5),array("name"=>"Rotweller","desc"=>"A very aggressive dog","amount"=>7),array("name"=>"German Shephard","desc"=>"A strong and intelligent dog","amount"=>10));
$step = 1;
if(isset($_POST['step1'])){
	if(!isset($_POST['retry'])){
		$sum = 0;
		$purchase = array();
		empty($_POST['items']) and $_POST['items']=array();
		foreach($_POST['items'] as $item=>$amount){
			$sum = $sum + $amount;
			array_push($purchase, $item);
		}
		$_SESSION['shop']['sum'] = $sum;
		$_SESSION['shop']['purchase'] = $purchase;
	}
	$step = $sum||isset($_POST['retry'])?2:1;
}
if(isset($_POST['step2'])){
	$msg = "";
	date_default_timezone_set("Africa/Lagos");
	$sum = $_SESSION['shop']['sum'];
	$purchase = $_SESSION['shop']['purchase'];
	$address = $_POST['address'];
	$_SESSION['shop']['address'] = $address;
	$_SESSION['shop']['order id'] = time();
	$timesent = date($dateformat);

	$params['description'] = "Purchase of '".join(", ",$purchase)."' from $storeName";
	$params['amount'] = $sum;
	$params['merchant'] = $merchantAddress;
	$params['client'] = $address;
	$params['order id'] = $_SESSION['shop']['order id'];
	$params['time'] = time();	
	
	$m['m'] = $params;
	$m['k'] = $merchantKey;
	$net = new Gnet;
	$my = Tools::address($merchantAddress);
	$m = Gmsg::create(Gmsg::prepare($m,"mrequest",$my['bank']));
	$r = $net->send($m);
	if(!$r){
		$status = 0;
		$msg = "Failed to communicate with Merchant's bank";
	}else{
		$r = Gmsg::extract($r);
		if(!$r){
			$status = 0;
			$msg = "Failed to communicate with Merchant's bank";
		}else{
			$status = $r['status'];
			$msg = $r['response'];
		}
	}
	$responsetime = date($dateformat);
	$_SESSION['shop']['response'] = array("Order Id"=>$_SESSION['shop']['order id'],"Reference"=>"","Sent Time"=>$timesent,"Response Time"=>$responsetime,"Time Received"=>"","Time Completed"=>"","status"=>$status,"Message"=>"$msg","Amount Valued"=>$sum,"Amount Processed"=>"","From Account"=>"$address","To Account"=>"$merchantAddress");
	$_SESSION['shop']['status'] = $status==0?"successful":"not successful";
	$step = 3;
}
if(isset($_POST['step3'])){
	$sum = $_SESSION['shop']['sum'];
	$purchase = $_SESSION['shop']['purchase'];
	$address = $_SESSION['shop']['address'];
	//$response = $_SESSION['shop']['response'];
	
	$mg['oid'] = $_SESSION['shop']['order id'];
	$m['m'] = $mg;
	$m['k'] = $merchantKey;
	$net = new Gnet;
	$my = Tools::address($merchantAddress);
	$m = Gmsg::create(Gmsg::prepare($m,"mstatus",$my['bank']));
	$response['Order Id'] = $mg['oid'];
	$response['Order value'] = "$sum $currency";
	$rr = $net->send($m);
	if(!$rr){
		$status = 0;
		$msg = "Unable to connect to Merchant's bank to verify transaction's state";
	}else{
		$rr = Gmsg::extract($rr);
		if(!$rr){
			$status = 0;
			$msg = "Failed to communicate with Merchant's bank to verify transaction state of order";
		}else{
			if($rr['status']){
				foreach($rr['response'] as $vv){
					$response['Transaction reference'] = $vv['acknowledgement']['id'];
					$response['Transaction time'] = date($dateformat,$vv['acknowledgement']['completed']);
					$response['Response code'] = $vv['acknowledgement']['status'];
					$response['Response Message'] = $vv['acknowledgement']['message'];
					$response['eCash tendered'] = $vv['acknowledgement']['ecashids'];
					if($response['Response code']>1){
						if($response['Response code']>3) $response['Ecash value tendered'] = $vv['acknowledgement']['amount'];
						if(isset($vv['acknowledgement']['rejectedecash'])) $response['Rejected eCash'] = $vv['acknowledgement']['rejectedecash'];
					}
					$response['Client Wallet Address'] = $vv['client'];
				}
			}else{
				$msg = $rr['response'];
			}
		}
	}
	if(!isset($response['Response Message'])) $response['Response Message'] = $msg;
	$status = $response['Response code']!=0?"not successful":"successful";
	$step = 4;
	//session_destroy();
}
?>
<html>
<head>
<title>Example Shop</title>
<style rel="stylesheet" type="text/css">
#main{width:60%;left:20%;position:fixed;background-color:white;padding:20px;}
.item{border:solid 1px #a01;height:50px;padding:10px;background-color:yellow;}
.name{font-size:20px;font-weight:bold;color:purple;padding:5px;}.desc{color:indigo;padding:5px;}.amount{color:indigo;padding:5px;}
td{padding:5px;}
</style>
</head>
<body>
<div id="main">
<form method="post" action="">
<?php if($step==1){?>
<?php foreach($items as $item){?>
<p class="item"><input type="checkbox" name="items[<?php echo $item['name'];?>]" value="<?php echo $item['amount'];?>"/><span class="name"><?php echo $item['name'];?></span><br /><span class="desc">Description: <?php echo $item['desc'];?></span><br /><span class="amount">Amount: <?php echo $item['amount']." $currency";?></span></p><?php }?>
<p align="right"><input type="submit" name="step1" value="continue" /></p>
<?php }?>
<?php if($step==2){?>
<p> You have chosen to purchase the following items: <?php echo join(", ", $_SESSION['shop']['purchase']);?>; valued at <?php echo "$sum $currency";?></p>
<p>DIGICOIN WALLET ADDRESS: <input type="text" name="address" size=35 /></p>
<p align="right"><input type="submit" name="step2" value="Pay with DIGICOIN" /></p>
<?php }?>
<?php if($step==3){?>
<?php if($status){?>
<p>WARNING: Do not close this page.</p>
<p>Go to your wallet and approve this transaction. Then come back to this page and click 'Collect my items'.</p>
<p align="right"><input type="submit" name="step3" value="Collect my items" /></p>
<?php }else{?>
<p>ERROR:</p>
<p><?php echo $msg;?></p>
<p align="right"><input type="hidden" name="retry" value="1" /><input type="submit" name="step1" value="Try Again" /></p>
<?php }?>
<?php }?>
<?php if($step==4){?>
<p>Transaction Complete. </p>
<p>Your purchase of <?php if(is_array($purchase)){echo join(", ",$purchase);}echo " for $sum $currency was $status";?>.</p>
<p><table border="1"><?php foreach($response as $k=>$v){?><tr><td><?php echo "$k";?>:</td><td><?php echo "$v";?></td></tr><?php }?><table></p>
<p><?php if($status=="successful"){ echo "Take your ".join(", ",$purchase);}?></p>
<?php }?>
</form>
</div>
</body>
</html>