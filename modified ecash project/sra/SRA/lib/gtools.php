<?php
class Tools{
	public static function makepath($arr){
		$l = count($arr);
		$path = "";
		for($i=1;$i<=$l;$i++){
			$path.=$arr[$i-1];
			if($i!=$l) $path.=DS;
		}
		gio::log("Created file path: $path", VERBOSE);
		return $path;
	}
	public static function log($m){
		@fwrite(fopen(config::$logFile,'a'),date(config::$timeFormat)."\t$m\r\n");
	}
	
	public static function arrtostr($arr,$sep=",",$vquote="",$kquote=""){
		$ret = "";
		foreach($arr as $k=>$v){
			$ret .= "$kquote$k$kquote=$vquote$v$vquote$sep";
		}
		$ret = substr($ret,0,(-1*strlen($sep)));
		return $ret;
	}

	public static function arrvtostr($arr,$sep=",",$vquote=""){
		$ret = "";
		foreach($arr as $k=>$v){
			$ret .= "$vquote$v$vquote$sep";
		}
		$ret = substr($ret,0,(-1*strlen($sep)));
		return $ret;
	}
	
	public static function arrktostr($arr,$sep=",",$kquote=""){
		$ret = "";
		foreach($arr as $k=>$v){
			$ret .= "$kquote$k$kquote$sep";
		}
		$ret = substr($ret,0,(-1*strlen($sep)));
		return $ret;
	}
	
	public static function address($address){
		if(!$address) return false;
		$p = explode(":",$address);
		if(count($p)<2) return false;
		$ret['address'] = $p[0];
		$ret['port'] = $p[1];
		if(isset($p[2])){
			$pe = explode("_",$p[2]);
			if(isset($pe[1])){
				$ret['bank'] = $pe[0];
				$ret['account'] = $pe[1];
			}else{
				$ret['account'] = $pe[0];
				$ret['bank'] = $pe[0];
			}
			$ret['srakey'] = !isset($pe)?"":(count($pe)==1?$ret['account']:$ret['bank']."_".$ret['account']);
		}
		return $ret;
	}
	
	public static function makesecrets($l=10){
		$seed = "";
		for($c=0;$c<$l;$c++){
			$seed .= chr(rand(65,90));
		}
		return $seed;
	}
	
	public static function configsvc(){
		$cfg[0] = gio::input("Enter the interface to run on [127.0.0.1]");
		if(empty($cfg[0])) $cfg[0] = "127.0.0.1";
		$cfg[1] = gio::input("Enter the port to run on [11000]");
		if(empty($cfg[1])) $cfg[1] = 11000;
		gio::savetofile(serialize($cfg),config::$svcCfgFile);
	}
}
?>