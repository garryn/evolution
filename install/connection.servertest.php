<?php

$host = $_POST['host'];
$uid = $_POST['uid'];
$pwd = $_POST['pwd'];

require_once("lang.php");

$output = $_lang["status_connecting"];
if (!$conn = @ mysql_connect($host, $uid, $pwd)) {
    $output .= '<span id="server_fail" style="color:#FF0000;"> '.$_lang['status_failed'].'</span>';
}
else {
    $output .= '<span id="server_pass" style="color:#80c000;"> '.$_lang['status_passed_server'].'</span>';

    // Mysql version check
    if ( version_compare(mysql_get_server_info(), '5.0.51', '=') ) {
        $output .= '<br /><span style="color:#FF0000;"> '.$_lang['mysql_5051'].'</span>';
    }
    // Mode check
    $mysqlmode = @ mysql_query("SELECT @@session.sql_mode");
    if (@mysql_num_rows($mysqlmode) > 0){
        $modes = mysql_fetch_array($mysqlmode, MYSQL_NUM);
        $strictMode = false;
        foreach ($modes as $mode) {
    		    if (strtoupper($mode) == "STRICT_TRANS_TABLES") $strictMode = true;
        }
        if ($strictMode) $output .= '<br /><span style="color:#FF0000;"> '.$_lang['strict_mode'].'</span>';
    }
}
echo $output;
?>