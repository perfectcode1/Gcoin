<?php
class query {
	public static function make($table,$value,$type='insert',$conditions=""){
		magic::proc($value);
		switch(strtolower($type)){
			case "insert":
				$keys = "";
				$vals = "";
				foreach($value as $key=>$val){
					if(!isset($donekey)) $keys .= "(";
					$vals .= "(";
					foreach($val as $k=>$v){
						if(!isset($donekey)) $keys .= "`$k`,";
						$vals .= "'$v',";
					}
					if(!isset($donekey)){
						$keys = substr($keys,0,-1);
						$keys .= ") ";
						$donekey = true;
					}
					$vals = substr($vals,0,-1);
					$vals .= "),";
				}
				$vals = substr($vals,0,-1);
				$qry = "insert into $table$keys values$vals;";
				break;
			case "update":
				$keys = "";
				foreach($value as $key=>$val){
					$keys .= Tools::arrtostr($val,", ","'","`").", ";
				}
				$keys = substr($keys,0,-2);
				$qry = "update $table set $keys";
				if($conditions) $qry .= " where $conditions;";
				break;
			case "delete":
				$keys = "";
				foreach($value as $key=>$val){
					$keys .= "(";
					$keys .= Tools::arrtostr($val," and ","'","`").") or ";
				}
				$keys = substr($keys,0,-4);
				$qry = "delete from $table where $keys;";
				break;
			default:
				$qry = false;
		}
		return $qry;
	}
}	
?>