<?php
class storage {	
	public static function save($arr,$ref=null){
		$ref = $ref?$ref:config::$walfile;
		/*$s = __class__;
		$engine = config::$storageengine.$s;
		$s = new $s;
		aggregate($s,$engine);
		$ret = $s->dosave($arr,$ref);*/
		
		$loc = Tools::makepath(array("wal","$ref"));
		switch($ref){
			case "wal":
				magic::proc($arr);
				$ret = gio::savetofile(serialize($arr),$loc);
				break;
			default:
				$ret = gio::savetofile(serialize($arr),$loc);
		}
		return $ret;
	}
	
	public static function load($ref=null){
		$ref = $ref?$ref:config::$walfile;
		/*$s = __class__;
		$engine = config::$storageengine.$s;
		$s = new $s;
		aggregate($s,$engine);
		$ret = $s->doload($ref);*/
		
		$loc = Tools::makepath(array("wal","$ref"));
		switch($ref){
			case "wal":
				$ret = unserialize(gio::readfile(Tools::makepath(array("wal","$ref"))));
				magic::proc($ret,false);
				break;
			default:
				$ret = unserialize(gio::readfile($loc));
		}
		return $ret;
	}
}
?>