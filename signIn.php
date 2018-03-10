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

// check username
if (empty($_POST['username'])) {
    $json(['error' => 'Bad Request'], 400);
}

// connect to db
R::setup($config['dsn']);
R::freeze(false);

// look up user
$user = R::findOne('user', 'username = ?', [$_POST['username']]);
// email lock against username
if (!empty($user->email) && empty($_POST['username'])) {
    $json(['error' => 'Unauthorized'], 401);
} elseif (!empty($user->email) && $_POST['username'] != $user->email) {
    $json(['error' => 'Unauthorized'], 401);
}

// has account
if (!empty($user->id)) {
    $json(['token' => $user->token], 200);
}

// new account
$user = R::dispense('user');
$user->username = $_POST['username'];
$user->email = isset($_POST['email']) ? $_POST['email'] : '';
$user->token = hash_hmac('sha256', $user->username, $user->email);
R::store($user);

$json(['token' => $user->token], 200);
