<?
/**Class details:
	- @warning Class sets sql_mode to ansi sql if mysql db to allow interroperability with postgres.	As such, double quotes " become table and column indicators, ` become useless, and single quotes are used as the primary means to quote strings
	- @note Most of the querying methods are overloaded; there are two forms of possible input:
		- Form 1:	simple sql string; eg "select * from bob where bob = 'bob'"
		- Form 2: 	see self::select
			
	@note public $db, the underlying PDO instance, set on lazy load
*/
Class Db{
	use SDLL;
	/// latest result set returning from $db->query()
	public $result;
	/// last SQL statement
	public $lastSql;
	/**
	@param	connectionInfo	array:
		@verbatim
array(
	driver => ...,
	database => ...,
	host => ...,
	user => ...,
	password ...
		@endverbatim
	@param	name	name of the connetion
	*/
	function __construct($connectionInfo){
		$this->connectionInfo = $connectionInfo;
	}

	function load(){
		if($this->connectionInfo['dsn']){
			$dsn = $this->connectionInfo['dsn'];
		}else{
			$dsn = $this->makeDsn($this->connectionInfo);
		}
		try{
			$this->under = new PDO($dsn,$this->connectionInfo['user'],$this->connectionInfo['password']);
		}catch(PDOException $e){
			if($this->connectionInfo['backup']){
				$this->connectionInfo = $this->connectionInfo['backup'];
				$this->load();
				return;
			}
			throw $e;
		}
		if($this->under->getAttribute(PDO::ATTR_DRIVER_NAME)=='mysql'){
			$this->query('SET SESSION sql_mode=\'ANSI\'');
			$this->query('SET SESSION time_zone=\'+00:00\'');
			#$this->under->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
		}
	}
	function makeDsn($connectionInfo){
		$connectionInfo['port'] = $connectionInfo['port'] ? $connectionInfo['port'] : '3306';
		return $connectionInfo['driver'].':dbname='.$connectionInfo['database'].';host='.$connectionInfo['host'].';port='.$connectionInfo['port'];
	}
	/// returns escaped string with quotes.	Use on values to prevent injection.
	/**
	@param	v	the value to be quoted
	*/
	protected function quote($v){
		return $this->under->quote($v);
	}
	/// return last run sql
	function lastSql(){
		return $this->lastSql;
	}
	/// perform database query
	/**
	@param	sql	the sql to be run
	@return the PDOStatement object
	*/
	protected function query($sql){
		if($this->result){
			$this->result->closeCursor();
		}
		$this->lastSql = $sql;
		$this->result = $this->under->query($sql);
		if((int)$this->under->errorCode()){
			$error = $this->under->errorInfo();
			$error = "--DATABASE ERROR--\n".' ===ERROR: '.$error[0].'|'.$error[1].'|'.$error[2]."\n ===SQL: ".$sql;
			Debug::toss($error,__CLASS__.'Exception');
		}
		if(!$this->result){
			$this->load();
			$this->result = $this->under->query($sql);
			if(!$this->result){
				Debug::toss("--DATABASE ERROR--\nNo result, likely connection timeout",__CLASS__.'Exception');
			}
		}
		return $this->result;
	}

	/// Used internally.	Checking number of arguments for functionality
	protected function getOverloadedSql($expected, $actual){
		$count = count($actual);
		if($count == 1 && strpos($actual[0],' ') === false){//single word string, this is a no-where table
			$actual[] = '1=1';
			$overloaded = 1;
		}else{
			$overloaded = $count - $expected;
		}
		if($overloaded > 0){
			//$overloaded + 1 because the expected $sql is actually one of the overloading variables
			$overloaderArgs = array_slice($actual,-($overloaded + 1));
			return call_user_func_array(array($this,'select'),$overloaderArgs);
			
		}else{
			return end($actual);
		}
	}
	
	/// query returning a row
	/**See class note for input
	@warning "limit 1" is appended to the sql input
	@return	a single row, or, if one column, return that columns value
	*/
	protected function row(){
		$sql = $this->getOverloadedSql(1,func_get_args());
		#function implies only 1 retured row
		if(!preg_match('@[\s]*show@i',$sql)){
			$sql .= "\nLIMIT 1";
		}
		if($res = $this->query($sql)){
			if($res->columnCount()==1){
				return	$res->fetchColumn();
			}
			return $res->fetch(PDO::FETCH_ASSOC);
		}
	}

	/// query returning multiple rows
	/**See class note for input
	@return	a sequential array of rows
	*/
	protected function rows($sql){
		$sql = $this->getOverloadedSql(1,func_get_args());
		$res2 = array();
		if($res = $this->query($sql)){
			$i = 0;
			while($row=$res->fetch(PDO::FETCH_ASSOC)){
				foreach($row as $k=>$v){
					$res2[$i][$k]=$v;
				}
				$i++;
			}
		}
		return $res2;
	}
	
	/// query returning a column
	/**
	See class note for input
	@return	array where each element is the column value of each row.  If multiple columns are in the select, just uses the first column
	*/
	protected function column($sql){
		$sql = $this->getOverloadedSql(1,func_get_args());
		$res = $this->query($sql);
		while($row=$res->fetch(PDO::FETCH_NUM)){$res2[]=$row[0];}
		if(!is_array($res2)){
			return array();
		}
		return $res2;
	}
	
	/// query returning columns keyed their numeric position
	/**
	See class note for input
	@return	array where each element is the column value of each row.  If multiple columns are in the select, just uses the first column
	*/
	protected function columns($sql){
		$sql = $this->getOverloadedSql(1,func_get_args());
		$res = $this->query($sql);
		while($row=$res->fetch(PDO::FETCH_NUM)){$res2[]=$row;}
		if(!is_array($res2)){
			return array();
		}
		return $res2;
	}

	/// query returning number indexed array
	/**See class note for input
	@return	row as numerically indexed array for potential use by php list function
	*/
	protected function enumerate($sql){
		$sql = $this->getOverloadedSql(1,func_get_args());
		$sql .= "\nLIMIT 1";
		return $this->query($sql)->fetch(PDO::FETCH_NUM);
	}
	
	
	/// query returning a column with keys
	/**See class note for input
	@param	key	the column key to be used for each element.	If they key is an array, the first array element is taken as the key, the second is taken as the mapped value column
	@return	array where one column serves as a key pointing to either another column or another set of columns
	*/
	
	protected function columnKey($key,$sql){
		$arguments = func_get_args();
		array_shift($arguments);
		$rows = call_user_func_array(array($this,'rows'),$arguments);
		if(is_array($key)){
			return Arrays::subsOnKey($rows,$key['key'] ? $key['key'] : $key[0], $key['value'] ? $key['value'] : $key[1]);
		}else{
			return Arrays::subsOnKey($rows,$key);
		}
	}
	
	/// internal use.	Key to value formatter (used for where clauses and updates)
	/**
	@param	kvA	various special syntax is applied:
		normally, sets key = to value, like "key = 'value'" with the value escaped
		if key starts with '"', unescaped value taken as entire where line
			ex: ['"':'1=1']
		if "?" is in the key, the part after the "?" will serve as the "equator", ("bob?<>"=>'sue') -> "bob <> 'sue'"
		if key starts with ":", value is not escaped
			if value = null (php null), set string to "null"
			if value is "null", on where is prefixed with "is".	
		if value = null, set value to unescaped "null"
	@param	type	1 = where, 2 = update
	*/
	protected function ktvf($kvA,$type=1){
		foreach($kvA as $k=>$v){
			if($k[0]=='"'){
				$kvtA[] = $v;
			}else{
				if(strpos($k,'?')!==false){
					preg_match('@(^[^?]+)\?([^?]+)$@',$k,$match);
					$k = $match[1];
					$equator = $match[2];
				}else{
					$equator = '=';
				}
				
				if($k[0]==':'){
					$k = substr($k,1);
					if($v == 'null' || $v === null){
						if($type == 1 && $equator == '='){
							$equator = 'is';
						}
						$v = 'null';
					}
				}elseif($v === null){
					if($type == 1 && $equator == '='){
						$equator = 'is';
					}
					$v = 'null';
				}else{
					$v = $this->quote($v);
				}
				$k = self::quoteIdentity($k);
				$kvtA[] = $k.' '.$equator.' '.$v;
			}
		}
		return (array)$kvtA;
	}
	
	///so as to prevent the column, or the table prefix from be mistaken by db as db construct, quote the column
	function quoteIdentity($identity){
		$identity = '"'.$identity.'"';
		#Fields like user.id to "user"."id"
		if(strpos($identity,'.')!==false){
			$identity = implode('"."',explode('.',$identity));
		}
		return $identity;
	}

	/// construct where clause from array or string
	/**
	@param	where	various forms:
		- either plain sql statement "bob = 'sue'"
		- single identifier "fj93" translated to "id = 'fj93'"
		- key to value array.	See self::ktvf()
	@return	where string
	@note if the where clause does not exist, function will just return nothing; this generally leads to an error
	*/
	protected function where($where){
		if(!$where){
			return;
		}elseif(is_array($where)){
			$where = implode("\n\tAND ",$this->ktvf($where));
		}elseif(!$where  && !Tool::isInt($where)){
			return;
		}elseif(!preg_match('@[ =<>]@',$where)){//ensures where is not long where string (bob=sue, bob is null), but simple item.
			if((string)(int)$where != $where){
				$where = $this->quote($where);
			}
			$where = 'id = '.$where;
		}
		return "\nWHERE ".$where;
	}
	protected function intos($command,$table,$rows){
		//use first row as template
		list($keys) = self::kvp($rows[0]);
		$insertRows = array();
		foreach($rows as $row){
			list(,$values) = self::kvp($row);
			$insertRows[] = '('.implode(',',$values).')';
		}
		Db::query($command.' INTO '.$this->quoteIdentity($table).' ('.implode(',',$keys).")\t\nVALUES ".implode(',',$insertRows));
	}
	
	/// Key value parser
	protected function kvp($kvA){
		foreach($kvA as $k=>$v){
			if($k[0]==':'){
				$k = substr($k,1);
				if($v === null){
					$v = 'null';
				}
			}elseif($v === null){
				$v = 'null';
			}else{
				$v = $this->quote($v);
			}
			$keys[] = '"'.$k.'"';
			$values[] = $v;
		}
		return array($keys,$values);
	}
	
	/// Key value formatter (used for insert like statements)
	/**
	@param	kva	array('key' => 'value',...)	special syntax is applied:
		- normally, sets (key) values (value) with the value escaped
		- if key starts with ":", value is not escaped
		- if value = null (php null), set string to null
	*/
	protected function kvf($kvA){
		list($keys,$values) = self::kvp($kvA);
		return ' ('.implode(',',$keys).")\t\nVALUES (".implode(',',$values).') ';
	}

	
	/// Insert into a table
	/**
	@param	table	table to insert on
	@param	kva	see self::kvf() function
	@return	see self::into
	*/
	protected function insert($table,$kvA){
		return $this->into('INSERT',$table,$kvA);
	}
	/// Insert with a table and ignore if duplicate key found
	/**
	@param	table	table to insert on
	@param	kva	see self::kvf() function
	@return	see self::into
	*/
	protected function insertIgnore($table,$kvA,$matchKeys=null){
		return $this->into('INSERT IGNORE',$table,$kvA,'',$matchKeys);
	}
	/// insert into table; on duplicate key update
	/**
	@param	table	table to insert on
	@param	kva	see self::kvf() function
	@param	update	either plain sql or null; if null, defaults to updating all values to $kvA input
	@param	matchKeys	keys used to identify row to get the id
	@return	see self::into
	*/
	protected function insertUpdate($table,$kvA,$update=null,$matchKeys=null){
		if(!$update){
			$update .= implode(', ',$this->ktvf($kvA,2));
		}elseif(is_array($update)){
			$update = implode(', ',$this->ktvf($update,2));
		}
		return $this->into('INSERT',$table,$kvA,"\nON DUPLICATE KEY UPDATE\n".$update,$matchKeys);
	}

	/// replace on a table
	/**
	@param	table	table to replace on
	@param	kva	see self::kvf() function
	@param	matchKeys	keys used to identify row to get the id
	@return	see Db::into
	*/
	protected function replace($table,$kvA,$matchKeys=null){
		return $this->into('REPLACE',$table,$kvA,'',$matchKeys);
	}

	/// internal use; perform insert into [called from in(), inUp()]
	/**
	@note	insert ignore and insert update do not return a row id, so, if the id is not provided and the matchKeys are not provided, may not return row id
	@return will attempt to get row id, otherwise will return count of affected rows
	*/
	protected function into($type,$table,$kvA,$update='',$matchKeys=null){
		$res = $this->query($type.' INTO '.$this->quoteIdentity($table).$this->kvf($kvA).$update);
		if($this->under->lastInsertId()){
			return $this->under->lastInsertId();
		}elseif($kvA['id']){
			return $kvA['id'];
		}elseif($matchKeys){
			$matchKva = Arrays::extract($matchKeys,$kvA);
			return $this->row($table,$matchKva,'id');
		}else{
			return $res->rowCount();
		}
	}

	/// perform update, returns number of affected rows
	/**
	@param	table	table to update
	@param	update	see self::ktvf() function
	@param	where	see self::where() function
	@return	row count
	*/
	protected function update($table,$update,$where){
		if(!$where){
			Debug::toss('Unqualified update is too risky.  Use 1=1 to verify');
		}
		$vf=implode(', ',$this->ktvf($update,2));
		$res = $this->query('UPDATE '.$this->quoteIdentity($table).' SET '.$vf.$this->where($where));
		return $res->rowCount();
	}
	
	/// perform delete
	/**
	@param	table	table to replace on
	@param	where	see self::where() function
	@return	row count
	@note as a precaution, to delete all must use $where = '1 = 1'
	*/
	protected function delete($table,$where){
		if(!$where){
			Debug::toss('Unqualified delete is too risky.  Use 1=1 to verify');
		}
		return $this->query('DELETE FROM '.$this->quoteIdentity($table).$this->where($where))->rowCount();
	}
	
	///generate sql
	/**
	Ex: 
		- row('select * from user where id = 20') vs row('user',20);
		- rows('select name from user where id > 20') vs sRows('user',array('id?>'=>20),'name')
	@param	from	table, array of tables, or from statement
	@param	where	see self::$where()
	@param	columns	list of columns; either string or array.	"*" default.
	@param	order	order by columns
	@param	limit	result limit
	@return sql string
	@note	this function is just designed for simple queries
	*/
	protected function select($from,$where=null,$columns='*',$order=null,$limit=null){
		if(is_array($from)){
			$from = '"'.implode('", "',$from).'"';
		}elseif(strpos($from,' ') === false){//ensure no space; don't quote a from statement
			$from = '"'.$from.'"';
		}
		if(is_array($columns)){
			$columns = implode(', ',array_map([$this,'quoteIdentity'],$columns));
		}
		$select = 'SELECT '.$columns."\nFROM ".$from.$this->where($where);
		if($order){
			if(!is_array($order)){
				$order = Arrays::stringArray($order);
			}
			$orders = array();
			foreach($order as $part){
				$part = explode(' ',$part);
				if(!$part[1]){
					$part[1] = 'ASC';
				}
				//'"' works with functions like "sum(cost)"
				$orders[] = '"'.$part[0].'" '.$part[1];
			}
			$select .= "\nORDER BY ".implode(',',$orders);
		}
		if($limit){
			$select .= "\nLIMIT ".$limit;
		}
		return $select;
	}
//+ helper tools {
	/// query check if there is a match
	/**See class note for input
	@return	true if match, else false
	*/
	protected function check($table,$where){
		$sql = $this->select($table,$where,'1');
		return $this->row($sql) ? true : false;
	}
	
	///get the id of some row, or make it if the row doesn't exist
	/**
	@param	additional	additional fields to merge with where on insert
	*/
	protected function id($table,$where,$additional=null){
		$sql = $this->select($table,$where,'id');
		$id = $this->row($sql);
		if(!$id){
			if($additional){
				$where = Arrays::merge($where,$additional);
			}
			$id = $this->insert($table,$where);
		}
		return $id;
	}
	
	///get id based on name if non-int, otherwise return int
	/**
		@param	dict	dictionary to update on query
	*/
	protected function namedId($table,$name,&$dict=null){
		if(Tool::isInt($name)){
			return $name;
		}
		$id = $this->row($table,['name'=>$name],'id');
		if($dict !== null){
			$dict[$name] = $id;
		}
		return $id;
	}
	
	/// perform a count and select rows; doesn't work with all sql
	/**
	Must have "ORDER" on separate and single line
	Must have "LIMIT" on separate line
	@return	array($count,$results)
	*/
	protected function countAndRows($countLimit,$sql){
		$sql = $this->getOverloadedSql(2,func_get_args());
		$countSql = $sql;
		//get sql limit if exists from last part of sql
		$limitRegex = '@\sLIMIT\s+([0-9,]+( [0-9,]+)?)\s*$@i';
		if(preg_match($limitRegex,$countSql,$match)){
			$limit = $match[1];
			$countSql = preg_replace($limitRegex,'',$countSql);
		}
		
		//order must be on single line or this will not work
		$orderRegex = '@\sORDER BY[\t ]+([^\n]+)\s*$@i';
		if(preg_match($orderRegex,$countSql,$match)){
			$order = $match[1];
			$countSql = preg_replace($orderRegex,'',$countSql);
		}
		
		$countSql = array_pop(preg_split('@[\s]FROM[\s]@i',$countSql,2));
		if($countLimit){
			$countSql = "SELECT COUNT(*)\n FROM (\nSELECT 1 FROM \n".$countSql."\nLIMIT ".$countLimit.') t ';
		}else{
			$countSql = "SELECT COUNT(*)\nFROM ".$countSql;
		}
		$count = $this->row($countSql);
		$results = $this->rows($sql);
		return array($count,$results);
	}
//+ }

//+	db information {
	//Get database tables
	protected function tables(){
		if($this->connectionInfo['driver'] == 'mysql'){
			return $this->column('show tables');
		}
	}
	//get database table column information
	protected function columnsInfo($table){
		$columns = array();
		if($this->connectionInfo['driver'] == 'mysql'){
			$rows = $this->rows('describe '.$this->quoteIdentity($table));
			foreach($rows as $row){
				$column =& $columns[$row['Field']];
				$column['type'] = self::parseColumnType($row['Type']);
				$column['limit'] = self::parseColumnLimit($row['Type']);
				$column['nullable'] = $row['Null'] == 'NO' ? false : true;
				$column['autoIncrement'] = preg_match('@auto_increment@',$row['Extra']) ? true : false;
				$column['default'] = $row['Default'];
				$column['key'] = $row['Key'];
			}
		}
		return $columns;
	}
	//take db specific column type and translate it to general
	static function parseColumnType($type){
		$type = preg_replace('@\([^)]*\)@','',$type);
		if(preg_match('@int@i',$type)){//int,bigint
			return 'int';
		}elseif(preg_match('@decimal@i',$type)){
			return 'decimal';
		}elseif(preg_match('@float@i',$type)){
			return 'float';
		}elseif(in_array($type,array('datetime','date','timestamp'))){
			return $type;
		}elseif(in_array($type,array('varchar','text'))){
			return 'text';
		}
	}
	static function parseColumnLimit($type){
		preg_match('@\(([0-9,]+)\)@',$type,$match);
		if($match[1]){
			$limit = explode(',',$match[1]);
			return $limit[0];
		}
	}
//+ }
}
