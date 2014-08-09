<?php
/**
 * @author Everett Morse
 * Copyright (c) 2013 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * 
 */

class App extends ActiveDbObject {
	
	function __construct($id=null) {
		#$this->belongs_to['parent'] = array('parents','parent_id');
		parent::__construct($id);
		
		$this->addValidation('name', 'min_length', 3);
		$this->addValidation('name', 'max_length', 128);
		$this->addValidation('api_key', 'exists', "");
	}
	
	/**
	 * Creates a new random API key.
	 */
	public function generateKey() {
		$text = rand().date('Y m d H i s').rand();
		$this->api_key = sha1($text);
	}
	
	/**
	 * Check a priv related to which app key is used.
	 * "Super App" is an app that can access features that transcend a single group, like
	 * creating new app keys.
	 */
	public static function requireSuperApp() {
		//Only the "super app" can do certain things.
		if( Page::getVar('app')->id != 1 )
			throw new RestException(null,'Not allowed',403);
	}
	
	public static function isSuperApp() {
		return Page::getVar('app')->id == 1;
	}
	
}
?>