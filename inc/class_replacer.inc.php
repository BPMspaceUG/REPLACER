
<?php
  // Parameter and inputstream
  $params = json_decode(file_get_contents('php://input'), true);
  $command = $params["cmd"];
    
  //RePlacer Class Definition starts here
  class RePlacer {
    // Variables
    private $db;

    public function __construct() {      
	require_once './../DB_config/login_credentials_DB_bpmspace_replacer_RO.inc.php';
	
      // create DB connection object
      $db = new mysqli(
        $config['db']['host'],
        $config['db']['user'],
        $config['db']['password'],
        $config['db']['database']
      );
      /* check connection */
      if($db->connect_errno){
        printf("Connect failed: %s", mysqli_connect_error());
        exit();
      }
      $db->query("SET NAMES utf8");
      $this->db = $db;
    }
    // Format data for output
    private function parseToJSON($result) {
      $results_array = array();
      if (!$result) return false;
      while ($row = $result->fetch_assoc()) {
        $results_array[] = $row;
      }
      return json_encode($results_array);
    }
    //================================== READ
    public function read($param) {
      $where = isset($param["where"]) ? $param["where"] : "";
      if (trim($where) <> "") $where = " WHERE ".$param["where"];
      // SQL
      $query = "SELECT ".$param["select"]." FROM ".
        $param["tablename"].$where." LIMIT ".$param["limitStart"].",".$param["limitSize"].";"; 
      //var_dump($query);
      $res = $this->db->query($query);
      return $this->parseToJSON($res);
    }
    //================================== REPLACER
    public function replace($RP,$replacer,$language) {
	  $command = 'read';
	  $paramJS = array("tablename,limitStart,limitSize,select");
	  $params = array("paramJS");
	  $params["paramJS"]['tablename']="replacer";
	  $params["paramJS"]['limitStart']='0';
	  $params["paramJS"]['limitSize']='10';
	  
	switch ($language)
	{
		case 'de':
        {
			$language_col = "replacer_language_de";
			break;
        }
		case 'en':
        {
			$language_col = "replacer_language_en"; 
			break;
        }
		
		default:
        {
			$language_col = "replacer_language_de";
        }
	}
		
	  $params["paramJS"]['select']=$language_col;
	  $params["paramJS"]['where']='replacer_pattern = "' . $replacer .'"';
	  //print_r($params["paramJS"]);
	  $result = $RP->$command($params["paramJS"]);
	  // Output
	  $result = json_decode ($result);
	  if (count($result) > 0) {
		$result = get_object_vars($result[0])["$language_col"];
	  } else $result = "REPLACER nothing in REPLACER DB for pattern <strong>\"" . $replacer."\"";
	  return $result;
	}	
  }
  // Class Definition ends here
  
?>