<?php
session_start();

// Create a captcha image
$width = 100;
$height = 30;
$image = imagecreatetruecolor($width, $height);

// Set the color
$bgColor = imagecolorallocate($image, 255, 255, 255);
$textColor = imagecolorallocate($image, 0, 0, 0);
$lineColor = imagecolorallocate($image, 200, 200, 200);

// Fill the background
imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

// Generate random captchas
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$captcha = '';
for ($i = 0; $i < 5; $i++) {
    $captcha .= $chars[rand(0, strlen($chars) - 1)];
}

// Store the verification code to the session
$_SESSION['captcha'] = $captcha;

// Add interference lines
for ($i = 0; $i < 5; $i++) {
    imageline($image, 0, rand() % $height, $width, rand() % $height, $lineColor);
}

// Write the captcha text
imagestring($image, 5, 20, 7, $captcha, $textColor);

// Output an image
header('Content-type: image/png');
imagepng($image);
imagedestroy($image);
?>