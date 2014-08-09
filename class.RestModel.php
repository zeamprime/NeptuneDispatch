<?php
/**
 * @author Everett Morse
 * Copyright (c) 2013 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * A "model" in the REST API. This is like a controller for this conceptual type. Sub classes 
 * implement the create, update, etc. for single/plural GET/POST/PUT/DELETE requests.
 *
 * single GET -> fetch(id)
 * single POST -> create
 * single PUT -> update(id)
 * single DELETE -> delete(id)
 *
 * plural GET -> index
 * plural POST -> bulk_create or iterates calling create
 * plural PUT -> bulk_update(ids) or iterates calling update(id)
 * plural DELETE -> bulk_delete(ids) or iterates calling delete(id)
 */

class RestModel
{
	protected $modelName;
	protected $typeSingular;
	protected $typePlural;
	
	//Attribute constraints
	protected $allowed_update_attrs = null;
	protected $required_update_attrs = null;
	protected $allowed_create_attrs = null;
	protected $required_create_attrs = null;
	protected $return_attrs = null;
	
	
	public function __construct($name = null) {
		if( !$name ) {
			$name = get_class($this);
			if( substr($name,-14) == "RestController")
				$name = substr($name, 0, strlen($name)-14);
			else if( substr($name,-9) == "RestModel")
				$name = substr($name, 0, strlen($name)-9);
			$this->modelName = Util::underscore($name);
		} else {
			$this->modelName = $name;
		}
		
		//Compute type once. E.g. GroupRestModel -> Group -> group and groups.
		$this->typeSingular = $this->modelName;//Util::underscore($this->modelName);
		$this->typePlural = Util::plural($this->typeSingular);
	}
	
	/**
	 * Configuration routine
	 */
	protected function allowAttrsOn($action, $list) {
		if( $action == 'update' )
			$this->allowed_update_attrs = $list;
		else if( $action == 'create' )
			$this->allowed_create_attrs = $list;
	}
	
	/**
	 * Configuration routine
	 */
	protected function requireAttrsOn($action, $list) {
		if( $action == 'update' )
			$this->required_update_attrs = $list;
		else if( $action == 'create' )
			$this->required_create_attrs = $list;
	}
	
	protected function restrictReturnAttrsTo($list) {
		$this->return_attrs = $list;
	}
	
	/**
	 * Used to dynamically proxy various actions rather than implement the specific method.
	 * You can return a generic action handler and record the action value for later.
	 * 
	 * @returns string method name or null if not supported.
	 */
	public function getActionMethod($action) {
		
		//Default implementation proxies the bulk* methods only.
		if( substr($action,0,5) === 'bulk_' ) {
			$action = substr($action,5);
			if( method_exists($this, $action) )
				return "iterate_bulk_$action";
		}
		
		return null;
	}
	
	/**
	 * Does a bulk operation by iterating over the input array and calling the single operation.
	 * Results are collected in a return array.
	 * 
	 * $id is ignored.
	 * $root must be an array of roots as input or null. If null, $params['create_count'] must exist
	 *   to indicate how many objects to create.
	 */
	public function iterate_bulk_create($id, $root, $params) {
		$results = array();
		if( $root === null ) {
			if( !isset($params['create_count']) )
				throw new Exception("Bulk create expects an array of input values");
			
			$cnt = (int)$params['create_count'];
			for($i = 0; $i < $cnt; $i++) {
				try {
					$results[] = $this->create($i, null, $params);
				} catch(Exceptoin $e) {
					$results[] = RestDispatch::exceptionReportObject($e,$i.'.', 
							$this->modelName, 'create');
				}
			}
		} else {
			if( !is_array($root) )
				throw new Exception("Bulk create expects an array of input values");
			
			$i = 0;
			foreach($root as $single_root) {
				try {
					$results[] = $this->create($id, $single_root, $params);
				} catch(Exception $e) {
					$results[] = RestDispatch::exceptionReportObject($e, $i.".", 
							$this->modelName, 'create');
				}
				$i++;
			}
		}
		return $results;
	}
	
	/**
	 * Does a bulk operation by iterating over the input array and calling the single operation.
	 * Results are collected in a return array.
	 * 
	 * $id is ignored.
	 * $root must be an object with keys 'ids' and 'roots' holding arrays.
	 */
	public function iterate_bulk_update($id, $root, $params) {
		if( !is_object($root) )
			throw new Exception("Bulk update expects an object with keys 'ids' and 'roots'.");
		if( !isset($root->ids) || !is_array($root->ids) )
			throw new Exception("Bulk update expects an array of ids.");
		if( !isset($root->roots) || !is_array($root->roots) )
			throw new Exception("Bulk update expects an array of roots.");
		
		foreach($root->ids as $i => $id) {
			$single_root = $root->roots[$i];
			try {
				$results[] = $this->update($id, $single_root, $params);
			} catch(Exception $e) {
				$results[] = RestDispatch::exceptionReportObject($e, $id.".", $this->modelName,
						"update");
			}
		}
		
		return $results;
	}
	
	/**
	 * Does a bulk operation by iterating over the input array and calling the single operation.
	 * Results are collected in a return array.
	 * 
	 * $id is ignored.
	 * $root must be an object with key 'ids'.
	 */
	public function iterate_bulk_delete($id, $root, $params) {
		if( !is_object($root) )
			throw new Exception("Bulk delete expects an object with keys 'ids' and 'roots'.");
		if( !isset($root->ids) || !is_array($root->ids) )
			throw new Exception("Bulk delete expects an array of ids.");
		
		foreach($root->ids as $i => $id) {
			try {
				$results[] = $this->delete($id, null, $params);
			} catch(Exception $e) {
				$results[] = RestDispatch::exceptionReportObject($e, $id.".", $this->modelName,
						"delete");
			}
		}
	}
	
	/////////////////////// HELPERS //////////////////////////////
	
	/**
	 * Helper to wrap objects in a root node.
	 */
	public static function wrap($data, $type) {
		$obj = array($type => $data);
		return $obj;
	}
	
	/**
	 * Filter attributes that should not be serialized and returned.
	 * Operates on the given data array (vs make copy) if possible.
	 * 
	 * @param $data : obj | array - If obj, will call getAttrs first.
	 * @return assoc array
	 */
	public static function filterAttrs($data, $allowed) {
		if( is_object($data) ) {
			if( $data instanceof ActiveDbObject || method_exists($obj, 'getAttrs') )
				$res = $data->getAttrs();
			else
				$res = (array)$data;
		} else
			$res = $data;
		
		foreach($res as $key => $value) {
			if( !in_array($key, $allowed) ) {
				unset($res[$key]);
			}
		}
		
		return $res;
	}
	
	/**
	 * Apply the appropriate filter and wrap the return.
	 * 
	 * @param $obj : DbObject - the object to return
	 */
	public function returnObj($obj) {
		if( $this->return_attrs === null ) {
			return self::wrap($obj, $this->typeSingular);
		} else {
			return self::wrap(self::filterAttrs($obj, $this->return_attrs), $this->typeSingular);
		}
	}
	
	public function returnObjs($objs) {
		if( $this->return_attrs === null ) {
			return self::wrap($objs, $this->typePlural);
		} else {
			$filteredObjs = array();
			foreach($objs as $obj) {
				$filteredObjs[] = self::filterAttrs($obj, $this->return_attrs);
			}
			return self::wrap($filteredObjs, $this->typePlural);
		}
	}
	
	/**
	 * Verify that no attributes are being sent other than the allowed ones.
	 * Throw errors otherwise.
	 */
	public static function restrictAttrs($data, $allowedAttrs) {
		$data = (array)$data;
		foreach($data as $attr => $value) {
			if( !in_array($attr, $allowedAttrs) )
				throw new RestException($attr, "Not allowed");
		}
	}
	
	/**
	 * Ensure that the required fields are present.
	 */
	public static function requireAttrs($data, $requiredAttrs = array() /*, $className = null*/) {
		$data = (array)$data;
		foreach($requiredAttrs as $attr) {
			if( !isset($data[$attr]) )
				throw new RestException($attr, "Missing required item.");
			if( $data[$attr] == "" )
				throw new RestException($attr, "Required item is empty.");
		}
	}
	
	/**
	 * Apply allowed/required attributes constraints to the input
	 */
	protected function constrainAttrs($data, $action) {
		if( $action === 'update' ) {
			if( $this->required_update_attrs !== null )
				self::requireAttrs($data, $this->required_update_attrs);
			if( $this->allowed_update_attrs !== null )
				self::restrictAttrs($data, $this->allowed_update_attrs);
		} else if( $action === 'create' ) {
			if( $this->required_create_attrs !== null )
				self::requireAttrs($data, $this->required_create_attrs);
			if( $this->allowed_create_attrs !== null )
				self::restrictAttrs($data, $this->allowed_create_attrs);
		}
	}
	
	/**
	 * Runs validators for the given model object, constructs an appropriate exception if
	 * any fail.
	 */
	protected static function ensureValid($obj) {
		$errors = array();
		if( !$obj->isValid($errors) ) {
			if( count($errors) == 1 ) {
				$keys = array_keys($errors);
				if( count($errors[$keys[0]]) == 1 ) {
					//Only one error
					$msg = $errors[$keys[0]][0];
					if( !$msg ) $msg = "Not valid";
					throw new RestException($keys[0], $msg);
				}
			}
			//Need a multi-exception
			throw new RestMultiException($errors, "Not valid");
		}
	}
	
	/**
	 * Interprets the 'order' filter from query params.
	 * @param $order : string - key, optionally prefixed with '-' or '+'.
	 * @param $validate : bool (Opt) - If true, make sure the key is a visible attr.
	 * 		This uses return_attrs is set, otherwise it will ask the model class for it's 
	 * 		schema. If not valid, throws a REST exception.
	 *                                 
	 * @returns value suitable for Db class's orderBy paramers, e.g. "name desc".
	 */
	public function parseOrderParam($order, $validate = false) {
		$dir = substr($order,0,1);
		if( $dir == '>' ) {
			$key = substr($order,1);
			$dir = ' desc';
		} else if( $dir == '<' ) {
			$key = substr($order,1);
			$dir = ' asc';
		} else {
			$key = $order;
			$dir = '';//default asc
		}
		if( $validate ) {
			$valid = false;
			if( $this->return_attrs !== null ) {
				$valid = in_array($key, $this->return_attrs);
			} else {
				$className = Util::camelizeClass($this->modelName);
				$instance = new $className();
				if( $instance instanceof ActiveDbObject || 
					method_exists($instance, 'getSchema')
				) {
					$schema = $instance->getSchema();
				} else {
					throw new RestException('order', 
							"Cannot load object schema to validate order key.", 500);
				}
				$valid = isset($schema[$key]);
			}
			if( !$valid ) {
				throw new RestException('order','Cannot sort by '.$key);
			}
		}
		return $key.$dir;
	}
	
	/**
	 * Parses a query string parameter of type boolean. Throws an exception if the given
	 * value is not a boolean.
	 * 
	 * Official docs say only JSON types accepted. But in the interest of user-
	 * friendliness I'll accept y/n and 1/0 as well since they are commonly used here.
	 *
	 * @param $name : string - parameter field name for error reporting
	 * @param $value : string - value to check
	 * @returns boolean or throws exception
	 */
	public static function parseBoolParam($name, $value) {
		$value = strtolower($value);
		if( $value == 'y' || $value == '1' || $value == 'true' )
			return true;
		if( $value == 'n' || $value == '0' || $value == 'false' )
			return false;
		throw new RestException($name, "Must be a boolean value.");
	}
	
	/**
	 * Same, but checks whether the param exists in the full params list first. If not,
	 * returns false.
	 */
	public static function parseOptBoolParam($name, $params) {
		if( !isset($params[$name]) )
			return false;
		return self::parseBoolParam($name, $params[$name]);
	}
	
	/**
	 * When generating help files we are asked for sets of information to place in the
	 * template.
	 * 
	 * @param $action : string - one of index,fetch,create,update,delete
	 * @return array of key => value pairs
	 */
	public function metadataFor($action) {
		$data = array();
		
		$data['description'] = $this->metaDescription();
		$extraDesc = $this->metaActionDesc($action);
		if( $extraDesc )
			$data['description'] .= "\n\n".$extraDesc;
		
		$data['security'] = $this->metaSecurity($action);
		
		$className = Util::camelizeClass($this->modelName);
		$data['Name'] = $className;
		$data['Names'] = Util::plural($className);
		$data['name'] = $this->modelName;
		$data['names'] = $this->typePlural;
		
		$schema = null;
		if( class_exists($className) ) {
			$instance = new $className();
			if( $instance instanceof ActiveDbObject || 
					method_exists($instance, 'getSchema') )
				$schema = $instance->getSchema();
		}
		if( $schema === null ) {
			//We kinda need something to go one.
			if( method_exists($this,'metaSchema') ) {
				$schema = $this->metaSchema();
			} else {
				//Make a guess using the union of allowed and required lists
				$schema = array();
				$check = array();
				if($this->allowed_update_attrs !== null) 
					$check = array_merge($check , $this->allowed_update_attrs);
				if($this->required_update_attrs !== null) 
					$check = array_merge($check , $this->required_update_attrs);
				if($this->allowed_create_attrs !== null) 
					$check = array_merge($check , $this->allowed_create_attrs);
				if($this->required_create_attrs !== null) 
					$check = array_merge($check , $this->required_create_attrs);
				if($this->return_attrs !== null) 
					$check = array_merge($check , $this->return_attrs);
				$check = array_unique($check);
				foreach($check as $attr) {
					$schema[$attr] = $this->metaGuessType($attr);
				}
			}
		}
		
		if( $action == 'fetch' ) {
			//Needs to know return attrs
			// If return_attrs is null, will give all from schema
			$attrData = $this->compileAttrData($this->return_attrs, null, $schema, 
						$instance);
		} else if( $action == 'update' ) {
			//Use list of allowed/required update attributes
			$attrData = $this->compileAttrData(
				$this->allowed_update_attrs,
				$this->required_update_attrs,
				$schema,
				$instance
			);
		} else if( $action == 'create' ) {
			//Use list of allowed/required update attributes
			$attrData = $this->compileAttrData(
				$this->allowed_create_attrs,
				$this->required_create_attrs,
				$schema,
				$instance
			);
		}
		if( $instance !== null && ($action == 'create' || $action == 'update') ) {
			foreach($attrData as $attr => &$info) {
				$info['validations'] = $instance->getFieldValidations($attr);
			}
		}
		$data['attributes'] = $attrData;
		
		if( $action == 'index' || $action == 'fetch' ) {
			$filters = $this->metaSupportedFiltersFor($action);
			foreach($filters as &$value) {
				if( is_string($value) ) {
					$info = array('name' => $value);
					switch($value) {
						case 'order':
							$info['desc'] = "Sort the list by the given key. Prefix the key with '>' for descending, '<' (or nothing) for ascending.";
							$info['example'] = "order=<name";
							break;
						case 'rel':
							$info['desc'] = "Boolean - Include related objects.";
							$info['example'] = "rel=true";
							break;
						case 'hidden':
							$info['desc'] = "Boolean - Include hidden/soft-deleted objects.";
							$info['example'] = "hidden=true";
							break;
						case 'ids_only':
							$info['desc'] = "Boolean - Return only IDs in place of full objects.";
							$info['example'] = "ids_only=true";
							break;
						case 'short':
							$info['desc'] = "Boolean - Return a minimal number of fields, like ID and name.";
							$info['example'] = "short=true";
							break;
					}
					$value = $info;
				}
			}
			$data['filters'] = $filters;
		}
		
		if( $action == 'delete' ) {
			$data['example_json'] = 'true';
		} else {
			if( $instance !== null ) {
				$this->metaFillExampleData($instance);
			
				//Cheat to get an ID in there
				$col = $instance->getIdColumn();
				if( !$instance->$col )
					$instance->setAttrs(array($col => rand(1,3000)));
			} else {
				$instance = new stdClass;
				$this->metaFillExampleData($instance);
			}
			
			if( $action == 'index' ) {
				$data['example_json'] = Page::json_encode_custom(
						$this->returnObjs(array($instance)));
			} else {
				$data['example_json'] = Page::json_encode_custom(
						$this->returnObj($instance));
			}
		}
		
		return $data;
	}
	
	/**
	 * Helper for metadataFor
	 */
	private function compileAttrData($allowed, $required, $schema, $instance) {
		$attrData = array();
		
		foreach($schema as $attr => $type) {
			if( $required === null )
				$req = false;
			else
				$req = in_array($attr, $required);
			if( !$req && $allowed !== null && !in_array($attr, $allowed) )
				continue;
			
			$desc = $this->metaFieldDesc($attr);
			if( !$desc && $instance !== null )
				$desc = $instance->getFieldDesc($attr);
			
			$attrData[$attr] = array(
				'type' => $type,
				'desc' => $desc,
				'required' => $req
			);
		}
		
		//Make sure we got everything
		if( count($required) > 0 ) {
			foreach($required as $attr) {
				if( !isset($attrData[$attr]) ) {
					$desc = $this->metaFieldDesc($attr);
					if( !$desc && $instance !== null )
						$desc = $instance->getFieldDesc($attr);
			
					$attrData[$attr] = array(
						'type' => $this->metaGuessType($attr),
						'desc' => $desc,
						'required' => true
					);
				}
			}
		}
		if( count($allowed) > 0 ) {
			foreach($allowed as $attr) {
				if( !isset($attrData[$attr]) ) {
					$desc = $this->metaFieldDesc($attr);
					if( !$desc && $instance !== null )
						$desc = $instance->getFieldDesc($attr);
			
					$attrData[$attr] = array(
						'type' => $this->metaGuessType($attr),
						'desc' => $desc,
						'required' => false
					);
				}
			}
		}
		
		return $attrData;
	}
	
	/**
	 * Helper for cases where we don't have a DB schema, just field names.
	 */
	private function metaGuessType($name) {
		if( substr($name,-3) == '_id' )
			return 'number';
		if( substr($name,-3) == '_at' || strpos('date',$name) !== false )
			return 'datetime';
		if( substr($name,0,2) == 'is' )
			return 'boolean';
		return 'string';
	}
	
	/**
	 * Just meta data. Override to give a better desc.
	 */
	protected function metaDescription() {
		return "Represents a ".Util::camelizeClass($this->modelName).'.';
	}
	
	/**
	 * Just meta data. Override to add descriptions for specific actions.
	 */
	protected function metaActionDesc($action) {
		return '';
	}
	
	/**
	 * Override to give descriptions for each field.
	 */
	protected function metaFieldDesc($field) {
		return '';
	}
	
	/**
	 * Override and return the names of filters (for common ones) or an array of data for
	 * unique ones with keys 'name', 'desc', and optionally 'example'.
	 * 
	 * Only index and fetch support filters. Most of the time only index supports any.
	 */
	protected function metaSupportedFiltersFor($action) {
		return array();
	}
	
	/**
	 * Override to fill out properties of the given model instance to be an example in 
	 * the API documentation.
	 */
	protected function metaFillExampleData($eg) {}
	
	/**
	 * Just meta data. Override to add a security section.
	 */
	protected function metaSecurity($action) {
		return '';
	}
}


?>