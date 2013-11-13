<?php
require '../init.inc.php';

$nosqlite = new NoSQLite();
$nosqlite->setDatabase(NOSQLITE_DATABASE,NOSQLITE_DB_USER,NOSQLITE_DB_PASSWORD);

$magic = $_REQUEST['magic'];

define('THE_MAGIC','abracadabra');

if ( $magic == THE_MAGIC ) {
	$dropped_OK = $nosqlite->dropTables();
	$need_magic = false;
} else {
	$dropped_OK = false;
	$need_magic = true;
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Drop noSQLite tables</title>
</head>

<body>
<h1>Drop noSQLite tables</h1>
<?php
	if ( $need_magic ) :
?>
<p>
This will remove all your noSQLite data; is this what you really want?</p>
<form action="drop_tables.php" method="post"><input name="magic" type="hidden" value="<?php echo THE_MAGIC; ?>" />
<label
for="Confirm">Please confirm that you want to drop tables</label>
: 
<input id="Confirm" name="Confirm" type="submit" value="Confirm" /></form>
<?php
	elseif ( $dropped_OK ) :
?>
<p>
Tables dropped OK :-)
<br />
<a href="create_tables.php">recreate new noSQLite tables</a>
</p>
<?php
	else :
?>
<p>
Failed to drop tables :-(
</p>
<?php
	endif;
?>

<hr />

<pre>
<?php
	debugShow();
?>
</pre>

</body>
</html>