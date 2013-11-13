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
<title>Test noSQLite raw items</title>
</head>

<body>
<h1>Test noSQLite raw items</h1>
<pre>
<?php
	$collection = $nosqlite->getCollectionByName('fred','raw');
	echo "getCollectionByName('fred') gives:\n" . print_r($collection,1) . "\n \n";
	
	if ( $collection ) {
		$item_id = $nosqlite->putItem($collection['id'],'one','the value of one');
		echo "putItem(".$collection['id'].",'one') id = $item_id \n\n";
		$item_id = $nosqlite->putItem($collection['id'],'two','the value of two');
		echo "putItem(".$collection['id'].",'one') id = $item_id \n\n";
		$item_id = $nosqlite->putItem($collection['id'],'three',array('message'=>'third tim is the charm','content'=>array('A','B','C')));
		echo "putItem(".$collection['id'].",'one') id = $item_id \n\n";
		
		$item = $nosqlite->getItem($collection['id'],'one','raw');
		echo "getItem(".$collection['id'].",'one') item = ".print_r($item,1) . "\n\n";
		$item = $nosqlite->getItem($collection['id'],'two','raw');
		echo "getItem(".$collection['id'].",'two') item = ".print_r($item,1) . "\n\n";
		$item = $nosqlite->getItem($collection['id'],'three','raw');
		echo "getItem(".$collection['id'].",'three') item = ".print_r($item,1) . "\n\n";
		
		$res = $nosqlite->deleteItemByName($collection['id'],'one');
		echo "deleteItem(".$collection['id'].",'one') res = $res \n\n";
		
		$item = $nosqlite->getItem($collection['id'],'one','raw');
		echo "getItem(".$collection['id'].",'one') item = ".print_r($item,1) . "\n\n";
		$item = $nosqlite->getItem($collection['id'],'two','raw');
		echo "getItem(".$collection['id'].",'two') item = ".print_r($item,1) . "\n\n";
		$item = $nosqlite->getItem($collection['id'],'three','raw');
		echo "getItem(".$collection['id'].",'three') item = ".print_r($item,1) . "\n\n";

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