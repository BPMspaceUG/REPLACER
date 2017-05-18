<?php

  include_once '../phpSecureLogin/includes/db_connect.inc.php';
  include_once '../phpSecureLogin/includes/functions.inc.php';
  sec_session_start();
  
  if(login_check($mysqli) != true) {
    header("Location: ../index.php?error_messages='You are not logged in!'");
    exit();
  }
  else {
    $logged = 'in';
  }

  // Includes
  include_once("./../DB_config/login_credentials_DB_bpmspace_replacer.inc.php");
  // Parameter and inputstream
  $params = json_decode(file_get_contents('php://input'), true);
  $command = $params["cmd"];
  

  
  /****************************
    S T A T E     E N G I N E  
  ****************************/
  class StateEngine {
    private $db;
    // tables
    private $table = 'connections'; // root element
    private $table_states = 'state';
    private $table_rules = 'state_rules';
    // columns
    private $colname_rootID = 'id';
    private $colname_stateID = 'state_id_ext';
    
    private $colname_stateID_at_TblStates = 'state_id';
    private $colname_stateName = 'name';
    private $colname_from = ' state_id_FROM';
    private $colname_to = 'state_id_TO';
    

    public function __construct($db/*, $tbl_root, $tbl_states, $tbl_rules, $col_rootID, $col_stateID, $colname_stateID_at_TblStates*/) {
      $this->db = $db;
      /*
      $this->table = $tbl_root;
      $this->table_states = $tbl_states;
      $this->table_rules = $tbl_rules;
      $this->colname_rootID = $col_rootID;
      $this->colname_stateID = $col_stateID;
      $this->colname_stateID_at_TblStates = $colname_stateID_at_TblStates;
      */
    }
    private function getResultArray($result) {
      $results_array = array();
      while ($row = $result->fetch_assoc()) {
        $results_array[] = $row;
      }
      return $results_array;
    }
    public function getActState($id) {
      settype($id, 'integer');
      $query = "SELECT a.".$this->colname_stateID." AS 'id', b.".
        $this->colname_stateName." AS 'name' FROM ".$this->table." AS a INNER JOIN ".
        $this->table_states." AS b ON a.".$this->colname_stateID."=b.".$this->colname_stateID_at_TblStates.
        " WHERE ".$this->colname_rootID." = $id;";
      $res = $this->db->query($query);
      return $this->getResultArray($res);
    }    
	public function getStates() {
        $query = "SELECT state_id AS 'id', name FROM ".$this->table_states; 
      $res = $this->db->query($query);
	  //echo json_encode($res);
      return $this->getResultArray($res);
    }    
    public function getStateAsObject($stateid) {
      settype($id, 'integer');
      $query = "SELECT ".$this->colname_stateID_at_TblStates." AS 'id', ".
        $this->colname_stateName." AS 'name' FROM ".$this->table_states.
        " WHERE ".$this->colname_stateID_at_TblStates." = $stateid;";
      $res = $this->db->query($query);
      return $this->getResultArray($res);
    }
    public function getNextStates($actstate) {
      settype($actstate, 'integer');
      $query = "SELECT a.".$this->colname_to." AS 'id', b.".
        $this->colname_stateName." AS 'name' FROM ".$this->table_rules." AS a INNER JOIN ".
        $this->table_states." AS b ON a.".$this->colname_to."=b.".$this->colname_stateID_at_TblStates.
        " WHERE ".$this->colname_from." = $actstate;";
      $res = $this->db->query($query);
      return $this->getResultArray($res);
    }
    
    public function setState($ElementID, $stateID) {

      // get actual state from element
      $actstateObj = $this->getActState($ElementID);
      if (count($actstateObj) == 0) return false;
      $actstateID = $actstateObj[0]["id"];
      $db = $this->db;
      $roottable = $this->table;

      // check transition, if allowed
      $trans = $this->checkTransition($actstateID, $stateID);
      // check if transition is possible
      if ($trans) {        
        $newstateObj = $this->getStateAsObject($stateID);
        $scripts = $this->getTransitionScripts($actstateID, $stateID);
        
        // Execute all scripts from database at transistion
        foreach ($scripts as $script) {
          // Set path to scripts
          $scriptpath = "functions/".$script["transistionScript"]; 

          // -----------> Standard Result
          $script_result = array(
            "allow_transition" => true,
            "show_message" => false,
            "message" => ""
          );
          
          // If script exists then load it
          if (trim($scriptpath) != "functions/" && file_exists($scriptpath))
            include_once($scriptpath);

          // update state in DB, when plugin says yes
          if (@$script_result["allow_transition"] == true) {
            $query = "UPDATE ".$this->table." SET ".$this->colname_stateID." = ".$stateID.
              " WHERE ".$this->colname_rootID." = ".$ElementID.";";
            $res = $this->db->query($query);
          }

          // Return
          return json_encode($script_result);
        }
        
      }
      return false; // exit
    }
    public function checkTransition($fromID, $toID) {
      settype($fromID, 'integer');
      settype($toID, 'integer');
      $query = "SELECT * FROM ".$this->table_rules." WHERE ".$this->colname_from." = $fromID ".
        "AND ".$this->colname_to." = $toID;";
      $res = $this->db->query($query);
      $cnt = $res->num_rows;
      return ($cnt > 0);
    }
    public function getTransitionScripts($fromID, $toID) {
      settype($fromID, 'integer');
      settype($toID, 'integer');
      $query = "SELECT transistionScript FROM ".$this->table_rules." WHERE ".
      "sqms_state_id_FROM = $fromID AND sqms_state_id_TO = $toID;";
      $return = array();
      $res = $this->db->query($query);
      $return = $this->getResultArray($res);
      return $return;
    }
  }



  //RequestHandler Class Definition starts here
  class RequestHandler {
    // Variables
    private $db;
    private $SE;

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
      $this->SE = new StateEngine($this->db);
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
      $cols = array_map('strtolower', $cols);
      // Loop every element
      foreach ($cols as $col) {
        // update only when no primary column
        if (!in_array($col, $primarycols)) {
          $update = $update . $col . "='" . $rows[$col] . "'";
          $update = $update . ", ";
        }
      }
      $update = substr($update, 0, -2); // remove last ' ,' (2 chars)
      return $update;
    }
    public function init() {
      // Send data from config file
      global $config_tables_json;
      return $config_tables_json;
    }
    //================================== CREATE
    public function create($param) {
      // Inputs
      $tablename = $param["table"];
      $rowdata = $param["row"];
      // Operation
      $query = "INSERT INTO ".$tablename." VALUES ('".implode("','", $rowdata)."');";
      $res = $this->db->query($query);
      // Output
      return $res ? "1" : "0";
    }
    //================================== READ
    public function read($param) {
      $where = isset($param["where"]) ? $param["where"] : "";
      if (trim($where) <> "") $where = " WHERE ".$param["where"];
      // SQL
      $query = "SELECT ".$param["select"]." FROM ".
        $param["tablename"].$where." LIMIT ".$param["limitStart"].",".$param["limitSize"].";"; 
      $res = $this->db->query($query);

      // TODO: Also read out statemachine and concat with results
      $states = array("states" => array("id" => 1, "name" => "unknown")); //$this->SE->getStateAsObject(1);
      //$result = array_merge($res, $states);

      return $this->parseToJSON($res);
    }
    //================================== UPDATE
    public function update($param) {
      // SQL
      $update = $this->buildSQLUpdatePart(array_keys($param["row"]), $param["primary_col"], $param["row"]);
      $where = $this->buildSQLWherePart($param["primary_col"], $param["row"]);
      $query = "UPDATE ".$param["table"]." SET ".$update." WHERE ".$where.";";
      //var_dump($query);
      $res = $this->db->query($query);
      // TODO: Check if rows where REALLY updated!
      // Output
      return $res ? "1" : "0";
    }
    //================================== DELETE
    public function delete($param) {
      /*  DELETE FROM table_name WHERE some_column=some_value AND x=1;  */
      $where = $this->buildSQLWherePart($param["primary_col"], $param["row"]);
      // Build query
      $query = "DELETE FROM ".$param["table"]." WHERE ".$where.";";
      $res = $this->db->query($query);
      // Output
      return $res ? "1" : "0";
    }
    //==== Statemachine -> substitue StateID of a Table with Statemachine
    public function getNextStates($param) {
      // Find right column (Maybe optimize with GUID)
      $keys = array_keys($param["row"]);
      $kid = array_search('state_id', $keys); // <= Column must contain state_id
      $real_key = $keys[$kid];
      $stateID = $param["row"][$real_key];
      // execute query
      $res = $this->SE->getNextStates($stateID);
      return json_encode($res);
    }
    public function getStates($param) {
      // IN: (table_name)
      // OUT: [{id: 1, name: 'unknown'}, {id: 2, name: 'test'}]
      $res = $this->SE->getStates(); //$param["row"]["state_id_ext"]);
      return json_encode($res);
    }
  }
  // Class Definition ends here
  // Request Handler ends here

  $RH = new RequestHandler();
  
  // check if at least a command is set
  if ($command != "") {
    // are there parameters?
    if ($params != "") {
      // execute with parameters
      $result = $RH->$command($params["paramJS"]);
    } else {
      // only execute
      $result = $RH->$command();
    }
    // Output
    echo $result;
    exit();
  }
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
  <title>bpmspace_replacer_v1</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- CSS -->
  <link rel="stylesheet" href="../css/bootstrap.min.css">
  <link rel="stylesheet" href="../css/bootstrap-theme.min.css">
  <link rel="stylesheet" href="../css/font-awesome.min.css">
  <link rel="stylesheet" href="css/grideditor.css">
  <!-- Custom CSS -->
  <style>
.newRows {background-color: #cffda2; /*#d8edff;*/}
thead tr {background-color: #eee;}
.controllcoulm {min-width: 100px; width: 100px; background-color: #eee;}
/*
.fresh .controllcoulm { background-color: #abfd5c; }
*/

/**********************************************************/
.panel-table .panel-body{
  padding:0;
}
.panel-table .panel-body .table-bordered{
  border-style: none;
  margin:0;
}
.panel-table .panel-body .table-bordered > thead > tr > th:last-of-type {
    text-align:center;
    width: 100px;
}
.panel-table .panel-body .table-bordered > thead > tr > th:last-of-type,
.panel-table .panel-body .table-bordered > tbody > tr > td:last-of-type {
  border-right: 0px;
}
.panel-table .panel-body .table-bordered > thead > tr > th:first-of-type,
.panel-table .panel-body .table-bordered > tbody > tr > td:first-of-type {
  border-left: 0px;
}
.panel-table .panel-body .table-bordered > tbody > tr:first-of-type > td{
  border-bottom: 0px;
}
.panel-table .panel-body .table-bordered > thead > tr:first-of-type > th{
  border-top: 0px;
}
.panel-table .panel-footer .pagination{
  margin:0;
}
/*
used to vertically center elements, may need modification if you're not using default sizes.
*/
.panel-table .panel-footer .col{
 line-height: 34px;
 height: 34px;
}
.panel-table .panel-heading .col h3{
 line-height: 30px;
 height: 30px;
}
.panel-table .panel-body .table-bordered > tbody > tr > td{
  line-height: 34px;
}

[animate-on-change] {
  transition: all 1s;
  -webkit-transition: all 1s;
}
[animate-on-change].changed {
  background-color: #cffda2;
  transition: none;
  -webkit-transition: none;
}

#bpm-liam-header { 
  margin-top: -20px; 
  margin-bottom: 10px; 
  padding-right: 50px;
}
#bpm-logo-care { 
  position:relativ;  
  z-index: 10;  
  margin-right: -20px;
}
#bpm-logo    { 
  position:relativ; 
  margin-bottom: 20px;
}
#bpm-menu {
  margin-right: 20px; 
  margin-left: 20px; 
  margin-bottom: 10px;
}
#bpm-content {
  margin-right: 20px; 
  margin-left: 20px; 
  margin-bottom: 10px;
}
#bpm-footer {
  margin-right: 10px; 
  margin-left: 10px; 
  margin-bottom: 10px;
}

/* State Engine */
.state1 {background-color: green;}
.state2 {background-color: yellow;}
.state3 {background-color: red;}
.state4 {background-color: orange;}
.state5 {background-color: blue;}
.state6 {background-color: lightblue;}
.state7 {background-color: lightgreen;}

  </style>
  <!-- JS -->
  <script type="text/javascript" src="../js/angular.min.js"></script>
  <script type="text/javascript" src="../js/angular-sanitize.min.js"></script>
  <script type="text/javascript" src="../js/ui-bootstrap-1.3.1.min.js"></script>
  <script type="text/javascript" src="../js/ui-bootstrap-tpls-1.3.1.min.js"></script>  
  <script type="text/javascript" src="../js/jquery-2.1.4.min.js"></script>
  <!--
  <script type="text/javascript" src="../js/tinymce.min.js"></script>
  <script type="text/javascript" src="../js/tinymceng.js"></script>
  -->
  <script type="text/javascript" src="../js/bootstrap.min.js"></script>
  <script type="text/javascript" src="../js/xeditable.min.js"></script>
  <!-- Neuer Editor -->
  <!--<script src="https://code.jquery.com/jquery-1.11.2.js"></script>-->
  <script src="https://code.jquery.com/ui/1.11.2/jquery-ui.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.3.2/tinymce.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.3.2/jquery.tinymce.min.js"></script>
  <script type="text/javascript" src="js/jquery.grideditor.min.js"></script>
</head>
<body ng-app="genApp" ng-controller="genCtrl">
  <div>  <!--  body menu starts here -->
  <app></app>
  <div class="container">
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
    <!-- Company Header -->
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
  </div>
  <!--
  <div id="json-renderer" class="collapsed"></div>
  -->
  <!-- NAVIGATION -->
  <div style="margin: 0 1em;">
  <nav class="navbar navbar-nav">
    <div class="container">
      <ul class="nav nav-pills" id="bpm-menu">
        <li ng-repeat="table in tables">
          <a id="nav-{{table.table_name}}" title="Goto table {{table.table_alias}}"
            href="#{{table.table_name}}" data-toggle="tab" ng-click="changeTab()">
            <i class="{{table.table_icon}}"></i>&nbsp;{{table.table_alias}}</a>
        </li>
      </ul>
    </div>
  </nav>  <!-- body content starts here  -->
  <div style="margin: 0 1em;">
    <div class="row">
      <div class="col-md-12 tab-content" id="bpm-content">

        <div ng-repeat="table in tables track by $index" class="tab-pane" id="{{table.table_name}}">
          <div class="panel panel-default panel-table" disabled>
            <div class="panel-heading">
              <h3 class="panel-title">
                <div class="pull-left" style="margin-top: .4em; font-weight: bold;">{{table.table_alias}}</div>
                <!-- Where filter -->
                <form class="form-inline pull-right">
                  <div class="form-group">
                    <input type="text" class="form-control" style="width:200px;" placeholder="WHERE"
                      ng-model="sqlwhere[$index]" />
                    <button class="btn btn-default" title="Refresh table"
                      ng-click="refresh(table, $index);"><i class="fa fa-refresh"></i></button>
                  </div>
                </form>
                <div class="clearfix"></div>
              </h3>
            </div>
            <div class="panel-body table-responsive" style="padding:0;">
              <table class="table table-bordered table-hover">
                <thead>
                  <tr>
                    <th ng-repeat="col in table.columnsX">{{col.COLUMN_NAME}}</th>
                    <th ng-hide="table.is_read_only"><em class="fa fa-cog"></em></th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Table Content -->
                  <tr ng-repeat="row in table.rows track by $index" ng-model="table"
                      data-toggle='modal' data-target="modal-container-1"
                      id="row{{'' + $parent.$index + $index}}">
                    <!-- Data entries -->
                    <td animate-on-change="cell" ng-repeat="cell in row track by $index">
                      <!-- Substitue State Machine -->
                      <div ng-if="((table.columnames[$index].indexOf('state') >= 0) && table.SE_enabled)">
                        <button class="btn" ng-class="'state'+cell"
                          ng-click="openSEPopup(table, row)">{{subState(cell)}}</button>
                      </div>
                      <!-- Normal field -->
                      <p ng-hide="((table.columnames[$index].indexOf('state') >= 0) && table.SE_enabled)">{{cell}}</p>
                    </td>
                    <!-- Edit options -->
                    <td class="controllcoulm" ng-hide="table.is_read_only">
                      <!-- Update Button -->
                      <a class="btn btn-default" data-toggle="modal" data-target="#modal" ng-click="loadRow(table, row)">
                        <i class="fa fa-pencil"></i>
                      </a>
                      <!-- Delete Button -->
                      <button id="del{{$index}}" class="btn btn-danger" title="Delete this Row"
                        ng-click="send('delete', {row:row, colum:$index, table:table})">
                        <i class="fa fa-times"></i></button>
                    </td>
                  </tr>
                  <!-- ############################## N E W ##### R O W #################################### -->
                  <!-- Table AddRow -->
                  <tr class="newRows" ng-hide="table.is_read_only">
                   <td ng-repeat="col in table.newRows[0] track by $index">
                      <!--<textarea class="form-control nRws" ng-model="table.newRows[0][$index]"></textarea>-->
                      <!-- Number -->
                      <input class="form-control nRws" type="number"
                        ng-show="table.columnsX[$index].COLUMN_TYPE.indexOf('int') >= 0 && table.columnsX[$index].COLUMN_TYPE.indexOf('tiny') < 0"
                        ng-model="table.newRows[0][$index]">
                      <!-- Text -->
                      <input class="form-control nRws" type="text"
                        ng-show="table.columnsX[$index].COLUMN_TYPE.indexOf('int') < 0"
                        ng-model="table.newRows[0][$index]">
                      <!-- Date -->
                      <!-- Boolean (tinyint or boolean) -->
                      <input class="form-control nRws" type="checkbox"
                        ng-show="table.columnsX[$index].COLUMN_TYPE.indexOf('tinyint') >= 0"
                        ng-model="table.newRows[0][$index]">
                      <!-- Datatype --> 
                      <div><small>{{ table.columnsX[$index].COLUMN_TYPE }}</small></div>
                   </td>
                   <td>
                      <!-- Create Button -->
                      <button class="btn btn-success" title="Create new Row"
                        ng-click="send('create', {row:table.newRows[0], table:table})">
                        <i class="fa fa-plus"></i> Add</button>
                   </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="panel-footer">
                <div class="row">
                  <div class="col col-xs-6">
                    <b>Status:</b> {{status}} - {{table.count}} Entries // Showing page {{PageIndex + 1}} of {{table.count / PageLimit | ceil}}
                  </div>
                  <div class="col col-xs-6">
                    <ul class="pagination pull-right"><!-- visible-xs -->
                      <li ng-class="{disabled: PageIndex <= 0}">
                        <a href="" ng-click="gotoPage(0, table, $index)">«</a>
                      </li>
                      <li ng-repeat="elem in getPages(table, PageIndex, PageLimit) track by $index"
                        ng-class="{disabled: elem == PageIndex}">
                        <a href="" ng-click="gotoPage(elem, table, $index)">{{elem+1}}</a>
                      </li>
                       <li ng-class="{disabled: (PageIndex + 1) >= (table.count / PageLimit)}">
                        <a href="" ng-click="gotoPage((table.count / PageLimit)-1, table, $index)">»</a>
                      </li>
                    </ul>
                  </div>
                </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  <!-- Modal for Editing DataRows -->
  <div class="modal fade" id="modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title">Edit</h4>
        </div>
        <div class="modal-body">

          <div class="form-group">
              <label for="x" class="col-sm-3 control-label">Pattern</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" ng-model="selectedTask.replacer_pattern">
              </div>
          </div>
          <div class="clearfix"></div>
          <br>
          <br>

          <div class="test1" style="border: 1px solid red;">
            <p>Deutsch</p>
            <div id="myGrid"><div ng-bind-html="selectedTask.replacer_language_de"></div></div>
          </div>

          <div class="test1" style="border: 1px solid red;">
            <p>Englisch</p>
            <div id="myGrid2"><div ng-bind-html="selectedTask.replacer_language_en"></div></div>
          </div>

          <!--<form class="form-horizontal">
            <div class="form-group" ng-repeat="(key, value) in selectedTask">
              <label for="x" class="col-sm-3 control-label">{{key}}</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" ng-model="selectedTask[key]">
              </div>
            </div>
          </form>-->
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary" ng-click="saveTask()" data-dismiss="modal">OK</button>
          <button type="button" class="btn btn-default pull-right" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
	<!-- Modal for StateEngine -->
	<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	  <div class="modal-dialog" role="document">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
	        <h4 class="modal-title" id="myModalLabel">Go to next State</h4>
	      </div>
	      <div class="modal-body">
	        <p></p>
	      </div>
	      <div class="modal-footer">
	      	<span class="pull-left">
	      		<span>Goto &rarr; </span>
            <span ng-repeat="state in nextstates">
	        	  <button type="button" class="btn btn-primary" ng-click="gotoState(state)" >{{state.name}}</button>
            </span>
	        </span>
	        <button type="button" class="btn btn-default pull-right" data-dismiss="modal">Close</button>
	      </div>
	    </div>
	  </div>
	</div>  
</div>
<!-- body content ends here -->    <!--  Footer -->
    <div class="row text-center">
      <p style="margin:0;">bpmspace_replacer_v1</p>
      <small>using</small>  
      <br/>            
      <small>
        <ul class="list-inline">
          <li><a target="_blank" href="http://getbootstrap.com/">Bootstrap</a></li>
          <li><a target="_blank" href="https://jquery.com/">jQuery</a></li>
          <li><a target="_blank" href="https://github.com/abodelot/jquery.json-viewer">jQuery json-viewer</a></li>
          <li><a target="_blank" href="https://angularjs.org/">AngularJS</a></li>
          <li><a target="_blank" href="http://php.net/">PHP</a></li>
          <li><a target="_blank" href="http://getfuelux.com/">FuelUX</a></li>
          <li><a target="_blank" href="https://angular-ui.github.io/">AngularUI</a></li>
          <li><a target="_blank" href="https://www.tinymce.com/">TinyMCE</a></li>
          <li><a target="_blank" href="https://vitalets.github.io/x-editable/">X-editable</a></li>
          <li><a target="_blank" href="https://github.com/peredurabefrog/phpSecureLogin">phpSecureLogin</a></li>
        </ul>
      </small>
    </div>
  </div>
  <!-- the line below gets replaced with the generated table -->
  <!-- replaceDBContent -->
  <!-- Angular handling-script -->
  <script type="text/javascript">
var app = angular.module("genApp", ["xeditable", "ngSanitize"])

app.run(function(editableOptions) {
  editableOptions.theme = 'bs2'; // bootstrap3 theme. Can be also 'bs2', 'default'
});

app.filter('ceil', function() {
    return function(input) {
        return Math.ceil(input);
    };
});

app.controller('genCtrl', function ($scope, $http) {

  $scope.historyLog = false  
  $scope.tables = []
  $scope.debug = window.location.search.match('debug=1')
  $scope.status = "";
  $scope.PageIndex = 0;
  $scope.PageLimit = 10; // default = 10
  $scope.sqlwhere = []
  $scope.nextstates = []
  $scope.statenames = []

  $scope.changeRow = function(table, row, operation) {
  	// TODO: this will be the function for everything
  	// [update, statemachine, delete]

  	// 1.Step -> Copy row in memory
  	//loadRow(table, row)

  	// 2.Step -> change the row and request additional information from server

  	// 3.Step -> execute if allowed

  	// 4.Step -> give feedback to the userinterface
  }

  $scope.loadRow = function(tbl, row) {
    $scope.selectedTask = angular.copy(row)
    $scope.selectedTable = tbl
  }
  $scope.saveTask = function() {
    console.log("Ok button clicked...")
    $scope.selectedTask.replacer_language_de = $('#myGrid').gridEditor('getHtml');
    $scope.selectedTask.replacer_language_en = $('#myGrid2').gridEditor('getHtml');
    //alert(content)
    $scope.send('update')
  }
  $scope.gotoPage = function(new_page_index, table, index) {
  	// TODO: PageIndex for every table
  	first_page = 0
  	last_page = Math.ceil(table.count / $scope.PageLimit) - 1
  	new_page = new_page_index

  	if (new_page < first_page) return
  	if (new_page > last_page) return
  	$scope.PageIndex = new_page
  	console.log("Goto Page clicked!", table.table_name, "Count:", table.count)
  	$scope.refresh(table, index)
  }

  $scope.getPages = function(table, page_index, page_limit) {
    max_number_of_buttons = 2
    number_of_pages = Math.ceil(table.count / $scope.PageLimit)
    if (number_of_pages <= 0) return
    page_array = new Array(number_of_pages-1)
    for (var i=0;i<number_of_pages;i++) page_array[i] = i

    // create array container
    if (number_of_pages < max_number_of_buttons)
      btns = page_array
    else {
      // More Pages than max displayed buttons -> sub array
      btns_next = page_array.slice(page_index, page_index+max_number_of_buttons+1)
      if (page_index <= max_number_of_buttons)
        btns_before = page_array.slice(0, page_index)
      else
        btns_before = page_array.slice(page_index-max_number_of_buttons, page_index)
      // concat
      btns = btns_before.concat(btns_next)
    }
    // output
    return btns
  }
  $scope.changeTab = function() {
    // start at index 0 -> Feature: Maybe save and restore
  	$scope.PageIndex = 0;
  }  
  $scope.openSEPopup = function(tbl, row) {
    $scope.loadRow(tbl, row) // select current Row
    $scope.send("getNextStates")
  }
  $scope.gotoState = function(nextstate) {
    // TODO: Optimize ... check on serverside if possible etc.
    res = null;
    for (property in $scope.selectedTask) {
      if (property.indexOf('state_id') >= 0)
        res = property
    }
    $scope.selectedTask[res] = nextstate.id
    $scope.send('update')
  }

  $scope.initTables = function() {
  	$scope.status = "Initializing...";

  	tables = null;
  	$http({
  		url: window.location.pathname, // use same page for reading out data
  		method: 'post',
  		data: {cmd: 'init', paramJS: ''}
  	}).success(function(resp){

  		tables = resp;

  		/*********************************************************************/

  		tables.forEach(
			function(tbl) {
				// no need for previous deselectet tables
				if(!tbl.is_in_menu){return}
				// Request from server
				// Read content
				$http({
					url: window.location.pathname, // use same page for reading out data
					method: 'post',
					data: {
					cmd: 'read',
					paramJS: {
						tablename: tbl.table_name,
						limitStart: $scope.PageIndex * $scope.PageLimit,
						limitSize: $scope.PageLimit,
						select: "*"
					}
				}
				}).success(function(response){
					// debugging
					console.log("Table '", tbl.table_name, "'", tbl);
					console.log(" - Data:", response);
					//define additional Rows
					var newRows = [[]]
					// Create new rows by columns
					Object.keys(tbl.columns).forEach(
						function(){newRows[newRows.length-1].push('')}
					);
					//define colum headers
					var keys = ['names']
					if(response[0] && typeof response[0] == 'object'){
						keys = Object.keys(response[0])
					}
					$scope.tables.push({
						table_name: tbl.table_name,
						table_alias: tbl.table_alias,
						table_icon: tbl.table_icon,
						columnsX: tbl.columns,
        		is_read_only: tbl.is_read_only,
        		SE_enabled: (tbl.se_active),
						columnames: keys,
						rows: response,
						count: 0,
						newRows : newRows
					})

	       	// Count entries
	        $scope.countEntries(tbl.table_name);
					// open first table in navbar
					 $('#nav-'+$scope.tables[0].table_name).click();
					// TODO: Platzhalter für Scope Texfelder generierung  
				});
				// Save tablenames in scope
				$scope.tablenames = $scope.tables.map(function(tbl){return tbl.table_name})
			}
		)
		$scope.status = "Initializing... done";


  		/*********************************************************************/

  	});	
  }

  $scope.countEntries = function(table_name) {
  	console.log("counting entries from table", table_name);
  	$http({
  		url: window.location.pathname,
  		method: 'post',
  		data: {
  			cmd: 'read',
  			paramJS: {tablename: table_name, limitStart: 0, limitSize: 1, 
  				select: "COUNT(*) AS cnt"
  			}
  	}
  	}).success(function(response){
  		// Find table in scope
  		act_tbl = $scope.tables.find(function(t){return t.table_name == table_name})
  		act_tbl.count = response[0].cnt;
  	})
  }

  $scope.subState = function(stateID) {
  	// Converts stateID -> Statename
  	res = stateID
	$scope.statenames.forEach(function(state){
		if (parseInt(state.id) == parseInt(stateID))
			res = state.name
	})
	return res
  }

  // Statemachine
  $scope.getStatemachine = function(table_name) {
  	console.log("get states from table", table_name);
  	$http({
  		url: window.location.pathname,
  		method: 'post',
  		data: {
  			cmd: 'getStates',
  			paramJS: {tablename: table_name}
  	}
  	}).success(function(response){
  		// Find table in scope
  		act_tbl = $scope.tables.find(function(t){return t.table_name == table_name})
  		console.log("States:", response)
  		$scope.statenames = response // save data in scope
  		console.log("Saved in scope!")
  		//act_tbl.count = response[0].cnt;
  	})
  }
  // Refresh Function
  $scope.refresh = function(scope_tbl, index) {
  	$scope.status = "Refreshing...";
    console.log($scope.sqlwhere[index]);
  	// Request from server
  	$http({
  		url: window.location.pathname, // use same page for reading out data
  		method: 'post',
  		data: {
  		cmd: 'read',
  		paramJS: {
  			tablename: scope_tbl.table_name,
  			limitStart: $scope.PageIndex * $scope.PageLimit,
  			limitSize: $scope.PageLimit,
  			select: "*",
        	where: $scope.sqlwhere[index]
  		}
  	}
  	}).success(function(response){
      	$scope.getStatemachine(scope_tbl.table_name)
      	$scope.countEntries(scope_tbl.table_name)
  		// Add data to Frontend and get additional information
  		$scope.tables.find(function(tbl){return tbl.table_name == scope_tbl.table_name}).rows = response;
  	})
  	$scope.status = "Refreshing... done";
  }

  $scope.initTables();

  /*
  Allround send for changes to DB
  */
  $scope.send = function(cud, param){
    //console.log(param.x)
    console.log("-> Send # CUD=", cud, "Params:", param)

    var body = {cmd: 'cud', paramJS: {}}

    // TODO: remove this
    // load in memory
    if (param)
    	$scope.loadRow(param.table, param.row)


    // TODO: probably not the best idea to send the primary columns from client
    // better assebmle them on the server side

    // Function which identifies _all_ primary columns
    function getPrimaryColumns(col) {
      var resultset = [];
      for (var i = 0; i < col.length-1; i++) {
        if (col[i].COLUMN_KEY.indexOf("PRI") >= 0) {
          // Column is primary column
          resultset.push(col[i].COLUMN_NAME);
        }
      }
      //console.log("---- Primary Columns:", resultset);
      return resultset;
    }

    function convertCols(inputObj) {
      var key, keys = Object.keys(inputObj);
      var n = keys.length;
      var newobj={}
      while (n--) {
        key = keys[n];
        newobj[key.toLowerCase()] = inputObj[key];
      }
      return newobj;
    }

    // Assemble data for Create, Update, Delete Functions
    // TODO: ----> kann man verbessern, alles sehr ähnlich
    if (cud == 'create') {
      body.paramJS = {
        row: param.row,
        table: param.table.table_name,
        primary_col: param.table.primary_col
      }
    }
    else if (cud == 'delete' || cud == 'update' || cud == 'getNextStates' || cud == 'getStates') {
    	console.log($scope.selectedTable)
    	console.log($scope.selectedTask)
   		// Confirmation when deleting
      	if (cud == 'delete') {
    		IsSure = confirm("Do you really want to delete this entry?");
    		if (!IsSure) return
      	}
		// if Sure -> continue
		body.paramJS = {
			row: convertCols($scope.selectedTask),
			primary_col: getPrimaryColumns($scope.selectedTable.columnsX),
			table: $scope.selectedTable.table_name
		}
	} else {
		// Unknown Command
    	console.log('unknown command: ', cud)
    	return
    }
    post()

    //========================================

    function post(){
      console.log("POST-Request", "Command:", cud, "Params:", body.paramJS)

      $http({
        url:window.location.pathname,
        method:'post',
        data: {
          cmd: cud,
          paramJS: body.paramJS
        }
      }).success(function(response){
        // Debugging
        console.log("ResponseData: ", response);
        $scope.lastResponse = response;

        // GUI Notifications for user feedback
        //-------------------- Entry Deleted
        if (response != 0 && (cud == 'delete' || cud == 'update')) {
          // if state was updated then
          //$('#myModal').modal('hide')
          // Refresh current table
          /*act_tbl = $scope.tables.find(function(t){
            return t.table_name == param.table.table_name})*/
          $scope.refresh($scope.selectedTable)
        }
        //-------------------- Entry Created
        else if (cud == 'create' && response != 0) {
          console.log("-> Entry was created");


        	// Find current table
        	act_tbl = $scope.tables.find(function(t){return t.table_name == param.table.table_name});

          // Clear all entry fields
          for (var x=0;x<act_tbl.newRows.length;x++) {
            for (var y=0;y<act_tbl.newRows[x].length;y++) {
              act_tbl.newRows[x][y] = '';            
            }
          }
          // Set focus on first element after adding, usability issues
          $(".nRws").first().focus();



        	// Refresh current table 
        	$scope.refresh($scope.selectedTable)
        }
        //---------------------- StateEngine (List Transitions)
        else if (cud == 'getNextStates') {
          $scope.nextstates = response
          $('#myModal').modal('show')
        }
        else if (cud == 'getStates') {
        	alert("WTF")
        }
      })
    }
  }
})

// Update animation
// TODO: only animate when cmd [update] was sent
app.directive('animateOnChange', function($timeout) {
  return function(scope, element, attr) {
    scope.$watch(attr.animateOnChange, function(nv,ov) {
      if (nv!=ov) {
        element.addClass('changed');
        $timeout(function() {
          element.removeClass('changed');
        }, 1000); // Could be enhanced to take duration as a parameter
      }
    });
  };
});</script>
<!-- Custom -->
<script>
    $(function() {
        // Initialize grid editor
        $('#myGrid').gridEditor({
            new_row_layouts: [[12], [6,6], [9,3]],
            content_types: ['tinymce'],
        });

        // Initialize grid editor
        $('#myGrid2').gridEditor({
            new_row_layouts: [[12], [6,6], [9,3]],
            content_types: ['tinymce'],
        });
    });
</script>
</body>
</html>
