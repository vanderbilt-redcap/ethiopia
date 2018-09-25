<?php

require_once "../../redcap_connect.php";

$projects = array(42231,47289,48727,83015,83016,48728,48729,48730,49705);
$diff = ["28573-2","28574-7","28576-32","28576-33","28576-35","28577-32","28577-33","28577-35","28579-4","28579-5","28753-13","28753-14","28575-15","28575-16","28576-36","28576-37","28577-36","28577-37","28578-24","28578-90","28579-6","28579-7","28579-8","28753-15","28753-16","28753-17","28575-17","28575-18","28575-19","28576-38","28576-39","28576-40","28576-41","28576-42","28578-26","28578-27","28578-28","28578-29","28579-9","28753-18","28753-19","28753-20","28574-20","28575-20","28575-21","28576-43","28578-30","28578-31","28579-10","28579-11","28753-21","28575-22","28575-25","28577-38","28577-39","28577-40","28578-32","28578-33","28743-4","28753-22","28575-24","28753-24","28575-26","28576-44","28576-46","28576-47","28577-41","28577-42","28577-43","28577-44","28578-35","28753-25","28577-46","28577-47","28577-48","28578-36","28578-37","28578-38","28579-13","28579-14","28579-15","28753-26","28575-27","28575-28","28576-48","28576-49","28576-50","28576-51","28576-52","28576-53","28577-49","28577-50","28577-51","28577-52","28579-16","28579-17","28753-27","28753-28","28753-29","28575-29","28576-55","28576-57","28576-58","28577-53","28577-54","28577-55","28577-57","28579-18","28575-30","28577-58","28577-59","28577-60","28577-61","28577-62","28577-63","28577-64","28578-39","28578-40","28579-19","28576-59","28576-60","28576-61","28578-41","28578-42","28753-33","28575-31","28578-43","28578-44","28578-46","28579-20","28743-5","28743-6","28743-7","28575-32","28575-33","28578-47","28578-48","28578-49","28578-50","28578-51","28579-21","28579-22","28579-24","28579-25","28579-26","28743-8","28743-9","28753-37","28753-38","28576-62","28576-63","28576-64","28576-65","28578-52","28578-53","28578-54","28578-55","28579-27","28579-28","28579-29","28579-30","28743-10","28743-11","28753-39","28753-40","28578-57","28578-58","28579-31","28579-32","28579-33","28743-13","28744-10","28744-11","28744-12","28753-41","28753-42","28576-66","28578-59","28578-60","28578-61","28578-62","28579-35","28743-14","28743-15","28744-2","28744-3","28753-43","28753-44"];

$fp = fopen("list_not_found_php", "r");
$diff2 = array();
while ($line = fgets($fp)) {
    if (!in_array($line, $diff2)) {
        $diff2[] = trim($line);
    } else {
        echo "Multiple in list_not_found_php! ".trim($line)."<br>";
    }
}
$diff = $diff2;
fclose($fp);

$april = "20170401000000";
$april11 = "20170410100000";
$january = "20170101000000";

$sql = "SELECT * FROM redcap_log_event WHERE ts >= $april AND ts < $april11 AND project_id IN (".implode(", ", $projects).") ORDER BY ts;";
$q = db_query($sql);
echo $sql."<br>";
echo "ERROR?: ".db_error()."<br>";
echo "Num rows: ".db_num_rows($q)."<br>";
$numApplicable = 0;
$numLeftover = 0;
$dags = 0;
$cron = 0;
$auto = 0;
$logComplete = 0;
$insertComplete = 0;
$usersApplicable = array();
echo "<ol>";
$counts = array();
while ($row = db_fetch_assoc($q)) {
    if (in_array($row['pk'], $diff)) {
        // echo json_encode($row)."<br>";
        if (preg_match("/proc_date.+2017-/", $row['data_values'])) {
            # original upload
            $numApplicable++;
            if (!in_array($row['user'], $usersApplicable)) {
                $usersApplicable[] = $row['user'];
            }
            // echo json_encode($row)."<br>";
            if ($row['event'] == "INSERT") {
                $numEquals = 0;
                $ary = count_chars($row['data_values'], 0);
                if ($ary[ord('=')]) {
                    $numEquals = $ary[ord('=')];
                }
                if (!isset($counts[$row['pk']])) {
                    $counts[$row['pk']] = 0;
                }
                $counts[$row['pk']]++;
                echo "<li>{$row['pk']} ({$counts[$row['pk']]}), Event: {$row['event']}, TS: {$row['ts']}, Items: $numEquals";

                $sqlStatements = preg_replace("/".$row['pk']."/", "automoved-".$row['project_id']."-2017-05-23-".$row['pk'], preg_replace("/\\n/", "\n", $row['sql_log']));
                $sqls = preg_split("/\n/", $sqlStatements);
                foreach($sqls as $sql) {
                    // db_query($sql);
                    if ($error = db_error()) {
                        echo "<br>ERROR: $error<br>$sql";
                    } else {
                        echo "<br>INSERTs made";
                        $insertComplete++;
                    }
                }

                $newrow = $row;
                $newrow['pk'] = "automoved-".$row['project_id']."-2017-05-23-".$row['pk'];
                $logFields = array();
                $logValues = array();
                foreach ($newrow as $field => $value) {
                    if ($field == "ts") {
                        $logFields[] = $field;
                        $logValues[] = date("YmdHis");
                    } else if ($field != "log_event_id") {
                        $logFields[] = $field;
                        $logValues[] = "'".db_real_escape_string($value)."'";
                    }
                }
                $sql = "INSERT INTO redcap_log_event (".implode(",", $logFields).") VALUES (".implode(",", $logValues).");"; 
                db_query($sql);
                if ($error = db_error()) {
                    echo "<br>ERROR: $error<br>$sql";
                } else {
                    echo "<br>Log Entry made";
                    $logComplete++;
                }

                echo "</li>";
            }
        } else if (preg_match("/Assign record to Data Access Group/", $row['description'])) {
            $dags++;
        } else if (preg_match("/Auto calculation/", $row['description'])) {
            $auto++;
        } else if ($row['user'] == "CRON") {
            $cron++;
        } else {
            $numLeftover++;
            // echo json_encode($row)."<br>";
        }
    }
}
echo "</ol>";

$missing = array();
foreach ($diff as $rec) {
    if (!isset($counts[$rec])) {
        $missing[] = $rec;
    }
}

echo "<p>".count($counts)." of ".count($diff)." unique records; missing: ".json_encode($missing)."</p>";
echo "$insertComplete records INSERTed<br>";
echo "$logComplete records entered into log<br>";
echo "num applicable: ".$numApplicable."<br>";
echo "num leftover: ".$numLeftover."<br>";
echo "num dags: ".$dags."<br>";
echo "num auto-calculation: ".$auto."<br>";
echo "num crons: ".$cron."<br>";
echo "users uplaoded: ".json_encode($usersApplicable)."<br>";
