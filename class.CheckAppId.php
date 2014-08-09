<?php
/**
 * @author Everett Morse
 * Copyright (c) 2013 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * Checks for the App ID and verifies it's key.
 * 
 * This should NOT be used with HMAC, rather instead of it. Since this method involves
 * sending the secret key (here used as a password, and you should be using HTTPS).
 */

class CheckAppId extends Middleware
{
	
	/**
	 * Check the App ID and Key of the request.
	 * 
	 * @param IO $path : array - the path components.
	 * @param IO $ext : string - the file extension or converted request type.
	 */
	public function apply(&$path, &$ext) {
		if( $path[0] == 'help' ) return;
		
		$key = Page::getRequestHeader('X-APP-ID');
		if( $key === false )
			throw new RestException("AppId", "Application ID is required.");
		list($id,$key) = explode(':',$key);
		if( !$id || !$key )
			throw new RestException("AppId", "Malformed application ID.");
		
		$app = new App(intval($id));
		if( !$app )
			throw new RestException("AppId", "Invalid application ID.");
		if( $app->api_key != $key )
			throw new RestException("AppId", "Invalid application ID.");
		
		Page::setVar('app',$app);
	}
	
	
}
?>