<?php
/*****************************************************************************
*
*    License:
*
*   Copyright (c) 2003-2006 ossim.net
*   Copyright (c) 2007-2009 AlienVault
*   All rights reserved.
*
*   This package is free software; you can redistribute it and/or modify
*   it under the terms of the GNU General Public License as published by
*   the Free Software Foundation; version 2 dated June, 1991.
*   You may not use, modify or distribute this program under any other version
*   of the GNU General Public License.
*
*   This package is distributed in the hope that it will be useful,
*   but WITHOUT ANY WARRANTY; without even the implied warranty of
*   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*   GNU General Public License for more details.
*
*   You should have received a copy of the GNU General Public License
*   along with this package; if not, write to the Free Software
*   Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,
*   MA  02110-1301  USA
*
*
* On Debian GNU/Linux systems, the complete text of the GNU General
* Public License can be found in `/usr/share/common-licenses/GPL-2'.
*
* Otherwise you can read it here: http://www.gnu.org/licenses/gpl-2.0.txt
****************************************************************************/
/**
* Class and Function List:
* Function list:
* Classes list:
*/
require_once ('classes/Session.inc');
require_once ('classes/Security.inc');
require_once ('ossim_db.inc');
require_once ('classes/Net.inc');
require_once ('classes/Net_group.inc');
require_once ('classes/Net_group_scan.inc');
require_once ('classes/Util.inc');

Session::logcheck("MenuPolicy", "PolicyNetworks");

$error = false;

$descr       = POST('descr');
$ngname      = POST('ngname');
$threshold_a = POST('threshold_a');
$threshold_c = POST('threshold_c');
$rrd_profile = POST('rrd_profile');
$networks    = ( isset($_POST['nets'] ) && !empty ( $_POST['nets']) ) ? Util::clean_array(POST('nets')) : array();

$num_networks = count($networks);

$validate = array (
	"ngname"      => array("validation"=>"OSS_ALPHA, OSS_SPACE, OSS_PUNC", "e_message" => 'illegal:' . _("Network Group Name")),
	"descr"       => array("validation"=>"OSS_ALPHA, OSS_NULLABLE, OSS_SPACE, OSS_PUNC, OSS_AT, OSS_NL", "e_message" => 'illegal:' . _("Description")),
	"nets"       => array("validation"=>"OSS_ALPHA, OSS_SCORE, OSS_PUNC, OSS_AT", "e_message" => 'illegal:' . _("Networks")),
	"rrd_profile" => array("validation"=>"OSS_ALPHA, OSS_NULLABLE, OSS_SPACE, OSS_PUNC", "e_message" => 'illegal:' . _("RRD Profile")),
	"threshold_a" => array("validation"=>"OSS_DIGIT", "e_message" => 'illegal:' . _("Threshold A")),
	"threshold_c" => array("validation"=>"OSS_DIGIT", "e_message" => 'illegal:' . _("Threshold C")),
	"nagios"      => array("validation"=>"OSS_NULLABLE, OSS_DIGIT", "e_message" => 'illegal:' . _("Nagios")));
	
if ( GET('ajax_validation') == true )
{
	$validation_errors = validate_form_fields('GET', $validate);
	if ( $validation_errors == 1 )
		echo 1;
	else if ( empty($validation_errors) )
		echo 0;
	else
		echo $validation_errors[0];
		
	exit();
}
else
{
	$validation_errors = validate_form_fields('POST', $validate);
	
	if ( ( $validation_errors == 1 ) ||  (is_array($validation_errors) && !empty($validation_errors)) || $num_networks == 0 )
	{
		$error = true;
		
		$message_error = array();
		
		if( $num_networks == 0)
			$message_error [] = _("You Need to select at least one Network");
		
		if ( is_array($validation_errors) && !empty($validation_errors) )
			$message_error = array_merge($message_error, $validation_errors);
		else
		{
			if ($validation_errors == 1)
				$message_error [] = _("Invalid send method");
		}
	}
	
	if ( POST('ajax_validation_all') == true )
	{
		if ( is_array($message_error) && !empty($message_error) )
			echo utf8_encode(implode( "<br/>", $message_error));
		else
			echo 0;
		
		exit();
	}
}


if ( $error == true )
{
	$_SESSION['_netgroup']['descr']       = $descr;
	$_SESSION['_netgroup']['ngname']      = $ngname;
	$_SESSION['_netgroup']['threshold_a'] = $threshold_a;
	$_SESSION['_netgroup']['threshold_c'] = $threshold_c;
	$_SESSION['_netgroup']['rrd_profile'] = $rrd_profile;
	$_SESSION['_netgroup']['networks']    = $networks;
}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title> <?php echo gettext("OSSIM Framework"); ?> </title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"/>
	<meta http-equiv="Pragma" content="no-cache">
	<link type="text/css" rel="stylesheet" href="../style/style.css"/>
</head>

<body>

<?php
if (POST('withoutmenu') != "1") 
{
	include ("../hmenu.php"); 
	$get_param = "name=$ngname";
}
else
	$get_param = "name=$ngname&withoutmenu=1";

if ( POST('insert') && !empty($ngname) )
{
    if ( $error == true)
	{
		$txt_error = "<div>"._("We Found the following errors").":</div><div style='padding:10px;'>".implode( "<br/>", $message_error)."</div>";				
		Util::print_error($txt_error);	
		Util::make_form("POST", "newnetgroupform.php?".$get_param);
		die();
	}
	
	$db = new ossim_db();
    $conn = $db->connect();
	
		
    Net_group::update($conn, $ngname, $threshold_c, $threshold_a, $rrd_profile, $networks, $descr);
    Net_group_scan::delete($conn, $ngname, 3001);
    
	//if (POST('nessus')) { Net_group_scan::insert($conn, $ngname, 3001, 0); }
    
	$db->close($conn);
	
	Util::clean_json_cache_files("(policy|vulnmeter|hostgroup)");
}

if ( isset($_SESSION['_netgroup']) )
	unset($_SESSION['_netgroup']);

    if ( $_SESSION["menu_sopc"]=="Network groups" && POST('withoutmenu') != "1" ) {
        ?>
        <p> <?php echo gettext("Network Group succesfully updated"); ?> </p>
        <script>document.location.href="netgroup.php"</script>
        <?
    }
    else {
    ?>
        <script>document.location.href="newnetgroupform.php?<?php echo $get_param; ?>&update=1"</script>
    <?php 
    }
    ?>
	</body>
</html>

