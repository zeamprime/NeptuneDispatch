<?php
/**	(c) 2015 Everett Morse
*	@author Everett Morse - www.neptic.com
*	
*	Construct and send emails. Based on PHPMailer library but loads in default config.
* 
*	There should be subclasses of this in app/controllers/MyMailer.php which should set up values
*	for to, from, subject, view and data. Then whoever instantiated the mailer can call send on it.
*/

class Mailer extends PHPMailer
{
	//Static config
	public static $DEFAULT_HOST = '';
	public static $DEFAULT_TYPE = 'smtp';
	public static $DEFAULT_SECURE = 'tls';
	public static $DEFAULT_PORT = '587';
	
	public static $DEFAULT_AUTH_USER = '';
	public static $DEFAULT_AUTH_PASS = '';
	
	public static $DEFAULT_FROM = '';
	public static $DEFAULT_FROM_NAME = 'Mailer';
	
	//Instance vars for rendering
	protected $view = 'default';
	protected $data = array();
		
	
	public function __construct() {
		//Apply static defaults to this instance
		if( self::$DEFAULT_HOST != '' && $this->Host == '' )
			$this->Host = self::$DEFAULT_HOST;
		if( self::$DEFAULT_TYPE != '' && $this->Mailer == ''  ) {
			$this->Mailer = self::$DEFAULT_TYPE;
		}
		if( self::$DEFAULT_SECURE != '' && $this->SMTPSecure == '' )
			$this->SMTPSecure = self::$DEFAULT_SECURE;
		if( self::$DEFAULT_PORT != '' && $this->Port == '' )
			$this->Port = self::$DEFAULT_PORT;
		
		if( self::$DEFAULT_AUTH != '' && $this->Username == '' ) {
			$this->SMTPAuth = true;
			$this->Username = self::$DEFAULT_AUTH;
		}
		if( self::$DEFAULT_AUTH_PASS != '' && $this->Password == '' )
			$this->Password = self::$DEFAULT_AUTH_PASS;
		
		if( self::$DEFAULT_FROM != '' && $this->From == '' )
			$this->From = self::$DEFAULT_FROM;
		if( self::$DEFAULT_FROM_NAME != '' && $this->FromName == '' )
			$this->FromName = self::$DEFAULT_FROM_NAME;
	}
	
	/**
	 * Load templates for HTML and/or plain text parts and replace mail merge values into it.
	 * Then set this as the body of the mail.
	 */
	public function render($view, $data) {
		$viewFile = Page::filepath("app/views/$view");
		
		if( file_exists($viewFile.'.html') ) {
			$this->isHTML(true);
			$html = file_get_contents($viewFile.'.html');
			$html = $this->replace($html, $data);
			$this->Body = $html;
		}
		
		if( file_exists($viewFile.'.txt') ) {
			$txt = file_get_contents($viewFile.'.txt');
			$txt = $this->replace($txt, $data);
			if( $this->Body == '' )
				$this->Body = $txt;
			else
				$this->AltBody = $txt;
		}
	}
	
	private function replace($tpl, $data) {
		foreach($data as $name => $value) {
			$tpl = str_replace($tpl, "{%{$name}%}", $value);
		}
		return $tpl;
	}
	
	/**
	 * Override to make sure we rendered the message body.
	 */
	public function send() {
		if( $this->Subject == '' )
			$this->Subject = "Message from Mailer";
		
        if( $this->Body == '' ) {
        	$this->render($this->view, $this->data);
        }
        
        return parent::send()
    }
	
}

?>