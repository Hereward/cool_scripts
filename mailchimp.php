<?php

$log_path = dirname(__FILE__) . '/tna_log.txt';
$root_path = '/home/planetonline/websites/truthnews/';
require_once("/home/planetonline/websites/truthnews/includes/dev_log.php");
define(BASEPATH, $root_path);
dev_log::init($log_path, 1);
//dev_log::write('BEGIN');

$db_conf_path = '/home/planetonline/websites/truthnews/ee/expressionengine/config/database.php';
include $db_conf_path;


require("$root_path/includes/mailchimp/vendor/autoload.php");
$MailChimp = new \Drewm\MailChimp('9e28985b6c7059deb5dace9e6d6d2cd1-us1');
print_r($MailChimp->call('lists/list'));




/*


$mysqli = mysqli_connect($db['expressionengine']['hostname'], $db['expressionengine']['username'], $db['expressionengine']['password'], $db['expressionengine']['database']);

if (mysqli_connect_errno($mysqli)) {
    dev_log::write("Failed to connect to MySQL: " . mysqli_connect_error());
    die();
}


$base_query = "SELECT * FROM exp_channel_titles LEFT JOIN exp_channel_data ON exp_channel_titles.entry_id=exp_channel_data.entry_id WHERE exp_channel_titles.channel_id = $channel AND exp_channel_data.$publish_to_youtube_name = 'yes' ORDER BY exp_channel_titles.entry_id ASC";
dev_log::write("base_query = $base_query");
$results = mysqli_query($mysqli, $base_query);

do_error($mysqli, $sql, $results);

if ($results->num_rows < 1) {
    dev_log::write("ZERO results returned from main query, I guess there's nothing to do here!");
    dev_log::write('END');
    die();
}

$results->data_seek(0);

$row = $results->fetch_assoc();
 * 
 */





function do_error($mysqli, $sql, $result = '') {
    $msg = '';
    if ($result) {
        $msg = '';
    } else {
        $msg = "DB Error: SQL = [$sql] Error = [$mysqli->errno $mysqli->error]";
    }

    if ($msg) {
        dev_log::write($msg);
        die();
    }
}
