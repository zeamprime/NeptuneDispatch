<?php
/**
 * @author Everett Morse
 * Copyright (c) 2013 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * A REST API Model that handles displaying help for other API models.
 * 
 * It can either create an index of models, an index of methods for a model, or show the help for a
 * single method on a model.
 * 
 * The output format is either Markdown or HTML. Both will be cached to avoid constant generation.
 */

define('MARKDOWN_LIB',"/lib/engine/vendor/Michelf/Markdown.php")
define('MARKDOWN_EXTRA_LIB',"/lib/engine/vendor/Michelf/MarkdownExtra.php")

class RestHelp extends RestModel
{
	// Some info about what we're getting help on.
	public $modelName;  // : string
	public $plural;     // : boolean
	public $displayName;// : string
	
	/**
	 * When model name is plural.
	 * 
	 * E.g. /help/customers
	 * E.g. /help/customers/get
	 */
	public function index($method, $_ignored, $params) {
		return $this->fetch($method, null, $params);
	}
	
	/**
	 * When model name is singlular.
	 * Also redirect from the other one.
	 */
	public function fetch($method, $_ignored, $params) {
		$this->format = $params['_response_type'];
		$method = strtoupper($method);
		
		//We need the model that we're looking up help on.
		$modelName = $this->modelName;
		if( $modelName ) {
			$filepath = ROOT_DIR . '/app/api/' . $modelName . '.php';
			if( !file_exists($filepath) ) {
				throw new Exception("Cannot find model \"{$modelName}\" for showing help.");
			}
			require_once($filepath);
			$this->displayName = Util::camelizeClass($modelName);
			$className = $this->displayName.'RestController';
			$model = new $className;
		}
		
		//Get an index or help on a specific action.
		if( $model !== null && $method ) {
			if( $this->plural ) {
				switch($method) {
					case 'GET': $action = 'index'; break;
					case 'POST': $action = 'bulk_create'; break;
					case 'PUT': $action = 'bulk_update'; break;
					case 'DELETE': $action = 'bulk_delete'; break;
					default: $action = null; break;
				}
			} else {
				switch($method) {
					case 'GET': $action = 'fetch'; break;
					case 'POST': $action = 'create'; break;
					case 'PUT': $action = 'update'; break;
					case 'DELETE': $action = 'delete'; break;
					default: $action = null; break;
				}
			}
			if( $action === null ) {
				//Fake action or custom one?
				$method = strtolower($method);
				if( preg_match("/^[-_a-z]+$/",$method) > 0 ) {
					$mdpath = ROOT_DIR.'/app/api/help/'.$this->modelName.'-custom-'.$method.'.md';
					if( file_exists($mdpath) ) {
						return $this->getCustomActionHelp($model, $method, $mdpath);
					}
				}
				return "The operation \"$method\" is not supported on \"$modelName\".";;
			}
			
			if( !method_exists($model, $action) && !$model->getActionMethod($action) ) {
				return "The operation \"$action\" is not supported on \"$modelName\".";
			}
			
			return $this->getHelp($action, $model);
			
		} else if( $this->modelName ) {
			return $this->getModelActions($model);
		} else {
			return $this->getModelsList();
		}
	}
	
	private function getCustomActionHelp($model, $action, $mdpath) {
		//Check cache first
		if( $this->format === 'html' ) {
			$cachepath = ROOT_DIR . "/cache/help-{$this->modelName}-custom-{$action}.html";
			if( file_exists($cachepath) ) {
				//Compare the modified time md source file.
				if( filemtime($cachepath) >= filemtime($mdpath) )
					return file_get_contents($cachepath);
			}
		}
		
		$md = file_get_contents($mdpath);
		if( $this->format == 'html' ) {
			//Need to render the Markdown text
			
			include_once ROOT_DIR.MARKDOWN_LIB;
			include_once ROOT_DIR.MARKDOWN_EXTRA_LIB;
			$html = \Michelf\MarkdownExtra::defaultTransform($md);
			
			$html = "<html><head>\n"
					. "<title>API Models</title>\n"
					. "<link rel='stylesheet' type='text/css' href='".
							Page::absPath("/inc/help.css")."'/>\n"
					. "</head><body>\n"
					. $html . "\n"
					. "</body></html>\n";
			
			//Update the cache
			@file_put_contents($cachepath, $html);
			
			return $html;
		} else
			return $md;
	}
	
	private function getHelp($action, $model) {
		$mdfile = ROOT_DIR.'/app/api/help/'.$this->modelName.'-'.$action.'.md';
		
		//Check cache first
		if( $this->format === 'html' ) {
			$cachepath = ROOT_DIR . "/cache/help-{$this->modelName}-{$action}.html";
			if( file_exists($cachepath) ) {
				//Compare the modified time md source file, or class file.
				if( file_exists($mdfile) )
					$filepath = $mdfile;
				else
					$filepath = ROOT_DIR . '/app/api/' . $this->modelName . '.php';
				if( filemtime($cachepath) >= filemtime($filepath) )
					return file_get_contents($cachepath);
			}
		}
		
		$text = "";
		if( file_exists($mdfile) ) {
			$text = file_get_contents($mdfile);
		} else if( substr($action,0,5) == 'bulk_' ) {
			$mdtemplate = ROOT_DIR.'/app/api/help/'.$action.'.md.php';
			if( file_exists($mdtemplate) ) {
				ob_start();
				include($mdtemplate);
				$text = ob_get_contents();
				ob_end_clean();
			}
		} else {
			//See if we can generate the markdown from the models
			
			$mdcachepath = ROOT_DIR . "/cache/help-{$this->modelName}-{$action}.md";
			$filepath = ROOT_DIR . '/app/api/' . $this->modelName . '.php';
			if( file_exists($mdcachepath) && 
				filemtime($mdcachepath) >= filemtime($filepath)
			) {
				$text = file_get_contents($mdcachepath);
			} else {
				$text = $this->generateHelp($model, $action);
				@file_put_contents($mdcachepath, $text);
			}
		}
		
		if( $this->format == 'html' ) {
			//Need to render the Markdown text
			
			include_once ROOT_DIR."MARKDOWN_LIB";
			include_once ROOT_DIR.MARKDOWN_EXTRA_LIB;
			$html = \Michelf\MarkdownExtra::defaultTransform($text);
			
			$html = "<html><head>\n"
					. "<title>API Models</title>\n"
					. "<link rel='stylesheet' type='text/css' href='".
							Page::absPath("/inc/help.css")."'/>\n"
					. "</head><body>\n"
					. $html . "\n"
					. "</body></html>\n";
			
			//Update the cache
			@file_put_contents($cachepath, $html);
			
			return $html;
			
		} else
			return $text;
	}
	
	/**
	 * Lists all actions available for this model.
	 */
	private function getModelActions($model) {
		//First check cache
		if( $this->format === 'html' ) {
			$cachepath = ROOT_DIR . "/cache/help-{$this->modelName}-actions.html";
		} else {
			$cachepath = ROOT_DIR . "/cache/help-{$this->modelName}-actions.md";
		}
		if( file_exists($cachepath) ) {
			//Compare the modified time to the model's file mod time.
			$filepath = ROOT_DIR . '/app/api/' . $this->modelName . '.php';
			iF( filemtime($cachepath) >= filemtime($filepath) )
				return file_get_contents($cachepath);
		}
		
		//Collect list
		$check = array(
			array('index', true, 'get'),
			array('fetch', false, 'get'),
			array('create', false, 'post'),
			array('update', false, 'put'), 
			array('delete', false, 'delete'),
			array('bulk_create', true, 'post'),
			array('bulk_update', true, 'put'),
			array('bulk_delete', true, 'delete')
		);
		$supported = array();
		foreach($check as $info) {
			if( method_exists($model, $info[0]) || $model->getActionMethod($info[0]) )
				$supported[] = $info;
		}
		
		//See if there is supplemental data
		$mdExtraPath = ROOT_DIR.'/app/api/help/'.$this->modelName.'-actions.md';
		$mdextra = "";
		if( file_exists($mdExtraPath) ) {
			$mdextra = file_get_contents($mdExtraPath);
		}
		
		//Generate output
		$dispName = $this->displayName;
		$desc = $model->metaDescription();
		if( $this->format === 'html' ) {
			$result = "<html><head>\n"
					. "<title>Actions for {$dispName}</title>\n"
					. "<link rel='stylesheet' type='text/css' href='".
							Page::absPath("/inc/help.css")."'/>\n"
					. "</head><body>\n";
			$result .= "<h1>Actions for {$dispName}</h1>";
			if( $desc )
				$result .= "<p>$desc</p>";
			if( count($supported) > 0 ) {
				$result .= "<ul>\n";
				foreach($supported as $info) {
					$modelName = $info[1]? Util::plural($this->modelName) : $this->modelName;
					$url = Page::absPath("/help/$modelName/{$info[2]}.html");
					$result .= "<li><a href=\"$url\">{$info[0]}</a></li>\n";
				}
				$result .= "</ul>\n";
			} else {
				$result .= "<b>None</b>";
			}
			if( $mdextra ) {
				include_once ROOT_DIR."/lib/Michelf/engine/vendor/Markdown.php";
				include_once ROOT_DIR."/lib/Michelf/engine/vendor/MarkdownExtra.php";
				$result .= \Michelf\MarkdownExtra::defaultTransform($mdextra);
			}
			$result .= "</body></html>\n";
		} else {
			$result = "# Actions for {$dispName}\n\n";
			if( $desc )
				$result .= "$desc\n\n";
			foreach($supported as $info) {
				$name = $info[1]? Util::plural($this->modelName) : $this->modelName;
				$url = Page::absPath("/help/$name/{$info[2]}.{$this->format}");
				$result .= "- [{$info[0]}]($url)\n";
			}
			$result .= $mdextra;
		}
		
		//Save cache, then return.
		@file_put_contents($cachepath, $result);
		return $result;
	}
	
	/**
	 * Looks for all REST API model files.
	 */
	private function getModelsList() {
		//First check cache
		if( $this->format === 'html' ) {
			$cachepath = ROOT_DIR . '/cache/help-index.html';
		} else {
			$cachepath = ROOT_DIR . '/cache/help-index.md';
		}
		if( file_exists($cachepath) )
			return file_get_contents($cachepath);
		
		//Scan API dir
		$files = scandir(ROOT_DIR.'/app/api');
		$modelNames = array();
		foreach($files as $file) {
			if( is_dir(ROOT_DIR.'/app/api/'.$file) ) continue;
			if( substr($file, -4) !== '.php' ) continue;
			$modelNames[] = substr($file,0,-4);
		}
		
		//See if we have additional message text
		$mdpath = ROOT_DIR.'/app/api/help/home.md';
		if( file_exists($mdpath) ) {
			$help = file_get_contents($mdpath);
			if( $this->format == 'html' ) {
				include_once ROOT_DIR."/lib/Michelf/engine/vendor/Markdown.php";
				include_once ROOT_DIR."/lib/Michelf/engine/vendor/MarkdownExtra.php";
				$help = \Michelf\MarkdownExtra::defaultTransform($help);
			}
		} else
			$help = "";
		
		if( $this->format === 'html' ) {
			$result = "<html><head>\n"
					. "<title>API Models</title>\n"
					. "<link rel='stylesheet' type='text/css' href='".
							Page::absPath("/inc/help.css")."'/>\n"
					. "</head><body>\n";
			$result .= "<h1>API Models</h1>";
			$result .= "<ul>\n";
			foreach($modelNames as $name) {
				$Name = Util::camelizeClass($name);
				$result .= "<li><a href=\"".Page::absPath("/help/$name.html")."\">$Name</a></li>\n";
			}
			$result .= "</ul>\n";
			$result .= $help;
			$result .= "</body></html>\n";
		} else {
			$result = "# API Models\n\n";
			foreach($modelNames as $name) {
				$Name = Util::camelizeClass($name);
				$result .= "- [$Name](".Page::absPath("/help/$name.".$this->format).")\n";
			}
			$result .= "\n".$help;
		}
		
		//Save cache, then return.
		@file_put_contents($cachepath, $result);
		return $result;
	}
	
	/**
	 * Given a model, probe it for information to generate a Markdown help file for the
	 * specified action.
	 * 
	 * Bulk calls do not come here, since they have a generic template that refers to the
	 * singular call's documentation.
	 */
	private function generateHelp($model, $action) {
		/*
		Plan:
		
		Use generic templates and replace properties like ./scripts/generate does. For
		actions update, create, and fetch get lists of allowed, required, and/or returned
		attributes along with validations on the model and generate descriptive tables
		to insert into the templates.
		
		Sections:
		- Description
		- Parameters -- update, create
		- Filters -- index, fetch
		- Response fields -- fetch
		- Response example
		- Errors generic
		- Errors validations -- update, create
		
		*/
		
		$metadata = $model->metadataFor($action);
		
		if( !isset($metadata['example_json']) )
			$metadata['example_json'] = "";
		$metadata['example_length'] = strlen($metadata['example_json']);
		
		$s = $_SERVER['HTTPS']? 's' : '';
		$path = ($action == 'index')? $metadata['names'] : $metadata['name'];
		if( in_array($action, array('update','fetch','delete')) )
			$path .= '/`*&lt;identifier&gt;*`';
		$metadata['example_url'] = Page::fullURL('/'.$path.'.json');
		
		//echo "<pre>"; var_dump($metadata); exit;
		if( $action == 'index' ) {
			$metadata['filter_table'] = $this->generateFiltersTable($metadata);
		} else if( $action == 'fetch' ) {
			$metadata['filter_table'] = $this->generateFiltersTable($metadata);
			$metadata['response_table'] = $this->generateResponseTable($metadata);
		} else if( $action == 'update' ) {
			$metadata['param_table'] = $this->generateParamTable($metadata);
			$metadata['response_table'] = $this->generateResponseTable($metadata);
			$metadata['error_table'] = $this->generateErrorTable($metadata);
		} else if( $action == 'create' ) {
			$metadata['param_table'] = $this->generateParamTable($metadata);
			$metadata['response_table'] = $this->generateResponseTable($metadata);
			$metadata['error_table'] = $this->generateErrorTable($metadata);
		}
		
		if( $metadata['security'] ) {
			$metadata['security'] = "## Security\n\n".$metadata['security']."\n";
		}
		
		
		$tpl = $this->readTemplate($action);
		if( $tpl === false ) return "";
		$text = $this->replaceTemplates($tpl, $metadata);
		
		$text .= "\n\n<small>*Modified: ".date("m/d/Y H:i:s")."*</small>";
		return $text;
	}
	
	function readTemplate($name) {
		$path = dirname(__FILE__).'/help-templates/'.$name.'.md';
		if( file_exists($path) ) {
			return file_get_contents($path);
		} else
			return false;
	}

	function replaceTemplates($file, $templates) {
		foreach($templates as $name => $value) {
			$file = str_replace("{%$name%}",$value,$file);
		}
		return $file;
	}
	
	private function generateFiltersTable($metadata) {
		if( count($metadata['filters']) == 0 )
			return "**None**";
		
		$md =  "| Parameter | Description | Example |\n";
		$md .= "|-----------|-------------|---------|\n";
		foreach($metadata['filters'] as $info) {
			$md .= '| '.$info['name'].' | '.$info['desc'].' | ';
			if( isset($info['example']) )
				$md .= $info['example'];
			$md .= " |\n";
		}
		
		return $md;
	}
	
	private function generateResponseTable($metadata) {
		if( count($metadata['attributes']) == 0 )
			return "**None**";
		
		$md =  "| Field       | Type    | Description                         |\n";
		$md .= "|-------------|---------|-------------------------------------|\n";
		foreach($metadata['attributes'] as $attr => $info) {
			$md .= "| {$attr} | {$info['type']} | {$info['desc']} |\n";
		}
		
		return $md;
	}
	
	private function generateParamTable($metadata) {
		if( count($metadata['attributes']) == 0 )
			return "**None**";
		
		$md =  "| Parameter    | Type     | Opt? | Description                  |\n";
		$md .= "|--------------|----------|------|------------------------------|\n";
		foreach($metadata['attributes'] as $attr => $info) {
			$opt = $info['required']? 'No' : 'Yes';
			$md .= "| {$attr} | {$info['type']} | $opt | {$info['desc']} |\n";
		}
		
		return $md;
	}
	
	private function generateErrorTable($metadata) {
		if( count($metadata['attributes']) == 0 )
			return "**None**";
		
		$hasValidations = false;
		
		$md =  "| Field       | Error                    |\n";
		$md .= "|-------------|--------------------------|\n";
		foreach($metadata['attributes'] as $attr => $info) {
			if( !isset($info['validations']) ) continue;
			foreach($info['validations'] as $check) {
				$hasValidations = true;
				$md .= "| $attr | $check |\n";
			}
		}
		
		if( !$hasValidations )
			return "**None**";
		
		return $md;
	}
	
}


?>