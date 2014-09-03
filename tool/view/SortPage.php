<?
namespace view;
//since there is integration with display-page level activity, this is a tool of the view
class SortPage{
	/**
	The use of the sort and page functions can vary in how the result is to interact with the rest of the 
		logic and the UI, so the result present provides as much information as might be necessary
	*/
	static function sort($allowed,$sort=null,$default=null,$quoteColumns=true){
		if(!$sort){
			$control = \Control::primary();
			$sort = $control->in['_sort'];
		}
		$sorts = explode(',',$sort);
		foreach($sorts as $sort){
			$order = substr($sort,0,1);
			if($order == '-'){
				$order = ' DESC';
				$field = substr($sort,1);
			}else{
				if($order == '+'){
					$field = substr($sort,1);
				}else{
					$field = $sort;
				}
				$order = ' ASC';
			}
			if(in_array($field,$allowed)){
				if($quoteColumns){
					$field = \Db::quoteIdentity($field);
				}
				$orders[]  = $field.$order;
				$usedSorts[] = $sort;
			}
		}
		if(!$orders){
			if($default){
				return self::sort($allowed,$default,null,$quoteColumns);
			}
		}
		return array(
				'sql' => ($orders ? ' ORDER BY '.implode(', ',$orders).' ' : ' '),
				'orders' => $orders,
				'sort' => ($usedSorts ? implode(',',$usedSorts) : [])
			);
	}
	static function page($sql,$pageNumber=null,$pageBy=50,$max=null){
		$pageBy = $pageBy ? $pageBy : 50;
		if($pageNumber === null){
			global $page;
			$pageNumber = (int)$page->in['_page'] - 1;
		}
		$pageNumber = $pageNumber > 0 ? $pageNumber : 0;
		
		$offset = $pageBy * $pageNumber;
		$sql .= "\nLIMIT ".$offset.', '.$pageBy;
		list($count,$rows) = \Db::countAndRows(($max ? $max + 1 : null),$sql);
		$top = $count;
		if($max && $count > $max){
			$top = $max;
		}
		$pages = ceil($top/$pageBy);
		return array(
				'rows' => $rows,
				'info' => array(
					'count' => $count,
					'pages' => $pages,
					'top' => $top,
					'page' => ($pageNumber + 1)
				)
			);
	}
}
