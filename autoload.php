<?php

//spl_autoload_register("__autoload"); //in the future we might have to do this.
function __autoload($className) {
	#echo "Trying to load $className<br/>\n";
	$base = dirname(__FILE__).'/../';
	
	//Most common first
	$path = $base.'engine/class.'.$className.'.php';
	if( file_exists($path) ) {
		include_once $path;
		return;
	}
	
	$modelBase = dirname(__FILE__).'/../../app/models/';
	$path = $modelBase.Util::underscore($className).'.php';
	if( file_exists($path) ) {
		include_once $path;
		return;
	}
	
	$path = $base.'class.'.$className.'.php';
	if( file_exists($path) ) {
		include_once $path;
		return;
	}
	
	//Then a bunch of fallbacks
	
	$path = $base.$className.'.php';
	if( file_exists($path) ) {
		include_once $path;
		return;
	}
	
	$path = $base.strtolower($className).'.php';
	if( file_exists($path) ) {
		include_once $path;
		return;
	}
	
	$path = $base.'engine/'.$className.'.php';
	if( file_exists($path) ) {
		include_once $path;
		return;
	}
	
	$path = $base.'engine/'.strtolower($className).'.php';
	if( file_exists($path) ) {
		include_once $path;
		return;
	}
	
	$path = $modelBase.$className.'.php';
	if( file_exists($path) ) {
		include_once $path;
		return;
	}
	
}


?>