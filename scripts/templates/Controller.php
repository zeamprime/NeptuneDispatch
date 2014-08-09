<?php
/**
 * @author Everett Morse
 * Copyright (c) 2014 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * 
 */

class {%Name%}Controller extends AppController {
	
	function __construct() {
		#$this->onPut('record','record_create');
		
		#$this->filters[] = '_login';
		
		#$this->layout = 'app';
		parent::__construct();
	}
	
	function index() {
		$this->render('{%name%}/index');
	}
	
}

?>
