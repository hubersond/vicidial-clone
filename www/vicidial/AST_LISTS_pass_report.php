<?php 
# AST_LISTS_pass_report.php
# 
# Copyright (C) 2014  Matt Florell <vicidial@gmail.com>    LICENSE: AGPLv2
#
# This is a list inventory report, not a calling report. This report will show
# statistics for all of the lists in the selected campaigns
#
# CHANGES
# 140116-0839 - First build based upon AST_LISTS_campaign_stats.php
# 140121-0707 - Fixed small issue in List select mode
# 140331-2122 - Converted division calculations to use MathZDC function, added HTML view
# 141114-0827 - Finalized adding QXZ translation to all admin files
# 141230-0919 - Added code for on-the-fly language translations display
#

$startMS = microtime();

header ("Content-type: text/html; charset=utf-8");

require("dbconnect_mysqli.php");
require("functions.php");

$PHP_AUTH_USER=$_SERVER['PHP_AUTH_USER'];
$PHP_AUTH_PW=$_SERVER['PHP_AUTH_PW'];
$PHP_SELF=$_SERVER['PHP_SELF'];
if (isset($_GET["group"]))				{$group=$_GET["group"];}
	elseif (isset($_POST["group"]))		{$group=$_POST["group"];}
if (isset($_GET["DB"]))					{$DB=$_GET["DB"];}
	elseif (isset($_POST["DB"]))		{$DB=$_POST["DB"];}
if (isset($_GET["submit"]))				{$submit=$_GET["submit"];}
	elseif (isset($_POST["submit"]))	{$submit=$_POST["submit"];}
if (isset($_GET["SUBMIT"]))				{$SUBMIT=$_GET["SUBMIT"];}
	elseif (isset($_POST["SUBMIT"]))	{$SUBMIT=$_POST["SUBMIT"];}
if (isset($_GET["file_download"]))				{$file_download=$_GET["file_download"];}
	elseif (isset($_POST["file_download"]))	{$file_download=$_POST["file_download"];}
if (isset($_GET["report_display_type"]))				{$report_display_type=$_GET["report_display_type"];}
	elseif (isset($_POST["report_display_type"]))	{$report_display_type=$_POST["report_display_type"];}
if (isset($_GET["use_lists"]))			{$use_lists=$_GET["use_lists"];}
	elseif (isset($_POST["use_lists"]))	{$use_lists=$_POST["use_lists"];}


$report_name = 'Lists Pass Report';
$db_source = 'M';
$JS_text="<script language='Javascript'>\n";
$JS_onload="onload = function() {\n";

#############################################
##### START SYSTEM_SETTINGS LOOKUP #####
$stmt = "SELECT use_non_latin,outbound_autodial_active,slave_db_server,reports_use_slave_db,enable_languages,language_method FROM system_settings;";
$rslt=mysql_to_mysqli($stmt, $link);
if ($DB) {$MAIN.="$stmt\n";}
$qm_conf_ct = mysqli_num_rows($rslt);
if ($qm_conf_ct > 0)
	{
	$row=mysqli_fetch_row($rslt);
	$non_latin =					$row[0];
	$outbound_autodial_active =		$row[1];
	$slave_db_server =				$row[2];
	$reports_use_slave_db =			$row[3];
	$SSenable_languages =			$row[4];
	$SSlanguage_method =			$row[5];
	}
##### END SETTINGS LOOKUP #####
###########################################

if ($non_latin < 1)
	{
	$PHP_AUTH_USER = preg_replace('/[^-_0-9a-zA-Z]/', '', $PHP_AUTH_USER);
	$PHP_AUTH_PW = preg_replace('/[^-_0-9a-zA-Z]/', '', $PHP_AUTH_PW);
	}
else
	{
	$PHP_AUTH_PW = preg_replace("/'|\"|\\\\|;/","",$PHP_AUTH_PW);
	$PHP_AUTH_USER = preg_replace("/'|\"|\\\\|;/","",$PHP_AUTH_USER);
	}

$stmt="SELECT selected_language from vicidial_users where user='$PHP_AUTH_USER';";
if ($DB) {echo "|$stmt|\n";}
$rslt=mysql_to_mysqli($stmt, $link);
$sl_ct = mysqli_num_rows($rslt);
if ($sl_ct > 0)
	{
	$row=mysqli_fetch_row($rslt);
	$VUselected_language =		$row[0];
	}

$auth=0;
$reports_auth=0;
$admin_auth=0;
$auth_message = user_authorization($PHP_AUTH_USER,$PHP_AUTH_PW,'REPORTS',1);
if ($auth_message == 'GOOD')
	{$auth=1;}

if ($auth > 0)
	{
	$stmt="SELECT count(*) from vicidial_users where user='$PHP_AUTH_USER' and user_level > 7 and view_reports > 0;";
	if ($DB) {echo "|$stmt|\n";}
	$rslt=mysql_to_mysqli($stmt, $link);
	$row=mysqli_fetch_row($rslt);
	$admin_auth=$row[0];

	$stmt="SELECT count(*) from vicidial_users where user='$PHP_AUTH_USER' and user_level > 6 and view_reports > 0;";
	if ($DB) {echo "|$stmt|\n";}
	$rslt=mysql_to_mysqli($stmt, $link);
	$row=mysqli_fetch_row($rslt);
	$reports_auth=$row[0];

	if ($reports_auth < 1)
		{
		$VDdisplayMESSAGE = _QXZ("You are not allowed to view reports");
		Header ("Content-type: text/html; charset=utf-8");
		echo "$VDdisplayMESSAGE: |$PHP_AUTH_USER|$auth_message|\n";
		exit;
		}
	if ( ($reports_auth > 0) and ($admin_auth < 1) )
		{
		$ADD=999999;
		$reports_only_user=1;
		}
	}
else
	{
	$VDdisplayMESSAGE = _QXZ("Login incorrect, please try again");
	if ($auth_message == 'LOCK')
		{
		$VDdisplayMESSAGE = _QXZ("Too many login attempts, try again in 15 minutes");
		Header ("Content-type: text/html; charset=utf-8");
		echo "$VDdisplayMESSAGE: |$PHP_AUTH_USER|$auth_message|\n";
		exit;
		}
	Header("WWW-Authenticate: Basic realm=\"CONTACT-CENTER-ADMIN\"");
	Header("HTTP/1.0 401 Unauthorized");
	echo "$VDdisplayMESSAGE: |$PHP_AUTH_USER|$PHP_AUTH_PW|$auth_message|\n";
	exit;
	}


##### BEGIN log visit to the vicidial_report_log table #####
$LOGip = getenv("REMOTE_ADDR");
$LOGbrowser = getenv("HTTP_USER_AGENT");
$LOGscript_name = getenv("SCRIPT_NAME");
$LOGserver_name = getenv("SERVER_NAME");
$LOGserver_port = getenv("SERVER_PORT");
$LOGrequest_uri = getenv("REQUEST_URI");
$LOGhttp_referer = getenv("HTTP_REFERER");
if (preg_match("/443/i",$LOGserver_port)) {$HTTPprotocol = 'https://';}
  else {$HTTPprotocol = 'http://';}
if (($LOGserver_port == '80') or ($LOGserver_port == '443') ) {$LOGserver_port='';}
else {$LOGserver_port = ":$LOGserver_port";}
$LOGfull_url = "$HTTPprotocol$LOGserver_name$LOGserver_port$LOGrequest_uri";

$LOGhostname = php_uname('n');
if (strlen($LOGhostname)<1) {$LOGhostname='X';}
if (strlen($LOGserver_name)<1) {$LOGserver_name='X';}

$stmt="SELECT webserver_id FROM vicidial_webservers where webserver='$LOGserver_name' and hostname='$LOGhostname' LIMIT 1;";
$rslt=mysql_to_mysqli($stmt, $link);
if ($DB) {echo "$stmt\n";}
$webserver_id_ct = mysqli_num_rows($rslt);
if ($webserver_id_ct > 0)
	{
	$row=mysqli_fetch_row($rslt);
	$webserver_id = $row[0];
	}
else
	{
	##### insert webserver entry
	$stmt="INSERT INTO vicidial_webservers (webserver,hostname) values('$LOGserver_name','$LOGhostname');";
	if ($DB) {echo "$stmt\n";}
	$rslt=mysql_to_mysqli($stmt, $link);
	$affected_rows = mysqli_affected_rows($link);
	$webserver_id = mysqli_insert_id($link);
	}

$stmt="INSERT INTO vicidial_report_log set event_date=NOW(), user='$PHP_AUTH_USER', ip_address='$LOGip', report_name='$report_name', browser='$LOGbrowser', referer='$LOGhttp_referer', notes='$LOGserver_name:$LOGserver_port $LOGscript_name |$group[0], $query_date, $end_date, $shift, $file_download, $report_display_type|', url='$LOGfull_url', webserver='$webserver_id';";
if ($DB) {echo "|$stmt|\n";}
$rslt=mysql_to_mysqli($stmt, $link);
$report_log_id = mysqli_insert_id($link);
##### END log visit to the vicidial_report_log table #####

if ( (strlen($slave_db_server)>5) and (preg_match("/$report_name/",$reports_use_slave_db)) )
	{
	mysqli_close($link);
	$use_slave_server=1;
	$db_source = 'S';
	require("dbconnect_mysqli.php");
	$MAIN.="<!-- Using slave server $slave_db_server $db_source -->\n";
	}

$stmt="SELECT user_group from vicidial_users where user='$PHP_AUTH_USER';";
if ($DB) {$MAIN.="|$stmt|\n";}
$rslt=mysql_to_mysqli($stmt, $link);
$row=mysqli_fetch_row($rslt);
$LOGuser_group =			$row[0];

$stmt="SELECT allowed_campaigns,allowed_reports from vicidial_user_groups where user_group='$LOGuser_group';";
if ($DB) {$MAIN.="|$stmt|\n";}
$rslt=mysql_to_mysqli($stmt, $link);
$row=mysqli_fetch_row($rslt);
$LOGallowed_campaigns = $row[0];
$LOGallowed_reports =	$row[1];

if ( (!preg_match("/$report_name/",$LOGallowed_reports)) and (!preg_match("/ALL REPORTS/",$LOGallowed_reports)) )
	{
    Header("WWW-Authenticate: Basic realm=\"CONTACT-CENTER-ADMIN\"");
    Header("HTTP/1.0 401 Unauthorized");
    echo _QXZ("You are not allowed to view this report").": |$PHP_AUTH_USER|$report_name|\n";
    exit;
	}

$NOW_DATE = date("Y-m-d");
$NOW_TIME = date("Y-m-d H:i:s");
$STARTtime = date("U");
if (!isset($group)) {$group = '';}

$i=0;
$group_string='|';
$group_ct = count($group);
while($i < $group_ct)
	{
	$group_string .= "$group[$i]|";
	$i++;
	}

$LOGallowed_campaignsSQL='';
$whereLOGallowed_campaignsSQL='';
if ( (!preg_match('/\-ALL/i', $LOGallowed_campaigns)) )
	{
	$rawLOGallowed_campaignsSQL = preg_replace("/ -/",'',$LOGallowed_campaigns);
	$rawLOGallowed_campaignsSQL = preg_replace("/ /","','",$rawLOGallowed_campaignsSQL);
	$LOGallowed_campaignsSQL = "and campaign_id IN('$rawLOGallowed_campaignsSQL')";
	$whereLOGallowed_campaignsSQL = "where campaign_id IN('$rawLOGallowed_campaignsSQL')";
	}
$regexLOGallowed_campaigns = " $LOGallowed_campaigns ";

if ($use_lists < 1)
	{
	$stmt="select campaign_id,campaign_name from vicidial_campaigns $whereLOGallowed_campaignsSQL order by campaign_id;";
	$rslt=mysql_to_mysqli($stmt, $link);
	if ($DB) {$MAIN.="$stmt\n";}
	$campaigns_to_print = mysqli_num_rows($rslt);
	$i=0;
	while ($i < $campaigns_to_print)
		{
		$row=mysqli_fetch_row($rslt);
		$groups[$i] =		$row[0];
		$group_names[$i] =	$row[1];
		if (preg_match('/\-ALL/',$group_string) )
			{$group[$i] = $groups[$i];}
		$i++;
		}
	}
else
	{
	$stmt="select list_id,list_name from vicidial_lists $whereLOGallowed_campaignsSQL order by list_id;";
	$rslt=mysql_to_mysqli($stmt, $link);
	if ($DB) {$MAIN.="$stmt\n";}
	$campaigns_to_print = mysqli_num_rows($rslt);
	$i=0;
	while ($i < $campaigns_to_print)
		{
		$row=mysqli_fetch_row($rslt);
		$groups[$i] =		$row[0];
		$group_names[$i] =	$row[1];
		if (preg_match('/\-ALL/',$group_string) )
			{$group[$i] = $groups[$i];}
		$i++;
		}
	}

$rollover_groups_count=0;
$i=0;
$group_string='|';
$group_ct = count($group);
while($i < $group_ct)
	{
	if ( (preg_match("/ $group[$i] /",$regexLOGallowed_campaigns)) or (preg_match("/-ALL/",$LOGallowed_campaigns)) )
		{
		$group_string .= "$group[$i]|";
		$group_SQL .= "'$group[$i]',";
		$groupQS .= "&group[]=$group[$i]";
		}
	$i++;
	}

if ($use_lists < 1)
	{
	if ( (preg_match('/\-\-ALL\-\-/',$group_string) ) or ($group_ct < 1) or (strlen($group_string) < 2) )
		{
		$group_SQL = "$LOGallowed_campaignsSQL";
		}
	else
		{
		$group_SQL = preg_replace('/,$/i', '',$group_SQL);
		$group_SQLand = "and campaign_id IN($group_SQL)";
		$group_SQL = "where campaign_id IN($group_SQL)";
		}
	}
else
	{
	if ( (preg_match('/\-\-ALL\-\-/',$group_string) ) or ($group_ct < 1) or (strlen($group_string) < 2) )
		{
		$group_SQL = "where list_id IN($group_SQL)";
		}
	else
		{
		$group_SQL = preg_replace('/,$/i', '',$group_SQL);
		$group_SQLand = "and list_id IN($group_SQL)";
		$group_SQL = "where list_id IN($group_SQL)";
		}

	}

# Get lists to query to avoid using a nested query
$lists_id_str="";
$list_stmt="SELECT list_id from vicidial_lists where active IN('Y','N') $group_SQLand";
$list_rslt=mysql_to_mysqli($list_stmt, $link);
while ($lrow=mysqli_fetch_row($list_rslt)) 
	{
	$lists_id_str.="'$lrow[0]',";
	}
$lists_id_str=substr($lists_id_str,0,-1);


### BEGIN gather all statuses that are in status flags  ###
$human_answered_statuses='';
$sale_statuses='';
$dnc_statuses='';
$customer_contact_statuses='';
$not_interested_statuses='';
$unworkable_statuses='';
$stmt="select status,human_answered,sale,dnc,customer_contact,not_interested,unworkable,scheduled_callback,completed,status_name from vicidial_statuses;";
$rslt=mysql_to_mysqli($stmt, $link);
if ($DB) {$MAIN.="$stmt\n";}
$statha_to_print = mysqli_num_rows($rslt);
$i=0;
while ($i < $statha_to_print)
	{
	$row=mysqli_fetch_row($rslt);
	$temp_status = $row[0];
	$statname_list["$temp_status"] = "$row[9]";
	if ($row[1]=='Y') {$human_answered_statuses .= "'$temp_status',";}
	if ($row[2]=='Y') {$sale_statuses .= "'$temp_status',";}
	if ($row[3]=='Y') {$dnc_statuses .= "'$temp_status',";}
	if ($row[4]=='Y') {$customer_contact_statuses .= "'$temp_status',";}
	if ($row[5]=='Y') {$not_interested_statuses .= "'$temp_status',";}
	if ($row[6]=='Y') {$unworkable_statuses .= "'$temp_status',";}
	if ($row[7]=='Y') {$scheduled_callback_statuses .= "'$temp_status',";}
	if ($row[8]=='Y') {$completed_statuses .= "'$temp_status',";}
	$i++;
	}
$stmt="select status,human_answered,sale,dnc,customer_contact,not_interested,unworkable,scheduled_callback,completed,status_name from vicidial_campaign_statuses where selectable IN('Y','N');";
$rslt=mysql_to_mysqli($stmt, $link);
if ($DB) {$MAIN.="$stmt\n";}
$statha_to_print = mysqli_num_rows($rslt);
$i=0;
while ($i < $statha_to_print)
	{
	$row=mysqli_fetch_row($rslt);
	$temp_status = $row[0];
	$statname_list["$temp_status"] = "$row[9]";
	if ( ($row[1]=='Y') and (!preg_match("/'$temp_status'/",$human_answered_statuses)) ) {$human_answered_statuses .= "'$temp_status',";}
	if ($row[2]=='Y') {$sale_statuses .= "'$temp_status',";}
	if ($row[3]=='Y') {$dnc_statuses .= "'$temp_status',";}
	if ($row[4]=='Y') {$customer_contact_statuses .= "'$temp_status',";}
	if ($row[5]=='Y') {$not_interested_statuses .= "'$temp_status',";}
	if ($row[6]=='Y') {$unworkable_statuses .= "'$temp_status',";}
	if ($row[7]=='Y') {$scheduled_callback_statuses .= "'$temp_status',";}
	if ($row[8]=='Y') {$completed_statuses .= "'$temp_status',";}
	$i++;
	}
if (strlen($human_answered_statuses)>2)		{$human_answered_statuses = substr("$human_answered_statuses", 0, -1);}
else {$human_answered_statuses="''";}
if (strlen($sale_statuses)>2)				{$sale_statuses = substr("$sale_statuses", 0, -1);}
else {$sale_statuses="''";}
if (strlen($dnc_statuses)>2)				{$dnc_statuses = substr("$dnc_statuses", 0, -1);}
else {$dnc_statuses="''";}
if (strlen($customer_contact_statuses)>2)	{$customer_contact_statuses = substr("$customer_contact_statuses", 0, -1);}
else {$customer_contact_statuses="''";}
if (strlen($not_interested_statuses)>2)		{$not_interested_statuses = substr("$not_interested_statuses", 0, -1);}
else {$not_interested_statuses="''";}
if (strlen($unworkable_statuses)>2)			{$unworkable_statuses = substr("$unworkable_statuses", 0, -1);}
else {$unworkable_statuses="''";}
if (strlen($scheduled_callback_statuses)>2)			{$scheduled_callback_statuses = substr("$scheduled_callback_statuses", 0, -1);}
else {$scheduled_callback_statuses="''";}
if (strlen($completed_statuses)>2)			{$completed_statuses = substr("$completed_statuses", 0, -1);}
else {$completed_statuses="''";}

if ($DB) {echo "<!-- SALE statuses: $sale_statuses -->";}




$HEADER.="<HTML>\n";
$HEADER.="<HEAD>\n";
$HEADER.="<STYLE type=\"text/css\">\n";
$HEADER.="<!--\n";
$HEADER.="   .green {color: white; background-color: green}\n";
$HEADER.="   .red {color: white; background-color: red}\n";
$HEADER.="   .blue {color: white; background-color: blue}\n";
$HEADER.="   .purple {color: white; background-color: purple}\n";
$HEADER.="-->\n";
$HEADER.=" </STYLE>\n";
$HEADER.="<link rel=\"stylesheet\" href=\"horizontalbargraph.css\">\n";

$HEADER.="<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=utf-8\">\n";
$HEADER.="<TITLE>"._QXZ("$report_name")."</TITLE></HEAD><BODY BGCOLOR=WHITE marginheight=0 marginwidth=0 leftmargin=0 topmargin=0>\n";

$short_header=1;

$MAIN.="<TABLE CELLPADDING=4 CELLSPACING=0><TR><TD>";

$MAIN.="<FORM ACTION=\"$PHP_SELF\" METHOD=GET name=vicidial_report id=vicidial_report>\n";
$MAIN.="<TABLE CELLSPACING=3><TR><TD VALIGN=TOP>";
$MAIN.="<INPUT TYPE=HIDDEN NAME=DB VALUE=\"$DB\">\n";
$MAIN.="<INPUT TYPE=HIDDEN NAME=use_lists VALUE=\"$use_lists\">\n";

if ($use_lists > 0)
	{
	$MAIN.="</TD><TD VALIGN=TOP> "._QXZ("Lists").":<BR>";
	$MAIN.="<SELECT SIZE=5 NAME=group[] multiple>\n";
	if  (preg_match('/\-\-ALL\-\-/',$group_string))
		{$MAIN.="<option value=\"--ALL--\" selected>-- "._QXZ("ALL LISTS")." --</option>\n";}
	else
		{$MAIN.="<option value=\"--ALL--\">-- "._QXZ("ALL LISTS")." --</option>\n";}
	$o=0;
	while ($campaigns_to_print > $o)
		{
		if (preg_match("/$groups[$o]\|/i",$group_string)) {$MAIN.="<option selected value=\"$groups[$o]\">$groups[$o] - $group_names[$o]</option>\n";}
		  else {$MAIN.="<option value=\"$groups[$o]\">$groups[$o] - $group_names[$o]</option>\n";}
		$o++;
		}
	$MAIN.="</SELECT>\n<BR>\n";
	$MAIN.="<a href=\"$PHP_SELF?use_lists=0&DB=$DB\">"._QXZ("SWITCH TO CAMPAIGNS")."</a>";
	}
else
	{
	$MAIN.="</TD><TD VALIGN=TOP> "._QXZ("Campaigns").":<BR>";
	$MAIN.="<SELECT SIZE=5 NAME=group[] multiple>\n";
	if  (preg_match('/\-\-ALL\-\-/',$group_string))
		{$MAIN.="<option value=\"--ALL--\" selected>-- "._QXZ("ALL CAMPAIGNS")." --</option>\n";}
	else
		{$MAIN.="<option value=\"--ALL--\">-- "._QXZ("ALL CAMPAIGNS")." --</option>\n";}
	$o=0;
	while ($campaigns_to_print > $o)
		{
		if (preg_match("/$groups[$o]\|/i",$group_string)) {$MAIN.="<option selected value=\"$groups[$o]\">$groups[$o] - $group_names[$o]</option>\n";}
		  else {$MAIN.="<option value=\"$groups[$o]\">$groups[$o] - $group_names[$o]</option>\n";}
		$o++;
		}
	$MAIN.="</SELECT>\n<BR>\n";
	$MAIN.="<a href=\"$PHP_SELF?use_lists=1&DB=$DB\">"._QXZ("SWITCH TO LISTS")."</a>";
	}
$MAIN.="</TD><TD VALIGN=TOP>";
$MAIN.=_QXZ("Display as").":<BR/>";
$MAIN.="<select name='report_display_type'>";
if ($report_display_type) {$MAIN.="<option value='$report_display_type' selected>$report_display_type</option>";}
$MAIN.="<option value='TEXT'>"._QXZ("TEXT")."</option><option value='HTML'>"._QXZ("HTML")."</option></select>&nbsp; ";
$MAIN.="<BR><BR>\n";
$MAIN.="<INPUT type=submit NAME=SUBMIT VALUE='"._QXZ("SUBMIT")."'>\n";
$MAIN.="</TD><TD VALIGN=TOP> &nbsp; &nbsp; &nbsp; &nbsp; ";
$MAIN.="<FONT FACE=\"ARIAL,HELVETICA\" COLOR=BLACK SIZE=2>";
if ($use_lists > 0)
	{
	if (strlen($group[0]) > 1)
		{
		$MAIN.=" <a href=\"./admin.php?ADD=311&list_id=$group[0]\">"._QXZ("MODIFY")."</a> | \n";
		$MAIN.=" <a href=\"./admin.php?ADD=999999\">"._QXZ("REPORTS")."</a> </FONT>\n";
		}
	else
		{
		$MAIN.=" <a href=\"./admin.php?ADD=100\">"._QXZ("LISTS")."</a> | \n";
		$MAIN.=" <a href=\"./admin.php?ADD=999999\">"._QXZ("REPORTS")."</a> </FONT>\n";
		}
	}
else
	{
	if (strlen($group[0]) > 1)
		{
		$MAIN.=" <a href=\"./admin.php?ADD=34&campaign_id=$group[0]\">"._QXZ("MODIFY")."</a> | \n";
		$MAIN.=" <a href=\"./admin.php?ADD=999999\">"._QXZ("REPORTS")."</a> </FONT>\n";
		}
	else
		{
		$MAIN.=" <a href=\"./admin.php?ADD=10\">"._QXZ("CAMPAIGNS")."</a> | \n";
		$MAIN.=" <a href=\"./admin.php?ADD=999999\">"._QXZ("REPORTS")."</a> </FONT>\n";
		}
	}
$MAIN.="</TD></TR></TABLE>";
$MAIN.="</FORM>\n\n";

$MAIN.="<PRE><FONT SIZE=2>\n\n";


if (strlen($group[0]) < 1)
	{
	$MAIN.="\n\n";
	$MAIN.=_QXZ("PLEASE SELECT A CAMPAIGN AND DATE ABOVE AND CLICK SUBMIT")."\n";
	}

else
	{
	$OUToutput = '';
	$OUToutput .= _QXZ("Lists Pass Report",45)." $NOW_TIME\n";

	$OUToutput .= "\n";

	##############################
	#########  LIST ID BREAKDOWN STATS

	$TOTALleads = 0;

	$OUToutput .= "\n";
	$OUToutput .= "---------- "._QXZ("LIST ID SUMMARY",19)." <a href=\"$PHP_SELF?DB=$DB$groupQS&SUBMIT=$SUBMIT&file_download=1\">"._QXZ("DOWNLOAD")."</a>\n";

	$OUToutput .= "+------------+------------------------------------------+----------+------------+----------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "\n";

	$OUToutput .= "|   "._QXZ("FIRST",8)." |                                          |          | "._QXZ("LEAD",10)." |          |";
	$OUToutput .= _QXZ("CONTACTS",9,"r")."|"._QXZ("CONTACTS",9,"r")."|"._QXZ("CONTACTS",9,"r")."|"._QXZ("CONTACTS",9,"r")."|"._QXZ("CONTACTS",9,"r")."|"._QXZ("CONTACTS",9,"r")."|";
	$OUToutput .= _QXZ("CNT RATE",9,"r")."|"._QXZ("CNT RATE",9,"r")."|"._QXZ("CNT RATE",9,"r")."|"._QXZ("CNT RATE",9,"r")."|"._QXZ("CNT RATE",9,"r")."|"._QXZ("CNT RATE",9,"r")."|";
	$OUToutput .= _QXZ("SALES",8,"r")." |"._QXZ("SALES",8,"r")." |"._QXZ("SALES",8,"r")." |"._QXZ("SALES",8,"r")." |"._QXZ("SALES",8,"r")." |"._QXZ("SALES",8,"r")." |";
	$OUToutput .= _QXZ("CONV RATE",9,"r")."|"._QXZ("CONV RATE",9,"r")."|"._QXZ("CONV RATE",9,"r")."|"._QXZ("CONV RATE",9,"r")."|"._QXZ("CONV RATE",9,"r")."|"._QXZ("CONV RATE",9,"r")."|";
	$OUToutput .= _QXZ("  DNC",8)." | "._QXZ(" DNC",7)." | "._QXZ(" DNC",7)." | "._QXZ(" DNC",7)." | "._QXZ(" DNC",7)." | "._QXZ(" DNC",7)." |";
	$OUToutput .= _QXZ("DNC RATE",9,"r")."|"._QXZ("DNC RATE",9,"r")."|"._QXZ("DNC RATE",9,"r")."|"._QXZ("DNC RATE",9,"r")."|"._QXZ("DNC RATE",9,"r")."|"._QXZ("DNC RATE",9,"r")."|";
	$OUToutput .= _QXZ("CUST CONT",9,"r")."|"._QXZ("CUST CONT",9,"r")."|"._QXZ("CUST CONT",9,"r")."|"._QXZ("CUST CONT",9,"r")."|"._QXZ("CUST CONT",9,"r")."|"._QXZ("CUST CONT",9,"r")."|";
	$OUToutput .= _QXZ("CUCT RATE",9,"r")."|"._QXZ("CUCT RATE",9,"r")."|"._QXZ("CUCT RATE",9,"r")."|"._QXZ("CUCT RATE",9,"r")."|"._QXZ("CUCT RATE",9,"r")."|"._QXZ("CUCT RATE",9,"r")."|";
	$OUToutput .= _QXZ("UNWORKABL",9,"r")."|"._QXZ("UNWORKABL",9,"r")."|"._QXZ("UNWORKABL",9,"r")."|"._QXZ("UNWORKABL",9,"r")."|"._QXZ("UNWORKABL",9,"r")."|"._QXZ("UNWORKABL",9,"r")."|";
	$OUToutput .= _QXZ("UNWK RATE",9,"r")."|"._QXZ("UNWK RATE",9,"r")."|"._QXZ("UNWK RATE",9,"r")."|"._QXZ("UNWK RATE",9,"r")."|"._QXZ("UNWK RATE",9,"r")."|"._QXZ("UNWK RATE",9,"r")."|";
	$OUToutput .= _QXZ("SCHEDL CB",9,"r")."|"._QXZ("SCHEDL CB",9,"r")."|"._QXZ("SCHEDL CB",9,"r")."|"._QXZ("SCHEDL CB",9,"r")."|"._QXZ("SCHEDL CB",9,"r")."|"._QXZ("SCHEDL CB",9,"r")."|";
	$OUToutput .= _QXZ("SHCB RATE",9,"r")."|"._QXZ("SHCB RATE",9,"r")."|"._QXZ("SHCB RATE",9,"r")."|"._QXZ("SHCB RATE",9,"r")."|"._QXZ("SHCB RATE",9,"r")."|"._QXZ("SHCB RATE",9,"r")."|";
	$OUToutput .= _QXZ("COMPLETED",9,"r")."|"._QXZ("COMPLETED",9,"r")."|"._QXZ("COMPLETED",9,"r")."|"._QXZ("COMPLETED",9,"r")."|"._QXZ("COMPLETED",9,"r")."|"._QXZ("COMPLETED",9,"r")."|";
	$OUToutput .= _QXZ("COMP RATE",9,"r")."|"._QXZ("COMP RATE",9,"r")."|"._QXZ("COMP RATE",9,"r")."|"._QXZ("COMP RATE",9,"r")."|"._QXZ("COMP RATE",9,"r")."|"._QXZ("COMP RATE",9,"r")."|";
	$OUToutput .= "\n";

	$OUToutput .= "| "._QXZ("LOAD DATE",10,"r")." | "._QXZ("LIST ID and NAME",40)." | "._QXZ("CAMPAIGN",8)." | "._QXZ("COUNT",10)." | "._QXZ("ACTIVE",8)." |";
	$OUToutput .= " "._QXZ("1st PASS",8,"r")."| "._QXZ("2nd PASS",8,"r")."| "._QXZ("3rd PASS",8,"r")."| "._QXZ("4th PASS",8,"r")."| "._QXZ("5th PASS",8,"r")."| "._QXZ("LIFE",7,"r")." |";
	$OUToutput .= " "._QXZ("1st PASS",8,"r")."| "._QXZ("2nd PASS",8,"r")."| "._QXZ("3rd PASS",8,"r")."| "._QXZ("4th PASS",8,"r")."| "._QXZ("5th PASS",8,"r")."| "._QXZ("LIFE",7,"r")." |";
	$OUToutput .= " "._QXZ("1st PASS",8,"r")."| "._QXZ("2nd PASS",8,"r")."| "._QXZ("3rd PASS",8,"r")."| "._QXZ("4th PASS",8,"r")."| "._QXZ("5th PASS",8,"r")."| "._QXZ("LIFE",7,"r")." |";
	$OUToutput .= " "._QXZ("1st PASS",8,"r")."| "._QXZ("2nd PASS",8,"r")."| "._QXZ("3rd PASS",8,"r")."| "._QXZ("4th PASS",8,"r")."| "._QXZ("5th PASS",8,"r")."| "._QXZ("LIFE",7,"r")." |";
	$OUToutput .= " "._QXZ("1st PASS",8,"r")."| "._QXZ("2nd PASS",8,"r")."| "._QXZ("3rd PASS",8,"r")."| "._QXZ("4th PASS",8,"r")."| "._QXZ("5th PASS",8,"r")."| "._QXZ("LIFE",7,"r")." |";
	$OUToutput .= " "._QXZ("1st PASS",8,"r")."| "._QXZ("2nd PASS",8,"r")."| "._QXZ("3rd PASS",8,"r")."| "._QXZ("4th PASS",8,"r")."| "._QXZ("5th PASS",8,"r")."| "._QXZ("LIFE",7,"r")." |";
	$OUToutput .= " "._QXZ("1st PASS",8,"r")."| "._QXZ("2nd PASS",8,"r")."| "._QXZ("3rd PASS",8,"r")."| "._QXZ("4th PASS",8,"r")."| "._QXZ("5th PASS",8,"r")."| "._QXZ("LIFE",7,"r")." |";
	$OUToutput .= " "._QXZ("1st PASS",8,"r")."| "._QXZ("2nd PASS",8,"r")."| "._QXZ("3rd PASS",8,"r")."| "._QXZ("4th PASS",8,"r")."| "._QXZ("5th PASS",8,"r")."| "._QXZ("LIFE",7,"r")." |";
	$OUToutput .= " "._QXZ("1st PASS",8,"r")."| "._QXZ("2nd PASS",8,"r")."| "._QXZ("3rd PASS",8,"r")."| "._QXZ("4th PASS",8,"r")."| "._QXZ("5th PASS",8,"r")."| "._QXZ("LIFE",7,"r")." |";
	$OUToutput .= " "._QXZ("1st PASS",8,"r")."| "._QXZ("2nd PASS",8,"r")."| "._QXZ("3rd PASS",8,"r")."| "._QXZ("4th PASS",8,"r")."| "._QXZ("5th PASS",8,"r")."| "._QXZ("LIFE",7,"r")." |";
	$OUToutput .= " "._QXZ("1st PASS",8,"r")."| "._QXZ("2nd PASS",8,"r")."| "._QXZ("3rd PASS",8,"r")."| "._QXZ("4th PASS",8,"r")."| "._QXZ("5th PASS",8,"r")."| "._QXZ("LIFE",7,"r")." |";
	$OUToutput .= " "._QXZ("1st PASS",8,"r")."| "._QXZ("2nd PASS",8,"r")."| "._QXZ("3rd PASS",8,"r")."| "._QXZ("4th PASS",8,"r")."| "._QXZ("5th PASS",8,"r")."| "._QXZ("LIFE",7,"r")." |";
	$OUToutput .= " "._QXZ("1st PASS",8,"r")."| "._QXZ("2nd PASS",8,"r")."| "._QXZ("3rd PASS",8,"r")."| "._QXZ("4th PASS",8,"r")."| "._QXZ("5th PASS",8,"r")."| "._QXZ("LIFE",7,"r")." |";
	$OUToutput .= " "._QXZ("1st PASS",8,"r")."| "._QXZ("2nd PASS",8,"r")."| "._QXZ("3rd PASS",8,"r")."| "._QXZ("4th PASS",8,"r")."| "._QXZ("5th PASS",8,"r")."| "._QXZ("LIFE",7,"r")." |";
	$OUToutput .= "\n";

	$OUToutput .= "+------------+------------------------------------------+----------+------------+----------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "\n";


	$CSV_text1.="\""._QXZ("LIST ID SUMMARY")."\"\n";
	$CSV_text1.="\""._QXZ("FIRST LOAD DATE")."\",\""._QXZ("LIST")."\",\""._QXZ("CAMPAIGN")."\",\""._QXZ("LEADS")."\",\""._QXZ("ACTIVE")."\"";
	$CSV_text1.=",\""._QXZ("CONTACTS 1st PASS")."\",\""._QXZ("CONTACTS 2nd PASS")."\",\""._QXZ("CONTACTS 3rd PASS")."\",\""._QXZ("CONTACTS 4th PASS")."\",\""._QXZ("CONTACTS 5th PASS")."\",\""._QXZ("CONTACTS LIFE")."\"";
	$CSV_text1.=",\""._QXZ("CNT RATE 1st PASS")."\",\""._QXZ("CNT RATE 2nd PASS")."\",\""._QXZ("CNT RATE 3rd PASS")."\",\""._QXZ("CNT RATE 4th PASS")."\",\""._QXZ("CNT RATE 5th PASS")."\",\""._QXZ("CNT RATE LIFE")."\"";
	$CSV_text1.=",\""._QXZ("SALES 1st PASS")."\",\""._QXZ("SALES 2nd PASS")."\",\""._QXZ("SALES 3rd PASS")."\",\""._QXZ("SALES 4th PASS")."\",\""._QXZ("SALES 5th PASS")."\",\""._QXZ("SALES LIFE")."\"";
	$CSV_text1.=",\""._QXZ("CONV RATE 1st PASS")."\",\""._QXZ("CONV RATE 2nd PASS")."\",\""._QXZ("CONV RATE 3rd PASS")."\",\""._QXZ("CONV RATE 4th PASS")."\",\""._QXZ("CONV RATE 5th PASS")."\",\""._QXZ("CONV RATE LIFE")."\"";
	$CSV_text1.=",\""._QXZ("DNC 1st PASS")."\",\""._QXZ("DNC 2nd PASS")."\",\""._QXZ("DNC 3rd PASS")."\",\""._QXZ("DNC 4th PASS")."\",\""._QXZ("DNC 5th PASS")."\",\""._QXZ("DNC LIFE")."\"";
	$CSV_text1.=",\""._QXZ("DNC RATE 1st PASS")."\",\""._QXZ("DNC RATE 2nd PASS")."\",\""._QXZ("DNC RATE 3rd PASS")."\",\""._QXZ("DNC RATE 4th PASS")."\",\""._QXZ("DNC RATE 5th PASS")."\",\""._QXZ("DNC RATE LIFE")."\"";
	$CSV_text1.=",\""._QXZ("CUSTOMER CONTACT 1st PASS")."\",\""._QXZ("CUSTOMER CONTACT 2nd PASS")."\",\""._QXZ("CUSTOMER CONTACT 3rd PASS")."\",\""._QXZ("CUSTOMER CONTACT 4th PASS")."\",\""._QXZ("CUSTOMER CONTACT 5th PASS")."\",\""._QXZ("CUSTOMER CONTACT LIFE")."\"";
	$CSV_text1.=",\""._QXZ("CUSTOMER CONTACT RATE 1st PASS")."\",\""._QXZ("CUSTOMER CONTACT RATE 2nd PASS")."\",\""._QXZ("CUSTOMER CONTACT RATE 3rd PASS")."\",\""._QXZ("CUSTOMER CONTACT RATE 4th PASS")."\",\""._QXZ("CUSTOMER CONTACT RATE 5th PASS")."\",\""._QXZ("CUSTOMER CONTACT RATE LIFE")."\"";
	$CSV_text1.=",\""._QXZ("UNWORKABLE 1st PASS")."\",\""._QXZ("UNWORKABLE 2nd PASS")."\",\""._QXZ("UNWORKABLE 3rd PASS")."\",\""._QXZ("UNWORKABLE 4th PASS")."\",\""._QXZ("UNWORKABLE 5th PASS")."\",\""._QXZ("UNWORKABLE LIFE")."\"";
	$CSV_text1.=",\""._QXZ("UNWORKABLE RATE 1st PASS")."\",\""._QXZ("UNWORKABLE RATE 2nd PASS")."\",\""._QXZ("UNWORKABLE RATE 3rd PASS")."\",\""._QXZ("UNWORKABLE RATE 4th PASS")."\",\""._QXZ("UNWORKABLE RATE 5th PASS")."\",\""._QXZ("UNWORKABLE RATE LIFE")."\"";
	$CSV_text1.=",\""._QXZ("SCHEDULED CALLBACK 1st PASS")."\",\""._QXZ("SCHEDULED CALLBACK 2nd PASS")."\",\""._QXZ("SCHEDULED CALLBACK 3rd PASS")."\",\""._QXZ("SCHEDULED CALLBACK 4th PASS")."\",\""._QXZ("SCHEDULED CALLBACK 5th PASS")."\",\""._QXZ("SCHEDULED CALLBACK LIFE")."\"";
	$CSV_text1.=",\""._QXZ("SCHEDULED CALLBACK RATE 1st PASS")."\",\""._QXZ("SCHEDULED CALLBACK RATE 2nd PASS")."\",\""._QXZ("SCHEDULED CALLBACK RATE 3rd PASS")."\",\""._QXZ("SCHEDULED CALLBACK RATE 4th PASS")."\",\""._QXZ("SCHEDULED CALLBACK RATE 5th PASS")."\",\""._QXZ("SCHEDULED CALLBACK RATE LIFE")."\"";
	$CSV_text1.=",\""._QXZ("COMPLETED 1st PASS")."\",\""._QXZ("COMPLETED 2nd PASS")."\",\""._QXZ("COMPLETED 3rd PASS")."\",\""._QXZ("COMPLETED 4th PASS")."\",\""._QXZ("COMPLETED 5th PASS")."\",\""._QXZ("COMPLETED LIFE")."\"";
	$CSV_text1.=",\""._QXZ("COMPLETED RATE 1st PASS")."\",\""._QXZ("COMPLETED RATE 2nd PASS")."\",\""._QXZ("COMPLETED RATE 3rd PASS")."\",\""._QXZ("COMPLETED RATE 4th PASS")."\",\""._QXZ("COMPLETED RATE 5th PASS")."\",\""._QXZ("COMPLETED RATE LIFE")."\"";
	$CSV_text1.="\n";

	$graph_stats=array();
	$graph_stats2=array();
	$max_stats2=array();

	$GRAPH="</PRE><table cellspacing=\"1\" cellpadding=\"0\" bgcolor=\"white\" summary=\"LIST ID Summary\" class=\"horizontalgraph\">\n";
	$GRAPH.="<caption align='top'>"._QXZ("LIST ID SUMMARY")."</caption>";
	$GRAPH.="<tr>\n";
	$GRAPH.="<th class=\"thgraph\" scope=\"col\">"._QXZ("LIST")."</th>\n";
	$GRAPH.="<th class=\"thgraph\" scope=\"col\">"._QXZ("LEADS")."</th>\n";
	$GRAPH.="</tr>\n";

	$GRAPH2="<tr>
	<th class='column_header grey_graph_cell' id='callstatsgraph1' ><a href='#' onClick=\"DrawGraph('CONTACTS1', '1'); return false;\">"._QXZ("CONTACTS 1st PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph2' ><a href='#' onClick=\"DrawGraph('CONTACTS2', '2'); return false;\">"._QXZ("CONTACTS 2nd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph3' ><a href='#' onClick=\"DrawGraph('CONTACTS3', '3'); return false;\">"._QXZ("CONTACTS 3rd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph4' ><a href='#' onClick=\"DrawGraph('CONTACTS4', '4'); return false;\">"._QXZ("CONTACTS 4th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph5' ><a href='#' onClick=\"DrawGraph('CONTACTS5', '5'); return false;\">"._QXZ("CONTACTS 5th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph6' ><a href='#' onClick=\"DrawGraph('CONTACTSALL', '6'); return false;\">"._QXZ("CONTACTS LIFE")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph7' ><a href='#' onClick=\"DrawGraph('CNTRATE1', '7'); return false;\">"._QXZ("CNT RATE 1st PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph8' ><a href='#' onClick=\"DrawGraph('CNTRATE2', '8'); return false;\">"._QXZ("CNT RATE 2nd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph9' ><a href='#' onClick=\"DrawGraph('CNTRATE3', '9'); return false;\">"._QXZ("CNT RATE 3rd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph10'><a href='#' onClick=\"DrawGraph('CNTRATE4', '10'); return false;\">"._QXZ("CNT RATE 4th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph11'><a href='#' onClick=\"DrawGraph('CNTRATE5', '11'); return false;\">"._QXZ("CNT RATE 5th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph12'><a href='#' onClick=\"DrawGraph('CNTRATEALL', '12'); return false;\">"._QXZ("CNT RATE LIFE")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph13'><a href='#' onClick=\"DrawGraph('SALES1', '13'); return false;\">"._QXZ("SALES 1st PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph14'><a href='#' onClick=\"DrawGraph('SALES2', '14'); return false;\">"._QXZ("SALES 2nd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph15'><a href='#' onClick=\"DrawGraph('SALES3', '15'); return false;\">"._QXZ("SALES 3rd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph16'><a href='#' onClick=\"DrawGraph('SALES4', '16'); return false;\">"._QXZ("SALES 4th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph17'><a href='#' onClick=\"DrawGraph('SALES5', '17'); return false;\">"._QXZ("SALES 5th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph18'><a href='#' onClick=\"DrawGraph('SALESALL', '18'); return false;\">"._QXZ("SALES LIFE")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph19'><a href='#' onClick=\"DrawGraph('CONVRATE1', '19'); return false;\">"._QXZ("CONV RATE 1st PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph20'><a href='#' onClick=\"DrawGraph('CONVRATE2', '20'); return false;\">"._QXZ("CONV RATE 2nd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph21'><a href='#' onClick=\"DrawGraph('CONVRATE3', '21'); return false;\">"._QXZ("CONV RATE 3rd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph22'><a href='#' onClick=\"DrawGraph('CONVRATE4', '22'); return false;\">"._QXZ("CONV RATE 4th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph23'><a href='#' onClick=\"DrawGraph('CONVRATE5', '23'); return false;\">"._QXZ("CONV RATE 5th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph24'><a href='#' onClick=\"DrawGraph('CONVRATEALL', '24'); return false;\">"._QXZ("CONV RATE LIFE")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph25'><a href='#' onClick=\"DrawGraph('DNC1', '25'); return false;\">"._QXZ("DNC 1st PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph26'><a href='#' onClick=\"DrawGraph('DNC2', '26'); return false;\">"._QXZ("DNC 2nd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph27'><a href='#' onClick=\"DrawGraph('DNC3', '27'); return false;\">"._QXZ("DNC 3rd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph28'><a href='#' onClick=\"DrawGraph('DNC4', '28'); return false;\">"._QXZ("DNC 4th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph29'><a href='#' onClick=\"DrawGraph('DNC5', '29'); return false;\">"._QXZ("DNC 5th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph30'><a href='#' onClick=\"DrawGraph('DNCALL', '30'); return false;\">"._QXZ("DNC LIFE")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph31'><a href='#' onClick=\"DrawGraph('DNCRATE1', '31'); return false;\">"._QXZ("DNC RATE 1st PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph32'><a href='#' onClick=\"DrawGraph('DNCRATE2', '32'); return false;\">"._QXZ("DNC RATE 2nd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph33'><a href='#' onClick=\"DrawGraph('DNCRATE3', '33'); return false;\">"._QXZ("DNC RATE 3rd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph34'><a href='#' onClick=\"DrawGraph('DNCRATE4', '34'); return false;\">"._QXZ("DNC RATE 4th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph35'><a href='#' onClick=\"DrawGraph('DNCRATE5', '35'); return false;\">"._QXZ("DNC RATE 5th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph36'><a href='#' onClick=\"DrawGraph('DNCRATEALL', '36'); return false;\">"._QXZ("DNC RATE LIFE")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph37'><a href='#' onClick=\"DrawGraph('CUSTCNT1', '37'); return false;\">"._QXZ("CUST CNT 1st PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph38'><a href='#' onClick=\"DrawGraph('CUSTCNT2', '38'); return false;\">"._QXZ("CUST CNT 2nd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph39'><a href='#' onClick=\"DrawGraph('CUSTCNT3', '39'); return false;\">"._QXZ("CUST CNT 3rd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph40'><a href='#' onClick=\"DrawGraph('CUSTCNT4', '40'); return false;\">"._QXZ("CUST CNT 4th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph41'><a href='#' onClick=\"DrawGraph('CUSTCNT5', '41'); return false;\">"._QXZ("CUST CNT 5th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph42'><a href='#' onClick=\"DrawGraph('CUSTCNTALL', '42'); return false;\">"._QXZ("CUST CNT LIFE")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph43'><a href='#' onClick=\"DrawGraph('CUSTCNTRATE1', '43'); return false;\">"._QXZ("CUST CUSTCNT RATE 1st PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph44'><a href='#' onClick=\"DrawGraph('CUSTCNTRATE2', '44'); return false;\">"._QXZ("CUST CUSTCNT RATE 2nd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph45'><a href='#' onClick=\"DrawGraph('CUSTCNTRATE3', '45'); return false;\">"._QXZ("CUST CUSTCNT RATE 3rd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph46'><a href='#' onClick=\"DrawGraph('CUSTCNTRATE4', '46'); return false;\">"._QXZ("CUST CUSTCNT RATE 4th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph47'><a href='#' onClick=\"DrawGraph('CUSTCNTRATE5', '47'); return false;\">"._QXZ("CUST CUSTCNT RATE 5th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph48'><a href='#' onClick=\"DrawGraph('CUSTCNTRATEALL', '48'); return false;\">"._QXZ("CUST CUSTCNT RATE LIFE")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph49'><a href='#' onClick=\"DrawGraph('UNWRK1', '49'); return false;\">"._QXZ("UNWRK 1st PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph50'><a href='#' onClick=\"DrawGraph('UNWRK2', '50'); return false;\">"._QXZ("UNWRK 2nd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph51'><a href='#' onClick=\"DrawGraph('UNWRK3', '51'); return false;\">"._QXZ("UNWRK 3rd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph52'><a href='#' onClick=\"DrawGraph('UNWRK4', '52'); return false;\">"._QXZ("UNWRK 4th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph53'><a href='#' onClick=\"DrawGraph('UNWRK5', '53'); return false;\">"._QXZ("UNWRK 5th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph54'><a href='#' onClick=\"DrawGraph('UNWRKALL', '54'); return false;\">"._QXZ("UNWRK LIFE")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph55'><a href='#' onClick=\"DrawGraph('UNWRKRATE1', '55'); return false;\">"._QXZ("UNWRK RATE 1st PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph56'><a href='#' onClick=\"DrawGraph('UNWRKRATE2', '56'); return false;\">"._QXZ("UNWRK RATE 2nd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph57'><a href='#' onClick=\"DrawGraph('UNWRKRATE3', '57'); return false;\">"._QXZ("UNWRK RATE 3rd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph58'><a href='#' onClick=\"DrawGraph('UNWRKRATE4', '58'); return false;\">"._QXZ("UNWRK RATE 4th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph59'><a href='#' onClick=\"DrawGraph('UNWRKRATE5', '59'); return false;\">"._QXZ("UNWRK RATE 5th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph60'><a href='#' onClick=\"DrawGraph('UNWRKRATEALL', '60'); return false;\">"._QXZ("UNWRK RATE LIFE")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph61'><a href='#' onClick=\"DrawGraph('SCHDCLBK1', '61'); return false;\">"._QXZ("SCHD CLBK 1st PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph62'><a href='#' onClick=\"DrawGraph('SCHDCLBK2', '62'); return false;\">"._QXZ("SCHD CLBK 2nd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph63'><a href='#' onClick=\"DrawGraph('SCHDCLBK3', '63'); return false;\">"._QXZ("SCHD CLBK 3rd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph64'><a href='#' onClick=\"DrawGraph('SCHDCLBK4', '64'); return false;\">"._QXZ("SCHD CLBK 4th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph65'><a href='#' onClick=\"DrawGraph('SCHDCLBK5', '65'); return false;\">"._QXZ("SCHD CLBK 5th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph66'><a href='#' onClick=\"DrawGraph('SCHDCLBKALL', '66'); return false;\">"._QXZ("SCHD CLBK LIFE")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph67'><a href='#' onClick=\"DrawGraph('SCHDCLBKRATE1', '67'); return false;\">"._QXZ("SCHD CLBK RATE 1st PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph68'><a href='#' onClick=\"DrawGraph('SCHDCLBKRATE2', '68'); return false;\">"._QXZ("SCHD CLBK RATE 2nd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph69'><a href='#' onClick=\"DrawGraph('SCHDCLBKRATE3', '69'); return false;\">"._QXZ("SCHD CLBK RATE 3rd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph70'><a href='#' onClick=\"DrawGraph('SCHDCLBKRATE4', '70'); return false;\">"._QXZ("SCHD CLBK RATE 4th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph71'><a href='#' onClick=\"DrawGraph('SCHDCLBKRATE5', '71'); return false;\">"._QXZ("SCHD CLBK RATE 5th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph72'><a href='#' onClick=\"DrawGraph('SCHDCLBKRATEALL', '72'); return false;\">"._QXZ("SCHD CLBK RATE LIFE")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph73'><a href='#' onClick=\"DrawGraph('COMPLTD1', '73'); return false;\">"._QXZ("COMPLTD 1st PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph74'><a href='#' onClick=\"DrawGraph('COMPLTD2', '74'); return false;\">"._QXZ("COMPLTD 2nd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph75'><a href='#' onClick=\"DrawGraph('COMPLTD3', '75'); return false;\">"._QXZ("COMPLTD 3rd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph76'><a href='#' onClick=\"DrawGraph('COMPLTD4', '76'); return false;\">"._QXZ("COMPLTD 4th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph77'><a href='#' onClick=\"DrawGraph('COMPLTD5', '77'); return false;\">"._QXZ("COMPLTD 5th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph78'><a href='#' onClick=\"DrawGraph('COMPLTDALL', '78'); return false;\">"._QXZ("COMPLTD LIFE")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph79'><a href='#' onClick=\"DrawGraph('COMPLTDRATE1', '79'); return false;\">"._QXZ("COMPLTD RATE 1st PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph80'><a href='#' onClick=\"DrawGraph('COMPLTDRATE2', '80'); return false;\">"._QXZ("COMPLTD RATE 2nd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph81'><a href='#' onClick=\"DrawGraph('COMPLTDRATE3', '81'); return false;\">"._QXZ("COMPLTD RATE 3rd PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph82'><a href='#' onClick=\"DrawGraph('COMPLTDRATE4', '82'); return false;\">"._QXZ("COMPLTD RATE 4th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph83'><a href='#' onClick=\"DrawGraph('COMPLTDRATE5', '83'); return false;\">"._QXZ("COMPLTD RATE 5th PASS")."</a></th>
	<th class='column_header grey_graph_cell' id='callstatsgraph84'><a href='#' onClick=\"DrawGraph('COMPLTDRATEALL', '84'); return false;\">"._QXZ("COMPLTD RATE LIFE")."</a></th>
	";

	$graph_header="<table cellspacing='0' cellpadding='0' class='horizontalgraph'><caption align='top'>"._QXZ("LIST ID SUMMARY")."</caption><tr><th class='thgraph' scope='col'>"._QXZ("LISTS")."</th>";
	$CONTACTS1_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CONTACTS 1st PASS")."</th></tr>";
	$CONTACTS2_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CONTACTS 2nd PASS")."</th></tr>";
	$CONTACTS3_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CONTACTS 3rd PASS")."</th></tr>";
	$CONTACTS4_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CONTACTS 4th PASS")."</th></tr>";
	$CONTACTS5_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CONTACTS 5th PASS")."</th></tr>";
	$CONTACTSALL_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CONTACTS LIFE")."</th></tr>";
	$CNTRATE1_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CNT RATE 1st PASS")."</th></tr>";
	$CNTRATE2_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CNT RATE 2nd PASS")."</th></tr>";
	$CNTRATE3_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CNT RATE 3rd PASS")."</th></tr>";
	$CNTRATE4_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CNT RATE 4th PASS")."</th></tr>";
	$CNTRATE5_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CNT RATE 5th PASS")."</th></tr>";
	$CNTRATEALL_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CNT RATE LIFE")."</th></tr>";
	$SALES1_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SALES 1st PASS")."</th></tr>";
	$SALES2_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SALES 2nd PASS")."</th></tr>";
	$SALES3_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SALES 3rd PASS")."</th></tr>";
	$SALES4_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SALES 4th PASS")."</th></tr>";
	$SALES5_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SALES 5th PASS")."</th></tr>";
	$SALESALL_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SALES LIFE")."</th></tr>";
	$CONVRATE1_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CONV RATE 1st PASS")."</th></tr>";
	$CONVRATE2_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CONV RATE 2nd PASS")."</th></tr>";
	$CONVRATE3_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CONV RATE 3rd PASS")."</th></tr>";
	$CONVRATE4_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CONV RATE 4th PASS")."</th></tr>";
	$CONVRATE5_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CONV RATE 5th PASS")."</th></tr>";
	$CONVRATEALL_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CONV RATE LIFE")."</th></tr>";
	$DNC1_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("DNC 1st PASS")."</th></tr>";
	$DNC2_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("DNC 2nd PASS")."</th></tr>";
	$DNC3_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("DNC 3rd PASS")."</th></tr>";
	$DNC4_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("DNC 4th PASS")."</th></tr>";
	$DNC5_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("DNC 5th PASS")."</th></tr>";
	$DNCALL_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("DNC LIFE")."</th></tr>";
	$DNCRATE1_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("DNC RATE 1st PASS")."</th></tr>";
	$DNCRATE2_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("DNC RATE 2nd PASS")."</th></tr>";
	$DNCRATE3_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("DNC RATE 3rd PASS")."</th></tr>";
	$DNCRATE4_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("DNC RATE 4th PASS")."</th></tr>";
	$DNCRATE5_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("DNC RATE 5th PASS")."</th></tr>";
	$DNCRATEALL_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("DNC RATE LIFE")."</th></tr>";
	$CUSTCNT1_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CUST CNT 1st PASS")."</th></tr>";
	$CUSTCNT2_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CUST CNT 2nd PASS")."</th></tr>";
	$CUSTCNT3_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CUST CNT 3rd PASS")."</th></tr>";
	$CUSTCNT4_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CUST CNT 4th PASS")."</th></tr>";
	$CUSTCNT5_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CUST CNT 5th PASS")."</th></tr>";
	$CUSTCNTALL_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CUST CNT LIFE")."</th></tr>";
	$CUSTCNTRATE1_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CUST CUSTCNT RATE 1st PASS")."</th></tr>";
	$CUSTCNTRATE2_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CUST CUSTCNT RATE 2nd PASS")."</th></tr>";
	$CUSTCNTRATE3_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CUST CUSTCNT RATE 3rd PASS")."</th></tr>";
	$CUSTCNTRATE4_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CUST CUSTCNT RATE 4th PASS")."</th></tr>";
	$CUSTCNTRATE5_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CUST CUSTCNT RATE 5th PASS")."</th></tr>";
	$CUSTCNTRATEALL_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("CUST CUSTCNT RATE LIFE")."</th></tr>";
	$UNWRK1_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("UNWRK 1st PASS")."</th></tr>";
	$UNWRK2_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("UNWRK 2nd PASS")."</th></tr>";
	$UNWRK3_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("UNWRK 3rd PASS")."</th></tr>";
	$UNWRK4_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("UNWRK 4th PASS")."</th></tr>";
	$UNWRK5_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("UNWRK 5th PASS")."</th></tr>";
	$UNWRKALL_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("UNWRK LIFE")."</th></tr>";
	$UNWRKRATE1_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("UNWRK RATE 1st PASS")."</th></tr>";
	$UNWRKRATE2_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("UNWRK RATE 2nd PASS")."</th></tr>";
	$UNWRKRATE3_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("UNWRK RATE 3rd PASS")."</th></tr>";
	$UNWRKRATE4_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("UNWRK RATE 4th PASS")."</th></tr>";
	$UNWRKRATE5_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("UNWRK RATE 5th PASS")."</th></tr>";
	$UNWRKRATEALL_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("UNWRK RATE LIFE")."</th></tr>";
	$SCHDCLBK1_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SCHD CLBK 1st PASS")."</th></tr>";
	$SCHDCLBK2_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SCHD CLBK 2nd PASS")."</th></tr>";
	$SCHDCLBK3_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SCHD CLBK 3rd PASS")."</th></tr>";
	$SCHDCLBK4_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SCHD CLBK 4th PASS")."</th></tr>";
	$SCHDCLBK5_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SCHD CLBK 5th PASS")."</th></tr>";
	$SCHDCLBKALL_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SCHD CLBK LIFE")."</th></tr>";
	$SCHDCLBKRATE1_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SCHD CLBK RATE 1st PASS")."</th></tr>";
	$SCHDCLBKRATE2_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SCHD CLBK RATE 2nd PASS")."</th></tr>";
	$SCHDCLBKRATE3_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SCHD CLBK RATE 3rd PASS")."</th></tr>";
	$SCHDCLBKRATE4_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SCHD CLBK RATE 4th PASS")."</th></tr>";
	$SCHDCLBKRATE5_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SCHD CLBK RATE 5th PASS")."</th></tr>";
	$SCHDCLBKRATEALL_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("SCHD CLBK RATE LIFE")."</th></tr>";
	$COMPLTD1_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("COMPLTD 1st PASS")."</th></tr>";
	$COMPLTD2_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("COMPLTD 2nd PASS")."</th></tr>";
	$COMPLTD3_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("COMPLTD 3rd PASS")."</th></tr>";
	$COMPLTD4_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("COMPLTD 4th PASS")."</th></tr>";
	$COMPLTD5_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("COMPLTD 5th PASS")."</th></tr>";
	$COMPLTDALL_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("COMPLTD LIFE")."</th></tr>";
	$COMPLTDRATE1_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("COMPLTD RATE 1st PASS")."</th></tr>";
	$COMPLTDRATE2_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("COMPLTD RATE 2nd PASS")."</th></tr>";
	$COMPLTDRATE3_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("COMPLTD RATE 3rd PASS")."</th></tr>";
	$COMPLTDRATE4_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("COMPLTD RATE 4th PASS")."</th></tr>";
	$COMPLTDRATE5_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("COMPLTD RATE 5th PASS")."</th></tr>";
	$COMPLTDRATEALL_graph=$graph_header."<th class='thgraph' scope='col'>"._QXZ("COMPLTD RATE LIFE")."</th></tr>";

	$lists_id_str="";
	$list_stmt="SELECT list_id from vicidial_lists where active IN('Y','N') $group_SQLand";
	$list_rslt=mysql_to_mysqli($list_stmt, $link);
	while ($lrow=mysqli_fetch_row($list_rslt)) 
		{
		$lists_id_str.="'$lrow[0]',";
		}
	$lists_id_str=substr($lists_id_str,0,-1);
	if (strlen($lists_id_str)<3) {$lists_id_str="''";}

	$stmt="select count(*),list_id from vicidial_list where list_id IN($lists_id_str) group by list_id;";
	$rslt=mysql_to_mysqli($stmt, $link);
	if ($DB) {$MAIN.="$stmt\n";}
	$listids_to_print = mysqli_num_rows($rslt);
	$i=0;
	while ($i < $listids_to_print)
		{
		$row=mysqli_fetch_row($rslt);
		$LISTIDcalls[$i] =	$row[0];
		$LISTIDlists[$i] =	$row[1];
		$list_id_SQL .=		"'$row[1]',";
		if ($row[0]>$max_calls) {$max_calls=$row[0];}
		$graph_stats[$i][0]=$row[0];
		$graph_stats[$i][1]=$row[1];
		$graph_stats2[$i][0]=$row[1];
		$i++;
		}
	if (strlen($list_id_SQL)>2)		{$list_id_SQL = substr("$list_id_SQL", 0, -1);}
	else {$list_id_SQL="''";}

	$i=0;
	while ($i < $listids_to_print)
		{
		$stmt="select list_name,active,campaign_id from vicidial_lists where list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$list_name_to_print = mysqli_num_rows($rslt);
		if ($list_name_to_print > 0)
			{
			$row=mysqli_fetch_row($rslt);
			$LISTIDlist_names[$i] =	$row[0];
			$LISTIDcampaign[$i] =	$row[2];
			$graph_stats[$i][1].=" - $row[0]";
			$graph_stats2[$i][0].=" - $row[0]";
			if ($row[1]=='Y')
				{$LISTIDlist_active[$i] = 'ACTIVE  '; $graph_stats[$i][1].=" (ACTIVE)"; $graph_stats2[$i][0].=" (ACTIVE)";}
			else
				{$LISTIDlist_active[$i] = 'INACTIVE'; $graph_stats[$i][1].=" (INACTIVE)"; $graph_stats2[$i][0].=" (INACTIVE)";}
			}

		$LISTIDentry_date[$i]='';
		$stmt="select entry_date from vicidial_list where list_id='$LISTIDlists[$i]' order by entry_date limit 1;";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$list_name_to_print = mysqli_num_rows($rslt);
		if ($list_name_to_print > 0)
			{
			$row=mysqli_fetch_row($rslt);
			$LISTIDentry_date[$i] =	$row[0];
			}

		$TOTALleads = ($TOTALleads + $LISTIDcalls[$i]);
		$LISTIDentry_dateS =	sprintf("%10s", $LISTIDentry_date[$i]); while(strlen($LISTIDentry_dateS)>10) {$LISTIDentry_dateS = substr("$LISTIDentry_dateS", 0, -1);}
		$LISTIDcampaignS =	sprintf("%8s", $LISTIDcampaign[$i]); while(strlen($LISTIDcampaignS)>8) {$LISTIDcampaignS = substr("$LISTIDcampaignS", 0, -1);}
		$LISTIDname =	sprintf("%-40s", "$LISTIDlists[$i] - $LISTIDlist_names[$i]"); while(strlen($LISTIDname)>40) {$LISTIDname = substr("$LISTIDname", 0, -1);}
		$LISTIDcount =	sprintf("%10s", $LISTIDcalls[$i]); while(strlen($LISTIDcount)>10) {$LISTIDcount = substr("$LISTIDcount", 0, -1);}



		########################################################
		########## BEGIN CONTACTS (Human-Answer flag) ##########

		$HA_count=0; $HA_one_count=0; $HA_two_count=0; $HA_three_count=0; $HA_four_count=0; $HA_five_count=0; $HA_all_count=0;

		$stmt="select count(*) from vicidial_list where status IN($human_answered_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$HA_results = mysqli_num_rows($rslt);
		if ($HA_results > 0)
			{$row=mysqli_fetch_row($rslt); $HA_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=1 and status IN($human_answered_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$HA_one_results = mysqli_num_rows($rslt);
		if ($HA_one_results > 0)
			{$row=mysqli_fetch_row($rslt); $HA_one_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=2 and status IN($human_answered_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$HA_two_results = mysqli_num_rows($rslt);
		if ($HA_two_results > 0)
			{$row=mysqli_fetch_row($rslt); $HA_two_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=3 and status IN($human_answered_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$HA_three_results = mysqli_num_rows($rslt);
		if ($HA_three_results > 0)
			{$row=mysqli_fetch_row($rslt); $HA_three_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count='4' and status IN($human_answered_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$HA_four_results = mysqli_num_rows($rslt);
		if ($HA_four_results > 0)
			{$row=mysqli_fetch_row($rslt); $HA_four_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count='5' and status IN($human_answered_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$HA_five_results = mysqli_num_rows($rslt);
		if ($HA_five_results > 0)
			{$row=mysqli_fetch_row($rslt); $HA_five_count = $row[0];}
		$stmt="select count(distinct lead_id) from vicidial_log where status IN($human_answered_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$HA_all_results = mysqli_num_rows($rslt);
		if ($HA_all_results > 0)
			{$row=mysqli_fetch_row($rslt); $HA_all_count = $row[0];}
		if ($HA_all_count > $HA_count) {$HA_count = $HA_all_count;}

		$HA_countS =	sprintf("%7s", $HA_count); while(strlen($HA_countS)>7) {$HA_countS = substr("$HA_countS", 0, -1);}
		$HA_one_countS =	sprintf("%7s", $HA_one_count); while(strlen($HA_one_countS)>7) {$HA_one_countS = substr("$HA_one_countS", 0, -1);}
		$HA_two_countS =	sprintf("%7s", $HA_two_count); while(strlen($HA_two_countS)>7) {$HA_two_countS = substr("$HA_two_countS", 0, -1);}
		$HA_three_countS =	sprintf("%7s", $HA_three_count); while(strlen($HA_three_countS)>7) {$HA_three_countS = substr("$HA_three_countS", 0, -1);}
		$HA_four_countS =	sprintf("%7s", $HA_four_count); while(strlen($HA_four_countS)>7) {$HA_four_countS = substr("$HA_four_countS", 0, -1);}
		$HA_five_countS =	sprintf("%7s", $HA_five_count); while(strlen($HA_five_countS)>7) {$HA_five_countS = substr("$HA_five_countS", 0, -1);}

		$HA_count_tot =	($HA_count + $HA_count_tot);
		$HA_one_count_tot =	($HA_one_count + $HA_one_count_tot);
		$HA_two_count_tot =	($HA_two_count + $HA_two_count_tot);
		$HA_three_count_tot =	($HA_three_count + $HA_three_count_tot);
		$HA_four_count_tot =	($HA_four_count + $HA_four_count_tot);
		$HA_five_count_tot =	($HA_five_count + $HA_five_count_tot);

		########## END CONTACTS (Human-Answer flag) ##########
		########################################################


		########################################################
		########## BEGIN CONTACT RATIO (Human-Answer flag out of total leads percentage) ##########

		$HR_count=$HA_count; 
		$HR_one_count=$HA_one_count;
		$HR_two_count=$HA_two_count;
		$HR_three_count=$HA_three_count;
		$HR_four_count=$HA_four_count;
		$HR_five_count=$HA_five_count;
		$HR_all_count=$HA_all_count;

		$HR_count_pct=0;
		$HR_one_count_pct=0;
		$HR_two_count_pct=0;
		$HR_three_count_pct=0;
		$HR_four_count_pct=0;
		$HR_five_count_pct=0;
		$HR_count_pct = (MathZDC($HR_count, $LISTIDcalls[$i]) * 100);
		$HR_one_count_pct = (MathZDC($HR_one_count, $LISTIDcalls[$i]) * 100);
		$HR_two_count_pct = (MathZDC($HR_two_count, $LISTIDcalls[$i]) * 100);
		$HR_three_count_pct = (MathZDC($HR_three_count, $LISTIDcalls[$i]) * 100);
		$HR_four_count_pct = (MathZDC($HR_four_count, $LISTIDcalls[$i]) * 100);
		$HR_five_count_pct = (MathZDC($HR_five_count, $LISTIDcalls[$i]) * 100);

		$HR_countS =	sprintf("%6.2f", $HR_count_pct); while(strlen($HR_countS)>7) {$HR_countS = substr("$HR_countS", 0, -1);}
		$HR_one_countS =	sprintf("%6.2f", $HR_one_count_pct); while(strlen($HR_one_countS)>7) {$HR_one_countS = substr("$HR_one_countS", 0, -1);}
		$HR_two_countS =	sprintf("%6.2f", $HR_two_count_pct); while(strlen($HR_two_countS)>7) {$HR_two_countS = substr("$HR_two_countS", 0, -1);}
		$HR_three_countS =	sprintf("%6.2f", $HR_three_count_pct); while(strlen($HR_three_countS)>7) {$HR_three_countS = substr("$HR_three_countS", 0, -1);}
		$HR_four_countS =	sprintf("%6.2f", $HR_four_count_pct); while(strlen($HR_four_countS)>7) {$HR_four_countS = substr("$HR_four_countS", 0, -1);}
		$HR_five_countS =	sprintf("%6.2f", $HR_five_count_pct); while(strlen($HR_five_countS)>7) {$HR_five_countS = substr("$HR_five_countS", 0, -1);}

		$HR_count_tot =	($HR_count + $HR_count_tot);
		$HR_one_count_tot =	($HR_one_count + $HR_one_count_tot);
		$HR_two_count_tot =	($HR_two_count + $HR_two_count_tot);
		$HR_three_count_tot =	($HR_three_count + $HR_three_count_tot);
		$HR_four_count_tot =	($HR_four_count + $HR_four_count_tot);
		$HR_five_count_tot =	($HR_five_count + $HR_five_count_tot);

		########## END  CONTACT RATIO (Human-Answer flag out of total leads percentage) ##########
		########################################################


		########################################################
		########## BEGIN SALES (Sales flag) ##########

		$SA_count=0; $SA_one_count=0; $SA_two_count=0; $SA_three_count=0; $SA_four_count=0; $SA_five_count=0; $SA_all_count=0;

		$stmt="select count(*) from vicidial_list where status IN($sale_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$SA_results = mysqli_num_rows($rslt);
		if ($SA_results > 0)
			{$row=mysqli_fetch_row($rslt); $SA_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=1 and status IN($sale_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$SA_one_results = mysqli_num_rows($rslt);
		if ($SA_one_results > 0)
			{$row=mysqli_fetch_row($rslt); $SA_one_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=2 and status IN($sale_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$SA_two_results = mysqli_num_rows($rslt);
		if ($SA_two_results > 0)
			{$row=mysqli_fetch_row($rslt); $SA_two_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=3 and status IN($sale_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$SA_three_results = mysqli_num_rows($rslt);
		if ($SA_three_results > 0)
			{$row=mysqli_fetch_row($rslt); $SA_three_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count='4' and status IN($sale_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$SA_four_results = mysqli_num_rows($rslt);
		if ($SA_four_results > 0)
			{$row=mysqli_fetch_row($rslt); $SA_four_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count='5' and status IN($sale_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$SA_five_results = mysqli_num_rows($rslt);
		if ($SA_five_results > 0)
			{$row=mysqli_fetch_row($rslt); $SA_five_count = $row[0];}
		$stmt="select count(distinct lead_id) from vicidial_log where status IN($sale_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$SA_all_results = mysqli_num_rows($rslt);
		if ($SA_all_results > 0)
			{$row=mysqli_fetch_row($rslt); $SA_all_count = $row[0];}
		if ($SA_all_count > $SA_count) {$SA_count = $SA_all_count;}

		$SA_countS =	sprintf("%7s", $SA_count); while(strlen($SA_countS)>7) {$SA_countS = substr("$SA_countS", 0, -1);}
		$SA_one_countS =	sprintf("%7s", $SA_one_count); while(strlen($SA_one_countS)>7) {$SA_one_countS = substr("$SA_one_countS", 0, -1);}
		$SA_two_countS =	sprintf("%7s", $SA_two_count); while(strlen($SA_two_countS)>7) {$SA_two_countS = substr("$SA_two_countS", 0, -1);}
		$SA_three_countS =	sprintf("%7s", $SA_three_count); while(strlen($SA_three_countS)>7) {$SA_three_countS = substr("$SA_three_countS", 0, -1);}
		$SA_four_countS =	sprintf("%7s", $SA_four_count); while(strlen($SA_four_countS)>7) {$SA_four_countS = substr("$SA_four_countS", 0, -1);}
		$SA_five_countS =	sprintf("%7s", $SA_five_count); while(strlen($SA_five_countS)>7) {$SA_five_countS = substr("$SA_five_countS", 0, -1);}

		$SA_count_tot =	($SA_count + $SA_count_tot);
		$SA_one_count_tot =	($SA_one_count + $SA_one_count_tot);
		$SA_two_count_tot =	($SA_two_count + $SA_two_count_tot);
		$SA_three_count_tot =	($SA_three_count + $SA_three_count_tot);
		$SA_four_count_tot =	($SA_four_count + $SA_four_count_tot);
		$SA_five_count_tot =	($SA_five_count + $SA_five_count_tot);

		########## END SALES (Sales flag) ##########
		########################################################


		########################################################
		########## BEGIN CONV SALES RATIO (Sales flag out of total leads percentage) ##########

		$SR_count=$SA_count; 
		$SR_one_count=$SA_one_count;
		$SR_two_count=$SA_two_count;
		$SR_three_count=$SA_three_count;
		$SR_four_count=$SA_four_count;
		$SR_five_count=$SA_five_count;
		$SR_all_count=$SA_all_count;

		$SR_count_pct=0;
		$SR_one_count_pct=0;
		$SR_two_count_pct=0;
		$SR_three_count_pct=0;
		$SR_four_count_pct=0;
		$SR_five_count_pct=0;
		$SR_count_pct = (MathZDC($SR_count, $LISTIDcalls[$i]) * 100);
		$SR_one_count_pct = (MathZDC($SR_one_count, $LISTIDcalls[$i]) * 100);
		$SR_two_count_pct = (MathZDC($SR_two_count, $LISTIDcalls[$i]) * 100);
		$SR_three_count_pct = (MathZDC($SR_three_count, $LISTIDcalls[$i]) * 100);
		$SR_four_count_pct = (MathZDC($SR_four_count, $LISTIDcalls[$i]) * 100);
		$SR_five_count_pct = (MathZDC($SR_five_count, $LISTIDcalls[$i]) * 100);

		$SR_countS =	sprintf("%6.2f", $SR_count_pct); while(strlen($SR_countS)>7) {$SR_countS = substr("$SR_countS", 0, -1);}
		$SR_one_countS =	sprintf("%6.2f", $SR_one_count_pct); while(strlen($SR_one_countS)>7) {$SR_one_countS = substr("$SR_one_countS", 0, -1);}
		$SR_two_countS =	sprintf("%6.2f", $SR_two_count_pct); while(strlen($SR_two_countS)>7) {$SR_two_countS = substr("$SR_two_countS", 0, -1);}
		$SR_three_countS =	sprintf("%6.2f", $SR_three_count_pct); while(strlen($SR_three_countS)>7) {$SR_three_countS = substr("$SR_three_countS", 0, -1);}
		$SR_four_countS =	sprintf("%6.2f", $SR_four_count_pct); while(strlen($SR_four_countS)>7) {$SR_four_countS = substr("$SR_four_countS", 0, -1);}
		$SR_five_countS =	sprintf("%6.2f", $SR_five_count_pct); while(strlen($SR_five_countS)>7) {$SR_five_countS = substr("$SR_five_countS", 0, -1);}

		$SR_count_tot =	($SR_count + $SR_count_tot);
		$SR_one_count_tot =	($SR_one_count + $SR_one_count_tot);
		$SR_two_count_tot =	($SR_two_count + $SR_two_count_tot);
		$SR_three_count_tot =	($SR_three_count + $SR_three_count_tot);
		$SR_four_count_tot =	($SR_four_count + $SR_four_count_tot);
		$SR_five_count_tot =	($SR_five_count + $SR_five_count_tot);

		########## END   CONV SALES RATIO (Sales flag out of total leads percentage) ##########
		########################################################


		########################################################
		########## BEGIN DNC (DNC flag) ##########

		$DN_count=0; $DN_one_count=0; $DN_two_count=0; $DN_three_count=0; $DN_four_count=0; $DN_five_count=0; $DN_all_count=0;

		$stmt="select count(*) from vicidial_list where status IN($dnc_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$DN_results = mysqli_num_rows($rslt);
		if ($DN_results > 0)
			{$row=mysqli_fetch_row($rslt); $DN_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=1 and status IN($dnc_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$DN_one_results = mysqli_num_rows($rslt);
		if ($DN_one_results > 0)
			{$row=mysqli_fetch_row($rslt); $DN_one_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=2 and status IN($dnc_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$DN_two_results = mysqli_num_rows($rslt);
		if ($DN_two_results > 0)
			{$row=mysqli_fetch_row($rslt); $DN_two_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=3 and status IN($dnc_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$DN_three_results = mysqli_num_rows($rslt);
		if ($DN_three_results > 0)
			{$row=mysqli_fetch_row($rslt); $DN_three_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count='4' and status IN($dnc_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$DN_four_results = mysqli_num_rows($rslt);
		if ($DN_four_results > 0)
			{$row=mysqli_fetch_row($rslt); $DN_four_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count='5' and status IN($dnc_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$DN_five_results = mysqli_num_rows($rslt);
		if ($DN_five_results > 0)
			{$row=mysqli_fetch_row($rslt); $DN_five_count = $row[0];}
		$stmt="select count(distinct lead_id) from vicidial_log where status IN($dnc_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$DN_all_results = mysqli_num_rows($rslt);
		if ($DN_all_results > 0)
			{$row=mysqli_fetch_row($rslt); $DN_all_count = $row[0];}
		if ($DN_all_count > $DN_count) {$DN_count = $DN_all_count;}

		$DN_countS =	sprintf("%7s", $DN_count); while(strlen($DN_countS)>7) {$DN_countS = substr("$DN_countS", 0, -1);}
		$DN_one_countS =	sprintf("%7s", $DN_one_count); while(strlen($DN_one_countS)>7) {$DN_one_countS = substr("$DN_one_countS", 0, -1);}
		$DN_two_countS =	sprintf("%7s", $DN_two_count); while(strlen($DN_two_countS)>7) {$DN_two_countS = substr("$DN_two_countS", 0, -1);}
		$DN_three_countS =	sprintf("%7s", $DN_three_count); while(strlen($DN_three_countS)>7) {$DN_three_countS = substr("$DN_three_countS", 0, -1);}
		$DN_four_countS =	sprintf("%7s", $DN_four_count); while(strlen($DN_four_countS)>7) {$DN_four_countS = substr("$DN_four_countS", 0, -1);}
		$DN_five_countS =	sprintf("%7s", $DN_five_count); while(strlen($DN_five_countS)>7) {$DN_five_countS = substr("$DN_five_countS", 0, -1);}

		$DN_count_tot =	($DN_count + $DN_count_tot);
		$DN_one_count_tot =	($DN_one_count + $DN_one_count_tot);
		$DN_two_count_tot =	($DN_two_count + $DN_two_count_tot);
		$DN_three_count_tot =	($DN_three_count + $DN_three_count_tot);
		$DN_four_count_tot =	($DN_four_count + $DN_four_count_tot);
		$DN_five_count_tot =	($DN_five_count + $DN_five_count_tot);

		########## END DNC (DNC flag) ##########
		########################################################


		########################################################
		########## BEGIN CONV DNC RATIO (DNC flag out of total leads percentage) ##########

		$DR_count=$DN_count; 
		$DR_one_count=$DN_one_count;
		$DR_two_count=$DN_two_count;
		$DR_three_count=$DN_three_count;
		$DR_four_count=$DN_four_count;
		$DR_five_count=$DN_five_count;
		$DR_all_count=$DN_all_count;

		$DR_count_pct=0;
		$DR_one_count_pct=0;
		$DR_two_count_pct=0;
		$DR_three_count_pct=0;
		$DR_four_count_pct=0;
		$DR_five_count_pct=0;
		$DR_count_pct = (MathZDC($DR_count, $LISTIDcalls[$i]) * 100);
		$DR_one_count_pct = (MathZDC($DR_one_count, $LISTIDcalls[$i]) * 100);
		$DR_two_count_pct = (MathZDC($DR_two_count, $LISTIDcalls[$i]) * 100);
		$DR_three_count_pct = (MathZDC($DR_three_count, $LISTIDcalls[$i]) * 100);
		$DR_four_count_pct = (MathZDC($DR_four_count, $LISTIDcalls[$i]) * 100);
		$DR_five_count_pct = (MathZDC($DR_five_count, $LISTIDcalls[$i]) * 100);

		$DR_countS =	sprintf("%6.2f", $DR_count_pct); while(strlen($DR_countS)>7) {$DR_countS = substr("$DR_countS", 0, -1);}
		$DR_one_countS =	sprintf("%6.2f", $DR_one_count_pct); while(strlen($DR_one_countS)>7) {$DR_one_countS = substr("$DR_one_countS", 0, -1);}
		$DR_two_countS =	sprintf("%6.2f", $DR_two_count_pct); while(strlen($DR_two_countS)>7) {$DR_two_countS = substr("$DR_two_countS", 0, -1);}
		$DR_three_countS =	sprintf("%6.2f", $DR_three_count_pct); while(strlen($DR_three_countS)>7) {$DR_three_countS = substr("$DR_three_countS", 0, -1);}
		$DR_four_countS =	sprintf("%6.2f", $DR_four_count_pct); while(strlen($DR_four_countS)>7) {$DR_four_countS = substr("$DR_four_countS", 0, -1);}
		$DR_five_countS =	sprintf("%6.2f", $DR_five_count_pct); while(strlen($DR_five_countS)>7) {$DR_five_countS = substr("$DR_five_countS", 0, -1);}

		$DR_count_tot =	($DR_count + $DR_count_tot);
		$DR_one_count_tot =	($DR_one_count + $DR_one_count_tot);
		$DR_two_count_tot =	($DR_two_count + $DR_two_count_tot);
		$DR_three_count_tot =	($DR_three_count + $DR_three_count_tot);
		$DR_four_count_tot =	($DR_four_count + $DR_four_count_tot);
		$DR_five_count_tot =	($DR_five_count + $DR_five_count_tot);

		########## END   CONV DNC RATIO (DNC flag out of total leads percentage) ##########
		########################################################


		########################################################
		########## BEGIN CUSTOMER CONTACT (Customer Contact flag) ##########

		$CC_count=0; $CC_one_count=0; $CC_two_count=0; $CC_three_count=0; $CC_four_count=0; $CC_five_count=0; $CC_all_count=0;

		$stmt="select count(*) from vicidial_list where status IN($customer_contact_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$CC_results = mysqli_num_rows($rslt);
		if ($CC_results > 0)
			{$row=mysqli_fetch_row($rslt); $CC_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=1 and status IN($customer_contact_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$CC_one_results = mysqli_num_rows($rslt);
		if ($CC_one_results > 0)
			{$row=mysqli_fetch_row($rslt); $CC_one_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=2 and status IN($customer_contact_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$CC_two_results = mysqli_num_rows($rslt);
		if ($CC_two_results > 0)
			{$row=mysqli_fetch_row($rslt); $CC_two_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=3 and status IN($customer_contact_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$CC_three_results = mysqli_num_rows($rslt);
		if ($CC_three_results > 0)
			{$row=mysqli_fetch_row($rslt); $CC_three_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count='4' and status IN($customer_contact_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$CC_four_results = mysqli_num_rows($rslt);
		if ($CC_four_results > 0)
			{$row=mysqli_fetch_row($rslt); $CC_four_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count='5' and status IN($customer_contact_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$CC_five_results = mysqli_num_rows($rslt);
		if ($CC_five_results > 0)
			{$row=mysqli_fetch_row($rslt); $CC_five_count = $row[0];}
		$stmt="select count(distinct lead_id) from vicidial_log where status IN($customer_contact_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$CC_all_results = mysqli_num_rows($rslt);
		if ($CC_all_results > 0)
			{$row=mysqli_fetch_row($rslt); $CC_all_count = $row[0];}
		if ($CC_all_count > $CC_count) {$CC_count = $CC_all_count;}

		$CC_countS =	sprintf("%7s", $CC_count); while(strlen($CC_countS)>7) {$CC_countS = substr("$CC_countS", 0, -1);}
		$CC_one_countS =	sprintf("%7s", $CC_one_count); while(strlen($CC_one_countS)>7) {$CC_one_countS = substr("$CC_one_countS", 0, -1);}
		$CC_two_countS =	sprintf("%7s", $CC_two_count); while(strlen($CC_two_countS)>7) {$CC_two_countS = substr("$CC_two_countS", 0, -1);}
		$CC_three_countS =	sprintf("%7s", $CC_three_count); while(strlen($CC_three_countS)>7) {$CC_three_countS = substr("$CC_three_countS", 0, -1);}
		$CC_four_countS =	sprintf("%7s", $CC_four_count); while(strlen($CC_four_countS)>7) {$CC_four_countS = substr("$CC_four_countS", 0, -1);}
		$CC_five_countS =	sprintf("%7s", $CC_five_count); while(strlen($CC_five_countS)>7) {$CC_five_countS = substr("$CC_five_countS", 0, -1);}

		$CC_count_tot =	($CC_count + $CC_count_tot);
		$CC_one_count_tot =	($CC_one_count + $CC_one_count_tot);
		$CC_two_count_tot =	($CC_two_count + $CC_two_count_tot);
		$CC_three_count_tot =	($CC_three_count + $CC_three_count_tot);
		$CC_four_count_tot =	($CC_four_count + $CC_four_count_tot);
		$CC_five_count_tot =	($CC_five_count + $CC_five_count_tot);

		########## END CUSTOMER CONTACT (Customer Contact flag) ##########
		########################################################


		########################################################
		########## BEGIN CUSTOMER CONTACT RATIO (Customer Contact flag out of total leads percentage) ##########

		$CR_count=$CC_count; 
		$CR_one_count=$CC_one_count;
		$CR_two_count=$CC_two_count;
		$CR_three_count=$CC_three_count;
		$CR_four_count=$CC_four_count;
		$CR_five_count=$CC_five_count;
		$CR_all_count=$CC_all_count;

		$CR_count_pct=0;
		$CR_one_count_pct=0;
		$CR_two_count_pct=0;
		$CR_three_count_pct=0;
		$CR_four_count_pct=0;
		$CR_five_count_pct=0;
		$CR_count_pct = (MathZDC($CR_count, $LISTIDcalls[$i]) * 100);
		$CR_one_count_pct = (MathZDC($CR_one_count, $LISTIDcalls[$i]) * 100);
		$CR_two_count_pct = (MathZDC($CR_two_count, $LISTIDcalls[$i]) * 100);
		$CR_three_count_pct = (MathZDC($CR_three_count, $LISTIDcalls[$i]) * 100);
		$CR_four_count_pct = (MathZDC($CR_four_count, $LISTIDcalls[$i]) * 100);
		$CR_five_count_pct = (MathZDC($CR_five_count, $LISTIDcalls[$i]) * 100);

		$CR_countS =	sprintf("%6.2f", $CR_count_pct); while(strlen($CR_countS)>7) {$CR_countS = substr("$CR_countS", 0, -1);}
		$CR_one_countS =	sprintf("%6.2f", $CR_one_count_pct); while(strlen($CR_one_countS)>7) {$CR_one_countS = substr("$CR_one_countS", 0, -1);}
		$CR_two_countS =	sprintf("%6.2f", $CR_two_count_pct); while(strlen($CR_two_countS)>7) {$CR_two_countS = substr("$CR_two_countS", 0, -1);}
		$CR_three_countS =	sprintf("%6.2f", $CR_three_count_pct); while(strlen($CR_three_countS)>7) {$CR_three_countS = substr("$CR_three_countS", 0, -1);}
		$CR_four_countS =	sprintf("%6.2f", $CR_four_count_pct); while(strlen($CR_four_countS)>7) {$CR_four_countS = substr("$CR_four_countS", 0, -1);}
		$CR_five_countS =	sprintf("%6.2f", $CR_five_count_pct); while(strlen($CR_five_countS)>7) {$CR_five_countS = substr("$CR_five_countS", 0, -1);}

		$CR_count_tot =	($CR_count + $CR_count_tot);
		$CR_one_count_tot =	($CR_one_count + $CR_one_count_tot);
		$CR_two_count_tot =	($CR_two_count + $CR_two_count_tot);
		$CR_three_count_tot =	($CR_three_count + $CR_three_count_tot);
		$CR_four_count_tot =	($CR_four_count + $CR_four_count_tot);
		$CR_five_count_tot =	($CR_five_count + $CR_five_count_tot);

		########## END   CUSTOMER CONTACT RATIO (Customer Contact flag out of total leads percentage) ##########
		########################################################


		########################################################
		########## BEGIN UNWORKABLE (Unworkable flag) ##########

		$UW_count=0; $UW_one_count=0; $UW_two_count=0; $UW_three_count=0; $UW_four_count=0; $UW_five_count=0; $UW_all_count=0;

		$stmt="select count(*) from vicidial_list where status IN($unworkable_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$UW_results = mysqli_num_rows($rslt);
		if ($UW_results > 0)
			{$row=mysqli_fetch_row($rslt); $UW_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=1 and status IN($unworkable_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$UW_one_results = mysqli_num_rows($rslt);
		if ($UW_one_results > 0)
			{$row=mysqli_fetch_row($rslt); $UW_one_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=2 and status IN($unworkable_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$UW_two_results = mysqli_num_rows($rslt);
		if ($UW_two_results > 0)
			{$row=mysqli_fetch_row($rslt); $UW_two_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=3 and status IN($unworkable_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$UW_three_results = mysqli_num_rows($rslt);
		if ($UW_three_results > 0)
			{$row=mysqli_fetch_row($rslt); $UW_three_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count='4' and status IN($unworkable_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$UW_four_results = mysqli_num_rows($rslt);
		if ($UW_four_results > 0)
			{$row=mysqli_fetch_row($rslt); $UW_four_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count='5' and status IN($unworkable_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$UW_five_results = mysqli_num_rows($rslt);
		if ($UW_five_results > 0)
			{$row=mysqli_fetch_row($rslt); $UW_five_count = $row[0];}
		$stmt="select count(distinct lead_id) from vicidial_log where status IN($unworkable_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$UW_all_results = mysqli_num_rows($rslt);
		if ($UW_all_results > 0)
			{$row=mysqli_fetch_row($rslt); $UW_all_count = $row[0];}
		if ($UW_all_count > $UW_count) {$UW_count = $UW_all_count;}

		$UW_countS =	sprintf("%7s", $UW_count); while(strlen($UW_countS)>7) {$UW_countS = substr("$UW_countS", 0, -1);}
		$UW_one_countS =	sprintf("%7s", $UW_one_count); while(strlen($UW_one_countS)>7) {$UW_one_countS = substr("$UW_one_countS", 0, -1);}
		$UW_two_countS =	sprintf("%7s", $UW_two_count); while(strlen($UW_two_countS)>7) {$UW_two_countS = substr("$UW_two_countS", 0, -1);}
		$UW_three_countS =	sprintf("%7s", $UW_three_count); while(strlen($UW_three_countS)>7) {$UW_three_countS = substr("$UW_three_countS", 0, -1);}
		$UW_four_countS =	sprintf("%7s", $UW_four_count); while(strlen($UW_four_countS)>7) {$UW_four_countS = substr("$UW_four_countS", 0, -1);}
		$UW_five_countS =	sprintf("%7s", $UW_five_count); while(strlen($UW_five_countS)>7) {$UW_five_countS = substr("$UW_five_countS", 0, -1);}

		$UW_count_tot =	($UW_count + $UW_count_tot);
		$UW_one_count_tot =	($UW_one_count + $UW_one_count_tot);
		$UW_two_count_tot =	($UW_two_count + $UW_two_count_tot);
		$UW_three_count_tot =	($UW_three_count + $UW_three_count_tot);
		$UW_four_count_tot =	($UW_four_count + $UW_four_count_tot);
		$UW_five_count_tot =	($UW_five_count + $UW_five_count_tot);

		########## END UNWORKABLE (Unworkable flag) ##########
		########################################################


		########################################################
		########## BEGIN UNWORKABLE RATIO (Unworkable flag out of total leads percentage) ##########

		$UR_count=$UW_count; 
		$UR_one_count=$UW_one_count;
		$UR_two_count=$UW_two_count;
		$UR_three_count=$UW_three_count;
		$UR_four_count=$UW_four_count;
		$UR_five_count=$UW_five_count;
		$UR_all_count=$UW_all_count;

		$UR_count_pct=0;
		$UR_one_count_pct=0;
		$UR_two_count_pct=0;
		$UR_three_count_pct=0;
		$UR_four_count_pct=0;
		$UR_five_count_pct=0;
		$UR_count_pct = (MathZDC($UR_count, $LISTIDcalls[$i]) * 100);
		$UR_one_count_pct = (MathZDC($UR_one_count, $LISTIDcalls[$i]) * 100);
		$UR_two_count_pct = (MathZDC($UR_two_count, $LISTIDcalls[$i]) * 100);
		$UR_three_count_pct = (MathZDC($UR_three_count, $LISTIDcalls[$i]) * 100);
		$UR_four_count_pct = (MathZDC($UR_four_count, $LISTIDcalls[$i]) * 100);
		$UR_five_count_pct = (MathZDC($UR_five_count, $LISTIDcalls[$i]) * 100);

		$UR_countS =	sprintf("%6.2f", $UR_count_pct); while(strlen($UR_countS)>7) {$UR_countS = substr("$UR_countS", 0, -1);}
		$UR_one_countS =	sprintf("%6.2f", $UR_one_count_pct); while(strlen($UR_one_countS)>7) {$UR_one_countS = substr("$UR_one_countS", 0, -1);}
		$UR_two_countS =	sprintf("%6.2f", $UR_two_count_pct); while(strlen($UR_two_countS)>7) {$UR_two_countS = substr("$UR_two_countS", 0, -1);}
		$UR_three_countS =	sprintf("%6.2f", $UR_three_count_pct); while(strlen($UR_three_countS)>7) {$UR_three_countS = substr("$UR_three_countS", 0, -1);}
		$UR_four_countS =	sprintf("%6.2f", $UR_four_count_pct); while(strlen($UR_four_countS)>7) {$UR_four_countS = substr("$UR_four_countS", 0, -1);}
		$UR_five_countS =	sprintf("%6.2f", $UR_five_count_pct); while(strlen($UR_five_countS)>7) {$UR_five_countS = substr("$UR_five_countS", 0, -1);}

		$UR_count_tot =	($UR_count + $UR_count_tot);
		$UR_one_count_tot =	($UR_one_count + $UR_one_count_tot);
		$UR_two_count_tot =	($UR_two_count + $UR_two_count_tot);
		$UR_three_count_tot =	($UR_three_count + $UR_three_count_tot);
		$UR_four_count_tot =	($UR_four_count + $UR_four_count_tot);
		$UR_five_count_tot =	($UR_five_count + $UR_five_count_tot);

		########## END   UNWORKABLE RATIO (Unworkable flag out of total leads percentage) ##########
		########################################################


		########################################################
		########## BEGIN SCHEDULED CALLBACK (Scheduled Callback flag) ##########

		$BA_count=0; $BA_one_count=0; $BA_two_count=0; $BA_three_count=0; $BA_four_count=0; $BA_five_count=0; $BA_all_count=0;

		$stmt="select count(*) from vicidial_list where status IN($scheduled_callback_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$BA_results = mysqli_num_rows($rslt);
		if ($BA_results > 0)
			{$row=mysqli_fetch_row($rslt); $BA_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=1 and status IN($scheduled_callback_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$BA_one_results = mysqli_num_rows($rslt);
		if ($BA_one_results > 0)
			{$row=mysqli_fetch_row($rslt); $BA_one_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=2 and status IN($scheduled_callback_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$BA_two_results = mysqli_num_rows($rslt);
		if ($BA_two_results > 0)
			{$row=mysqli_fetch_row($rslt); $BA_two_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=3 and status IN($scheduled_callback_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$BA_three_results = mysqli_num_rows($rslt);
		if ($BA_three_results > 0)
			{$row=mysqli_fetch_row($rslt); $BA_three_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count='4' and status IN($scheduled_callback_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$BA_four_results = mysqli_num_rows($rslt);
		if ($BA_four_results > 0)
			{$row=mysqli_fetch_row($rslt); $BA_four_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count='5' and status IN($scheduled_callback_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$BA_five_results = mysqli_num_rows($rslt);
		if ($BA_five_results > 0)
			{$row=mysqli_fetch_row($rslt); $BA_five_count = $row[0];}
		$stmt="select count(distinct lead_id) from vicidial_log where status IN($scheduled_callback_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$BA_all_results = mysqli_num_rows($rslt);
		if ($BA_all_results > 0)
			{$row=mysqli_fetch_row($rslt); $BA_all_count = $row[0];}
		if ($BA_all_count > $BA_count) {$BA_count = $BA_all_count;}

		$BA_countS =	sprintf("%7s", $BA_count); while(strlen($BA_countS)>7) {$BA_countS = substr("$BA_countS", 0, -1);}
		$BA_one_countS =	sprintf("%7s", $BA_one_count); while(strlen($BA_one_countS)>7) {$BA_one_countS = substr("$BA_one_countS", 0, -1);}
		$BA_two_countS =	sprintf("%7s", $BA_two_count); while(strlen($BA_two_countS)>7) {$BA_two_countS = substr("$BA_two_countS", 0, -1);}
		$BA_three_countS =	sprintf("%7s", $BA_three_count); while(strlen($BA_three_countS)>7) {$BA_three_countS = substr("$BA_three_countS", 0, -1);}
		$BA_four_countS =	sprintf("%7s", $BA_four_count); while(strlen($BA_four_countS)>7) {$BA_four_countS = substr("$BA_four_countS", 0, -1);}
		$BA_five_countS =	sprintf("%7s", $BA_five_count); while(strlen($BA_five_countS)>7) {$BA_five_countS = substr("$BA_five_countS", 0, -1);}

		$BA_count_tot =	($BA_count + $BA_count_tot);
		$BA_one_count_tot =	($BA_one_count + $BA_one_count_tot);
		$BA_two_count_tot =	($BA_two_count + $BA_two_count_tot);
		$BA_three_count_tot =	($BA_three_count + $BA_three_count_tot);
		$BA_four_count_tot =	($BA_four_count + $BA_four_count_tot);
		$BA_five_count_tot =	($BA_five_count + $BA_five_count_tot);

		########## END SCHEDULED CALLBACK (Scheduled Callback flag) ##########
		########################################################


		########################################################
		########## BEGIN SCHEDULED CALLBACK RATIO (Scheduled Callback flag out of total leads percentage) ##########

		$BR_count=$BA_count; 
		$BR_one_count=$BA_one_count;
		$BR_two_count=$BA_two_count;
		$BR_three_count=$BA_three_count;
		$BR_four_count=$BA_four_count;
		$BR_five_count=$BA_five_count;
		$BR_all_count=$BA_all_count;

		$BR_count_pct=0;
		$BR_one_count_pct=0;
		$BR_two_count_pct=0;
		$BR_three_count_pct=0;
		$BR_four_count_pct=0;
		$BR_five_count_pct=0;
		$BR_count_pct = (MathZDC($BR_count, $LISTIDcalls[$i]) * 100);
		$BR_one_count_pct = (MathZDC($BR_one_count, $LISTIDcalls[$i]) * 100);
		$BR_two_count_pct = (MathZDC($BR_two_count, $LISTIDcalls[$i]) * 100);
		$BR_three_count_pct = (MathZDC($BR_three_count, $LISTIDcalls[$i]) * 100);
		$BR_four_count_pct = (MathZDC($BR_four_count, $LISTIDcalls[$i]) * 100);
		$BR_five_count_pct = (MathZDC($BR_five_count, $LISTIDcalls[$i]) * 100);

		$BR_countS =	sprintf("%6.2f", $BR_count_pct); while(strlen($BR_countS)>7) {$BR_countS = substr("$BR_countS", 0, -1);}
		$BR_one_countS =	sprintf("%6.2f", $BR_one_count_pct); while(strlen($BR_one_countS)>7) {$BR_one_countS = substr("$BR_one_countS", 0, -1);}
		$BR_two_countS =	sprintf("%6.2f", $BR_two_count_pct); while(strlen($BR_two_countS)>7) {$BR_two_countS = substr("$BR_two_countS", 0, -1);}
		$BR_three_countS =	sprintf("%6.2f", $BR_three_count_pct); while(strlen($BR_three_countS)>7) {$BR_three_countS = substr("$BR_three_countS", 0, -1);}
		$BR_four_countS =	sprintf("%6.2f", $BR_four_count_pct); while(strlen($BR_four_countS)>7) {$BR_four_countS = substr("$BR_four_countS", 0, -1);}
		$BR_five_countS =	sprintf("%6.2f", $BR_five_count_pct); while(strlen($BR_five_countS)>7) {$BR_five_countS = substr("$BR_five_countS", 0, -1);}

		$BR_count_tot =	($BR_count + $BR_count_tot);
		$BR_one_count_tot =	($BR_one_count + $BR_one_count_tot);
		$BR_two_count_tot =	($BR_two_count + $BR_two_count_tot);
		$BR_three_count_tot =	($BR_three_count + $BR_three_count_tot);
		$BR_four_count_tot =	($BR_four_count + $BR_four_count_tot);
		$BR_five_count_tot =	($BR_five_count + $BR_five_count_tot);

		########## END   SCHEDULED CALLBACK RATIO (Scheduled Callback flag out of total leads percentage) ##########
		########################################################


		########################################################
		########## BEGIN COMPLETED (Completed flag) ##########

		$MP_count=0; $MP_one_count=0; $MP_two_count=0; $MP_three_count=0; $MP_four_count=0; $MP_five_count=0; $MP_all_count=0;

		$stmt="select count(*) from vicidial_list where status IN($completed_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$MP_results = mysqli_num_rows($rslt);
		if ($MP_results > 0)
			{$row=mysqli_fetch_row($rslt); $MP_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=1 and status IN($completed_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$MP_one_results = mysqli_num_rows($rslt);
		if ($MP_one_results > 0)
			{$row=mysqli_fetch_row($rslt); $MP_one_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=2 and status IN($completed_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$MP_two_results = mysqli_num_rows($rslt);
		if ($MP_two_results > 0)
			{$row=mysqli_fetch_row($rslt); $MP_two_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count=3 and status IN($completed_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$MP_three_results = mysqli_num_rows($rslt);
		if ($MP_three_results > 0)
			{$row=mysqli_fetch_row($rslt); $MP_three_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count='4' and status IN($completed_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$MP_four_results = mysqli_num_rows($rslt);
		if ($MP_four_results > 0)
			{$row=mysqli_fetch_row($rslt); $MP_four_count = $row[0];}
		$stmt="select count(*) from vicidial_log where called_count='5' and status IN($completed_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$MP_five_results = mysqli_num_rows($rslt);
		if ($MP_five_results > 0)
			{$row=mysqli_fetch_row($rslt); $MP_five_count = $row[0];}
		$stmt="select count(distinct lead_id) from vicidial_log where status IN($completed_statuses) and list_id='$LISTIDlists[$i]';";
		$rslt=mysql_to_mysqli($stmt, $link);
		if ($DB) {$MAIN.="$stmt\n";}
		$MP_all_results = mysqli_num_rows($rslt);
		if ($MP_all_results > 0)
			{$row=mysqli_fetch_row($rslt); $MP_all_count = $row[0];}
		if ($MP_all_count > $MP_count) {$MP_count = $MP_all_count;}

		$MP_countS =	sprintf("%7s", $MP_count); while(strlen($MP_countS)>7) {$MP_countS = substr("$MP_countS", 0, -1);}
		$MP_one_countS =	sprintf("%7s", $MP_one_count); while(strlen($MP_one_countS)>7) {$MP_one_countS = substr("$MP_one_countS", 0, -1);}
		$MP_two_countS =	sprintf("%7s", $MP_two_count); while(strlen($MP_two_countS)>7) {$MP_two_countS = substr("$MP_two_countS", 0, -1);}
		$MP_three_countS =	sprintf("%7s", $MP_three_count); while(strlen($MP_three_countS)>7) {$MP_three_countS = substr("$MP_three_countS", 0, -1);}
		$MP_four_countS =	sprintf("%7s", $MP_four_count); while(strlen($MP_four_countS)>7) {$MP_four_countS = substr("$MP_four_countS", 0, -1);}
		$MP_five_countS =	sprintf("%7s", $MP_five_count); while(strlen($MP_five_countS)>7) {$MP_five_countS = substr("$MP_five_countS", 0, -1);}

		$MP_count_tot =	($MP_count + $MP_count_tot);
		$MP_one_count_tot =	($MP_one_count + $MP_one_count_tot);
		$MP_two_count_tot =	($MP_two_count + $MP_two_count_tot);
		$MP_three_count_tot =	($MP_three_count + $MP_three_count_tot);
		$MP_four_count_tot =	($MP_four_count + $MP_four_count_tot);
		$MP_five_count_tot =	($MP_five_count + $MP_five_count_tot);

		########## END COMPLETED (Completed Callback flag) ##########
		########################################################


		########################################################
		########## BEGIN COMPLETED RATIO (Completed flag out of total leads percentage) ##########

		$MR_count=$MP_count; 
		$MR_one_count=$MP_one_count;
		$MR_two_count=$MP_two_count;
		$MR_three_count=$MP_three_count;
		$MR_four_count=$MP_four_count;
		$MR_five_count=$MP_five_count;
		$MR_all_count=$MP_all_count;

		$MR_count_pct=0;
		$MR_one_count_pct=0;
		$MR_two_count_pct=0;
		$MR_three_count_pct=0;
		$MR_four_count_pct=0;
		$MR_five_count_pct=0;
		$MR_count_pct = (MathZDC($MR_count, $LISTIDcalls[$i]) * 100);
		$MR_one_count_pct = (MathZDC($MR_one_count, $LISTIDcalls[$i]) * 100);
		$MR_two_count_pct = (MathZDC($MR_two_count, $LISTIDcalls[$i]) * 100);
		$MR_three_count_pct = (MathZDC($MR_three_count, $LISTIDcalls[$i]) * 100);
		$MR_four_count_pct = (MathZDC($MR_four_count, $LISTIDcalls[$i]) * 100);
		$MR_five_count_pct = (MathZDC($MR_five_count, $LISTIDcalls[$i]) * 100);

		$MR_countS =	sprintf("%6.2f", $MR_count_pct); while(strlen($MR_countS)>7) {$MR_countS = substr("$MR_countS", 0, -1);}
		$MR_one_countS =	sprintf("%6.2f", $MR_one_count_pct); while(strlen($MR_one_countS)>7) {$MR_one_countS = substr("$MR_one_countS", 0, -1);}
		$MR_two_countS =	sprintf("%6.2f", $MR_two_count_pct); while(strlen($MR_two_countS)>7) {$MR_two_countS = substr("$MR_two_countS", 0, -1);}
		$MR_three_countS =	sprintf("%6.2f", $MR_three_count_pct); while(strlen($MR_three_countS)>7) {$MR_three_countS = substr("$MR_three_countS", 0, -1);}
		$MR_four_countS =	sprintf("%6.2f", $MR_four_count_pct); while(strlen($MR_four_countS)>7) {$MR_four_countS = substr("$MR_four_countS", 0, -1);}
		$MR_five_countS =	sprintf("%6.2f", $MR_five_count_pct); while(strlen($MR_five_countS)>7) {$MR_five_countS = substr("$MR_five_countS", 0, -1);}

		$MR_count_tot =	($MR_count + $MR_count_tot);
		$MR_one_count_tot =	($MR_one_count + $MR_one_count_tot);
		$MR_two_count_tot =	($MR_two_count + $MR_two_count_tot);
		$MR_three_count_tot =	($MR_three_count + $MR_three_count_tot);
		$MR_four_count_tot =	($MR_four_count + $MR_four_count_tot);
		$MR_five_count_tot =	($MR_five_count + $MR_five_count_tot);

		########## END   COMPLETED RATIO (Completed flag out of total leads percentage) ##########
		########################################################



		$OUToutput .= "| $LISTIDentry_dateS | $LISTIDname | $LISTIDcampaignS | $LISTIDcount | $LISTIDlist_active[$i] ";
		$OUToutput .= "| $HA_one_countS | $HA_two_countS | $HA_three_countS | $HA_four_countS | $HA_five_countS | $HA_countS ";
		$OUToutput .= "| $HR_one_countS% | $HR_two_countS% | $HR_three_countS% | $HR_four_countS% | $HR_five_countS% | $HR_countS% ";
		$OUToutput .= "| $SA_one_countS | $SA_two_countS | $SA_three_countS | $SA_four_countS | $SA_five_countS | $SA_countS ";
		$OUToutput .= "| $SR_one_countS% | $SR_two_countS% | $SR_three_countS% | $SR_four_countS% | $SR_five_countS% | $SR_countS% ";
		$OUToutput .= "| $DN_one_countS | $DN_two_countS | $DN_three_countS | $DN_four_countS | $DN_five_countS | $DN_countS ";
		$OUToutput .= "| $DR_one_countS% | $DR_two_countS% | $DR_three_countS% | $DR_four_countS% | $DR_five_countS% | $DR_countS% ";
		$OUToutput .= "| $CC_one_countS | $CC_two_countS | $CC_three_countS | $CC_four_countS | $CC_five_countS | $CC_countS ";
		$OUToutput .= "| $CR_one_countS% | $CR_two_countS% | $CR_three_countS% | $CR_four_countS% | $CR_five_countS% | $CR_countS% ";
		$OUToutput .= "| $UW_one_countS | $UW_two_countS | $UW_three_countS | $UW_four_countS | $UW_five_countS | $UW_countS ";
		$OUToutput .= "| $UR_one_countS% | $UR_two_countS% | $UR_three_countS% | $UR_four_countS% | $UR_five_countS% | $UR_countS% ";
		$OUToutput .= "| $BA_one_countS | $BA_two_countS | $BA_three_countS | $BA_four_countS | $BA_five_countS | $BA_countS ";
		$OUToutput .= "| $BR_one_countS% | $BR_two_countS% | $BR_three_countS% | $BR_four_countS% | $BR_five_countS% | $BR_countS% ";
		$OUToutput .= "| $MP_one_countS | $MP_two_countS | $MP_three_countS | $MP_four_countS | $MP_five_countS | $MP_countS ";
		$OUToutput .= "| $MR_one_countS% | $MR_two_countS% | $MR_three_countS% | $MR_four_countS% | $MR_five_countS% | $MR_countS% ";
		$OUToutput .= "|\n";

		$CSV_text1.="\"$LISTIDentry_dateS\",\"$LISTIDname\",\"$LISTIDcampaignS\",\"$LISTIDcount\",\"$LISTIDlist_active[$i]\"";
		$CSV_text1.=",\"$HA_one_countS\",\"$HA_two_countS\",\"$HA_three_countS\",\"$HA_four_countS\",\"$HA_five_countS\",\"$HA_countS\"";
		$CSV_text1.=",\"$HR_one_countS%\",\"$HR_two_countS%\",\"$HR_three_countS%\",\"$HR_four_countS%\",\"$HR_five_countS%\",\"$HR_countS%\"";
		$CSV_text1.=",\"$SA_one_countS\",\"$SA_two_countS\",\"$SA_three_countS\",\"$SA_four_countS\",\"$SA_five_countS\",\"$SA_countS\"";
		$CSV_text1.=",\"$SR_one_countS%\",\"$SR_two_countS%\",\"$SR_three_countS%\",\"$SR_four_countS%\",\"$SR_five_countS%\",\"$SR_countS%\"";
		$CSV_text1.=",\"$DN_one_countS\",\"$DN_two_countS\",\"$DN_three_countS\",\"$DN_four_countS\",\"$DN_five_countS\",\"$DN_countS\"";
		$CSV_text1.=",\"$DR_one_countS%\",\"$DR_two_countS%\",\"$DR_three_countS%\",\"$DR_four_countS%\",\"$DR_five_countS%\",\"$DR_countS%\"";
		$CSV_text1.=",\"$CC_one_countS\",\"$CC_two_countS\",\"$CC_three_countS\",\"$CC_four_countS\",\"$CC_five_countS\",\"$CC_countS\"";
		$CSV_text1.=",\"$CR_one_countS%\",\"$CR_two_countS%\",\"$CR_three_countS%\",\"$CR_four_countS%\",\"$CR_five_countS%\",\"$CR_countS%\"";
		$CSV_text1.=",\"$UW_one_countS\",\"$UW_two_countS\",\"$UW_three_countS\",\"$UW_four_countS\",\"$UW_five_countS\",\"$UW_countS\"";
		$CSV_text1.=",\"$UR_one_countS%\",\"$UR_two_countS%\",\"$UR_three_countS%\",\"$UR_four_countS%\",\"$UR_five_countS%\",\"$UR_countS%\"";
		$CSV_text1.=",\"$BA_one_countS\",\"$BA_two_countS\",\"$BA_three_countS\",\"$BA_four_countS\",\"$BA_five_countS\",\"$BA_countS\"";
		$CSV_text1.=",\"$BR_one_countS%\",\"$BR_two_countS%\",\"$BR_three_countS%\",\"$BR_four_countS%\",\"$BR_five_countS%\",\"$BR_countS%\"";
		$CSV_text1.=",\"$MP_one_countS\",\"$MP_two_countS\",\"$MP_three_countS\",\"$MP_four_countS\",\"$MP_five_countS\",\"$MP_countS\"";
		$CSV_text1.=",\"$MR_one_countS%\",\"$MR_two_countS%\",\"$MR_three_countS%\",\"$MR_four_countS%\",\"$MR_five_countS%\",\"$MR_countS%\"";
		$CSV_text1.="\n";
			
		$graph_stats2[$i][1]=$HA_one_countS;
		$graph_stats2[$i][2]=$HA_two_countS;
		$graph_stats2[$i][3]=$HA_three_countS;
		$graph_stats2[$i][4]=$HA_four_countS;
		$graph_stats2[$i][5]=$HA_five_countS;
		$graph_stats2[$i][6]=$HA_countS;
		$graph_stats2[$i][7]=$HR_one_countS;
		$graph_stats2[$i][8]=$HR_two_countS;
		$graph_stats2[$i][9]=$HR_three_countS;
		$graph_stats2[$i][10]=$HR_four_countS;
		$graph_stats2[$i][11]=$HR_five_countS;
		$graph_stats2[$i][12]=$HR_countS;
		$graph_stats2[$i][13]=$SA_one_countS;
		$graph_stats2[$i][14]=$SA_two_countS;
		$graph_stats2[$i][15]=$SA_three_countS;
		$graph_stats2[$i][16]=$SA_four_countS;
		$graph_stats2[$i][17]=$SA_five_countS;
		$graph_stats2[$i][18]=$SA_countS;
		$graph_stats2[$i][19]=$SR_one_countS;
		$graph_stats2[$i][20]=$SR_two_countS;
		$graph_stats2[$i][21]=$SR_three_countS;
		$graph_stats2[$i][22]=$SR_four_countS;
		$graph_stats2[$i][23]=$SR_five_countS;
		$graph_stats2[$i][24]=$SR_countS;
		$graph_stats2[$i][25]=$DN_one_countS;
		$graph_stats2[$i][26]=$DN_two_countS;
		$graph_stats2[$i][27]=$DN_three_countS;
		$graph_stats2[$i][28]=$DN_four_countS;
		$graph_stats2[$i][29]=$DN_five_countS;
		$graph_stats2[$i][30]=$DN_countS;
		$graph_stats2[$i][31]=$DR_one_countS;
		$graph_stats2[$i][32]=$DR_two_countS;
		$graph_stats2[$i][33]=$DR_three_countS;
		$graph_stats2[$i][34]=$DR_four_countS;
		$graph_stats2[$i][35]=$DR_five_countS;
		$graph_stats2[$i][36]=$DR_countS;
		$graph_stats2[$i][37]=$CC_one_countS;
		$graph_stats2[$i][38]=$CC_two_countS;
		$graph_stats2[$i][39]=$CC_three_countS;
		$graph_stats2[$i][40]=$CC_four_countS;
		$graph_stats2[$i][41]=$CC_five_countS;
		$graph_stats2[$i][42]=$CC_countS;
		$graph_stats2[$i][43]=$CR_one_countS;
		$graph_stats2[$i][44]=$CR_two_countS;
		$graph_stats2[$i][45]=$CR_three_countS;
		$graph_stats2[$i][46]=$CR_four_countS;
		$graph_stats2[$i][47]=$CR_five_countS;
		$graph_stats2[$i][48]=$CR_countS;
		$graph_stats2[$i][49]=$UW_one_countS;
		$graph_stats2[$i][50]=$UW_two_countS;
		$graph_stats2[$i][51]=$UW_three_countS;
		$graph_stats2[$i][52]=$UW_four_countS;
		$graph_stats2[$i][53]=$UW_five_countS;
		$graph_stats2[$i][54]=$UW_countS;
		$graph_stats2[$i][55]=$UR_one_countS;
		$graph_stats2[$i][56]=$UR_two_countS;
		$graph_stats2[$i][57]=$UR_three_countS;
		$graph_stats2[$i][58]=$UR_four_countS;
		$graph_stats2[$i][59]=$UR_five_countS;
		$graph_stats2[$i][60]=$UR_countS;
		$graph_stats2[$i][61]=$BA_one_countS;
		$graph_stats2[$i][62]=$BA_two_countS;
		$graph_stats2[$i][63]=$BA_three_countS;
		$graph_stats2[$i][64]=$BA_four_countS;
		$graph_stats2[$i][65]=$BA_five_countS;
		$graph_stats2[$i][66]=$BA_countS;
		$graph_stats2[$i][67]=$BR_one_countS;
		$graph_stats2[$i][68]=$BR_two_countS;
		$graph_stats2[$i][69]=$BR_three_countS;
		$graph_stats2[$i][70]=$BR_four_countS;
		$graph_stats2[$i][71]=$BR_five_countS;
		$graph_stats2[$i][72]=$BR_countS;
		$graph_stats2[$i][73]=$MP_one_countS;
		$graph_stats2[$i][74]=$MP_two_countS;
		$graph_stats2[$i][75]=$MP_three_countS;
		$graph_stats2[$i][76]=$MP_four_countS;
		$graph_stats2[$i][77]=$MP_five_countS;
		$graph_stats2[$i][78]=$MP_countS;
		$graph_stats2[$i][79]=$MR_one_countS;
		$graph_stats2[$i][80]=$MR_two_countS;
		$graph_stats2[$i][81]=$MR_three_countS;
		$graph_stats2[$i][82]=$MR_four_countS;
		$graph_stats2[$i][83]=$MR_five_countS;
		$graph_stats2[$i][84]=$MR_countS;


		$i++;
		}

	// CYCLE THROUGH ARRAY TO SEE IF ANY NEW MAX VARS
	for ($q=0; $q<count($graph_stats2); $q++) {
		for ($x=1; $x<count($graph_stats2[$q]); $x++) {
			$graph_stats2[$q][$x]=trim($graph_stats2[$q][$x]);
			if ($graph_stats2[$q][$x]>$max_stats2[$x]) {$max_stats2[$x]=$graph_stats2[$q][$x];}
		}
	}


	$HA_count_totS =	sprintf("%7s", $HA_count_tot); while(strlen($HA_count_totS)>7) {$HA_count_totS = substr("$HA_count_totS", 0, -1);}
	$HA_one_count_totS =	sprintf("%7s", $HA_one_count_tot); while(strlen($HA_one_count_totS)>7) {$HA_one_count_totS = substr("$HA_one_count_totS", 0, -1);}
	$HA_two_count_totS =	sprintf("%7s", $HA_two_count_tot); while(strlen($HA_two_count_totS)>7) {$HA_two_count_totS = substr("$HA_two_count_totS", 0, -1);}
	$HA_three_count_totS =	sprintf("%7s", $HA_three_count_tot); while(strlen($HA_three_count_totS)>7) {$HA_three_count_totS = substr("$HA_three_count_totS", 0, -1);}
	$HA_four_count_totS =	sprintf("%7s", $HA_four_count_tot); while(strlen($HA_four_count_totS)>7) {$HA_four_count_totS = substr("$HA_four_count_totS", 0, -1);}
	$HA_five_count_totS =	sprintf("%7s", $HA_five_count_tot); while(strlen($HA_five_count_totS)>7) {$HA_five_count_totS = substr("$HA_five_count_totS", 0, -1);}

	$SA_count_totS =	sprintf("%7s", $SA_count_tot); while(strlen($SA_count_totS)>7) {$SA_count_totS = substr("$SA_count_totS", 0, -1);}
	$SA_one_count_totS =	sprintf("%7s", $SA_one_count_tot); while(strlen($SA_one_count_totS)>7) {$SA_one_count_totS = substr("$SA_one_count_totS", 0, -1);}
	$SA_two_count_totS =	sprintf("%7s", $SA_two_count_tot); while(strlen($SA_two_count_totS)>7) {$SA_two_count_totS = substr("$SA_two_count_totS", 0, -1);}
	$SA_three_count_totS =	sprintf("%7s", $SA_three_count_tot); while(strlen($SA_three_count_totS)>7) {$SA_three_count_totS = substr("$SA_three_count_totS", 0, -1);}
	$SA_four_count_totS =	sprintf("%7s", $SA_four_count_tot); while(strlen($SA_four_count_totS)>7) {$SA_four_count_totS = substr("$SA_four_count_totS", 0, -1);}
	$SA_five_count_totS =	sprintf("%7s", $SA_five_count_tot); while(strlen($SA_five_count_totS)>7) {$SA_five_count_totS = substr("$SA_five_count_totS", 0, -1);}

	$DN_count_totS =	sprintf("%7s", $DN_count_tot); while(strlen($DN_count_totS)>7) {$DN_count_totS = substr("$DN_count_totS", 0, -1);}
	$DN_one_count_totS =	sprintf("%7s", $DN_one_count_tot); while(strlen($DN_one_count_totS)>7) {$DN_one_count_totS = substr("$DN_one_count_totS", 0, -1);}
	$DN_two_count_totS =	sprintf("%7s", $DN_two_count_tot); while(strlen($DN_two_count_totS)>7) {$DN_two_count_totS = substr("$DN_two_count_totS", 0, -1);}
	$DN_three_count_totS =	sprintf("%7s", $DN_three_count_tot); while(strlen($DN_three_count_totS)>7) {$DN_three_count_totS = substr("$DN_three_count_totS", 0, -1);}
	$DN_four_count_totS =	sprintf("%7s", $DN_four_count_tot); while(strlen($DN_four_count_totS)>7) {$DN_four_count_totS = substr("$DN_four_count_totS", 0, -1);}
	$DN_five_count_totS =	sprintf("%7s", $DN_five_count_tot); while(strlen($DN_five_count_totS)>7) {$DN_five_count_totS = substr("$DN_five_count_totS", 0, -1);}

	$CC_count_totS =	sprintf("%7s", $CC_count_tot); while(strlen($CC_count_totS)>7) {$CC_count_totS = substr("$CC_count_totS", 0, -1);}
	$CC_one_count_totS =	sprintf("%7s", $CC_one_count_tot); while(strlen($CC_one_count_totS)>7) {$CC_one_count_totS = substr("$CC_one_count_totS", 0, -1);}
	$CC_two_count_totS =	sprintf("%7s", $CC_two_count_tot); while(strlen($CC_two_count_totS)>7) {$CC_two_count_totS = substr("$CC_two_count_totS", 0, -1);}
	$CC_three_count_totS =	sprintf("%7s", $CC_three_count_tot); while(strlen($CC_three_count_totS)>7) {$CC_three_count_totS = substr("$CC_three_count_totS", 0, -1);}
	$CC_four_count_totS =	sprintf("%7s", $CC_four_count_tot); while(strlen($CC_four_count_totS)>7) {$CC_four_count_totS = substr("$CC_four_count_totS", 0, -1);}
	$CC_five_count_totS =	sprintf("%7s", $CC_five_count_tot); while(strlen($CC_five_count_totS)>7) {$CC_five_count_totS = substr("$CC_five_count_totS", 0, -1);}

	$UW_count_totS =	sprintf("%7s", $UW_count_tot); while(strlen($UW_count_totS)>7) {$UW_count_totS = substr("$UW_count_totS", 0, -1);}
	$UW_one_count_totS =	sprintf("%7s", $UW_one_count_tot); while(strlen($UW_one_count_totS)>7) {$UW_one_count_totS = substr("$UW_one_count_totS", 0, -1);}
	$UW_two_count_totS =	sprintf("%7s", $UW_two_count_tot); while(strlen($UW_two_count_totS)>7) {$UW_two_count_totS = substr("$UW_two_count_totS", 0, -1);}
	$UW_three_count_totS =	sprintf("%7s", $UW_three_count_tot); while(strlen($UW_three_count_totS)>7) {$UW_three_count_totS = substr("$UW_three_count_totS", 0, -1);}
	$UW_four_count_totS =	sprintf("%7s", $UW_four_count_tot); while(strlen($UW_four_count_totS)>7) {$UW_four_count_totS = substr("$UW_four_count_totS", 0, -1);}
	$UW_five_count_totS =	sprintf("%7s", $UW_five_count_tot); while(strlen($UW_five_count_totS)>7) {$UW_five_count_totS = substr("$UW_five_count_totS", 0, -1);}

	$BA_count_totS =	sprintf("%7s", $BA_count_tot); while(strlen($BA_count_totS)>7) {$BA_count_totS = substr("$BA_count_totS", 0, -1);}
	$BA_one_count_totS =	sprintf("%7s", $BA_one_count_tot); while(strlen($BA_one_count_totS)>7) {$BA_one_count_totS = substr("$BA_one_count_totS", 0, -1);}
	$BA_two_count_totS =	sprintf("%7s", $BA_two_count_tot); while(strlen($BA_two_count_totS)>7) {$BA_two_count_totS = substr("$BA_two_count_totS", 0, -1);}
	$BA_three_count_totS =	sprintf("%7s", $BA_three_count_tot); while(strlen($BA_three_count_totS)>7) {$BA_three_count_totS = substr("$BA_three_count_totS", 0, -1);}
	$BA_four_count_totS =	sprintf("%7s", $BA_four_count_tot); while(strlen($BA_four_count_totS)>7) {$BA_four_count_totS = substr("$BA_four_count_totS", 0, -1);}
	$BA_five_count_totS =	sprintf("%7s", $BA_five_count_tot); while(strlen($BA_five_count_totS)>7) {$BA_five_count_totS = substr("$BA_five_count_totS", 0, -1);}

	$MP_count_totS =	sprintf("%7s", $MP_count_tot); while(strlen($MP_count_totS)>7) {$MP_count_totS = substr("$MP_count_totS", 0, -1);}
	$MP_one_count_totS =	sprintf("%7s", $MP_one_count_tot); while(strlen($MP_one_count_totS)>7) {$MP_one_count_totS = substr("$MP_one_count_totS", 0, -1);}
	$MP_two_count_totS =	sprintf("%7s", $MP_two_count_tot); while(strlen($MP_two_count_totS)>7) {$MP_two_count_totS = substr("$MP_two_count_totS", 0, -1);}
	$MP_three_count_totS =	sprintf("%7s", $MP_three_count_tot); while(strlen($MP_three_count_totS)>7) {$MP_three_count_totS = substr("$MP_three_count_totS", 0, -1);}
	$MP_four_count_totS =	sprintf("%7s", $MP_four_count_tot); while(strlen($MP_four_count_totS)>7) {$MP_four_count_totS = substr("$MP_four_count_totS", 0, -1);}
	$MP_five_count_totS =	sprintf("%7s", $MP_five_count_tot); while(strlen($MP_five_count_totS)>7) {$MP_five_count_totS = substr("$MP_five_count_totS", 0, -1);}

	$HR_count_Tpc=0;
	$HR_one_count_Tpc=0;
	$HR_two_count_Tpc=0;
	$HR_three_count_Tpc=0;
	$HR_four_count_Tpc=0;
	$HR_five_count_Tpc=0;
	$HR_count_Tpc = (MathZDC($HR_count_tot, $TOTALleads) * 100);
	$HR_one_count_Tpc = (MathZDC($HR_one_count_tot, $TOTALleads) * 100);
	$HR_two_count_Tpc = (MathZDC($HR_two_count_tot, $TOTALleads) * 100);
	$HR_three_count_Tpc = (MathZDC($HR_three_count_tot, $TOTALleads) * 100);
	$HR_four_count_Tpc = (MathZDC($HR_four_count_tot, $TOTALleads) * 100);
	$HR_five_count_Tpc = (MathZDC($HR_five_count_tot, $TOTALleads) * 100);

	$HR_count_totS =	sprintf("%6.2f", $HR_count_Tpc); while(strlen($HR_count_totS)>7) {$HR_count_totS = substr("$HR_count_totS", 0, -1);}
	$HR_one_count_totS =	sprintf("%6.2f", $HR_one_count_Tpc); while(strlen($HR_one_count_totS)>7) {$HR_one_count_totS = substr("$HR_one_count_totS", 0, -1);}
	$HR_two_count_totS =	sprintf("%6.2f", $HR_two_count_Tpc); while(strlen($HR_two_count_totS)>7) {$HR_two_count_totS = substr("$HR_two_count_totS", 0, -1);}
	$HR_three_count_totS =	sprintf("%6.2f", $HR_three_count_Tpc); while(strlen($HR_three_count_totS)>7) {$HR_three_count_totS = substr("$HR_three_count_totS", 0, -1);}
	$HR_four_count_totS =	sprintf("%6.2f", $HR_four_count_Tpc); while(strlen($HR_four_count_totS)>7) {$HR_four_count_totS = substr("$HR_four_count_totS", 0, -1);}
	$HR_five_count_totS =	sprintf("%6.2f", $HR_five_count_Tpc); while(strlen($HR_five_count_totS)>7) {$HR_five_count_totS = substr("$HR_five_count_totS", 0, -1);}

	$SR_count_Tpc=0;
	$SR_one_count_Tpc=0;
	$SR_two_count_Tpc=0;
	$SR_three_count_Tpc=0;
	$SR_four_count_Tpc=0;
	$SR_five_count_Tpc=0;
	$SR_count_Tpc = (MathZDC($SR_count_tot, $TOTALleads) * 100);
	$SR_one_count_Tpc = (MathZDC($SR_one_count_tot, $TOTALleads) * 100);
	$SR_two_count_Tpc = (MathZDC($SR_two_count_tot, $TOTALleads) * 100);
	$SR_three_count_Tpc = (MathZDC($SR_three_count_tot, $TOTALleads) * 100);
	$SR_four_count_Tpc = (MathZDC($SR_four_count_tot, $TOTALleads) * 100);
	$SR_five_count_Tpc = (MathZDC($SR_five_count_tot, $TOTALleads) * 100);

	$SR_count_totS =	sprintf("%6.2f", $SR_count_Tpc); while(strlen($SR_count_totS)>7) {$SR_count_totS = substr("$SR_count_totS", 0, -1);}
	$SR_one_count_totS =	sprintf("%6.2f", $SR_one_count_Tpc); while(strlen($SR_one_count_totS)>7) {$SR_one_count_totS = substr("$SR_one_count_totS", 0, -1);}
	$SR_two_count_totS =	sprintf("%6.2f", $SR_two_count_Tpc); while(strlen($SR_two_count_totS)>7) {$SR_two_count_totS = substr("$SR_two_count_totS", 0, -1);}
	$SR_three_count_totS =	sprintf("%6.2f", $SR_three_count_Tpc); while(strlen($SR_three_count_totS)>7) {$SR_three_count_totS = substr("$SR_three_count_totS", 0, -1);}
	$SR_four_count_totS =	sprintf("%6.2f", $SR_four_count_Tpc); while(strlen($SR_four_count_totS)>7) {$SR_four_count_totS = substr("$SR_four_count_totS", 0, -1);}
	$SR_five_count_totS =	sprintf("%6.2f", $SR_five_count_Tpc); while(strlen($SR_five_count_totS)>7) {$SR_five_count_totS = substr("$SR_five_count_totS", 0, -1);}

	$DR_count_Tpc=0;
	$DR_one_count_Tpc=0;
	$DR_two_count_Tpc=0;
	$DR_three_count_Tpc=0;
	$DR_four_count_Tpc=0;
	$DR_five_count_Tpc=0;
	$DR_count_Tpc = (MathZDC($DR_count_tot, $TOTALleads) * 100);
	$DR_one_count_Tpc = (MathZDC($DR_one_count_tot, $TOTALleads) * 100);
	$DR_two_count_Tpc = (MathZDC($DR_two_count_tot, $TOTALleads) * 100);
	$DR_three_count_Tpc = (MathZDC($DR_three_count_tot, $TOTALleads) * 100);
	$DR_four_count_Tpc = (MathZDC($DR_four_count_tot, $TOTALleads) * 100);
	$DR_five_count_Tpc = (MathZDC($DR_five_count_tot, $TOTALleads) * 100);

	$DR_count_totS =	sprintf("%6.2f", $DR_count_Tpc); while(strlen($DR_count_totS)>7) {$DR_count_totS = substr("$DR_count_totS", 0, -1);}
	$DR_one_count_totS =	sprintf("%6.2f", $DR_one_count_Tpc); while(strlen($DR_one_count_totS)>7) {$DR_one_count_totS = substr("$DR_one_count_totS", 0, -1);}
	$DR_two_count_totS =	sprintf("%6.2f", $DR_two_count_Tpc); while(strlen($DR_two_count_totS)>7) {$DR_two_count_totS = substr("$DR_two_count_totS", 0, -1);}
	$DR_three_count_totS =	sprintf("%6.2f", $DR_three_count_Tpc); while(strlen($DR_three_count_totS)>7) {$DR_three_count_totS = substr("$DR_three_count_totS", 0, -1);}
	$DR_four_count_totS =	sprintf("%6.2f", $DR_four_count_Tpc); while(strlen($DR_four_count_totS)>7) {$DR_four_count_totS = substr("$DR_four_count_totS", 0, -1);}
	$DR_five_count_totS =	sprintf("%6.2f", $DR_five_count_Tpc); while(strlen($DR_five_count_totS)>7) {$DR_five_count_totS = substr("$DR_five_count_totS", 0, -1);}

	$CR_count_Tpc=0;
	$CR_one_count_Tpc=0;
	$CR_two_count_Tpc=0;
	$CR_three_count_Tpc=0;
	$CR_four_count_Tpc=0;
	$CR_five_count_Tpc=0;
	$CR_count_Tpc = (MathZDC($CR_count_tot, $TOTALleads) * 100);
	$CR_one_count_Tpc = (MathZDC($CR_one_count_tot, $TOTALleads) * 100);
	$CR_two_count_Tpc = (MathZDC($CR_two_count_tot, $TOTALleads) * 100);
	$CR_three_count_Tpc = (MathZDC($CR_three_count_tot, $TOTALleads) * 100);
	$CR_four_count_Tpc = (MathZDC($CR_four_count_tot, $TOTALleads) * 100);
	$CR_five_count_Tpc = (MathZDC($CR_five_count_tot, $TOTALleads) * 100);

	$CR_count_totS =	sprintf("%6.2f", $CR_count_Tpc); while(strlen($CR_count_totS)>7) {$CR_count_totS = substr("$CR_count_totS", 0, -1);}
	$CR_one_count_totS =	sprintf("%6.2f", $CR_one_count_Tpc); while(strlen($CR_one_count_totS)>7) {$CR_one_count_totS = substr("$CR_one_count_totS", 0, -1);}
	$CR_two_count_totS =	sprintf("%6.2f", $CR_two_count_Tpc); while(strlen($CR_two_count_totS)>7) {$CR_two_count_totS = substr("$CR_two_count_totS", 0, -1);}
	$CR_three_count_totS =	sprintf("%6.2f", $CR_three_count_Tpc); while(strlen($CR_three_count_totS)>7) {$CR_three_count_totS = substr("$CR_three_count_totS", 0, -1);}
	$CR_four_count_totS =	sprintf("%6.2f", $CR_four_count_Tpc); while(strlen($CR_four_count_totS)>7) {$CR_four_count_totS = substr("$CR_four_count_totS", 0, -1);}
	$CR_five_count_totS =	sprintf("%6.2f", $CR_five_count_Tpc); while(strlen($CR_five_count_totS)>7) {$CR_five_count_totS = substr("$CR_five_count_totS", 0, -1);}

	$UR_count_Tpc=0;
	$UR_one_count_Tpc=0;
	$UR_two_count_Tpc=0;
	$UR_three_count_Tpc=0;
	$UR_four_count_Tpc=0;
	$UR_five_count_Tpc=0;
	$UR_count_Tpc = (MathZDC($UR_count_tot, $TOTALleads) * 100);
	$UR_one_count_Tpc = (MathZDC($UR_one_count_tot, $TOTALleads) * 100);
	$UR_two_count_Tpc = (MathZDC($UR_two_count_tot, $TOTALleads) * 100);
	$UR_three_count_Tpc = (MathZDC($UR_three_count_tot, $TOTALleads) * 100);
	$UR_four_count_Tpc = (MathZDC($UR_four_count_tot, $TOTALleads) * 100);
	$UR_five_count_Tpc = (MathZDC($UR_five_count_tot, $TOTALleads) * 100);

	$UR_count_totS =	sprintf("%6.2f", $UR_count_Tpc); while(strlen($UR_count_totS)>7) {$UR_count_totS = substr("$UR_count_totS", 0, -1);}
	$UR_one_count_totS =	sprintf("%6.2f", $UR_one_count_Tpc); while(strlen($UR_one_count_totS)>7) {$UR_one_count_totS = substr("$UR_one_count_totS", 0, -1);}
	$UR_two_count_totS =	sprintf("%6.2f", $UR_two_count_Tpc); while(strlen($UR_two_count_totS)>7) {$UR_two_count_totS = substr("$UR_two_count_totS", 0, -1);}
	$UR_three_count_totS =	sprintf("%6.2f", $UR_three_count_Tpc); while(strlen($UR_three_count_totS)>7) {$UR_three_count_totS = substr("$UR_three_count_totS", 0, -1);}
	$UR_four_count_totS =	sprintf("%6.2f", $UR_four_count_Tpc); while(strlen($UR_four_count_totS)>7) {$UR_four_count_totS = substr("$UR_four_count_totS", 0, -1);}
	$UR_five_count_totS =	sprintf("%6.2f", $UR_five_count_Tpc); while(strlen($UR_five_count_totS)>7) {$UR_five_count_totS = substr("$UR_five_count_totS", 0, -1);}

	$BR_count_Tpc=0;
	$BR_one_count_Tpc=0;
	$BR_two_count_Tpc=0;
	$BR_three_count_Tpc=0;
	$BR_four_count_Tpc=0;
	$BR_five_count_Tpc=0;
	$BR_count_Tpc = (MathZDC($BR_count_tot, $TOTALleads) * 100);
	$BR_one_count_Tpc = (MathZDC($BR_one_count_tot, $TOTALleads) * 100);
	$BR_two_count_Tpc = (MathZDC($BR_two_count_tot, $TOTALleads) * 100);
	$BR_three_count_Tpc = (MathZDC($BR_three_count_tot, $TOTALleads) * 100);
	$BR_four_count_Tpc = (MathZDC($BR_four_count_tot, $TOTALleads) * 100);
	$BR_five_count_Tpc = (MathZDC($BR_five_count_tot, $TOTALleads) * 100);

	$BR_count_totS =	sprintf("%6.2f", $BR_count_Tpc); while(strlen($BR_count_totS)>7) {$BR_count_totS = substr("$BR_count_totS", 0, -1);}
	$BR_one_count_totS =	sprintf("%6.2f", $BR_one_count_Tpc); while(strlen($BR_one_count_totS)>7) {$BR_one_count_totS = substr("$BR_one_count_totS", 0, -1);}
	$BR_two_count_totS =	sprintf("%6.2f", $BR_two_count_Tpc); while(strlen($BR_two_count_totS)>7) {$BR_two_count_totS = substr("$BR_two_count_totS", 0, -1);}
	$BR_three_count_totS =	sprintf("%6.2f", $BR_three_count_Tpc); while(strlen($BR_three_count_totS)>7) {$BR_three_count_totS = substr("$BR_three_count_totS", 0, -1);}
	$BR_four_count_totS =	sprintf("%6.2f", $BR_four_count_Tpc); while(strlen($BR_four_count_totS)>7) {$BR_four_count_totS = substr("$BR_four_count_totS", 0, -1);}
	$BR_five_count_totS =	sprintf("%6.2f", $BR_five_count_Tpc); while(strlen($BR_five_count_totS)>7) {$BR_five_count_totS = substr("$BR_five_count_totS", 0, -1);}

	$MR_count_Tpc=0;
	$MR_one_count_Tpc=0;
	$MR_two_count_Tpc=0;
	$MR_three_count_Tpc=0;
	$MR_four_count_Tpc=0;
	$MR_five_count_Tpc=0;
	$MR_count_Tpc = (MathZDC($MR_count_tot, $TOTALleads) * 100);
	$MR_one_count_Tpc = (MathZDC($MR_one_count_tot, $TOTALleads) * 100);
	$MR_two_count_Tpc = (MathZDC($MR_two_count_tot, $TOTALleads) * 100);
	$MR_three_count_Tpc = (MathZDC($MR_three_count_tot, $TOTALleads) * 100);
	$MR_four_count_Tpc = (MathZDC($MR_four_count_tot, $TOTALleads) * 100);
	$MR_five_count_Tpc = (MathZDC($MR_five_count_tot, $TOTALleads) * 100);

	$MR_count_totS =	sprintf("%6.2f", $MR_count_Tpc); while(strlen($MR_count_totS)>7) {$MR_count_totS = substr("$MR_count_totS", 0, -1);}
	$MR_one_count_totS =	sprintf("%6.2f", $MR_one_count_Tpc); while(strlen($MR_one_count_totS)>7) {$MR_one_count_totS = substr("$MR_one_count_totS", 0, -1);}
	$MR_two_count_totS =	sprintf("%6.2f", $MR_two_count_Tpc); while(strlen($MR_two_count_totS)>7) {$MR_two_count_totS = substr("$MR_two_count_totS", 0, -1);}
	$MR_three_count_totS =	sprintf("%6.2f", $MR_three_count_Tpc); while(strlen($MR_three_count_totS)>7) {$MR_three_count_totS = substr("$MR_three_count_totS", 0, -1);}
	$MR_four_count_totS =	sprintf("%6.2f", $MR_four_count_Tpc); while(strlen($MR_four_count_totS)>7) {$MR_four_count_totS = substr("$MR_four_count_totS", 0, -1);}
	$MR_five_count_totS =	sprintf("%6.2f", $MR_five_count_Tpc); while(strlen($MR_five_count_totS)>7) {$MR_five_count_totS = substr("$MR_five_count_totS", 0, -1);}


	$TOTALleads =		sprintf("%10s", $TOTALleads);

	$OUToutput .= "+------------+------------------------------------------+----------+------------+----------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "\n";

	$OUToutput .= "             | "._QXZ("TOTALS",17,"r").":                                  | $TOTALleads |          |";
	$OUToutput .= " $HA_one_count_totS | $HA_two_count_totS | $HA_three_count_totS | $HA_four_count_totS | $HA_five_count_totS | $HA_count_totS |";
	$OUToutput .= " $HR_one_count_totS% | $HR_two_count_totS% | $HR_three_count_totS% | $HR_four_count_totS% | $HR_five_count_totS% | $HR_count_totS% |";
	$OUToutput .= " $SA_one_count_totS | $SA_two_count_totS | $SA_three_count_totS | $SA_four_count_totS | $SA_five_count_totS | $SA_count_totS |";
	$OUToutput .= " $SR_one_count_totS% | $SR_two_count_totS% | $SR_three_count_totS% | $SR_four_count_totS% | $SR_five_count_totS% | $SR_count_totS% |";
	$OUToutput .= " $DN_one_count_totS | $DN_two_count_totS | $DN_three_count_totS | $DN_four_count_totS | $DN_five_count_totS | $DN_count_totS |";
	$OUToutput .= " $DR_one_count_totS% | $DR_two_count_totS% | $DR_three_count_totS% | $DR_four_count_totS% | $DR_five_count_totS% | $DR_count_totS% |";
	$OUToutput .= " $CC_one_count_totS | $CC_two_count_totS | $CC_three_count_totS | $CC_four_count_totS | $CC_five_count_totS | $CC_count_totS |";
	$OUToutput .= " $CR_one_count_totS% | $CR_two_count_totS% | $CR_three_count_totS% | $CR_four_count_totS% | $CR_five_count_totS% | $CR_count_totS% |";
	$OUToutput .= " $UW_one_count_totS | $UW_two_count_totS | $UW_three_count_totS | $UW_four_count_totS | $UW_five_count_totS | $UW_count_totS |";
	$OUToutput .= " $UR_one_count_totS% | $UR_two_count_totS% | $UR_three_count_totS% | $UR_four_count_totS% | $UR_five_count_totS% | $UR_count_totS% |";
	$OUToutput .= " $BA_one_count_totS | $BA_two_count_totS | $BA_three_count_totS | $BA_four_count_totS | $BA_five_count_totS | $BA_count_totS |";
	$OUToutput .= " $BR_one_count_totS% | $BR_two_count_totS% | $BR_three_count_totS% | $BR_four_count_totS% | $BR_five_count_totS% | $BR_count_totS% |";
	$OUToutput .= " $MP_one_count_totS | $MP_two_count_totS | $MP_three_count_totS | $MP_four_count_totS | $MP_five_count_totS | $MP_count_totS |";
	$OUToutput .= " $MR_one_count_totS% | $MR_two_count_totS% | $MR_three_count_totS% | $MR_four_count_totS% | $MR_five_count_totS% | $MR_count_totS% |";
	$OUToutput .= "\n";

	$OUToutput .= "             +------------------------------------------+----------+------------+          +";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "---------+---------+---------+---------+---------+---------+";
	$OUToutput .= "\n";

	$CSV_text1.="\"\",\"\",\""._QXZ("TOTAL")."\",\"$TOTALleads\",\"\"";
	$CSV_text1.=",\"$HA_one_count_totS\",\"$HA_two_count_totS\",\"$HA_three_count_totS\",\"$HA_four_count_totS\",\"$HA_five_count_totS\",\"$HA_count_totS\"";
	$CSV_text1.=",\"$HR_one_count_totS%\",\"$HR_two_count_totS%\",\"$HR_three_count_totS%\",\"$HR_four_count_totS%\",\"$HR_five_count_totS%\",\"$HR_count_totS%\"";
	$CSV_text1.=",\"$SA_one_count_totS\",\"$SA_two_count_totS\",\"$SA_three_count_totS\",\"$SA_four_count_totS\",\"$SA_five_count_totS\",\"$SA_count_totS\"";
	$CSV_text1.=",\"$SR_one_count_totS%\",\"$SR_two_count_totS%\",\"$SR_three_count_totS%\",\"$SR_four_count_totS%\",\"$SR_five_count_totS%\",\"$SR_count_totS%\"";
	$CSV_text1.=",\"$DN_one_count_totS\",\"$DN_two_count_totS\",\"$DN_three_count_totS\",\"$DN_four_count_totS\",\"$DN_five_count_totS\",\"$DN_count_totS\"";
	$CSV_text1.=",\"$DR_one_count_totS%\",\"$DR_two_count_totS%\",\"$DR_three_count_totS%\",\"$DR_four_count_totS%\",\"$DR_five_count_totS%\",\"$DR_count_totS%\"";
	$CSV_text1.=",\"$CC_one_count_totS\",\"$CC_two_count_totS\",\"$CC_three_count_totS\",\"$CC_four_count_totS\",\"$CC_five_count_totS\",\"$CC_count_totS\"";
	$CSV_text1.=",\"$CR_one_count_totS%\",\"$CR_two_count_totS%\",\"$CR_three_count_totS%\",\"$CR_four_count_totS%\",\"$CR_five_count_totS%\",\"$CR_count_totS%\"";
	$CSV_text1.=",\"$UW_one_count_totS\",\"$UW_two_count_totS\",\"$UW_three_count_totS\",\"$UW_four_count_totS\",\"$UW_five_count_totS\",\"$UW_count_totS\"";
	$CSV_text1.=",\"$UR_one_count_totS%\",\"$UR_two_count_totS%\",\"$UR_three_count_totS%\",\"$UR_four_count_totS%\",\"$UR_five_count_totS%\",\"$UR_count_totS%\"";
	$CSV_text1.=",\"$BA_one_count_totS\",\"$BA_two_count_totS\",\"$BA_three_count_totS\",\"$BA_four_count_totS\",\"$BA_five_count_totS\",\"$BA_count_totS\"";
	$CSV_text1.=",\"$BR_one_count_totS%\",\"$BR_two_count_totS%\",\"$BR_three_count_totS%\",\"$BR_four_count_totS%\",\"$BR_five_count_totS%\",\"$BR_count_totS%\"";
	$CSV_text1.=",\"$MP_one_count_totS\",\"$MP_two_count_totS\",\"$MP_three_count_totS\",\"$MP_four_count_totS\",\"$MP_five_count_totS\",\"$MP_count_totS\"";
	$CSV_text1.=",\"$MR_one_count_totS%\",\"$MR_two_count_totS%\",\"$MR_three_count_totS%\",\"$MR_four_count_totS%\",\"$MR_five_count_totS%\",\"$MR_count_totS%\"";
	$CSV_text1.="\n";

	$totals2[1]=$HA_one_count_totS;
	$totals2[2]=$HA_two_count_totS;
	$totals2[3]=$HA_three_count_totS;
	$totals2[4]=$HA_four_count_totS;
	$totals2[5]=$HA_five_count_totS;
	$totals2[6]=$HA_count_totS;
	$totals2[7]=$HR_one_count_totS;
	$totals2[8]=$HR_two_count_totS;
	$totals2[9]=$HR_three_count_totS;
	$totals2[10]=$HR_four_count_totS;
	$totals2[11]=$HR_five_count_totS;
	$totals2[12]=$HR_count_totS;
	$totals2[13]=$SA_one_count_totS;
	$totals2[14]=$SA_two_count_totS;
	$totals2[15]=$SA_three_count_totS;
	$totals2[16]=$SA_four_count_totS;
	$totals2[17]=$SA_five_count_totS;
	$totals2[18]=$SA_count_totS;
	$totals2[19]=$SR_one_count_totS;
	$totals2[20]=$SR_two_count_totS;
	$totals2[21]=$SR_three_count_totS;
	$totals2[22]=$SR_four_count_totS;
	$totals2[23]=$SR_five_count_totS;
	$totals2[24]=$SR_count_totS;
	$totals2[25]=$DN_one_count_totS;
	$totals2[26]=$DN_two_count_totS;
	$totals2[27]=$DN_three_count_totS;
	$totals2[28]=$DN_four_count_totS;
	$totals2[29]=$DN_five_count_totS;
	$totals2[30]=$DN_count_totS;
	$totals2[31]=$DR_one_count_totS;
	$totals2[32]=$DR_two_count_totS;
	$totals2[33]=$DR_three_count_totS;
	$totals2[34]=$DR_four_count_totS;
	$totals2[35]=$DR_five_count_totS;
	$totals2[36]=$DR_count_totS;
	$totals2[37]=$CC_one_count_totS;
	$totals2[38]=$CC_two_count_totS;
	$totals2[39]=$CC_three_count_totS;
	$totals2[40]=$CC_four_count_totS;
	$totals2[41]=$CC_five_count_totS;
	$totals2[42]=$CC_count_totS;
	$totals2[43]=$CR_one_count_totS;
	$totals2[44]=$CR_two_count_totS;
	$totals2[45]=$CR_three_count_totS;
	$totals2[46]=$CR_four_count_totS;
	$totals2[47]=$CR_five_count_totS;
	$totals2[48]=$CR_count_totS;
	$totals2[49]=$UW_one_count_totS;
	$totals2[50]=$UW_two_count_totS;
	$totals2[51]=$UW_three_count_totS;
	$totals2[52]=$UW_four_count_totS;
	$totals2[53]=$UW_five_count_totS;
	$totals2[54]=$UW_count_totS;
	$totals2[55]=$UR_one_count_totS;
	$totals2[56]=$UR_two_count_totS;
	$totals2[57]=$UR_three_count_totS;
	$totals2[58]=$UR_four_count_totS;
	$totals2[59]=$UR_five_count_totS;
	$totals2[60]=$UR_count_totS;
	$totals2[61]=$BA_one_count_totS;
	$totals2[62]=$BA_two_count_totS;
	$totals2[63]=$BA_three_count_totS;
	$totals2[64]=$BA_four_count_totS;
	$totals2[65]=$BA_five_count_totS;
	$totals2[66]=$BA_count_totS;
	$totals2[67]=$BR_one_count_totS;
	$totals2[68]=$BR_two_count_totS;
	$totals2[69]=$BR_three_count_totS;
	$totals2[70]=$BR_four_count_totS;
	$totals2[71]=$BR_five_count_totS;
	$totals2[72]=$BR_count_totS;
	$totals2[73]=$MP_one_count_totS;
	$totals2[74]=$MP_two_count_totS;
	$totals2[75]=$MP_three_count_totS;
	$totals2[76]=$MP_four_count_totS;
	$totals2[77]=$MP_five_count_totS;
	$totals2[78]=$MP_count_totS;
	$totals2[79]=$MR_one_count_totS;
	$totals2[80]=$MR_two_count_totS;
	$totals2[81]=$MR_three_count_totS;
	$totals2[82]=$MR_four_count_totS;
	$totals2[83]=$MR_five_count_totS;
	$totals2[84]=$MR_count_totS;

	for ($d=0; $d<count($graph_stats2); $d++) {
		if ($d==0) {$class=" first";} else if (($d+1)==count($graph_stats2)) {$class=" last";} else {$class="";}
		$CONTACTS1_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='MathZDC(800*".$graph_stats2[$d][1].", $max_stats2[$d])' width='".round(MathZDC(800*$graph_stats2[$d][1], $max_stats2[1]))."' height='16' />".$graph_stats2[$d][1]."</td></tr>";
		$CONTACTS2_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][2], $max_stats2[2]))."' height='16' />".$graph_stats2[$d][2]."</td></tr>";
		$CONTACTS3_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][3], $max_stats2[3]))."' height='16' />".$graph_stats2[$d][3]."</td></tr>";
		$CONTACTS4_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][4], $max_stats2[4]))."' height='16' />".$graph_stats2[$d][4]."</td></tr>";
		$CONTACTS5_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][5], $max_stats2[5]))."' height='16' />".$graph_stats2[$d][5]."</td></tr>";
		$CONTACTSALL_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][6], $max_stats2[6]))."' height='16' />".$graph_stats2[$d][6]."</td></tr>";
		$CNTRATE1_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][7], $max_stats2[7]))."' height='16' />".$graph_stats2[$d][7]."</td></tr>";
		$CNTRATE2_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][8], $max_stats2[8]))."' height='16' />".$graph_stats2[$d][8]."</td></tr>";
		$CNTRATE3_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][9], $max_stats2[9]))."' height='16' />".$graph_stats2[$d][9]."</td></tr>";
		$CNTRATE4_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][10], $max_stats2[10]))."' height='16' />".$graph_stats2[$d][10]."</td></tr>";
		$CNTRATE5_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][11], $max_stats2[11]))."' height='16' />".$graph_stats2[$d][11]."</td></tr>";
		$CNTRATEALL_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][12], $max_stats2[12]))."' height='16' />".$graph_stats2[$d][12]."</td></tr>";
		$SALES1_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][13], $max_stats2[13]))."' height='16' />".$graph_stats2[$d][13]."</td></tr>";
		$SALES2_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][14], $max_stats2[14]))."' height='16' />".$graph_stats2[$d][14]."</td></tr>";
		$SALES3_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][15], $max_stats2[15]))."' height='16' />".$graph_stats2[$d][15]."</td></tr>";
		$SALES4_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][16], $max_stats2[16]))."' height='16' />".$graph_stats2[$d][16]."</td></tr>";
		$SALES5_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][17], $max_stats2[17]))."' height='16' />".$graph_stats2[$d][17]."</td></tr>";
		$SALESALL_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][18], $max_stats2[18]))."' height='16' />".$graph_stats2[$d][18]."</td></tr>";
		$CONVRATE1_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][19], $max_stats2[19]))."' height='16' />".$graph_stats2[$d][19]."</td></tr>";
		$CONVRATE2_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][20], $max_stats2[20]))."' height='16' />".$graph_stats2[$d][20]."</td></tr>";
		$CONVRATE3_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][21], $max_stats2[21]))."' height='16' />".$graph_stats2[$d][21]."</td></tr>";
		$CONVRATE4_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][22], $max_stats2[22]))."' height='16' />".$graph_stats2[$d][22]."</td></tr>";
		$CONVRATE5_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][23], $max_stats2[23]))."' height='16' />".$graph_stats2[$d][23]."</td></tr>";
		$CONVRATEALL_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][24], $max_stats2[24]))."' height='16' />".$graph_stats2[$d][24]."</td></tr>";
		$DNC1_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][25], $max_stats2[25]))."' height='16' />".$graph_stats2[$d][25]."</td></tr>";
		$DNC2_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][26], $max_stats2[26]))."' height='16' />".$graph_stats2[$d][26]."</td></tr>";
		$DNC3_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][27], $max_stats2[27]))."' height='16' />".$graph_stats2[$d][27]."</td></tr>";
		$DNC4_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][28], $max_stats2[28]))."' height='16' />".$graph_stats2[$d][28]."</td></tr>";
		$DNC5_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][29], $max_stats2[29]))."' height='16' />".$graph_stats2[$d][29]."</td></tr>";
		$DNCALL_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][30], $max_stats2[30]))."' height='16' />".$graph_stats2[$d][30]."</td></tr>";
		$DNCRATE1_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][31], $max_stats2[31]))."' height='16' />".$graph_stats2[$d][31]."</td></tr>";
		$DNCRATE2_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][32], $max_stats2[32]))."' height='16' />".$graph_stats2[$d][32]."</td></tr>";
		$DNCRATE3_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][33], $max_stats2[33]))."' height='16' />".$graph_stats2[$d][33]."</td></tr>";
		$DNCRATE4_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][34], $max_stats2[34]))."' height='16' />".$graph_stats2[$d][34]."</td></tr>";
		$DNCRATE5_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][35], $max_stats2[35]))."' height='16' />".$graph_stats2[$d][35]."</td></tr>";
		$DNCRATEALL_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][36], $max_stats2[36]))."' height='16' />".$graph_stats2[$d][36]."</td></tr>";
		$CUSTCNT1_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][37], $max_stats2[37]))."' height='16' />".$graph_stats2[$d][37]."</td></tr>";
		$CUSTCNT2_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][38], $max_stats2[38]))."' height='16' />".$graph_stats2[$d][38]."</td></tr>";
		$CUSTCNT3_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][39], $max_stats2[39]))."' height='16' />".$graph_stats2[$d][39]."</td></tr>";
		$CUSTCNT4_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][40], $max_stats2[40]))."' height='16' />".$graph_stats2[$d][40]."</td></tr>";
		$CUSTCNT5_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][41], $max_stats2[41]))."' height='16' />".$graph_stats2[$d][41]."</td></tr>";
		$CUSTCNTALL_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][42], $max_stats2[42]))."' height='16' />".$graph_stats2[$d][42]."</td></tr>";
		$CUSTCNTRATE1_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][43], $max_stats2[43]))."' height='16' />".$graph_stats2[$d][43]."</td></tr>";
		$CUSTCNTRATE2_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][44], $max_stats2[44]))."' height='16' />".$graph_stats2[$d][44]."</td></tr>";
		$CUSTCNTRATE3_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][45], $max_stats2[45]))."' height='16' />".$graph_stats2[$d][45]."</td></tr>";
		$CUSTCNTRATE4_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][46], $max_stats2[46]))."' height='16' />".$graph_stats2[$d][46]."</td></tr>";
		$CUSTCNTRATE5_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][47], $max_stats2[47]))."' height='16' />".$graph_stats2[$d][47]."</td></tr>";
		$CUSTCNTRATEALL_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][48], $max_stats2[48]))."' height='16' />".$graph_stats2[$d][48]."</td></tr>";
		$UNWRK1_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][49], $max_stats2[49]))."' height='16' />".$graph_stats2[$d][49]."</td></tr>";
		$UNWRK2_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][50], $max_stats2[50]))."' height='16' />".$graph_stats2[$d][50]."</td></tr>";
		$UNWRK3_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][51], $max_stats2[51]))."' height='16' />".$graph_stats2[$d][51]."</td></tr>";
		$UNWRK4_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][52], $max_stats2[52]))."' height='16' />".$graph_stats2[$d][52]."</td></tr>";
		$UNWRK5_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][53], $max_stats2[53]))."' height='16' />".$graph_stats2[$d][53]."</td></tr>";
		$UNWRKALL_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][54], $max_stats2[54]))."' height='16' />".$graph_stats2[$d][54]."</td></tr>";
		$UNWRKRATE1_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][55], $max_stats2[55]))."' height='16' />".$graph_stats2[$d][55]."</td></tr>";
		$UNWRKRATE2_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][56], $max_stats2[56]))."' height='16' />".$graph_stats2[$d][56]."</td></tr>";
		$UNWRKRATE3_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][57], $max_stats2[57]))."' height='16' />".$graph_stats2[$d][57]."</td></tr>";
		$UNWRKRATE4_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][58], $max_stats2[58]))."' height='16' />".$graph_stats2[$d][58]."</td></tr>";
		$UNWRKRATE5_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][59], $max_stats2[59]))."' height='16' />".$graph_stats2[$d][59]."</td></tr>";
		$UNWRKRATEALL_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][60], $max_stats2[60]))."' height='16' />".$graph_stats2[$d][60]."</td></tr>";
		$SCHDCLBK1_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][61], $max_stats2[61]))."' height='16' />".$graph_stats2[$d][61]."</td></tr>";
		$SCHDCLBK2_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][62], $max_stats2[62]))."' height='16' />".$graph_stats2[$d][62]."</td></tr>";
		$SCHDCLBK3_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][63], $max_stats2[63]))."' height='16' />".$graph_stats2[$d][63]."</td></tr>";
		$SCHDCLBK4_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][64], $max_stats2[64]))."' height='16' />".$graph_stats2[$d][64]."</td></tr>";
		$SCHDCLBK5_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][65], $max_stats2[65]))."' height='16' />".$graph_stats2[$d][65]."</td></tr>";
		$SCHDCLBKALL_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][66], $max_stats2[66]))."' height='16' />".$graph_stats2[$d][66]."</td></tr>";
		$SCHDCLBKRATE1_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][67], $max_stats2[67]))."' height='16' />".$graph_stats2[$d][67]."</td></tr>";
		$SCHDCLBKRATE2_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][68], $max_stats2[68]))."' height='16' />".$graph_stats2[$d][68]."</td></tr>";
		$SCHDCLBKRATE3_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][69], $max_stats2[69]))."' height='16' />".$graph_stats2[$d][69]."</td></tr>";
		$SCHDCLBKRATE4_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][70], $max_stats2[70]))."' height='16' />".$graph_stats2[$d][70]."</td></tr>";
		$SCHDCLBKRATE5_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][71], $max_stats2[71]))."' height='16' />".$graph_stats2[$d][71]."</td></tr>";
		$SCHDCLBKRATEALL_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][72], $max_stats2[72]))."' height='16' />".$graph_stats2[$d][72]."</td></tr>";
		$COMPLTD1_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][73], $max_stats2[73]))."' height='16' />".$graph_stats2[$d][73]."</td></tr>";
		$COMPLTD2_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][74], $max_stats2[74]))."' height='16' />".$graph_stats2[$d][74]."</td></tr>";
		$COMPLTD3_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][75], $max_stats2[75]))."' height='16' />".$graph_stats2[$d][75]."</td></tr>";
		$COMPLTD4_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][76], $max_stats2[76]))."' height='16' />".$graph_stats2[$d][76]."</td></tr>";
		$COMPLTD5_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][77], $max_stats2[77]))."' height='16' />".$graph_stats2[$d][77]."</td></tr>";
		$COMPLTDALL_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][78], $max_stats2[78]))."' height='16' />".$graph_stats2[$d][78]."</td></tr>";
		$COMPLTDRATE1_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][79], $max_stats2[79]))."' height='16' />".$graph_stats2[$d][79]."</td></tr>";
		$COMPLTDRATE2_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][80], $max_stats2[80]))."' height='16' />".$graph_stats2[$d][80]."</td></tr>";
		$COMPLTDRATE3_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][81], $max_stats2[81]))."' height='16' />".$graph_stats2[$d][81]."</td></tr>";
		$COMPLTDRATE4_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][82], $max_stats2[82]))."' height='16' />".$graph_stats2[$d][82]."</td></tr>";
		$COMPLTDRATE5_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][83], $max_stats2[83]))."' height='16' />".$graph_stats2[$d][83]."</td></tr>";
		$COMPLTDRATEALL_graph.="  <tr><td class='chart_td$class'>".$graph_stats2[$d][0]."</td><td nowrap class='chart_td value$class'><img src='images/bar.png' alt='' width='".round(MathZDC(800*$graph_stats2[$d][84], $max_stats2[84]))."' height='16' />".$graph_stats2[$d][84]."</td></tr>";
	}

	$CONTACTS1_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[1]."</th></tr>";
	$CONTACTS2_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[2]."</th></tr>";
	$CONTACTS3_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[3]."</th></tr>";
	$CONTACTS4_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[4]."</th></tr>";
	$CONTACTS5_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[5]."</th></tr>";
	$CONTACTSALL_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[6]."</th></tr>";
	$CNTRATE1_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[7]."</th></tr>";
	$CNTRATE2_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[8]."</th></tr>";
	$CNTRATE3_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[9]."</th></tr>";
	$CNTRATE4_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[10]."</th></tr>";
	$CNTRATE5_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[11]."</th></tr>";
	$CNTRATEALL_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[12]."</th></tr>";
	$SALES1_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[13]."</th></tr>";
	$SALES2_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[14]."</th></tr>";
	$SALES3_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[15]."</th></tr>";
	$SALES4_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[16]."</th></tr>";
	$SALES5_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[17]."</th></tr>";
	$SALESALL_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[18]."</th></tr>";
	$CONVRATE1_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[19]."</th></tr>";
	$CONVRATE2_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[20]."</th></tr>";
	$CONVRATE3_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[21]."</th></tr>";
	$CONVRATE4_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[22]."</th></tr>";
	$CONVRATE5_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[23]."</th></tr>";
	$CONVRATEALL_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[24]."</th></tr>";
	$DNC1_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[25]."</th></tr>";
	$DNC2_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[26]."</th></tr>";
	$DNC3_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[27]."</th></tr>";
	$DNC4_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[28]."</th></tr>";
	$DNC5_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[29]."</th></tr>";
	$DNCALL_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[30]."</th></tr>";
	$DNCRATE1_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[31]."</th></tr>";
	$DNCRATE2_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[32]."</th></tr>";
	$DNCRATE3_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[33]."</th></tr>";
	$DNCRATE4_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[34]."</th></tr>";
	$DNCRATE5_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[35]."</th></tr>";
	$DNCRATEALL_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[36]."</th></tr>";
	$CUSTCNT1_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[37]."</th></tr>";
	$CUSTCNT2_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[38]."</th></tr>";
	$CUSTCNT3_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[39]."</th></tr>";
	$CUSTCNT4_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[40]."</th></tr>";
	$CUSTCNT5_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[41]."</th></tr>";
	$CUSTCNTALL_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[42]."</th></tr>";
	$CUSTCNTRATE1_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[43]."</th></tr>";
	$CUSTCNTRATE2_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[44]."</th></tr>";
	$CUSTCNTRATE3_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[45]."</th></tr>";
	$CUSTCNTRATE4_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[46]."</th></tr>";
	$CUSTCNTRATE5_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[47]."</th></tr>";
	$CUSTCNTRATEALL_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[48]."</th></tr>";
	$UNWRK1_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[49]."</th></tr>";
	$UNWRK2_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[50]."</th></tr>";
	$UNWRK3_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[51]."</th></tr>";
	$UNWRK4_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[52]."</th></tr>";
	$UNWRK5_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[53]."</th></tr>";
	$UNWRKALL_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[54]."</th></tr>";
	$UNWRKRATE1_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[55]."</th></tr>";
	$UNWRKRATE2_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[56]."</th></tr>";
	$UNWRKRATE3_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[57]."</th></tr>";
	$UNWRKRATE4_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[58]."</th></tr>";
	$UNWRKRATE5_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[59]."</th></tr>";
	$UNWRKRATEALL_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[60]."</th></tr>";
	$SCHDCLBK1_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[61]."</th></tr>";
	$SCHDCLBK2_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[62]."</th></tr>";
	$SCHDCLBK3_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[63]."</th></tr>";
	$SCHDCLBK4_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[64]."</th></tr>";
	$SCHDCLBK5_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[65]."</th></tr>";
	$SCHDCLBKALL_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[66]."</th></tr>";
	$SCHDCLBKRATE1_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[67]."</th></tr>";
	$SCHDCLBKRATE2_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[68]."</th></tr>";
	$SCHDCLBKRATE3_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[69]."</th></tr>";
	$SCHDCLBKRATE4_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[70]."</th></tr>";
	$SCHDCLBKRATE5_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[71]."</th></tr>";
	$SCHDCLBKRATEALL_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[72]."</th></tr>";
	$COMPLTD1_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[73]."</th></tr>";
	$COMPLTD2_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[74]."</th></tr>";
	$COMPLTD3_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[75]."</th></tr>";
	$COMPLTD4_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[76]."</th></tr>";
	$COMPLTD5_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[77]."</th></tr>";
	$COMPLTDALL_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[78]."</th></tr>";
	$COMPLTDRATE1_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[79]."</th></tr>";
	$COMPLTDRATE2_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[80]."</th></tr>";
	$COMPLTDRATE3_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[81]."</th></tr>";
	$COMPLTDRATE4_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[82]."</th></tr>";
	$COMPLTDRATE5_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[83]."</th></tr>";
	$COMPLTDRATEALL_graph.="  <tr><th class='thgraph' scope='col'>TOTAL:</th><th class='thgraph' scope='col'>".$totals2[84]."</th></tr>";


	$JS_onload.="\tDrawGraph('CONTACTS1', '1');\n"; 
	$JS_text.="function DrawGraph(graph, th_id) {\n";
	$JS_text.="	var graph_CONTACTS1=\"$CONTACTS1_graph\";\n";
	$JS_text.="	var graph_CONTACTS2=\"$CONTACTS2_graph\";\n";
	$JS_text.="	var graph_CONTACTS3=\"$CONTACTS3_graph\";\n";
	$JS_text.="	var graph_CONTACTS4=\"$CONTACTS4_graph\";\n";
	$JS_text.="	var graph_CONTACTS5=\"$CONTACTS5_graph\";\n";
	$JS_text.="	var graph_CONTACTSALL=\"$CONTACTSALL_graph\";\n";
	$JS_text.="	var graph_CNTRATE1=\"$CNTRATE1_graph\";\n";
	$JS_text.="	var graph_CNTRATE2=\"$CNTRATE2_graph\";\n";
	$JS_text.="	var graph_CNTRATE3=\"$CNTRATE3_graph\";\n";
	$JS_text.="	var graph_CNTRATE4=\"$CNTRATE4_graph\";\n";
	$JS_text.="	var graph_CNTRATE5=\"$CNTRATE5_graph\";\n";
	$JS_text.="	var graph_CNTRATEALL=\"$CNTRATEALL_graph\";\n";
	$JS_text.="	var graph_SALES1=\"$SALES1_graph\";\n";
	$JS_text.="	var graph_SALES2=\"$SALES2_graph\";\n";
	$JS_text.="	var graph_SALES3=\"$SALES3_graph\";\n";
	$JS_text.="	var graph_SALES4=\"$SALES4_graph\";\n";
	$JS_text.="	var graph_SALES5=\"$SALES5_graph\";\n";
	$JS_text.="	var graph_SALESALL=\"$SALESALL_graph\";\n";
	$JS_text.="	var graph_CONVRATE1=\"$CONVRATE1_graph\";\n";
	$JS_text.="	var graph_CONVRATE2=\"$CONVRATE2_graph\";\n";
	$JS_text.="	var graph_CONVRATE3=\"$CONVRATE3_graph\";\n";
	$JS_text.="	var graph_CONVRATE4=\"$CONVRATE4_graph\";\n";
	$JS_text.="	var graph_CONVRATE5=\"$CONVRATE5_graph\";\n";
	$JS_text.="	var graph_CONVRATEALL=\"$CONVRATEALL_graph\";\n";
	$JS_text.="	var graph_DNC1=\"$DNC1_graph\";\n";
	$JS_text.="	var graph_DNC2=\"$DNC2_graph\";\n";
	$JS_text.="	var graph_DNC3=\"$DNC3_graph\";\n";
	$JS_text.="	var graph_DNC4=\"$DNC4_graph\";\n";
	$JS_text.="	var graph_DNC5=\"$DNC5_graph\";\n";
	$JS_text.="	var graph_DNCALL=\"$DNCALL_graph\";\n";
	$JS_text.="	var graph_DNCRATE1=\"$DNCRATE1_graph\";\n";
	$JS_text.="	var graph_DNCRATE2=\"$DNCRATE2_graph\";\n";
	$JS_text.="	var graph_DNCRATE3=\"$DNCRATE3_graph\";\n";
	$JS_text.="	var graph_DNCRATE4=\"$DNCRATE4_graph\";\n";
	$JS_text.="	var graph_DNCRATE5=\"$DNCRATE5_graph\";\n";
	$JS_text.="	var graph_DNCRATEALL=\"$DNCRATEALL_graph\";\n";
	$JS_text.="	var graph_CUSTCNT1=\"$CUSTCNT1_graph\";\n";
	$JS_text.="	var graph_CUSTCNT2=\"$CUSTCNT2_graph\";\n";
	$JS_text.="	var graph_CUSTCNT3=\"$CUSTCNT3_graph\";\n";
	$JS_text.="	var graph_CUSTCNT4=\"$CUSTCNT4_graph\";\n";
	$JS_text.="	var graph_CUSTCNT5=\"$CUSTCNT5_graph\";\n";
	$JS_text.="	var graph_CUSTCNTALL=\"$CUSTCNTALL_graph\";\n";
	$JS_text.="	var graph_CUSTCNTRATE1=\"$CUSTCNTRATE1_graph\";\n";
	$JS_text.="	var graph_CUSTCNTRATE2=\"$CUSTCNTRATE2_graph\";\n";
	$JS_text.="	var graph_CUSTCNTRATE3=\"$CUSTCNTRATE3_graph\";\n";
	$JS_text.="	var graph_CUSTCNTRATE4=\"$CUSTCNTRATE4_graph\";\n";
	$JS_text.="	var graph_CUSTCNTRATE5=\"$CUSTCNTRATE5_graph\";\n";
	$JS_text.="	var graph_CUSTCNTRATEALL=\"$CUSTCNTRATEALL_graph\";\n";
	$JS_text.="	var graph_UNWRK1=\"$UNWRK1_graph\";\n";
	$JS_text.="	var graph_UNWRK2=\"$UNWRK2_graph\";\n";
	$JS_text.="	var graph_UNWRK3=\"$UNWRK3_graph\";\n";
	$JS_text.="	var graph_UNWRK4=\"$UNWRK4_graph\";\n";
	$JS_text.="	var graph_UNWRK5=\"$UNWRK5_graph\";\n";
	$JS_text.="	var graph_UNWRKALL=\"$UNWRKALL_graph\";\n";
	$JS_text.="	var graph_UNWRKRATE1=\"$UNWRKRATE1_graph\";\n";
	$JS_text.="	var graph_UNWRKRATE2=\"$UNWRKRATE2_graph\";\n";
	$JS_text.="	var graph_UNWRKRATE3=\"$UNWRKRATE3_graph\";\n";
	$JS_text.="	var graph_UNWRKRATE4=\"$UNWRKRATE4_graph\";\n";
	$JS_text.="	var graph_UNWRKRATE5=\"$UNWRKRATE5_graph\";\n";
	$JS_text.="	var graph_UNWRKRATEALL=\"$UNWRKRATEALL_graph\";\n";
	$JS_text.="	var graph_SCHDCLBK1=\"$SCHDCLBK1_graph\";\n";
	$JS_text.="	var graph_SCHDCLBK2=\"$SCHDCLBK2_graph\";\n";
	$JS_text.="	var graph_SCHDCLBK3=\"$SCHDCLBK3_graph\";\n";
	$JS_text.="	var graph_SCHDCLBK4=\"$SCHDCLBK4_graph\";\n";
	$JS_text.="	var graph_SCHDCLBK5=\"$SCHDCLBK5_graph\";\n";
	$JS_text.="	var graph_SCHDCLBKALL=\"$SCHDCLBKALL_graph\";\n";
	$JS_text.="	var graph_SCHDCLBKRATE1=\"$SCHDCLBKRATE1_graph\";\n";
	$JS_text.="	var graph_SCHDCLBKRATE2=\"$SCHDCLBKRATE2_graph\";\n";
	$JS_text.="	var graph_SCHDCLBKRATE3=\"$SCHDCLBKRATE3_graph\";\n";
	$JS_text.="	var graph_SCHDCLBKRATE4=\"$SCHDCLBKRATE4_graph\";\n";
	$JS_text.="	var graph_SCHDCLBKRATE5=\"$SCHDCLBKRATE5_graph\";\n";
	$JS_text.="	var graph_SCHDCLBKRATEALL=\"$SCHDCLBKRATEALL_graph\";\n";
	$JS_text.="	var graph_COMPLTD1=\"$COMPLTD1_graph\";\n";
	$JS_text.="	var graph_COMPLTD2=\"$COMPLTD2_graph\";\n";
	$JS_text.="	var graph_COMPLTD3=\"$COMPLTD3_graph\";\n";
	$JS_text.="	var graph_COMPLTD4=\"$COMPLTD4_graph\";\n";
	$JS_text.="	var graph_COMPLTD5=\"$COMPLTD5_graph\";\n";
	$JS_text.="	var graph_COMPLTDALL=\"$COMPLTDALL_graph\";\n";
	$JS_text.="	var graph_COMPLTDRATE1=\"$COMPLTDRATE1_graph\";\n";
	$JS_text.="	var graph_COMPLTDRATE2=\"$COMPLTDRATE2_graph\";\n";
	$JS_text.="	var graph_COMPLTDRATE3=\"$COMPLTDRATE3_graph\";\n";
	$JS_text.="	var graph_COMPLTDRATE4=\"$COMPLTDRATE4_graph\";\n";
	$JS_text.="	var graph_COMPLTDRATE5=\"$COMPLTDRATE5_graph\";\n";
	$JS_text.="	var graph_COMPLTDRATEALL=\"$COMPLTDRATEALL_graph\";\n";

	$JS_text.="	for (var i=1; i<=84; i++) {\n";
	$JS_text.="		var cellID=\"callstatsgraph\"+i;\n";
	$JS_text.="		document.getElementById(cellID).style.backgroundColor='#DDDDDD';\n";
	$JS_text.="	}\n";
	$JS_text.="	var cellID=\"callstatsgraph\"+th_id;\n";
	$JS_text.="	document.getElementById(cellID).style.backgroundColor='#999999';\n";
	$JS_text.="\n";
	$JS_text.="	var graph_to_display=eval(\"graph_\"+graph);\n";
	$JS_text.="	document.getElementById('call_stats_graph').innerHTML=graph_to_display;\n";
	$JS_text.="}\n";

	$GRAPH3="<tr><td colspan='84' class='graph_span_cell'><span id='call_stats_graph'><BR>&nbsp;<BR></span></td></tr></table><BR><BR>";


	for ($d=0; $d<count($graph_stats); $d++) {
		if ($d==0) {$class=" first";} else if (($d+1)==count($graph_stats)) {$class=" last";} else {$class="";}
		$GRAPH.="  <tr>\n";
		$GRAPH.="	<td class=\"chart_td$class\">".$graph_stats[$d][1]."</td>\n";
		$GRAPH.="	<td nowrap class=\"chart_td value$class\"><img src=\"images/bar.png\" alt=\"\" width=\"".round(MathZDC(400*$graph_stats[$d][0], $max_calls))."\" height=\"16\" />".$graph_stats[$d][0]."</td>\n";
		$GRAPH.="  </tr>\n";
	}
	$GRAPH.="  <tr>\n";
	$GRAPH.="	<th class=\"thgraph\" scope=\"col\">"._QXZ("TOTAL").":</th>\n";
	$GRAPH.="	<th class=\"thgraph\" scope=\"col\">".trim($TOTALleads)."</th>\n";
	$GRAPH.="  </tr>\n";
	$GRAPH.="</table><PRE>\n";
	$GRAPH.="<BR><BR><a name='callsgraph'/><table border='0' cellpadding='0' cellspacing='2' width='800'>";


	if ($report_display_type=="HTML")
		{
		$MAIN.=$GRAPH.$GRAPH2.$GRAPH3;
		}
	else
		{
		$MAIN.="$OUToutput";
		}



	$ENDtime = date("U");
	$RUNtime = ($ENDtime - $STARTtime);
	$MAIN.="\n"._QXZ("Run Time").": $RUNtime "._QXZ("seconds")."|$db_source\n";
	$MAIN.="</PRE>\n";
	$MAIN.="</TD></TR></TABLE>\n";
	$MAIN.="</BODY></HTML>\n";

	}

	if ($file_download>0) {
		$FILE_TIME = date("Ymd-His");
		$CSVfilename = "AST_LISTS_pass_report_$US$FILE_TIME.csv";
		$CSV_var="CSV_text1";
		$CSV_text=preg_replace('/^ +/', '', $$CSV_var);
		$CSV_text=preg_replace('/\n +,/', ',', $CSV_text);
		$CSV_text=preg_replace('/ +\"/', '"', $CSV_text);
		$CSV_text=preg_replace('/\" +/', '"', $CSV_text);
		// We'll be outputting a TXT file
		header('Content-type: application/octet-stream');

		// It will be called LIST_101_20090209-121212.txt
		header("Content-Disposition: attachment; filename=\"$CSVfilename\"");
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		ob_clean();
		flush();

		echo "$CSV_text";

	} else {
		$JS_onload.="}\n";
		$JS_text.=$JS_onload;
		$JS_text.="</script>\n";

		echo $HEADER;
		echo $JS_text;
		require("admin_header.php");
		echo $MAIN;
	}


if ($db_source == 'S')
	{
	mysqli_close($link);
	$use_slave_server=0;
	$db_source = 'M';
	require("dbconnect_mysqli.php");
	}

$endMS = microtime();
$startMSary = explode(" ",$startMS);
$endMSary = explode(" ",$endMS);
$runS = ($endMSary[0] - $startMSary[0]);
$runM = ($endMSary[1] - $startMSary[1]);
$TOTALrun = ($runS + $runM);

$stmt="UPDATE vicidial_report_log set run_time='$TOTALrun' where report_log_id='$report_log_id';";
if ($DB) {echo "|$stmt|\n";}
$rslt=mysql_to_mysqli($stmt, $link);

exit;

?>
