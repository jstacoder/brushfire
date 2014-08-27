<?
namespace view;
class Field{
	static function phone($value){
		if(strlen($value) == 10){
			$areacode = substr($value,0,3);
			$part1 = substr($value,3,3);
			$part2 = substr($value,6);
			return '('.$areacode.') '.$part1.'-'.$part2;
		}
	}
	static function date(&$value){
		if($value && InputValidate::check('date',$value)){
			return (new \Time($value,$_ENV['timezone']))->setZone($_ENV['inOutTimezone'])->date();
		}
	}
	static function datetime($value){
		if($value && InputValidate::check('date',$value)){
			return (new \Time($value,$_ENV['timezone']))->setZone($_ENV['inOutTimezone'])->datetime();
		}
	}
	static function usaDate($value){
		return (new \Time($time,$_ENV['timezone']))->format('F j, Y, g:i a',$timezone);
	}
	static function conditionalBr2Nl($value){
		if(!preg_match('@<div|<p|<table@',$value)){
			$value = preg_replace('@<br ?/>@',"\n",$value);
		}
		return $value;
	}
	static function limit($text,$wordSize=35,$totalText=null){
		while(preg_match('@((?>[^\s]{'.$wordSize.'}))([^\s])@',$text)){
			$text = preg_replace('@((?>[^\s]{'.$wordSize.'}))([^\s])@','$1 $2',$text,1);
		}
		if($totalText && strlen($text) > $totalText){
			$text = '<span class="shortened" title="'.htmlspecialchars($text).'">'.htmlspecialchars(substr($text,0,$totalText)).'</span>';
		}
		return $text;
	}
}
