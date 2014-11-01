<?php

$log_path = dirname(__FILE__) . '/tna_log.txt';
$root_path = '/home/planetonline/websites/truthnews/radio/video_conversion';
$src_path = '/home/planetonline/websites/truthnews/radio/export';
$media_input_path = '/home/planetonline/websites/truthnews/radio/export';
$media_output_path = '/home/planetonline/websites/truthnews/radio/video_conversion/export';
define(BASEPATH, $root_path);
$db_conf_path = '/home/planetonline/websites/truthnews/ee/expressionengine/config/database.php';
$generic_image_path = '/home/planetonline/websites/truthnews/storage/images/framework/tna_vi_generic.png';
$privacy_status = 'private'; //'public' 'private';
include $db_conf_path;
$allow = true;

//chdir($root_path);

require_once("/home/planetonline/websites/truthnews/includes/dev_log.php");

dev_log::init($log_path, 1);
dev_log::write('BEGIN');
$conf = array();

$mysqli = mysqli_connect($db['expressionengine']['hostname'], $db['expressionengine']['username'], $db['expressionengine']['password'], $db['expressionengine']['database']);

if (mysqli_connect_errno($mysqli)) {
    dev_log::write("Failed to connect to MySQL: " . mysqli_connect_error());
    die();
}

$channel = get_channel($mysqli);
$youtube_image_name = 'field_id_' . get_fid($mysqli, 'youtube_image');
$publish_to_youtube_name = 'field_id_' . get_fid($mysqli, 'publish_to_youtube');

$media_date_name = 'field_id_' . get_fid($mysqli, 'media_date');
$title_name = 'title';
$description_name = 'field_id_' . get_fid($mysqli, 'description');
$media_segments_name = 'field_id_' . get_fid($mysqli, 'media_segments');



$base_query = "SELECT * FROM exp_channel_titles LEFT JOIN exp_channel_data ON exp_channel_titles.entry_id=exp_channel_data.entry_id WHERE exp_channel_titles.channel_id = $channel AND exp_channel_data.$publish_to_youtube_name = 'yes' ORDER BY exp_channel_titles.entry_id ASC";
dev_log::write("base_query = $base_query");
$results = mysqli_query($mysqli, $base_query);

do_error($mysqli, $sql, $results);

/*
  if (!$results) {
  dev_log::write("SELECT query failed: (" . $mysqli->errno . ") " . $mysqli->error);
  die();
  }

 */

if ($results->num_rows < 1) {
    dev_log::write("ZERO results returned from main query, I guess there's nothing to do here!");
    die();
}

$results->data_seek(0);

$row = $results->fetch_assoc();
$img_path = '';
$ulid = '';
$yi_str = $row[$youtube_image_name];

//dev_log::write("yi_str =[$yi_str]");


if ($yi_str) {
    $ulid = extract_ulid($yi_str);
    $img_root_path = get_up($mysqli, $ulid);
    $img_path = replace_ulid($yi_str, $img_root_path);
} else {
    $img_path = $generic_image_path;
}

dev_log::write("channel=[$channel] yi_name=[$youtube_image_name] py_name=[$publish_to_youtube_name] ulid=[$ulid] img_root_path=[$img_root_path] img_path=[$img_path]");

$media_segments = $row[$media_segments_name];
$media_date = $row[$media_date_name];
$title = $row[$title_name];
dev_log::write("TITLE = [$title]");
$description = $row[$description_name] . " \n\n";
$url_title = $row['url_title'];
$entry_id = $row['entry_id'];
$weblink = "http://www.truthnews.com.au/radio/story/$url_title";
$subscribe_link = "http://www.truthnews.com.au/subscribe";

$description .= "Visit the website: $weblink \n";
$description .= "Become a subscriber: $subscribe_link \n";

$media_inputs = array();
$media_outputs = array();


for ($index = 0; $index < $media_segments; $index++) {
    $seg = $index + 1;
    $num_tag = ($index) ? '_' . $seg : '';
    $mp3_name = "TNRA_$media_date" . $num_tag . ".mp3";
    $mp4_name = "TNRA_$media_date" . $num_tag . ".mp4";
    $media_inputs[] = "$media_input_path/$mp3_name";
    $media_outputs[] = "$media_output_path/$mp4_name";
    dev_log::write(
            "num_tag = [$num_tag] " .
            "media_input=[$media_input_path/$mp3_name] exists=[" . file_exists("$media_input_path/$mp3_name") . "] " .
            "media_output=$media_output_path/$mp4_name exists=[" . file_exists("$media_output_path/$mp4_name") . "]"
    );
}



/*
  if (file_exists($media_outputs[0]) || !file_exists($media_inputs[0])) {
  $allow = false;
  } else {
  $allow = true;
  }
 * 
 */


dev_log::write("allow=[$allow] img_path=[$img_path] media_segments=[$media_segments] media_date=[$media_date] title=[$title] description=[$description]");


$i = 0;
$total_errors = 0;
foreach ($media_inputs as $input) {
    $sample = "$root_path/TNRA_20141004_2_subscriber_short.mp3";
    //dev_log::write("SAMPLE PATH=[$sample]");
    $input = $sample;
    $locked = upload_status($mysqli, $media_outputs[$i], 1);
    $input_exists = file_exists($input);
    
    
    $errors = 0;
    if (!$locked && $input_exists) {
        dev_log::write("INPUT file = [$input] EXISTS, YAY!");
        
        if (file_exists($media_outputs[$i])) {
            unlink($media_outputs[$i]);
        }

        $ffmpeg_error = convert_video($img_path, $input, $media_outputs[$i]);
        
        if (file_exists($media_outputs[$i])) {
            if ($ffmpeg_error) {
                $errors++;
                dev_log::write("FFMPEG REPORTS AN ERROR!");
            } else {
                chdir("$root_path/py_scripts");
                dev_log::write("OUTPUT FILE ({$media_outputs[$i]} EXISTS > DO UPLOAD.");
                if ($i == 1) {
                    $title = substr($title, 0, 85) . " - Part 2";
                    $description = "HOUR 2: " . $description;
                } elseif ($i == 0) {
                    if (count($media_inputs) > 1) {
                        $title = substr($title, 0, 85) . " - Part 1";
                        $description = "HOUR 1: " . $description;
                    }
                }
                $python_error = 1; //youtube_upload($media_outputs[$i], $title, $description, 'test', '22', $privacy_status);
                if ($python_error) {
                    $errors++;
                    dev_log::write("PYTHON REPORTS AN ERROR!");
                }
            }

            //break 2;
        } else {
            $errors++;
            dev_log::write("OUTPUT FILE ({$media_outputs[$i]} NOT FOUND > CANCEL UPLOAD.");
            //continue 2;
        }
        
    } else {
        if (!$input_exists) {
            dev_log::write("INPUT file [$input] DOES NOT EXIST, BOO!");
            $errors++;
        }
         
        if ($locked) {
            dev_log::write("INPUT file [$input] IS LOCKED, MOVE ON!");
        }
    }
    if (!$errors) {
        post_upload($mysqli, $media_outputs[$i]);
    }
    
    $total_errors += $errors;
    $i++;
}

if ($total_errors == 0) {
    deactivate($mysqli,$entry_id);
}


//$mysqli->close();
//die();

/*
 * $res = $mysqli->query("SELECT id FROM test ORDER BY id ASC");
  $res->num_rows;
  $res->data_seek(0);
  while ($row = $res->fetch_assoc()) {
  echo " id = " . $row['id'] . "\n";
  }

  $result->close();

  $mysqli->close();

 */

dev_log::write('END');

$mysqli->close();

function get_channel($mysqli) {

    $sql = "SELECT * FROM exp_channels WHERE channel_name = 'tna'";
    $result = mysqli_query($mysqli, $sql);

    //dev_log::write($sql);
    //dev_log::write("num_rows = $result->num_rows");

    if ($result->num_rows) {
        $result->data_seek(0);
        $row = $result->fetch_assoc();
        return $row['channel_id'];
    } else {
        return '';
    }
}

function get_fid($mysqli, $name) {

    $sql = "SELECT * FROM exp_channel_fields WHERE field_name = '$name'";
    $result = mysqli_query($mysqli, $sql);

    //dev_log::write($sql);
    //dev_log::write("num_rows = $result->num_rows");

    if ($result->num_rows) {
        $result->data_seek(0);
        $row = $result->fetch_assoc();
        return $row['field_id'];
    } else {
        return '';
    }
}

function extract_ulid($data) {
    $subject = $data;
    $pattern = '/^{filedir_(\d+)}/';

    //dev_log::write("extract_ulid: subject=[$subject] pattern=[$pattern]");
    preg_match($pattern, $subject, $matches);
    //dev_log::write("extract_ulid: matches[1]= [{$matches[1]}]");

    return $matches[1];
}

function replace_ulid($data, $path) {
    $pattern = '/^{filedir_(\d+)}(.+)$/';

    $replacement = $path . '$2';
    $output = preg_replace($pattern, $replacement, $data);
    //dev_log::write("replace_ulid: pattern =[$pattern] replacement=[$replacement] output = [$output]");
    return $output;
}

function get_up($mysqli, $id) {

    $sql = "SELECT * FROM exp_upload_prefs WHERE id = $id";
    $result = mysqli_query($mysqli, $sql);

    //dev_log::write($sql);
    // dev_log::write("num_rows = $result->num_rows");

    if ($result->num_rows) {
        $result->data_seek(0);
        $row = $result->fetch_assoc();
        return $row['server_path'];
    } else {
        return '';
    }
}

function convert_video($v_image, $v_source, $v_output) {
    //$cwd = getcwd();
    //dev_log::write("CWD = $cwd");
//ffmpeg -loop 1 -i tna_screen_grab_640p.jpg -i TNRA_20141004_2_subscriber_short.mp3 -shortest -vcodec libx264 -crf 23 -preset medium -acodec copy TNRA_20141004_2_subscriber_short_9.mp4
    //$v_image = 'tna_screen_grab_640p.jpg';
    //$v_source = "$root_path/TNRA_20141004_2_subscriber_short.mp3";
    //$v_output = "$root_path/TNRA_20141004_2_subscriber_short_13.mp4";
    dev_log::write("start conversion");

    $ffmpeg_com = "ffmpeg -loop 1 -i $v_image -i $v_source -force_key_frames 00:00:00.000 -shortest -vcodec libx264 -crf 23 -preset medium -acodec copy $v_output";
    dev_log::write("ffmpeg_com = [$ffmpeg_com]");
    exec($ffmpeg_com, $output, $return_var);

    dev_log::write("ffmpeg_com: return_var=$return_var");
    if ($return_var) {
        dev_log::write("RETURN_VAR = " . print_r($output, true));
    }
    dev_log::write("end conversion");
    return $return_var;
}

function youtube_upload($v_output, $v_title, $v_desc, $v_keywords, $v_cat, $v_pstatus) {
    $cwd = getcwd();
    dev_log::write("CWD = $cwd");


    $args = array();

//$v_title = "Cow's milk is good for you!";
//$v_desc = "This is just a test. It's fun!";
    //$v_keywords = "test";
    //$v_cat = "22";
    //$v_pstatus = "private";

    dev_log::write("start upload");
    $args['file'] = escapeshellarg($v_output);
    $args['title'] = escapeshellarg($v_title);
    $args['description'] = escapeshellarg($v_desc);
    $args['keywords'] = escapeshellarg($v_keywords);
    $args['category'] = escapeshellarg($v_cat);
    $args['privacyStatus'] = escapeshellarg($v_pstatus);

    $py_comm = "python upload_video.py";
    $py_comm .= " --file {$args['file']}";
    $py_comm .= " --title {$args['title']}";
    $py_comm .= " --description {$args['description']}";
    $py_comm .= " --keywords {$args['keywords']}";
    $py_comm .= " --category {$args['category']}";
    $py_comm .= " --privacyStatus {$args['privacyStatus']}";
    $py_comm .= " --noauth_local_webserver";

//echo $py_comm . "\n";
    dev_log::write("py_comm = [$py_comm]");

    exec($py_comm, $output, $return_var);
    dev_log::write("py_comm: return_var=$return_var");
    if ($return_var) {
        dev_log::write("RETURN_VAR = " . print_r($output, true));
    }
    dev_log::write("end upload");
    return $return_var;
}

function upload_status($mysqli, $filename, $locked=0) {
    //$now = date("Y-m-d H:i:s");
    $sql = "SELECT * FROM tna_video_uploads WHERE filename = '$filename' ORDER BY id ASC";
    $result = mysqli_query($mysqli, $sql);

    //dev_log::write($sql);
    // dev_log::write("num_rows = $result->num_rows");

    if ($result->num_rows) {
        $result->data_seek(0);
        $row = $result->fetch_assoc();

        if ($row['locked'] == $locked) {
            return 1;
        }
    }

    return 0;
}

function post_upload($mysqli, $filename) {
    $now = date("Y-m-d H:i:s");

    $uploaded = upload_status($mysqli, $filename, 0);

    if ($uploaded) {
        $sql = "DELETE FROM tna_video_uploads WHERE filename = '$filename'";
        $result = mysqli_query($mysqli, $sql);
        do_error($mysqli, $sql, $result);
    }

    $sql = "INSERT INTO tna_video_uploads (filename, locked, created) VALUES ('$filename', '1', '$now')";
    $result = mysqli_query($mysqli, $sql);
    do_error($mysqli, $sql, $result);
}

function deactivate($mysqli, $entry_id = '') {
    if ($entry_id) {
        $sql = "UPDATE exp_channel_data SET $publish_to_youtube_name = 'no' WHERE entry_id = $entry_id";
        dev_log::write($sql);
        $result = mysqli_query($mysqli, $sql);
        do_error($mysqli, $sql, $result);
    } else {
        dev_log::write("CHANNEL ENTRY [$entry_id] DOES NOT EXIST!");
    }
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
