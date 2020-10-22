<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Copyright (C) 2008-2016 All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	KonradSC <konrd@yahoo.com>
*/

//includes
	require_once "root.php";
	require_once "resources/require.php";

	//logging
	openlog("FusionPBX", LOG_PID | LOG_PERROR, LOG_LOCAL0);

//send http error
	function http_error($error,$error_message) {
		if ($error === "404") {
			header("HTTP/1.0 404 Not Found");
			echo "<html>\n";
			echo "<head><title>404 Not Found</title></head>\n";
			echo "<body bgcolor=\"white\">\n";
			echo "<center><h1>404 Not Found</h1></center>\n";
			echo "<hr><center>nginx/1.12.1</center>\n";
			echo "</body>\n";
			echo "</html>\n";
		}
		elseif ($error === "401") {
			header("HTTP/1.0 401 Unauthorized");
			echo "<html>\n";
			echo "<head><title>401 Unauthorized</title></head>\n";
			echo "<body bgcolor=\"white\">\n";
			echo "<center><h1>401 Unauthorized ".$error_message."</h1></center>\n";
			echo "<hr><center>nginx/1.12.1</center>\n";
			echo "</body>\n";
			echo "</html>\n";
		}
		elseif ($error === "400") {
			header("HTTP/1.0 400 Bad Request");
			echo "<html>\n";
			echo "<head><title>400 Bad Request</title></head>\n";
			echo "<body bgcolor=\"white\">\n";
			echo "<center><h1>400 Bad Request</h1></center>\n";
			echo "<hr><center>nginx/1.12.1</center>\n";
			echo "</body>\n";
			echo "</html>\n";
		}
		elseif ($error === "500") {
    		header("HTTP/1.0 500 Internal Server Error");
    		echo "<html>\n";
    		echo "<head><title>500 Internal Server Error</title></head>\n";
    		echo "<body bgcolor=\"white\">\n";
    		echo "<center><h1>500 Internal Server Error</h1></center>\n";
    		echo "<hr><center>nginx/1.12.1</center>\n";
    		echo "</body>\n";
    		echo "</html>\n";
		}
		exit;
	}

// $username_part = explode('@', $_GET['username']);
// $extension = $username_part[0];
// $domain_name = $username_part[1];

//define PHP variables from the HTTP values
$mac = substr($_REQUEST['deviceId'], -10);
$reprovision = $_REQUEST['reprovision'];
$password = $_REQUEST['password'];
$device_id = $_REQUEST['deviceId'];


//check the password and get the domain_uuid and domain name and extension details
$sql = "select s.sessiontalk_key, s.generated_date, e.domain_uuid, d.domain_name, e.extension_uuid, e.extension, e.password, e.number_alias ";
$sql .= "from v_sessiontalk_keys as s, v_extensions as e, v_domains as d ";
$sql .= "where sessiontalk_key = :password ";
$sql .= "AND e.extension_uuid = s.extension_uuid ";
$sql .= "AND d.domain_uuid = e.domain_uuid ";
$parameters['password'] = $password;
$database = new database;
$sessiontalk_row = $database->select($sql, $parameters, 'row');
unset($sql, $parameters);

//Check if sessiontalk_key exists. We check if it is expired later.
if (!$sessiontalk_row) {
	http_error("401", "");
}


$domain_uuid = $sessiontalk_row['domain_uuid'];
$domain_name = $sessiontalk_row['domain_name'];

//get the default settings
$sql = "select * from v_default_settings ";
$sql .= "where default_setting_enabled = 'true' ";
$sql .= "order by default_setting_order asc ";
$database = new database;
$result = $database->select($sql, null, 'all');
//unset the previous settings
if (is_array($result) && @sizeof($result) != 0) {
	foreach ($result as $row) {
		unset($_SESSION[$row['default_setting_category']]);
	}
	//set the settings as a session
	foreach ($result as $row) {
		$name = $row['default_setting_name'];
		$category = $row['default_setting_category'];
		$subcategory = $row['default_setting_subcategory'];
		if (strlen($subcategory) == 0) {
			if ($name == "array") {
				$_SESSION[$category][] = $row['default_setting_value'];
			}
			else {
				$_SESSION[$category][$name] = $row['default_setting_value'];
			}
		}
		else {
			if ($name == "array") {
				$_SESSION[$category][$subcategory][] = $row['default_setting_value'];
			}
			else {
				$_SESSION[$category][$subcategory]['uuid'] = $row['default_setting_uuid'];
				$_SESSION[$category][$subcategory][$name] = $row['default_setting_value'];
			}
		}
	}
}
unset($sql, $result, $row);

//get the domains settings
if (is_uuid($domain_uuid)) {
	$sql = "select * from v_domain_settings ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "and domain_setting_enabled = 'true' ";
	$sql .= "order by domain_setting_order asc ";
	$parameters['domain_uuid'] = $domain_uuid;
	$database = new database;
	$result = $database->select($sql, $parameters, 'all');
	//unset the arrays that domains are overriding
	if (is_array($result) && @sizeof($result) != 0) {
		foreach ($result as $row) {
			$name = $row['domain_setting_name'];
			$category = $row['domain_setting_category'];
			$subcategory = $row['domain_setting_subcategory'];
			if ($name == "array") {
				unset($_SESSION[$category][$subcategory]);
			}
		}
		//set the settings as a session
		foreach ($result as $row) {
			$name = $row['domain_setting_name'];
			$category = $row['domain_setting_category'];
			$subcategory = $row['domain_setting_subcategory'];
			if (strlen($subcategory) == 0) {
				//$$category[$name] = $row['domain_setting_value'];
				if ($name == "array") {
					$_SESSION[$category][] = $row['domain_setting_value'];
				}
				else {
					$_SESSION[$category][$name] = $row['domain_setting_value'];
				}
			}
			else {
				//$$category[$subcategory][$name] = $row['domain_setting_value'];
				if ($name == "array") {
					$_SESSION[$category][$subcategory][] = $row['domain_setting_value'];
				}
				else {
					$_SESSION[$category][$subcategory][$name] = $row['domain_setting_value'];
				}
			}
		}
	}
}

// Set variables
$transport = $_SESSION['provision']['sessiontalk_transport']['text'];
$srtp = $_SESSION['provision']['sessiontalk_srtp']['text'];
$qr_expiration = $_SESSION['provision']['sessiontalk_qr_expiration']['numeric'];
$max_activations = $_SESSION['provision']['sessiontalk_max_activations']['numeric'];
$domain_part = explode('.', $domain_name);
$sub_domain = $domain_part[0]; 



$expiration = $sessiontalk_row['generated_date'] + $qr_expiration;





// // get device lines for associated device
// if (strlen($device_id) < 0 ) {
//     $sql = "SELECT l.user_id, l.password, l.sip_transport, d.device_uuid ";
//     $sql .= "FROM v_devices AS d, v_device_lines AS l, v_sessiontalk_devices as s ";
//     $sql .= "WHERE s.sessiontalk_deviceid = :device_id ";
//     $sql .= "AND s.device_uuid = d.device_uuid ";
//     $sql .= "AND d.device_uuid = l.device_uuid ";
//     $parameters['device_id'] = $device_id;
//     $database = new database;
//     $lines = $database->select($sql, $parameters, 'row');
//     unset($sql, $parameters);
// }

//get activation count for the key
$sql = "SELECT count(*) FROM v_sessiontalk_devices ";
$sql .= "WHERE sessiontalk_key = :password ";
$parameters['password'] = $password;
$database = new database;
$activations = $database->select($sql, $parameters, 'column');
unset($sql, $parameters);

//get the device activation state for the deviceid
$sql = "SELECT * FROM v_sessiontalk_devices ";
$sql .= "WHERE sessiontalk_deviceid = :device_id ";
$parameters['device_id'] = $device_id;
$database = new database;
$activation = $database->select($sql, $parameters, 'all');
unset($sql, $parameters);



if ($reprovision == "false" && strlen($device_id) > 0 && !count($activation) ) {
    //check for code expiration
    if (date("U") > $expiration){
        http_error("401","Key Expired");
    }
    //check if code is already used
    if ($activations >= $max_activations) {
        http_error("403","");
    }
    
    
 

    //optional: respect device limits
    // if ($_SESSION['limit']['devices']['numeric'] != '') {
    // 	$sql = "select count(*) from v_devices where domain_uuid = :domain_uuid ";
    // 	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
    // 	$database = new database;
    // 	$total_devices = $database->select($sql, $parameters, 'column');
    //	if ($total_devices >= $_SESSION['limit']['devices']['numeric']) {
    //		 	http_error("403", "");
    //	 	)
    // 	}
    // 	unset($sql, $parameters, $total_devices);
    // }
    
    //create device
    //set the device uuid
    $device_uuid = uuid();
    
    //prepare the array
    $array['devices'][0]['domain_uuid'] = $domain_uuid;
    $array['devices'][0]['device_uuid'] = $device_uuid;
    $array['devices'][0]['device_mac_address'] = $mac;
    
    //$array['devices'][0]['device_provisioned_ip'] = $device_provisioned_ip;
    //$array['devices'][0]['device_label'] = $device_label;
    // $array['devices'][0]['device_user_uuid'] = $device_user_uuid;
    // $array['devices'][0]['device_username'] = $device_username;
    // $array['devices'][0]['device_password'] = $device_password;
    $array['devices'][0]['device_vendor'] = "sessiontalk";
    $array['devices'][0]['device_model'] = "SessionCloud";
    // $array['devices'][0]['device_firmware_version'] = $device_firmware_version;
    $array['devices'][0]['device_enabled'] = "true";
    $array['devices'][0]['device_enabled_date'] = 'now()';
    $array['devices'][0]['device_template'] = "sessiontalk";
    // $array['devices'][0]['device_profile_uuid'] = is_uuid($device_profile_uuid) ? $device_profile_uuid : null;
    //$array['devices'][0]['device_description'] = $device_description;
    $y = 0;
    
    //create the device line. we only create one during first provision, future provisions will read manually added lines.
    $device_line_uuid = uuid();
    $array['devices'][0]['device_lines'][$y]['domain_uuid'] = $domain_uuid;
    $array['devices'][0]['device_lines'][$y]['device_uuid'] = $device_uuid;
    $array['devices'][0]['device_lines'][$y]['device_line_uuid'] = $device_line_uuid;
    $array['devices'][0]['device_lines'][$y]['line_number'] = 1;
    $array['devices'][0]['device_lines'][$y]['server_address'] = $domain_name;
    $array['devices'][0]['device_lines'][$y]['display_name'] = $sessiontalk_row["effective_caller_id_name"];
    $array['devices'][0]['device_lines'][$y]['user_id'] = $sessiontalk_row['number_alias'] ?: $sessiontalk_row["extension"];
    $array['devices'][0]['device_lines'][$y]['auth_id'] = $sessiontalk_row['number_alias'] ?: $sessiontalk_row["extension"];
    $array['devices'][0]['device_lines'][$y]['password'] = $sessiontalk_row["password"];
    $array['devices'][0]['device_lines'][$y]['enabled'] = "true";
    $array['devices'][0]['device_lines'][$y]['sip_port'] = $_SESSION['provision']['line_sip_port']['numeric'];
    $array['devices'][0]['device_lines'][$y]['sip_transport'] =  $_SESSION['provision']['sessiontalk_transport']['text'];
    $array['devices'][0]['device_lines'][$y]['register_expires'] = $_SESSION['provision']['line_register_expires']['numeric'];
    
    //save the device
    $database = new database;
    $database->app_name = 'devices';
    $database->app_uuid = '4efa1a1a-32e7-bf83-534b-6c8299958a8e';
    $database->save($array);

    //save the Sessiontalk device association
    $sql = "INSERT INTO v_sessiontalk_devices ";
    $sql .= "VALUES ( :sessiontalk_deviceid, :device_uuid, :sessiontalk_key ) ";
    $parameters['sessiontalk_deviceid'] = $device_id;
    $parameters['device_uuid'] = $device_uuid;
    $parameters['sessiontalk_key'] = $password;
    $database = new database;
    $database->execute($sql,$parameters);
    unset($sql, $parameters);
        

    

	
}
elseif ($reprovision == "true" && strlen($device_id) > 0 && count($activation)) {
	

	//register that we have seen the device
	$sql = "update v_devices ";
	$sql .= "set device_provisioned_date = :device_provisioned_date, device_provisioned_method = :device_provisioned_method, device_provisioned_ip = :device_provisioned_ip ";
	$sql .= "where domain_uuid = :domain_uuid and device_uuid = :device_uuid ";
	$parameters['domain_uuid'] = $domain_uuid;
	$parameters['device_uuid'] = $device_uuid;
	$parameters['device_provisioned_date'] = date("Y-m-d H:i:s");
	$parameters['device_provisioned_method'] = (isset($_SERVER["HTTPS"]) ? 'https' : 'http');
	$parameters['device_provisioned_ip'] = $_SERVER['REMOTE_ADDR'];
	$database = new database;
	$database->execute($sql, $parameters);
	unset($sql, $parameters);
	
}
else {
	http_error('400', "");
}

if ($activations >= $max_activations) {
    http_error("403", "");
}

// get device lines for associated device - Overwrite Existing
if (strlen($device_id) > 0 ) {
    $sql = "SELECT l.user_id, l.password, l.sip_transport, s.device_uuid ";
    $sql .= "FROM v_sessiontalk_devices as s ";
    $sql .= "JOIN v_device_lines as l ";
    $sql .= "ON l.device_uuid = s.device_uuid ";
    $sql .= "WHERE s.sessiontalk_deviceid = :device_id ";

    $parameters['device_id'] = $device_id;
    $database = new database;
    $lines = $database->select($sql, $parameters, 'row');
    unset($sql, $parameters);
}
else {
    http_error('400',"");
}

//loop through the lines
if (is_array($lines) && count($lines) != 0) {
	$i = 0;
//	foreach ($lines as $line) {
		$account_array['sipusername'] = $lines['user_id'];
		$account_array['sippassword'] = $lines['password'];
		$account_array['subdomain'] = $sub_domain;
		$account_array['authusername'] = $lines['user_id'];
		$account_array['transport'] = $transport ?: $lines['sip_transport'];
		$account_array['srtp'] = $srtp;
		$account_array['messaging'] = "Disabled";
		$account_array['video'] = "Disabled";
		$account_array['callrecording'] = "Disabled";
		$settings['update'] = "false";
		$settings['errmsg'] = "Contact Support";
		$settings['sipaccounts'][$i++] = $account_array;	
	
//	}
	
}
else {
    http_error('500', "");
}



	header('Content-Type: application/json');
	print_r(json_encode($settings));


?>
