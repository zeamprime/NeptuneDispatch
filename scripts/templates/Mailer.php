<?php
/**
 * @author Everett Morse
 * Copyright (c) 2015 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * Send a {%ClassName%} email.
 */

class {%ClassName%}Mailer extends Mailer
{
	public function __construct() {
		parent::__construct();
		
		$this->Subject = "Welcome";
		$this->addAddress('john@example.com', 'John Smith');
		//$this->addReplyTo('webmaster@example.com', 'Web Master');
		
		$this->view = 'mailers/{%file_name%}';
		$this->data['Name'] = 'John Smith';
	}
}
?>