<?php
/**
 * @author Everett Morse
 * @copyright (c) 2013 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * Home page with intro, login, etc.
 */

class HomeController extends AppController {
	
	function __construct() {
		$this->layout = 'home';
		//$this->filters[] = '_login';
		parent::__construct();
	}
	
	function index() {
		$this->data['scripts'][] = "js/jquery.js";
		$this->data['scripts'][] = "js/jquery.cookie.js";
		$this->data['scripts'][] = "js/jquery.storageapi.min.js";
		$this->data['scripts'][] = "js/underscore.js";
		$this->data['scripts'][] = "js/date.js";
		$this->data['scripts'][] = "js/sha1.js";
		$this->data['scripts'][] = "js/api.js";
		
		//$app = App::find(2); //Web Client
		//$this->data['appKey'] = '2:'.$app->api_key;
		
		$this->render('home/index');
	}
	
}

?>