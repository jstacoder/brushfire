<?
///for handling order_in order_out indexed tree (for things like nested comments)
/**
@note since the moving operations would cause intermediary order_in overlaps, nodes being moved are 
@note trivial: can reproduce functionality with order_out column removed using order_depth to find next-non-child node (to get range), but this is impractical
*/
class DbTree{
	use SingletonDefault;
	public $db;
	public $baseWhere = array();
	function __construct($table, $fkColumn=null, $db=null){
		if(!$db){
			$db = Db::$primary;
		}
		$this->db = $db;
		$this->table = $table;
		$this->fkColumn = $fkColumn;
		$this->baseWhere = $this->fkColumn ? [$this->fkColumn => null] : array();
	}
	static $functionExceptions = ['deleteTree'];
	///tree operations need to be atomic, and mutex
	function __call($fnName,$args){
		if(in_array($fnName,self::$functionExceptions)){
			return call_user_func_array(array($this,$fnName),$args);
		}
		
		//get node (since all private functions require it)
		$node = $args[0] = $this->getNode($args[0]);
		if($this->fkColumn){
			$this->baseWhere[$this->fkColumn] = $node[$this->fkColumn];
		}
		
		//get foreign key to localise lock
		$lockName = 'DbTree-'.$this->table;
		if($this->fkColumn){
			$lockName .= '-'.$node[$this->fkColumn];
		}
		

		$this->__methodExists($fnName);
		Lock::req($lockName,2);
		$this->db->db->beginTransaction();
		try{
			$return = call_user_func_array(array($this,$fnName),$args);
			$this->db->db->commit();
		}catch (Exception $e){
			$this->db->db->rollBack();
			throw $e;
		}finally{
			Lock::off($lockName);
		}
		return $return;
	}
	function deleteTree($fk=null){
		$this->db->delete($this->table, $this->baseWhere + ['"'=>'1=1']);
	}
	///prepend (optionally create)
	private function prepend($node,$parent=[]){
		$parent = $this->getNode($parent);
		$position['order_in'] = $parent['order_in'] + 1;
		$position['order_depth'] = $parent['order_depth'] + 1;
		$position['id__parent'] = $parent['id'];
		return $this->insert($node,$position);
	}
	///append (optionally create)
	private function append($node,$parent=[]){
		$parent = $this->getNode($parent);
		if(!$parent['order_out']){//no parent, add to end of top level
			$lastOrderIn = $this->db->row($this->table,$this->baseWhere,'order_out','order_in desc');
			$position['order_in'] = $lastOrderIn + 1;
			$position['order_depth'] = 1;
			$position['id__parent'] = 0;
		}elseif($parent['order_out'] - $parent['order_in'] > 1){//parent already has children
			$position['order_in'] = $parent['order_out'];
			$position['order_depth'] = $parent['order_depth'] + 1;
			$position['id__parent'] = $parent['id'];
		}else{
			return $this->prepend($node,$parent);
		}
		
		return $this->insert($node,$position);
	}
	
	private function insert($node,$position=[]){
		if($node['order_in']){//node is being moved
			$this->expand($node,$position['order_in']);
			if($node['order_in'] >= $position['order_in']){
				$additionalAdjustment = $node['order_out'] - $node['order_in'] + 1;
			}
			$this->adjust($node,$position['order_in'],$position['order_depth'],$additionalAdjustment);
			$this->collapse($node);
			$this->db->update($this->table,['id__parent'=>$position['id__parent']],$node['id']);
		}else{//node is being inserted
			$this->expand($node,$position['order_in']);
			$node['order_in'] = $position['order_in'];
			$node['order_out'] = $node['order_in'] + 1;
			$node['order_depth'] = $position['order_depth'];
			$node['id__parent'] = $position['id__parent'];
			return $this->db->insert($this->table,$node);
		}
	}
	
	protected function getNode($node){
		if($node && Tool::isInt($node)){
			return $this->db->row($this->table,$node,Arrays::implode(',',['id','order_in','order_out','order_depth',$this->fkColumn]));
		}
		return (array)$node;
	}
	protected function getParent($node){
		return $this->db->row($this->table,$this->baseWhere + ['id'=>$node['id__parent']]);
	}
	protected function delete($node){
		$this->db->delete($this->table,$this->baseWhere + ['order_in?>='=>$node['order_in'],'order_in?<'=>$node['order_out']]);
		$this->collapse($node,$node['id__parent']);
	}
	///collapse order after node deletion or move
	protected function collapse($node){
		$adjustment = $node['order_out'] - $node['order_in'] + 1;
		$this->db->update($this->table,
			[':order_in'=> 'order_in - '.$adjustment],
			$this->baseWhere + ['order_in?>'=>$node['order_out']]);
		$this->db->update($this->table,
			[':order_out'=> 'order_out - '.$adjustment],
			$this->baseWhere + ['order_out?>'=>$node['order_out']]);
		
	}
	///expand order (>=order_in) for after node insert or move
	/**@note  */
	function expand($node,$targetIn){
		$adjustment = $node['order_out'] ? $node['order_out'] - $node['order_in'] + 1 : 2;
		//update following nodes
		$this->db->update($this->table,
			[':order_in'=> 'order_in + '.$adjustment], $this->baseWhere + ['order_in?>='=>$targetIn]);
		//update containing and following nodes
		$this->db->update($this->table,
			[':order_out'=> 'order_out + '.$adjustment], $this->baseWhere + ['order_out?>='=>$targetIn]);
	}
	///adjust node and children order_in after move
	/**
	@note	additionalAdjustment due to overlap may caused on expand if move to higher order_in
	*/
	function adjust($node,$orderIn,$depth,$additionalAdjustment=false){
		$orderAdjustment = $orderIn - $node['order_in'];
		if($additionalAdjustment){
			$node['order_in'] += $additionalAdjustment;
			$node['order_out'] += $additionalAdjustment;
			$orderAdjustment -= $additionalAdjustment;//moving backwards, so effectively increases |x|
		}
		$depthAdjustment = $depth - $node['order_depth'];
		$this->db->update($this->table,
			[':order_in'=> 'order_in + '.$orderAdjustment,
				':order_out'=> 'order_out + '.$orderAdjustment,
				':order_depth' => 'order_depth + '.$depthAdjustment],
			$this->baseWhere + ['order_in?>='=>$node['order_in'], 'order_in?<='=>$node['order_out']]);
	}
	
	
	protected function parents($node,$columns=['id','order_in','order_out','order_depth']){
		if(is_array($columns)){
			$columns = array_map(function($value){return 't1.'.$value;},$columns);
			$columns = implode(', ',array_map([$this->db,'quoteIdentity'],$columns));
		}
		
		$joinWhere = implode("\n\tAND ",$this->db->ktvf($this->baseWhere + [':t2.order_in' => 't1.order_in']));
		$where = $this->db->where($this->baseWhere + ['order_in?<' => $node['order_in'],'order_depth?<'=>$node['order_depth']]);
		return $this->db->rows('select '.$columns.' 
			from '.$this->db->quoteIdentity($this->table).' t1
				inner join (
					select max(order_in) order_in
					from '.$this->db->quoteIdentity($this->table).
					$where.'
					group by order_depth
				) t2 on '.$joinWhere.'
			order by t1.order_depth desc');
		
	}
	protected function children($node=null,$columns=['id','order_in','order_out','order_depth']){
		$where = $node ? ['order_in?>' => $node['order_in'],'order_out?<'=>$node['order_out']] : [];
		return $this->db->rows($this->table,
			$this->baseWhere + $where,
			$columns);
	}
/*
//+ order_in + order_depth only functions {
	protected function children($node,$columns='*'){
		list($node,$child,$where) = $this->fathom($node);
		return Db::rows($this->table,$where,$columns);
	}
	///get boundaries and child where
	protected function fathom($node){
		$node = $this->getNode($node);
		$child = $this->lastChild($node);

		$where = $this->baseWhere + ['order_in?>'=>$node['order_in']];
		if($child){
			$where['order_in?<='] = $child['order_in'];
		}
		return [$node,$where];
	}
	///get next node not child of provided
	protected function next($node){
		//get child range
		return Db::row($this->table,
			$this->baseWhere + ['order_in?>'=>$node['order_in'], 'order_depth?<='=>$node['order_depth']],
			'id, order_in, order_depth','order_in asc');
	}
	///highest order_in child
	function lastChild($node){
		$next = $this->next($node);
		if($next){
			$child = $this->db->row($this->table,$this->baseWhere + ['order_in' => $next['order_in'] - 1],
				'id, order_in, order_depth');
			if($child['order_in'] != $node['order_in']){
				return $child;
			}
		}else{
			$child = $this->db->row($this->table,$this->baseWhere + ['order_in?>' => $node['order_in']],
				'id, order_in, order_depth','order_in desc');
		}
	}
//+ }
*/
}
