<?php
    header('Content-Type: application/json');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    require_once("../config/config.php");

    $servername = $config['api']['host'];
    $username =   $config['api']['databaseUser'];
    $password =   $config['api']['databasePass'];
    $dbname =     $config['api']['database'];

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    $retObj = new stdClass();

    $recordQuery = "SELECT MAX(record) as r FROM server_record";
    $recordPlayers = $conn->query($recordQuery);
    if ($recordPlayers) {
        $rowPlayers = $recordPlayers->fetch_assoc();
        $retObj->onlineRecord = $rowPlayers['r'];
    } else {
        $retObj->onlineRecord = 0;
    }
    
    if($config['status']['serverStatus_online'] == 1) {
        $retObj->status = "Online";
    } else {
        $retObj->status = "Offline";
    }

    $playersQuery = "SELECT * FROM players WHERE online > 0 ";
    $onlinePlayers = $conn->query($playersQuery);
    if ($onlinePlayers) {
        $retObj->onlinePlayers = $onlinePlayers->num_rows;
    } else {
        $retObj->onlinePlayers = 0;
    }

    echo json_encode($retObj);
?>