<?php
/**
 *	Base controller for all your web app controllers.
 *	You can add shared functionality here (e.g. authentication).
 */
class AppController extends Controller {
	
	function __construct() {
		session_start();
		
		#$this->layout = 'app';
		parent::__construct();
		session_start();
	}
	
	
	protected function _login() {
		$user = RequireUser::verifyRequestUser(!HMAC::usingHMAC(), $token, true);
		if( $user ) {
			$this->data['user'] = $user;
			$this->data['token'] = $token;
		}
	}
	protected function _require_login() {
		if( !isset($this->data['user']) ) {
			Page::sendError("You must log in to view this page.");
		}
	}
	
}

?>