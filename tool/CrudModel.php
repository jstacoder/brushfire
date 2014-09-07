<?
/**
	For standardized validation, inserting, deleting, and updating.  Based on a db table, will handle validation and filtering based on column attributes such as type.
	Will default to use all table columns, and select from the input that which is avaible (set), but can be modified to use only certain columns.  
*/
class CrudModel{
	function __construct(){
		if(!$control){
			$control = Control::primary();
		}
		$this->control = $control;
		$this->lt = $control->lt;
		$this->db = $this->control->db;
	}
	function columns($table=null){
		$table = $table ? $table : $this->lt->model['table'];
		if(!$this->columns[$table]){
			$this->columns[$table] = Db::columnsInfo($table);
		}
		return $this->columns[$table];
	}
	//determine various filters and validators based on database columns
	function handleColumns(){
		$columns = self::columns($this->lt->model['table']);
		$usedColumns = $this->lt->model['columns'] ? $this->lt->model['columns'] : array_keys($columns);
		
		//create validation and deal with special columns
		foreach($usedColumns as $column){
			//special columns
			if($column == 'created'){
				$this->control->in[$column] = new Time('now',$_ENV['timezone']);
			}elseif($column == 'updated'){
				$this->control->in[$column] = new Time('now',$_ENV['timezone']);
			}elseif($column == 'id'){
				$validaters[$column][] = 'f:toString';
				$validaters[$column][] = '?!v:filled';
				$validaters[$column][] = '!v:existsInTable|'.$this->lt->model['table'];
			}else{
				$validaters[$column][] = 'f:toString';
				if(!$columns[$column]['nullable']){
					//column must be present
					$validaters[$column][] = '!v:exists';
				}else{
					//for nullable columns, empty inputs (0 character strings) are null
					$validaters[$column][] = array('f:toDefault',null);
					
					//column may not be present.  Only validate if present
					$validaters[$column][] = '?!v:filled';
				}
				switch($columns[$column]['type']){
					case 'datetime':
					case 'timestamp':
						$validaters[$column][] = '!v:date';
						$validaters[$column][] = 'f:toDatetime';
					break;
					case 'date':
						$validaters[$column][] = '!v:date';
						$validaters[$column][] = 'f:toDate';
					break;
					case 'text':
						if($columns[$column]['limit']){
							$validaters[$column][] = '!v:lengthRange|0,'.$columns[$column]['limit'][0];
						}
					break;
					case 'int':
						$validaters[$column][] = 'f:trim';
						$validaters[$column][] = '!v:isInteger';
					break;
					case 'decimal':
					case 'float':
						$validaters[$column][] = 'f:trim';
						$validaters[$column][] = '!v:isFloat';
					break;
				}
			}
		}
		$this->usedColumns = $usedColumns;
		$this->validaters = $validaters;
	}
	
	function validate(){
		$this->handleColumns();
		if(method_exists($this->lt,'validate')){
			$this->lt->validate();
		}
		//Crud standard validaters come after due to them being just the requisite validaters for entering db; input might be changed to fit requisite by Page validaters.
		if($this->validaters){
			$this->control->filterAndValidate($this->validaters);
		}
		return !$this->control->errors();
	}
	
	//only run db changer functions if $this->lt->model['table'] available
	function create(){
		if($this->validate()){
			//since used keys can be dynamically generated, if the input does not contain a matching key, do not set it on the insert or update.  
			// However, if the key exists but the value is null, the update and insert should include this key with value null
			$this->insert = Arrays::extract($this->usedColumns,$this->control->in,$x=null,false);
			unset($this->insert['id']);
			$this->lt->insert = $this->insert;
			$this->control->id = $id = Db::insert($this->lt->model['table'],$this->insert);
			return $id;
		}
	}
	function update(){
		if($this->validate()){
			$this->update = Arrays::extract($this->usedColumns,$this->control->in,$x=null,false);
			unset($this->update['id']);
			$this->lt->update = $this->update;
			Db::update($this->lt->model['table'],$this->update,$this->control->id);
			return true;
		}
	}
	///standardized to return id
	function delete(){
		if(Db::delete($this->lt->model['table'],$this->control->id)){
			return $this->control->id;
		}
	}
	function read(){
		if($this->lt->item = Db::row($this->lt->model['table'],$this->control->id)){
			return true;
		}
		if($_ENV['CrudbadIdCallback']){
			call_user_func($_ENV['CrudbadIdCallback']);
		}
		
	}
}
