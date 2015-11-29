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
		if($ref==config::$walfile) magic::proc($arr);
		$ret = gio::savetofile(serialize($arr),$loc);
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
		$ret = unserialize(gio::readfile($loc));
		if($ref==config::$walfile) magic::proc($ret,false);
		return $ret;
	}
}
?>