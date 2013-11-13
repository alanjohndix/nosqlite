<?php
require '../init.inc.php';

$nosqlite = new NoSQLite();
$nosqlite->setDatabase(NOSQLITE_DATABASE,NOSQLITE_DB_USER,NOSQLITE_DB_PASSWORD);

debug("DEBUG");

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Test noSQLite collection object</title>
</head>

<body>
<h1>Test noSQLite collection object</h1>
<pre>
<?php
	$collection = $nosqlite->getCollectionByName('fred');
	echo "getCollectionByName('fred') gives:\n" . print_r($collection,1) . "\n \n";
	
	if ( $collection ) {
		$item_id = $collection->put('one','the value of one');
		echo "put('one') id = $item_id \n\n";
		$item_id = $collection->put('two','the value of two');
		echo "put('two') id = $item_id \n\n";
		$item_id = $collection->put('three',array('message'=>'third tim is the charm','content'=>array('A','B','C')));
		echo "put('three') id = $item_id \n\n";
		
		$item = $collection->get('one');
		echo "get('one') item = ".print_r($item,1) . "\n\n";
		$item = $collection->get('two');
		echo "get('two') item = ".print_r($item,1) . "\n\n";
		$item = $collection->get('three');
		echo "get('three') item = ".print_r($item,1) . "\n\n";
		
		$res = $collection->remove('one');
		echo "remove('one') res = $res \n\n";
		
		$item = $collection->get('one');
		echo "get('one') item = ".print_r($item,1) . "\n\n";
		$item = $collection->get('two');
		echo "get('two') item = ".print_r($item,1) . "\n\n";
		$item = $collection->get('three');
		echo "get('three') item = ".print_r($item,1) . "\n\n";

	}

?>
</pre>
<hr />

<pre>
<?php
	debugShow();
?>
</pre>

<hr />

</body>
</html>