<?
class Model{
	public $relations = [];
	function __construct(){
		$this->name = $this->name ? $this->name : array_pop(explode('\\',get_called_class()));
		$this->info = DbModel::primary()->tables[$this->name];
		$this->in = Control::primary()->in;
		$this->getNameColumn();
		
		//resolve interdepencies
		foreach((array)$this->info['out'] as $column=>$table){
			$tableModel = $this->linkedTable($table);
			foreach((array)$tableModel->relations['m2o'] as $m2o){
				$parentTable = $tableModel->info['out'][$m2o];
				$localColumnName = array_search($parentTable,$this->info['out']);
				if($localColumnName){
					$this->interdependency[$column] = ['lc'=>$localColumnName,'fc'=>$m2o];
				}
			}
		}
	}
	function getNameColumn(){
		if(!$this->nameColumn){
			if($this->info['columns']['name']){
				$this->nameColumn = 'name';
			}elseif($this->info['columns']['title']){
				$this->nameColumn = 'title';
			}elseif($this->info['columns']['first_name']){
				if($this->info['columns']['last_name']){
					$this->nameColumn = 'concat(first_name,\' \',last_name) full_name';
				}else{
					$this->nameColumn = 'first_name';
				}
			}elseif($this->info['columns']['last_name']){
				$this->nameColumn = 'last_name';
			}else{
				//try to find a keyed varchar column
				
				//just find a small varchar column
				
				//just use the id
			}
		}
		return $this->nameColumn;
	}
	function getOptions(){
		foreach($this->info['out'] as $column=>$table){
			if($this->interdependency[$column]){
				$this->getSubOptions($column);
			}else{
				$tableModel = $this->linkedTable($table);
				$this->options[$column] = Db::columnKey('id',$table,null,'id,'.Db::quoteIdentity($tableModel->getNameColumn()));
			}
		}
	}
	function getSubOptions($column){
		if($this->interdependency[$column]){
			
		}
	}
	
	//get the row, along with the name of rows linked to the row
	/**
	primary table aliased as 't1'
	connected tables aliased as fc
	default select connects name-like column as fc'_name'
	*/
	function read($selects=null){
		$joins = [];
		if(!$selects){
			$selects[] = 't1.*';
			$doSelects = true;
		}
		foreach((array)$this->info['out'] as $column=>$table){
			$tableModel = $this->linkedTable($table);
			$newTableName = Db::quoteIdentity($column);
			$joins[] = 'left join '.Db::quoteIdentity($table).' as '.$newTableName.' on t1.'.Db::quoteIdentity($column).' = '.$newTableName.'.id';
			if($doSelects){
				$selects[] = $newTableName.'.'.Db::quoteIdentity($tableModel->getNameColumn()).' as '.Db::quoteIdentity($column.'_name');
			}
		}
		return Db::row('select '.implode(',',$selects).' 
			from '.Db::quoteIdentity($this->name).' as t1 '.implode("\n",$joins).'
			where t1.id = '.Control::primary()->id);
	}
	public $tables = [];
	function linkedTable($name){
		if(!$this->tables[$name]){
			$model = '\model\\'.$name;
			$this->tables[$name] = new $model;
		}
		return $this->tables[$name];
	}
}