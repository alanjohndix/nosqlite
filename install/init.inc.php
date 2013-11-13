<?php
error_reporting(E_ALL ^ E_NOTICE);
ini_set('display_errors',true);

$installRoot = dirname(__FILE__);
$appRoot = dirname($installRoot);
//$appRoot = $installRoot;

require_once $appRoot . '/config.php' ;

set_include_path(get_include_path() . PATH_SEPARATOR . $installRoot);
set_include_path(get_include_path() . PATH_SEPARATOR . $installRoot . '/lib');

define('LIB_DIR', $installRoot . '/lib'.DIRECTORY_SEPARATOR);
define('EXAMPLES_DIR', $installRoot.'/examples/');
if ( ! defined('ALAN_DIR') ) {
	define('ALAN_DIR',LIB_DIR.'alan'.DIRECTORY_SEPARATOR);
}

if ( ! defined('RDF_TYPE') ) {
	define( 'RDF_TYPE', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' );
}


require_once ALAN_DIR . 'util.php';
require_once ALAN_DIR . 'log.php';

DebugLog::theDebugLog('save');

if ( ! defined('IN_API') ) {
	define('IN_API',false);
}
if ( ! defined('BASE_URL') ) {
	$myurl = curPageURL();
	$pos = strrpos($myurl,'/');
	if ( $pos === false ) {
		$base = "$myurl";  // weird something wrong
	} else {
		$base = substr($myurl,0,$pos+1);  // N.B. inlcudes trailing '/'; 
	}
	if ( IN_API ) {
		if ( ends_with($base,'api/') ) {
			$base = strip_suffix($base,'api/');
		}
	}
	
	define('BASE_URL',$base);
}
if ( ! defined('API_URL') ) {
	define('API_URL',BASE_URL . 'api/');
}

if ( IN_API ) {
	require_once ALAN_DIR . 'jsonservice.class.php';
}

require_once LIB_DIR . 'nosqlite.class.php';

?>