<?php
  
function ReplaceIT($buffer)
        {
            //$buffer = str_replace("636487950ß78897866re54636747890ß9090897867", "453267890ß0987654367890ß0987654", $buffer);
           /*$buffer = str_replace("        <svg class=\"pull-right\" height=\"100\" width=\"200\">
          <rect fill=\"blue\" x=\"0\" y=\"0\" width=\"200\" height=\"100\" rx=\"15\" ry=\"15\"></rect>
          <text x=\"100\" y=\"55\" fill=\"white\" text-anchor=\"middle\" alignment-baseline=\"central\">sample</text>
        </svg>", "<img  class=\"pull-right\" width=\"50%\" src=\"..\images\bpmspace_icon-REPLACER-right-200px-text.png\" alt=\"\" />", $buffer);
*/
            return ($buffer);
            }

  
  // start buffer
  $buffer = "";
  ob_start("ReplaceIT");

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
  // include_once("$filepath_liam/_header_LIAM.inc.php");  
  // Includes Credentials
  include_once("$filepath_liam/DB_config/login_credentials_DB_bpmspace_replacer.inc.php");
  // Include IPMS generated REPLACER 
  include_once("$filepath_liam/REPLACER/inc/bpmspace_replacer_v2.inc.php");

  ob_end_flush();
?>