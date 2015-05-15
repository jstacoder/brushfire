<?
/**
	For standardized validation, inserting, deleting, and updating.  Based on a db table, will handle validation and filtering based on column attributes such as type.
	Will default to use all table columns, and select from the input that is available, but can be modified to use only certain columns.  
*/
class CrudModel{
	function __construct($table,$selectedColumns=null){
		$control = Control::primary();
		$this->control = $control;
		$this->lt = $this->control->lt;
		
		$this->table = $table;
		$this->selectedColumns = $selectedColumns;
	}
	function columns($table=null){
		$table = $table ? $table : $this->table;
		if(!$this->columns[$table]){
			$this->columns[$table] = Db::columnsInfo($table);
		}
		return $this->columns[$table];
	}
	//determine various filters and validators based on database columns
	function handleColumns(){
		$columns = self::columns();
		if($this->selectedColumns){
			$columns = Arrays::extract($this->selectedColumns,$columns,$x=null,false);
		}
		if($this->action == 'update'){
			$columns = Arrays::extract(array_keys($this->control->in),$columns,$x=null,false);
		}
		
		
		$validaters = $this->getColumnValidations($columns);
		
		if($this->action){
			$validaters[''][] = 'v.checkUniqueKeys|'.$this->table.';'.$this->action;
		}
		
		$this->usedColumns = array_keys($columns);
		$this->validaters = $validaters;
	}
	function getColumnValidations($columns){
		//create validation and deal with special columns
		foreach($columns as $column=>$info){
			//special columns
			if($column == 'created'){
				$this->control->in[$column] = new Time('now',$_ENV['timezone']);
			}elseif($column == 'updated'){
				$this->control->in[$column] = new Time('now',$_ENV['timezone']);
			}elseif($column == 'id'){
				$validaters[$column][] = 'f.toString';
				$validaters[$column][] = '?!v.filled';
				$validaters[$column][] = '!v.inTable|'.$this->table;
			}else{
				$validaters[$column][] = 'f.toString';
				if(!$info['nullable']){
					if($info['default'] === null){
						//column must be present
						$validaters[$column][] = '!v.exists';
					}else{
						$validaters[$column][] = ['f.unsetMissing'];
						$validaters[$column][] = ['?!v.filled'];
					}
				}else{
					//for nullable columns, empty inputs (0 character strings) are null
					$validaters[$column][] = array('f.toDefault',null);
					
					//column may not be present.  Only validate if present
					$validaters[$column][] = '?!v.filled';
				}
				switch($info['type']){
					case 'datetime':
					case 'timestamp':
						$validaters[$column][] = '!v.date';
						$validaters[$column][] = 'f.datetime';
					break;
					case 'date':
						$validaters[$column][] = '!v.date';
						$validaters[$column][] = 'f.toDate';
					break;
					case 'text':
						if($info['limit']){
							$validaters[$column][] = '!v.lengthRange|0;'.$info['limit'];
						}
					break;
					case 'int':
						$validaters[$column][] = 'f.trim';
						$validaters[$column][] = '!v.isInteger';
					break;
					case 'decimal':
					case 'float':
						$validaters[$column][] = 'f.trim';
						$validaters[$column][] = '!v.isFloat';
					break;
				}
			}
		}
		return $validaters;
	}
	
	function validate(){
		$this->handleColumns();
		if($ltHasValidation = method_exists($this->lt,'validate')){
			$this->lt->validate();
		}
		//only apply additional validators if no error to avoid duplicate errors and to avoid running code relying on no errors
		if(!$this->control->hasError()){
			//run any validation from arbitrary tools
			Hook::run('crudValidateInput');
			
			if(!$this->control->hasError()){
				//run default validation
				if($this->validaters){
					if($ltHasValidation){
						$this->control->filterAndValidate($this->validaters);
					}else{
						$this->control->validate($this->validaters);
					}
				}
			}
		}
		return !$this->control->hasError();
	}
	
	public $action;
	
	///@only call $this->table available
	function create(){
		$this->action = 'create';
		if($this->validate()){
			Hook::run('crudPreCreate');
			$return = $this->doCreate($this->usedColumns, $this->table);
			Hook::run('crudPostCreate');
			return $return;
		}
	}
	///extract fields from input and do insert on table
	function doCreate($columns,$table){
		//include nulls, but don't force keys
		$this->insert = Arrays::extract($columns,$this->control->in,$x=null,false);
		unset($this->insert['id']);
		$this->lt->insert = $this->insert;
		$this->control->id = $id = Db::insert($table,$this->insert);
		return $id;
	}
	function update(){
		$this->action = 'update';
		if($this->validate()){
			Hook::run('crudPreUpdate');
			$this->doUpdate($this->usedColumns, $this->table);
			Hook::run('crudPostUpdate');
			return true;
		}
	}
	function  doUpdate($columns,$table){
		$this->update = Arrays::extract($columns,$this->control->in,$x=null,false);
		unset($this->update['id']);
		$this->lt->update = $this->update;
		Db::update($table,$this->update,$this->control->id);
		return true;
	}
	///standardized to return id
	function delete(){
		if(Db::delete($this->table,$this->control->id)){
			return $this->control->id;
		}
	}
	function read(){
		if($this->control->item = Db::row($this->table,$this->control->id)){
			return true;
		}
		if($_ENV['CrudbadIdCallback']){
			call_user_func($_ENV['CrudbadIdCallback']);
		}
		
	}
}
