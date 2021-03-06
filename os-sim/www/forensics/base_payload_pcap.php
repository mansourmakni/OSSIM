<?php
/**
* Class and Function List:
* Function list:
* Classes list:
*/
/*******************************************************************************
** OSSIM Forensics Console
** Copyright (C) 2009 OSSIM/AlienVault
** Copyright (C) 2004 BASE Project Team
** Copyright (C) 2000 Carnegie Mellon University
**
** (see the file 'base_main.php' for license details)
**
** Built upon work by Roman Danyliw <rdd@cert.org>, <roman@danyliw.com>
** Built upon work by the BASE Project Team <kjohnson@secureideas.net>
*/
include_once ("base_conf.php");
include_once ("$BASE_path/includes/base_constants.inc.php");
include_once ("$BASE_path/includes/base_include.inc.php");
// Check role out and redirect if needed -- Kevin
$roleneeded = 10000;
$BUser = new BaseUser();
if (($BUser->hasRole($roleneeded) == 0) && ($Use_Auth_System == 1)) {
    base_header("Location: " . $BASE_urlpath . "/index.php");
    exit();
}
//$cid = ImportHTTPVar("cid", VAR_DIGIT);
//$sid = ImportHTTPVar("sid", VAR_DIGIT);
/* Connect to the Alert database. */
$db = NewBASEDBConnection($DBlib_path, $DBtype);
$db->baseDBConnect($db_connect_method, $alert_dbname, $alert_host, $alert_port, $alert_user, $alert_password);
$sql2 = "SELECT '', '', data_payload FROM extra_data ";
$sql2.= "WHERE sid='" . $sid . "' AND cid='" . $cid . "'";
$result2 = $db->baseExecute($sql2);
$myrow2 = $result2->baseFetchRow();
$result2->baseFreeRows();
/* Get encoding information for current sensor. */
$sql3 = 'SELECT encoding FROM sensor WHERE sid=' . $sid;
$result3 = $db->baseExecute($sql3);
$myrow3 = $result3->baseFetchRow();
$result3->baseFreeRows();
$ip_sql = "SELECT ip_ver, ip_hlen, ip_tos, ip_len, ip_id, ip_flags, ip_off,";
$ip_sql.= "ip_ttl, ip_proto, ip_csum, ip_src, ip_dst FROM iphdr ";
$ip_sql.= "WHERE sid='" . $sid . "' AND cid='" . $cid . "'";
//echo $ip_sql;
$ip_res = $db->baseExecute($ip_sql);
$ip = $ip_res->baseFetchRow();
$ip_res->baseFreeRows();
$l4_sql = "";
if ($ip[8] == 1) {
    $l4_sql = "SELECT icmp_type, icmp_code, icmp_csum, icmp_id, icmp_seq ";
    $l4_sql.= "FROM icmphdr WHERE sid='" . $sid . "' AND cid='" . $cid . "'";
} elseif ($ip[8] == 6) {
    $l4_sql = "SELECT tcp_sport, tcp_dport, tcp_seq, tcp_ack, tcp_off,";
    $l4_sql.= "tcp_res, tcp_flags, tcp_win, tcp_csum, tcp_urp from tcphdr ";
    $l4_sql.= "WHERE sid='" . $sid . "' AND cid='" . $cid . "'";
} elseif ($ip[8] == 17) {
    $l4_sql = "SELECT udp_sport, udp_dport, udp_len, udp_csum FROM udphdr ";
    $l4_sql.= "WHERE sid='" . $sid . "' AND cid='" . $cid . "'";
}
// Error when l4_res = ""
if ($l4_sql != "") {
    $l4_res = $db->baseExecute($l4_sql);
    $l4 = $l4_res->baseFetchRow();
    $l4_res->baseFreeRows();
}
/* 0 == hex, 1 == base64, 2 == ascii; cf. snort-2.4.4/src/plugbase.h */
if ($myrow3[0] == 0) {
        $pcap_header = $myrow2[0];
        $data_header = $myrow2[1];
        $data_payload = $myrow2[2];
} elseif ($myrow3[0] == 1) {
        $pcap_header = bin2hex(base64_decode($myrow2[0]));
        $data_header = bin2hex(base64_decode($myrow2[1]));
        $data_payload = bin2hex(base64_decode($myrow2[2]));
} else {
    /* database contains neither hex nor base64 encoding. */
    //header ('HTTP/1.0 200');
    //header ('Content-Type: text/html');
    //print "<h1> File not found:</h1>";
    //print "<br>Only HEX and BASE64 encoding types are supported, nothing else.";
    //print "<br><br><hr><i>Generated by base_payload.php</i><br>";
    exit;
}
/*
* Calculating snaplen which is length of payload plus header,
* for HEX we have to divide by two -> two HEX characters
* represent one binary byte.
*/
// tack an ethernet header on there
$data_header = "DEADCAFEBABE1122334455660800";
// later on, all of this gets interpreted as hex, so simply
// pull the values from the db, convert them to hex, 0-pad them
// as necessary, and tack them together.
$data_header.= sprintf("%02s", $ip[0] . $ip[1]); // ver&ihl
$data_header.= sprintf("%02s", dechex($ip[2])); // tos
$data_header.= sprintf("%04s", dechex($ip[3])); // len
$data_header.= sprintf("%04s", dechex($ip[4])); // id
$data_header.= sprintf("%04s", dechex(($ip[5]<<13)|$ip[6])); // flags & offset
$data_header.= sprintf("%02s", dechex($ip[7])); // ttl
$data_header.= sprintf("%02s", dechex($ip[8])); // proto
$data_header.= sprintf("%04s", dechex($ip[9])); // csum.
// http://us2.php.net/manual/en/function.dechex.php#71795
# source IP
$chars = ($ip[10] <= 0x0fffffff) ? 1 : 0;
$data_header.= sprintf("%02s", substr(dechex((float) $ip[10]),0,2-$chars));
for ($i = 1; $i < 4; $i++) $data_header.= sprintf("%02s", substr(dechex((float) $ip[10]), $i*2-$chars, 2));

# dest IP
$chars = ($ip[11] <= 0x0fffffff) ? 1 : 0;
$data_header.= sprintf("%02s", substr(dechex((float) $ip[11]),0,2-$chars));
for ($i = 1; $i < 4; $i++) $data_header.= sprintf("%02s", substr(dechex((float) $ip[11]), $i*2-$chars, 2));
			
if ($ip[8] == 1) {
    $data_header.= sprintf("%02s", dechex((float)$l4[0])); // type
    $data_header.= sprintf("%02s", dechex((float)$l4[1])); // code
    $data_header.= sprintf("%04s", dechex((float)$l4[2])); // sum
    // only echo req/rep, timestamp, info req/rep have id/seq
    if ($l4[0] == 0 || $l4[0] == 8 || ($l4[0] >= 13 && $l4[0] <= 16)) {
        $data_header.= sprintf("%04s", dechex((float)$l4[3])); // id
        $data_header.= sprintf("%04s", dechex((float)$l4[4])); // seq
        
    }
} elseif ($ip[8] == 6) {
    $data_header.= sprintf("%04s", dechex((float)$l4[0])); // source port
    $data_header.= sprintf("%04s", dechex((float)$l4[1])); // dest port
    $data_header.= sprintf("%08s", dechex((float)$l4[2])); // seq #
    $data_header.= sprintf("%08s", dechex((float)$l4[3])); // ack #
    $data_header.= sprintf("%01s", dechex((float)$l4[4])); // offset
    $data_header.= sprintf("%03s", dechex((float)$l4[6])); // flags
    $data_header.= sprintf("%04s", dechex((float)$l4[7])); // window
    $data_header.= sprintf("%04s", dechex((float)$l4[8])); // checksum
    $data_header.= sprintf("%04s", dechex((float)$l4[9])); // urg ptr
    // walk opts...
    $tcp_opt_sql = "SELECT optid, opt_code, opt_len, opt_data FROM opt ";
    $tcp_opt_sql.= "WHERE sid='" . $sid . "' AND cid='" . $cid . "' AND opt_proto=6 ORDER BY optid ASC";
    $tcp_opt_res = $db->baseExecute($tcp_opt_sql);
    $tcp_opt_data = "";
    while ($tcp_opt = $tcp_opt_res->baseFetchRow()) {
        $tcp_opt_data.= sprintf("%02s", dechex((float)$tcp_opt[1]));
        // if opt_len == 0, its an "opt kind", and thus has no length or data
        if ($tcp_opt[2] != 0) {
            $tcp_opt_data.= sprintf("%02s", dechex((float)$tcp_opt[2] + 2));
            $tcp_opt_data.= $tcp_opt[3];
        }
    }
    $tcp_opt_res->baseFreeRows();
    $data_header.= $tcp_opt_data;
} elseif ($ip[8] == 17) {
    $data_header.= sprintf("%04s", dechex((float)$l4[0])); // source port
    $data_header.= sprintf("%04s", dechex((float)$l4[1])); // dest port
    $data_header.= sprintf("%04s", dechex((float)$l4[2])); // len
    $data_header.= sprintf("%04s", dechex((float)$l4[3])); // sum
    
}
$snaplen = (strlen($data_header) + strlen($data_payload)) / 2;
/* Create pcap file header. */
$hdr['magic'] = pack('L', 0xa1b2c3d4); /* unsigned long  (always 32 bit, machine byte order) */
$hdr['version_major'] = pack('S', 2); /* unsigned short (always 16 bit, machine byte order) */
$hdr['version_minor'] = pack('S', 4); /* unsigned short (always 16 bit, machine byte order) */
$hdr['thiszone'] = pack('I', 0); /* signed   long  (always 32 bit, machine byte order) */
$hdr['sigfigs'] = pack('L', 0); /* unsigned long  (always 32 bit, machine byte order) */
$hdr['snaplen'] = pack('L', $snaplen); /* unsigned long  (always 32 bit, machine byte order) */
$hdr['linktype'] = pack('L', 1); /* unsigned long  (always 32 bit, machine byte order) */
/* Create pcap packet header. Converting hex to decimal and then to network byte order (big endian). */
$ts_sql = "SELECT timestamp FROM acid_event ";
$ts_sql.= "WHERE sid='" . $sid . "' AND cid='" . $cid . "'";
$ts_res = $db->baseExecute($ts_sql);
$ts_string = $ts_res->baseFetchRow();
$ts_res->baseFreeRows();
$ts = strtotime($ts_string[0]);
list(, $phdr['timeval_sec']) = unpack('L', pack('L', $ts));
list(, $phdr['timeval_usec']) = unpack('L', pack('L', 0));
list(, $phdr['caplen']) = unpack('L', pack('L', $snaplen));
list(, $phdr['len']) = unpack('L', pack('L', $snaplen));
/* Copy header to packet, convert hex to dec and from dec to char. */
for ($i = 0; $i < strlen($data_header); $i = $i + 2) $packet.= chr(hexdec(substr($data_header, $i, 2)));
/* Copy payload to packet, convert hex to dec and from dec to char. */
for ($i = 0; $i < strlen($data_payload); $i = $i + 2) $packet.= chr(hexdec(substr($data_payload, $i, 2)));
$f = fopen("/var/tmp/base_packet_" . $sid . "-" . $cid . ".pcap", "w");
/* Writing pcap file header */
foreach($hdr as $value) fputs($f, $value);
foreach($phdr as $value) fputs($f, pack('L', $value));
/* Writing packet */
fputs($f, $packet);
fclose($f);
?>
<h1><?php echo _("pcap File")?>:</h1>
<link rel="stylesheet" type="text/css" href="../style/tree.css" />
<script type="text/javascript" src="../js/jquery-ui-1.7.custom.min.js"></script>
<script type="text/javascript" src="../js/jquery.tmpl.1.1.1.js"></script>
<script type="text/javascript" src="../js/jquery.dynatree.js"></script>
<script type="text/javascript">
var loading = '<br/><img src="../pixmaps/theme/loading2.gif" border="0" align="absmiddle"><span style="margin-left:5px"><?php echo _("Loading tree")?>...</span>';
var layer = '#container';
var nodetree = null;
function load_tree(filter) {
	$('#loading').html(loading);
	$.ajax({
		type: "GET",
		url: "base_payload_tshark_tree.php",
		data: "cid=<?php echo $cid?>&sid=<?php echo $sid?>",
		success: function(msg) { 
			//alert (msg);
			$(layer).html(msg);
			$(layer).dynatree({
				clickFolderMode: 2,
				imagePath: "../forensics/styles",
				onActivate: function(dtnode) {
					//alert(dtnode.data.url);
				},
				onDeactivate: function(dtnode) {}
			});
			nodetree = $(layer).dynatree("getRoot");
			$('#loading').html("");
		}
	});
}
</script>
<style type='text/css'>
	.dynatree-container {
		border:none !important;
	}
	
	.container {
		line-height:16px
	}
	
</style>
<div id="loading"></div>
<div id="container"></div>
