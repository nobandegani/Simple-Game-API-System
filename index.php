<?php
/* Copyright (c) 2021-2024 by Inoland */


require_once('vendor/autoload.php');

use Leaf\App;
use Leaf\Config;

require_once('configs.php');
require_once('data.php');
require_once('functions.php');

session_start();

if ( B_DEBUG ){
    Config::set("env", "development");
}

if (Config::get("env") === "development") {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

global $pdo;

$app = new App;

if (REDIS_ENABLE){
    global $redis;
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379); // Adjust the IP and port if needed

    $redis->auth(REDIS_PW);
    $redis->select(13);
}


$app->before('GET|POST', '/.*', function () use ($app) {
    // Get the client's IP address
    $ip = $_SERVER['REMOTE_ADDR'];

    // Check rate limit for this IP
    if (!checkRateLimit($ip)) {
        $app->response()->json(['error' => 'Rate limit exceeded. Try again later.'], 429);
        exit();
    }

    // Retrieve headers using getallheaders()
    $headers = getallheaders();

    // Validate API Key
    if (!isset($headers['X-Api-Key']) || $headers['X-Api-Key'] !== API_KEY) {
        $app->response()->json(['error' => 'Unauthorized: Invalid API key'], 401);
        exit();
    }

    // Basic Authentication
    if (!isset($headers['Authorization'])) {
        $app->response()->json(['error' => 'Unauthorized: Missing Authorization header'], 401);
        exit();
    }

    // Decode Basic Authentication credentials
    if (strpos($headers['Authorization'], 'Basic ') === 0) {
        $authCredentials = base64_decode(substr($headers['Authorization'], 6));
        list($basicUsername, $basicPassword) = explode(':', $authCredentials, 2);

        // Validate against hardcoded credentials
        if ($basicUsername !== API_UN || $basicPassword !== API_PW) {
            $app->response()->json(['error' => 'Unauthorized: Invalid credentials'], 401);
            exit();
        }
    } else {
        $app->response()->json(['error' => 'Unauthorized: Invalid Authorization header'], 401);
        exit();
    }
});

$app->get('/', function () use($app) {
    $app->response()->json([
        'message' => 'hello',
    ]);
});

$app->post('/players', function () use ($app) {
    $action = (int)$app->request()->get('action');
    $id = $app->request()->get('id');
    $pname = $app->request()->get('name');
    $region = (int)$app->request()->get('region');
    $status_lor = (int)$app->request()->get('status_lor');

    if (!isset($action) || !is_numeric($action)) {
        $app->response()->json([
            'error' => 'Invalid action',
            'action' => $action

        ], 400);
    }

    if (!isset($id)) {
        $app->response()->json(['error' => 'Invalid id'], 400);
    }

    if (isset($pname) && !is_string($pname)) {
        $app->response()->json(['error' => 'Invalid pname'], 400);
    }

    if (isset($region) && !is_numeric($region)) {
        $app->response()->json(['error' => 'Invalid region'], 400);
    }

    if (isset($status_lor) && !is_numeric($status_lor)) {
        $app->response()->json(['error' => 'Invalid status_lor'], 400);
    }

    if ($action == 1) {
        if (!is_string($id)) {
            $app->response()->json(['error' => 'Invalid pid'], 400);
        }
        addPlayer($app, $id, $pname, $region);
    } else if($action == 2){
        if (!is_numeric($id)) {
            $app->response()->json(['error' => 'Invalid id'], 400);
        }
        $player = getPlayer($app, $id, false, true);
        $app->response()->json([
            'status' => 'success',
            'player' => $player
        ]);
    } else if($action == 3){
        if (!is_numeric($id)) {
            $app->response()->json(['error' => 'Invalid id'], 400);
        }
        $player = getPlayer($app, $id, false, true);
        $app->response()->json([
            'status_lor' => $player['status_lor'],
            'id' => $player['id']
        ]);
    }else if($action == 4){
        if (!is_numeric($id)) {
            $app->response()->json(['error' => 'Invalid id'], 400);
        }
        if (!isset($status_lor) || !is_numeric($status_lor)) {
            $app->response()->json(['error' => 'Invalid status_lor'], 400);
        }

        $player = getPlayer($app, $id, false, true);

        if ($status_lor == $player['status_lor']){
            $app->response()->json([
                'status' => 'failed',
                'message' => 'same'
            ]);
            return;
        }
        if ($player['status_lor'] > 1 && false){
            $app->response()->json([
                'status' => 'failed',
                'message' => 'cant undo the lose'
            ]);
            return;
        }
        $updateinfo[] = [];
        $updateinfo['status_lor'] = $status_lor;
        $result = updatePlayer($app, $id, $updateinfo, true);
        $app->response()->json([
            'status' => $result,
            'old' => $player['status_lor'],
            'new' => $status_lor
        ]);
    } else {
        $app->response()->json(['error' => 'Invalid action'], 400);
    }
});

$app->post('/data_lor', function () use ($app) {
    $action = (int)$app->request()->get('action');
    $id = (int)$app->request()->get('id');

    if (!isset($action) || !is_numeric($action)) {
        $app->response()->json(['error' => 'Invalid action'], 400);
    }

    if (!isset($id) || !is_numeric($id)) {
        $app->response()->json(['error' => 'Invalid id'], 400);
    }

    if ($action == 1 || $action == 2){
        $row = getPlatform($app, $id);
        if (!$row){
            return;
        }
    }

    if ($action == 1) {
        $result = getStatusPlatform($app, $row, false);
    }else if($action == 2){
        $result = getStatusPlatform($app, $row, true);
    }else{
        $app->response()->json(['error' => 'Invalid action'], 400);
        return;
    }

    $app->response()->json($result);
});

$app->post('/action_lor', function () use ($app) {
    $action = (int)$app->request()->get('action');
    $id = (int)$app->request()->get('id');
    $puid = $app->request()->get('puid');
    $isr = (int)$app->request()->get('isr');

    if (!isset($action) || !is_numeric($action)) {
        $app->response()->json(['error' => 'Invalid action'], 400);
    }

    if (!isset($id) || !is_numeric($id)) {
        $app->response()->json(['error' => 'Invalid id'], 400);
    }

    if (!isset($puid) || !is_numeric($puid)) {
        $app->response()->json(['error' => 'Invalid puid'], 400);
    }

    if (!isset($isr) || !is_numeric($isr) || $isr < 0 || $isr > 1 ) {
        $app->response()->json(['error' => 'Invalid isr'], 400);
    }

    if ($action == 1){
        $row = getPlatform($app, $id);

        if (!$row){
            return;
        }
    }


    if ($action == 1) {
        $result = getStatusPlatform($app, $row, false);
        if ( $result['status'] == true){
            $app->response()->json([
                'status' => 'determined',
            ]);
        }else{
            $player = getPlayer($app, $puid, false, true);

            if (!IsStatusLorValid((int)$player['status_lor'])){
                $app->response()->json([
                    'status' => 'failed',
                    'message' => 'player status is ' . $player['status_lor']
                ]);
                return;
            }

            $correct = $row['isr'] == $isr? 1:0;
            $update_result = UpdatePlatform($app, $id, $puid, $correct);

            if ($update_result === 'right'){
                $app->response()->json([
                    'status' => 'right',
                ]);
            }else if ($update_result === 'wrong'){
                $update_info[]=[];
                $update_info['status_lor'] = 2;
                $update_player = updatePlayer($app, $puid, $update_info, true);
                if ($update_player === "updated"){
                    $app->response()->json([
                        'status' => 'wrong',
                        'message' => 'u lost'
                    ]);
                }
            }else{
                return;
            }
        }
    }else{
        $app->response()->json(['error' => 'Invalid action'], 400);
        return;
    }
});


$app->run();

?>


