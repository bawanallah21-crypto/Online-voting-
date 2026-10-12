<?php

$servername = "sql308.infinityfree.com";
$username = "ifo_43103094";
$password = "8DHbQ3F0pyI08sF";
$database = "IFO_43103094_XXX";

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
