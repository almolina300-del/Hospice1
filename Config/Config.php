<?php
define('SQL_HOST','localhost');
define('SQL_USER','root');
define('SQL_PASS','');
define('SQL_DB','hospice');

$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>