<?php
/**
 * @author Everett Morse
 * @copyright (c) 2009-2014
 * 
 * Place (symlink) in root dir of project
 * TODO: support configuring htaccess / rewrite rules to point here.
 */

define('ROOT_DIR',realpath(dirname(__FILE__)));
include_once ROOT_DIR.'/config/config.php';
include_once ROOT_DIR.'/lib/engine/autoload.php';

if( !defined('ERROR_TEMPLATE') )
	define('ERROR_TEMPLATE', null);
if( !defined('DEFAULT_LAYOUT') )
	define('DEFAULT_LAYOUT', null);


/*
 Requests come in as 
 	/path/to/public/index.php/controller/action/id
 or
 	/path/to/public/controller/action/id
 depending on whether rewriting rules are in play.  In the rewrite case, I need to use REQUEST_URI
 to determine what is wanted.  In the other case, I need to use PATH_INFO.  I can que off of the
 presence/absence of PATH_INFO to know if I'm using rewrites.
 
 TODO: find out what happens if I go directly to index.php by name.  Do I use rewrite form or not
 in that case, since I suspect PATH_INFO won't be there.
*/
if( isset($_SERVER['PATH_INFO']) || defined('NO_REWRITES') ) {
	Page::$frontCtlInPath = true;
	$path = isset($_SERVER['PATH_INFO'])? trim($_SERVER['PATH_INFO']) : '';
	//echo "Using path info<br/>";
	Page::calculateWebRoot();
} else {
	Page::$frontCtlInPath = false;
	
	//Need to remove webroot
	$path = $_SERVER['REQUEST_URI'];
	//echo "Using request uri<br/>";
	$path = explode('?',$path); //REQUEST_URI includes the query string
	$path = $path[0];
	Page::calculateWebRoot();
	$l = strlen(Page::$webRoot);
	if( substr($path,0,$l) == Page::$webRoot )
		$path = substr($path,$l);
}

//Remove leading and trailing slash
if( substr($path,0,1) == '/' )
	$path = substr($path,1);
if( substr($path,-1,1) == '/' )
	$path = substr($path,0,-1);

//See if we didn't give a path.
if( $path == '' ) {
	if( defined('HOME_PATH') && constant('HOME_PATH') != '' ) {
		$path = constant('HOME_PATH');
		if( substr($path,0,1) != '/' )
			$path = '/'.$path;
		//Now redirect to here
		header("Location: ".Page::path($path)."\n");
		exit;
	} else
		Page::sendError("No default route defined.", null, ERROR_TEMPLATE, DEFAULT_LAYOUT);
}

//Parse some path info
$idx = strrpos($path,'.');
if( $idx !== false) {
	$ext = substr($path, $idx+1);
	$path = substr($path, 0, $idx);
} else $ext = "";
$path = explode('/',$path);

//echo "Server data=<pre>"; var_dump($_SERVER); echo "</pre>"; exit;

Page::checkProxyStaticAsset($path,$ext); //may exit

//Check if the REST dispatcher can claim this
$type = RestDispatch::requestDataType($ext);
if( RestDispatch::isRestType($type) ) {
	RestDispatch::dispatch($path, $type);
	exit;
} else if( $path[0] == 'help' && RestDispatch::isRestHelpType($type) ) {
	//HTML normally is not REST, but it is if it's for help on a REST API call.
	RestDispatch::dispatch($path, $type);
	exit;
} 
/*
Note: This could use $ext == '', to handle paths like "/groups", but the HTTP Accepts param should
      still have type JSON (etc) which would let us distinguish API from UI.
else if( $type == '' && RestDispatch::isRestModel($path[0]) ) {
	//Even still, give API precedence
	RestDispatch::dispatch($path, 'json');
	exit;
}
*/

//Start session before we call anything in the controller
//session_start();

//Load hooks for special routing rules
if( file_exists(ROOT_DIR.'/config/routes.php') ) {
	include_once(ROOT_DIR.'/config/routes.php');
}

if( defined('MIDDLEWARE') ) {
	foreach(Middleware::loadMiddleware(explode(',',constant('MIDDLEWARE'))) as $mid) {
		$mid->apply($path, $ext);
	}
}

Page::dispatch($path);

?>
