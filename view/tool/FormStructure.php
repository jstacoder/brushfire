<?php
class FormStructure{
	//Form method names and FormStructure method names don't conflict, so assume non FormStructure method names are Form method names, and encapsulate in formStructure where first argument is display param
	static function __callStatic($method,$arguments){
		$display = array_shift($arguments);
		$input = call_user_func_array(array('Form',$method),$arguments);
		return self::fieldColumns($arguments[0],$display,$input);
		
	}
	static function make($field){
		$input = call_user_func_array(array('Form',$field['form']),$arguments);
		return self::fieldColumns($arguments[0],$display,$input);
	}
	static function fieldsColumns($fields){
		$html = '';
		foreach($fields as $field){
			$input = call_user_func_array(array('Form',$field[2]),$field['arguments']);
			$formFieldPart = self::fieldColumns($field[0],$field[1],$input);
			$html .= '<tr>
					'.$formFieldPart.'
				</tr>';
		}
		return $html;
	}
	
	static function fieldColumns($name,$display,$input,$notes=false){
		return '<td data-fieldDisplay="'.$name.'" data-fieldContainer="'.$name.'"'.($notes ? ' title="'.htmlspecialchars($notes).'"' : null).'><span>'.
				$display.
			'</span></td>
			<td data-fieldContainer="'.$name.'">'.
				$input
			.'</td>';
	}
}