<?php
require 'guid.inc.php';

class NoSQLiteCollection {
	var $nosqlite;
	var $id = false;
	var $name;
	var $meta;
	function __construct($nosqlite,$collection=false) {
		$this->nosqlite = $nosqlite;
		//debug("new NoSQLiteCollection: collection=".print_r($collection,1));
		if ( is_array($collection) ) {
			//debug("  from info");
			$this->setInfo($collection);
		} elseif ( is_string($collection) ) {
			//debug("  from name");
			$this->setName($collection);
		} else {
			//debug("  can't use info");
		}
	}
	function setId($id) {
		$info = $this->nosqlite->getCollection($id,'raw');
		return $this->setInfo($info);
	}
	function setName($name) {
		$info = $this->nosqlite->getCollectionByName($name,'raw');
		return $this->setInfo($info);
	}
	function setInfo($info) {
		if ( $info ) {
			$this->id = $info['id'];
			$this->name = $info['name'];
			$this->meta = $info['value'];
			return true;
		} else {
			$this->id = false;
			$this->name = false;
			$this->meta = false;
			return false;
		}
	}
	function get($name,$format='mongo') {
		if ( ! $this->id ) return false;
		return $this->nosqlite->getItem($this->id,$name,$format);
	}
	function getAll($format='mongo') {
		if ( ! $this->id ) return false;
		return $this->nosqlite->getAllItemsInCollection($this->id,$format);
	}
	function to_words( $text ) {
		$words = array_filter( preg_split( '/[^a-zA-Z0-9]+]/', $text ) );
		return $words;
	}
	function overlap( $words1, $words2 ) {  // words 1 is needle words 2 is haystack
		$words1 = array_unique( $words1, SORT_STRING );
		$words2 = array_unique( $words2, SORT_STRING );
		$len1 = count($words1);
		$len2 = count($words2);
		$overlap = 0;
		for ( $i1=0, $i2=0; $i1<$len1 && $i2<$len2; ) {
			$cmp = strcmp( $words1[$i1], $words2[$i2] );
			if ( $cmp == 0 ) {
				$i1++; $i2++; $overlap++;
			} elseif ( $cmp < 0 ) { // word 1 < word 2
				$i1++;
			} else { // ( $cmp > 0 ) word 1 > word 2
				$i2++;
			}
		}
		return ( 100 * ( $overlap + $overlap/($len2+1) ) ) /($len1+1); 
			// always better to match more, but larger targets less impressive when matched
			// range 0-100, including fractional values
	}
	function search($fields,$search_text,$limit=10) {
		if ( $limit <= 0 ) return array();
		if ( ! is_array( $fields ) ) $fields = array( $fields );
		$search_words = $this->to_words( $search_text );
		//echo "find(".print_r($pattern,1).")       <br>\n\n";
		$docs = $this->getAll();
		//echo "got docs       <br>\n\n";
		//echo "got ".($docs?count($docs):"no")." docs       <br>\n\n";
		if (! $docs ) return false;
		$filtered = array();
		//$count = 2;
		$best_list = array();
		foreach( $docs as $doc ) {
			$values = array();
			foreach( $fields as $field ) {
				$values = $values + $this->getFieldExpr($field,$doc); // bioth arrays
			}
			$haystack_text = implode( ' ', $values );
			$haystack_words = $this->to_words( $haystack_text );
			
			$relevance = $this->overlap( $search_words, $haystack_words );
			
			if ( $relevance <= 0 ) continue;
			
			//echo "search: $relevance = overlap( [{".implode('}{',$search_words)."}], [{".implode('}{',$haystack_words)."}] ) <br>\n";
			
			for( $i=0; $i<count($best_list) && $relevance > $best_list[$i]['relevance']; $i++ ); // do nout
			
			if ( $i>0 || count($best_list) < $limit ) {
				//echo "insert into best list, was ".print_r($best_list,1)." <br>\n\n";
				//$best_list = array_splice( $best_list, $i, 0, array( array( 'relevance'=>$relevance, 'document'=>$doc ) ) );
				$best_list = array_slice( $best_list, 0, $i) + array( array( 'relevance'=>$relevance, 'document'=>$doc ) ) + array_slice( $best_list, $i);
				if ( count($best_list) > $limit ) {
					//echo "trim as count ".count($best_list)." > $limit <br>\n\n";
					array_shift($best_list);
				}
				//echo "after ".print_r($best_list,1)." <br>\n\n";
			}
		}
		//echo "search returns ".print_r($best_list,1)." <br>\n\n";
		return array_reverse( $best_list );
	}
	function getFieldExpr($field,$doc) {
		$fparts = explode( '.', $field );
		$res = array ( $doc );
		foreach( $fparts as $name ) {
			$merged = array();
			foreach ( $res as $data ) {
				if ( ! array_key_exists( $name, $data ) ) continue;
				$val = $data[$name];
				if ( $this->type($val) == 'index' ) {
					$merged = $merged + $val;   // union duplicates allowed
				} else {
					$merged[] = $val; 
				}
			}
			$res = $merged;
		}
		return $res;
	}
	function findOne($pattern) {
		$filtered = $this->find($pattern);
		if ( count($filtered) ) {
			return $filtered[0];
		} else {
			return false;
		}
	}
	function find($pattern,$limit=0) {
		//debug( "find(".print_r($pattern,1).")" );
		$docs = $this->getAll();
		//echo "got docs       <br>\n\n";
		//debug( "got ".($docs?count($docs):"no")." docs" );
		if (! $docs ) return false;
		$filtered = array();
		$count=0;
		//$count = 2;
		foreach( $docs as $doc ) {
			//if ( --$count < 0 ) break;
			//debug( "matching: ".print_r($doc,1) );
			if ( $this->match($doc,$pattern) ) {
				//debug( "***** matched!");
				$filtered[] = $doc;
				$count++;
				if ( $count >= $limit ) {
					break;
				}
			}
		}
		//debug( "filtered down to ".count($filtered)." docs" );
		return $filtered;
	}
	function match($document,$pattern) {
		foreach ( $pattern as $field => $field_pat ) {
			$val = @$document[$field];
			//echo "match $field vs ".print_r($field_pat,1) . "<br>\n";
			//echo "field $field = ".print_r($val,1) . "<br>\n";
			if ( ! $this->matchVal( $val, $field_pat ) ) {
				return false;
			}
		} // foreach
		return true;
	}
	function matchVal( $val, $val_pattern ) {
		//echo "<dl><dt>matchVal: </dt>\n";
		//	echo "  <dd>  val: ".print_r($val,1) . "</dd>\n";
		//	echo "  <dd>  pat: ".print_r($val_pattern,1) . "</dd> </dl>\n";
		$val_type = $this->type($val);
		$pat_type = $this->type($val_pattern);
		switch ( $val_type ) {
			case 'number': case 'string':
					switch ( $pat_type ) {
						case 'number': case 'string':
						   // echo "value match $val_pattern == $val <br>\n";
							return $val_pattern == $val;
						case 'index':
						case 'sparse':
						    //echo "literal value matched to expr pattern <br>\n";
							return $this->matchArrayExpr( $val, $val_pattern );
						default: // assume assoc
							return false;  // wrong type
					}
			
			case 'index':  // use mongo set semantics
			case 'sparse':
					switch ( $pat_type ) {
						case 'number': case 'string':
						    //echo "value match pattern $val_pattern in set ".print_r($val,1)." <br>\n";
							return in_array( $val_pattern, $val );
						case 'index':
						case 'sparse':
							foreach ( $val as $one_val ) {
								if ( $this->matchArrayExpr( $one_val, $val_pattern ) ) {
									return true;
								}
							}
							return false;
						default: // assume assoc
							foreach ( $val as $one_val ) {
								if ( $this->matchVal( $one_val, $val_pattern ) ) {
									return true;
								}
							}
							return false;
					}
			
			default: // assume assoc
					switch ( $pat_type ) {
						case 'assoc':
							return $this->match( $val, $val_pattern );
						default: // assume assoc
							return false;  // wrong type
					}
		} // switch val
	}
	function matchArrayExpr( $val, $expr ) {  // ************  real versn should use assoc
		//echo "<dl><dt>matchArrayExpr: </dt>\n";
		//	echo "  <dd>  val: ".print_r($val,1) . "</dd>\n";
		//	echo "  <dd>  expr: ".print_r($expr,1) . "</dd> </dl>\n";
		switch ( $expr[0] ) {
			case '$gt':
					return $val > $expr[1];
			case '$lt':
					return $val < $expr[1];
			case '$ge':
					return $val >= $expr[1];
			case '$le':
					return $val <= $expr[1];
			default:
					return false;
		}
	}
	function matchExpr( $val, $expr ) {
		//echo "<dl><dt>matchExpr: </dt>\n";
		//	echo "  <dd>  val: ".print_r($val,1) . "</dd>\n";
		//	echo "  <dd>  expr: ".print_r($expr,1) . "</dd> </dl>\n";
		// N.B. implicit and
		foreach( $expr as $op => $arg ) {
			if ( ! matchOp( $val, $op, $arg ) ) return false;
		}
		return true;
	}
	function matchOp( $val, $op, $arg ) {
		//echo "<dl><dt>matchOp($op) </dt>\n";
		//	echo "  <dd>  val: ".print_r($val,1) . "</dd>\n";
		//	echo "  <dd>   op: $op</dd>\n";
		//	echo "  <dd>  arg: ".print_r($arg,1) . "</dd> </dl>\n";
		switch ( $op ) {
			case '$gt':
					return $val > $arg;
			case '$lt':
					return $val < $arg;
			case '$ge':
					return $val >= $arg;
			case '$le':
					return $val <= $arg;
			default:
					debug( "unrecognised nosqlite expression op: ".$op );
					return false;
		}
	}
	function type($v) {
		if ( is_object($v) ) {
			return 'object';
		} else if ( is_array($v) ) {
			return array_type( $v );
		} else if ( is_numeric($v) ) {
			return 'number';
		} else {
			return 'string';
		}
	}
	function put($name,$value) {
		if ( ! $this->id ) return false;
		return $this->nosqlite->putItem($this->id,$name,$value);
	}
	static function split_dollar_fields( $pattern ) {
		$dollar = array();
		$plain=array();
		foreach ( $pattern as $name=>$value ) {
			if ( $name{0}=='$' ) {
				$dollar[$name]=$value;
			} else {
				$plain[$name]=$value;
			}
		}
		return array( 'dollar'=>$dollar, 'plain'=>$plain );
	}
	
	function applyUpdate( $doc, $replace ) {
		foreach ( $replace as $name=>$value ) {
			if ( $name{0}=='$' ) {
				switch( $name ) {
					case '$set':
							$doc = array_merge( $doc, $value );
							break;
					case '$inc':
							foreach ( $value as $inc_name => $inc_amount) {
								$doc[$inc_name] += $inc_amount;
							}
							break;
					case '$addToSet':
							//echo "doing addToSet: doc=". h(print_r($doc,1)) . "  pattern=" . h(print_r($value,1)) . " <br>\n";
							foreach ( $value as $as_name => $as_value) {
								NoSQLite::addToSet( $as_value, $doc[$as_name] );
							}
							//echo "after addToSet: doc=". h(print_r($doc,1)) . " <br>\n";
							break;
					default:
							debug( 'NoSqlite unrecognised update option ' . $name );
				}
			} else {
				if ( is_array( $value ) || is_object( $value ) ) {
					$doc[$name]=$this->applyUpdate( $doc[$name], $value );
				} else {
					$doc[$name]=$value;
				}
			}
		} 
		return $doc;
	}
	function update($pattern,$replace,$upsert=false,$multi=false) {
		//debug( "update(".print_r($pattern,1).",".print_r($replace,1).",$upsert,$multi)");
		if ( ! $this->id ) return false;
		$pattern_split =  self::split_dollar_fields( $pattern );
		$limit = $multi ? 0 : 1 ;
		$update_list = $this->find($pattern_split['plain'],$limit);  // NOT got any dollar bits yet
		if ( ! $update_list ) {
			//debug( "update: nothing found");
			if ( ! $upsert ) return 0;
			$update_list = array( $pattern_split['plain'] );
		}
		foreach( $update_list as $doc ) {
			//debug( "update: doc: ".print_r($doc,1)."");
			$doc = $this->applyUpdate( $doc, $replace );
			$this->save($doc);
			//debug( "update: saved: ".print_r($doc,1)."");
		}
		return count( $update_list );
	}
	function save($value) {
		return $this->insert($value);
		/*
		if ( ! $this->id ) return false;
		if ( ! $value['_id'] ) {
			$value['_id'] = NoSQLite::guid();
		}
		return $this->nosqlite->putItem($this->id,$value['_id'],$value);
		*/
	}
	function insert($document,$options=array()) {
		if ( ! $this->id ) return false;
		//$name = $document['_id'];
		if ( @$document['_id'] ) {
			$name = $document['_id'];
		} else {
			$name = generatePackedGuid();
		}
		if( $this->nosqlite->putItem($this->id,$name,$document) ) {
			$document['_id'] = $name;
			return $document;
		} else {
			return false;
		}
	}
	function remove($name,$options=array()) {
		if ( ! $this->id ) return false;
		if ( is_string($name) ) {
			return $this->nosqlite->deleteItemByName($this->id,$name);
		} else {
			if ( $options['justOne'] ) {
			} else {
			}
			debug('remove(criteria) not yet implemented');
			return false;
		}
	}
	function clearAll() {
		return $this->nosqlite->clearCollection($this->id);
	}
	
}



class NoSQLite {
	static function addToSet( $item, &$set ) {
		if ( ! $set ) {
			$set = array($item);
			return true;
		} elseif ( !in_array( $item, $set ) ) {
			$set[] = $item;
			return true;
		} else {
			return false;
		}
	}
	static function guid( ) {
		return generatePackedGuid();
	}
	
	
	var $database, $link=false;
	var $prefix='nosqlite';
	
	var $collection_table, $data_table, $index_table;
	
	var $errors = array();

	function __construct($prefix='nosqlite_',$database=false,$user=false,$password=false,$host='localhost') {
		$this->setPrefix($prefix);
		if ( $database ) $this->setDatabase($database,$user,$password,$host);
	}
	
	function getErrors() {
		return $this->errors;
	}

	function clearErrors() {
		$this->errors = array();
	}

	function addError($err) {
		$this->errors[] = $err;
	}

	function setPrefix($prefix='nosqlite_') {
		$this->prefix = $prefix;
		$this->collection_table  = $prefix . 'collection';
		$this->data_table  = $prefix . 'data';
		$this->index_table = $prefix . 'index';
	}

	function setDatabase($database,$user,$password,$host='localhost') {
		$this->database = $database;
		$this->link = mysql_connect($host,$user,$password);
		if ( $this->link ) mysql_select_db($database,$this->link);
	}

	function doSQL($sql) {
		if ( ! $this->link ) return false;
		//debug ( $sql );
		$res = mysql_query($sql,$this->link);
		if ( $res ) {
			//debug ( "SQL OK: " . $res );
			return $res;
		}
		debug( "SQL error: " . mysql_error() );
		debug ("query is \"$sql\" ");
		$this->addError( "SQL error: " . mysql_error() );
		return false;
	}
	
	function createTables() {
		$sql =  "CREATE TABLE IF NOT EXISTS ".$this->collection_table."  (  \n"
		      . "   id int auto_increment primary key, \n"
		      . "	hash       varchar(255) UNIQUE KEY, \n"
		      . "	name       text, \n"
		      . "	value      text \n"
		      . ") ";
		if ( ! $this->doSQL($sql) ) return false;
		$sql =  "CREATE TABLE IF NOT EXISTS ".$this->data_table."  (  \n"
		      . "   id int auto_increment primary key, \n"
		      . "	collection_id  int, \n"
		      . "	hash       varchar(255), \n"
		      . "	name       text, \n"
		      . "	value      text, \n"
		      . "	UNIQUE KEY collection_hash  (collection_id,hash) \n"
		      . ") ";
		return $this->doSQL($sql);
	}
	
	function hash($name) {
		return md5($name);
	}
	
	function dropTables() {
		$sql =  "DROP TABLE IF EXISTS ".$this->collection_table.",".$this->data_table;
		$drop_res = $this->doSQL($sql);
		return $drop_res;
		/*
		$sql = "SHOW COLUMNS FROM ".$this->collection_table;
		if ( $this->doSQL($sql) ) return false;
		$sql = "SHOW COLUMNS FROM ".$this->data_table;
		if ( $this->doSQL($sql) ) return false;
		return true;
		*/
	}
	
	function createCollection($name,$jvalue=false) {
		if ( ! $jvalue ) $jvalue = array();
		$value = json_encode($jvalue);
		//debug("createCollection($name,$value)");
		$hash = $this->hash($name);
		$collection_id = $this->do_insert_or_update($this->collection_table,array('hash'=>$hash),array('name'=>$name,'value'=>$value),true,false);
		return $collection_id;
	}
	
	function deleteCollection($id) {
		return $this->delete_by_map($this->collection_table,array('id'=>$id));
	}
	
	function deleteCollectionByName($name) {
		$hash = $this->hash($name);
		return $this->delete_by_map($this->collection_table,array('hash'=>$hash));
	}
	
	function getAllCollections($format='object') {
		$maker = $this->getCollectionMaker($format); 
		$collection = $this->get_all_by_map($this->collection_table,false,$maker);
		return $collection;
	}
	
	function getCollection($id,$format='object') {
		$maker = $this->getCollectionMaker($format); 
		$collection = $this->get_one_by_map($this->collection_table,array('id'=>$id),$maker);
		return $collection;
	}
	
	function getCollectionByName($name,$format='object') {
		$maker = $this->getCollectionMaker($format); 
		$hash = $this->hash($name);
		$collection = $this->get_one_by_map($this->collection_table,array('hash'=>$hash),$maker);
		return $collection;
	}
	
	function clearCollection($collection_id) {
		return $this->delete_by_map($this->data_table,array('collection_id'=>$collection_id));
	}
	
	function selectCollection($name) {
		$collection = $this->getCollectionByName($name);
		if ( ! $collection ) {
			$id = $this->createCollection($name);
			$collection = new NoSQLiteCollection($this);
			$collection->setId($id); 
		}
		return $collection;
	}
	
	
	function getCollectionMaker($format='raw') {
		switch( $format ) {
			case 'raw':
						return array( $this, 'makeCollectionFromSQL' );
			case 'object':
			default:
						return array( $this, 'makeCollectionObjectFromSQL' );
		}
	}
	
	function makeCollectionObjectFromSQL($record) {
		$info = $this->makeCollectionFromSQL($record);
		$obj = new NoSQLiteCollection($this,$info);
		return $obj;
	}
	
	function makeCollectionFromSQL($record) {
		if ( ! $record ) {
			return false;
		}
		$id = $record['id'];
		$name = $record['name'];
		$value = $record['value'];
		$jvalue = json_decode($value,true);
		return array('id'=>$id,'name'=>$name,'value'=>$jvalue);
	}
	
	function putItem($collection_id,$name,$jvalue=false) {
		$hash = $this->hash($name);
		$value = json_encode($jvalue);
		$item_id = $this->do_insert_or_update($this->data_table,array('collection_id'=>$collection_id,'hash'=>$hash),array('name'=>$name,'value'=>$value),true);
		return $item_id;
	}
	
	function getAllItemsInCollection($collection_id,$format='mongo') {
		//$maker = array($this,'makeItemFromSQL');
		$maker = $this->getItemMaker($format); 
		$items = $this->get_all_by_map($this->data_table,array('collection_id'=>$collection_id),$maker);
		return $items;
	}
	
	function getItem($collection_id,$name,$format='mongo') {
		//debug("getItem($collection_id,$name,$format");
		$hash = $this->hash($name);
		//$maker = array($this,'makeItemFromSQL');
		$maker = $this->getItemMaker($format); 
		$collection = $this->get_one_by_map($this->data_table,array('collection_id'=>$collection_id,'hash'=>$hash),$maker);
		return $collection;
	}
	
	function deleteItem($id) {
		return $this->delete_by_map($this->data_table,array('id'=>$id));
	}
	
	function deleteItemByName($collection_id,$name) {
		$hash = $this->hash($name);
		return $this->delete_by_map($this->data_table,array('collection_id'=>$collection_id,'hash'=>$hash));
	}
	
	function getItemMaker($format='raw') {
		switch( $format ) {
			case 'raw':
						return array( $this, 'makeItemFromSQL' );
			case 'mongo':
			default:
						return array( $this, 'makeItemMongoFromSQL' );
		}
	}
	
	function makeItemMongoFromSQL($record) {
		$info = $this->makeItemFromSQL($record);
		$mongo = $info['value'];
		if ( ! is_array( $mongo ) ) $mongo = array ( '_value' => $mongo );
		$mongo['_id'] = $info['name'];
		return $mongo;
	}
	
	function makeItemFromSQL($record) {
		if ( ! $record ) {
			return false;
		}
		$id = $record['id'];
		$collection_id = $record['collection_id'];
		$name = $record['name'];
		$value = $record['value'];
		$jvalue = json_decode($value,true);
		return array('id'=>$id,'collection_id'=>$collection_id,'name'=>$name,'value'=>$jvalue);
	}
	
	function sqlq($str) {
	  return "'" . mysql_real_escape_string($str) . "'";
	}
	
	function sqldate($timestamp=0) {
	  if ( ! $timestamp ) $timestamp = time();
	  return date("Y-m-d H:i:s",$timestamp);
	}

	function do_insert_or_update($table,$keys,$vars,$return_id = false,$update_vars=true) {
		list($knames,$kvals) = $this->vars_2_insert($keys);
		$names = $knames;
		$vals = $kvals;
		$set = '';
		if ( $vars ) {
			list($vnames,$vvals) = $this->vars_2_insert($vars);
			$names .= ',' . $vnames;
			$vals .= ',' . $vvals;
			if ( $update_vars ) {
				$setvars = $this->vars_2_set($vars);
			}
		} else {
			$setvars = '';
		}
		if ( $return_id ) {
			$idset = 'id=LAST_INSERT_ID(id)';
			if ( @ $setvars ) {
				$setvars = $idset . ', '.$setvars;
			} else {
				$setvars = $idset;
			}
		}
		$sql = "INSERT INTO $table ( $names ) VALUES ( $vals) ";
		if ( $setvars ) {
			$sql .= "ON DUPLICATE KEY UPDATE $setvars";
		}
		$result = $this->doSQL($sql);
		if ( ! $result ) {
			return false;
		}
		if ( $return_id ) {
			$id = mysql_insert_id();
			//echo "<!-- returning $id from $sql --><br>\n";
			return $id;
		} else {
			return true;
		}
	}
	
	function do_insert($table,$vars,$return_id = false) {
		list($names,$vals) = vars_2_insert($vars);
		$sql = "INSERT INTO $table ( $names ) VALUES ( $vals)";
		$result = $this->doSQL($sql);
		if ( ! $result ) {
			return false;
		}
		if ( $return_id ) {
			$id = mysql_insert_id();
			//echo "<!-- returning $id from $sql --><br>\n";
			return $id;
		} else {
			return true;
		}
	}
	
	function vars_2_insert($vars) {
		$names ="";
		$vals ="";
		foreach ( $vars as $name => $val ) {
			if ( $names ) {
				$names .= ", ";
				$vals .= ", ";
			}
			$names .= $name;
			$vals .= $this->sqlq($val);
		}
		return array($names,$vals);
	}
	
	function vars_2_where($vars) {
		$where = "";
		foreach ( $vars as $name => $val ) {
			if ( is_array($val) ) {
				$orlist = "";
				foreach ( $val as $oneval ) {
					if ( $orlist ) $orlist .= " OR ";
					$orlist .=  $name . "=" . $this->sqlq($oneval);
				}
				if ( $orlist ) {
					$wherebit = "( $orlist )";
				} else {
					$wherebit = "";
				}
			} else {
				$wherebit = $name . "=" . $this->sqlq($val);
			}
			if ( $wherebit ) {
				if ( $where ) {
					$where .= " AND ";
				}
				$where .= $wherebit;
			}
		}
		return $where;
	}
	
	function vars_2_set($vars) {
		$setvars = "";
		foreach ( $vars as $name => $val ) {
			if ( $setvars ) {
				$setvars .= ", ";
			}
			$setvars .= $name . "=" . $this->sqlq($val);
		}
		return $setvars;
	}
	
	function do_update_by_map($table,$where_vars,$update_vars) {
		//echo "do_update_by_map($table,$where_vars,$update_vars)<br>\n";
		if ( !$update_vars || count($update_vars) == 0 ) {
			debug( "no update_vars!" );
			return true;  // succeed on NOOP
		}
		$setvars = $this->vars_2_set($update_vars);
		//echo "setvars=" . $setvars . "<br>\n";

		$sql = "UPDATE $table SET $setvars";
		if ( $where_vars  && count($where_vars) > 0 ) {
			$where = $this->vars_2_where($where_vars);
			if ( ! $where ) return false;
			$sql .= " WHERE $where";
		}
		//echo "sql=" . $sql . "<br>\n";
		$result = $this->doSQL($sql);
		return $result;
	}
	
	function delete_by_map($table,$vars) {
		$sql = "DELETE FROM $table";
		if ( $vars  && count($vars) > 0 ) {
			$where = $this->vars_2_where($vars);
			if ( ! $where ) return false;
			$sql .= " WHERE $where";
		}
		return $this->doSQL($sql);
	}
	
	function count_by_map($table,$vars) {
		$sql = "SELECT count(*) FROM $table";
		if ( $vars  && count($vars) > 0 ) {
			$where = $this->vars_2_where($vars);
			if ( ! $where ) return array();
			$sql .= " WHERE $where";
		}
		return $this->get_count($sql);
	}
	
	function get_all_by_map($table,$vars,$maker=false) {
		$sql = "SELECT * FROM $table";
		if ( $vars  && count($vars) > 0 ) {
			$where = $this->vars_2_where($vars);
			if ( ! $where ) return array();
			$sql .= " WHERE $where";
		}
		//echo "get_all_by_map: sql=\"$sql\"<br>\n";
		//return array();
		return $this->get_all($sql,$maker);
	}
	
	function get_one_by_map($table,$vars,$maker=false) {
		$sql = "SELECT * FROM $table";
		if ( $vars  && count($vars) > 0 ) {
			$where = $this->vars_2_where($vars);
			if ( ! $where ) return false;
			$sql .= " WHERE $where";
		}
		return $this->get_one($sql,$maker);
	}
	
	function get_one($sql,$maker=false) {
		$result = $this->doSQL($sql);
		if ( ! $result ) {
			return false;
		}
		$nos = mysql_num_rows($result);
		if ( $nos < 1 ) {
			return false;
		}
		$row = mysql_fetch_array ($result);
		if ( $maker ) {
			return call_user_func($maker,$row);
		} else {
			return $row;
		}
	}
	
	function get_all($sql,$maker=false) {
		$result = $this->doSQL($sql);
		if ( ! $result ) {
			return false;
		}
		$records = array();
		while ( $row = mysql_fetch_array ($result) ) {
			if ( $maker ) {
				$records[] = call_user_func($maker,$row);
			} else {
				$records[] = $row;
			}
		}
		return $records;
	}

	function get_count($sql) {
		$result = $this->doSQL($sql);
		if ( ! $result ) {
			return false;
		}
		$nos = mysql_num_rows($result);
		if ( $nos != 1 ) {
			return 0;
		}
		$row = mysql_fetch_array ($result);
		return $row[0];
	}
}







?>