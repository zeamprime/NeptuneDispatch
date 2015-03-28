<?php
/**
*	Set up database, default routes, etc.
*/

/* Environment - Some things are more verbose in dev */
define('ENV','dev'); //dev, prod, test

/* Set up database */
include_once dirname(__FILE__).'/../lib/engine/class.Db.php';
Db::setParams(
	"localhost",	//host
	"web_user",		//username
	"password",		//password
	"myproject"		//default db
);
//Default is ERR_HALT which prints backtrace and exits. Good for debugging, exception is 
// better for production.
if( constant('ENV') == 'prod' ) {
	Db::$errorMode = Db::ERR_EXCEPTION; 
}
define('DB_PRE','');

/* Default routes */
define('HOME_PATH','home');
define('DEFAULT_LAYOUT','');

/* PHP insists that you tell it the time zone */
date_default_timezone_set('America/Los_Angeles');

/* Secure passwords by also hashing with a secret that isn't in the db */
define('PASS_SECRET',"asbcdefghijksldf");

/* Add middleware as comma-separated lists */
define('API_MIDDLEWARE','HMAC,RequireUser');

/* Configure email */
Mailer::DEFAULT_HOST = "smtp.gmail.com";
Mailer::DEFAULT_AUTH_USER = "username";
Mailer::DEFAULT_AUTH_PASS = "password";
Mailer::DEFAULT_FROM = "robot@example.com";

?>
