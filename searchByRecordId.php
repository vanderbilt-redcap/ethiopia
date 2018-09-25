<?php

require_once("../../redcap_connect.php");

$projects = array(42231,47289,48727,48728,48729,48730,49705);
        $sql = "SELECT project_id FROM redcap_projects WHERE app_title LIKE 'ImPACT Africa Global Perioperative Outcomes%'";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            if (in_array($row['project_id'], $projects)) {
                $projects[] = $row['project_id'];
            }
        }
        $sql = "SELECT record,project_id
                FROM redcap_data d
                WHERE d.record LIKE '".$_GET['record']."'
                    AND d.project_id IN (".implode(", ", $projects).");";
        $q = db_query($sql);
        $projects2 = array();
        while ($row = db_fetch_assoc($q)) {
            if (!in_array($row['project_id'], $projects2)) {
                $projects2[] = $row['project_id'];
            }
            echo json_encode($row)."<br>";
        }
        echo $sql."<br><br>";
        echo db_num_rows($q)." records (".implode(", ", $projects2).") ".db_error()."<br>";
