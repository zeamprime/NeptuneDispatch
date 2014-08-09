<?php
/**
 * @author Everett Morse
 * Copyright (c) 2013 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * Exception that holds specific detail for API errors, like what field is invalid and why.
 */


class RestException extends Exception
{
	public $field;	//e.g. "name", or a path like "group.1.details.name"
	public $httpCode; 	//500, 404, etc.
	
	//400 = Bad Request
	//401 = Unauthorized
	//403 = Forbidden
	//404 = Not Found
	//405 = Method Not Allowed
	//406 = Not acceptable (accepts header)
	
	public function __construct($field, $message, $code = 400) {
		parent::__construct($message);
		$this->field = $field;
		$this->httpCode = $code;
	}
	
	public function toString() {
		if( $field )
			return "RestException ({$this->httpCode}) in '{$this->field}': {$this->message}";
		else
			return "RestException ({$this->httpCode}): {$this->message}";
	}
	
}
?>
