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
<title>Test noSQLite raw collections</title>
</head>

<body>
<h1>Test noSQLite raw collections</h1>
<pre>
<?php
	$res = $nosqlite->deleteCollectionByName('fred');
	echo "deleteCollectionByName('fred') gives $res \n";	
	$res = $nosqlite->deleteCollectionByName('tom');
	echo "deleteCollectionByName('tom') gives $res \n";
	$res = $nosqlite->deleteCollectionByName('harry');
	echo "deleteCollectionByName('harry') gives $res \n";

	$f_id = $nosqlite->createCollection('fred',array('a'=>1,'b'=>'hello world','c'=>array('x'=>42)));
	echo "inserted fred id = $f_id \n\n";

	$f2_id = $nosqlite->createCollection('fred','bye bye');
	echo "inserted fred id = $f2_id \n\n";

	$t_id = $nosqlite->createCollection('tom','This is Tom');
	echo "inserted tom id = $t_id \n\n";
	
	$collection = $nosqlite->getCollection($f_id,'raw');
	echo "getCollection($f_id) gives:\n" . print_r($collection,1) . "\n \n";

	$collection = $nosqlite->getCollection($t_id,'raw');
	echo "getCollection($t_id) gives:\n" . print_r($collection,1) . "\n \n";

	$collection = $nosqlite->getCollectionByName('fred','raw');
	echo "getCollectionByName('fred') gives:\n" . print_r($collection,1) . "\n \n";

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