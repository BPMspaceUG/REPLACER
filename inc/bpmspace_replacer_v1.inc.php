<?php
  //Header
  //header('Access-Control-Allow-Origin: *');
  //header('Access-Control-Allow-Methods: POST');
  //Includes
  //include_once("bpmspace_replacer_v1-config.php");
  //Parameter and inputstream
  $params = json_decode(file_get_contents('php://input'), true);
  $command = $params["cmd"];
  

  
  class StateMachine {
    // Variables
    private $db;
    private $ID = -1;
    private $db_name = "";
    private $table = "";

    public function __construct($db, $db_name, $tablename = "") {
      $this->db = $db;
      $this->db_name = $db_name;
      $this->table = $tablename;
      if ($this->table != "")
      	$this->ID = $this->getSMIDByTablename($tablename);
    }

    private function getResultArray($rowObj) {
      $res = array();
      if (!$rowObj) return $res; // exit if query failed
      while ($row = $rowObj->fetch_assoc())
        $res[] = $row;
      return $res;
    }
    private function getSMIDByTablename($tablename) {
    	// Return newest statemachine (MAX)
      $query = "SELECT MAX(id) AS 'id' FROM `$this->db_name`.state_machines WHERE tablename = '$tablename';";
      $res = $this->db->query($query);
     	$r = $this->getResultArray($res);
      if (empty($r)) return -1; // statemachine does not exist
      return (int)$r[0]['id'];
    }
    private function getStateAsObject($stateid) {
      settype($id, 'integer');
      $query = "SELECT state_id AS 'id', name AS 'name' FROM $this->db_name.state WHERE state_id = $stateid;";
      $res = $this->db->query($query);
      return $this->getResultArray($res);
    }

    public function createDatabaseStructure() {
    	$db_name = $this->db_name;
    	// ------------------------------- T A B L E S
    	// Create Table 'state_machines'
		  $query = "CREATE TABLE IF NOT EXISTS `$db_name`.`state_machines` (
			  `id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `tablename` varchar(45) DEFAULT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
		  $this->db->query($query);
      // Add Form_data column if not exists
      $query = "SHOW COLUMNS FROM  `$db_name`.`state_machines`;";
      $res = $this->db->query($query);
      $rows = $this->getResultArray($res);
      // Build one string with all columnnames
      $columnstr = "";
      foreach ($rows as $row) $columnstr .= $row["Field"];
      // Column [form_data] does not yet exist
      if (strpos($columnstr, "form_data") === FALSE) {
        $query = "ALTER TABLE `$db_name`.`state_machines` ADD COLUMN `form_data` LONGTEXT NULL AFTER `tablename`;";
        $res = $this->db->query($query);
      }
      // Column [form_data] does not yet exist
      if (strpos($columnstr, "transition_script") === FALSE) {
        $query = "ALTER TABLE `$db_name`.`state_machines` ADD COLUMN `transition_script` LONGTEXT NULL AFTER `tablename`;";
        $res = $this->db->query($query);
      }
		  // Create Table 'state'
		  $query = "CREATE TABLE IF NOT EXISTS `$db_name`.`state` (
			  `state_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `name` varchar(45) DEFAULT NULL,
			  `form_data` longtext,
			  `entrypoint` tinyint(1) NOT NULL DEFAULT '0',
			  `statemachine_id` bigint(20) NOT NULL DEFAULT '1',
			  PRIMARY KEY (`state_id`)
			) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
		  $this->db->query($query);
		  // Create Table 'state_rules'
		  $query = "CREATE TABLE IF NOT EXISTS `$db_name`.`state_rules` (
			  `state_rules_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `state_id_FROM` bigint(20) NOT NULL,
			  `state_id_TO` bigint(20) NOT NULL,
			  `transition_script` longtext,
			  PRIMARY KEY (`state_rules_id`)
			) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
		  $this->db->query($query);
			// ------------------------------- F O R E I G N - K E Y S
		  // 'state_rules'
		  $query = "ALTER TABLE `$db_name`.`state_rules` ".
		    "ADD INDEX `state_id_fk1_idx` (`state_id_FROM` ASC), ".
		    "ADD INDEX `state_id_fk_to_idx` (`state_id_TO` ASC);";
		  $this->db->query($query);
		  $query = "ALTER TABLE `$db_name`.`state_rules` ".
		  	"ADD CONSTRAINT `state_id_fk_from` FOREIGN KEY (`state_id_FROM`) ".
		  	"REFERENCES `$db_name`.`state` (`state_id`) ON DELETE NO ACTION ON UPDATE NO ACTION, ".
		  	"ADD CONSTRAINT `state_id_fk_to` FOREIGN KEY (`state_id_TO`) ".
		  	"REFERENCES `$db_name`.`state` (`state_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;";
		  $this->db->query($query);
		  // 'state'
		  $query = "ALTER TABLE `$db_name`.`state` ".
		  	"ADD INDEX `state_machine_id_fk` (`statemachine_id` ASC);";
		  $this->db->query($query);
		  $query = "ALTER TABLE `$db_name`.`state` ".
		  	"ADD CONSTRAINT `state_machine_id_fk` FOREIGN KEY (`statemachine_id`) ".
		  	"REFERENCES `$db_name`.`state_machines` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;";
		  $this->db->query($query);
		  // TODO: Foreign Key for [state <-> state_machines]
    }    
    private function createNewState($statename, $isEP) {
    	$db_name = $this->db_name;
    	$SMID = $this->ID;
    	// build query
    	$query = "INSERT INTO `$db_name`.`state` (`name`, `form_data`, `statemachine_id`, `entrypoint`) ".
      	"VALUES ('$statename', '', $SMID, $isEP);";
      $this->db->query($query);
      return $this->db->insert_id;
    }
    public function createBasicStateMachine($tablename) {
    	$db_name = $this->db_name;
      // check if a statemachine already exists for this table
      $ID = $this->getSMIDByTablename($tablename);
      if ($ID > 0) return $ID; // SM already exists
      // Insert new statemachine for a table
      $query = "INSERT INTO `$db_name`.`state_machines` (`tablename`) VALUES ('$tablename');";
      $this->db->query($query);
      $ID = $this->db->insert_id; // returns the ID for the created SM
      $this->ID = $ID;
      // Insert states (new, active, inactive)
      $ID_new = $this->createNewState('new', 1);
      $ID_active = $this->createNewState('active', 0);
      $ID_inactive = $this->createNewState('inactive', 0);
      // Insert rules (new -> active, active -> inactive)
      $query = "INSERT INTO `$db_name`.`state_rules` ".
        "(`state_id_FROM`, `state_id_TO`, `transition_script`) VALUES ".
        "($ID_new, $ID_new, ''), ".
        "($ID_active, $ID_active, ''), ".
        "($ID_inactive, $ID_inactive, ''), ".
        "($ID_new, $ID_active, ''), ".
        "($ID_active, $ID_new, ''), ".
        "($ID_active, $ID_inactive, ''), ".
        "($ID_inactive, $ID_active, '')";
      $this->db->query($query);
      return $ID;
    }
    // TODO:
    public function getBasicFormDataByColumns($columns) {
      // possibilities = [RO, RW, HD]
      $res = array();
      // Loop each column
      for ($i=0;$i<count($columns);$i++) {
        $res[$columns[$i]] = "RO";
      }
      return $res;
    }
    public function getFormDataByStateID($StateID) {
      if (!($this->ID > 0)) return "";
      settype($StateID, 'integer');
      $query = "SELECT form_data AS 'fd' FROM $this->db_name.state ".
        "WHERE statemachine_id = $this->ID AND state_id = $StateID;";
      $res = $this->db->query($query);
      $r = $this->getResultArray($res);
      return $r[0]['fd'];
    }
    public function getCreateFormByTablename() {
      if (!($this->ID > 0)) return "";
      settype($StateID, 'integer');
      $query = "SELECT form_data AS 'fd' FROM $this->db_name.`state_machines` ".
        "WHERE id = $this->ID;";
      $res = $this->db->query($query);
      $r = $this->getResultArray($res);
      return $r[0]['fd'];
    }
    public function getID() {
    	return $this->ID;
    }
    public function getStates() {
      $query = "SELECT state_id AS 'id', name, entrypoint FROM $this->db_name.state WHERE statemachine_id = $this->ID;";
      $res = $this->db->query($query);
      return $this->getResultArray($res);
    }
    public function getLinks() {
      $query = "SELECT state_id_FROM AS 'from', state_id_TO AS 'to' FROM $this->db_name.state_rules ".
               "WHERE state_id_FROM AND state_id_TO IN (SELECT state_id FROM $this->db_name.state WHERE statemachine_id = $this->ID);";
      $res = $this->db->query($query);
      return $this->getResultArray($res);
    }
    public function getEntryPoint() {
    	if (!($this->ID > 0)) return -1;
      $query = "SELECT state_id AS 'id' FROM $this->db_name.state ".
      	"WHERE entrypoint = 1 AND statemachine_id = $this->ID;";
      $res = $this->db->query($query);
      $r = $this->getResultArray($res);
      return (int)$r[0]['id'];
    }
    public function getNextStates($actStateID) {
      settype($actStateID, 'integer');
      $query = "SELECT a.state_id_TO AS 'id', b.name AS 'name' FROM $this->db_name.state_rules AS a ".
        "JOIN state AS b ON a.state_id_TO = b.state_id WHERE state_id_FROM = $actStateID;";
      $res = $this->db->query($query);
      return $this->getResultArray($res);
    }
    public function getActState($id, $primaryIDColName) {
      settype($id, 'integer');
      $query = "SELECT a.state_id AS 'id', b.name AS 'name' FROM $this->db_name.".$this->table.
        " AS a INNER JOIN state AS b ON a.state_id = b.state_id WHERE $primaryIDColName = $id;";
      $res = $this->db->query($query);
      return $this->getResultArray($res);
    }
    public function setState($ElementID, $stateID, $primaryIDColName, &$param = null) {
      // get actual state from element
      $actstateObj = $this->getActState($ElementID, $primaryIDColName);
      if (count($actstateObj) == 0) return false;
      $actstateID = $actstateObj[0]["id"];
      // check transition, if allowed
      $trans = $this->checkTransition($actstateID, $stateID);
      // check if transition is possible
      if ($trans) {
        $newstateObj = $this->getStateAsObject($stateID);
        $scripts = $this->getTransitionScripts($actstateID, $stateID);        
        // Execute all scripts from database at transistion
        foreach ($scripts as $script) {
          // --- ! Execute Script (eval = evil) ! ---
          eval($script["transition_script"]);
          // -----------> Standard Result
          if (empty($script_result)) {
            $script_result = array(
              "allow_transition" => true,
              "show_message" => false,
              "message" => ""
            );
          }
          // update state in DB, when plugin says yes
          if (@$script_result["allow_transition"] == true) {
            $query = "UPDATE $this->db_name.".$this->table." SET state_id = $stateID WHERE $primaryIDColName = $ElementID;";
            $res = $this->db->query($query);
          }
          // Return
          return json_encode($script_result);
        }        
      }
      return false;
    }
    public function checkTransition($fromID, $toID) {
      settype($fromID, 'integer');
      settype($toID, 'integer');
      $query = "SELECT * FROM $this->db_name.state_rules WHERE state_id_FROM=$fromID AND state_id_TO=$toID;";
      $res = $this->db->query($query);
      $cnt = $res->num_rows;
      return ($cnt > 0);
    }
    public function getTransitionScripts($fromID, $toID) {
      settype($fromID, 'integer');
      settype($toID, 'integer');
      $query = "SELECT transition_script FROM $this->db_name.state_rules WHERE ".
      "state_id_FROM = $fromID AND state_id_TO = $toID;";
      $return = array();
      $res = $this->db->query($query);
      $return = $this->getResultArray($res);
      return $return;
    }
  }



  //RequestHandler Class Definition starts here
  class RequestHandler {
    private $db;

    public function __construct() {
      // create DB connection object - Data comes from config file
      $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
      // check connection
      if($db->connect_errno){
        printf("Connect failed: %s", mysqli_connect_error());
        exit();
      }
      $db->query("SET NAMES utf8");
      $this->db = $db;
    }
    private function getPrimaryColByTablename($tablename) {
      $config = json_decode($this->init(), true);
      $res = array();
      // Loop table configuration
      for ($i=0; $i<count($config); $i++) {
        if ($config[$i]["table_name"] == $tablename) {
          $cols = $config[$i]["columns"];
          break;
        }
      }
      // Find primary columns
      foreach ($cols as $col) {
        if ($col["COLUMN_KEY"] == "PRI")
          $res[] = $col["COLUMN_NAME"];
      }
      return $res;
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
    private function buildSQLWherePart($primarycols, $rowcols) {
      $where = "";
      foreach ($primarycols as $col) {
        $where = $where . $col . "='" . $rowcols[$col] . "'";
        $where = $where . " AND ";
      }
      $where = substr($where, 0, -5); // remove last ' AND ' (5 chars)
      return $where;
    }
    private function buildSQLUpdatePart($cols, $primarycols, $rows) {
      $update = "";
      // Convert everything to lowercase      
      $primarycols = array_map('strtolower', $primarycols);
      //$cols = array_map('strtolower', $cols);
      // Loop every element
      foreach ($cols as $col) {
        // update only when no primary column
        if (!in_array($col, $primarycols)) {
          $update = $update . $col."='".$this->db->real_escape_string($rows[$col])."'";
          $update = $update . ", ";
        }
      }
      $update = substr($update, 0, -2); // remove last ' ,' (2 chars)
      return $update;
    }
    // TODO: Rename to loadConfig
    public function init() {
      global $config_tables_json;
      return $config_tables_json;
    }
    //================================== CREATE
    public function create($param) {
      // Inputs
      $tablename = $param["table"];
      $rowdata = $param["row"];
      // Split array
      foreach ($rowdata as $key => $value) {        
        // Check if has StateMachine // TODO: Optimize
        if ($value == '%!%PLACE_EP_HERE%!%') {
          $SE = new StateMachine($this->db, DB_NAME, $tablename);
          $value = $SE->getEntryPoint();
        }
        // Append and escape to prevent sqli
        $keys[] = $this->db->real_escape_string($key);
        $vals[] = $this->db->real_escape_string($value);
      }
      // Checking
      if (count($keys) != count($vals)) {
        echo "ERORR while buiding Query! (k=".count($keys).", v=".count($vals).")";
        exit;
      }
      // Operation
      $query = "INSERT INTO ".$tablename." (".implode(",", $keys).") VALUES ('".implode("','", $vals)."');";
      $res = $this->db->query($query);
      $lastID = $this->db->insert_id;
      // Output (return last id instead of 1)
      return $res ? $lastID : "0";
    }
    //================================== READ
    public function read($param) {
      // Parameters
      $where = isset($param["where"]) ? $param["where"] : "";
      $orderby = isset($param["orderby"]) ? $param["orderby"] : "";
      $ascdesc = isset($param["ascdesc"]) ? $param["ascdesc"] : "";
      $joins = isset($param["join"]) ? $param["join"] : "";

      // ORDER BY
      $ascdesc = strtolower(trim($ascdesc));
      if ($ascdesc == "asc" || $ascdesc == "") $ascdesc == "ASC";
      if ($ascdesc == "desc") $ascdesc == "DESC";
      if (trim($orderby) <> "")
        $orderby = " ORDER BY ".$param["orderby"]." ".$ascdesc;
      else
        $orderby = " "; // ORDER BY replacer_id DESC";

      // LIMIT
      // TODO: maybe if limit Start = -1 then no limit is used
      $limit = " LIMIT ".$param["limitStart"].",".$param["limitSize"];

      // JOIN
      $join_from = $param["tablename"]." AS a"; // if there is no join
      $sel = array();
      $sel_raw = array();
      $sel_str = "";
      if (count($joins) > 0) {
        // Multi-join
        for ($i=0;$i<count($joins);$i++) {
          $join_from .= " JOIN ".$joins[$i]["table"]." AS t$i ON ".
                        "t$i.".$joins[$i]["col_id"]."= a.".$joins[$i]["replace"];
          $sel[] = "t$i.".$joins[$i]["col_subst"]." AS '".$joins[$i]["replace"]."'";
          $sel_raw[] = "t$i.".$joins[$i]["col_subst"];
        }
        $sel_str = ",".implode(",", $sel);
      }

      // SEARCH
      if (trim($where) <> "") {
        // Get columns from the table
        $res = $this->db->query("SHOW COLUMNS FROM ".$param["tablename"].";");
        $k = [];
        while ($row = $res->fetch_array()) { $k[] = $row[0]; } 
        $k = array_merge($k, $sel_raw); // Additional JOIN-columns     
        // xxx LIKE = '%".$param["where"]."%' OR yyy LIKE '%'
        $q_str = "";
        foreach ($k as $key) {
          $prefix = "";
          // if no "." in string then refer to first table
          if (strpos($key, ".") === FALSE) $prefix = "a.";
          $q_str .= " ".$prefix.$key." LIKE '%".$where."%' OR ";
        }
        // Remove last 'OR '
        $q_str = substr($q_str, 0, -3);

        $where = " WHERE ".$q_str;
      }
      // Concat final query
      $query = "SELECT ".$param["select"].$sel_str." FROM ".$join_from.$where.$orderby.$limit.";";
      $query = str_replace("  ", " ", $query);
      $res = $this->db->query($query);
      // Return result as JSON
      return $this->parseToJSON($res);
    }
    //================================== UPDATE
    public function update($param) {
      // Primary Columns
      $tablename = $param["table"];
      $pCols = $this->getPrimaryColByTablename($tablename);
      // SQL
      $update = $this->buildSQLUpdatePart(array_keys($param["row"]), $pCols, $param["row"]);
      $where = $this->buildSQLWherePart($pCols, $param["row"]);
      $query = "UPDATE ".$param["table"]." SET ".$update." WHERE ".$where.";";
      //var_dump($query);
      $res = $this->db->query($query);
      // TODO: Check if rows where REALLY updated!
      // Output
      return $res ? "1" : "0";
    }
    //================================== DELETE
    public function delete($param) {
      // Primary Columns
      $tablename = $param["table"];
      $pCols = $this->getPrimaryColByTablename($tablename);
      /* DELETE FROM table_name WHERE some_column=some_value AND x=1; */
      $where = $this->buildSQLWherePart($pCols, $param["row"]);
      // Build query
      $query = "DELETE FROM ".$param["table"]." WHERE ".$where.";";
      $res = $this->db->query($query);
      // Output
      return $res ? "1" : "0";
    }
    public function getFormData($param) {
      $tablename = $param["table"];
      $SM = new StateMachine($this->db, DB_NAME, $tablename);
      // Check if has state machine ?
      if ($SM->getID() > 0) {
        $stateID = $param["row"]["state_id"];
        $r = $SM->getFormDataByStateID($stateID);
        if (empty($r)) $r = "1"; // default: allow editing (if there are no rules set)
        return $r;
      } else {
        // respond true if no statemachine (means: allow editing)
        return "1"; 
      }
    }
    public function getFormCreate($param) {
      $tablename = $param["table"];
      $SM = new StateMachine($this->db, DB_NAME, $tablename);
      // Check if has state machine ?
      if ($SM->getID() > 0) {
        $r = $SM->getCreateFormByTablename();
        if (empty($r)) $r = "1"; // default: allow editing (if there are no rules set)
        return $r;
      } else {
        // allow editing if no statemachine
        return "1"; 
      }
    }
    //==== Statemachine -> substitue StateID of a Table with Statemachine
    public function getNextStates($param) {
      // Find right column (Maybe optimize with GUID)
      $row = $param["row"];

      // TODO: Get StateID not from client -> find itself by using [table, ElementID]
      // {
      $stateID = false;
      foreach ($row as $key => $value) {
        // if column contains *state_id*
        if (strpos($key, 'state') !== false) {
          $stateID = $value;
          break;
        }
      }
      // Return invalid
      if ($stateID === false) return json_encode(array());
      // }
      
      // execute query
      $tablename = $param["table"];
      $SE = new StateMachine($this->db, DB_NAME, $tablename);
      $res = $SE->getNextStates($stateID);
      return json_encode($res);
    }
    public function makeTransition($param) {
      // Get the next ID for the next State
      $nextStateID = $param["row"]["state_id"];
      $tablename = $param["table"];
      $pricols = $this->getPrimaryColByTablename($tablename);
      $pricol = $pricols[0]; // Count should always be 1
      $ElementID = $param["row"][$pricol];
      // Statemachine
      $SE = new StateMachine($this->db, DB_NAME, $tablename);
      // get ActStateID
      $actstateObj = $SE->getActState($ElementID, $pricol);
      if (count($actstateObj) == 0) {
        echo "Element not found";
        return false;
      }
      $actstateID = $actstateObj[0]["id"];
      // Try to set State
      $result = $SE->setState($ElementID, $nextStateID, $pricol, $param);
      // Check if was a recursive state
      $r = json_decode($result, true);
      // Special case [Save] transition
      if ($nextStateID == $actstateID) {
        if ($r["allow_transition"]) {
          $this->update($param); // Update all other rows
        }
      }
      // Return to client
      echo $result;
    }
    public function getStates($param) {
      $tablename = $param["table"];
      $SE = new StateMachine($this->db, DB_NAME, $tablename);
      $res = $SE->getStates();
      return json_encode($res);
    }
    public function smGetLinks($param) {
      $tablename = $param["table"];
      $SE = new StateMachine($this->db, DB_NAME, $tablename);
      $res = $SE->getLinks();
      return json_encode($res);
    }
  }
  // Class Definition ends here
  // Request Handler ends here
  //----------------------------------------------------------

  $RH = new RequestHandler();  
  if ($command != "") { // check if at least a command is set    
    if ($params != "") // are there parameters?      
      $result = $RH->$command($params["paramJS"]); // execute with params
    else
      $result = $RH->$command(); // only execute
    // Output
    echo $result;
    exit(); // Terminate further execution
  }
?><!DOCTYPE html>
<html lang="en-US">
<head>
  <title>bpmspace_replacer_v1</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- CSS -->
  <link rel="stylesheet" href="../css/bootstrap.min.css">
  <link rel="stylesheet" href="../css/bootstrap-theme.min.css">
  <link rel="stylesheet" href="../css/font-awesome.min.css">
  <!-- Custom CSS -->
  <style>
thead tr {background-color: #eee;}
.controllcoulm {min-width: 100px; width: 100px; background-color: #eee;}
/**********************************************************/

.panel-table .panel-body { padding:0; margin:0;}
.panel-table .panel-body .table-bordered { border-style: none; margin:0;}
.panel-table .panel-body .table-bordered > thead > tr > th:last-of-type,
.panel-table .panel-body .table-bordered > tbody > tr > td:last-of-type { border-right: 0px;}
.panel-table .panel-body .table-bordered > thead > tr > th:first-of-type,
.panel-table .panel-body .table-bordered > tbody > tr > td:first-of-type { border-left: 0px; text-align: center; width: 100px; background-color: #eee; }
.panel-table .panel-body .table-bordered > tbody > tr:first-of-type > td { border-bottom: 0px; }
.panel-table .panel-body .table-bordered > thead > tr:first-of-type > th { border-top: 0px; }

.panel-table .panel-footer .pagination{ margin:0; }
.panel-table .panel-footer .col{ line-height: 34px; height: 34px; }
.panel-table .panel-heading .col h3{ line-height: 30px; height: 30px; }
.panel-table .panel-body .table-bordered > tbody > tr > td{ line-height: 34px; }



.panel.with-nav-tabs .panel-heading{
    padding: 5px 5px 0 5px;
}
.panel.with-nav-tabs .nav-tabs{
	border-bottom: none;
}
.panel.with-nav-tabs .nav-justified{
	margin-bottom: -1px;
}
/********************************************************************/
/*** PANEL DEFAULT ***/
.with-nav-tabs.panel-default .nav-tabs > li > a,
.with-nav-tabs.panel-default .nav-tabs > li > a:hover,
.with-nav-tabs.panel-default .nav-tabs > li > a:focus {
    color: #777;
}
.with-nav-tabs.panel-default .nav-tabs > .open > a,
.with-nav-tabs.panel-default .nav-tabs > .open > a:hover,
.with-nav-tabs.panel-default .nav-tabs > .open > a:focus,
.with-nav-tabs.panel-default .nav-tabs > li > a:hover,
.with-nav-tabs.panel-default .nav-tabs > li > a:focus {
    color: #777;
	background-color: #ddd;
	border-color: transparent;
}
.with-nav-tabs.panel-default .nav-tabs > li.active > a,
.with-nav-tabs.panel-default .nav-tabs > li.active > a:hover,
.with-nav-tabs.panel-default .nav-tabs > li.active > a:focus {
	color: #555;
	background-color: #fff;
	border-color: #ddd;
	border-bottom-color: transparent;
}
.with-nav-tabs.panel-default .nav-tabs > li.dropdown .dropdown-menu {
    background-color: #f5f5f5;
    border-color: #ddd;
}
.with-nav-tabs.panel-default .nav-tabs > li.dropdown .dropdown-menu > li > a {
    color: #777;   
}
.with-nav-tabs.panel-default .nav-tabs > li.dropdown .dropdown-menu > li > a:hover,
.with-nav-tabs.panel-default .nav-tabs > li.dropdown .dropdown-menu > li > a:focus {
    background-color: #ddd;
}
.with-nav-tabs.panel-default .nav-tabs > li.dropdown .dropdown-menu > .active > a,
.with-nav-tabs.panel-default .nav-tabs > li.dropdown .dropdown-menu > .active > a:hover,
.with-nav-tabs.panel-default .nav-tabs > li.dropdown .dropdown-menu > .active > a:focus {
    color: #fff;
    background-color: #555;
}







/* Table */
.tableCont th {cursor: pointer;}
.tableCont th:hover {background-color: #ddd; color: steelblue;}
.tableCont th, tr td {white-space: nowrap;}

/*********************************************/

.fKeyLink {}
.fKeyLink:hover {text-decoration: none;}

/* State Engine */
.stateBtn {cursor: pointer; font-weight: bold;}
.state1 {color: darkgreen;}
.state2 {color: steelblue;}
.state3 {color: red;}
.state4 {color: orange;}
.state5 {color: lightblue;}
.state6 {color: darkmagenta;}
.state7 {color: steelblue;}
.state8 {color: brown;}
.state9 {color: mediumorchid;}
.state10 {color: tan;}
.state11 {color: wheat;}
.state12 {color: darksalmon;}
  </style>
  <!-- JS -->
  <script type="text/javascript" src="../js/viz-lite.js"></script>
  <script type="text/javascript" src="../js/angular.min.js"></script>
  <script type="text/javascript" src="../js/angular-sanitize.min.js"></script>
  <script type="text/javascript" src="../js/jquery-2.1.4.min.js"></script>
  <script type="text/javascript" src="../js/bootstrap.min.js"></script>
</head>
<body ng-app="genApp" ng-controller="genCtrl">
<!--  body menu starts here -->
<div class="container">  
  <!-- Company Header -->
  <div class="row">
    <div  class="row text-right">
      <div class="col-md-12">
        <a href='#' id="bpm-logo-care" class="btn collapsed" data-toggle="collapse" data-target="#bpm-logo, #bpm-liam-header">
          <i class="fa fa-caret-square-o-down"></i>
        </a>
      </div>
      <div class="col-md-12 collapse" id="bpm-liam-header">
        <?php include_once('../_header_LIAM.inc.php'); ?>          
      </div>
    </div>
  </div>
  <div class="row collapse">
    <div class="col-md-12" id="bpm-logo">
      <div class="col-md-6 ">
        <svg height="100" width="100">
          <rect fill="red" x="0" y="0" width="100" height="100" rx="15" ry="15"></rect>
          <text x="50" y="55" fill="white" text-anchor="middle" alignment-baseline="central">your logo</text>
        </svg>
      </div>
      <div class="col-md-6 ">
        <svg class="pull-right" height="100" width="200">
          <rect fill="blue" x="0" y="0" width="200" height="100" rx="15" ry="15"></rect>
          <text x="100" y="55" fill="white" text-anchor="middle" alignment-baseline="central">sample</text>
        </svg>
      </div>
    </div>
  </div>
  <!-- NAVIGATION -->
  <!--
  <ul class="nav nav-tabs" role="tablist" id="myTabs">
    <li ng-repeat="table in tables" role="presentation" ng-class="{active: (selectedTable.table_name == table.table_name)}">
      <a href="#{{table.table_name}}" aria-controls="{{table.table_name}}" data-toggle="tab" role="tab">
        <i class="{{table.table_icon}}"></i>&nbsp;<span ng-bind="table.table_alias"></span>
      </a>
    </li>
  </ul> 
-->
</div><!-- Loading Screen or Errors -->
<div class="container">
  <div class="alert alert-info" ng-show="isLoading">
    <p><i class="fa fa-cog fa-spin"></i> Loading ...</p>
  </div>
</div>

<!-- body content starts here  -->
<div class="container" id="content" ng-hide="isLoading">
  <div class="row">
    <div class="col-xs-12">

      <div class="panel panel-default panel-table">
        <!-- Panel Header -->
        <div class="panel-heading">
          <!-- Tabs-->
          <div class="pull-left">
            <ul class="nav nav-tabs">
              <li ng-repeat="t in tables" ng-class="{active: (selectedTable.table_name == t.table_name)}">
                <a href="#{{t.table_name}}" data-toggle="tab" ng-click="changeTab(t.table_name)">
                  <i class="{{t.table_icon}}"></i>&nbsp;<span ng-bind="t.table_alias"></span>
                </a>
              </li>
            </ul>
          </div>
          <!-- Where filter -->
          <form class="form-inline pull-right">
            <div class="form-group">
              <!-- PROCESS -->
              <button class="btn btn-default" title="Show Process" ng-hide="!selectedTable.se_active" type="button"
                ng-click="openSEPopup(selectedTable.table_name)"><i class="fa fa-random"></i></button>
              <!-- ADD -->
              <button class="btn btn-success" title="Create Entry" ng-hide="selectedTable.is_read_only" type="button"
              	ng-click="addEntry(selectedTable.table_name)"><i class="fa fa-plus"></i></button>
              <!-- SEARCH -->
              <input type="text" class="form-control" style="width:150px;" placeholder="Search..."
                ng-model="selectedTable.sqlwhere"/>
              <!-- REFRESH -->
              <button class="btn btn-default" title="Refresh" 
              	ng-click="refresh(selectedTable.table_name);"><i class="fa fa-refresh"></i></button>                  
            </div>
          </form>
          <!--Clear -->
          <div class="clearfix"></div>
        </div>
        <!-- Panel Body -->
        <div class="panel-body">
          <div class="tab-content" style="overflow: auto;">
            <div ng-repeat="table in tables" class="tab-pane" ng-class="{active: (selectedTable.table_name == table.table_name)}" id="{{table.table_name}}">
            	<!-- No Entries -->
            	<table class="table table-bordered table-condensed" ng-if="table.count <= 0">
            		<thead>
            			<tr><th style="padding: 3em 0; font-weight: normal;">No entries found</th></tr>
            		</thead>          		
            	</table>
              <!-- Data content -->
              <table class="table table-bordered table-striped table-hover table-condensed tableCont" ng-if="table.count > 0">
                <!-- ============= COLUMN HEADERS ============= -->
                <thead>
                  <tr>
                    <!-- Control-Column -->
                    <th ng-hide="table.is_read_only">
                      <em class="fa fa-cog"></em>
                    </th>
                    <!-- Data-Columns -->
                    <th ng-repeat="col in table.columns | orderBy: 'col_order'"
                    		ng-click="sortCol(table, col.COLUMN_NAME)"
                    		ng-if="col.is_in_menu">
                      <span>{{col.column_alias}}
                        <i class="fa fa-caret-down" ng-show="table.sqlorderby == col.COLUMN_NAME && table.sqlascdesc == 'desc'"></i>
                        <i class="fa fa-caret-up" ng-show="table.sqlorderby == col.COLUMN_NAME && table.sqlascdesc == 'asc'"></i>
                      </span>
                    </th>
                  </tr>
                </thead>
                <!-- ============= CONTENT ============= -->
                <tbody>
                  <tr ng-repeat="row in table.rows" ng-class="getRowCSS(row)">
                    <!-- Control-Column -->
                    <td class="controllcoulm" ng-hide="table.is_read_only">
                      <!-- Edit Button -->
                      <button class="btn btn-default" ng-click="editEntry(table, row)" title="Edit Entry">
                        <i class="fa fa-pencil"></i>
                      </button>
                      <!-- Delete Button -->
                      <button class="btn btn-danger" ng-click="deleteEntry(table, row)" title="Delete Entry">
                        <i class="fa fa-times"></i>
                      </button>
                    </td>
                    <!-- DATA ROWS -->
                    <td ng-repeat="cell in row track by $index" 
                    		ng-if="getColByName(table, table.row_order[$index]).is_in_menu">
                      <!-- Substitue State Machine -->
                      <!-- TODO: Use ForeignKeys for this function -->
                      <div ng-if="(table.row_order[$index] == 'state_id' && table.se_active)">
                        <b ng-class="'state'+ row[table.row_order[$index]]">{{substituteSE(table.table_name, row[table.row_order[$index]])}}</b>
                      </div>
                      <!-- Cell -->
                      <span ng-if="!(table.row_order[$index] == 'state_id' && table.se_active)">
                        {{ row[table.row_order[$index]] | limitTo: 40 }}{{ row[table.row_order[$index]].length > 40 ? '...' : ''}}
                     	</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <!-- Panel Footer -->
        <div class="panel-footer">
          <div class="row">
            <div class="col col-xs-6">
              <span class="text-primary">
                <span>{{selectedTable.count}} Entries total</span>
                <span ng-if="getNrOfPages(selectedTable) > 0"> - Page {{selectedTable.PageIndex + 1}} of {{ getNrOfPages(selectedTable) }}</span>
              </span>
            </div>
            <div class="col col-xs-6">
              <ul class="pagination pull-right"><!-- visible-xs -->
                <!-- JUMP to first page -->
                <li ng-class="{disabled: selectedTable.PageIndex <= 0}">
                  <a href="" ng-click="gotoPage(0, selectedTable)">«</a>
                </li>          
                <!-- Page Buttons -->
                <li ng-repeat="btn in getPageination(selectedTable.table_name)"
                  ng-class="{disabled: btn + selectedTable.PageIndex == selectedTable.PageIndex}">
                  <a href="" ng-click="gotoPage(btn + selectedTable.PageIndex, selectedTable)">{{btn + selectedTable.PageIndex + 1}}</a>
                </li>
                <!-- JUMP to last page -->
                 <li ng-class="{disabled: (selectedTable.PageIndex + 1) >= (selectedTable.count / PageLimit)}">
                  <!-- TODO: fix 9999 number, maybe to (-1) -->
                  <a href="" ng-click="gotoPage(999999, selectedTable)">»</a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
</div>

<!-- Modal for Create -->
<div class="modal fade" id="modalCreate" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title">
          <i class="fa fa-plus"></i> Create Entry <small>in <b>{{selectedTable.table_alias}}</b></small>
        </h4>
      </div>
      <div class="modal-body">
        <!-- Content -->
        <form class="form-horizontal">
          <!-- Add if is in menu -->
          <div class="form-group"
            ng-repeat="(key, value) in selectedRow"
            ng-if="getColByName(selectedTable, key).is_in_menu
              && !(selectedTable.se_active && (key.indexOf('state_id') >= 0))
              && (selectedTable.form_data[key] != 'HI')">
            <!-- [LABEL] -->
            <label class="col-sm-3 control-label">{{getColAlias(selectedTable, key)}}</label>
            <!-- [VALUE] -->
            <div class="col-sm-9">
              <!-- Foreign Key (FK) -->
              <span ng-if="getColByName(selectedTable, key).foreignKey.table != ''">
                <a class="btn btn-default"
                  ng-click="(selectedTable.form_data[key] == 'RO') || openFK(key)"
                  ng-readonly="selectedTable.form_data[key] == 'RO'">
                  <i class="fa fa-key"></i> {{value}}
                </a>
              </span>
              <!-- NO FK -->
              <span ng-if="getColByName(selectedTable, key).foreignKey.table == ''">
                <!-- Number  -->
                <input class="form-control" type="number" string-to-number 
                  ng-if="getColByName(selectedTable, key).COLUMN_TYPE.indexOf('int') >= 0
                  && getColByName(selectedTable, key).COLUMN_TYPE.indexOf('tiny') < 0"
                  ng-model="selectedRow[key]"
                  ng-readonly="selectedTable.form_data[key] == 'RO'" autofocus>
                <!-- Text -->
                <input class="form-control" type="text"
                  ng-if="getColByName(selectedTable, key).COLUMN_TYPE.indexOf('int') < 0
                  && getColByName(selectedTable, key).COLUMN_TYPE.indexOf('long') < 0
                  && !getColByName(selectedTable, key).is_ckeditor"
                  ng-model="selectedRow[key]"
                  ng-readonly="selectedTable.form_data[key] == 'RO'" autofocus>
                <!-- LongText (probably HTML) -->
                <textarea class="form-control" rows="3"
                  ng-if="getColByName(selectedTable, key).COLUMN_TYPE.indexOf('longtext') >= 0
                  || getColByName(selectedTable, key).is_ckeditor"
                  ng-model="selectedRow[key]" style="font-family: Courier;"
                  ng-readonly="selectedTable.form_data[key] == 'RO'" autofocus></textarea>
                <!-- Boolean (tinyint or boolean) -->
                <input class="form-control"
                  type="checkbox"
                  ng-show="getColByName(selectedTable, key).COLUMN_TYPE.indexOf('tinyint') >= 0
                  && !getColByName(selectedTable, key).is_read_only"
                  ng-model="selectedRow[key]"
                  ng-true-value="'1'"
                  ng-false-value="'0'"
                  ng-readonly="selectedTable.form_data[key] == 'RO'"
                  style="width: 50px;"
                  autofocus>
                <!-- TODO: Date -->
              </span>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <!-- CREATE / CLOSE -->
        <button class="btn btn-success" data-dismiss="modal" ng-click="send('create', {row: selectedRow, table: selectedTable})">
          <i class="fa fa-plus"></i> Create</button>
        &nbsp;
        <button class="btn btn-default pull-right" type="button" data-dismiss="modal">
          <i class="fa fa-times"></i> Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal for Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">
          <i class="fa fa-pencil"></i> Edit Entry <small>in <b>{{selectedTable.table_alias}}</b></small>
        </h4>
      </div>
      <div class="modal-body">
        <!-- Content -->
        <form class="form-horizontal">
          <!-- Add if is in menu -->
          <div class="form-group"
            ng-repeat="(key, value) in selectedRow"
            ng-if="getColByName(selectedTable, key).is_in_menu
                && !(selectedTable.se_active && (key.indexOf('state_id') >= 0))
                && (selectedTable.form_data[key] != 'HI')">
            <!-- [LABEL] -->
            <label class="col-sm-3 control-label">{{getColAlias(selectedTable, key)}}</label>
            <!-- [VALUE] -->
            <div class="col-sm-9">
              <!-- Foreign Key (FK) -->
              <span ng-if="getColByName(selectedTable, key).foreignKey.table != ''">
              	<a class="btn btn-default"
                  ng-click="(selectedTable.form_data[key] == 'RO') || openFK(key)"
                  ng-readonly="selectedTable.form_data[key] == 'RO'">
                  <i class="fa fa-key"></i> {{value}}
                </a>
              </span>
              <!-- NO FK -->
              <span ng-if="getColByName(selectedTable, key).foreignKey.table == ''">
                <!-- Number  -->
                <input class="form-control" type="number" string-to-number 
                  ng-if="getColByName(selectedTable, key).COLUMN_TYPE.indexOf('int') >= 0
                  && getColByName(selectedTable, key).COLUMN_TYPE.indexOf('tiny') < 0"
                  ng-model="selectedRow[key]"
                  ng-readonly="selectedTable.form_data[key] == 'RO'" autofocus>
                <!-- Text -->
                <input class="form-control" type="text"
                  ng-if="getColByName(selectedTable, key).COLUMN_TYPE.indexOf('int') < 0
                  && getColByName(selectedTable, key).COLUMN_TYPE.indexOf('long') < 0
                  && !getColByName(selectedTable, key).is_ckeditor"
                  ng-model="selectedRow[key]"
                  ng-readonly="selectedTable.form_data[key] == 'RO'" autofocus>
                <!-- LongText (probably HTML) -->
                <textarea class="form-control" rows="3"
                  ng-if="getColByName(selectedTable, key).COLUMN_TYPE.indexOf('longtext') >= 0
                  || getColByName(selectedTable, key).is_ckeditor"
                  ng-model="selectedRow[key]" style="font-family: Courier;"
                  ng-readonly="selectedTable.form_data[key] == 'RO'" autofocus></textarea>
                <!-- Boolean (tinyint or boolean) -->
                <input class="form-control"
                  type="checkbox"
                  ng-show="getColByName(selectedTable, key).COLUMN_TYPE.indexOf('tinyint') >= 0
                  && !getColByName(selectedTable, key).is_read_only"
                  ng-model="selectedRow[key]"
                  ng-true-value="'1'"
                  ng-false-value="'0'"
                  ng-readonly="selectedTable.form_data[key] == 'RO'"
                  style="width: 50px;"
                  autofocus>
                <!-- TODO: Date -->
              </span>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <!-- STATE MACHINE -->
        <span class="pull-left" ng-hide="!selectedTable.se_active || selectedTable.hideSmBtns">
          <span ng-repeat="state in selectedTable.nextstates">
            <!-- Recursive State -->
            <span ng-if="state.id == selectedRow.state_id">
              <a class="btn btn-primary" ng-click="gotoState(state)">
                <i class="fa fa-floppy-o"></i> Save</a>
            </span>
            <!-- Normal state -->
            <span ng-if="state.id != selectedRow.state_id" class="btn btn-default stateBtn"
              ng-class="'state'+state.id" ng-click="gotoState(state)">{{state.name}}</span>
          </span>
        </span>
        <!-- If has no StateMachine -->
        <span class="pull-left" ng-if="!selectedTable.se_active">
          <button class="btn btn-primary" ng-click="saveEntry()">
            <i class="fa fa-floppy-o"></i> Save</button>
          <button class="btn btn-primary" ng-click="saveEntry()" data-dismiss="modal">
            <i class="fa fa-floppy-o"></i> Save &amp; Close</button>
        </span>
        <!-- Close Button -->
        <button class="btn btn-default pull-right" type="button" data-dismiss="modal">
          <i class="fa fa-times"></i> Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal for ForeignKey -->
<div class="modal fade" id="myFKModal" tabindex="-1" role="dialog"">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="myFKModalLabel"><i class="fa fa-key"></i> Select a Foreign Key</h4>
      </div>
      <div class="modal-body">
      <!-- Search form -->
        <form class="form-inline">
          <div class="form-group">
            <label for="searchtext">Search:</label>
            <input type="text" class="form-control" id="searchtext" placeholder="Seachword" ng-model="FKTbl.sqlwhere" autofocus>
          </div>
          <button type="submit" class="btn btn-default" ng-click="refresh(FKTbl.table_name)"><i class="fa fa-search"></i> Search</button>
        </form>
        <br>
        <!-- Table Content -->
        <div style="overflow: auto;">
          <table class="table table-bordered table-striped table-hover table-condensed table-responsive">
            <thead>
              <tr>
                <th ng-repeat="(key, value) in FKTbl.rows[0]" ng-if="getColByName(FKTbl, key).is_in_menu">
                  <span>{{getColAlias(FKTbl, key)}}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr ng-repeat="row in FKTbl.rows" ng-click="selectFK(row)" style="cursor: pointer;">
                <td ng-repeat="(key, value) in row" ng-if="getColByName(FKTbl, key).is_in_menu">
                  {{value | limitTo: 50}}{{value.length > 50 ? '...' : ''}}
                </td>
              </tr>
            </tbody>
          </table>
        </div>        
        <span class="text-primary">
          <span>{{FKTbl.count}} Entries total</span>
          <span ng-if="getNrOfPages(FKTbl) > 0"> - Page {{FKTbl.PageIndex + 1}} of {{ getNrOfPages(FKTbl) }}</span>
        </span>
      </div>
      <div class="modal-footer">
        <div class="row">
          <div class="col-xs-8">
            <ul class="pagination" style="margin:0; padding:0;">
              <li ng-class="{disabled: FKTbl.PageIndex <= 0}"><a href="" ng-click="gotoPage(0, FKTbl)">«</a></li>          
              <li ng-repeat="btn in getPageination(FKTbl.table_name)"
                ng-class="{disabled: btn + FKTbl.PageIndex == FKTbl.PageIndex}">
                <a href="" ng-click="gotoPage(btn + FKTbl.PageIndex, FKTbl)">{{btn + FKTbl.PageIndex + 1}}</a>
              </li>
              <li ng-class="{disabled: (FKTbl.PageIndex + 1) >= (FKTbl.count / PageLimit)}">
                <a href="" ng-click="gotoPage(999999, FKTbl)">»</a>
              </li>
            </ul>
          </div>        
          <div class="col-xs-4">
            <button class="btn btn-default pull-right" type="button" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal for StateEngine -->
<div class="modal fade" id="modalStateMachine" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">
          <i class="fa fa-random"></i> State-Machine <small>for <b>{{selectedTable.table_alias}}</b></small>
        </h4>
      </div>
      <div class="modal-body">
        <div id="statediagram" style="max-height: 300px; overflow: auto;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default pull-right" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
      </div>
    </div>
  </div>
</div>

<!-- content ends here -->
  <!--  Footer -->
  <div class="container">
    <div class="footer">
      <div class="row text-center">
        <small>
          <ul class="list-inline">
            <li><b>bpmspace_replacer_v1</b></li>
            <li>using</li>
            <li><a target="_blank" href="http://getbootstrap.com/">Bootstrap</a></li>
            <li><a target="_blank" href="https://jquery.com/">jQuery</a></li>
            <li><a target="_blank" href="https://angularjs.org/">AngularJS</a></li>
            <li><a target="_blank" href="http://php.net/">PHP</a></li>
            <li><a target="_blank" href="https://github.com/peredurabefrog/phpSecureLogin">phpSecureLogin</a></li>
            <li><a target="_blank" href="https://github.com/mdaines/viz.js">viz.js</a></li>
          </ul>
        </small>
      </div>
    </div>
  </div>
  <!-- Angular handling-script -->
  <script type="text/javascript">/************************** ANGULAR START *************************/ 
var app = angular.module("genApp", [])
//--- Controller
app.controller('genCtrl', function ($scope, $http) {
  // Variables
  $scope.tables = []
  $scope.isLoading = true
  $scope.PageLimit = 15 // default = 10
  $scope.selectedRow = {}
  $scope.selectedRowOrig = {}
  $scope.FKTbl = []
  $scope.pendingState = false

  $scope.getColAlias = function(table, col_name) {
  	var res = ''
  	table.columns.forEach(function(col){
  		// Compare names
  		if (col.COLUMN_NAME == col_name)
  			res = col.column_alias
  	})
  	if (res == '') return col_name; else return res;
  }
  $scope.getColByName = function(table, col_name) {
    var res = null // empty object
    table.columns.forEach(function(col){
      // Compare names
      if (col.COLUMN_NAME == col_name)
        res = col
    })
    if (res === null) return null; else return res;
  }
  $scope.sortCol = function(table, columnname) {
    table.sqlascdesc = (table.sqlascdesc == "desc") ? "asc" : "desc"
    table.sqlorderby = columnname
    $scope.refresh(table.table_name)
  }
  $scope.openFK = function(key) {
    var table_name = $scope.getColByName($scope.selectedTable, key).foreignKey.table
    // Get the table from foreign key
    $scope.FKTbl = $scope.getTableByName(table_name)
    $scope.FKActCol = key
    // Show Modal
    $('#myFKModal').modal('show')
  }
  $scope.substituteFKColsWithIDs = function(row) {
    var col = $scope.getColByName($scope.selectedTable, $scope.FKActCol).foreignKey.col_id
    $scope.selectedRow[$scope.FKActCol+"________newID"] = row[col]
    var substcol = $scope.getColByName($scope.selectedTable, $scope.FKActCol).foreignKey.col_subst
    var keys = Object.keys($scope.selectedRow)
    for (var i=0;i<keys.length;i++) {
      if (keys[i] == $scope.FKActCol)
        $scope.selectedRow[$scope.FKActCol] = row[substcol]
    }
  }
  $scope.selectFK = function(row) {
    $scope.substituteFKColsWithIDs(row)
    // Close modal
    $('#myFKModal').modal('hide')
  }
  $scope.getPageination = function(table_name) {
    var MaxNrOfButtons = 5
    var t = $scope.getTableByName(table_name)
    if (!t) return
    NrOfPages = $scope.getNrOfPages(t)
    // [x] Case 1: Pages are less then NrOfBtns => display all
    if (NrOfPages <= MaxNrOfButtons) {
      pages = new Array(NrOfPages)
      for (var i=0;i<pages.length;i++)
        pages[i] = i - t.PageIndex
    } else {
      // [x] Case 2: Pages > NrOfBtns display NrOfBtns
      pages = new Array(MaxNrOfButtons)
      // [x] Case 2.1 -> Display start edge
      if (t.PageIndex < Math.floor(pages.length / 2))
        for (var i=0;i<pages.length;i++) pages[i] = i - t.PageIndex
      // [x] Case 2.2 -> Display middle
      else if ((t.PageIndex >= Math.floor(pages.length / 2))
        && (t.PageIndex < (NrOfPages - Math.floor(pages.length / 2))))
        for (var i=0;i<pages.length;i++) pages[i] = -Math.floor(pages.length / 2) + i 
      // [x] Case 2.3 -> Display end edge
      else if (t.PageIndex >= NrOfPages - Math.floor(pages.length / 2)) {
        for (var i=0;i<pages.length;i++) pages[i] = NrOfPages - t.PageIndex + i - pages.length
      }
    }
    return pages
  }
  $scope.gotoPage = function(newIndex, table) {
  	var lastPageIndex = Math.ceil(table.count / $scope.PageLimit) - 1
    // Check borders
  	if (newIndex < 0) newIndex = 0 // Lower limit
  	if (newIndex > lastPageIndex) newIndex = lastPageIndex // Upper Limit
    // Set new index
  	table.PageIndex = newIndex
  	$scope.refresh(table.table_name)
  }
  $scope.getNrOfPages = function(table) {
    if (table)
      return Math.ceil(table.count / $scope.PageLimit)
  }
  $scope.changeTab = function(table_name) {
    $scope.selectedTable = $scope.getTableByName(table_name)
  }
  $scope.loadRow = function(tbl, row) {
    $scope.selectedRow = angular.copy(row)
    $scope.selectedTable = tbl
  }
  $scope.saveEntry = function() {
    // Task is already loaded in memory
    $scope.send('update')
  }
  $scope.editEntry = function(table, row) {
  	console.log("[Edit] Button clicked")
    $scope.loadRow(table, row)
    $scope.send("getFormData")
    $scope.hideSmBtns = true
  }
  $scope.deleteEntry = function(table, row) {
    console.log("[Delete] Button clicked")
    $scope.loadRow(table, row)
    $scope.send('delete')
  }
  $scope.addEntry = function(table_name) {
  	console.log("[Create] Button clicked")
    var t = $scope.getTableByName(table_name)
    // create an empty element
    var newRow = {}
    t.columns.forEach(function(col){
      // check if not auto_inc
      if (col.EXTRA != 'auto_increment')
      	newRow[col.COLUMN_NAME] = ''   
    })
    $scope.loadRow(t, newRow)
    $scope.send("getFormCreate")
  }
  $scope.getRowCSS = function(row) {
    if (angular.equals(row, $scope.selectedRow) && $scope.pendingState) {
      return "info"
    }
    return ""
  }
  $scope.gotoState = function(nextstate) {
    $scope.selectedTable.hideSmBtns = true
    $scope.selectedRow['state_id'] = nextstate.id
    $scope.send('makeTransition')
  }
  $scope.getTableByName = function(table_name) {
    if (typeof table_name != "string") return
    return $scope.tables.find(function(t){
      return t.table_name == table_name;
    })
  }
  $scope.initTables = function() {
	  console.log(window.location.pathname)
    // Request data from config file
  	$http({
  		url: window.location.pathname,
  		method: 'post',
  		data: {
        cmd: 'init',
        paramJS: ''
      }
  	}).success(function(resp){
		console.log(resp)
      // Init each table
  		resp.forEach(function(t){
        // If table is in menu
        if (t.is_in_menu) {
          // Add where, sqlwhere, order
          t.sqlwhere = ''
          t.sqlwhere_old = ''
          t.sqlorderby = ''
          t.sqlascdesc = ''
          t.nextstates = []
          t.statenames = []
          t.PageIndex = 0
          // Push into angular scope
          $scope.tables.push(t)
        }
      })
      // Refresh each table
      $scope.tables.forEach(function(t){
        console.log("Init Table", t)

        // Sort Columns
        var cols = []
        t.columns.forEach(function(col){
          cols.push(col.COLUMN_NAME)
        })
        cols.sort(function(a, b) {
          var a1 = $scope.getColByName(t, a).col_order
          var b1 = $scope.getColByName(t, b).col_order
          return a1 - b1
        })
        t.row_order = cols

        // Refresh Table
        $scope.refresh(t.table_name)
      })
      // GUI
      $scope.isLoading = false

      // Auto click first tab
      // TODO: Remove?
      var tbls = $scope.tables.sort()
      var first_tbl_name = tbls[0].table_name
      $scope.selectedTable = $scope.getTableByName(first_tbl_name)
      $('#'+first_tbl_name).tab('show')

  	});	
  }
  $scope.countEntries = function(table_name) {  	
    var t = $scope.getTableByName(table_name)
    // Get columns from columns
    var joins = []
    t.columns.forEach(function(col) {
      if (col.foreignKey.table != "") { // Check if there is a substitute for the column
        col.foreignKey.replace = col.COLUMN_NAME
        joins.push(col.foreignKey)
      }
    })
    // Request
    $http({
      method: 'post',
      url: window.location.pathname,
      data: {
        cmd: 'read',
        paramJS: {
          select: "COUNT(*) AS cnt",
          tablename: t.table_name,
          limitStart: 0,
          limitSize: 1,
          where: t.sqlwhere,
          orderby: t.sqlorderby,
          ascdesc: t.sqlascdesc,
          join: joins
        }
      }
    }).success(function(response){
      t.count = response[0].cnt
    });
  }

  //------------------------------------------------------- Statemachine functions

  $scope.substituteSE = function(tablename, stateID) {
    t = $scope.getTableByName(tablename)
    if (!t.se_active) return
    // Converts stateID -> Statename
    res = stateID
    t.statenames.forEach(function(state){
      if (parseInt(state.id) == parseInt(stateID))
        res = state.name
    })
    return res
  }
  $scope.getStatemachine = function(table_name) {
    var t = $scope.getTableByName(table_name)
    if (!t.se_active) return
    // Request
  	$http({
  		url: window.location.pathname,
  		method: 'post',
  		data: {
  			cmd: 'getStates',
  			paramJS: {table: table_name}
  	}
  	}).success(function(response){
      t.statenames = response
  	})
  }
  function isExitNode(NodeID, links) {
  	var res = true;
  	links.forEach(function(e){
  		if (e.from == NodeID && e.from != e.to)
  			res = false;
    })
    return res
  }
  function formatLabel(strLabel) {
  	// insert \n every X char
  	return strLabel.replace(/(.{10})/g, "$&" + "\n")
  }
  $scope.drawProcess = function(tbl) {
    var strLinks = ""
    var strLabels = ""
    var strEP = ""
    // Links
    tbl.smLinks.forEach(function(e){strLinks += "s"+e.from+"->s"+e.to+";\n"})
    // Nodes
    tbl.smNodes.forEach(function(e){
      // draw EntryPoint
      if (e.entrypoint == 1) strEP = "start->s"+e.id+";\n" // [Start] -> EntryNode
      // Check if is exit node
      extNd = isExitNode(e.id, tbl.smLinks) // Set flag
      // Actual State
      strActState = ""
      if (!extNd) // no Exit Node
      	strLabels += 's'+e.id+' [label="'+formatLabel(e.name)+'"'+strActState+'];\n'
     	else // Exit Node
     		strLabels += 's'+e.id+' [label="\n\n\n\n'+e.name+'" shape=doublecircle color=gray20 fillcolor=gray20 width=0.15 height=0.15];\n'
    })
    // Render SVG
    document.getElementById("statediagram").innerHTML = Viz(`
    digraph G {
      # global
      rankdir=LR; outputorder=edgesfirst; pad=0.5;
      node [style="filled, rounded" color=gray20 fontcolor=gray20 fontname="Helvetica-bold" shape=box fixedsize=true fontsize=9 fillcolor=white width=0.9 height=0.4];
      edge [fontsize=10 color=gray80 arrowhead=vee];
      start [label="\n\n\nStart" shape=circle color=gray20 fillcolor=gray20 width=0.15 height=0.15];
      # links
      `+strEP+`
      `+strLinks+`
      # nodes
      `+strLabels+`
    }`);
  }
  $scope.openSEPopup = function(table_name) {
  	var t = $scope.getTableByName(table_name)
  	// if no statemachine exists, exit
    if (!t.se_active) return
  	// Request STATES
    $http({url: window.location.pathname, method: 'post',
      data: {cmd: 'getStates', paramJS: {table: t.table_name}}
    }).success(function(response){
      t.smNodes = response
      // Request LINKS
      $http({url: window.location.pathname, method: 'post',
        data: {cmd: 'smGetLinks', paramJS: {table: t.table_name}}
      }).success(function(response){
        t.smLinks = response
        $scope.drawProcess(t)
        // Finally, if everything is loading, show Modal
        $('#modalStateMachine').modal('show')
      })
    })    
  }
  //-------------------------------------------------------

  // Refresh Function
  $scope.refresh = function(table_name) {
    var t = $scope.getTableByName(table_name)
    // Search-Event (set LIMIT Param to 0)
    if (t.sqlwhere != t.sqlwhere_old)
    	t.PageIndex = 0
    // Get columns from columns
    var sel = []
    var joins = []
    t.columns.forEach(function(col) {
      // TODO: -> better on server side
      if (col.foreignKey.table != "") { // Check if there is a substitute for the column
        col.foreignKey.replace = col.COLUMN_NAME
        joins.push(col.foreignKey)
      } else 
        sel.push("a."+col.COLUMN_NAME)
    })
    str_sel = sel.join(",")

  	// Request from server
  	$http({
  		url: window.location.pathname,
  		method: 'post',
  		data: {
    		cmd: 'read',
    		paramJS: {
    			tablename: t.table_name,
    			limitStart: t.PageIndex * $scope.PageLimit,
    			limitSize: $scope.PageLimit,
    			select: str_sel,
          where: t.sqlwhere,
          orderby: t.sqlorderby,
         	ascdesc: t.sqlascdesc,
          join: joins
    		}
  	  }
  	}).success(function(response){ 

      data = response
      t.rows = data // Save cells in tablevar
      t.sqlwhere_old = t.sqlwhere

      // Refresh Counter (changes when delete or create happens) => countrequest if nr of entries >= PageLimit
      if (response.length >= $scope.PageLimit)      	
        $scope.countEntries(table_name)
      else {
        if (t.PageIndex == 0) t.count = response.length
      }
      // Get the states from table
      // TODO: ...? obsolete? maybe only refresh at init, then alsway getNextstates
      $scope.getStatemachine(table_name)
  	})
  }

  // --------------------------
  
  $scope.initTables()

  // --------------------------

  $scope.filterFKeys = function(table, row) {
    var result = {}
    var keys = Object.keys(row) // get column names
    for (var i=0;i<keys.length;i++) {
      var col = keys[i]
      // if they have no foreign key --> just add to result
      tmpCol = $scope.getColByName(table, col)
      if (tmpCol) {
        if (tmpCol.foreignKey.table == "") {
          // No Foreign-Key present
          result[col] = row[col]
        } else {
        	// Foreign-Key present -> Exchange ID
          newID = $scope.selectedRow[col+"________newID"]
          if (newID)
            result[col] = newID // Only set when exists            
          //else
            //result[col] = row[col]
          // Remove object key
          delete $scope.selectedRow[col+"________newID"]
        }
      }
    }
    return result
  }

  //============================================== Basic Send Method

  $scope.send = function(cud, param) {

    if (param) $scope.loadRow(param.table, param.row)
    var body = {cmd: 'cud', paramJS: {}}
    var t = $scope.selectedTable

    //------------------- Assemble Data
  	if (cud == 'create' || cud == 'delete' ||	cud == 'update' ||
  			cud == 'getFormData' || cud == 'getFormCreate' ||
  			cud == 'getNextStates' ||	cud == 'getStates' ||	cud == 'makeTransition') {

        $scope.pendingState = true

     		// Confirmation when deleting
        if (cud == 'delete') {
      		IsSure = confirm("Do you really want to delete this entry?")
      		if (!IsSure) return
        }
        // Prepare Data
  		  body.paramJS = {
    			row : $scope.selectedRow,
    			table : t.table_name
    		}
        // Filter out foreign keys
        if (cud == 'update' || cud == 'makeTransition') {
          // Filter foreign keys
          body.paramJS.row = $scope.filterFKeys(t, body.paramJS.row)
        }
        // Check if state_machine at create
        if (cud == 'create') {
          // StateEngine for entrypoints
          // TODO: Optimize, or even better: remove column completely
          // Also select an Entrypoint if there are more than 1
          // also possible for different processes for each element
          if (t.se_active) body.paramJS.row.state_id = '%!%PLACE_EP_HERE%!%';
          // check Foreign keys
          body.paramJS.row = $scope.filterFKeys(t, body.paramJS.row)
        }
    }

    // ------------------- Send request
    console.log("===> POST ---", cud, "--- params=", body.paramJS)
    // Request
    $http({
      url: window.location.pathname,
      method: 'post',
      data: {
      	cmd: cud,
      	paramJS: body.paramJS
      }
    }).success(function(response) {
      // Response
      console.log("<= ResponseData: ", response)
      $scope.pendingState = false
      var table = $scope.getTableByName(body.paramJS.table)
      //-------------------- table data was modified
      if (response != 0 && (cud == 'delete' || cud == 'update' || cud == 'create')) {  
        // Created
				if (cud == 'create') {
          console.log("New Element with ID", response, "created.")
          $('#modalCreate').modal('hide') // Hide create-modal
          // TODO: Maybe jump to entry which was created
        }
        $scope.refresh(body.paramJS.table)
      }
      else if (cud == 'getFormData') {
        $scope.send("getNextStates") // get next States
        table.form_data = response
        $('#modalEdit').modal('show')
      }
      else if (cud == 'getFormCreate') {
        table.form_data = response
        $('#modalCreate').modal('show')
      }
      //---------------------- StateEngine (List Transitions)
      else if (cud == 'getNextStates') {
      	// Save next States
        table.nextstates = response
        $scope.selectedTable.hideSmBtns = false
      }
      else if (cud == 'makeTransition') {      	
        // Show Transition Message
        // TODO: Make possible HTML Formated Message -> Small modal
        if (response.show_message)
          alert(response.message)
        // Refresh Table
        $scope.refresh(body.paramJS.table)
        $scope.send("getFormData")
      }
      else {
        // Error from server
        alert("Error at ["+cud+"] command.\nThe server returned:\n" + response)
      }
    })
  }
})
app.directive('stringToNumber', function() {
  return {
    require: 'ngModel',
    link: function(scope, element, attrs, ngModel) {
      ngModel.$parsers.push(function(value) { return '' + value; })
      ngModel.$formatters.push(function(value) { return parseFloat(value); })
    }
  }
})
/************************** ANGULAR END *************************/ 

// Every time a modal is shown, if it has an autofocus element, focus on it.
$('#myFKModal').on('shown.bs.modal', function() { $(this).find('[autofocus]').focus() });
$('#modalCreate').on('shown.bs.modal', function() { $(this).find('[autofocus]').first().focus() });
$('#modalEdit').on('shown.bs.modal', function() { $(this).find('[autofocus]').first().focus() });
</script>
</body>
</html>
