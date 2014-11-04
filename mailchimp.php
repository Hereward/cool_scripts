<?php

$log_path = dirname(__FILE__) . '/mailchimp_log.txt';
$root_path = '/home/planetonline/websites/truthnews';
require_once("$root_path/includes/dev_log.php");
require_once("$root_path/includes/MailChimp.php");

define(BASEPATH, $root_path);
dev_log::init($log_path, 1);
dev_log::write('BEGIN MAILCHIMP');

$chimp = new \Drewm\MailChimp('9e28985b6c7059deb5dace9e6d6d2cd1-us1');

$db_conf_path = '/home/planetonline/websites/truthnews/ee/expressionengine/config/database.php';
include $db_conf_path;

$mysqli = mysqli_connect($db['expressionengine']['hostname'], $db['expressionengine']['username'], $db['expressionengine']['password'], $db['expressionengine']['database']);

if (mysqli_connect_errno($mysqli)) {
    dev_log::write("Failed to connect to MySQL: " . mysqli_connect_error());
    die();
}

$sql = "SELECT * FROM tna_subscriber_details LEFT JOIN exp_members ON tna_subscriber_details.member_id = exp_members.member_id WHERE tna_subscriber_details.mailout=0 ORDER BY `tna_subscriber_details`.`member_id` ASC";

$results = mysqli_query($mysqli, $sql);
do_error($mysqli, $sql, $results);

if ($results->num_rows < 1) {
    dev_log::write("ZERO results returned from main query, I guess there's nothing to do here!");
    dev_log::write('END MAILCHIMP');
    die();
}

$results->data_seek(0);
$subs = array();
while ($row = $results->fetch_assoc()) {
    $subs[] = $row['member_id'];

    add_to_list($chimp, $row['first_name'], $row['last_name'], $row['email']);
}

update_list($mysqli,$subs);
  
        
dev_log::write('END MAILCHIMP');

$mysqli->close();


function add_to_list($chimp, $fname, $lname, $email) {
    $msg = "ADD: $fname $lname $email";
    dev_log::write($msg);
    $result = $chimp->call('lists/subscribe', array(
					'id'                => 'f51df448d4',
					'email'             => array('email'=>$email),
					'merge_vars'        => array('FNAME'=>$fname, 'LNAME'=>$lname),
					'double_optin'      => false,
					'update_existing'   => true,
					'replace_interests' => false,
					'send_welcome'      => false,
				));
    
    $msg = print_r($result, true);
    dev_log::write("RESULT: $msg");
}

function update_list($mysqli,$subs) {
    $str = implode(",", $subs);
    
    $sql = "UPDATE tna_subscriber_details set mailout = 1 WHERE member_id IN ($str)";
    dev_log::write($sql);
    $results = mysqli_query($mysqli, $sql);
    do_error($mysqli, $sql, $results);
    
    dev_log::write("AFFECTED ROWS = ".$mysqli->affected_rows);
  
}

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
