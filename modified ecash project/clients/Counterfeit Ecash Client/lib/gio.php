<?php
@date_default_timezone_set(config::$timezone);
error_reporting(0);
class gio {
	public static function input($message="",$type="",$length='255') {
		gio::output("$message: ");
		if(empty($type)){ 
			$stdin = fopen ("php://stdin","r"); 
			$ret = fgets($stdin,$length); 
		}else{
			switch($type){
				case "integer":
					fscanf(STDIN, "%d\n", $ret);
					break;
				case "float":
					fscanf(STDIN, "%f\n", $ret); 
					break;
				case "string":
					fscanf(STDIN, "%s\n", $ret); 
					break;
				default:
					gio::log("Invalid input type '$type'",E_USER_WARNING);
					$ret = false;
					break;
			}
		}
		return trim($ret);
	}
	
	public static function inputc($message=""){
		gio::output("$message: ");
		$stdin = fopen ("php://stdin","r");
		$ret = fgetc($stdin);
		return trim($ret);
	}
	public static function confirm($msg=""){
		$msg .= " ([y/n]?)";
		if(strtolower(self::inputc($msg))=="y"){
			return true;
		}
		return false;
	}
	public static function output($message=""){
		$stdout = fopen('php://stdout', 'w');
		fwrite($stdout,"\n\t$message");
	}
	public static function display($arr,&$return=false){
		if(is_string($arr)) return self::output($arr);
		while(list($k,$v) = each($arr)){
			if(is_array($v)){
				self::display($v,$return);
			}else{
				$sep = strlen($k)+1 < 16 ? "\t" : "";
				$sep .= strlen($k)+1 < 8 ? "\t" : "";
				$k = ucwords($k);
				if($return!==false){
					$return[] = "$k:$sep\t$v";
				}else{
					self::output("$k:$sep\t$v");
				}
			}
		}
	}
	public static function log($logmsg,$level=false,$file="",$line=0){
		if(!config::$loggingLevel) return;
		$level = $level===false?VERBOSE:$level;
		if($level > (config::$loggingLevel==1?E_USER_ERROR:(config::$loggingLevel==2?E_USER_WARNING:(config::$loggingLevel==3?E_USER_NOTICE:VERBOSE)))) return;
		switch($level):
			case E_USER_ERROR:
				$l = "  Error";
				break;
			case E_USER_WARNING:
				$l = "Warning";
				break;
			case E_USER_NOTICE:
				$l = " Notice";
				break;
			default:
				$l = "Verbose";
				break;
		endswitch;
		if($level < E_USER_NOTICE && $file):
			$logmsg .= " in $file on line $line.";
		endif;
		$logmsg = config::$displayLogLevel?"$l:\t$logmsg":"$logmsg";
		if(config::$fileLogging&&isset($GLOBALS['_debug_f'])&&$GLOBALS['_debug_f']===true) Tools::log("$logmsg");
		if(isset($GLOBALS['_debug'])&&$GLOBALS['_debug']===true) self::output($logmsg);
	}
	public static function temp($data,$maxmemorysize=2092152){
		if(!is_resource($data)):
			$temp = fopen('php://temp/maxmemory:$maxmemorysize','w+');
			fwrite($temp,$data);
			rewind($temp);
		else:
			$temp = fread($data, $maxmemorysize);
		endif;
		return $temp;
	}
	
	public static function savetofile($data, $file, $mode = ""){
		$file = config::$encryptLocalStorage?"$file.".(config::$encrypedLocalStorageExtention):"$file";
		gio::log("Writing file: $file ...", VERBOSE);
		$fp = @fopen("$file", 'wb');
		!config::$encryptLocalStorage?'':@stream_filter_append($fp, GsonCrypt::getLocalEncAlgo('mcrypt'), STREAM_FILTER_WRITE, GsonCrypt::getLocalEncKeys());
		$ret = @fwrite($fp, $data);
		@fclose($fp);
		if(!empty($mode)){
			@chmod("$file",$mode);
		}
		if($ret):gio::log("... Done writing file: $file", VERBOSE);else:gio::log("... Error writing file: $file ...", E_USER_WARNING);endif;
		return $ret;
	}
	
	public static function readfile($file){
		$file = config::$encryptLocalStorage?"$file.".(config::$encrypedLocalStorageExtention):"$file";
		gio::log("Reading file: $file ...", VERBOSE);
		$fp = @fopen("$file", 'rb');
		!config::$encryptLocalStorage?'':@stream_filter_append($fp, GsonCrypt::getLocalEncAlgo('mdecrypt'), STREAM_FILTER_READ, GsonCrypt::getLocalEncKeys());
		$data = rtrim(@stream_get_contents($fp));
		@fclose($fp);
		if($data):gio::log("... Done reading file: $file", VERBOSE);else:gio::log("... Error reading file: $file ...", E_USER_WARNING);endif;
		return $data;
	}
	
	public static function saverawfile($data,$file,$mode = ""){
		gio::log("Writing rawfile: $file ...", VERBOSE);
		$ret = @file_put_contents($file,$data);
		if(!$ret){
			gio::log("... Error writing rawfile: $file ...", E_USER_WARNING);
		}else{
			if(!empty($mode)){
				@chmod("$file",$mode);
			}
			gio::log("... Done writing rawfile: $file", VERBOSE);
		}
		return $ret;
	}
	
	public static function readrawfile($file){
		gio::log("Reading rawfile: $file ...", VERBOSE);
		$ret = @file_get_contents($file);
		if(!$ret){
			gio::log("... Error reading rawfile: $file ...", E_USER_WARNING);
		}else{
			gio::log("... Done reading rawfile: $file", VERBOSE);
		}
		return $ret;
	}
}
?>