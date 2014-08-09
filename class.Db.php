<?php
/**	(c) 2009 Everett Morse
*	@author Everett Morse - www.neptic.com
	7:20pm - 9:18pm
*	
*	Quick, simple, effective database connector class for PHP.
*	Much simpler to use than PEAR, PDO, etc. with lots of helful functions.
*	
*	Class DB has configuration and static methods.  It will maintain a list of db connections,
*	instantiating a new one when necessary.  If auto-free is enabled (off by default) then a
*	connection will be reused once the data has been read from it.
*	
*	DB Connection is the main object that handles creating and executing queries and retrieving
*	results.  The static methods all call an instance method on an instance of this class.
*	This implementation connects to MySQL.  You could create a new DB Connection class to connect
*	to some other kind of db.
*	
*	Usage:
*		
*		1. Static singleton
*			
*			//Config file
*				Db::setParams('localhost', 'apache', 'password', 'inventory');
*			
*			//Usage in the global scope, a function, etc.
*				
*				$productInfo = Db::getRows("SELECT * FROM products");
*		
*		2. Global instance(s)
*			
*			//Config file
*				$db = Db::connect('localhost', 'apache', 'password', 'inventory');
*				$dbLog = Db::connect('localhost', 'apache', 'password', 'webstats');
*		
*			//Usage in a function
*				
*				global $db;
*				$productInfo = $db->getRows("SELECT * FROM products");
*		
*		3. Local Instances
*			
*			//Config file
*				Db::setParams('localhost', 'apache', 'password', 'inventory');
*			
*			//Usage in a function
*				
*				$db = Db::getConnection();
*				$productInfo = $db->getRows("SELECT * FROM products");
*		
*		4. Pooled
*			
*			//Config file
*				Db::setParams('localhost', 'apache', 'password', 'inventory');
*				Db::$maxPoolSize = 5;
*				Db::$autoFree = true;		//returns to pool automatically
*				Db::fillPool(2);
*			
*			//Usage in a function
*				
*				$db = Db::getConnection();
*				$productInfo = $db->getRows("SELECT * FROM products");
*				
*				//If auto-free is off, uncomment this line
*				//Db::addToPool($db);
*		
*		
*		
*	Notes:
*	- Anywhere you can pass in a table name you can pass either a string or an array of two strings
*	  that will be interpretted as a <DB, TableName> tuple.
*	- All results return associative arrays
*	- Where clauses can be an associative array of values to check for equivalence, or a string
*	
*	Known issues:
*	- Due to how php's mysql functions work, if you call Db::connect to the same host but on two
*	  different databases, you'll actually be sharing a connection and thus will still need to
*	  prefix all table names with the db name.  One work-around is to use a different host string,
*	  for example "localhost" and "127.0.0.1" are different hosts to PHP. (I could force it, I 
*	  think, with the "new_link" parameter.)
*	- There is no handling of result sets separately.  That means once you execute a new query you
*	  lose the results from the previous query.  The easy work-around is to grab all the result rows
*	  with getRows (etc.) before executing a new query.  The only methods that would leave a result
*	  set unused anyway are query, execute, and getNextRow.
*	- The pooling idea really isn't that useful for PHP, since each request is in it's own process/
*	  thread anyway.  So I don't recomment using it, I just wrote it for fun, or for a case where
*	  a large app keeps grabbing local copies of db connections rather than having one global or
*	  static access.
*/



class Db
{
	//Constants
	const ERR_HALT = 0;				//On error, halt and print error message and backtrace
	const ERR_EXCEPTION = 1;		//On error, throw an exception
	const ERR_TRIGGER_WARNING = 2;	//On error, trigger a PHP warning
	const ERR_TRIGGER_ERROR = 3;	//On error, trigger a PHP error
	const ERR_TRIGGER_FATAL = 4;	//On error, trigger a PHP fatal error
	
	//Config - for new connections
	public static $host;
	public static $user;
	public static $password;
	public static $db;					//this is just the default db for the new connections made
	
	public static $autoFree = false;	//when a query is done, free and return to pool
	public static $errorMode = self::ERR_HALT;
	
	
	//Config - shared
	public static $maxPoolSize = 1;		//if not zero, close connections when we have too many
	
	//Pool
	private static $pool = array();
	private static $connection;			//if you call static db access methods, uses this connection
	
	
	
	/////////////////////// DB METHODS /////////////////////////
	
	
	/**
	 * Quick method for setting all the connection parameters
	 * 
	 * @param string $host
	 * @param string $user
	 * @param string $password
	 * @param string $db
	 */
	public static function setParams($host, $user, $password, $db) {
		self::$host = $host;
		self::$user = $user;
		self::$password = $password;
		self::$db = $db;
	}
	
	/**
	 * Creates a new connection and returns it.  This does not add it to the pool.  You do not need
	 * to call this function normally.
	 * 
	 * Note: to switch to a different database type, change this method.
	 * 
	 * @param string $host
	 * @param string $user
	 * @param string $password
	 * @param string $db
	 * @return DbConnection
	 */
	public static function connect($host, $user, $password, $db) {
		$con = new DbMySql($host, $user, $password, $db);
		$con->autoFree = self::$autoFree;
		$con->errorMode = self::$errorMode;
		return $con;
	}
	
	/**
	 * Place a DB connection in the pool. (Does not check for duplicates.)
	 * 
	 * @param DbConnection $connection
	 */
	public static function addToPool(DbConnection $connection) {
		if( $connection == self::$connection )
			self::$connection = null;	//tried to auto-free our static copy, so release our handle
		
		if( self::$maxPoolSize && count(self::$pool) >= self::$maxPoolSize ) {
			$connection->close();
			return;
		}
		
		self::$pool[] = $connection;
	}
	
	/**
	 * Fills the pool up, either to max size or to the indicated number of connections.
	 * 
	 * @param int $num (Optional) - the number of connections to start with
	 */
	public static function fillPool($num = 0) {
		if( $num == 0 )
			$num = self::$maxPoolSize;
		
		while( count(self::$pool) < $num ) {
			self::$pool[] = self::connect(self::$host, self::$user, self::$password, self::$db);
		}
	}
	
	/**
	 * Gets a db connection, first from the pool and second by creating one.
	 * 
	 * @return DbConnection
	 */
	public static function getConnection() {
		if( count(self::$pool) > 0 ) {
			return array_pop(self::$pool);
		} else
			return self::connect(self::$host, self::$user, self::$password, self::$db);
	}
	
	
	/////////////////// QUERY METHODS //////////////////////////
	#Note: see DbMySql for documentation of specific methods
	
	
	private static function getDb() {
		if( self::$connection === null )
			self::$connection = self::getConnection();
		return self::$connection;
	}
	
	static public function query($stmt) { return self::getDb()->query($stmt); }
	static public function escape($value) { return self::getDb()->escape($value); }
	static public function buildWhere($fields) { return self::getDb()->buildWhere($fields); }
	static public function buildOrder($order) { return self::getDb()->buildOrder($order); }
	static public function table($table) { return self::getDb()->table($table); }
	
	static public function getRow($stmt) { return self::getDb()->getRow($stmt); }
	static public function getRows($stmt) { return self::getDb()->getRows($stmt); }
	static public function getRowsBy($stmt,$column) { return self::getDb()->getRowsBy($stmt, $column); }
	static public function getNextRow() { return self::getDb()->getNextRow(); }
	
	static public function getValue($stmt) { return self::getDb()->getValue($stmt); }
	static public function getValues($stmt) { return self::getDb()->getValues($stmt); }
	static public function getValuesIndexed($stmt) { return self::getDb()->getValuesIndexed($stmt); }
	
	static public function prepare($stmt) { return self::getDb()->prepare($stmt); }
	static public function execute($preparedStmt, $values) { return self::getDb()->execute($preparedStmt, $values); }
	
	static public function executeUpdate($table, $fields, $where) { return self::getDb()->executeUpdate($table, $fields, $where); }
	static public function executeInsert($table, $fields) { return self::getDb()->executeInsert($table, $fields); }
	static public function executeDelete($table, $where) { return self::getDb()->executeDelete($table, $where); }
	static public function executeSelect($table, $where, $getFirst = true, $orderBy = null) { return self::getDb()->executeSelect($table, $where, $getFirst, $orderBy); }
	
	static public function getInsertId() { return self::getDb()->getInsertId(); }
	static public function getAffectedRows() { return self::getDb()->getAffectedRows(); }
	static public function getNumRows() { return self::getDb()->getNumRows(); }
	static public function getNumFields() { return self::getDb()->getNumFields(); }
	
	
}


/// Quick and dirty abstract parent class, see DbMySql for documentation
abstract class DbConnection
{
	abstract public function query($stmt);
	abstract public function close();
	abstract protected function reportError($note = "");
	abstract public function escape($value);
	abstract public function buildWhere($fields);
	abstract public function table($table);
	
	abstract public function getRow($stmt);
	abstract public function getRows($stmt);
	abstract public function getRowsBy($stmt,$column);
	abstract public function getNextRow();
	
	abstract public function getValue($stmt);
	abstract public function getValues($stmt);
	abstract public function getValuesIndexed($stmt);
	
	abstract public function prepare($stmt);
	abstract public function execute($preparedStmt, $values);
	
	abstract public function executeUpdate($table, $fields, $where);
	abstract public function executeInsert($table, $fields);
	abstract public function executeDelete($table, $where);
	abstract public function executeSelect($table, $where, $getFirst = true, $orderBy = null);
	
	abstract public function getInsertId();
	abstract public function getAffectedRows();
	abstract public function getNumRows();
	abstract public function getNumFields();
}


class DbMySql extends DbConnection
{
	//Config
	public $autoFree;
	public $errorMode;
	
	private $host;
	private $user;
	private $password;
	private $db;
	
	//Internal
	private $link;		//the MySql connection resource
	private $result;	//the MySql result resource
	
	
	/**
	 * Creates a connection to the database.  (Actual connection is delayed until we try to execute
	 * a query of some sort.)
	 */
	public function __construct($host, $user, $password, $db) {
		$this->host = $host;
		$this->user = $user;
		$this->password = $password;
		$this->db = $db;
	}
	
	/**
	 * Creates the connection, selects the default db
	 */
	private function connect() {
		$link = mysql_connect($this->host, $this->user, $this->password);
		if( $link !== false )
			$this->link = $link;
		else
			$this->reportError("Could not connect");
		
		if( $this->db ) {
			if( @mysql_select_db($this->db, $this->link) === false ) {
				$this->reportError("Could not select database '{$this->db}'");
			}
		}
	}
	
	///////////////////// MASTER QUERY HANDLING ////////////////
	
	
	/**
	 * Performs a query.  Handles errors, resources, etc.
	 */
	public function query($stmt) {
		
		if( $this->link === null )
			$this->connect();
		
		//echo "<br/>QUERY:<pre>$stmt</pre><br/>";
		$result = mysql_query($stmt, $this->link);
		
		if( !$result ) {
			//No resource, or false --> failed
			$this->reportError("Invalid query",$stmt);
		} else if( $result === true ) {
			
			if( $this->autoFree ) {
				//@mysql_free_result($result);
				$this->result = null;
				Db::addToPool($this);
			}
			
		} else {
			$this->result = $result;
		}
	}
	
	/**
	 * Close this connection.
	 */
	public function close() {
		mysql_close($this->link);
		$this->link = null;
	}
	
	/**
	 * Report an error with a query or connection.
	 * 
	 * @param string $note (Optional) - Brief note about what failed, we'll print more with it
	 */
	protected function reportError($note = "",$query = null) {
		$note = ($note? $note.': ' : '') . mysql_error();
		
		switch($this->errorMode) {
			case Db::ERR_HALT:
				echo $note . "\n";
				if( $query !== null && constant('ENV') == 'dev' )
					echo $query."\n";
				debug_print_backtrace();
				exit(1);
			case Db::ERR_EXCEPTION:
				throw new Exception( $note );
			case Db::ERR_TRIGGER_WARNING:
				trigger_error( $note, E_USER_WARNING );
				break;
			case Db::ERR_TRIGGER_ERROR:
				trigger_error( $note, E_USER_ERROR );
				break;
			case Db::ERR_TRIGGER_FATAL:
				trigger_error( $note, E_ERROR );
				break;
			default:	//undocumented means of ignoring errrors, better way is to use warn and @
				break;
		}
	}
	
	/**
	 * Escapes a value properly for building queries.  This handles addslashes as well (on the 
	 * assumption that magic quotes is not an issue.  If you have that on, stripslashes first
	 * since I don't want to have to guess if you're passing data from a form or elsewhere).
	 */
	public function escape($value) {
		if( $this->link === null )
			$this->connect();	//we'll need the connection for this newer escape string function
		return mysql_real_escape_string($value, $this->link);
	}
	
	/**
	 * Creates a where clause (no "where" included) with proper escaping that compares columns for
	 * equivalence to given values.
	 * 
	 * If the value is type-equals to null, the comparison "is null" is used.
	 * The value can also be an array pair (value, equality) where equality is a boolean true or
	 * false.  False will use != or "is not null", true uses = or "is null".
	 */
	public function buildWhere($fields) {
		$where = '';
		$sep = '';
		foreach($fields as $name => $value) {
			if( is_array($value) ) {
				$eq = $value[1];
				$value = $value[0];
			} else
				$eq = true;
			
			if( is_string($eq) ) {
				$where .= $sep . '`' . $name . '`'.$eq.'"' . $this->escape($value) . "\"";				
			} else if( $eq ) {
				if( $value === null )
					$where .= $sep . '`' . $name . '` is null';
				else 
					$where .= $sep . '`' . $name . "`=\"" . $this->escape($value) . "\"";				
			} else {
				if( $value === null )
					$where .= $sep . '`' . $name . '` is not null';
				else 
					$where .= $sep . '`' . $name . "`!=\"" . $this->escape($value) . "\"";
			}
			$sep = ' && ';
		}
		
		return $where;
	}
	
	/**
	 * Take as input the name of a field with optional ' asc' or ' desc' following it.
	 * Return escaped SQL suitable for putting after ' order by '.
	 */
	public function buildOrder($orderBy) {
		$dir = " asc";
		if( substr($orderBy, -4) == " asc" ) {
			$orderBy = substr($orderBy,0, -4);
		} else if(substr($orderBy, -5) == " desc") {
			$orderBy = substr($orderBy,0, -5);
			$dir = " desc";
		}
		return '`' . $orderBy . '`' . $dir;
	}
	
	/**
	 * Get the table name properly escaped.  Handles the array format too, so this is convenient
	 * for when database + table is stored as a config variable.
	 */
	public function table($table) {
		if( is_array($table) ) {
			$table = '`' . $table[0] . '`.`' . $table[1] . '`';
		} else
			$table = '`' . $table . '`';
		
		return $table;
	}
	
	
	///////////////////// ROW ACCESS METHODS ///////////////////
	
	
	/**
	 * Execute an SQL statement and return the first result row
	 */
	public function getRow($stmt) {
		$this->query($stmt);
		
		if( $this->result ) {
			$row = mysql_fetch_assoc($this->result);
			
			if( !$row && $this->autoFree ) {
				@mysql_free_result($result);
				$this->result = null;
				Db::addToPool($this);
			}
			
			return $row;
		}
		
		return null;
	}
	
	/**
	 * Get an array with all the results
	 */
	public function getRows($stmt) {
		$this->query($stmt);
		
		if( $this->result ) {
			
			$rows = array();
			while( ($row = mysql_fetch_assoc($this->result)) ) {
				$rows[] = $row;
			}
			
			if( $this->autoFree ) {
				@mysql_free_result($result);
				$this->result = null;
				Db::addToPool($this);
			}
			
			return $rows;
		}
		
		return null;
	}
	
	/**
	 * Gets an array of all the result rows, with a certain column as the index in the array
	 */
	public function getRowsBy($stmt,$column) {
		$this->query($stmt);
		
		if( $this->result ) {
			
			$rows = array();
			while( ($row = mysql_fetch_assoc($this->result)) ) {
				$rows[ $row[$column] ] = $row;
			}
			
			if( $this->autoFree ) {
				@mysql_free_result($result);
				$this->result = null;
				Db::addToPool($this);
			}
			
			return $rows;
		}
		
		return null;
	}
	
	/**
	 * Gets the next row in a result set
	 */
	public function getNextRow() {
		if( $this->result ) {
			$row = mysql_fetch_assoc($this->result);
			
			if( !$row && $this->autoFree ) {
				@mysql_free_result($result);
				$this->result = null;
				Db::addToPool($this);
			}
			
			return $row;
		}
		
		return null;
	}
	
	
	
	///////////////////// VALUE ACCESS METHODS /////////////////
	
	
	/**
	 * Get the value in the first column, first row
	 */
	public function getValue($stmt) {
		$this->query($stmt);
		
		if( $this->result ) {
			
			$row = @mysql_fetch_row($this->result);
			$value = $row[0];
			
			if( $this->autoFree ) {
				@mysql_free_result($result);
				$this->result = null;
				Db::addToPool($this);
			}
			
			return $value;
		}
		
		return null;
	}
	
	/**
	 * Get an array of the values in the first column over all the rows
	 */
	public function getValues($stmt) {
		$this->query($stmt);
		
		if( $this->result ) {
			
			$rows = array();
			while( ($row = @mysql_fetch_row($this->result)) ) {
				$rows[] = $row[0];
			}
			
			if( $this->autoFree ) {
				@mysql_free_result($result);
				$this->result = null;
				Db::addToPool($this);
			}
			
			return $rows;
		}
		
		return null;
	}
	
	/**
	 * Get an array of values where the first column is the index in the array and the second
	 * column is the value.
	 * 
	 * For example:
	 * 		$itemNames = Db::getValuesIndexed("SELECT item_id, name FROM items");
	 *		echo "Item ID 321 is " . $itemNames[321] . "\n";
	 */
	public function getValuesIndexed($stmt) {
		$this->query($stmt);
		
		if( $this->result ) {
			
			$rows = array();
			while( ($row = @mysql_fetch_row($this->result)) ) {
				$rows[ $row[0] ] = $row[1];
			}
			
			if( $this->autoFree ) {
				@mysql_free_result($result);
				$this->result = null;
				Db::addToPool($this);
			}
			
			return $rows;
		}
		
		return null;
	}
	
	
	///////////////////// PREPARED STATEMENTS //////////////////
	
	
	/**
	 * Prepares a statment to execute later.  Put a ? anywhere you want a value to appear.
	 * Do not put quotes around the question marks.
	 * 
	 * @param string $stmt - the statement, with question marks where values should go
	 * @return PreparedStatement (implemented as an array)
	 */
	public function prepare($stmt) {
		return explode('?', $stmt.' ');	//add space so ? is never the last char
	}
	
	/**
	 * Execute a prepared statement with the given values.  Uses the same order from the prepare
	 * call to put the values in.
	 * 
	 * @param PreparedStatement $preparedStmt - from prepare()
	 * @param array $values - unescaped, that will be done automatically
	 * 
	 * @throws Exception - if the wrong number of values are given
	 */
	public function execute($preparedStmt, $values) {
		if( count($preparedStmt) != count($values) + 1 ) {
			throw new Exception("Incorrect number of values for the prepared statement");
		}
		
		$stmt = $preparedStmt[0];
		for($c = 0; $c < count($values); $c++) {
			if( $values[$c] === null )
				$stmt .= "NULL" . $preparedStmt[$c + 1];
			else
				$stmt .= "\"" . $this->escape( $values[$c] ) . "\"" . $preparedStmt[$c + 1];
		}
		
		$this->query($stmt);
	}
	
	
	///////////////////// EXECUTE METHODS //////////////////////
	
	
	/**
	 * Creates an update statement and runs it. Handles escaping, etc.
	 * 
	 * @param mixed $table - the table name, or an array of database + table name
	 * @param array $fields - index is the field name, value is the value
	 * @param mixed $where - the where clause (without "where" in front), or an array to build one
	 */
	public function executeUpdate($table, $fields, $where) {
		
		if( is_array($table) ) {
			$table = '`' . $table[0] . '`.`' . $table[1] . '`';
		} else
			$table = '`' . $table . '`';
		
		$stmt = 'update ' . $table . ' set ';
		$sep = '';
		foreach($fields as $name => $value) {
			if( $value === null )
				$stmt .= $sep . '`' . $name . '`=NULL ';
			else
				$stmt .= $sep . '`' . $name . "`=\"" . $this->escape($value) . "\" ";
			$sep = ',';
		}
		if( is_array($where) )
			$where = $this->buildWhere($where);
		$stmt .= 'where ' . $where;
		
		$this->query($stmt);
		
		//update has no result resource, so query handled the auto-free
	}
	
	/**
	 * Creates an insert statement and runs it. Handles escaping, etc.
	 * 
	 * @param mixed $table - the table name, or an array of database + table name
	 * @param array $fields - index is the field name, value is the value
	 */
	public function executeInsert($table, $fields) {
		if( is_array($table) ) {
			$table = '`' . $table[0] . '`.`' . $table[1] . '`';
		} else
			$table = '`' . $table . '`';
		
		$names = '';
		$values = '';
		$sep = '';
		foreach($fields as $name => $value) {
			$names .= $sep . '`' . $name . '`';
			if( $value === null )
				$values .= $sep . 'NULL';
			else
				$values .= $sep . "\"" . $this->escape($value) . "\"";
			$sep = ',';	//fields after the first one are comma-separated
		}
		$stmt = 'insert into ' . $table . ' (' . $names . ') values (' . $values . ')';
		
		$this->query($stmt);
		
		//update has no result resource, so query handled the auto-free
	}
	
	/**
	 * Creates a delete statement and executes it.  Mostly useful for handling the table name in
	 * the same way as executeUpdate and executeInsert, so you can store it in a config var.
	 * 
	 * @param mixed $table - the table name, or an array of database + table name
	 * @param mixed $where - the where clause (without "where" in front), or an array to build one
	 */
	public function executeDelete($table, $where) {
		if( is_array($table) ) {
			$table = '`' . $table[0] . '`.`' . $table[1] . '`';
		} else
			$table = '`' . $table . '`';
		
		if( is_array($where) )
			$where = $this->buildWhere($where);
		
		$stmt = 'delete from ' . $table . ' where ' . $where;
		
		$this->query($stmt);
		
		//update has no result resource, so query handled the auto-free
	}
	
	/**
	 * Creates a select statement and returns the first result.  Mostly useful for handling the 
	 * table name in the same way as executeUpdate and executeInsert, so you can store it in a 
	 * config var.
	 * 
	 * @param mixed $table - the table name, or an array of database + table name
	 * @param mixed $where - the where clause (without "where" in front), or an array to build one
	 * @param boolean $getFirst (Optional) - return the first row or not
	 * @param string $orderBy (Optional) - order results by this column (can end with " asc" or
	 * 			" desc")
	 * @return array
	 */
	public function executeSelect($table, $where, $getFirst = true, $orderBy = null) {
		if( is_array($table) ) {
			$table = '`' . $table[0] . '`.`' . $table[1] . '`';
		} else
			$table = '`' . $table . '`';
		
		$limit = "";
		if( is_array($where) ) {
			if( isset($where['limit']) ) {
				$limit = ' limit '.$where['limit'];
				unset($where['limit']);
			}
			$where = $this->buildWhere($where);
		}
		if( $where )
			 $where = ' where ' . $where;
		
		if( $orderBy ) {
			$order = ' order by '.$this->buildOrder($orderBy);
		} else
			$order = "";
		
		$stmt = 'select * from ' . $table . $where . $order;
		
		$this->query($stmt);
		
		if( $this->result && $getFirst ) {
			$row = mysql_fetch_assoc($this->result);
			
			if( !$row && $this->autoFree ) {
				@mysql_free_result($result);
				$this->result = null;
				Db::addToPool($this);
			}
			
			return $row;
		}
		
		return null;
	}
	
	
	///////////////////// INFORMATION METHODS //////////////////
	
	
	/**
	 * Gets the last insert id after inserting into a table with an auto-increment field.
	 * @return int
	 */
	public function getInsertId() {
		//commented out, since insert statements don't return a result resource.
		//if( $this->result === null )
		//	return 0;
		return @mysql_insert_id($this->link);
	}
	
	/**
	 * Get the number of rows affected by the last query
	 * @return int
	 */
	public function getAffectedRows() {
		if( $this->result === null )
			return 0;
		return mysql_affected_rows($this->result);
	}
	
	/**
	 * Get the number of rows returned by the last query
	 * @return int
	 */
	public function getNumRows() {
		if( $this->result === null )
			return 0;
		return mysql_num_rows($this->result);
	}
	
	/**
	 * Get the number of fields in the last query
	 * @return int
	 */
	public function getNumFields() {
		if( $this->result === null )
			return 0;
		return mysql_num_fields($this->result);
	}
	
	
	
}



?>
