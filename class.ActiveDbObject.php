<?php
/**	(c) 2011 Everett Morse
*	@author Everett Morse - www.neptic.com
*	
*	Base class for automatic/magic object mapping.  A subclass must set up basic properties, like
*	table name and id column (defaults are guessed), then the class scans the db for the schema. It
*	uses magic methods to implement getting and setting variables of the object.
*	
*	It also implements the toJSON method for serialization.
*/
require_once 'class.DbObject.php';


class ActiveDbObject extends DbObject
{
	//Config
	protected $table;
	protected $idColumn = 'id';
	protected $useAutoIncrement = true;
	protected $dateModCol = null;
	protected $dateNewCol = null;
	
	//Data
	protected $data = array();			//assoc array of column => value
	protected $schema = null;			//master list of what attrs this object has.
	protected $isInDb = false;
	
	//Relationships
	protected $has_a = array();	 //property => table_name | array(table_name, id_column)
	protected $has_many = array();
	protected $belongs_to = array();
	protected $linked = array(); //property => object if loaded
	
	//Validations
	protected $validations = array();
	
	
	//Caches
	protected static $objectCache = array(); //table => array( id => object )
	protected static $schemaCache = array(); //table => schema array
	
	
	/**
	 * Constructor
	 * 
	 * @param int $id (Optional) - the value of the id column
	 * OR
	 * @param array $attrs - create a new, unsaved object with these attrs
	 */
	public function __construct($id_or_attrs = null) {
		if( !$this->table )
			$this->table = Util::plural(Util::underscore(get_class($this)));
		
		if( $this->table != 'active_db_objects' ) {
			$this->loadSchema();
			
			if( $this->dateNewCol === null ) {
				if( isset($this->schema['created_at']) )
					$this->dateNewCol = 'created_at';
				else if( isset($this->schema['date_created']) )
					$this->dateNewCol = 'date_created';
			}
			if( $this->dateModCol === null ) {
				if( isset($this->schema['updated_at']) )
					$this->dateModCol = 'updated_at';
				else if( isset($this->schema['modified_at']) )
					$this->dateModCol = 'modified_at';
				else if( isset($this->schema['date_modified']) )
					$this->dateModCol = 'date_modified';
				else if( isset($this->schema['date_updated']) )
					$this->dateModCol = 'date_updated';
			}
		}
		
		if( $id_or_attrs !== null ) {
			if( is_array($id_or_attrs) ) {
				$this->setAttrs($id_or_attrs);
			} else if( is_numeric($id_or_attrs) ) {
				$where = array( $this->idColumn => $id_or_attrs );
				$data = Db::executeSelect($this->table, $where);
				$this->loadFrom($data);
				
				self::$objectCache[$this->table][$id_or_attrs] = $this;
				return;
			}
		}
		
		/*
		//Attempted fix for case where new instance -> get property hits error.
		//changed my solution to just use isset, since this breaks insertion of a
		//attr that cannot be null but has not yet been set, and has a default in the db.
		
		//If still here, make sure we have an entry for each known attribute
		foreach($this->schema as $name => $type) {
			if( !isset($this->data[$name]) ) {
				$this->data[$name] = null;
			}
		}//*/
	}
	
	/**
	 * Scan the db to see what properties we have.
	 */
	protected function loadSchema() {
		if( isset(self::$schemaCache[$this->table]) ) {
			$this->schema = self::$schemaCache[$this->table];
			return;
		}
		
		$schema = Db::getRows("desc `$this->table`");
		//Field, Type, Null, Key, Default, Extra
		
		$this->schema = array();
		foreach($schema as $field) {
			$this->schema[$field['Field']] = $field['Type'];
		}
		
		self::$schemaCache[$this->table] = $this->schema;
	}
	
	/**
	 * A meta data function.  Returns the array of field => type mappings.
	 * It will translate the types to JSON types (boolean, number, string). Except extend
	 * to also have datetime, date, and time types.
	 */
	public function getSchema() {
		if( !isset($this->schema) )
			$this->loadSchema();
		
		$result = array();
		foreach($this->schema as $field => $type) {
			if( strpos($type, "int") !== false ) {
				if( strpos($type, "(1)") !== false )
					$type = "boolean";
				else
					$type = "number";
			} else if( strpos($type, "float") !== false ) {
				$type = "number";
			} else if( strpos($type, "char") !== false ) {
				$type = "string";
			} else if( strpos($type, "text") !== false ) {
				$type = "string";
			} else if( strpos($type, "blob") !== false ) {
				$type = "string";
			//} else if( strpos($type, "enum") !== false ) {
			//	$type = "array";
			} // else date, time, datetime -> keep
			
			$result[$field] = $type;
		}
		return $result;
	}
	
	/**
	 * Get meta data on a field. Override to suply custom descriptions.
	 * (MySQL doesn't have descriptions, but some back-ends might.)
	 */
	public function getFieldDesc($field) {
		return '';
	}
	
	/**
	 * Metadata - Gets what validations apply to a field.  If the validation is a custom
	 * function then calls getValidatorDesc and generates a generic description if none
	 * given.
	 */
	public function getFieldValidations($field) {
		if( !isset($this->validations[$field]) )
			return array();
		$info = array();
		foreach($this->validations[$field] as $prop => $constraint) {
			switch($prop) {
				case 'min_length':
					$info[] = "Length must be at least $constraint.";
					break;
				case 'max_length':
					$info[] = "Length must at most $constraint.";
					break;
				case 'validator': //calls a method
					$desc = $this->getValidatorDesc($constraint);
					if( !$desc )
						$desc = "Must pass $constraint.";
					$info[] = $desc;
					break;
				case 'exists':
					$info[] = "Cannot be null.";
					break;
				case 'unique':
					$info[] = "Must be unique.";
					break;
				case 'is_int':
					$info[] = "Must be an integer.";
					break;
				case 'is_datetime':
					$info[] = "Must be parsable as a date/time.";
					break;
			}
		}
		return $info;
	}
	
	protected function getValidatorDesc($name) { return ''; }
	
	public function getIdColumn() { return $this->idColumn; }
	
	/**
	 * Given a row from the database, populate this object.
	 * 
	 * @param array $data - an associative array
	 */
	public function loadFrom($data) {
		$this->data = $data;	//done.  wasn't that easy :-)
		
		//Check id to see if we're from the db
		if( $this->data[ $this->idColumn ] !== null )
			$this->isInDb = true;
		else
			$this->isInDb = false;
	}
	
	/**
	 * Sets the attributes of this object. Only sets those that are in the db schema.
	 */
	public function setAttrs($data) {
		foreach($data as $name => $value) {
			if( isset($this->schema[$name]) ) {
				$this->data[$name] = $value;
			}
		}
	}
	
	/**
	 * Save the object, determining whether to insert or update as needed.
	 */
	public function save() {
		$now = gmdate('Y-m-d H:i:s');
		if( $this->dateModCol )
			$this->data[$this->dateModCol] = $now;
		
		if( $this->isInDb ) {
			//Update
			
			$where = array( $this->idColumn => $this->data[ $this->idColumn ] );
			Db::executeUpdate($this->table, $this->data, $where);
		} else {
			//Insert
			if( $this->dateNewCol )
				$this->data[$this->dateNewCol] = $now;
			Db::executeInsert($this->table, $this->data);
			
			if( $this->useAutoIncrement ) {
				$this->data[ $this->idColumn ] = Db::getInsertId();
			}
			
			$this->isInDb = true;
		}
	}
	
	/**
	 * Delete this object from the db.  Don't forget to clear out the id or your $isInDb flag.
	 */
	public function delete() {
		if( !$this->isInDb ) return;
		
		$where = array( $this->idColumn => $this->data[ $this->idColumn ] );
		Db::executeDelete($this->table, $where);
		$this->isInDb = false;
		
		unset(self::$objectCache[$this->table][$this->data[$this->idColumn]]);
		//Do I want this? $this->data[$this->idColumn] = null;
	}
	
	public function reload() {
		if( $this->isInDb ) {
			$this->linked = array();
			
			$where = array( $this->idColumn => $this->data[$this->idColumn] );
			$data = Db::executeSelect($this->table, $where);
			$this->loadFrom($data);
		}
	}
	
	/////////////////////////// ACCESSORS / MUTATORS //////////////////
	
	
	/**
	 * Determine if this object is in the db or still only in memory.
	 * Simple implementation:
	 *		return $this->id !== null;
	 * 
	 * @return boolean
	 */
	public function isInDb() {
		return $this->isInDb;
	}
	
	/**
	 * Get the id for this object.  If the object is not in the db this should return null.
	 * 
	 * @param mixed - value of the id column, or tuple for composite key, or null.
	 */
	public function getId() {
		if( !$this->isInDb )
			return null;
		
		return $this->data[ $this->idColumn ];
	}
	
	
	/**
	 * Handles calls to getters/setters
	 */
	public function __call($method, $params) {
		$type = substr($method, 0, 3);
		
		if( $type == 'get' ) {
			
			$name = substr($method, 3);
			$name = $this->findMethodVariable($name);
			
			if( $name === null )
				return null;
			return $this->data[ $name ];
			
		} else if( $type == 'set' ) {
			
			$name = substr($method, 3);
			$name = $this->findMethodVariable($name);
			
			if( $name !== null )
				$this->data[ $name ] = $params[0];
		}
	}
	
	/**
	 * Takes a cammel-cased name and attempts various other forms to find the column name
	 */
	private function findMethodVariable($name) {
		if( $this->schema === null )
			$this->loadSchema();
		
		//Find the variable
		if( array_key_exists($name, $this->schema) )
			return $name;
			
		//Simple case change
		$check = strtolower($name);
		if( array_key_exists($check, $this->schema) )
			return $check;
		
		$check = strtoupper($name);
		if( array_key_exists($check, $this->schema) )
			return $check;
		
		
		/*Turned off lots to speed things up
		//From cammel to snake
		$check1 = ereg_replace("([a-z])([A-Z])","\\1_\\2",$name);	//underscore b/f capitol letter (but not first letter)
		$check = strtoupper($check1);
		if( array_key_exists($check, $this->schema) )
			return $check;
		
		$check = strtolower($check1);
		if( array_key_exists($check, $this->schema) )
			return $check;
		
		if( array_key_exists($check1, $this->schema) )
			return $check1;
		
		//From snake to cammel
		$check = str_replace('_','', ucwords(strtolower($name)));
		if( array_key_exists($check, $this->schema) )
			return $check;
		//*/
		
		return null;
	}
	
	/**
	 * Tries a few forms of the variable name.  Input might be snake cased, camel cased, etc.
	 * Possibly whoever is trying to get the property uses the same name as the db's name, but the
	 * user might instead be using a standardized form of the name.
	 */
	private function findPropertyVariable($name) {
		if( $this->schema === null )
			$this->loadSchema();
		
		//Find the variable
		if( array_key_exists($name, $this->schema) )
			return $name;
			
		//Simple case change
		$check = strtolower($name);
		if( array_key_exists($check, $this->schema) )
			return $check;
		
		/*Turned off lots to speed things up
		$check = strtoupper($name);
		if( array_key_exists($check, $this->schema) )
			return $check;
		
		//From snake to cammel
		$check = str_replace('_','', ucwords($name));
		if( array_key_exists($check, $this->schema) )
			return $check;
		
		$check = str_replace('_','', ucwords(strtolower($name)));
		if( array_key_exists($check, $this->schema) )
			return $check;
		
		//From cammel to snake
		$check1 = ereg_replace("([a-z])([A-Z])","\\1_\\2",$name);	//underscore b/f capitol letter (but not first letter)
		$check = strtoupper($check1);
		if( array_key_exists($check, $this->schema) )
			return $check;
		
		$check = strtolower($check1);
		if( array_key_exists($check, $this->schema) )
			return $check;
		
		if( array_key_exists($check1, $this->schema) )
			return $check1;
		//*/
		
		return null;
	}


	
	/**
	 * Called when reading inaccessible members
	 * 
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name) {
		$name2 = $this->findPropertyVariable($name);
		if( $name2 !== null ) {
			if( isset($this->data[ $name2 ]) ) //avoid annoying notices
				return $this->data[ $name2 ];
			else
				return null; //not set
		}
		
		//Check for linked objects
		if( isset($this->linked[$name]) )
			return $this->linked[$name];
		if( isset($this->belongs_to[$name]) ) {
			//Key for the other object is in the current table
			list($table, $column) = $this->belongs_to[$name];
			//echo "Checking belongs_to $name ($table,$column)\n";
			if( !isset($this->data[$column]) || !is_numeric($this->data[$column]) )
				return null; //don't have one anyway
			if( isset(self::$objectCache[$table][$this->data[$column]]) ) {
				$this->linked[$name] = self::$objectCache[$table][$this->data[$column]];
				return $this->linked[$name];
			} else {
				$className = Util::camelizeClass(Util::singular($name));
				$this->linked[$name] = new $className($this->data[$column]);
				if( $this->linked[$name]->isInDb() )
					self::$objectCache[$table][$this->data[$column]] = $this->linked[$name];
				return $this->linked[$name];
			}
		}
		if( isset($this->has_a[$name]) ) {
			//Key is in the other table
			list($table, $column) = $this->has_a[$name];
			//echo "Checking has_a $name ($table,$column)\n";
			
			$className = Util::camelizeClass(Util::singular($name));
			$x = new $className();
			
			if( isset(self::$objectCache[$x->table][$x->idColumn]) ) {
				$this->linked[$name] = self::$objectCache[$x->table][$x->idColumn];
				return $this->linked[$name];
			} else {
				$list = self::getObjectsWhere(
					array($column => $this->data[$this->idColumn]),
					$table, 
					$x->idColumn
				);
				$this->linked[$name] = $list[0];
				return $this->linked[$name];
			}
		}
		if( isset($this->has_many[$name]) ) {
			//Key is in other table
			list($table, $column) = $this->has_many[$name];
			//echo "Checking has_many $name ($table,$column)\n";
			$class = Util::camelizeClass(Util::singular($table));
			$x = new $class();
			$list = self::getObjectsWhere(
					array($column => $this->data[$this->idColumn]),
					$table, 
					$x->idColumn
				);
			$this->linked[$name] = $list;
			return $this->linked[$name];
		}
		
		return null;
	}
	
	/**
	 * Called when writing inaccessible members
	 * 
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	public function __set($name, $value) {
		$name = $this->findPropertyVariable($name);
		if( $name !== null ) {
			$this->data[ $name ] = $value;
		}
	}
	
	/**
	 * Called when using isset or empty on inaccessible members
	 * 
	 * @param string $name
	 * @return boolean
	 */
	public function __isset($name) {
		$name = $this->findPropertyVariable($name);
		if( $name !== null ) {
			return $this->data[ $name ] !== null;
		}
		return false;
	}
	
	/**
	 * Called when using unset on inaccessible members.
	 * 
	 * We don't actually unset the variable, though, we just set it to null.  It would come back as
	 * false from an isset call, but we still have a record of it for saving the object and for
	 * allowing a get/set on it.
	 * 
	 * @param string $name
	 * @return void
	 */
	public function __unset($name) {
		$name = $this->findPropertyVariable($name);
		if( $name !== null ) {
			$this->data[ $name ] = null;
		}
	}
	
	
	function __toString() {
		if( $this->isInDb )
			return get_class($this).' ('.$this->data[$this->idColumn].')';
		else
			return get_class($this).' (unsaved)';
	}
	
	function toJSON() {
		$data = array();
		foreach($this->schema as $field => $type) {
			if( isset($this->data[$field]) )
				$data[$field] = $this->data[$field];
			else
				$data[$field] = null;
		}
		$data['__class'] = get_class($this);
		return json_encode($data);
	}
	
	//Same as toJSON, but just returns the array.  This gets a list of all properties.
	function getAttrs() {
		$data = array();
		foreach($this->schema as $field => $type) {
			if( isset($this->data[$field]) )
				$data[$field] = $this->data[$field];
			else
				$data[$field] = null;
		}
		$data['__class'] = get_class($this);
		return $data;
	}
	
	/**
	 * Sets each of the given attributes using the setter method.
	 */
	public function setValues($data) {
		foreach($data as $name => $value) {
			if( isset($this->schema[$name]) ) {
				$setter = 'set'.Util::camelize($name);
				if( method_exists($this, $setter) )
					$this->$setter($value);
				else
					$this->$name = $value; //will use __set
			}
		}
	}
	
	/**
	 * Checks all validations for this object.
	 * 
	 * @param OUT $errors : array - If given, validation errors returned here. Will have
	 *                              a bucket for each attr with an array of messages.
	 * @retuns boolean
	 */
	public function isValid(&$errors = null) {
		
		foreach($this->validations as $attr => $list) {
			foreach($list as $prop => $constraint) {
				switch($prop) {
					case 'min_length':
						if( strlen($this->$attr) < $constraint ) {
							if( $errors !== null )
								$errors[$attr][] = "length is less than $constraint";
							else
								return false;
						}
						break;
					case 'max_length':
						if( strlen($this->$attr) > $constraint ) {
							if( $errors !== null )
								$errors[$attr][] = "length is greater than $constraint";
							else
								return false;
						}
						break;
					case 'validator': //calls a method
						$result = $this->$constraint($this->$attr, $attr);
						if( $result !== true ) {
							if( $errors !== null ) {
								if( is_string($result) ) //might return better msg
									$errors[$attr][] = $result;
								else
									$errors[$attr][] = "failed $constraint";
							} else
								return false;
						}
						break;
					case 'exists':
						if( $this->$attr === null ) {
							if( $errors !== null )
								$errors[$attr][] = "cannot be null";
							else
								return false;
						}
						break;
					case 'unique':
						if( $this->$attr !== null ) {
							if( $this->isInDb )
								$where = array($x->idColumn => array($id,false));
							else
								$where = array();
							$where[$attr] = $this->$attr;
							$where = Db::buildWhere($where);
							$other = Db::getValue("select 1 from ".Db::table($this->table)
									." where ".$where);
							if( $other == 1 ) {
								if( $errors !== null )
									$errors[$attr][] = "must be unique.";
								else
									return false;
							}
						}
						break;
					case 'is_int':
						if( $this->$attr !== null ) {
							if( !is_int($this->$attr) ) {
								if( $errors !== null )
									$errors[$attr][] = "must be an integer.";
								else
									return false;
							}
						}
						break;
					case 'is_datetime':
						if( $this->$attr !== null && strtotime($this->$attr) === false ) {
							if( $errors !== null )
								$errors[$attr][] = "must be a valid datetime.";
							else
								return false;
						}
						break;
				}
			}
		}
		
		if( $errors !== null && count($errors) > 0 )
			return false;
		else
			return true;
	}
	
	/**
	 * Adds a validation to be checked by isValid.
	 * 
	 * Example: "name", "min_length", 3
	 * 
	 */
	protected function addValidation($attr, $property, $constraint) {
		if( !isset($this->validations[$attr]) )
			$this->validations[$attr] = array($property => $constraint);
		else 
			$this->validations[$attr][$property] = $constraint;
	}
	
	///////////////////////// STATIC SEARCH METHODS /////////////////
	
	//WARNING: this could remove changes from a modified object.  Should I instead not update?
	//Changed: Yeah, commented out the update part.
	public static function loadOrUpdateObject($row, $table, $idColumn = null) {
		if( $idColumn === null ) $idColumn = 'id';
		if( isset(self::$objectCache[$table][$row[$idColumn]]) ) {
			$obj = self::$objectCache[$table][$row[$idColumn]];
			//$obj->setAttrs($row);
		} else {
			$className = Util::camelizeClass(Util::singular($table));
			$obj = new $className(null);
			$obj->table = $table;
			if($idColumn !== null) $obj->idColumn = $idColumn;
			$obj->loadFrom($row);
			self::$objectCache[$table][$row[$idColumn]] = $obj;
		}
		
		return $obj;
	}
	
	/**
	 * Gets all objects of this type.
	 * 
	 * @param mixed $table - the table to look in
	 * @param mixed $idColumn (Optional) - column for the id. (default is "id")
	 * @param string $orderBy (Optional) - name of field, can end with " asc" or " desc"
	 * @return ActiveDbObject[]
	 */
	public static function getObjects($table, $idColumn = null, $orderBy = null) {
		$results = array();
		
		Db::executeSelect($table, "1=1", false, $orderBy);
		while( ($row = Db::getNextRow()) ) {
			$results[] = self::loadOrUpdateObject($row,$table,$idColumn);
		}
		
		return $results;
	}
	
	/**
	 * Gets all objects of this type that meet some condition
	 * 
	 * @param mixed $where - the where clause, either as a string or array
	 * @param mixed $table - the table to look in
	 * @param mixed $idColumn (Optional) - column for the id. (default is "id")
	 * @param string $orderBy (Optional) - name of field, can end with " asc" or " desc"
	 * @return ActiveDbObject[]
	 */
	public static function getObjectsWhere($where, $table, $idColumn = null, 
			$orderBy = null) 
	{
		$results = array();
		
		Db::executeSelect($table, $where, false, $orderBy);
		while( ($row = Db::getNextRow()) ) {
			$results[] = self::loadOrUpdateObject($row,$table,$idColumn);
		}
		
		return $results;
	}
	
	public static function findObject($id, $table, $idColumn = null) {
		if( $idColumn === null ) $idColumn = 'id';
		$all = getObjectsWhere(array($idColumn => $id), $table, $idColumn);
		return $all[0];
	}
	
	
	
	//------- Trickier stuff to add functionality to subclasses
	
	static function all($orderBy = null) {
		$class = get_called_class();
		$x = new $class();
		return self::getObjects($x->table, $x->idColumn, $orderBy);
	}
	
	static function find($id) {
		$class = get_called_class();
		$x = new $class();
		if( !isset(self::$objectCache[$x->table][$id]) ) {
			$where = array( $x->idColumn => $id );
			$data = Db::executeSelect($x->table, $where);
			if( $data ) {
				$x->loadFrom($data);
				return self::$objectCache[$x->table][$id] = $x;
			} else
				return null;
		}
		return self::$objectCache[$x->table][$id];
	}
	
	static function exists($id) {
		$class = get_called_class();
		$x = new $class(); //constructor is what determins table name
		
		//assume it exists if we have a cached version, tho could have been deleted
		if( isset(self::$objectCache[$x->table][$id]) )
			return true;
		
		$where = Db::buildWhere(array( $x->idColumn => $id ));
		$exists = Db::getValue("select 1 from ".Db::table($x->table)." where ".$where);
		return $exists === '1';
	}
	
	static function where($where, $orderBy = null) {
		$class = get_called_class();
		$x = new $class();
		return self::getObjectsWhere($where, $x->table, $x->idColumn, $orderBy);
	}
	
}

?>
