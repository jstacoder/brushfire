<?
/**
For data, there are a couple of considerations
	how to format the data for plain viewing
	how to format the data for in form viewing
	what form field to use for form viewing
	how to interpret formatted data on input
	
	examples
		is dollar amount
			display on form with $
			interpret input with $
			show on display with $
		is status
			use select for array statuses
			display mapped array status
			require status be in arrays
			
*/
namespace view;
use Control, Tool, Debug;
class Data{
	use \SingletonDefault;
	function __construct($model=null){
		$this->model = $model ? $model : Control::primary()->model;
		$this->control = Control::primary();
	}
	protected function form($inputs=null,$type='create'){
		return $this->formTemplate($this->inputLines($inputs),$type);
	}
	protected function formTemplate($content,$type){
		return '<form action="" method="post" data-addMessageContainers>
			<input type="hidden" name="_create" value=1/>
			<table class="standard standardInput">
				<tbody>'.$content.'</tbody>
				<tfooter>
					<tr>
						<td colspan=2><input type="submit" name="submit" value="'.ucwords($type).'"/></td>
					</tr>
				</tfooter>
			</table>
		</form>';
	}
	protected function inputLines($inputs=null){
		$inputs = $inputs ? $inputs : array_keys($this->model->info['columns']);
		foreach($inputs as $input){
			$form .= $this->inputLine($input);
		}
		return $form;
	}
	protected function inputLine($field,$column=null){
		if(!$column){
			$column = &$this->model->info['columns'][$field];
		}
		$column['name'] = $field;
		$column['displayName'] = $column['displayName'] ? $column['displayName'] : self::removeIdPart(Tool::capitalize($field));
		
		if($column['displayInputLine']){
			if(is_callable($column['displayInputLine'])){
				return call_user_func($column['displayInputLine'],$field,$column);
			}else{
				return $column['displayInputLine'];
			}
			
		}
		$input = $this->input($field,$column);
		if($input !== false){
			return $this->wrapFormLine($column,$input);
		}
	}
	protected function input($field, $column=null){
		if(!$column){
			$column = &$this->model->info['columns'][$field];
		}
		$column['name'] = $field;
		$column['displayName'] = $column['displayName'] ? $column['displayName'] : self::removeIdPart(Tool::capitalize($field));
		
		if($this->model->interdependency[$field]){
			$options['@data-dependee'] = $this->model->interdependency[$field]['lc'];
		}
		if($column['displayInput']){
			if(is_callable($column['displayInput'])){
				return call_user_func($column['displayInput'],$field,$column);
			}else{
				return $column['displayInput'];
			}
		}
		if($column['autoIncrement']){
			return htmlspecialchars($this->control->item[$field]);
		}
		if(in_array($column['type'],['date','datetime','timestamp'])){
			return Form::text($field,null,['@class'=>'datepicker']);
		}
		if($column['type'] == 'text' && (!$column['limit'] || $column['limit'] > 255)){
			return Form::textarea($field);
		}
		if($this->model->options[$field] || $this->model->interdependency[$field]){
			return Form::select($field,$this->model->options[$field],null,$options);
		}
		return Form::text($field,null,$options);
	}
	static function removeIdPart($name){
		$parts = explode(' ',$name);
		if(count($parts) > 1 && strtolower(end($parts)) == 'id'){
			array_pop($parts);
			return implode(' ',$parts);
		}
		return $name;
	}
	protected function wrapFormLine($column,$content){
		return $this->wrapFormLineTemplate($column['name'],htmlspecialchars($column['displayName']),$content);
	}
	protected function wrapFormLineTemplate($field,$title,$content){
		return '
<tr>
	<td for="'.$field.'">'.$title.'</td>
	<td>'.$content.'</td>
</tr>';
	}
	
	/**
	special formatting
		date shown as a date rendered in by js for localisation
		dollar amount shown with '$' sign
		percentage shown with '%' sign
	*/
	protected function item($fields=null){
		return $this->itemTemplate($this->fieldLines($fields));
	}
	protected function itemTemplate($content){
		return '<form action="" method="post" data-addMessageContainers>
			<input type="hidden" name="_create" value=1/>
			<table class="standard">
				<tbody>'.$content.'</tbody>
			</table>
		</form>';
	}
	protected function fieldLines($fields=null){
		$fields = $fields ? $fields : array_keys($this->model->info['columns']);
		foreach($fields as $field){
			$html .= $this->fieldLine($field);
		}
		return $html;
	}
	protected function fieldLine($field,$column=null){
		if(!$column){
			$column = &$this->model->info['columns'][$field];
		}
		$column['name'] = $field;
		$column['displayName'] = $column['displayName'] ? $column['displayName'] : self::removeIdPart(Tool::capitalize($field));
		if($column['displayFieldLine']){
			if(is_callable($column['displayFieldLine'])){
				return call_user_func($column['displayFieldLine'],$field,$column);
			}else{
				return $column['displayFieldLine'];
			}
			
		}
		$fieldData = $this->field($field,$column);
		if($fieldData !== false){
			return $this->wrapFormLine($column,$fieldData);
		}
	}
	protected function field($field, $column=null){
		if(!$column){
			$column = &$this->model->info['columns'][$field];
		}
		$column['name'] = $field;
		$column['displayName'] = $column['displayName'] ? $column['displayName'] : self::removeIdPart(Tool::capitalize($field));
		
		if($column['displayItem']){
			if(is_callable($column['displayItem'])){
				return call_user_func($column['displayItem'],$field,$column);
			}else{
				return $column['displayItem'];
			}
		}
		
		if(in_array($column['type'],['date','datetime','timestamp'])){
			return \view\Format::_datetime($this->control->item[$field]);
		}
		if($this->model->info['out'][$field] && isset($this->control->item[$field.'_name'])){
			return '<span data-id="'.$this->control->item[$field].'" data-table="'.$this->model->info['out'][$field].'">'.htmlspecialchars($this->control->item[$field.'_name']).'</span>';
		}
		return htmlspecialchars($this->control->item[$field]);
	}
	protected function wrapDataLine($column,$content){
		return $this->wrapDataLineTemplate($column['name'],htmlspecialchars($column['displayName']),$content);
	}
	protected function wrapDataLineTemplate($field,$title,$content){
		return '
<tr>
	<td>'.$title.'</td>
	<td>'.$content.'</td>
</tr>';
	}
	
	protected function items($fields=null){
		$fields = $fields ? $fields : array_keys($this->model->info['columns']);
		return $this->itemsTemplate($this->itemsLines($fields));
	}
	protected function itemsHeader($fields){
		foreach($fields as $field){
			$column = &$this->model->info['columns'][$field];
			$column['name'] = $field;
			$column['displayName'] = $column['displayName'] ? $column['displayName'] : self::removeIdPart(Tool::capitalize($field));
			$header .= '<th data-field="" data-title>'.$field.'</th>';
		}
	}
	protected function itemsTemplate($header,$content){
		return '<form action="" method="post" data-addMessageContainers>
			<input type="hidden" name="_create" value=1/>
			<table class="standard">
				<tbody>'.$content.'</tbody>
			</table>
		</form>';
	}
	protected function itemsLines($fields){
		foreach($this->control->items as $item){
			$html .= $this->itemLine($item);
		}
		return $html;
	}
	protected function itemLine($field,$column=null){
		if(!$column){
			$column = &$this->model->info['columns'][$field];
		}
		$column['name'] = $field;
		$column['displayName'] = $column['displayName'] ? $column['displayName'] : self::removeIdPart(Tool::capitalize($field));
		if($column['displayFieldLine']){
			if(is_callable($column['displayFieldLine'])){
				return call_user_func($column['displayFieldLine'],$field,$column);
			}else{
				return $column['displayFieldLine'];
			}
			
		}
		$fieldData = $this->field($field,$column);
		if($fieldData !== false){
			return $this->wrapFormLine($column,$fieldData);
		}
	}
	protected function wrapItemLine($column,$content){
		return $this->wrapDataLineTemplate($column['name'],htmlspecialchars($column['displayName']),$content);
	}
	protected function wrapItemLineTemplate($field,$title,$content){
		return '
<tr>
	<td>'.$title.'</td>
	<td>'.$content.'</td>
</tr>';
	}
}