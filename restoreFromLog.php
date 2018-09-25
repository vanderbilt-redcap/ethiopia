<?php

require_once "../../redcap_connect.php";

$projects = array(42231,47289,48727,48728,48729,48730,49705);

$fp = fopen("list_not_found", "r");
$records = array();
$origRecords = array();
while ($line = fgets($fp)) {
    $record = trim($line);
    $records[$record] = $projects;
    $origRecords[] = $record;
}

function renameRecord($rec) {
    $nodes = preg_split("/-/", $rec);
    $newNodes = array();
    for ($i = 5; $i < count($nodes); $i++) {
        $newNodes[] = $nodes[$i];
    }
    return implode("-", $newNodes);
}
function getPIDFromRecordName($record) {
    $nodes = preg_split("/-/", $record);
    return $nodes[1];
}

foreach ($records as $record => $pid) {
    $newrec = renameRecord($record);
    $pids = array(getPIDFromRecordName($record));
    $records[$newrec] = $projects;
}

$jsons = array();
$records2count = array();
$proc_dates = array();
$mrns = array();
foreach ($records as $record => $projectIDs) {
    $sql = "SELECT * FROM redcap_log_event WHERE project_id IN (".implode(", ", $projectIDs).") AND pk = '$record' ORDER BY ts;";
    $q = db_query($sql);
    // echo "<p>$record: ".db_num_rows($q)." rows back; ".db_error()."</p>";
    while ($row = db_fetch_assoc($q)) {
        if ($row['data_values'] && (!preg_match("/^record/", $row['data_values'])) && !preg_match("/redcap_data_access_group/", $row['data_values'])) {
            $nodes = preg_split("/',/", $row['data_values']);
            $nodes2 = array();
            for ($i = 0; $i < count($nodes); $i++) {
                $subnodes = preg_split("/checked,/", $nodes[$i]);
                if (count($subnodes) > 1) {
                    for ($j = 0; $j < count($subnodes) - 1; $j++) {
                        $nodes2[] = $subnodes[$j]."checked";
                    }
                    if (preg_match("/checked$/", $subnodes[count($subnodes) - 1]))
                    {
                        $nodes2[] = $subnodes[count($subnodes) - 1];
                    } else {
                        $nodes2[] = $subnodes[count($subnodes) - 1]."'";
                    }
                } else {
                    if ($i + 1 < count($nodes)) {
                        $nodes2[] = $nodes[$i]."'";
                    } else {
                        $nodes2[] = $nodes[$i];
                    }
                }
            }
            $values = array();
            foreach ($nodes2 as $node) {
                $a = preg_split("/ = /", $node);
                if (count($a) == 2) {
                    $a[0] = trim($a[0]);
                    $a[1] = preg_replace("/^'/", "", $a[1]);
                    $a[1] = preg_replace("/'$/", "", $a[1]);
                    $values[$a[0]] = $a[1];
                } else {
                    echo "<p>ERROR (".$row['pk']."): Node $node does not split in ".$row['data_values'].".</p>";
                }
            }
            $values['record_id'] = $row['pk'];
            if (!in_array($row['pk'], $records2count)) {
                $records2count[] = $row['pk'];
            }
            echo $row['ts']." <span style='color: red;'>".$row['pk']."</span> ".$row['project_id'].": ".json_encode($values)."<br>";
            if (isset($values['mrn'])) {
                $mrns[$row['pk']] = $values['mrn'];
            }
            if (isset($values['proc_date'])) {
                $proc_dates[$row['pk']] = $values['proc_date'];
            }
            $jsons[] = $values;
        }
    }
}

$both = array();
foreach ($mrns as $rec => $one) {
    if ($proc_dates[$rec]) {
        $both[$rec] = 1;
    }
}

echo count($jsons)." record lines for ".count($records2count)." records.<br>";
echo count($mrns)." mrns and ".count($proc_dates)." and ".count($both)." both.<br>";
echo count($origRecords)." original records.<br>";

$tokens = array();
$tokens[42231] = "3EE5380CCA1C20D238A2A5EA541B0DDB";
$tokens[47289] = "F3270DB9F7D04E403415883B2CD6D3CB";
$tokens[48727] = "52355F8FF85AACBCFD0318E2FB659382";
$tokens[48728] = "D440544A284B241C13331763281E577F";
$tokens[48729] = "0FAB0E95251B1E2434AB82D0DB80E370";
$tokens[48730] = "9A525173F0E586918F1965A4AE2229F5";
$tokens[49705] = "58986CA274FDF0A57557AAC233350CF9";

$redcap = array();
foreach ($tokens as $pid => $token) {
    $data = array(
        'token' => $token,
        'content' => 'record',
        'format' => 'json',
        'type' => 'flat',
        'fields' => array('mrn', 'proc_date', 'record_id'),
        'rawOrLabel' => 'raw',
        'rawOrLabelHeaders' => 'raw',
        'exportCheckboxLabel' => 'false',
        'exportSurveyFields' => 'false',
        'exportDataAccessGroups' => 'false',
        'returnFormat' => 'json'
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://redcap.vanderbilt.edu/api/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
    $output = curl_exec($ch);
    $redcap[$pid] = json_decode($output, true);
    curl_close($ch);
}

$matches = array();
foreach ($redcap as $pid => $data) {
    $matches[$pid] = array();
    foreach ($mrns as $record => $mrn) {
        if (isset($proc_dates[$record])) {
            $proc_date = $proc_dates[$record];
            foreach ($data as $row) {
                if ($row['mrn'] && $row['proc_date']) {
                    $currRecId = $row['record_id'];
                    $currMRN = $row['mrn'];
                    $currProcDate = $row['proc_date'];

                    if (($currMRN == $mrn) && ($currProcDate = $proc_date)) {
                        $matches[$pid][] = array("mrn" => $mrn, "proc_date" => $proc_date);
                        break;
                    }
                }
            }
        }
    }
}

echo "<ul>MATCHES:";
foreach ($matches as $pid => $pairs) {
    echo "<li>$pid: ".count($pairs)."</li>";
}
echo "</ul>";
