<?php

require_once("../../../redcap_connect.php");

$download = isset($_GET['download']);
$myMax = 0;

function generateNewRecordId($projectId) {
    global $myMax;

    $max = $myMax;
    $search = "csv-".$projectId."-".date("Y-m-d")."-";
    $sql = "SELECT record FROM redcap_data
            WHERE record LIKE '$search%'
                AND project_id = $projectId;";
    $q = db_query($sql);
    while ($row = db_fetch_assoc($q)) {
        if (preg_match("/".$search."/", $row['record'])) {
            $str = preg_replace("/".$search."/", "", $row['record']);
            if ($str > $max) {
                $max = $str;
            }
        }
    }
    $rec = "csv-".date("Y-m-d")."-".($max + 1);
    $myMax = $max + 1;
    return $rec;
}


if (isset($_GET['submit']) && (isset($_POST['project_id'])) && ($_POST['project_id'] != '')) {
    $numFiles = $_POST['numFiles'];
    $projectId = $_POST['project_id'];

    if($download){
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");

        // disposition / encoding on response body
        header("Content-Disposition: attachment;filename=" . $projectId . "_mobile_app.csv");
        header("Content-Transfer-Encoding: binary");

        $fpOut = fopen('php://output', 'w');
    }

    $saveRecord = function($headers, $data, $filename, $rowIndex) use ($projectId){
        $lineNumber = $rowIndex + 1;

        $fields = [];
        for ($i = 0; $i < count($headers); $i++) {
            $fields[$headers[$i]] = $data[$i];
        }

        $procDate = $fields['proc_date'];
        $dateParts = explode('-', $procDate);
        $year = $dateParts[0];
        if(strlen($year) == 4){
			// header('content-type text/plain');
			// print_r(json_encode([$fields]));
			// exit;
            $response = REDCap::saveData($projectId, 'json', json_encode([$fields]));
            $errors = $response['errors'];

            if (empty($errors) && count($response['ids']) != 1) {
                $errors[] = 'The id of the record was not included in the response!  That means it did not get saved, even though REDCap returned no errors.';
            }
        }
        else{
            $errors = ["The proc_date specified was '$procDate'.  Only dates in YYYY-MM-DD format are supported.  Was this file opened in Excel prior to upload?  If so, Excel has likely mangled the dates."];
        }

        if (!empty($errors)) {
            echo "The import failed on the file named '$filename' on line number $lineNumber with the following error(s):<br><br>";
            var_dump($errors);
            echo "<br><br>Previous files and lines were imported successfully.";
            die();
        }

        $warningsWeCareAbout = [];
        foreach ($response['warnings'] as $warning) {
            if (strpos($warning, 'Calculated fields cannot be imported') !== FALSE) {
                continue;
            }

            $warningsWeCareAbout[] = $warning;
        }

        if (!empty($warnings)) {
            echo "Warning(s) on line $lineNumber of '$filename':<br>";
            foreach ($warnings as $warning) {
                if (strpos($warning, 'Calculated fields cannot be imported') !== FALSE) {
                    continue;
                }

                echo $warning . '<br>';
            }
            echo '<br>';
        }
    };

    $handleData = function($headers, $data, $filename, $rowIndex) use ($download, $fpOut, $saveRecord){
        if($download){
            fputcsv($fpOut, $data);
        }
        else{
            $saveRecord($headers, $data, $filename, $rowIndex);
        }
    };

    $handleHeaders = function ($headers) use ($download, $handleData) {
        if ($download) {
            $handleData(null, $headers, null, null);
        }
    };

    $hasHeaderRowBeenWritten = false;
    $headers = array();
    for ($i = 0; $i < $numFiles; $i++) {
        $file = $_FILES['file' . $i];
        $filename = $file['tmp_name'];
        $fp = fopen($filename, "r");
        $j = 0;
        $myheaders = array();
        while ($row = fgetcsv($fp)) {
            if ($row[0]) {
                if ($j === 0) {
                    if (!$hasHeaderRowBeenWritten) {
                        $hasHeaderRowBeenWritten = true;
                        for ($k =0; $k < count($row); $k++) {
                            $headers[] = $row[$k];
                        }
						
                        $handleHeaders($headers);
                    }
                    for ($k = 0; $k < count($row); $k++) {
                        $myheaders[$row[$k]] = $k;
                    }
                } else {
                    $needsNewRecordId = false;
                    $sql = "SELECT field_name, value FROM redcap_data
                            WHERE project_id = $projectId
                                AND record = '".db_real_escape_string($row[0])."';";
                    $q = db_query($sql);
                    if ($error = db_error()) {
                        die($error);
                    }
                    $fields = array();
                    while ($row2 = db_fetch_assoc($q)) {
                        $fields[$row2['field_name']] = $row['value'];
                    }
                    if ($fields['proc_date'] && $fields['mrn']) {
                        $MRN = $row[$myheaders['mrn']];
                        $procDate = $row[$myheaders['proc_date']];
                        if ($MRN != $fields['mrn']) {
                            $needsNewRecordId = true;
                        }
                        if ($procDate != $fields['proc_date']) {
                            $needsNewRecordId = true;
                        }
                    } else {
                        $needsNewRecordId = true;
                    }
                    if ($needsNewRecordId) {
                        $row[0] = generateNewRecordId($projectId);
                    }
                    $newRow = array();
                    foreach ($headers as $header) {
                        if (isset($myheaders[$header])) {
                            $newRow[] = $row[$myheaders[$header]];
                        } else {
                            $newRow[] = "";
                        }
                    }
                    $handleData($headers, $newRow, $file['name'], $j);
                }
            }
            $j++;
        }
        fclose($fp);
    }

    if($download){
        fclose($fpOut);
    }
    else{
        echo "The CSVs have been combined and automatically imported into project $projectId.";
    }
} else {
    echo "<script src='https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js'></script>";
    echo "<script>";
    echo "
    function showNext(i) {
        i = Number(i);
        if ($('#file'+i).val() !== '') {
            $('#pFile'+(i+1)).show();
            $('#numFiles').val(Number($('#numFiles').val()) + 1);
        }
    }
    ";

    $postUrl = '?submit=1';
    if($download){
        $postUrl .= '&download';
    }

    echo "</script>";
    echo "<form action='$postUrl' enctype='multipart/form-data' method='POST'>";
    echo "<p>Project: <select name='project_id'><option value=''></option>";
    // $sql = "SELECT app_title, project_id FROM redcap_projects WHERE app_title LIKE '%ImPACT Ethiopia%' ORDER BY project_id";
    $sql = "SELECT app_title, project_id FROM redcap_projects WHERE app_title LIKE '%Ethiopia%' ORDER BY project_id";
    $q = db_query($sql);
    if ($error = db_error()) {
        echo "ERROR: $error<br>";
    }
    while ($row = db_fetch_assoc($q)) {
        echo "<option value='{$row['project_id']}'>";
        echo $row['app_title'];
        echo "</option>";
    }
    echo "</select></p>";
    for ($i = 0; $i < 25; $i++) {
        $style = "";
        if ($i > 0) {
            $style = "style='display: none;'";
        }
        echo "<p id='pFile$i' $style>File $i: <input type='file' name='file$i' onchange='showNext($i);' id='file$i'></p>";
    }

    if($download){
        $buttonText = 'Download Combined CSV';
    }
    else{
        $buttonText = 'Combine & Import Into Project';
    }

    echo "<input type='hidden' id='numFiles' name='numFiles' value='0'>";
    echo "<p><button>$buttonText</button></p>";
    echo "</form>";

    if(!$download){
        ?>
        <script>
            $(function () {
                $('form').submit(function () {
                    if($('select[name="project_id"]').val() == ''){
                        alert('You must select a project.')
                        return false
                    }

                    if ($('input[name="numFiles"]').val() == 0) {
                        alert('You must select at least one file.')
                        return false
                    }

                    var confirmation = confirm('The data will automatically be imported into the selected project.  Are you sure you want to continue?')

                    if(confirmation){
                        $(function(){
                            // This call is wrapped in a function to add it to the even queue so it doesn't prevent the form submission.
                            $('body').html('Importing.  Please wait...')
                        })
                    }

                    return confirmation
                })
            })
        </script>
        <?php
    }
}
