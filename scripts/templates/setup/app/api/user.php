<?php
/**
 * @author Everett Morse
 * @copyright (c) 2013 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * A user.
 */

class UserRestController extends RestModel
{
	public function __construct() {
		parent::__construct();
		
		//Configure
		$this->allowAttrsOn('update', array('password'));
		$this->allowAttrsOn('create', array('username','password'));
		$this->requireAttrsOn('create', array('username'));
		//$this->restrictReturnAttrsTo(array(...));
	}
		
	////// Metadata for generating help /////////
	protected function metaDescription() {
		return "A user.";
	}
	protected function metaActionDesc($action) {
		switch($action) {
			case 'create': return "When creating a user, if no password is given one will be"
					."generated.";
		}
		return '';
	}
	protected function metaFieldDesc($field) {
		switch($field) {
			case 'username': return "User name for login and display.";
			case 'password': return "Secret used for logging in.";
			case 'created_at': return "When the user was created.";
		}
		return '';
	}
	protected function metaSupportedFiltersFor($action) {
		if( $action == 'index' )
			return array(
				'order',
				array(
					'name' => 'limit',
					'desc'=>'Return only the most N users.'
				)
			);
		if( $action == 'fetch' )
			return array('rel','short');
		return array();
	}
	protected function metaFillExampleData($eg) {
		$eg->username = "TheMaster";
		$eg->password = "abcdefg";
		$eg->created_at = date('Y-m-d H:i:s');
	}
	protected function metaSecurity($action) {
		switch($action) {
			case 'fetch':
			case 'index': return "Any user can view the list, minus passwords.";
			case 'create': return "Anyone can register a new user.";
			case 'update': return "Only a user may update his/her password.";
			case 'delete': return "Deleting is not allowed.";
		}
	}
	///////// End Metadata ///////
	
	
	
	/**
	 * Return a listing of available records.
	 */
	public function index($id, $data, $params) {
		if( isset($params['order']) )
			$order = $this->parseOrderParam($params['order'],true);
		else
			$order = "created_at desc";
		
		$limit = 0;
		if( isset($params['limit']) ) {
			$limit = intval($params['limit']);
		}
		
		if( $limit > 0 ) {
			$users = User::where(array('limit' => $limit), $order);
		} else {
			$user = User::all($order);
		}
		
		if( !User::IsSuperUser() ) {
			$filteredObjs = array();
			$shortAttrs = array('username','created_at');
			foreach($events as $obj) {
				$filteredObjs[] = RestModel::filterAttrs($obj, $shortAttrs);
			}
			return self::wrap($filteredObjs, 'users');
		} else
			return RestModel::wrap($entries, 'users');
	}
	
	/**
	 * Return the requested record.
	 */
	public function fetch($id, $data, $params) {
		$obj = User::find($id);
		if( !$obj )
			throw new RestException($id, "Not found", 404);
		
		$res = RestModel::wrap($obj, 'user');
		return $res;
	}
	
	/**
	 * Create a new record and return it.
	 */
	public function create($id, $data, $params) {
		$this->constrainAttrs($data, 'create');
		
		$obj = new User();
		$obj->setValues($data);
		
		//Set defaults for values that were not given
		if( !isset($obj->password) ) {
			$str = SHA1(date('Y-m-d H:i:s').rand());
			$obj->password = substr($str,0,12);
		}
		
		$this->ensureValid($obj);
		$obj->save();
		return $this->returnObj($obj);
	}
	
}
?>
