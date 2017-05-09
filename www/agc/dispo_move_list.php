<?php
# dispo_move_list.php
# 
# Copyright (C) 2014  Matt Florell <vicidial@gmail.com>    LICENSE: AGPLv2
#
# This script is designed to be used in the "Dispo URL" field of a campaign
# or in-group. It should take in the lead_id to check for the same lead_id
# in order to change it's list_id to whatever new_list_id is set to. The
# sale_status field is a list of statuses separated by three dashes each '---'
# which contain the statuses for which the process should be run.
#
# This script is part of the API group and any modifications of data are
# logged to the vicidial_api_log table.
#
# This script limits the number of altered leads to 1 per instance and it will
# not run if the search field of the lead is empty.
#
# Example of what to put in the Dispo URL field:
# VARhttp://192.168.1.1/agc/dispo_move_list.php?lead_id=--A--lead_id--B--&dispo=--A--dispo--B--&user=--A--user--B--&pass=--A--pass--B--&new_list_id=10411099&sale_status=SALE---SSALE---XSALE&reset_dialed=Y&log_to_file=1
# 
# Definable Fields: (other fields should be left as they are)
# - log_to_file -	(0,1) if set to 1, will create a log file in the agc directory
# - sale_status -	(SALE---XSALE) a triple-dash "---" delimited list of the statuses that are to be moved
# - new_list_id -	(999,etc...) the list_id that you want the matching status leads to be moved to
# - reset_dialed -	(Y,N) if set to Y, will reset the called_since_last_reset flag on the lead
#    Multiple sets of statuses:
# - sale_status_1, new_list_id_1, reset_dialed_1 - adding an underscore and number(1-99) will allow for another set of statuses to check for and what to do with them
#
# CHANGES
# 100915-1600 - First Build
# 110702-2020 - Added multiple sets of options
# 111005-1102 - Added check and update for scheduled callback entry
# 120223-2124 - Removed logging of good login passwords if webroot writable is enabled
# 130328-0015 - Converted ereg to preg functions
# 130603-2216 - Added login lockout for 15 minutes after 10 failed logins, and other security fixes
# 130802-1007 - Changed to PHP mysqli functions
# 140811-0844 - Changed to use QXZ function for echoing text
# 141118-1235 - Formatting changes for QXZ output
# 141216-2110 - Added language settings lookups and user/pass variable standardization
#

$api_script = 'deactivate';

header ("Content-type: text/html; charset=utf-8");

require_once("dbconnect_mysqli.php");
require_once("functions.php");

$filedate = date("Ymd");
$filetime = date("H:i:s");
$IP = getenv ("REMOTE_ADDR");
$BR = getenv ("HTTP_USER_AGENT");

$PHP_AUTH_USER=$_SERVER['PHP_AUTH_USER'];
$PHP_AUTH_PW=$_SERVER['PHP_AUTH_PW'];
$PHP_SELF=$_SERVER['PHP_SELF'];
if (isset($_GET["lead_id"]))				{$lead_id=$_GET["lead_id"];}
	elseif (isset($_POST["lead_id"]))		{$lead_id=$_POST["lead_id"];}
if (isset($_GET["sale_status"]))			{$sale_status=$_GET["sale_status"];}
	elseif (isset($_POST["sale_status"]))	{$sale_status=$_POST["sale_status"];}
if (isset($_GET["dispo"]))					{$dispo=$_GET["dispo"];}
	elseif (isset($_POST["dispo"]))			{$dispo=$_POST["dispo"];}
if (isset($_GET["new_list_id"]))			{$new_list_id=$_GET["new_list_id"];}
	elseif (isset($_POST["new_list_id"]))	{$new_list_id=$_POST["new_list_id"];}
if (isset($_GET["reset_dialed"]))			{$reset_dialed=$_GET["reset_dialed"];}
	elseif (isset($_POST["reset_dialed"]))	{$reset_dialed=$_POST["reset_dialed"];}
if (isset($_GET["user"]))					{$user=$_GET["user"];}
	elseif (isset($_POST["user"]))			{$user=$_POST["user"];}
if (isset($_GET["pass"]))					{$pass=$_GET["pass"];}
	elseif (isset($_POST["pass"]))			{$pass=$_POST["pass"];}
if (isset($_GET["DB"]))						{$DB=$_GET["DB"];}
	elseif (isset($_POST["DB"]))			{$DB=$_POST["DB"];}
if (isset($_GET["log_to_file"]))			{$log_to_file=$_GET["log_to_file"];}
	elseif (isset($_POST["log_to_file"]))	{$log_to_file=$_POST["log_to_file"];}


#$DB = '1';	# DEBUG override
$US = '_';
$TD = '---';
$STARTtime = date("U");
$NOW_TIME = date("Y-m-d H:i:s");
$sale_status = "$TD$sale_status$TD";
$search_value='';
$match_found=0;
$k=0;

$user=preg_replace("/\'|\"|\\\\|;| /","",$user);
$pass=preg_replace("/\'|\"|\\\\|;| /","",$pass);

#############################################
##### START SYSTEM_SETTINGS AND USER LANGUAGE LOOKUP #####
$VUselected_language = '';
$stmt="SELECT selected_language from vicidial_users where user='$user';";
if ($DB) {echo "|$stmt|\n";}
$rslt=mysql_to_mysqli($stmt, $link);
	if ($mel > 0) {mysql_error_logging($NOW_TIME,$link,$mel,$stmt,'00XXX',$user,$server_ip,$session_name,$one_mysql_log);}
$sl_ct = mysqli_num_rows($rslt);
if ($sl_ct > 0)
	{
	$row=mysqli_fetch_row($rslt);
	$VUselected_language =		$row[0];
	}

$stmt = "SELECT use_non_latin,enable_languages,language_method FROM system_settings;";
$rslt=mysql_to_mysqli($stmt, $link);
	if ($mel > 0) {mysql_error_logging($NOW_TIME,$link,$mel,$stmt,'02001',$user,$server_ip,$session_name,$one_mysql_log);}
if ($DB) {echo "$stmt\n";}
$qm_conf_ct = mysqli_num_rows($rslt);
if ($qm_conf_ct > 0)
	{
	$row=mysqli_fetch_row($rslt);
	$non_latin =				$row[0];
	$SSenable_languages =		$row[1];
	$SSlanguage_method =		$row[2];
	}
##### END SETTINGS LOOKUP #####
###########################################

if ($non_latin < 1)
	{
	$user=preg_replace("/[^-_0-9a-zA-Z]/","",$user);
	}

if ($DB>0) {echo "$lead_id|$search_field|$campaign_check|$sale_status|$dispo|$new_status|$user|$pass|$DB|$log_to_file|\n";}

if (preg_match("/$TD$dispo$TD/",$sale_status))
	{$match_found=1;}
else
	{
	$sale_status='';
	$new_list_id='';
	$reset_dialed='';
	while( ($match_found < 1) and ($k < 99) )
		{
		$k++;
		$sale_status='';
		$statusfield = "sale_status_$k";
		if (isset($_GET["$statusfield"]))			{$sale_status=$_GET["$statusfield"];}
			elseif (isset($_POST["$statusfield"]))	{$sale_status=$_POST["$statusfield"];}
		$sale_status = "$TD$sale_status$TD";

		if ($DB) {echo _QXZ("MULTI_MATCH CHECK:")." $k|$sale_status|$statusfield|\n";}

		if (strlen($sale_status)>0)
			{
			if (preg_match("/$TD$dispo$TD/",$sale_status))
				{
				$match_found=1;
				$newlistfield = "new_list_id_$k";
				$resetfield = "reset_dialed_$k";
				if (isset($_GET["$newlistfield"]))			{$new_list_id=$_GET["$newlistfield"];}
					elseif (isset($_POST["$newlistfield"]))	{$new_list_id=$_POST["$newlistfield"];}
				if (isset($_GET["$resetfield"]))			{$reset_dialed=$_GET["$resetfield"];}
					elseif (isset($_POST["$resetfield"]))	{$reset_dialed=$_POST["$resetfield"];}
				if ($DB) {echo _QXZ("MULTI_MATCH:")." $k|$sale_status|$new_list_id|$reset_dialed\n";}
				}
			}
		}
	}
if ($match_found > 0)
	{
	if ($non_latin < 1)
		{
		$user=preg_replace("/[^-_0-9a-zA-Z]/","",$user);
		$pass=preg_replace("/[^-_0-9a-zA-Z]/","",$pass);
		}

	$session_name = preg_replace("/\'|\"|\\\\|;/","",$session_name);
	$server_ip = preg_replace("/\'|\"|\\\\|;/","",$server_ip);

	$auth=0;
	$auth_message = user_authorization($user,$pass,'',0,0,0);
	if ($auth_message == 'GOOD')
		{$auth=1;}

	$stmt="SELECT count(*) from vicidial_live_agents where user='$user';";
	if ($DB) {echo "|$stmt|\n";}
	$rslt=mysql_to_mysqli($stmt, $link);
	$row=mysqli_fetch_row($rslt);
	$authlive=$row[0];

	if( (strlen($user)<2) or (strlen($pass)<2) or ($auth==0) or ($authlive==0))
		{
		echo _QXZ("Invalid Username/Password:")." |$user|$pass|$auth|$authlive|$auth_message|\n";
		exit;
		}

	if ( (strlen($lead_id) > 0) and (strlen($new_list_id) > 2) )
		{
		$search_count=0;
		$stmt = "SELECT count(*) FROM vicidial_list where lead_id='$lead_id' and list_id!='$new_list_id';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {echo "$stmt\n";}
		$sc_ct = mysqli_num_rows($rslt);
		if ($sc_ct > 0)
			{
			$row=mysqli_fetch_row($rslt);
			$search_count = $row[0];
			}

		if ($search_count > 0)
			{
			$reset_dialedSQL='';
			if ($reset_dialed=='Y') {$reset_dialedSQL=", called_since_last_reset='N'";}
			$stmt="UPDATE vicidial_list SET list_id='$new_list_id' $reset_dialedSQL where lead_id='$lead_id' limit 1;";
			if ($DB) {echo "$stmt\n";}
			$rslt=mysql_to_mysqli($stmt, $link);
			$affected_rows = mysqli_affected_rows($link);

			$campaign_idSQL='';
			$stmtA = "SELECT campaign_id FROM vicidial_lists where list_id='$new_list_id';";
			$rslt=mysql_to_mysqli($stmtA, $link);
			if ($DB) {echo "$stmtA\n";}
			$vlc_ct = mysqli_num_rows($rslt);
			if ($vlc_ct > 0)
				{
				$row=mysqli_fetch_row($rslt);
				$campaign_idSQL = ",campaign_id='$row[0]'";
				}

			$stmtB="UPDATE vicidial_callbacks SET list_id='$new_list_id' $campaign_idSQL where lead_id='$lead_id' limit 1;";
			if ($DB) {echo "$stmtB\n";}
			$rslt=mysql_to_mysqli($stmtB, $link);
			$CBaffected_rows = mysqli_affected_rows($link);

			$SQL_log = "$stmt|$stmtB|$CBaffected_rows|";
			$SQL_log = preg_replace('/;/','',$SQL_log);
			$SQL_log = addslashes($SQL_log);
			$stmt="INSERT INTO vicidial_api_log set user='$user',agent_user='$user',function='deactivate_lead',value='$lead_id',result='$affected_rows',result_reason='$lead_id',source='vdc',data='$SQL_log',api_date='$NOW_TIME',api_script='$api_script';";
			$rslt=mysql_to_mysqli($stmt, $link);

			$MESSAGE = _QXZ("DONE: %1s match found, %2s updated to %3s with %4s status",0,'',$search_count,$affected_rows,$new_list_id,$dispo);
			echo "$MESSAGE\n";
			}
		else
			{
			$MESSAGE = _QXZ("DONE: no match found within %1s     %2s",0,'',$lead_id,$new_list_id);
			echo "$MESSAGE\n";
			}
		}
	else
		{
		$MESSAGE = _QXZ("DONE: %1s is empty for lead %2s",0,'',$search_field,$lead_id);
		echo "$MESSAGE\n";
		}
	}
else
	{
	$MESSAGE = _QXZ("DONE: dispo is not a sale status: %1s",0,'',$dispo);
	echo "$MESSAGE\n";
	}

if ($log_to_file > 0)
	{
	$fp = fopen ("./dispo_move_list.txt", "a");
	fwrite ($fp, "$NOW_TIME|$k|$lead_id|$new_list_id|$sale_status|$dispo|$user|XXXX|$DB|$log_to_file|$MESSAGE|\n");
	fclose($fp);
	}
