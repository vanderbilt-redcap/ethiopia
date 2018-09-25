<?php

require_once("../../redcap_connect.php");

$projects = array(42231,47289,48727,48728,48729,48730,49705);
$totals = array();
$titles = array();
for ($i = 1; $i <= 31; $i++) {
    if ($i < 10) {
        $i = "0$i";
    }
    $date = "2017-03-$i";
    echo "<h2>$date</h2>";
    foreach ($projects as $project_id) {
        $sql = "SELECT app_title FROM redcap_projects WHERE project_id = $project_id;";
        $q = db_query($sql);
        $title = "";
        while ($row = db_fetch_assoc($q)) {
            $title = $row['app_title'];
            $titles[$project_id] = $title;
        }
        $sql = "SELECT record,field_name, value
               FROM redcap_data d
               WHERE d.project_id = $project_id
                   AND d.field_name='proc_date'
                   AND d.value LIKE '$date'";
        $q = db_query($sql);
        $records = array();
        while ($row = db_fetch_assoc($q)) {
            if (!in_array($row['record'], $records)) {
                $records[] = $row['record'];
            }
        }
        echo "$title ($project_id): ".db_num_rows($q)." records (".implode(", ", $records).") ".db_error()."<br>";
        if (!isset($totals[$project_id])) {
            $totals[$project_id] = 0;
        }
        $totals[$project_id] += db_num_rows($q);
    }
}

echo "<h2>Totals</h2>";
foreach ($titles as $project_id => $title) {
    echo "$title ($project_id): {$totals[$project_id]}<br>"; 
}

echo "<h2>Search</h2>";
$sql = "SELECT project_id, record, field_name, value
       FROM redcap_data d
       WHERE d.project_id IN (".implode(", ", $projects).")
           AND d.field_name='proc_date'
           AND (d.record LIKE 'automoved-48728-2017-04-16%'
           OR d.record LIKE '%-28578-61')";
$q = db_query($sql);
echo db_num_rows($q)." back<br>";
while ($row = db_fetch_assoc($q)) {
    echo json_encode($row)."<br>";
}
