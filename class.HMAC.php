<?php
/**
 * @author Everett Morse
 * Copyright (c) 2013 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * Implements HMAC signature checking.
 */

class HMAC extends Middleware
{
	
	/**
	 * Check the HMAC signature of the request.
	 * 
	 * @param IO $path : array - the path components.
	 * @param IO $ext : string - the file extension or converted request type.
	 */
	public function apply(&$path, &$ext) {
		Page::setVar('useHMAC',true);
		
		if( $path[0] == 'help' ) return;
		
		//Check app ID
		$key = Page::getRequestHeader('X-APP-ID');
		if( $key === false )
			throw new RestException("AppId", "Application ID is required.");
		if( strpos($key,':') !== false ) //only the ID please
			throw new RestException("AppId", "Invalid application ID.");
		$app = new App(intval($key));
		if( !$app )
			throw new RestException("AppId", "Invalid application ID.");
		
		Page::setVar('app',$app);
		
		//Check timestamp
		$signTimeStr = Page::getRequestHeader('X-HASH-TIME');
		if( $signTimeStr === false )
			throw new RestException("HashTime", "Hashing time is required.");
		$signTime = strtotime($signTimeStr);
		if( $signTime == -1 || $signTime < time() - 60 )
			throw new RestException("HashTime", "Signature has expired.");
		
		//Check signature
		$hash = Page::getRequestHeader('X-HASH');
		if( $hash === false )
			throw new RestException("Hash", "Hash signature is required.");
		
		//Includes: url, query string, time, app ID, user ID if present, and message body.
		$url = Page::getRequestSchema().Page::getServerName().$_SERVER['REQUEST_URI'];
		$url = explode('?',$url); //REQUEST_URI includes the query string
		$url = $url[0];
		
		$text = $url.$_SERVER['QUERY_STRING'].$signTimeStr.$app->id;
		
		$user = RequireUser::verifyRequestUser(false, $token);
		if( $user !== null ) {
			$apiKey = $app->api_key . $token->token;
			$text .= $user->id;
			Page::setVar('user', $user); //save for privilege checks later
		} else
			$apiKey = $app->api_key;
		
		$text .= http_get_request_body();
		$computed = self::hmacsha1($apiKey,$text);
		//echo "$computed\n$text\n$apiKey\n";exit;		
		if( $computed != $hash ) {
			if( defined('ENV') && constant('ENV') == 'dev' )
				throw new RestException("Hash", "Invalid signature.\n".
						"$computed\n$text\n$apiKey\n");
			else
				throw new RestException("Hash", "Invalid signature.");
		}
	}
	
	
	//http://laughingmeme.org/code/hmacsha1.php.txt
	//Which says "sample data from OAuth 1.0 Core, v5" so I assume this is compat w/ that.
	//Example key is a 33 char string.
	// Modified function names etc. a bit.
	public static function hmacsha1($key, $data) {
		return base64_encode(self::raw_hmacsha1($key, $data));
	}
	public static function raw_hmacsha1($key,$data) {
		$blocksize=64;
		$hashfunc='sha1';
		if (strlen($key)>$blocksize)
			$key=pack('H*', $hashfunc($key));
		$key=str_pad($key,$blocksize,chr(0x00));
		$ipad=str_repeat(chr(0x36),$blocksize);
		$opad=str_repeat(chr(0x5c),$blocksize);
		$hmac = pack(
					'H*',$hashfunc(
						($key^$opad).pack(
							'H*',$hashfunc(
								($key^$ipad).$data
							)
						)
					)
				);
		return $hmac;
	}
	
	/**
	 * Checks if HMAC is in the config.  If already set then no need to check.
	 */
	public static function usingHMAC() {
		if( Page::hasVar('useHMAC') )
			return true;
		
		if( defined('API_MIDDLEWARE') && 
				strpos(constant('API_MIDDLEWARE'),'HMAC') !== false ) {
			Page::setVar('useHMAC',true);
			return true;
		}
		return false;
	}
	
}
?>