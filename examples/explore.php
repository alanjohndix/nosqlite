<?php
require '../init.inc.php';

$nosqlite = new NoSQLite();
$nosqlite->setDatabase(NOSQLITE_DATABASE,NOSQLITE_DB_USER,NOSQLITE_DB_PASSWORD);

debug("DEBUG");

$collection = $_REQUEST['collection'];
$action = $_REQUEST['action'];

$message = false;

if ( $collection ) {
	$collection_data = $nosqlite->getCollectionByName($collection);
//	$header = "Items in collection " . htmlentities($collection);
	if ( $action == 'delete_item' ) {
		$name = $_REQUEST['name'];
		if ( $collection_data->remove($name) ) {
			$message = "Deleted Item ".$name." from collection.";
		} else {
			$message = "Couldn't delete Item ".$name." from collection.";
		}
	} 
	$header = "Items in collection " . $collection;
	$tolist = $collection_data->getAll('raw');
	$is_collection = true;
	$is_collection_list = false;
	$collection_url = "explore.php?collection=".urlencode($collection);
} else {
	$header = "Collections";
	$tolist = $nosqlite->getAllCollections('raw');
	$is_collection = false;
	$is_collection_list = true;
}


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title><?php echoh( $header ); ?></title>
</head>

<body>
<h1><?php echoh( $header ); ?></h1>
<?php
	if ( $message ) {
		echo "<p>".htmlentities($message)."</p>\n";
	}
?>
<?php
	if ( $is_collection ) {
		echo "<h2>Meta Information</h2>\n";
		echo "<pre>".print_r($collection_data->meta,1)."</pre>\n		
<hr>\n";
	echo "<h2>Items</h2>\n";
	}
	if ( $tolist ) {
		echo "<table> \n";
		foreach ( $tolist as $item ) {
			$hid = htmlentities( $item['id'] );
			$hname = htmlentities( $item['name'] );
			$hvalue = htmlentities( print_r($item['value'],1) );
			if ( $is_collection_list ) {
				$url = htmlentities( "explore.php?collection=".urlencode($item['name']) );
				$hname = "<a href=\"".$url."\">".$hname."</a>";
			} else {
				$delete_url = htmlentities( $collection_url . "&action=delete_item&name=".urlencode($item['name']) );
				$actions = "<a href=\"".$delete_url."\">delete</a>";
			}
			echo "<tr><td valign=\"top\">".$hid."</td><td valign=\"top\">".$actions."</td><td valign=\"top\"0>".$hname."</td><td valign=\"top\" width=350><pre>".$hvalue."</pre></td></tr> \n";
		}
		echo "</table> \n";
	} else {
		echo "<p>no items</p>\n";
	}
?>
<hr />

<pre>
<?php
	debugShow();
?>
</pre>

<hr />

</body>
</html>