<?php
/*** Created Kyle McGuffin */
echo "AAA";
include_once("projects.php");

echo "BBB";

# Define the environment: options include "DEV", "TEST" or "PROD"
if (is_file('/app001/victrcore/lib/Victr/Env.php'))
	include_once('/app001/victrcore/lib/Victr/Env.php');

echo "CCC";

if(class_exists("Victr_Env")) {
	$envConf = Victr_Env::getEnvConf();

	if ($envConf[Victr_Env::ENV_CURRENT] === Victr_Env::ENV_PROD) {
		define("ENVIRONMENT", "PROD");
	}
	elseif ($envConf[Victr_Env::ENV_CURRENT] === Victr_Env::ENV_DEV) {
		define("ENVIRONMENT", "TEST");
	}
}
else {
	define("ENVIRONMENT", "DEV");
}

echo "DDD";

foreach($linkedProjects as $projectTitle) {
	if(defined(ENVIRONMENT."_".$projectTitle)) {
		define($projectTitle, constant(ENVIRONMENT."_".$projectTitle));
	}
}

echo "EEE";

# Define REDCap path
if (ENVIRONMENT == "DEV") {
	define("CONNECT_FILE_PATH", "../../");
}
else {
	define("CONNECT_FILE_PATH", dirname(dirname(dirname(__FILE__))));
}

echo "FFF";

## Definitions for field names for Master Project
//define("FACULTY_ID_FIELD", "vu_net_id");

//define("MAX_DEGREE_COUNT", 5);

## User name for testing
if(ENVIRONMENT == "DEV") {
	define("USERID", "mcguffk");
}

echo "GGG ".CONNECT_FILE_PATH;

require_once(CONNECT_FILE_PATH."/redcap_connect.php");
echo "HHH";
//require_once(CONNECT_FILE_PATH."/plugins/Core/bootstrap.php");
echo "III";

