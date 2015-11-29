<?php
class filestorage {
	public function dosave($arr,$ref=null){
		$ref = $ref?$ref:config::$walfile;
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
	
	public function doload($ref=""){
		$ref = $ref?$ref:config::$walfile;
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

// TO BE COMPLETED AT REAL LIVE IMPLEMENTATION
class dbstorage {
	public function dosave($arr,$ref=null){
		$ref = $ref?$ref:"wal";
		require_once(Tools::makepath("",""));
		$qry = query::make("$ref",$arr,"delete");
		$qry2 = query::make("$ref",$arr,"insert");
		switch($ref){
			case "wal":
				magic::proc($arr);
				// Dbqry
				break;
			default:
				// Dbqry
		}
		return false;
	}
	
	public function doload($ref){
		$qry = query::make("$ref",$arr,"select");
		switch($ref){
			case "wal":
				// Dbqry
				magic::proc($ret,false);
				break;
			default:
				// Dbqry
		}
		return false;
	}
}
?>