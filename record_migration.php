<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 10/13/2016
 * Time: 4:52 PM
 */
$f = fopen("kenya_record_migration.csv","r");
include_once("base.php");

$Core->Libraries("Project","Record");

$project = new \Plugin\Project(42231);

$header = fgetcsv($f);
if($project->getFirstFieldName() == "") {
	die("COuldn't find project");
}

while($row = fgetcsv($f)) {
	$startRecord = $row[0];
	$endRecord = $row[1];

	$recordTaken = true;
	$targetRecord = new \Plugin\Record($project,[[$project->getFirstFieldName()]],[$project->getFirstFieldName() => $endRecord]);
	try {
		$targetRecord->getId();
	}
	catch(Exception $e) {
		$recordTaken = false;
	}

	if($recordTaken) {
		$endRecord .= "_1";
	}

	$sqlValue = "UPDATE redcap_data
			SET value = '$endRecord'
			WHERE project_id = ".$project->getProjectId()."
				AND record = '".$startRecord."'
				AND field_name = '".$project->getFirstFieldName()."'";

	$sqlRecord = "UPDATE redcap_data
			SET record = '$endRecord'
			WHERE project_id = ".$project->getProjectId()."
				AND record = '".$startRecord."'";

	echo "<pre>";
	echo "$sqlValue\n\n$sqlRecord";
	echo "</pre>";

	db_query($sqlValue);
	db_query($sqlRecord);
}