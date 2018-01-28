<?php
 
  // include PATH & URL Variables
  include_once "../_path_url.inc.php";
  
  include_once "$filepath_liam/phpSecureLogin/includes/db_connect.inc.php";
  include_once "$filepath_liam/phpSecureLogin/includes/functions.inc.php";
  sec_session_start();
  
  if(login_check($mysqli) != true) {
    header("Location: $url_liam/index.php?error_messages='You are not logged in!'");
    die();
  }
  else {
    $logged = 'in';
  }
  
  // Includes LIAM HEADER
  include_once("$filepath_liam/_header_LIAM.inc.php");  
  // Includes Credentials
  include_once("$filepath_liam/DB_config/login_credentials_DB_bpmspace_replacer.inc.php");
  // Include IPMS generated REPLACER 
  include_once("$filepath_liam/REPLACER/inc/bpmspace_replacer_v1.inc.php");

?>