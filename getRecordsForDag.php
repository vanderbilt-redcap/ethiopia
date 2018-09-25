<?php

require_once "../../redcap_connect.php";

if (isset($_GET['dag'])) {
    $sql = "SELECT app_title, project_id FROM redcap_projects WHERE app_title LIKE 'ImPACT Africa Global Perioperative Outcomes%' ORDER BY project_id";
    $q = db_query($sql);
    if ($error = db_error()) {
        echo "ERROR: $error<br>";
    }
    $projects = array();
    while ($row = db_fetch_assoc($q)) {
        $projects[$row['project_id']] = $row['app_title'];
    }
    
    $projectPids = array();
    foreach ($projects as $pid => $title) {
        $projectPids[] = $pid;
    }
    
    $sql = "SELECT DISTINCT record, project_id FROM redcap_data
            WHERE field_name = '__GROUPID__'
                AND value = ".$_GET['dag']."
                AND project_id IN (".implode(",", $projectPids).");";
    $q = db_query($sql);
    if ($error = db_error()) {
        echo "ERROR: $error $sql<br>";
    }
    echo db_num_rows($q)." records back<br>";
    while ($row = db_fetch_assoc($q)) {
        echo json_encode($row)."<br>";
    }
} else {
    echo "Please specify a dag=[#DAG#] in the URL.";
}
