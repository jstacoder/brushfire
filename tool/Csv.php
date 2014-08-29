<?
///deal with making and reading csv files
/**
Use Example:
	statitically
		$csv = new StringFile($csvText);
		$csv = new FileObject(FILEPATH);
		//get headers
		$positions = Csv::getCsvPositions($csv,array('destinationColumnName' => 'searchedForColumnName'));
		while($line = $csv->fgets()){
			$columns = Csv::getMappedColumns($line,$positions);
		}
	
	instancely
		$csv = new Csv(FILEPATH or TEXT,$delimiter);
		$csv->map(array('destinationColumnName' => 'searchedForColumnName'))
		while($columns = $csv->mGet()){
			
		}
	
	
*/

class Csv{
	function __construct($source,$delimiters=",\t"){
		if(is_file($source)){
			$this->source = new FileObject($source);
		}else{
			$this->source = new StringFile($source);
		}
		$this->delimiters = $delimiters;
	}
	function __call($methodName,$arguments){
		return call_user_func_array(array($this->source,$methodName),$arguments);
	}
	function map($findMap=null){
		$this->source->fseek(0);
		return $this->positions = self::getCsvPositions($this->source->gets(),$findMap,$this->delimiters);
	}
	//get columns
	function get(){
		if($line = $this->source->gets()){
			return self::parseLineColumns($line,$this->delimiters);
		}
	}
	//get columns and map them accordingly
	function mGet(){
		if($line = $this->source->gets()){
			return self::getMappedColumns($line,$this->positions,$this->delimiters);
		}
	}
	
	
	///maps the position of source columns with names equal to or similar to search columns to destination columns
	/**
	@param	source	array(position => columnName)
	@param	findMap	array(destinationColumn => searchColumn, ...)  searchColumn is what is compared against the source column
	@return	array(searchColumn => position,...)
	*/
	static function mapCsvColumns($source,$map){
		foreach($source as $i=>$v){
			$value = trim($v);
			foreach($map as $key=>$search){
				$qSearch = preg_quote($search);
				if(preg_match('@^'.$qSearch.'$@i',$value)){
					$positions[$key] = $i;
					//potentially, multiple columns many:one
					continue;
				}
				if(preg_match('@'.$qSearch.'@i',$value)){
					if(!isset($positions[$key])){
						$positions[$key] = $i;
					}
				}
			}
		}
		return $positions;
	}
	///extract the header column positions into an array of name to position
	/**
	@param	file	file object or string with the header column
	@param	delimiters	the delimiter characters used in the cvs
	@param	findMap	see mapCsvColumns paramter of same name
	@return	array(name=>position,...)
	*/
	static function getCsvPositions($line,$findMap=null,$delimiters=",\t"){
		$headerColumns = self::parseLineColumns($line,$delimiters);
		if(!$findMap){
			foreach($headerColumns as $column){
				$findMap[$column] = $column;
			}
		}
		return self::mapCsvColumns($headerColumns,$findMap);
	}
	///takes a line and figures out the extent of each column
	/**
	@param	string	the string representing the line in the csv
	@param	delimiterCharacters those which delimit
	@return	array(position => value)
	
	@note doesn't handle  quoted double quotes.   CSV quotes '"' by replacing with '"""'
	*/
	static function parseLineColumns($string,$delimiterCharacters=",\t"){
		$charCount = strlen($string);
		
		$delimiterCharacters = str_split($delimiterCharacters);
		$delimiters = array();
		foreach($delimiterCharacters as $char){
			$delimiters[$char] = true;
		}

		$inQuote = false;
		$startField = true;
		$fields = array();
		for($i=0,$f=-1;$i<$charCount;$i++){
			if($startField){
				$f++;
				$fields[$f] = '';
			}
			$quote = $string[$i] == '"' ? $string[$i] : false;
			//start of new field, and field is quoted
			if(!$inQuote && $quote && $startField){
				$inQuote = $quote;
				$startField = false;
				continue;
			}
			//encountered endquote
			elseif($inQuote && $quote == $inQuote){
				$inQuote = false;
				continue;
			}
			//encountered delimiter and not in a quote
			if(!$inQuote && $delimiters[$string[$i]]){
				$startField = true;
				continue;
			}
			
			//part of field
			$fields[$f] .= $string[$i];
			
			$startField = false;
		}
		return $fields;
	}
	static function getMappedColumns($line,$map,$delimiters=",\t"){
		$columns = self::parseLineColumns($line,$delimiters);
		return Arrays::map($map,$columns);
	}
}
