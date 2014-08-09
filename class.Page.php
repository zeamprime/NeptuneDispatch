<?php
/**
*	(c) 2012 Everett Morse
*	www.neptic.com
*	
*	Main dispatch and rendering control, used by the front controller.
*	
*	In a different architecture, I used a class like this to hold rendering info.  In this version
*	this is more just a namespace for dispatch functions, since Controller holds variables used
*	for rendering.
*/

class Page
{
	static $webRoot = null;
	static $frontCtlInPath = false;
	static $routeMap = array(); //list of array(regex,controller,action);
	
	static $vars = array(); //stash some processed data here, e.g. from middleware
	
	//////////// Dispatch Functions ///////////
	
	/**
	 * Catch a few special cases -- wouldn't need to if we set up mod_rewrite well, but this is a 
	 * cheap version of a rewrite system for getting at resources. Browsers call into dispatch.php 
	 * for these because we give relative paths in the HTML that the server sees and have not 
	 * removed dispatch.php from the visible path with mod_rewrite.
	 */
	function checkProxyStaticAsset($pathPieces, $ext) {
		//if( in_array($pathPieces[0], array('public')) ) {
			$path = ROOT_DIR . '/' . implode('/',$pathPieces).'.'.$ext;
			
			if( $pathPieces[0] != 'public' && !file_exists($path) ) {
				// Try looking for the file in the public directory.
				$path = ROOT_DIR . '/public/' . implode('/',$pathPieces).'.'.$ext;
			}
			
			if( !file_exists($path) )
				return;
			if( $ext == 'php' )
				return;
			
			//Set mime type
			if( $ext == 'js' )
				header('Content-Type: text/javascript');
			else if( $ext == 'css' )
				header('Content-Type: text/css');
			else if( $ext == 'png' )
				header('Content-Type: image/png');
			else if( $ext == 'gif' )
				header('Content-Type: image/gif');
			else if( $ext == 'jpg' )
				header('Content-Type: image/jpg');
			else if( $ext == 'html' )
				header('Content-Type: text/html');
			else
				header('Content-Type: text/plain');
			
			header('Content-Length: ' . filesize($path));
			//send data
			readfile($path);
			exit;
		//}
	}
	
	function dispatch($path) {
		//Find HTTP method
		if( isset($_SERVER['REQUEST_METHOD']) ) {
			$method = $_SERVER['REQUEST_METHOD'];
			if( isset($_REQUEST['_method']) && $method == 'POST' )
				$method = strtoupper($_REQUEST['_method']); //allow clients to pretend
		} else {
			//If nginx is configured badly, might miss REQUEST_METHOD and/or SERVER_NAME
			$method = 'GET';
			if( isset($_REQUEST['_method']) ) //allow for GET too b/c we had to assume GET.
				$method = strtoupper($_REQUEST['_method']);
		}
		
		
		if( ($route = self::checkRegexRoutes($path)) !== null ) {
			list($regex,$controllerName,$action) = $route;
			$filePath = ROOT_DIR . '/app/controllers/' . $controllerName;
			$id = null;
		} else {
			//Get the ID -- strips off numeric final path components.
			$id = null;
			if( is_numeric($path[count($path)-1]) ) {
				$id = array_pop($path);
			}
			
			//Find controller name (first component that isn't a directory in app/controllers)
			$controllerName = array_shift($path);
			$filePath = ROOT_DIR . '/app/controllers/' . $controllerName;
			while( is_dir($filePath) && count($path) > 0) {
				$controllerName = array_shift($path);
				$filePath .= '/' . $controllerName;
			}
			
			//Find action
			if( count($path) > 0 )
				$action = array_shift($path);
			else
				$action = "index";
				
			//Also check for non-numeric id left over
			if( $id === null && count($path) > 0 )
				$id = array_shift($path);
		}
		
		
		if( is_file($filePath . '.php') ) {
			//Load Controller
			$controller = include($filePath . '.php');
			if( !$controller || !($controller instanceof Object) ) {
				//Try to instantiate one (convert snake-case to camel-case, append Controller)
				$name = str_replace('_',' ',strtolower($controllerName));
				$name = str_replace(' ','',ucwords($name)).'Controller';
				if( class_exists($name) )
					$controller = new $name();
			}
			if( !$controller ) {
				self::sendError("Invalid resource path (\"$filePath\")", 404, ERROR_TEMPLATE, DEFAULT_LAYOUT);
			}
		} else
			self::sendError("Invalid resource path (\"$filePath\")", 404, ERROR_TEMPLATE, DEFAULT_LAYOUT);
			
		try {
			if( $controller instanceof Controller ) {
				//chance to change the action name, probably based on $method
				$action = $controller->route($action, $method);
				$controller->beforeFilter($action, $method);
			}
		
			if( substr($action,0,1) == '_' || !method_exists($controller, $action) )
				self::sendError("Invalid controller method \"$action\"", 404, ERROR_TEMPLATE, DEFAULT_LAYOUT);
			else
				$controller->$action($id);
		} catch(Exception $e) {
			self::sendError("Caught exception: <pre>".$e."</pre>", 500, ERROR_TEMPLATE, DEFAULT_LAYOUT);
		}
	}
	
	function checkRegexRoutes($path) {
		$path = implode('/',$path);
		foreach(self::$routeMap as $route) {
			if( !is_array($route) || count($route) != 3 ) continue;
			if( preg_match($route[0],$path) )
				return $route;
		}
		return null;
	}
	
	function calculateWebRoot() {
		if( self::$frontCtlInPath ) {
			$script = $_SERVER['PHP_SELF'];
			$i = strpos($script, 'index.php');
			if( $i >= 0 )
				self::$webRoot = substr($script,0,$i-1);
			else
				self::$webRoot = '';
		} else if( defined('WEB_ROOT') ) {
			self::$webRoot = constant('WEB_ROOT');
		} else {
			$relativeFrom = dirname($_SERVER['PHP_SELF']);
			//$relativeFrom = $_SERVER['REQUEST_URI'];
			$localFrom = dirname($_SERVER['SCRIPT_FILENAME']);
			$compareTo = dirname(__FILE__);
			//echo "rel=$relativeFrom, local=$localFrom, file=$compareTo<br/>";
			if( strrpos($relativeFrom,'.') !== false &&
					(strrpos($relativeFrom,'/') === false ||
					strrpos($relativeFrom,'.') > strrpos($relativeFrom,'/')) )
				$relativeFrom = dirname($relativeFrom);
			
			if( substr($localFrom,0,11) == "/var/chroot" )
					$localFrom = substr($localFrom,11);
			
			//Strip off the part that is the same
			for($c = 0; $c < strlen($localFrom); $c++) {
					if( substr($localFrom,$c,1) != substr($compareTo,$c,1) )
							break;
			}
			//$c now points to the first non-matched char.  We know that the
			//includes directory comes from the root, so now we can see how
			//far from the root the current page is.
	
			$remainder = substr($localFrom, $c);
			//echo "=> remainder=$remainder<br/>";
	
			if( $remainder != "" )
					$levels = 1 + ( strlen($remainder) - strlen(str_replace("/","",$remainder)) );
			else
					$levels = 0;
			//echo "levels from project root=$levels<br/>";
			
			//For each level down we are, remove a dir off the relativeFrom
			$absPath = $relativeFrom;
			$page = "";       //and we'll add those levels here
			while($levels > 0) {
	
					$page = substr($absPath, strrpos($absPath,"/"));
					$absPath = substr($absPath, 0, strrpos($absPath,"/"));
	
					$levels--;
			}
	
			$page .= '/' . basename($_SERVER['PHP_SELF']);
	
			//echo "page=$page<br/>";
			//echo "absPath=$absPath<br/>";
	
			if( ($i = strpos($absPath, "/index.php")) ) {
					#echo "i=$i<br/>";
					$absPath = substr($absPath, 0, $i);// . substr($this->absPath, $i + 10);
			}
			
			//OK, now, index.php is in the /public dir, and this function gets called only when we
			// don't have a pre-defined web root.  I cannot determine webroot correctly if the 
			// public dir is symlinked into the web server's document path, so assume that user will
			// supply in that case, leaving us with the case where the whole project was dropped in
			// htdocs.  So I need to go back down to the public dir.
			//eam 5/17/12 Not in Lunch app config, that was for blog:
			//$absPath .= '/public';
			#echo "absPath=$absPath<br/>\n";
			
			self::$webRoot = $absPath;
			
			#if( isset($_SERVER['SERVER_NAME']) )
			#	echo 'http://'.$_SERVER['SERVER_NAME'].$absPath;
		}
	}
	
	//////////// Helper Functions //////////////
	
	
	/**
	 * Converts a URL that is relative to the project web root to an absolute URL.
	 * This handles figuring out what path prefix (/ or some sub-dir) is needed.
	 */
	public static function absPath($url) {
		if( self::$webRoot == null )
			self::calculateWebRoot();
		return self::$webRoot.(self::$frontCtlInPath? '/':'').$url;
	}
	
	//Path to a controller.  If not using rewrites, should insert index.php in there.
	public static function path($url) {
		if( self::$frontCtlInPath ) {
			return self::$webRoot.'/index.php'.$url;
		} else
			return self::absPath($url);
	}
	
	/**
	 * convert to actual file path ...
	 */
	public static function filepath($path) {
		return ROOT_DIR.'/'.$path;
	}
	
	/**
	 * Get the server name
	 */
	public static function getServerName() {
		if(isset($_SERVER['SERVER_NAME']))
			return $_SERVER['SERVER_NAME'];
		else
			return $_SERVER['HTTP_HOST'];
	}
	
	/**
	 * Get 'http://' or 'https://'.
	 */
	public static function getRequestSchema() {
		if( $_SERVER['HTTPS'] )
			return 'https://';
		else
			return 'http://';
	}
	
	/**
	 * Includes schema and host name.
	 */
	public static function fullURL($url) {
		return self::getRequestSchema().self::getServerName().self::absPath($url);
	}
		
	/*From dispatch_functions.php in older vers of front controller.
	function absPath($fromRoot) {
		$script = $_SERVER['PHP_SELF'];
		$i = strpos($script, 'index.php');
		if( $i >= 0 )
			$base = substr($script,0,$i);
		else
			$base = '/';
		return $base.$fromRoot;
	}
	
	function path($fromRoot) {
		$script = $_SERVER['PHP_SELF'];
		$i = strpos($script, 'index.php');
		if( $i >= 0 )
			$base = substr($script,0,$i);
		else
			$base = '/';
		return $base.'index.php'.$fromRoot;
	}*/
	
	/**
	 * Ask for a header the nice way. Handle the trickiness of converting to the right
	 * constant name and looking in the right place.
	 * 
	 * @param $header : string - name of header
	 * @returns mixed - false if not present, otherwise it's value .
	 */
	function getRequestHeader($header) {
		//TODO: this works for Apache, but might not for Nginx.
		
		$var = 'HTTP_'.str_replace('-','_',$header);
		if( !isset($_SERVER[$var]) )
			return false;
		return $_SERVER[$var];
	}
	
	/**
	 * Middleware can stash data here.
	 */
	public static function getVar($name) {
		return self::$vars[$name];
	}
	public static function setVar($name, $val) {
		self::$vars[$name] = $val;
	}
	public static function hasVar($name) {
		return isset(self::$vars[$name]);
	}
	
	///////////////// Output Functions ///////////////
	
	function sendStatusCode($code) {
		switch($code) {
			case 404: 	$status = 'Not Found'; break;
			case 410: 	$status = 'Removed'; break;
			case 500: 	$status = 'Internal Server Error'; break;
			case 200:	$status = "OK"; break;
			case 201:	$status = "Created"; break;
			case 300:	$status = "Moved"; break;
			case 301:	$status = "Moved Permanently"; break;
			case 400:	$status = "Bad Request"; break;
			case 401:	$status = "Unauthorized"; break;
			case 403:	$status = "Forbidden"; break;
			case 405:	$status = "Method Not Allowed"; break;
			case 406:	$status = "Not Acceptable"; break;
			default:	$status = 'Error'; break;
		}
		header("HTTP/1.0 $code $status");
		//Docs say for FastCGI it should be this:
		//header("Status: $code $status");
	}
	
	function sendError($msg, $code = null, $templateName = null, $layout = null) {
		if( $code )
			self::sendStatusCode($code);
		if( $templateName ) {
			self::sendView($templateName, array('message' => $msg), $layout);
		} else {
			?>
			<html><head><title>Error</title></head>
			<body>
			<h1>Error!</h1>
			<p>
			<?=$msg?>
			</p>
			<?if( constant('ENV') == 'dev' ) { ?>
			<pre><? debug_print_backtrace() ?></pre>
			<?}?>
			</body>
			</html>
			<?
			exit;
		}
	}
	
	function sendText($msg) {
		echo $msg;
		exit;
	}
	
	function sendView($name, $data = null, $layout = null) {
		//Find the view
		$filePath = dirname(__FILE__) . '/../../app/views/' . $name . '.php';
		if( file_exists($filePath) ) {
			
			//Determine layout
			if( $layout == null && constant('DEFAULT_LAYOUT') != '' )
				$layout = constant('DEFAULT_LAYOUT');
			if( $layout ) {
				$layoutPath = dirname(__FILE__) . '/../../app/views/layouts/' . $layout . '.php';
				if( file_exists($layoutPath) ) {
				
					ob_start();
					include $filePath;
					$contents = ob_get_clean();
					
					include $layoutPath;
					
					exit;
				} else
					echo "Warning: could not find layout '$layout'";
			}
			
			//Use view without layout
			include $filePath;
		} else
			self::sendError("Could not find view \"$name\" (at \"$filePath\")", 404, ERROR_TEMPLATE);
		exit;
	}
	
	function renderViewAsString($name, $data = null, $layout = null) {
		//Find the view
		$filePath = dirname(__FILE__) . '/../../app/views/' . $name . '.php';
		if( file_exists($filePath) ) {
			
			//Determine layout
			if( $layout == null && constant('DEFAULT_LAYOUT') != '' )
				$layout = constant('DEFAULT_LAYOUT');
			if( $layout ) {
				$layoutPath = dirname(__FILE__) . '/../../app/views/layouts/' . $layout . '.php';
				if( file_exists($layoutPath) ) {
				
					ob_start();
					include $filePath;
					$content = ob_get_clean();
					
					ob_start();
					include $layoutPath;
					$full_content = ob_get_clean();
					
					return $full_content;
				} else
					return null;
			}
			
			//Use view without layout
			ob_start();
			include $filePath;
			$content = ob_get_clean();
			return $content;
		}
		
		return null;
	}
	
	
	function sendPartial($name, $data = null) {
		$filePath = dirname(__FILE__).'/../../app/views/'.$name;
		$filePath = dirname($filePath).'/_'.basename($filePath).'.php';
		if( file_exists($filePath) ) {
			include $filePath;
		} else
			self::sendError("Could not find partial view \"$name\" (at \"$filePath\")");
	}
	
	function sendJSON($data) {
		header('Content-Type: application/json');
		//header('Content-Type: text/plain');
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		
		echo self::json_encode_custom($data);
		//echo '(' . json_encode($data) . ')';
	}
	
	//Recursively convert to JSON, but using toJSON on anything that has the method defined.
	//FYI: Recursion goes into arrays, but not into objects. (I could, just didn't want to.)
	function json_encode_custom($data) {
		if( is_array($data) ) {
			$arr = array();
			$asObj = false;
			foreach($data as $key => $value) {
				if( !is_numeric($key) )
					$asObj = true;
				$arr[$key] = self::json_encode_custom($value);
			}
			if( $asObj ) {
				$json = '{'; $sep = '';
				foreach($arr as $key => $value) {
					$json .= $sep.'"'.$key.'": '.$value;
					$sep = ', ';
				}
				$json .= '}';
				return $json;
			} else
				return '[' . implode(', ',$arr) . ']';
		} else if( is_object($data) ) {
			if( method_exists($data, 'toJSON') )
				return $data->toJSON();
			else
				return json_encode($data);
		} else 
			return json_encode($data);
	}
	
	function sendXML($data) {
		header('Content-Type: text/xml');
		
		if( is_object($data) && method_exists($data, "toXML") )
			$xml = $data->toXML();
		else
			$xml = "<message>Engine: generic object-to-XML conversion not implemented.</message>";
		
		echo $xml;
	}
	
}

?>
