<?php

require_once "../../redcap_connect.php";

// $sql = "SELECT * FROM redcap_log_event WHERE project_id = 42231 AND ts > 20170410000000 AND ts < 20170411000000;";
// $q = db_query($sql);
// $records = array();
// while ($row = db_fetch_assoc($q)) {
    // if ($row['event'] == "DELETE") {
        // if (!in_array($row['pk'], $records)) {
            // $records[] = $row['pk'];
        // }
    // }
// }
$fp = fopen("list", "r");
$records = array();
while ($line = fgets($fp)) {
    $record = trim($line);
    $records[] = $record;
}
echo "<h2>".count($records)." records moved on 04.10.2017 from project pid 42231</h2>";
// echo "'".implode("'<br>'", $records)."'";

$server = "ori33lp";
$username = "pearsosj";
$password = "aggrieve.abdias.wager.seedtime.emend";

$conn = new mysqli($server, $username, $password);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 
echo "Connected successfully";

$projects = array(42231,47289,48727,48728,48729,48730,49705);
$foundRecords = array();
foreach ($records as $record) {
    $query = "SELECT record FROM redcap_data WHERE project_id IN (".implode(", ", $projects).") AND record = '$record';";
    if ($stmt = $mysqli->prepare($query)) {
        $stmt->execute();
        $numRows = $stmt->num_rows;
        if ($numRows > 0) {
            $foundRecords[] = $record;
        }
    }
}
echo "<h2>".count($foundRecords)." records found</h2>";

mysqli_close($conn);
