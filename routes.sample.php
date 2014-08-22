<?
/**
Format:
	each rule should be an array of at least 2 elements, but possibly 3 elements:
		1: the matching string
			the matching string matches against path
		2: the replacement string.  If regex flag is on, replacement string is a preg_replace replacement string excluding the delimiters.  Otherwise, the replacement string will replace the entire path.
		3: the flags
	
	flags can be combined with commas.  There are various flags:
		once: applies rule once, then ignores it the rest of the time
		file:last: last rule matched in the file.  Route does not parse any more rules in the containing file, but will parse rules in subsequent files
		loop:last is the last matched rule.  Route will just stop parsing rules after this.
		
		caseless: ingore capitalisation
		regex: applies regex pattern matching
			last-matched-rule regex-match-groups are saved to Route::$regexMatch
				Note, regex uses '(?<'.$gropuName.'>'.$pattern.')' for named matches
				Note, named regexes are extracted (see php "extract") into all control files.
	
*/

/** @file */
//load index control on paths ending with directory
$rules[] = array('/$','/index','regex');

//identify id part of path for inclusion in control
$rules[] = array('^/blog/(?<id>[0-9]+)','/blog/view/$1','loop:last');