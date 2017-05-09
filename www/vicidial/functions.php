<?php
# 
# functions.php    version 2.10
#
# functions for administrative scripts and reports
#
# Copyright (C) 2015  Matt Florell <vicidial@gmail.com>    LICENSE: AGPLv2
#
#
# CHANGES:
# 90524-1503 - First Build
# 110708-1723 - Added HF precision option
# 111222-2124 - Added max stats bar chart function
# 120125-1235 - Small changes to max stats function to allow for total system stats
# 120213-1417 - Changes to allow for ra stats
# 120713-2137 - Added download function for max stats
# 130615-2111 - Added user authentication function and login lockout for 15 minutes after 10 failed login
# 130705-1957 - Added password encryption compatibility
# 130831-0919 - Changed to mysqli PHP functions
# 140319-1924 - Added MathZDC function
# 140918-1609 - Added admin QXZ print/echo function with length padding
# 141118-0109 - Added options for up to 9 ordered variables within QXZ function output
# 141229-1535 - Added code to QXZ allowing for on-the-fly mysql phrase lookups
# 150210-1358 - Added precision S default to 0 in sec_convert
# 150216-1528 - Fixed non-latin problem, issue #828
#

##### BEGIN validate user login credentials, check for failed lock out #####
function user_authorization($user,$pass,$user_option,$user_update)
	{
	global $link;
#	require("dbconnect_mysqli.php");

	#############################################
	##### START SYSTEM_SETTINGS LOOKUP #####
	$stmt = "SELECT use_non_latin,webroot_writable,pass_hash_enabled,pass_key,pass_cost FROM system_settings;";
	$rslt=mysql_to_mysqli($stmt, $link);
	if ($DB) {echo "$stmt\n";}
	$qm_conf_ct = mysqli_num_rows($rslt);
	if ($qm_conf_ct > 0)
		{
		$row=mysqli_fetch_row($rslt);
		$non_latin =					$row[0];
		$SSwebroot_writable =			$row[1];
		$SSpass_hash_enabled =			$row[2];
		$SSpass_key =					$row[3];
		$SSpass_cost =					$row[4];
		}
	##### END SETTINGS LOOKUP #####
	###########################################

	$STARTtime = date("U");
	$TODAY = date("Y-m-d");
	$NOW_TIME = date("Y-m-d H:i:s");
	$ip = getenv("REMOTE_ADDR");
	$browser = getenv("HTTP_USER_AGENT");
	$LOCK_over = ($STARTtime - 900); # failed login lockout time is 15 minutes(900 seconds)
	$LOCK_trigger_attempts = 10;

	$user = preg_replace("/\'|\"|\\\\|;/","",$user);
	$pass = preg_replace("/\'|\"|\\\\|;/","",$pass);

	$passSQL = "pass='$pass'";

	if ($SSpass_hash_enabled > 0)
		{
		if (file_exists("../agc/bp.pl"))
			{$pass_hash = exec("../agc/bp.pl --pass=$pass");}
		else
			{$pass_hash = exec("../../agc/bp.pl --pass=$pass");}
		$pass_hash = preg_replace("/PHASH: |\n|\r|\t| /",'',$pass_hash);
		$passSQL = "pass_hash='$pass_hash'";
		}

	$stmt="SELECT count(*) from vicidial_users where user='$user' and $passSQL and user_level > 7 and active='Y' and ( (failed_login_count < $LOCK_trigger_attempts) or (UNIX_TIMESTAMP(last_login_date) < $LOCK_over) );";
	if ($user_option == 'REPORTS')
		{$stmt="SELECT count(*) from vicidial_users where user='$user' and $passSQL and user_level > 6 and active='Y' and ( (failed_login_count < $LOCK_trigger_attempts) or (UNIX_TIMESTAMP(last_login_date) < $LOCK_over) );";}
	if ($user_option == 'REMOTE')
		{$stmt="SELECT count(*) from vicidial_users where user='$user' and $passSQL and user_level > 3 and active='Y' and ( (failed_login_count < $LOCK_trigger_attempts) or (UNIX_TIMESTAMP(last_login_date) < $LOCK_over) );";}
	if ($user_option == 'QC')
		{$stmt="SELECT count(*) from vicidial_users where user='$user' and $passSQL and user_level > 1 and active='Y' and ( (failed_login_count < $LOCK_trigger_attempts) or (UNIX_TIMESTAMP(last_login_date) < $LOCK_over) );";}
	if ($DB) {echo "|$stmt|\n";}
	if ($non_latin > 0) {$rslt=mysql_to_mysqli("SET NAMES 'UTF8'", $link);}
	$rslt=mysql_to_mysqli($stmt, $link);
	$row=mysqli_fetch_row($rslt);
	$auth=$row[0];

	if ($auth < 1)
		{
		$auth_key='BAD';
		$stmt="SELECT failed_login_count,UNIX_TIMESTAMP(last_login_date) from vicidial_users where user='$user';";
		if ($non_latin > 0) {$rslt=mysql_to_mysqli("SET NAMES 'UTF8'", $link);}
		$rslt=mysql_to_mysqli($stmt, $link);
		$cl_user_ct = mysqli_num_rows($rslt);
		if ($cl_user_ct > 0)
			{
			$row=mysqli_fetch_row($rslt);
			$failed_login_count =	$row[0];
			$last_login_date =		$row[1];

			if ($failed_login_count < $LOCK_trigger_attempts)
				{
				$stmt="UPDATE vicidial_users set failed_login_count=(failed_login_count+1),last_ip='$ip' where user='$user';";
				$rslt=mysql_to_mysqli($stmt, $link);
				}
			else
				{
				if ($LOCK_over > $last_login_date)
					{
					$stmt="UPDATE vicidial_users set last_login_date=NOW(),failed_login_count=1,last_ip='$ip' where user='$user';";
					$rslt=mysql_to_mysqli($stmt, $link);
					}
				else
					{$auth_key='LOCK';}
				}
			}
		if ($SSwebroot_writable > 0)
			{
			$fp = fopen ("./project_auth_entries.txt", "a");
			fwrite ($fp, "ADMIN|FAIL|$NOW_TIME|$user|$auth_key|$ip|$browser|\n");
			fclose($fp);
			}
		}
	else
		{
		if ($user_update > 0)
			{
			$stmt="UPDATE vicidial_users set last_login_date=NOW(),last_ip='$ip',failed_login_count=0 where user='$user';";
			$rslt=mysql_to_mysqli($stmt, $link);
			}
		$auth_key='GOOD';
		}
	return $auth_key;
	}
##### END validate user login credentials, check for failed lock out #####


##### BEGIN reformat seconds into HH:MM:SS or MM:SS #####
function sec_convert($sec,$precision)
	{
	$sec = round($sec,0);

	if ($sec < 1)
		{
		if ($precision == 'HF')
			{return "0:00:00";}
		else
			{
			if ($precision == 'S')
				{return "0";}
			else
				{return "0:00";}

			}
		}
	else
		{
		if ($precision == 'HF')
			{$precision='H';}
		else
			{
			if ( ($sec < 3600) and ($precision != 'S') ) {$precision='M';}
			}

		if ($precision == 'H')
			{
			$Fhours_H =	MathZDC($sec, 3600);
			$Fhours_H_int = floor($Fhours_H);
			$Fhours_H_int = intval("$Fhours_H_int");
			$Fhours_M = ($Fhours_H - $Fhours_H_int);
			$Fhours_M = ($Fhours_M * 60);
			$Fhours_M_int = floor($Fhours_M);
			$Fhours_M_int = intval("$Fhours_M_int");
			$Fhours_S = ($Fhours_M - $Fhours_M_int);
			$Fhours_S = ($Fhours_S * 60);
			$Fhours_S = round($Fhours_S, 0);
			if ($Fhours_S < 10) {$Fhours_S = "0$Fhours_S";}
			if ($Fhours_M_int < 10) {$Fhours_M_int = "0$Fhours_M_int";}
			$Ftime = "$Fhours_H_int:$Fhours_M_int:$Fhours_S";
			}
		if ($precision == 'M')
			{
			$Fminutes_M = MathZDC($sec, 60);
			$Fminutes_M_int = floor($Fminutes_M);
			$Fminutes_M_int = intval("$Fminutes_M_int");
			$Fminutes_S = ($Fminutes_M - $Fminutes_M_int);
			$Fminutes_S = ($Fminutes_S * 60);
			$Fminutes_S = round($Fminutes_S, 0);
			if ($Fminutes_S < 10) {$Fminutes_S = "0$Fminutes_S";}
			$Ftime = "$Fminutes_M_int:$Fminutes_S";
			}
		if ($precision == 'S')
			{
			$Ftime = $sec;
			}
		return "$Ftime";
		}
	}
##### END reformat seconds into HH:MM:SS or MM:SS #####


##### BEGIN counts like elements in an array, optional sort asc desc #####
function array_group_count($array, $sort = false) 
	{
	$tally_array = array();

	$i=0;
	foreach (array_unique($array) as $value) 
		{
		$count = 0;
		foreach ($array as $element) 
			{
		    if ($element == "$value")
		        {$count++;}
			}

		$count =		sprintf("%010s", $count);
		$tally_array[$i] = "$count $value";
		$i++;
		}
	
	if ( $sort == 'desc' )
		{rsort($tally_array);}
	elseif ( $sort == 'asc' )
		{sort($tally_array);}

	return $tally_array;
	}
##### END counts like elements in an array, optional sort asc desc #####


##### BEGIN bar chart using max stats data #####
function horizontal_bar_chart($campaign_id,$days_graph,$title,$link,$metric,$metric_name,$more_link,$END_DATE,$download_link)
	{
	$stats_start_time = time();
	if ($END_DATE) 
		{
		$Bstats_date[0]=$END_DATE;
		}
	else
		{
		$Bstats_date[0]=date("Y-m-d");
		}
	$Btotal_calls[0]=0;
	$link_text='';
	$max_count=0;
	$i=0;
	$NWB = "$download_link &nbsp; <a href=\"javascript:openNewWindow('help.php?ADD=99999";
	$NWE = "')\"><IMG SRC=\"help.gif\" WIDTH=20 HEIGHT=20 BORDER=0 ALT=\"HELP\" ALIGN=TOP></A>";


	### get stats for last X days
	$stmt="SELECT stats_date,$metric from vicidial_daily_max_stats where campaign_id='$campaign_id' and stats_flag='OPEN' and stats_date<='$Bstats_date[0]';";
	if ($metric=='total_calls_inbound_all')
		{$stmt="SELECT stats_date,sum(total_calls) from vicidial_daily_max_stats where stats_type='INGROUP' and stats_flag='OPEN' and stats_date<='$Bstats_date[0]' group by stats_date;";}
	if ($metric=='total_calls_outbound_all')
		{$stmt="SELECT stats_date,sum(total_calls) from vicidial_daily_max_stats where stats_type='CAMPAIGN' and stats_flag='OPEN' and stats_date<='$Bstats_date[0]' group by stats_date;";}
	if ($metric=='ra_total_calls')
		{$stmt="SELECT stats_date,total_calls from vicidial_daily_ra_stats where stats_flag='OPEN' and stats_date<='$Bstats_date[0]' and user='$campaign_id';";}
	if ($metric=='ra_concurrent_calls')
		{$stmt="SELECT stats_date,max_calls from vicidial_daily_ra_stats where stats_flag='OPEN' and stats_date<='$Bstats_date[0]' and user='$campaign_id';";}
	$rslt=mysql_to_mysqli($stmt, $link);
	$Xstats_to_print = mysqli_num_rows($rslt);
	if ($Xstats_to_print > 0) 
		{
		$rowx=mysqli_fetch_row($rslt);
		$Bstats_date[0] =  $rowx[0];
		$Btotal_calls[0] = $rowx[1];
		if ($max_count < $Btotal_calls[0]) {$max_count = $Btotal_calls[0];}
		}
	$stats_date_ARRAY = explode("-",$Bstats_date[0]);
	$stats_start_time = mktime(10, 10, 10, $stats_date_ARRAY[1], $stats_date_ARRAY[2], $stats_date_ARRAY[0]);
	while($i <= $days_graph)
		{
		$Bstats_date[$i] =  date("Y-m-d", $stats_start_time);
		$Btotal_calls[$i]=0;
		$stmt="SELECT stats_date,$metric from vicidial_daily_max_stats where campaign_id='$campaign_id' and stats_date='$Bstats_date[$i]';";
		if ($metric=='total_calls_inbound_all')
			{$stmt="SELECT stats_date,sum(total_calls) from vicidial_daily_max_stats where stats_date='$Bstats_date[$i]' and stats_type='INGROUP' group by stats_date;";}
		if ($metric=='total_calls_outbound_all')
			{$stmt="SELECT stats_date,sum(total_calls) from vicidial_daily_max_stats where stats_date='$Bstats_date[$i]' and stats_type='CAMPAIGN' group by stats_date;";}
		if ($metric=='ra_total_calls')
			{$stmt="SELECT stats_date,total_calls from vicidial_daily_ra_stats where stats_date='$Bstats_date[$i]' and user='$campaign_id';";}
		if ($metric=='ra_concurrent_calls')
			{$stmt="SELECT stats_date,max_calls from vicidial_daily_ra_stats where stats_date='$Bstats_date[$i]' and user='$campaign_id';";}
		echo "<!-- $i) $stmt \\-->\n";
		$rslt=mysql_to_mysqli($stmt, $link);
		$Ystats_to_print = mysqli_num_rows($rslt);
		if ($Ystats_to_print > 0) 
			{
			$rowx=mysqli_fetch_row($rslt);
			$Btotal_calls[$i] =		$rowx[1];
			if ($max_count < $Btotal_calls[$i]) {$max_count = $Btotal_calls[$i];}
			}
		$i++;
		$stats_start_time = ($stats_start_time - 86400);
		}
	if ($max_count < 1) 
		{echo "<!-- no max stats cache summary information available -->";}
	else
		{
		if ($title=='campaign') {$out_in_type=' outbound';}
		if ($title=='in-group') {$out_in_type=' inbound';}
		if ($more_link > 0) {$link_text = "<a href=\"$PHP_SELF?ADD=999993&campaign_id=$campaign_id&stage=$title\"><font size=1>more summary stats...</font></a>";}
		echo "<table cellspacing=\"1\" cellpadding=\"0\" bgcolor=\"white\" summary=\"Multiple day $metric_name.\" style=\"background-image:url(images/bg_fade.png); background-repeat:repeat-x; background-position:left top; width: 33em;\">\n";
		echo "<caption align=\"top\">$days_graph Day $out_in_type $metric_name for this $title &nbsp; $link_text  &nbsp; $NWB#max_stats$NWE<br /></caption>\n";
		echo "<tr>\n";
		echo "<th scope=\"col\" style=\"text-align: left; vertical-align:top;\"><span class=\"auraltext\">date</span> </th>\n";
		echo "<th scope=\"col\" style=\"text-align: left; vertical-align:top;\"><span class=\"auraltext\">$metric_name</span> </th>\n";
		echo "</tr>\n";

		$max_multi = MathZDC(400, $max_count);
		$i=0;
		while($i < $days_graph)
			{
			$bar_width = intval($max_multi * $Btotal_calls[$i]);
			if ($Btotal_calls[$i] < 1) {$Btotal_calls[$i] = "-none-";}
			echo "<tr>\n";
			echo "<td class=\"chart_td\"><font style=\"font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 60%;\">$Bstats_date[$i] </font></td>\n";
			echo "<td class=\"chart_td\"><img src=\"images/bar.png\" alt=\"\" width=\"$bar_width\" height=\"10\" style=\"vertical-align: middle; margin: 2px 2px 2px 0;\"/><font style=\"font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 60%;\"> $Btotal_calls[$i]</font></td>\n";
			echo "</tr>\n";
			$i++;
			}
		echo "</table>\n";
		}
	}
##### END bar chart using max stats data #####


##### BEGIN download max stats data #####
function download_max_system_stats($campaign_id,$days_graph,$title,$metric,$metric_name,$END_DATE)
	{
	global $CSV_text, $link;
	$stats_start_time = time();
	if ($END_DATE) 
		{
		$Bstats_date[0]=$END_DATE;
		}
	else
		{
		$Bstats_date[0]=date("Y-m-d");
		}
	$Btotal_calls[0]=0;
	$link_text='';
	$i=0;

	### get stats for last X days
	$stmt="SELECT stats_date,$metric from vicidial_daily_max_stats where campaign_id='$campaign_id' and stats_flag='OPEN' and stats_date<='$Bstats_date[0]';";
	if ($metric=='total_calls_inbound_all')
		{$stmt="SELECT stats_date,sum(total_calls) from vicidial_daily_max_stats where stats_type='INGROUP' and stats_flag='OPEN' and stats_date<='$Bstats_date[0]' group by stats_date;";}
	if ($metric=='total_calls_outbound_all')
		{$stmt="SELECT stats_date,sum(total_calls) from vicidial_daily_max_stats where stats_type='CAMPAIGN' and stats_flag='OPEN' and stats_date<='$Bstats_date[0]' group by stats_date;";}
	if ($metric=='ra_total_calls')
		{$stmt="SELECT stats_date,total_calls from vicidial_daily_ra_stats where stats_flag='OPEN' and stats_date<='$Bstats_date[0]' and user='$campaign_id';";}
	if ($metric=='ra_concurrent_calls')
		{$stmt="SELECT stats_date,max_calls from vicidial_daily_ra_stats where stats_flag='OPEN' and stats_date<='$Bstats_date[0]' and user='$campaign_id';";}
	$rslt=mysql_to_mysqli($stmt, $link);
	$Xstats_to_print = mysqli_num_rows($rslt);
	if ($Xstats_to_print > 0) 
		{
		$rowx=mysqli_fetch_row($rslt);
		$Bstats_date[0] =  $rowx[0];
		$Btotal_calls[0] = $rowx[1];
		if ($max_count < $Btotal_calls[0]) {$max_count = $Btotal_calls[0];}
		}
	$stats_date_ARRAY = explode("-",$Bstats_date[0]);
	$stats_start_time = mktime(10, 10, 10, $stats_date_ARRAY[1], $stats_date_ARRAY[2], $stats_date_ARRAY[0]);
	while($i <= $days_graph)
		{
		$Bstats_date[$i] =  date("Y-m-d", $stats_start_time);
		$Btotal_calls[$i]=0;
		$stmt="SELECT stats_date,$metric from vicidial_daily_max_stats where campaign_id='$campaign_id' and stats_date='$Bstats_date[$i]';";
		if ($metric=='total_calls_inbound_all')
			{$stmt="SELECT stats_date,sum(total_calls) from vicidial_daily_max_stats where stats_date='$Bstats_date[$i]' and stats_type='INGROUP' group by stats_date;";}
		if ($metric=='total_calls_outbound_all')
			{$stmt="SELECT stats_date,sum(total_calls) from vicidial_daily_max_stats where stats_date='$Bstats_date[$i]' and stats_type='CAMPAIGN' group by stats_date;";}
		if ($metric=='ra_total_calls')
			{$stmt="SELECT stats_date,total_calls from vicidial_daily_ra_stats where stats_date='$Bstats_date[$i]' and user='$campaign_id';";}
		if ($metric=='ra_concurrent_calls')
			{$stmt="SELECT stats_date,max_calls from vicidial_daily_ra_stats where stats_date='$Bstats_date[$i]' and user='$campaign_id';";}
		$rslt=mysql_to_mysqli($stmt, $link);
		$Ystats_to_print = mysqli_num_rows($rslt);
		if ($Ystats_to_print > 0) 
			{
			$rowx=mysqli_fetch_row($rslt);
			$Btotal_calls[$i] =		$rowx[1];
			if ($max_count < $Btotal_calls[$i]) {$max_count = $Btotal_calls[$i];}
			}
		$i++;
		$stats_start_time = ($stats_start_time - 86400);
		}

	if ($title=='campaign') {$out_in_type=' outbound';}
	if ($title=='in-group') {$out_in_type=' inbound';}
	$CSV_text.="\"$days_graph Day $out_in_type $metric_name for this $title\"\n";

	if ($max_count < 1) 
		{$CSV_text.="\"no max stats cache summary information available\"\n";}
	else
		{
		$CSV_text.="\"DATE\",\"$metric_name\"\n";

		$i=0;
		while($i < $days_graph)
			{
			$bar_width = intval($max_multi * $Btotal_calls[$i]);
			if ($Btotal_calls[$i] < 1) {$Btotal_calls[$i] = "-none-";}
			$CSV_text.="\"$Bstats_date[$i]\",\"$Btotal_calls[$i]\"\n";
			$i++;
			}

		$CSV_text.="\n\n";
		}
	}
##### END download max stats data #####


function mysql_to_mysqli($stmt, $link) 
	{
	$rslt=mysqli_query($link, $stmt);
	return $rslt;
	}


function MathZDC($dividend, $divisor, $quotient=0) 
	{
	if ($divisor==0) 
		{
		return $quotient;
		}
	else if ($dividend==0) 
		{
		return 0;
		}
	else 
		{
		return ($dividend/$divisor);
		}
	}


# function to print/echo content, options for length, alignment and ordered internal variables are included
function _QXZ($English_text, $sprintf=0, $align="l", $v_one='', $v_two='', $v_three='', $v_four='', $v_five='', $v_six='', $v_seven='', $v_eight='', $v_nine='')
	{
	global $SSenable_languages, $SSlanguage_method, $VUselected_language, $link;

	if ($SSenable_languages == '1')
		{
		if ($SSlanguage_method != 'DISABLED')
			{
			if ( (strlen($VUselected_language) > 0) and ($VUselected_language != 'default English') )
				{
				if ($SSlanguage_method == 'MYSQL')
					{
					$stmt="SELECT translated_text from vicidial_language_phrases where english_text='$English_text' and language_id='$VUselected_language';";
					$rslt=mysql_to_mysqli($stmt, $link);
					$sl_ct = mysqli_num_rows($rslt);
					if ($sl_ct > 0)
						{
						$row=mysqli_fetch_row($rslt);
						$English_text =		$row[0];
						}
					}
				}
			}
		}

	if (preg_match("/%\ds/",$English_text))
		{
		$English_text = preg_replace("/%1s/", $v_one, $English_text);
		$English_text = preg_replace("/%2s/", $v_two, $English_text);
		$English_text = preg_replace("/%3s/", $v_three, $English_text);
		$English_text = preg_replace("/%4s/", $v_four, $English_text);
		$English_text = preg_replace("/%5s/", $v_five, $English_text);
		$English_text = preg_replace("/%6s/", $v_six, $English_text);
		$English_text = preg_replace("/%7s/", $v_seven, $English_text);
		$English_text = preg_replace("/%8s/", $v_eight, $English_text);
		$English_text = preg_replace("/%9s/", $v_nine, $English_text);
		}
	### uncomment to test output
	#	$English_text = str_repeat('*', strlen($English_text));
	if ($sprintf>0) 
		{
		if ($align=="r") 
			{
			$fmt="%".$sprintf."s";
			} 
		else 
			{
			$fmt="%-".$sprintf."s";
			}
		$English_text=sprintf($fmt, $English_text);
		}
	return $English_text;
	}

?>
