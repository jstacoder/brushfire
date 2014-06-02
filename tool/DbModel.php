<?
/**
Using framework standard db related naming, this model can determine relations.  And, with these links, can use relation based queries.

@note Use loadModel(true) to clear cache

For clarity
	dt: domestic table
	dc: domestic column
	ft: foreign table
	fc: foreign column



*/
class DbModel{
	use SDLL { SDLL::__call as ___call; SDLL::__construct as ___construct; }
	function __call($fnName,$args){
		if(method_exists(__class__,$fnName)){
			return $this->___call($fnName,$args);
		}elseif(method_exists($this->db,$fnName)){
			$sql = $this->select($args[0],$args[1],$args[2],$args[3],$args[4]);
			return call_user_func(array($this->db,$fnName),$sql);
		}
		Debug::toss(__class__.' Method not found: '.$fnName);
	}
	function load($db,$savePath){
		$this->loadModel();
		return $this->tables;
	}
	function  __construct($db,$savePath=null){
		$this->db = $db;
		$this->savePath = $savePath;
		if(!$this->savePath){
			$connectionInfo = $this->db->connectionInfo;
			$subName = $connectionInfo['database'] ? $connectionInfo['database'] : md5($connectionInfo['dsn']);
			$this->savePath = Config::$x['storageFolder'].'models/'.$this->db->name.'.'.$subName;
		}
		$this->___construct($db,$savePath);
	}
	///if model is previously constructed into file, load it, otherwise, construct it
	protected function loadModel(){
		$this->tables = array();
		if(is_file($this->savePath)){
			require($this->savePath);
			$this->tables = $tables;
		}else{
			$this->model();
			$file = "<?\n".'$tables = '.var_export($this->tables,1).';';
			Files::write($this->savePath,$file);
		}
	}
	///clear cache, load model again
	protected function reloadModel(){
		if(is_file($this->savePath)){
			unlink($this->savePath);	
		}
		$this->loadModel();
	}
	///generate the array that represents the model and put it into static::$models
	protected function model(){
		$tables = $this->db->tables();
		foreach($tables as $table){
			$this->modelTable($table);
		}
		$this->shareLinks();
	}
	protected function modelTable($table){
		$db = $this->db;
		$mTable = &$this->tables[$table];
		
		$mTable['columns'] = $columns = $db->columnsInfo($table);
		$basePath = '/'.str_replace('_','/',$table).'/';
		$mTable['links'] = array();
		foreach($columns as $column=>$info){
			//first, check for fc_ indicater
			if(preg_match('@^fc_@',$column)){
				preg_match('@(^_+)(.*)@',$column,$match);
				$parts = explode('__',substr($match[2],3));
				$absoluteTable = self::getAbsolute($basePath,$part[0],$match[1]);
				$mTable['links'][] = array('ft'=>$absoluteTable,'fc'=>$part[1],'dc'=>$column);
			}else{
				//+	Id Column referencing {
				if($column[0] != '_' && preg_match('@(.+)_id($|__)@',$column,$match)){//named column
					$mTable['links'][] = array('ft'=>$match[1],'fc'=>'id','dc'=>$column);
				}elseif(preg_match('@^(_+)id($|__)@',$column,$match)){//purely backwards relative + "id"
					$relativity = $match[1];
					$absoluteTable = self::getAbsolute($basePath,'',$relativity);
					$mTable['links'][] = array('ft'=>$absoluteTable,'fc'=>'id','dc'=>$column);
				}elseif(preg_match('@^(_+)(.*)?(_id($|__))@',$column,$match)){//relative id columns
					$relativity = $match[1];
					$relativeTable = $match[2];
					$absoluteTable = self::getAbsolute($basePath,$relativeTable,$relativity);
					$mTable['links'][] = array('ft'=>$absoluteTable,'fc'=>'id','dc'=>$column);
				}
				//+	}
			}
		}
		return $mTable;
	}
	///map domestic columns (dc) to foreign columns (fc) by using existing fc to dc map
	protected function shareLinks(){
		$tables = $this->tables;///going to be modifying links, so make a copy
		foreach($tables as $name=>$table){
			foreach($table['links'] as $link){
				$this->tables[$link['ft']]['links'][] = array('ft'=>$name,'fc'=>$link['dc'],'dc'=>$link['fc']);
			}
		}
	}
	///get a non-conflicting alias for the table
	protected function alias($tableName,&$acronyms){
		$acronym = Tool::acronym($tableName);
		$alias = $acronym.((int)$acronyms[$acronym]);
		$acronyms[$acronym]++;
		return $alias;
	}
	
	//basePath can be the fformatted basePath or a table name
	static function getAbsolute($basePath,$relativePath,$relativity=null){
		if($basePath[0] != '/'){
			$basePath = '/'.str_replace('_','/',$basePath).'/';
		}
		if(!$relativity){
			if(preg_match('@(^_+)(.*)@',$relativePath,$match)){
				$relativePath = $match[2];
				$relativity = $match[1];
			}
		}
		$relativePath = $basePath.str_replace('_','../',$relativity).$relativePath;
		//ensure path has ending "/"
		if(substr($relativePath,-1) != '/'){
			$relativePath .= '/';
		}
		$absoluteTable = str_replace('/','_',substr(Tool::absolutePath($relativePath),1,-1));
		return $absoluteTable;
	}
	/**
	@param	$columns	in one of three forms
		dc
		ft.fc
		ft.dc.fc
			since, on occasion, a table may be doubly referenced, it is necessary to specify which refernce column to use on a join
	*/
	/** Requirements
		//General design
			tableIdentity.column aliasColumnName, ...
			from table tableIdentity
				left join table tableIdentity on tableIdentity.referenceColumn = tableIdentity.referencedColumn
			where tableIdentity.column = x
			order by tableIdentity.column
		// functionality
			generate tableIdentity
				acronym + number
			swap aliasColumnName with tableIdentity.column on where, order
		*/
	/**
	@param	where	array as passed to Db::where.  Be careful on text where, identifiers are search from within whole text
	*/
	protected function select($table,$columns,$where=array(),$order=null,$limit=null){
		$mTable = $this->tables[$table];
		$links = array();
		//since the same table may be joined more than once, need alias each time
		$acronyms = array();
		
		foreach($columns as $column){
			$parts = explode('.',$column);
			if(count($parts) == 1){
				$columnIdentities[$column] = array(
						'quoted' => Db::quoteIdentity('t.'.$column),
						'unquoted' => 't.'.$column
					);
			}elseif(count($parts) >= 2){
				//+	get absolute referenced table path {
				if($parts[0][0] == '_'){//back relative table
					$referencedTable = self::getAbsolute($table,$parts[0]);
				}elseif(substr($column,-1) == '_'){//foward relative table
					$referencedTable = $table.'_'.$parts[0];
				}else{
					$referencedTable = $parts[0];
				}
				//+	}
				
				if(count($parts) == 3){
					$link = $this->findLink($table,$referencedTable,$parts[1]);
					$originalColumn = $parts[2];
				}else{
					$link = $this->findLink($table,$referencedTable);
					$originalColumn = $parts[1];
				}
				
				if(!$links[$link]){
					$links[$link] = $this->alias($mTable['links'][$link]['ft'],$acronyms);
				}
				
				$columnIdentities[$column] = array(
						'quoted' => Db::quoteIdentity($links[$link]).'.'.Db::quoteIdentity($originalColumn),
						'unquoted' => $links[$link].'.'.$originalColumn,
					);
			}
		}
		
		//+	handle where, select, and order {
		//need to replace aliases with identities.  So, attempt to identify and replace the aliases
		
		if($where){
			if(is_array($where)){
				foreach($where as $k=>$v){
					$newWhere = array('key'=>$k,'value'=>$v);
					foreach($columnIdentities as $alias => $identity){
						$pattern = '@(^|[^a-zA-Z0-9._])'.preg_quote($alias).'([^a-zA-Z0-9._]|$)@';
						if(preg_match($pattern,$k)){
							$newK = preg_replace($pattern,'$1'.$identity['unquoted'].'$2',$k);
							$newWhere = array('key'=>$newK,'value'=>$v);
							break;
						}
					}
					$newWheres[$newWhere['key']] = $newWhere['value'];
				}
			}else{
				foreach($columnIdentities as $alias => $identity){
					$pattern = '@(^|[^a-zA-Z0-9._])'.preg_quote($alias).'([^a-zA-Z0-9._]|$)@';
					$where = preg_replace($pattern,'$1'.$identity['unquoted'].'$2',$where);
				}
			}
			$where = Db::where($newWheres);
		}
		//create order and the select with aliases and identities
		foreach($columnIdentities as $alias => $identity){
			if($order){
				$order = preg_replace('@(^|[^a-zA-Z0-9.])'.preg_quote($alias).'([^a-zA-Z0-9.]|$)@','$1'.$identity['quoted'].'$2',$order);
			}
			//create select
			$select[] = $identity['quoted'].' '.Db::quote($alias);
		}
		$select = 'SELECT '.implode(', ',$select);
		if($order){
			$order = "\n ORDER BY ".$order;
		}
		//+ }
				
		//create from
		$from[] = "\nFROM ".Db::quoteIdentity($table).' t';
		foreach($links as $link=>$alias){
			$link = $mTable['links'][$link];
			$from[] = $link['ft'].' '.$alias.' on t.'.$link['dc'].' = '.$alias.'.'.$link['fc'];
		}
		$from = implode("\n\tLEFT JOIN ",$from);
		if($limit){
			$limit = "\n LIMIT ".$limit;
		}
		return $select.$from.$where.$order.$limit;
	}
	protected function findLink($table,$referencedTable,$referenceColumn=null){
		foreach($this->tables[$table]['links'] as $k=>$v){
			if($v['ft'] == $referencedTable){
				if(!$referenceColumn || $referenceColumn == $v['dc']){
					return $k;
				}
			}
		}
		Debug::toss('DbModel failed to find link',var_export(func_get_args(),1));
	}
	protected function columnKey($column,$table,$columns,$where=array(),$order=null,$limit=null){
		$sql = $this->select($table,$columns,$where,$order,$limit);
		return $this->db->columnKey($sql);
	}
}