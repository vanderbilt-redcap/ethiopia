<?php
/*This is a cron designed to remove a record from its DAG 28 days after the indicated date of the procedure.
This is an effort to limit the amount of data being downloaded/uploaded from the laptops used in the field.*/
define("NOAUTH", true);

define("MOVED_PREPEND","automoved");
require_once(dirname(dirname(dirname(__FILE__))) . "/redcap_connect.php");

header('content-type: text/plain');
echo "Ran Ethiopia DAG Cron at ".date("M-d-Y H:i:s")." by ".get_current_user()."\n";

$projects = array(83015,83016);
// $projects = array(1064);
$homeProjectId = "77075";
// $homeProjectId = "1065";
$homeEventId = "91872";
$projectCount = 0;

if(is_file(__DIR__."/config.php")) {
	include_once("config.php");
}
foreach ($projects as $projectId) {
    $projectCount++;
    echo "$projectCount: Inspecting project $projectId\n";
    # short term is for data collectors to leave on devices
    # task: unassign from all DAGs after 28 days
    $recordDataShortTermPre = array();
    $recordDataShortTerm = array();

    $sqlShortTerm = "SELECT d2.record,d2.field_name, d2.value
            FROM redcap_data d
            JOIN redcap_data d2
            ON d.project_id=d2.project_id AND d.record=d2.record AND (d2.field_name='__GROUPID__' OR d2.field_name='proc_date')
            WHERE d.project_id = $projectId
            AND d.field_name='proc_date'
            AND d.value <= (NOW() - INTERVAL 28 DAY)";
    $resultShortTerm = db_query($sqlShortTerm);
    $recordCount=0;
    while ($row = db_fetch_assoc($resultShortTerm)) {
        if (!isset($recordDataShortTermPre[$row['record']])) {
            $recordDataShortTermPre[$row['record']] = array();
        }
        $recordDataShortTermPre[$row['record']][$row['field_name']] = $row['value'];
    }
    foreach ($recordDataShortTermPre as $record => $row) {
        if (isset($row['__GROUPID__'])) {
            $recordDataShortTerm[$record] = $row;
            $recordCount++;
        }
    }
    echo "$projectCount: $recordCount rows back for short term records\n";

    # long term is for research assistants to analyze in host projects
    # task: move to mother project after 3 months (90 days)
    $recordDataLongTerm = array();
	
    $sqlLongTerm = "SELECT record,field_name, value
            FROM redcap_data d
            WHERE d.project_id = $projectId
            AND d.field_name='proc_date'
            AND d.value <= (NOW() - INTERVAL 90 DAY)";
    $resultLongTerm = db_query($sqlLongTerm);
    $recordCount=0;
    while ($row = db_fetch_assoc($resultLongTerm)) {
        if (!isset($recordDataLongTerm[$row['record']])) {
            $recordDataLongTerm[$row['record']] = array();
        }
        $recordDataLongTerm[$row['record']][$row['field_name']] = $row['value'];
        $recordCount++;
    }
    echo "$projectCount: $recordCount rows back for long term records\n";

    // ts = date("Y-m-d");
    $recordCount = 0;
    foreach ($recordDataShortTerm as $record=>$data) {
        $record_new = MOVED_PREPEND."-".$projectId."-".date("Y-m-d")."-".$record;
        $updateSql = "UPDATE redcap_events_calendar SET group_id = NULL WHERE project_id=$projectId AND record='$record'";
        if (!db_query($updateSql)) die("Failure to run SQL statement:".$updateSql." with error ".db_error()."\r\n");

        $deleteSql = "DELETE FROM redcap_data WHERE project_id=$projectId AND record='$record' AND field_name='__GROUPID__'";
        if (!db_query($deleteSql)) die("Failure to run SQL statement:".$deleteSql." with error ".db_error()."\r\n");

        $renameSql1 = "UPDATE redcap_data SET value = '$record_new' WHERE project_id=$projectId AND record='$record' AND field_name = 'record_id'";
        if (!db_query($renameSql1)) die("Failure to run SQL statement:".$renameSql1." with error ".db_error()."\r\n");

        $renameSql2 = "UPDATE redcap_data SET record = '$record_new' WHERE project_id=$projectId AND record='$record'";
        if (!db_query($renameSql2)) die("Failure to run SQL statement:".$renameSql2." with error ".db_error()."\r\n");

        logUpdate($deleteSql, $projectId, "UPDATE", "redcap_data", $record,"redcap_data_access_group = ''","Remove record from Data Access Group");
        logUpdate($renameSql1, $projectId, "UPDATE", "redcap_data", $record,"record_id = '$record_new'","After removing DAG, rename first field name to rename record");
        logUpdate($renameSql2, $projectId, "UPDATE", "redcap_data", $record,"record = '$record_new'","Rename record after renaming first field name");
        echo "Short term ($projectCount, $recordCount): Removed DAG and updated $record to $record_new (".json_encode($data).")\n";

        # bookkeeping
        $recordCount++;
    }

    $recordCount = 0;
    # reassign project and event
    foreach ($recordDataLongTerm as $record_new=>$data) {
		## Check if this record has a renamed record and rename if not
		if(substr($record_new,0,strlen(MOVED_PREPEND)) != MOVED_PREPEND) {
			$record = $record_new;
			$record_new = MOVED_PREPEND."-".$projectId."-".date("Y-m-d")."-".$record;

			$renameSql1 = "UPDATE redcap_data SET value = '$record_new' WHERE project_id=$projectId AND record='$record' AND field_name = 'record_id'";
			if (!db_query($renameSql1)) die("Failure to run SQL statement:".$renameSql1." with error ".db_error()."\r\n");

			$renameSql2 = "UPDATE redcap_data SET record = '$record_new' WHERE project_id=$projectId AND record='$record'";
			if (!db_query($renameSql2)) die("Failure to run SQL statement:".$renameSql2." with error ".db_error()."\r\n");

			logUpdate($renameSql1, $projectId, "UPDATE", "redcap_data", $record,"record_id = '$record_new'","After removing DAG, rename first field name to rename record");
			logUpdate($renameSql2, $projectId, "UPDATE", "redcap_data", $record,"record = '$record_new'","Rename record after renaming first field name");
		}

        $renameSql3 = "UPDATE redcap_data SET event_id=$homeEventId, project_id=$homeProjectId WHERE project_id=$projectId AND record='$record_new'";
        if (!db_query($renameSql3)) die("Failure to run SQL statement:".$renameSql3." with error ".db_error()."\r\n");

        echo "Long term  ($projectCount, $recordCount): Changed project from $projectId to $homeProjectId for $record_new (".json_encode($data).")\n";
        logUpdate($renameSql3, $projectId, "UPDATE", "redcap_data", $record_new,"record = '$record_new'","Move project from $projectId to $homeProjectId");
        $recordCount++;
    }
}

echo "Done!\r\n";

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
