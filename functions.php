<?php
/* Copyright (c) 2021-2024 by Inoland */

function initPDO(){
    global $pdo;
    if ($pdo){
        return $pdo;
    }
    try {
        $pdo = new PDO(DB_CONFIG, DB_UN, DB_PW);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}


function checkRateLimit($ip) {
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }

    $currentTime = time();

    // Initialize the IP entry if it doesn't exist
    if (!isset($_SESSION['rate_limit'][$ip])) {
        $_SESSION['rate_limit'][$ip] = [];
    }

    // Clean up old requests
    $_SESSION['rate_limit'][$ip] = array_filter($_SESSION['rate_limit'][$ip], function($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) <= RATE_LIMIT_TIME_WINDOW;
    });

    // Check if the IP has exceeded the rate limit
    if (count($_SESSION['rate_limit'][$ip]) >= RATE_LIMIT) {
        return false;
    }

    // Log the new request timestamp
    $_SESSION['rate_limit'][$ip][] = $currentTime;

    return true;
}

/**
 * Use Built-in Redis System
 *
 *
 * @param int $action 0=exists, 1=get, 2=set, 3=expire, 4=del.
 * @return bool if its successful or not
 */
function useRedis($action = 0, $cacheKey = '', $cacheContent = '', $bJsonEncode = true){
    global $redis;

    if (!REDIS_ENABLE) {
        return false;
    }

    if ($action == 0) {
        return $redis->exists($cacheKey);
    }else if($action == 1) {
        $temp = $redis->get($cacheKey);
        return $bJsonEncode? json_decode($temp, true):$temp;
    }else if($action == 2) {
        return $redis->set($cacheKey, $bJsonEncode? json_encode($cacheContent): $cacheContent);
    }else if($action == 3) {
        return $redis->expire($cacheKey, REDIS_TTL);
    }else if($action == 4){
        return $redis->del($cacheKey);
    }else{
        return false;
    }
}
function IsStatusLorValid ($status_lor){
    if ($status_lor === 1){
        return true;
    }
    return false;
}
function addPlayer($app, $pid, $pname, $region) {
    if (!isset($pname) || !is_string($pname)) {
        return $app->response()->json(['error' => 'Invalid pname'], 400);
    }

    if (!isset($region) || !is_numeric($region)) {
        return $app->response()->json(['error' => 'Invalid region'], 400);
    }
    $player = getPlayer($app, $pid, true, false);
    if ($player) {
        return $app->response()->json([
            'status' => 'exists',
            'id' => (int)$player['id'],
            'player' => $player
        ]);
    }else{
        $pdo = initPDO();
        try {
            $stmt = $pdo->prepare("INSERT INTO players (pid, pname, region) VALUES (:pid, :pname, :region)");
            $stmt->execute([
                ':pid' => $pid,
                ':pname' => $pname,
                ':region' => $region
            ]);

            $newPlayerId = $pdo->lastInsertId();
            return $app->response()->json([
                'status' => 'added',
                'id' => (int)$newPlayerId
            ]);

        } catch (PDOException $e) {
            return $app->response()->json(['error' => 'Failed to add player: ' . $e->getMessage()], 500);
        }
    }
}
function getPlayer($app, $id, $bUsePID, $bReturnFail) {
    $cacheKey = "players:id:$id";

    if ( useRedis(0, $cacheKey) && !$bUsePID) {
        return useRedis(1, $cacheKey);
    }else{
        $pdo = initPDO();
        try {
            if ($bUsePID){
                $stmt = $pdo->prepare("SELECT * FROM players WHERE pid = :id LIMIT 1");
            }else{
                $stmt = $pdo->prepare("SELECT * FROM players WHERE id = :id LIMIT 1");
            }
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                if ($bUsePID){
                    $cacheKey = "players:id:" . $row['id'];
                }
                useRedis(2, $cacheKey, $row);
                useRedis(3, $cacheKey);
                return $row;
            } else {
                if ($bReturnFail){
                    $app->response()->json(['error' => 'Row not found'], 401);
                }
                return '';
            }
        } catch (PDOException $e) {
            $app->response()->json(['error' => 'Failed to fetch row: ' . $e->getMessage()], 500);
            return '';
        }
    }
}
function updatePlayer($app, $id, $updateInfo, $bReturnFail) {
    //its only status_lor right now,
    //because we dont need to update anything else
    //we dont need to update player name, cuz we can get it by steam or epic id
    //only need to add other status for other levels
    $bUpdateStatus_Lor = false;

    if ( isset($updateInfo['status_lor'] ) ){
        $bUpdateStatus_Lor = true;
    }

    if( !$bUpdateStatus_Lor){
        if ($bReturnFail){
            $app->response()->json(['error' => 'no updating field is set'], 401);
        }
        return '';
    }

    if ($bUpdateStatus_Lor){
        $status_lor_sql = 'status_lor = :status_lor';
    }

    $pdo = initPDO();
    try {
        $stmt = $pdo->prepare("UPDATE players SET status_lor = :status_lor WHERE id = :id");
        $stmt->execute([
            ':status_lor' => $updateInfo['status_lor'],
            ':id' => $id
        ]);

        if ($stmt->rowCount() > 0) {
            $cacheKey = "players:id:$id";
            useRedis(4,$cacheKey);

            return 'updated';
        } else {
            if ($bReturnFail) {
                $app->response()->json(['error' => 'No rows were updated'], 500);
            }
            return '';
        }
    } catch (PDOException $e) {
        if ($bReturnFail) {
            $app->response()->json(['error' => 'Failed to update platform: ' . $e->getMessage()], 500);
        }
        return '';
    }
}
function getPlatform($app, $id) {
    $cacheKey = "data_lor:id:$id";

    if (useRedis(0,$cacheKey)) {
        return useRedis(1,$cacheKey);
    }else{
        $pdo = initPDO();
        try {
            $stmt = $pdo->prepare("SELECT * FROM data_lor WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                useRedis(2,$cacheKey, $row);
                useRedis(3, $cacheKey);
                return $row;
            } else {
                $app->response()->json(['error' => 'Row not found'], 401);
                return '';
            }
        } catch (PDOException $e) {
            $app->response()->json(['error' => 'Failed to fetch row: ' . $e->getMessage()], 500);
            return '';
        }
    }
}
function getStatusPlatform($app, $row, $bPlayer){
    if ($row['puid']) {
        if ($bPlayer){
            $player = getPlayer($app, $row['puid'], false, true);

            $result = [
                'status' => true,
                'isr'   => $row['isr'],
                'player' => $player,
                'correct' => $row['correct'],
                'datetime' => $row['datetime'],
            ];
        }else{
            $result = [
                'status' => true,
                'isr'   => $row['isr'],
                'correct' => $row['correct'],
                'datetime' => $row['datetime'],
            ];
        }

    } else {
        $result = [
            'status' => false
        ];
    }
    return $result;
}
function UpdatePlatform($app, $id, $puid, $correct){
    $pdo = initPDO();
    try {
        $stmt = $pdo->prepare("UPDATE data_lor SET puid = :puid, datetime = NOW(), correct = :correct WHERE id = :id");
        $stmt->execute([
            ':puid' => $puid,
            ':correct' => $correct,
            ':id' => $id
        ]);

        if ($stmt->rowCount() > 0) {
            $cacheKey = "data_lor:id:$id";
            useRedis(4, $cacheKey);

            return $correct? 'right': 'wrong';
        } else {
            $app->response()->json(['error' => 'No rows were updated'], 500);
            return '';
        }
    } catch (PDOException $e) {
        $app->response()->json(['error' => 'Failed to update platform: ' . $e->getMessage()], 500);
        return '';
    }
}

