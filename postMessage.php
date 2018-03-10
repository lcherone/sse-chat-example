<?php
use RedBeanPHP\R;

require_once('vendor/autoload.php');

// load config
$config = include('config.php');

// json response helper
$json = function ($data = [], $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json;charset=utf8');
    exit(json_encode($data, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK));
};

// check ajax
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    $json(['error' => 'Bad Request'], 400);
}

// check post
$_POST = json_decode(file_get_contents('php://input'), true);
//
if (empty($_POST)) {
    $json(['error' => 'Bad Request'], 400);
}

// check token
if (empty($_POST['token'])) {
    $json(['error' => 'Unauthorized'], 401);
}

// connect to db
R::setup($config['dsn']);
R::freeze(false);

// look up user token
$user = R::findOne('user', 'token = ?', [$_POST['token']]);
//
if (empty($user->id)) {
    $json(['error' => 'Unauthorized'], 401);
}

// check message
if (empty($_POST['message'])) {
    $json(['error' => 'Bad Request'], 400);
}

//
$message = R::dispense('message');

$message->user = $_POST['token'];
$message->date = time();
$message->username = $user->username;
$message->email = $user->email;
$message->body = $_POST['message'];
R::store($message);

$json(true);