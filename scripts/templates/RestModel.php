<?php
/**
 * @author Everett Morse
 * Copyright (c) 2014 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * A {%Name%}.
 */

class {%Name%}RestController extends RestModel
{
	public function __construct() {
		parent::__construct();
		
		//Configure
		$this->allowAttrsOn('update', array('name','description'));
		$this->allowAttrsOn('create', array('name','description'));
		$this->requireAttrsOn('create', array('name'));
		//$this->restrictReturnAttrsTo(array(...));
	}
	
	////// Metadata for generating help /////////
	protected function metaDescription() {
		return "A {%Name%} is ...";
	}
	protected function metaActionDesc($action) {
		switch($action) {
			case 'fetch': return "...";
			case 'update': return "...";
		}
		return '';
	}
	protected function metaFieldDesc($field) {
		switch($field) {
			case 'name': return "...";
			case 'description': return "...";
		}
		return '';
	}
	protected function metaSupportedFiltersFor($action) {
		if( $action == 'index' )
			return array('order');
		return array();
	}
	protected function metaFillExampleData($eg) {
		$eg->name = "Abc";
		$eg->created_at = date('Y-m-d H:i:s');
		$eg->modified_at = date('Y-m-d H:i:s');
	}
	protected function metaSecurity($action) {
		return "You must be logged in.";
	}
	///////// End Metadata ///////
	
	
	
	/**
	 * Return a listing of available records.
	 */
	public function index($id, $data, $params) {
		if( isset($params['order']) )
			$order = $this->parseOrderParam($params['order'],true);
		
		return RestModel::wrap({%Name%}::all($order), '{%names%}');
		//return $this->returnObjs({%Name%}::all($order)); //restricts returned attrs
	}
	
	/**
	 * Return the requested record.
	 */
	public function fetch($id, $data, $params) {
		$obj = {%Name%}::find($id);
		if( !$obj )
			throw new RestException($id, "Not found", 404);
		
		//returnObj is exactly this unless we restrict what attrs are returned.
		return RestModel::wrap($obj, '{%name%}');
		//return $this->returnObj($obj);
	}
	
	/**
	 * Create a new record and return it.
	 */
	public function create($id, $data, $params) {
		$this->constrainAttrs($data, 'create');
		$obj = new {%Name%}();
		$obj->setValues($data);
		
		//Set defaults for values that were not given
		//...
		
		$this->ensureValid($obj);
		
		$obj->save();
		return $this->returnObj($obj);
	}
	
	/**
	 * Update the specified record. Returns status.
	 */
	public function update($id, $data, $params) {
		$obj = {%Name%}::find($id);
		if( !$obj )
			throw new RestException('id', "Not found", 404);
		
		$this->constrainAttrs($data, 'update');
		$obj->setValues($data);
		$this->ensureValid($obj);
		
		$obj->save();
		return $this->returnObj($obj);
	}
	
	/**
	 * Delete the specified record.
	 */
	public function delete($id, $data, $params) {
		$obj = {%Name%}::find($id);
		if( !$obj )
			throw new RestException('id', "Not found", 404);
		$obj->delete();
		return true;
	}
	
}
?>
