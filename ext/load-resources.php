<?php
if (isset ( $_GET )) {
	switch ($_GET ['type']) {
		case 'css' :
			$header = 'text/css';
			$file = 'ext-main.css.gz';
			break;
		
		case 'js' :
			$header = 'text/javascript';
			$file = 'ext-main.js.gz';
			break;
	}
}
$offset = 60 * 60;
$expires = "Expires: " . gmdate ( "D, d M Y H:i:s", time () + $offset ) . " GMT";

//passing headers
header ( "content-type: $header; charset: UTF-8" );
header ( 'Cache-Control: max-age = 2592000' );
header ( $expires );
header ( "Content-Encoding: gzip" );
readfile ( $file );
exit();
?>