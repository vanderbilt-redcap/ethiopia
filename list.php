<?php

require_once "../../redcap_connect.php";

$sql = "SELECT * FROM redcap_log_event WHERE project_id = 42231 AND ts > 20170410000000 AND ts < 20170411000000;";
$q = db_query($sql);
$records = array();
while ($row = db_fetch_assoc($q)) {
    if ($row['event'] == "DELETE") {
        if (!in_array($row['pk'], $records)) {
            $records[] = $row['pk'];
        }
    }
}
echo "<h2>".count($records)." records moved on 04.10.2017 from project pid 42231</h2>";
echo implode("<br>", $records);
