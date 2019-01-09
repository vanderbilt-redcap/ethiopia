<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/redcap_connect.php");
if(is_file(__DIR__."/config.php")) {
	include_once("config.php");
}
$updateSql = "UPDATE redcap_data SET event_id = 181075 WHERE project_id=77075";
$run = db_query($updateSql);
if ($run) {
	echo "Event ID set to 181075 for project id: 77075";
} else {
	echo "SQL query failed. Event ID not set.";
}

function logUpdate($sql_run, $project_id, $event, $table, $record, $dataValues, $description) {
    // Log the event in the redcap_log_event table
    $ts         = str_replace(array("-",":"," "), array("","",""), NOW);
    $page       = (defined("PAGE") ? PAGE : (defined("PLUGIN") ? "PLUGIN" : ""));
    $userid     = defined("USERID") ? USERID : "CRON";
    $ip         = (isset($userid) && $userid == "[survey respondent]") ? "" : getIpAddress(); // Don't log IP for survey respondents
    $event      = strtoupper($event);
    $event_id   = (isset($_GET['event_id']) && is_numeric($_GET['event_id'])) ? $_GET['event_id'] : "NULL";

    // Query
    $sql = "INSERT INTO redcap_log_event
            (project_id, ts, user, ip, page, event, object_type, sql_log, pk, event_id, data_values, description, change_reason)
            VALUES ($project_id, $ts, '".prep($userid)."', ".checkNull($ip).", '$page', '$event', '$table', ".checkNull($sql_run).",
            ".checkNull($record).", $event_id, ".checkNull($dataValues).", ".checkNull($description).", NULL)";
    //echo "$sql<br/>";
    db_query($sql);
}

logUpdate($updateSql, 77075, "UPDATE", "redcap_data", null, "event_id = 181075", "One-time correction of event_id to 181075");