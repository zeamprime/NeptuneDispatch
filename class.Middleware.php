<?php
/**
 * @author Everett Morse
 * Copyright (c) 2013 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * Interface for middleware.
 * 
 * There are two places where this can be applied. One is inside REST dispatch, which will
 * only get paths for REST API calls and is inside the try-catch block to report 
 * exceptions in a REST-ful way.  The other is before the traditional front controller and
 * has no support other than using Page class utilities.
 * 
 * Attach middleware in the config file.
 */

class Middleware
{
	
	/**
	 * Examine the path or other request properties, then alter what you will or send a
	 * response and exit.
	 * 
	 * @param IO $path : array - the path components.
	 * @param IO $ext : string - the file extension or converted request type.
	 */
	public function apply(&$path, &$ext) {
		
	}
	
	/**
	 * Given a list of class names (from a configuration file), load an instance of each,
	 * reporting errors if any are invalid.
	 *
	 * @param $names : string[] - class names
	 * @returns array of Middleware instances
	 */
	public static function loadMiddleware($names) {
		$res = array();
		foreach($names as $name) {
			//$instance = null;
			if( !class_exists($name) ) {
				echo "Could not find middleware '$name'.\n";
				if( defined('ENV') && constant('ENV') == 'dev' )
					continue;
				else
					exit; //Don't allow failure in production 
			}
			$instance = new $name();
			if( !($instance instanceof Middleware) ) {
				echo "Class '$name' is not middleware.\n";
				if( defined('ENV') && constant('ENV') == 'dev' )
					continue;
				else
					exit; //Don't allow failure in production 
			}
			$res[] = $instance;
		}
		return $res;
	}
	
}
?>