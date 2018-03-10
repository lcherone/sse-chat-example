<?php
use RedBeanPHP\R;

require_once('vendor/autoload.php');

// load config
$config = include('config.php');

// make session read-only
session_start();
session_write_close();

R::setup($config['dsn']);
R::freeze(false);

// no normal requests
if ($_SERVER['HTTP_ACCEPT'] !== 'text/event-stream') {
    exit();
}

// disable default disconnect checks
ignore_user_abort(true);

// set headers for stream
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Access-Control-Allow-Origin: *");

// a new stream or an existing one
$lastEventId = intval(isset($_SERVER["HTTP_LAST_EVENT_ID"]) ? $_SERVER["HTTP_LAST_EVENT_ID"] : 0);

if ($lastEventId === 0) {
    // resume from a previous event
    $lastEventId = floatval(isset($_GET["lastEventId"]) ? $_GET["lastEventId"] : 0);
}

echo ":".str_repeat(" ", 2048)."\n";
echo "retry: 3000\n";

// start stream
$ping = 0;
$ticks = 0;
while (true) {

    // user disconnected, kill process
    if (connection_aborted()) {
        exit();
    } else {

        // check if got new rows
        $latestEventId  = R::getCell('SELECT id FROM message WHERE id > ? LIMIT 1', [$lastEventId]);

        //
        if (!empty($latestEventId) && $lastEventId < $latestEventId) {

            // fetch new data
            $data = [];
            foreach ((array) R::find('message', 'id >= ?', [$latestEventId]) as $row) {
                $data[] = [
                    'username' => $row->username,
                    'gravatar' => gravatar($row->email),
                    'body' => $row->body
                ];

                $lastEventId = $row->id;
            }

            echo "id: " . $lastEventId . "\n";
            echo "event: message\n";
            echo "data: ".json_encode($data)."\n\n";
        } else {
            //  such that every 10th second ping
            if ($ping % 10 == 0) {
                $ping = 0;
                echo "event: ping\n\n";
            }
            $ping++;
        }
    }

    // flush buffer
    ob_flush();
    flush();

    // sleep for a sec
    sleep($config['loop_sleep']);

    // kill after 6 hours
    if ($ticks > $config['kill_after']) {
        exit();
    }
    $ticks++;
}

/**
 * 
 */
function gravatar($email = '') {
    $gravurl = '//www.gravatar.com/avatar/'.md5(strtolower(trim($email))).'?s=60&d=identicon&r=pg';
    return '<img src="'.$gravurl.'" width="60" height="60" border="0" class="img-responsive img-circle" alt="">';
}