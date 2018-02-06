
<?php
  // Parameter and inputstream
  $params = json_decode(file_get_contents('php://input'), true);
  $command = $params["cmd"];
    
  //RePlacer Class Definition starts here
  class RePlacer {
    // Variables
    private $db;
    private $counter;

    public function __construct() {   

      require_once __DIR__.'/../../DB_config/login_credentials_DB_bpmspace_replacer_RO.inc.php';

	    $this->counter = 0;
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
    public function getDB(){
      return $this->db;
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
    public function replace($RP,$replacer,$language,$isId = null) {
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
    if(!is_null($isId)){
       $params["paramJS"]['where']='replacer_id = "' . $replacer .'"';
    }
      else {
	  $params["paramJS"]['where']='replacer_pattern = "' . $replacer .'"';}
	  //print_r($params["paramJS"]);
	  $result = $RP->$command($params["paramJS"]);
	  // Output
	  $result = json_decode ($result);

	  if (count($result) > 0) {
		$result = get_object_vars($result[0])["$language_col"];
    
	  } else $result = "Nothing in REPLACER DB for pattern: <strong>\"" . $replacer."\"</strong>";

    $parts = explode("#!#", $result);

// Create array for all the patterns to be replaced in in slide.

    $arr = array();

 //   print_r($arr);

// Maximum number of repacements on one slide = 10 for performance reasons

    if (count($parts) > 22){
        $result = "On this slide there are more than (22/2)-1 replacements";
      }
    else if(count($parts)>1){
      if($this->counter > 9){
        $result = "More than 10 recursive Replacements";
      }
    else {
            $this->counter += 1;
            $result = $parts[0];
            foreach ($parts as $key => $value) {
              if (($key % 2) == 1) {
                $rep = $RP->replace($RP,$value,$language);
                if(empty($parts[$key+1])){
                $result .= $rep;
                }
                else  
                $result .= $rep.$parts[$key+1];
              }
            }

//        $rep = $RP->replace($RP,$parts[1],$language); // If you want to replace the replacer with id ,$isId has to be added and some debugging to be done.

 //       $result = $parts[0].$rep.$parts[2];
        }
      }
      
       
    else $result = $parts[0];
    $this->counter = 0;
	

  $result = "<!-- Pattern Replace Start: " . $replacer ." -->". $result . "<!-- Pattern Replace End: " . $replacer ." -->";

    
	  return $result;
	}	
  }
  // Class Definition ends here
  
?>