<?php
// include BPMspaceReplacer

	include_once "./inc/class_replacer.inc.php"; 
  
	$RP = new RePlacer();
	$replacer = 'TEST_REPLACE';
	echo $RP->replace($RP,$replacer,'en');
	echo "<hr>";
	echo $RP->replace($RP,$replacer,'de');
	echo "<hr>";
	$replacer = 'TEST_REPLACE_3';
	echo $RP->replace($RP,$replacer,'en');
	echo "<hr>";
	echo $RP->replace($RP,$replacer,'de');
	echo "<hr>";
	$replacer = 'T091230123650265890176r02365rtouewi5t48723406rzg0fb48fv';
	echo $RP->replace($RP,$replacer,'en');
	echo "<hr>";
	echo $RP->replace($RP,$replacer,'de');
	echo "<hr>";
	//echo "test end";
?>