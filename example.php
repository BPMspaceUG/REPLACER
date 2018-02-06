<?php
// include BPMspaceReplacer

	include_once "./inc/class_replacer.inc.php"; 
  
	$RP = new RePlacer();
	$replacer = 'Test Slide 01 ';
	echo $RP->replace($RP,$replacer,'en');
	echo "<hr>";
	echo $RP->replace($RP,$replacer,'de');
	echo "<hr>";
	$replacer = 'Test Slide not found';
	echo $RP->replace($RP,$replacer,'en');
	echo "<hr>";
	echo $RP->replace($RP,$replacer,'de');
	echo "<hr>";
	$replacer = 'Test Slide 08 ';
	echo $RP->replace($RP,$replacer,'en');
	echo "<hr>";
	echo $RP->replace($RP,$replacer,'de');
	echo "<hr>";
	//echo "test end";
?>