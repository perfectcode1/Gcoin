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
		}
		return $ret;
	}
	
	public static function makesecrets($n=1){
		$tot = $n;
		$secrets = array();
		while($n){
			$seed = "";
			$c = 0;
			while($c<10){
				$seed .= chr(rand(65,90));
				$c++;
			}
			array_push($secrets,$seed);
			$n--;
		}
		if($tot==1) return $secrets[0];
		return $secrets;
	}
}
?>