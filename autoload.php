<?php

function np_autoload($className) {
	#echo "Trying to load $className<br/>\n";
	$base = dirname(__FILE__).'/../';
	
	//Most common first
	$path = $base.'engine/class.'.$className.'.php';
	if( file_exists($path) ) {
		include_once $path;
		return true;
	}
	
	$modelBase = dirname(__FILE__).'/../../app/models/';
	$path = $modelBase.Util::underscore($className).'.php';
	if( file_exists($path) ) {
		include_once $path;
		return true;
	}
	
	$path = $base.'class.'.$className.'.php';
	if( file_exists($path) ) {
		include_once $path;
		return true;
	}
	
	//Then a bunch of fallbacks
	
	$path = $base.$className.'.php';
	if( file_exists($path) ) {
		include_once $path;
		return true;
	}
	
	$path = $base.strtolower($className).'.php';
	if( file_exists($path) ) {
		include_once $path;
		return true;
	}
	
	$path = $base.'engine/'.$className.'.php';
	if( file_exists($path) ) {
		include_once $path;
		return true;
	}
	
	$path = $base.'engine/'.strtolower($className).'.php';
	if( file_exists($path) ) {
		include_once $path;
		return true;
	}
	
	$path = $modelBase.$className.'.php';
	if( file_exists($path) ) {
		include_once $path;
		return true;
	}
	return false;
}


if (version_compare(PHP_VERSION, '5.1.2', '>=')) {
	spl_autoload_register("np_autoload");
	
	//Register Vendors
	@require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'PHPMailer'.
			DIRECTORY_SEPARATOR.'PHPMailerAutoload.php';
} else {
	//Old way
	function __autoload($className) {
		if( np_autoload($className) ) return;
		
		//Try Vendors
		@require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.
				'PHPMailer'.DIRECTORY_SEPARATOR.'PHPMailerAutoload.php';
		PHPMailerAutoload($classname);
	}
}

?>