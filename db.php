<?php

$servername = "pstama.ifree.page";
$username = "ifo_43103094";
$password = "80HbQ3F0pyI08sF";
$database = "online_voting";

$conn = new mysqli(
    $servername,
    $username,
    $password,
    $database
);

if($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
}

?>
