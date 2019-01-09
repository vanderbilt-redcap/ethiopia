<?php
$updateSql = "UPDATE redcap_data SET event_id = 181075 WHERE project_id=77075";
$run = db_query($updateSql);
if ($run) {
	echo "Event ID set to 181075 for project id: 77075";
} else {
	echo "SQL query failed. Event ID not set.";
}