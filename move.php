<?php

require_once("../../redcap_connect.php");

function getNewName($record) {
    $rec = $record;
    while (preg_match("/-automoved-/", $rec)) {
        $nodes = preg_split("/-/", $rec);
        $newNodes = array();
        $pastFirst = false;
        $pastSecond = false;
        foreach ($nodes as $node) {
            if ($node == "automoved") {
                if (!$pastFirst) {
                    $pastFirst = true;
                } else {
                    $pastSecond = true;
                }
            }
            if ($pastSecond) {
                $newNodes[] = $node;
            }
        }
        $rec = implode("-", $newNodes);
    }
    return $rec;
}

function getReturnProject($record)
{
    $rec = getNewName($record);
    $nodes = preg_split("/-/", $rec);
    return $nodes[1];
}

$projectId = 42231;
$sql = "SELECT d.record, d.value
        FROM redcap_data d
        WHERE d.project_id = $projectId
            AND d.field_name='proc_date'
            AND d.value > (NOW() - INTERVAL 120 DAY)";
$q = db_query($sql);
$records = array();
while ($row = db_fetch_assoc($q)) {
    if (preg_match("/^automoved-/", $row['record'])) {
        // echo "Move ".$row['record']." with proc_date ".$row['value']." to ".getNewName($row['record'])." pid ".getReturnProject($row['record'])."<br>";
        $sql = "SELECT e.event_id FROM redcap_events_metadata e JOIN redcap_events_arms a ON a.project_id = ".getReturnProject($row['record'])." AND a.arm_id = e.arm_id;";
        echo $sql."<br>";
        $q2 = db_query($sql);
        if ($error = db_error()) {
            echo "&nbsp;&nbsp;&nbsp;SELECT ERROR: ".$error."<br>";
        }
        $event_id = "";
        if ($row2 = db_fetch_assoc($q2)) {
            $eventId = $row2['event_id'];
        }
    
        if ($eventId) {
            $sql = "UPDATE redcap_data SET record='".getNewName($row['record'])."', event_id = '$eventId', project_id='".getReturnProject($row['record'])."' WHERE record = '".$row['record']."' AND project_id = $projectId;";
            db_query($sql);
            echo $sql."<br>";
            if ($error = db_error()) {
                echo "&nbsp;&nbsp;&nbsp;UPDATE ERROR: ".$error."<br>";
            }
        } else {
            echo "&nbsp;&nbsp;&nbsp;eventId ERROR: ".db_error()."<br>";
        }
    }
}
