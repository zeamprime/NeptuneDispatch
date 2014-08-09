<?php
/**
 * @author Everett Morse
 * @copyright (c) 2013 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * Example user class.
 */

class User extends ActiveDbObject 
{
	private $privs = array();
	
	
	function __construct($id=null) {
		$this->has_many['identities'] = array('user_identities','user_id');
		$this->has_many['tokens'] = array('user_tokens','user_id');
		//$this->has_many['user_privileges'] = array('user_privileges','user_id');
		parent::__construct($id);
		
		$this->addValidation('primary_email_id', 'validator', 'emailBelongsToUser');
	}
	
	function joinedNow() {
		if( !$this->joined_at || $this->joined_at == '0000-00-00 00:00:00' )
			$this->joined_at = gmdate('Y-m-d H:i:s');
	}
	
	function isStub() {
		return $this->joined_at == '' || $this->joined_at == '0000-00-00 00:00:00';
	}
	
	function emailBelongsToUser($val,$attr) {
		if( $val === null )
			return true; //allow no primary email.
		$email = Email::find($val);
		if( !$email )
			return "Email record does not exist";
		if( $email->user_id != $this->id )
			return "Email does not belong to this user";
		return true;
	}
	
	////////////////////// Login /////////////////
	
	/**
	 * Set the User's password, which requires generating new salt, hashing, and saving the hash
	 * and salt in the password field.
	 */
	function setPassword($pass) {
		$salt = substr(md5(rand()), 0, 10);
		$this->password = $salt.$this->hashPassword($pass, $salt, constant('PASS_SECRET'));
	}
	
	/**
	 * Check if a user-entered password matches the password for this User.
	 */
	function checkPassword($pass) {
		$salt = substr($this->password, 0, 10);
		if( $this->password == $salt.$this->hashPassword($pass, $salt, constant('PASS_SECRET')) )
			return true;
		else
			return false;
	}
	
	function hashPassword($pass, $salt, $secret) {
		$hash = sha1($salt.$pass.$secret);
		$pre = $this->xorstr($salt, 0x5c);
		$post = $this->xorstr($salt, 0x36);
		for($i = 0; $i < 1000; $i++) {
			$hash = sha1($pre.$hash.$post); //attempt to add more entropy
		}
		return $hash;
	}
	
	function xorstr($str, $c) {
		$out = "";
		for($i = 0; $i < strlen($str); $i++) {
			$out .= chr( $c ^ ord(substr($str, $i, 1)) );
		}
		return $out;
	}
	
	/**
	 * Check if a password is good enough.
	 */
	public function isValidPassword($password, &$errors) {
		$valid = true;
		if( strlen($password) < 8 ) {
			if($errors !== null) $errors[] = "The password must have at least 8 chars.";
			return false; //no point checking other things
		}
		if( !preg_match('/[a-z]/',$password) ) {
			if($errors !== null) $errors[] = "You must have at least one lower-case letter.";
			$valid = false;
		}
		if( !preg_match('/[A-Z]/',$password) ) {
			if($errors !== null) $errors[] = "You must have at least one upper-case letter.";
			$valid = false;
		}
		if( !preg_match('/[0-9]/',$password) ) {
			if($errors !== null) $errors[] = "You must have at least one number.";
			$valid = false;
		}
		if( strpos(strtolower($this->name),strtolower($password)) !== false ) {
			if($errors !== null) $errors[] = "The password cannot contain your name.";
			$valid = false;
		}
		return $valid;
	}
	
	/////////////////// End Login //////////////////
	
	/////////////////// Privileges /////////////////
	
	
	/**
	 * Get this user's privileges in a certain group.
	 * @param $group : int - group id
	 * @returns UserPrivileges or null
	 */
	public function getPrivilegesInGroup($group) {
		if( !isset($this->privs[$group]) ) {
			$privs = UserPrivilege::where(array(
				'user_id' => $this->id,
				'group_id' => $group
			));
			if( count($privs) == 0 )
				$this->privs[$group] = null;
			else
				$this->privs[$group] = $privs[0];
		}
			
		return $this->privs[$group];
	}
	
	/**
	 * Check a privilege.
	 * @param $priv : string - the name of the priv to check. This is prefixes with 'G:'
	 *                         for a group priv or 'P:' for a poll priv.
	 * @param $group : int - the group id
	 * @returns boolean
	 */
	public function hasPrivilegeInGroup($priv, $group) {
		$privs = $this->getPrivilegesInGroup($group);
		if( $privs === null ) return false;
		return $privs->has($priv, true);
	}
	
	/**
	 * Same as hasPrivilegeInGroup, but throws a REST exception if it fails.
	 */
	public function requirePriv($priv, $groupId) {
		if( !$this->hasPrivilegeInGroup($priv, $groupId) )
			throw new RestException("UserId", "Not authorized for $priv in group "
					.$groupId.'.', 403);
	}
	
	/**
	 * Check if the user has at least one privilege in the given list.
	 * 
	 * @param $privs : string[] - list of acceptable privileges. (You must include 
	 							  G:SuperAdmin yourself if desired.)
	 * @param $groupId : int
	 * @param $desc : string (Opt) - Use this in the error message if none found.
	 * @throws RestException
	 */
	public function requireOnePriv($privs, $groupId, $desc = null) {
		if( !$this->hasOnePrivilegeInGroup($privs, $groupId) ) {
			if( $desc === null ) {
				$last = array_pop($privs);
				$desc = implode(', ',$privs).(count($privs)>1? ',':'').' or '.$last;
			}
			throw new RestException("UserId", "Not authorized for $desc in group ".
					$groupId.'.', 403);
		}
	}
	
	public function hasOnePrivilegeInGroup($privs, $group) {
		//NOTE: There could be a more efficient way to do this if we didn't have two 
		// separate sets of privs with the G: and P: prefixes.  Or if we stuck them 
		// together in a list and just named them that way maybe.
		
		$groupPrivs = $this->getPrivilegesInGroup($group);
		if( !$groupPrivs )
			return false;
		foreach($privs as $priv) {
			if( $groupPrivs->has($priv,false) )
				return true;
		}
		return false;
	}
	
	
	///////////////// End Privileges ///////////////
	
	
}
?>
