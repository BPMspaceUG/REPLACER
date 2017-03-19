
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
	  
	  $params["paramJS"]['select']=$language;
	  $params["paramJS"]['where']='replacer_pattern = "' . $replacer .'"';
	  //print_r($params["paramJS"]);
	  $result = $RP->$command($params["paramJS"]);
	  // Output
	  //echo $result;
	  $string =str_replace('"}]','',str_replace('[{"":"','',str_replace($language,'',$result)));
	  $string =str_replace('','',$string);
	  
	  $string = strtr($string, array(
		'u00A0'    => ' ',
		'u0026'    => '&',
		'u003C'    => '<',
		'u003E'    => '>',
		'u00E4'    => 'ä',
		'u00C4'    => 'Ä',
		'u00F6'    => 'ö',
		'u00D6'    => 'Ö',
		'u00FC'    => 'ü',
		'u00DC'    => 'Ü',
		'u00DF'    => 'ß',
		'u20AC'    => '€',
		'u0024'    => '$',
		'u00A3'    => '£',
	 
		'u00a0'    => ' ',
		'u003c'    => '<',
		'u003e'    => '>',
		'u00e4'    => 'ä',
		'u00c4'    => 'Ä',
		'u00f6'    => 'ö',
		'u00d6'    => 'Ö',
		'u00fc'    => 'ü',
		'u00dc'    => 'Ü',
		'u00df'    => 'ß',
		'u20ac'    => '€',
		'u00a3'    => '£',
		));
		if ($string == '[]') {
			$string = '[ERROR REPLACER]<br/>';
		} 
	  return stripcslashes($string);
	}	
  }
  // Class Definition ends here
  
?>