<?php
if(get_current_user() == "oriapp") {
	define("NOAUTH", true);
}

include_once("base.php");

$Core->Libraries(["ProjectSet","RecordSet"]);

use \Plugin\RecordSet, \Plugin\ProjectSet, \Plugin\Project;

const MASTER_PROJECT = 42231;
const PROJECTS_TO_COPY = "48727,83015,83016,48728,48729,48730,49705,56875,56876";

$masterProject = new Project(MASTER_PROJECT);
$childProjectSet = new ProjectSet(explode(",",PROJECTS_TO_COPY));

$masterRecords = new RecordSet($masterProject,
		[RecordSet::getKeyComparatorPair($masterProject->getFirstFieldName(),"!=") => ""]);

/** @var Project $childProject */
foreach($childProjectSet->getProjects() as $childProject) {
    echo "Inspecting Project ".$childProject->getProjectId()." - ".$childProject->getProjectName()."\n";
	$childRecords = new RecordSet($childProject, [RecordSet::getKeyComparatorPair($masterProject->getFirstFieldName(),"!=") => ""]);

	foreach($childRecords->getRecords() as $newRecord) {
		$masterRecordId = $childProject->getProjectId()."_".$newRecord->getId();
        echo "   Inspecting $masterRecordId\n";

		$matchingRecord = reset($masterRecords->filterRecords([$masterProject->getFirstFieldName() => $masterRecordId])->getRecords());

        $newRecordId = "automoved-".date("Y-m-d")."-".$masterRecordId;
		if(!$matchingRecord) {
			$matchingRecord = \Plugin\Record::createRecordFromId($masterProject, $newRecordId);

			$matchingRecord->setDetails([]);
		}

		$newDetails = $newRecord->getDetails();
		// $newDetails[$masterProject->getFirstFieldName()] = $newRecordId;
		// unset($newDetails["__GROUPID__"]);

		$matchingRecord->updateDetails($newDetails);
        print date('Y-m-d H:m')." Moved $masterRecordId to $newRecordId\n";
	}
}
