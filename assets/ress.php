<?php
// Register session
session_start();

// Disable caching
header("Cache-Control: no-store"); // HTTP/1.1

if($_REQUEST['density']){
    $_SESSION['density'] = $_REQUEST['density'];
    die();
}

if ( !isset($_SESSION['vw']) || (isset($_REQUEST['vw']) && $_REQUEST['vw'] != $_SESSION['request']) ) {

    $_SESSION['request'] = $_REQUEST['vw'];
    $_SESSION['reloadinfo'] += 1;
    $_SESSION['reload'] = 1;

    // Return nothing
    die(header("HTTP/1.0 404 Not Found"));

} else {

    $_SESSION['vw'] = $_SESSION['request'];

    // Create a valid image
    $im = imagecreate($_SESSION['vw'], 1);
    $bg = imagecolorallocate($im, 255, 255, 255);
    $textcolor = imagecolorallocate($im, 0, 0, 0);

    // Write the string at the top left
    imagestring($im, 5, 0, 0, 'Viewport-width: '.$_SESSION['vw'], $textcolor);

    // Output the image
    header('Content-type: image/png');
    imagepng($im);
    imagedestroy($im);
}