<?php
// include BPMspaceReplacer

	include_once "./inc/class_replacer.inc.php"; 
  
	$RP = new RePlacer();
	$replacer = 'TEST_REPLACE';
	echo $RP->replace($RP,$replacer,'replacer_language_en');
	echo $RP->replace($RP,$replacer,'replacer_language_de');
	$replacer = 'T091230123650265890176r02365rtouewi5t48723406rzg0fb48fv';
	echo $RP->replace($RP,$replacer,'replacer_language_en');
	echo $RP->replace($RP,$replacer,'replacer_language_de');
	//echo "test end";
?>