<?php
/**
 * @author Everett Morse
 * Copyright (c) 2014 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * 
 */

class {%Name%} extends ActiveDbObject {
	
	function __construct($id=null) {
		#$this->belongs_to['parent'] = array('parents','parent_id');
		parent::__construct($id);
		
		//$this->addValidation('name', 'min_length', 3);
	}
}
?>
