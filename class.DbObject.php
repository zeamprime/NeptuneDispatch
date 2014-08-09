<?php
/**	(c) 2009 Everett Morse
*	@author Everett Morse - www.neptic.com
*	
*	Parent class for data model objects that are connected to a database. In terms of design 
*	patterns, these objects act as both bean and DAO. The interface described below is designed to 
*	be very easy to use in code.
*	
*	Notes:
*	- $id must be a single value/object for this interface to work, but it could be a tuple of 
*	  values passed inside an array when you have composite keys.
*	- The getId method requires NULL to be returned when the object is not saved.  This suggests
*	  that you use $this->id !== null as a means to determine if you should insert or update (etc.).
*	  However, there may be times when this is inconvenient, so the suggestion would be to add a 
*	  flag to your object, like $this->isInDb.  For most objects the id should work fine.
*/



abstract class DbObject
{
	
	
	/**
	 * Constructor (can't make an abstract constructor, but follow this anyway)
	 * If an id is given, load the object, otherwise create a blank, unsaved object.
	 * 
	 * Should grab the row from the database and then pass it to loadFrom
	 * 
	 * @param int $id (Optional) - the value of the id column
	 * 
	 * ----
	 * Constructor #2
	 * 
	 * @param array $attributes - array of attributes to assign to this object
	 */
	#abstract public function __construct($id_or_attrs = null);
	
	
	/**
	 * Given a row from the database, populate this object.
	 * 
	 * @param array $data - an associative array
	 */
	abstract public function loadFrom($data);
	
	/**
	 * Save the object, determining whether to insert or update as needed.
	 */
	abstract public function save();
	
	/**
	 * Delete this object from the db.  Don't forget to clear out the id or your $isInDb flag.
	 */
	abstract public function delete();
	
	
	/////////////////////////// ACCESSORS / MUTATORS //////////////////
	
	
	/**
	 * Determine if this object is in the db or still only in memory.
	 * Simple implementation:
	 *		return $this->id !== null;
	 * 
	 * @return boolean
	 */
	abstract public function isInDb();
	
	/**
	 * Get the id for this object.  If the object is not in the db this should return null.
	 * 
	 * @param mixed - value of the id column, or tuple for composite key, or null.
	 */
	abstract public function getId();
	
	
	///////////////////////// STATIC SEARCH METHODS /////////////////
	
	
	/**
	 * Recommended: have static methods to search for and load multiple objects.  These are DAO
	 * methods.
	 * 
	 * Example:
	 * 		
	 * 		public static function getOrdersFor($customerId) {
	 * 			
	 * 			Db::query("select * from Orders where CustomerId = '$customerId'");
	 * 			$results = array();
	 * 			while( ($row = Db::getNextRow()) ) {
	 * 				$obj = new Order();
	 *				$obj->loadFrom($row);
	 *				$results[] = $obj;
	 * 			}
	 * 			return $results;
	 *			
	 * 		}
	 * 
	 */
	#abstract public static function getObjects With / For / etc ( $param );
}

?>