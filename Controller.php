<?php
/**	(c) 2011 Everett Morse
*	@author Everett Morse - www.neptic.com
*	
*	Base class for controllers.  Gives some features like before filter, REST routing, etc.
*/

class Controller {
	private $routes = array();
	protected $method = null;
	protected $action = null;
	
	protected $filters = array();
	
	protected $layout = null; //default layout
	protected $data = array(); //passed to views
	
	function __construct() {
		if( isset($_SESSION['flash']['errors']) )
			$this->data['errors'] = $_SESSION['flash']['errors'];
		if( isset($_SESSION['flash']['notices']) )
			$this->data['notices'] = $_SESSION['flash']['notices'];
		if( isset($_SESSION['flash']) )
			unset($_SESSION['flash']);
	}
	
	//Filters
	public function beforeFilter($action, $method) {
		foreach($this->filters as $filter) {
			if( is_string($filter) ) {
				$this->$filter($action, $method);
			} else if( is_array($filter) ) {
				list($name, $only, $except) = $filter;
				if( is_array($only) ) { //uses whitelist
					if( in_array($action, $only) )
						$this->$name($action, $method);
				} else if( is_array($except) ) { //uses blacklist
					if( !in_array($action, $except) )
						$this->$name($action, $method);
				} else
					$this->$name($action, $method); //normal
			}
		}
	}
	
	
	//Routing, useful for mapping POST, GET, PUT, DELETE to different action functions
	public function route($action, $method) {
		$route = null;
		if( isset($this->routes[$action]) ) {
			$route = $this->routes[$action];
		} else if( !method_exists($this,$action) && isset($this->routes['__any']) ) {
			$route = $this->routes['__any'];
		}
		
		if( $route !== null ) {
			if( isset($route[$method]) )
				$action = $route[$method]; //name of the action to call
			else if( isset($route['any']) )
				$action = $route['any'];
		}
		
		$this->action = $action;
		$this->method = $method;
		
		return $action;
	}
	protected function onGet($action, $method) {
		$this->routes[$action]['GET'] = $method;
	}
	protected function onPost($action, $method) {
		$this->routes[$action]['POST'] = $method;
	}
	protected function onPut($action, $method) {
		$this->routes[$action]['PUT'] = $method;
	}
	protected function onDelete($action, $method) {
		$this->routes[$action]['DELETE'] = $method;
	}
	protected function onAny($action, $method) {
		$this->routes[$action]['any'] = $method;
	}
	
	
	//Render helper
	protected function render($view = null) {
		if( $view === null )
			$view = $this->action;
		Page::sendView($view,$this->data, $this->layout);
	}
	
	function redirect($url) {
		if( isset($this->data['errors']) )
			$_SESSION['flash']['errors'] = $this->data['errors'];
		if( isset($this->data['notices']) )
			$_SESSION['flash']['notices'] = $this->data['notices'];
		header("Location: $url\n");
	}
	
	//Some helper actions
	public function deny() {
		Page::sendError("Access denied");
	}
	
}

?>