<?
///Information of how rules.php should be formatted and some samples
/**
Format:
	- each rule should be an array of at least 2 elements, but possibly 3 elements
	- the first element is the matching string
	- the second element is the replacement string.  If regex flag is on, replacement string is a preg_replace replacement string
	- the third element is the flags
	- flags can be combined with commas.  There are various flags:
		- ignore: ignores rule on all following runs
		- nextFile: bypasses all the rest of the rules in the file and parses the next file before parsing the "current" file again
		- last: is the last rule run for the file.  Route does not parse any more rules in the containing file, but will parse rules in subsequent files
		- veryLast: is the last rule run for all files; Route will just stop parsing
		- insensitive: match is case insensitive
		- token: token based; match is against current token.  Replacement argument still replaces entire url
		- regex: applies regex pattern matching
	
*/

/** @file */
$rules[] = array('^$','blogs/post','regex,insensitive');
$rules[] = array('documentation','doc','token');