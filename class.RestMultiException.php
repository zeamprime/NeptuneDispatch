<?php
/**
 * @author Everett Morse
 * Copyright (c) 2013 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * REST API Exception that holds multiple field => message pairs.
 */


class RestMultiException extends RestException
{
	public $messages;
	
	//400 = Bad Request
	//401 = Unauthorized
	//403 = Forbidden
	//404 = Not Found
	//405 = Method Not Allowed
	//406 = Not acceptable (accepts header)
	
	public function __construct($errors, $message = "", $code = 400) {
		parent::__construct("",$message);
		$this->messages = $errors;
		$this->httpCode = $code;
	}
	
	public function toString() {
		return "RestMultiException ({$this->httpCode}): {$this->message}";
	}
	
}
?>
