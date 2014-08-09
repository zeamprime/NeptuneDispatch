<?php
/**
 * @author Everett Morse
 * Copyright (c) 2014 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * 
 */

class Migration{%version%} extends Migration {
	function info() {
		return "n/a";
	}
	function up() {
		Db::query("
			create table `{%table%}` (
				id int(11) auto_increment primary key
				
			)
		");
		
	}
	function down() {
		Db::query("drop table `{%table%}`");
	}
}
?>
