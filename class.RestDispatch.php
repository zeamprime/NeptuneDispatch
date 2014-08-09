<?php
/**
 *	@author Everett Morse
 *	Copyright (c) 2013 Everett Morse. All rights reserved.
 *	www.neptic.com
 *
 *	Dispatcher / Front controller for REST API.
 *	
 *	The idea is to skip the normal front controller that picks out controller/action/id. It also has
 *	handling for HTTP method where controler+action+method determines what function to call on the
 *	controller instance. But that's all processing we don't want here.  Instead we want the path to
 *	be the model name and we *always* want an HTTP method. Sometimes we get ids and sometimes we get
 *	filter parameters (in a query string or otherwise).
 *	
 *	The API expects payloads to arrive as the request body or packed in a _data variable. Similarly
 *	you can use a POST with the variable _method to simulate any method. This way if a client is
 *	limited to only GET and POST calls you can still manage. Or you can also put filter params in
 *	post bodies where they are protected by SSL too.
 *	
 *	Preferred data type is JSON. But I'll leave it open to add XML and PHP serialization as well.
 *	
 *	The main Front Controller parses the request path and decides whether it's a REST or normal
 *	request.  This is based on the Accepts request header and/or the data type in the request URI's
 *	extension. As such, we provide a helper to report data type.
 *	
 *	When a request arrives we determine which model and method to pass it to. We create an array of
 *	filter parameters and fetch the ID if any. We parse any data body (JSON etc) that arrived. Then
 *	we give it all to the model controller.  The model gives back an object which we encode in the
 *	appropriate format.
 *	
 *	There is a built-in documentation system.  This works by requesting /help/model(s)/verb, or 
 *	/help/model(s) for a list of verbs that model supports. If the request is HTML then you get
 *	rendered HTML, otherwise you get plain Markdown text wrapped in JSON/XML/etc. Note that you can
 *	make things nicer by using a controller like "/docs" to put up a frame with HTML content from
 *  this help system in the center frame and a list of verbs in a side frame.
 *	
 *	VERB /model/id
 *	GET - `fetch`. Give id, get data back.
 *	POST - `create`. Returns new object back, or if it can de-dup returns the original.
 *	PUT - `update`. Returns status, not the whole object. True/false, maybe more.
 *	DELETE - `delete`. Returns status.
 *
 *	VERB /models (note the "s")
 *	GET - `index`. Optional filters. Filters by access level too.
 *	POST - `bulk_create`
 *	PUT - `bulk_update`
 *	- If bulk_create / bulk_update are not defined then we iterate the input array to pass each
 *	  input to the model's single create/update function and return the results in an array. Update
 *	  should pass ids in the variable "ids" as an array.
 *
 *	GET /help/model(s)/verb
 *	- Gets the man page, in Markdown, for the specified API call.
 *	- GET /help/ will list all models and some basic help.
 *	- GET /help/model(s) will list all verbs for that model.
 */


//Polyfill
if( !function_exists('http_get_request_body') ) {
	function http_get_request_body() {
		global $the_request_body9292;
		if( !isset($the_request_body9292) ) {
			$the_request_body9292 = @file_get_contents('php://input');
		
			//Maybe $HTTP_RAW_POST_DATA ?
			//Might need Content-Length to be correct?
		}
		return $the_request_body9292;
	}
}

class RestDispatch
{
	
	/**
	 * Determine requested data type based on file extension and HTTP Accept header.
	 *
	 * @returns one of 'html', 'json', 'xml', or empty string for anything else.
	 */
	public static function requestDataType($ext) {
		//First see if extension makes it obvious
		
		switch(strtolower($ext)) {
			case 'html': case 'htm': return 'html';
			case 'json': return 'json';
			case 'xml': return 'xml';
			case 'md': case 'markdown': return 'md';
		}
		
		$list = explode(';',$_SERVER['HTTP_ACCEPT']); //first ';' piece has comma-separated list.
		$list = explode(',',$list[0]);
		
		foreach($list as $accepts) {
			if( $accepts == 'text/html' ) return 'html';
			if( strpos($accepts, 'json') !== false ) return 'json';
			if( strpos($accepts, 'xml') !== false ) return 'xml';
			if( strpos($accepts, 'markdown') !== false ) return 'md';
		}
		
		return '';
	}
	
	/**
	 *	Returns whether the given type is one that always is handled by the REST API.
	 */
	public static function isRestType($type) {
		return $type == 'json';
		//Future: XML and perhaps PHP serialization.
	}
	
	public static function isRestHelpType($type) {
		return $type == 'html' || $type == 'md';
	}
	
	/**
	 * If no type extension is given then we have to do more to see if this path represents 
	 * something in the API or should go to web UI controllers.
	 */
	public static function isRestModel($name) {
		if($name == 'help') return true;
		
		echo "isRestModel($name)";
		
		if( Util::isPlural($name) )
			$name = Util::singular($name);
		if( $model === null ) {
			$filepath = ROOT_DIR . '/app/api/' . $name . '.php';
			return file_exists($filepath);
		}
	}
	
	/**
	 * Entry point to REST controller.
	 * This includes handling 'help' paths, which might be html or otherwise.
	 * Input data is decoded per the type. Output encoded per the same type.
	 * 
	 * @param path : string array - path components
	 * @param type : string - json, xml, html
	 */
	public static function dispatch($path, $type) {
		//echo "path=<pre>"; var_dump($path); echo "</pre>";
		//echo "type=$type";
		
		//We only support limited types. HTML only allowed for help pages.
		if( $type != 'json' ) {
			if( $path[0] != 'help' && !self::isRestHelpType($type) ) {
				self::sendError("Unsupported request data type.",'json', 406);
				return;
			}
		}
		
		try {
			
			if( defined('API_MIDDLEWARE') ) {
				$mids = Middleware::loadMiddleware(explode(',',constant('API_MIDDLEWARE')));
				foreach($mids as $mid) {
					$mid->apply($path, $ext);
				}
			}
			
			
			$model = null;
			$method = $_SERVER['REQUEST_METHOD'];
			if( $method == 'POST' && isset($_POST['_method']) )
				$method = $_POST['_method']; //allow simulating any method with a POST.
		
			if( $path[0] == 'help' ) {
				// Either an index (of models or methods in one), or for a specific model + method.
				$modelName = $path[1];
				$model = new RestHelp($modelName, $type);
				$id = $path[2]; //if it exists, this is the method name to ask about.
			} else {
				$modelName = $path[0];
				$id = $path[1];
			}
			
			//Determine if model name is plural
			$plural = Util::isPlural($modelName);
			if( $plural )
				$modelName = Util::singular($modelName); //need single for source file
			
			//For non-help calls, get the model object
			if( $model === null ) {
				$filepath = ROOT_DIR . '/app/api/' . $modelName . '.php';
				if( !file_exists($filepath) ) {
					self::sendError("Cannot find model {$modelName}.", $type, 404);
					return;
				}
				require_once($filepath);
				$className = Util::camelizeClass($modelName)."RestController";
				$model = new $className;
			} else {
				//Must be help, so tell it some info
				$model->modelName = $modelName;
				$model->plural = $plural;
			}
			
			//See if the action is supported
			if( $plural ) {
				switch($method) {
					case 'GET': $action = 'index'; break;
					case 'POST': $action = 'bulk_create'; break;
					case 'PUT': $action = 'bulk_update'; break;
					case 'DELETE': $action = 'bulk_delete'; break;
					default: 
						self::sendError("Unsupported method: $method", $type, 404);
						return;
				}
			} else {
				switch($method) {
					case 'GET': $action = 'fetch'; break;
					case 'POST': $action = 'create'; break;
					case 'PUT': $action = 'update'; break;
					case 'DELETE': $action = 'delete'; break;
					default: 
						self::sendError("Unsupported method: $method", $type, 404);
						return;
				}
			}
			if( !method_exists($model, $action) ) {
				//Still ask, since the model class might proxy some things, like bulk create.
				$action = $model->getActionMethod($action);
				if( !$action ) {
					self::sendError("Unsupported method: $method ($modelName.$action)", $type, 404);
					return;
				}
			}
			
			//Parse params
			$params = array();
			//if( $method == 'GET' ) { //TODO: is this a good distinction?
				//Query params
				parse_str($_SERVER['QUERY_STRING'],$params);
			/*} else {
				//Post-style params
				foreach($_POST as $key => $val) {
					if( substr($key,0,1) == '_' ) continue; //magic variables skipped
					$params[$key] = $val;
				}
			}*/
			$params['_response_type'] = $type; //in case the model wants to know
		
			//Grab body
			$root = null;
			if( $method != 'GET' && $type == 'json' ) {
				if( isset($_POST['_data']) ) {
					$root = json_decode($_POST['_data']);
				} else {
					$body = http_get_request_body();
					$root = json_decode($body);
					if( $body != "" && $root === null )
						throw new RestException(null,"Malformed JSON.");
				}
			}
			
			//Run command
			$result = $model->$action($id, $root, $params);
			
			//Return contents
			if( $type == 'json' ) {
				Page::sendJSON($result);
			} else if( $type == 'html' ) {
				if( is_array($result) || is_object($result) ) {
					//Clearly not HTML, so let's kinda fix that.
					ob_start();
					echo "<html><body><pre>";
					var_dump($result);
					echo "</pre></body></html>";
					$result = ob_get_contents();
					ob_end_clean();
				}
				echo $result; //converts to a string, hopefully it's HTML.
			} else if( $type == 'xml' ) {
				Page::sendXML($result);
			} else if( $type == 'md' ) {
				header('Content-Type: text/markdown');
				echo $result;
			}
		} catch(RestException $re) {
			self::sendErrorObject(self::exceptionReportObject($re, '', $modelName, 
					$action), $type, $re->httpCode);
		} catch(Exception $e) {
			self::sendError("Encountered an error while processing request: ".$e->getMessage(),
					 $type, 500);
		}
	}
	
	/**
	 * Format an error message appropriately for the type and send it.
	 */
	static function sendError($msg, $type, $code = 500) {
		Page::sendStatusCode($code);
		if( $type == 'html' ) {
			echo "<html><head><title>Error</title></head><body>";
			echo "<h1>Error</h1><p>$msg</p>";
			echo "</body></html>";
		} else {
			$obj = new stdClass;
			$obj->error = $msg;
			
			if( $type == 'xml' )
				Page::sendXML($obj);
			else
				Page::sendJSON($obj);
		}
	}
	
	static function sendRestError($model, $action, $field, $message, $type = 'json', $code = 400) {
		Page::sendStatusCode($code);
		
		$obj = new stdClass;
		if($model) $obj->model = $model;
		if($action) $obj->action = $action;
		if($field) $obj->field = $field;
		$obj->message = $message;
		
		if( $type == 'html' ) {
			echo "<html><head><title>Error</title></head><body>";
			echo "<h1>Error</h1>";
			echo "<pre>";
			var_dump($obj); //sure we could format this better, but who's using HTML?
			echo "</pre>";
			echo "</body></html>";
		} else {
			$wrapper = array('error' => $obj);
			if( $type == 'xml' )
				Page::sendXML($wrapper);
			else
				Page::sendJSON($wrapper);
		}
	}
	
	static function sendErrorObject($obj, $type = 'json', $code = 400) {
		Page::sendStatusCode($code);
		
		if( $type == 'html' ) {
			echo "<html><head><title>Error</title></head><body>";
			echo "<h1>Error</h1>";
			echo "<pre>";
			var_dump($obj); //sure we could format this better, but who's using HTML?
			echo "</pre>";
			echo "</body></html>";
		} else {
			/*Problematic: sometimes $obj is type array. In any case, it should always be
			  wrapped already.
			if( !isset($obj->error) && !isset($obj->errors) )
				$wrapper = array('error' => $obj);
			else
				$wrapper = $obj;*/
			if( $type == 'xml' )
				Page::sendXML($obj);
			else
				Page::sendJSON($obj);
		}
	}
	
	/**
	 * Construct an object suitable for returning from the API.
	 */
	public function exceptionReportObject($e, $fieldPrefix = "", $model = null, $action = null) {
		if( $e instanceof RestMultiException ) {
			$obj = new stdClass;
			if( $model ) $obj->model = $model;
			if( $action ) $obj->action = $action;
			$obj->errors = array();
			foreach($e->messages as $field => $list) {
				foreach($list as $msg) {
					$eo = new stdClass;
					$eo->field = $fieldPrefix.$field;
					$eo->message = $msg;
					$obj->errors[] = $eo;
				}
			}
			$obj->message = $e->getMessage();
			return $obj;
		} else if( $e instanceof RestException ) {
			$obj = new stdClass;
			if( $model ) $obj->model = $model;
			if( $action ) $obj->action = $action;
			if( $e->field ) $obj->field = $fieldPrefix.$e->field;
			$obj->message = $e->getMessage();
			
			/*I wouldn't really want this, occasionally useful.
			if( defined('ENV') && constant('ENV') == 'dev' )
				$obj->backtrace = $e->getTrace();//*/
			
			return array('error' => $obj);
		} else {
			$obj = new stdClass;
			$obj->error = $e->getMessage();
			return $obj;
		}
	}
}

?>