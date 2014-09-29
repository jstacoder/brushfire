<?
class Model{
	public $relations = [];
	///[$localC]
	public $interdependency = [];
	function __construct(){
		$this->name = $this->name ? $this->name : array_pop(explode('\\',get_called_class()));
		$this->info = DbModel::primary()->tables[$this->name];
		$this->control = Control::primary();
		$this->in = &$this->control->in;
		$this->getNameColumn();
		
		//resolve interdepencies
		foreach((array)$this->info['out'] as $column=>$table){
			$tableModel = $this->tableModel($table);
			foreach((array)$tableModel->relations['m2o'] as $m2oColumn){
				$parentTable = $tableModel->info['out'][$m2oColumn];
				$localParentColumn = array_search($parentTable,$this->info['out']);//there is a m2o relation, and the parent table is in the current table - so there is an interdependency
				if($localParentColumn){
					$this->interdependency[$column] = ['lc'=>$localParentColumn,'fc'=>$m2oColumn];
				}
			}
		}
		
		foreach((array)$columnHandlers as $column=>$handler){
			$this->setColumnHandler($column,$handler);
		}
	}
	function setColumnHandler($column,$handler){
		call_user_func([$this,'handle_'.$handler],$column);
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
				$this->options[$column] = $this->getSubOption($column);
			}else{
				$tableModel = $this->tableModel($table);
				$this->options[$column] = Db::columnKey('id',$table,null,'id,'.Db::quoteIdentity($tableModel->getNameColumn()).' name');
			}
		}
	}
	function getSubOptions(){
		foreach($this->info['out'] as $column=>$table){
			if($this->interdependency[$column]){
				$this->options[$column] = $this->getSubOption($column);
			}
		}
	}
	function getSubOption($column){
		if($this->interdependency[$column]){
			$lcParent = $this->interdependency[$column]['lc'];
			if($this->in[$lcParent]){
				$parentTable = $this->info['out'][$lcParent];
				$childTable = $this->info['out'][$column];
				$tableModel = $this->tableModel($childTable);
				$fcParent = $this->interdependency[$column]['lc'];
				return Db::columnKey('id','select id, '.Db::quoteIdentity($tableModel->getNameColumn()).' as name
					from '.$childTable.'
					where '.Db::quoteIdentity($fcParent).' = '.Db::quote($this->in[$lcParent]).'
						or '.Db::quoteIdentity($fcParent).' is null');
			}
		}
	}
	function handleSuboptionsRequest(){
		if($this->in['_getSubOptions']){
			$view = View::primary();
			$this->getSubOptions();
			$view->json['options'] = $this->options;
			$view->endStdJson();
		}
	}
	//does the filter and validate
	function validate(){
		$this->addValidation();
		return (new CrudModel($this->name))->validate();
	}
	function addValidation(){
		Hook::add('crudValidateInput',[$this,'validateInterdependentKeys'],['deleteAfter'=>1]);
	}
	function create(){
		$this->addValidation();
		
		if($id = (new CrudModel($this->name))->create()){
			return $id;
		}
	}
	function update(){
		if(!$this->control->id){
			\control\Common::reqId();
		}
		$this->addValidation();
		
		if((new CrudModel($this->name))->update()){
			return true;
		}
	}
	
	
	//get the row, along with the name of rows linked to the row
	/**
	primary table aliased as 't1'
	connected tables aliased as fc
	default select connects name-like column as fc'_name'
	*/
	function read($selects=null,$id=null){
		$id = $id ? $id : Control::primary()->id;
		$sql = $this->readSql($selects);
		return Db::row($sql.' where t1.id = '.$id);
	}
	function readSql($selects=null){
		$joins = [];
		if(!$selects){
			$selects[] = 't1.*';
			$doSelects = true;
		}
		foreach((array)$this->info['out'] as $column=>$table){
			$tableModel = $this->tableModel($table);
			$newTableName = Db::quoteIdentity($column);
			$joins[] = 'left join '.Db::quoteIdentity($table).' as '.$newTableName.' on t1.'.Db::quoteIdentity($column).' = '.$newTableName.'.id';
			if($doSelects){
				$selects[] = $newTableName.'.'.Db::quoteIdentity($tableModel->getNameColumn()).' as '.Db::quoteIdentity($column.'_name');
			}
		}
		return 'select '.implode(',',$selects).'
			from '.Db::quoteIdentity($this->name).' as t1 '.implode("\n",$joins).' ';
	}
	public $tables = [];
	///get the table model and store it in local array
	function tableModel($name){
		if(!$this->tables[$name]){
			$model = '\model\\'.$name;
			$this->tables[$name] = new $model;
		}
		return $this->tables[$name];
	}
	
	///validates subcategories and such
	function validateInterdependentKeys(){
		foreach((array)$this->info['out'] as $column=>$table){
			if($this->in[$column]){
				if(!Db::check($table,$this->in[$column])){
					$this->control->error('Unmatching key: '.$column);
				}
				if($interdependency = $this->interdependency[$column]){
					$dependencyMet = Db::row('select 1 
						from '.Db::quoteIdentity($table).'
						where id = '.$this->in[$column].'
							and ('.Db::quoteIdentity($interdependency['fc']).' = '.Db::quote($this->in[$interdependency['lc']]).'
								or '.Db::quoteIdentity($interdependency['fc']).' is null)');
					if(!$dependencyMet){
						$this->control->error('Interdependency not met: '.$column);
					}
				}
			}
		}
	}
	
//+	column handlers {
	function handle_dollar($column){
		Hook::add('crudValidateInput',[$this,'validateInterdependentKeys'],['deleteAfter'=>1]);
		\view\Form::text($column,null,['@data-dollar'=>true]);
		\view\Format::_dollar($this->control->item[$column]);
		
		$this->info['columns'][$column]['displayInput'];
		$this->info['columns'][$column]['displayData'];
	}
//+	}
}