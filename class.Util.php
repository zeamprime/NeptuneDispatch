<?php
/**	(c) 2009-2015 Everett Morse
*	@author Everett Morse - www.neptic.com
*	
*	A collection of useful methods.  They are all static methods on the Util class so they can be
*	used from anywhere without being simple functions in the global scope.
*	
*	Notes:
*	- If the name "Util" conflicts with anything, you can rename it (and global search/replace all
*	  of my code %s/Util::/NepticUtil::/g).  Since PHP namespaces are still too new, I didn't use 
*	  them.
*/



class Util
{
	
	/**
	 * Get the input value as a currency string
	 * 
	 * @param double $value
	 * @return string - like $2.00 (negative values are printed as -$2.00)
	 */
	public static function currency($value) {
		if( $value < 0 )
			return '-$'.number_format(abs($value), 2);
		else
			return '$'.number_format($value, 2);
	}
	
	/**
	 * Add some interval of time.  (This only keeps the hour+min+second if using one of those 
	 * as the unit.)
	 * 
	 * @param mixed $date - a string that can be parsed, or an integer timestamp
	 * @param int $offset
	 * @param char $unit (Optional) - one of: m=month, d=day, Y=year, H=hour, i=minute, s=second 
	 *				Note that these are the same as php date function chars. (Default is day.)
	 * @param string - date in the form "Y-m-d", or "Y-m-d H:i:s"
	 */
	public static function addDate($date, $offset, $unit = 'd') {
		if( is_string($date) )
			$date = strtotime($date);
		
		switch($unit) {
			case 'm':
				return date('Y-m-d', 
						mktime(0,0,0, date('m',$date) + $offset, date('d',$date), date('Y',$date)));
			case 'd':
				return date('Y-m-d', 
						mktime(0,0,0, date('m',$date), date('d',$date) + $offset, date('Y',$date)));
			case 'Y':
				return date('Y-m-d', 
						mktime(0,0,0, date('m',$date), date('d',$date), date('Y',$date) + $offset));
			case 'H':
				return date('Y-m-d H:i:s', 
						mktime(date('H',$date) + $offset, date('i',$date), date('s',$date), 
						date('m',$date), date('d',$date), date('Y',$date)));
			case 'i':
				return date('Y-m-d H:i:s', 
						mktime(date('H',$date), date('i',$date) + $offset, date('s',$date), 
						date('m',$date), date('d',$date), date('Y',$date)));
			case 's':
				return date('Y-m-d H:i:s', 
						mktime(date('H',$date), date('i',$date), date('s',$date) + $offset, 
						date('m',$date), date('d',$date), date('Y',$date)));
			default:
				return date('Y-m-d', $date);
		}
	}
	
	/**
	 * Add some interval of time.  Keeps info down to the second.
	 * 
	 * @param mixed $time - a string that can be parsed, or an integer timestamp
	 * @param int $offset
	 * @param char $unit (Optional) - one of: m=month, d=day, Y=year, H=hour, i=minute, s=second 
	 *				Note that these are the same as php date function chars. (Default is day.)
	 * @param int - timestamp
	 */
	public static function addTime($time, $offset, $unit = 'd') {
		if( is_string($time) )
			$time = strtotime($time);
		
		switch($unit) {
			case 'm':
				return mktime(date('H',$time), date('i',$time), date('s',$time), 
						date('m',$time) + $offset, date('d',$time), date('Y',$time));
			case 'd':
				return mktime(date('H',$time), date('i',$time), date('s',$time), 
						date('m',$time), date('d',$time) + $offset, date('Y',$time));
			case 'Y':
				return mktime(date('H',$time), date('i',$time), date('s',$time), 
						date('m',$time), date('d',$time), date('Y',$time) + $offset);
			case 'H':
				return mktime(date('H',$time) + $offset, date('i',$time), date('s',$time), 
						date('m',$time), date('d',$time), date('Y',$time));
			case 'i':
				return mktime(date('H',$time), date('i',$time) + $offset, date('s',$time), 
						date('m',$time), date('d',$time), date('Y',$time));
			case 's':
				return mktime(date('H',$time), date('i',$time), date('s',$time) + $offset, 
						date('m',$time), date('d',$time), date('Y',$time));
			default:
				return $time;
		}
	}
	
	
	//Citation:
	//  singluar and plural functions originally taken from CodeIgniter source.
	//  Modified: added exceptions list.
	private static $pluralMap = array(
		'mouse' => 'mice',
		'person' => 'people',
		'deer' => 'deer'
	);
	
	/**
	 * Singular
	 *
	 * Takes a plural word and makes it singular
	 *
	 * @access	public
	 * @param	string
	 * @return	str
	 */	
	function singular($str)
	{
		$str = strtolower(trim($str));
		
		if( $sing = array_search($str, self::$pluralMap) ) {
			return $sing;
		}
		
		$end = substr($str, -3);
	
		if ($end == 'ies')
		{
			$str = substr($str, 0, strlen($str)-3).'y';
		}
		elseif ($end == 'ses')
		{
			$str = substr($str, 0, strlen($str)-2);
		}
		else
		{
			$end = substr($str, -1);
		
			if ($end == 's')
			{
				$str = substr($str, 0, strlen($str)-1);
			}
		}
	
		return $str;
	}

	/**
	 * Plural
	 *
	 * Takes a singular word and makes it plural
	 *
	 * @access	public
	 * @param	string
	 * @param	bool
	 * @return	str
	 */	
	function plural($str, $force = FALSE)
	{
		//$str = strtolower(trim($str));
		$str = trim($str); //eam 1/1/14 - lower case helps if ending is upper, but messes 
						   // up class names.
		
		if( isset(self::$pluralMap[$str]) ) {
			return self::$pluralMap[$str];
		}
		
		$end = substr($str, -1);

		if ($end == 'y')
		{
			// Y preceded by vowel => regular plural
			$vowels = array('a', 'e', 'i', 'o', 'u');
			$str = in_array(substr($str, -2, 1), $vowels) ? $str.'s' : substr($str, 0, -1).'ies';
		}
		elseif ($end == 's')
		{
			if ($force == TRUE)
			{
				$str .= 'es';
			}
		}
		else
		{
			$str .= 's';
		}

		return $str;
	}
	
	/** 
	 * Checks the word ending to see if it's likely a plural.
	 */
	function isPlural($str) {
		$str = strtolower(trim($str));
		
		if( key_exists($str, self::$pluralMap) )
			return false; //it's a single -> plural map. So this must be singular form.
		
		//Check for common plural endings
		$end = substr($str, -1);
		if( $end == 's' )
			return true;
		
		//Check if it's a plural one in the map
		if( array_search($str, self::$pluralMap) !== false )
			return true;
		
		return false;
	}
	
	//Also grabbed these from CodeIgniter, though I often don't use these.
	function camelize($str)
	{		
		$str = 'x'.strtolower(trim($str));
		$str = ucwords(preg_replace('/[\s_]+/', ' ', $str));
		return substr(str_replace(' ', '', $str), 1);
	}
	/*function underscore($str) //this version just replaces space with underscore
	{
		return preg_replace('/[\s]+/', '_', strtolower(trim($str)));
	}*/
	function humanize($str)
	{
		return ucwords(preg_replace('/[_]+/', ' ', strtolower(trim($str))));
	}
	
	function underscore($str) {
		$str = trim($str);
		$str = preg_replace('/([a-z0-9])?([A-Z])/','$1 $2',trim($str));
		return str_replace(' ','_',trim(strtolower($str)));
	}
	function camelizeClass($str)
	{		
		$str = strtolower(trim($str));
		$str = ucwords(preg_replace('/[\s_]+/', ' ', $str));
		return str_replace(' ', '', $str);
	}
	
	
	/**
	 * Generate a random string of given length. Useful for auth tokens or salt.
	 * @param int $length - num chars in the returned string (>0)
	 * @return string
	 */
	function randomString($length) {
		$str = "";
		$len = 0;
		while(($len = strlen($str)) < $length)
			$str .= md5(rand());
		
		if($len == $length)
			return $str;
		
		$start = rand(0,$len - $length); //can pick any starting point that leaves enough chars
		return substr($str, $start, $length);
	}
	
	
	/**
	 * I shouldn't have to do this, but sometimes magic quotes is still on. This strips them 
	 * conditionally.
	 */
	function stripmagic($in) {
		if( get_magic_quotes_gpc() )
			return stripslashes($in);
		return $in;
	}
	
	/**
	 * String utils
	 */
	static function str_endswith($str, $search) {
		return (substr($str, strlen($str) - strlen($search)) == $search);
	}
	static function str_startswith($str, $search) {
		return (substr($str, 0, strlen($search)) == $search);
	}
	static function str_contains($str, $search) {
		return (strpos($str, $search) !== false);
	}
}
?>