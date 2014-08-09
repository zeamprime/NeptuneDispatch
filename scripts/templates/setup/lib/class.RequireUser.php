<?php
/**
 * @author Everett Morse
 * Copyright (c) 2013 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * Makes sure we have a user.  This may have been loaded already (HMAC does it), so check
 * the shared globals first.
 * 
 * IMPORTANT: HMAC requires that the user's token be kept secret and that the token's ID
 * is sent, but without HMAC we use the token itself. So if HMAC is enabled make sure it
 * loads BEFORE this class.
 * 
 * BOTH HMAC and RequireUser are needed to require a user, since HMAC considers the user 
 * to be optional.
 * 
 * EXCEPTIONS: We do not require a user for 'help' or 'login' paths.
 * TODO: have a configurable exceptions list and move this class into the engine folder.
 */

class RequireUser extends Middleware
{
	
	/**
	 * Check for a user ID.
	 * 
	 * @param IO $path : array - the path components.
	 * @param IO $ext : string - the file extension or converted request type.
	 */
	public function apply(&$path, &$ext) {
		if( $path[0] == 'help' ) return;
		if( $path[0] == 'login' ) return;
		
		//If using HMAC, then it already loaded the user.  Otherwise we will load it but
		// expecting the user's token rather than the token ID.
		if( Page::hasVar('user') ) return;
		
		$user = self::verifyRequestUser(true);
		if( $user === null )
			throw new RestException("UserId", "User required.");
		Page::setVar('user',$user);
	}
	
	/**
	 * Shared with HMAC. Looks for the user in request headers and cookies. Checks that
	 * the token has not expired.
	 * 
	 * @param $isToken : bool - whether the second part of the user cookie/header is the
	 *                          token (non-HMAC) or just the token's id (HMAC, since token
	 *                          is part of the composite key).
	 * @param OUT $token : UserToken (Opt) - if you want this back.
	 * @param $allowCookie : bool (Opt) - whether we should check for a cookie too.
	 * @returns User, or null if not sent
	 * @throws RestException if a user was sent but is invalid.
	 */
	public static function verifyRequestUser($isToken, &$token = null, $allowCookie = false) {
		//if($allowCookie) var_dump($_COOKIE);
		
		$userId = Page::getRequestHeader('X-USER-KEY');
		if( $userId === false ) {
			if( $allowCookie && isset($_COOKIE['userKey']) ) {
				$userId = $_COOKIE['userKey'];
			} else
				return null;
		}
		
		list($userId,$tokenKey) = explode(':',$userId);
		$user = User::find(intval($userId));
		if( !$user )
			throw new RestException("UserId", "Invalid user.");
		
		if( $isToken )
			$token = UserToken::where(array('token' => $tokenKey));
		else
			$token = UserToken::where(array('id' => $tokenKey));
		if( count($token) == 0 )
			throw new RestException("UserId", "Invalid user.");
		$token = $token[0];
		if( $token->isExpired() )
			throw new RestException("UserId", "Token expired.");
		
		return $user;
	}
	
	
}
?>