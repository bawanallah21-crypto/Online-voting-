<?php
session_start();
require_once '../db.php';

if(isset($_POST['login'])){

    $username = $_POST['username'];
    $password = $_POST['password'];

    if(
        ($username == "adminpstama111" && $password == "admin/001/111")
        ||
        ($username == "adminpstama222" && $password == "admin/002/222")
    ){

        $_SESSION['admin'] = $username;

        header("Location: admin_dashboard.php");
        exit();

    }else{

        echo "
        <script>
        alert('Invalid Admin Login Details');
        </script>
        ";
    }
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Admin Login</title>

<style>

body{
    margin:0;
    font-family:Arial, sans-serif;
    background:#f4f6f9;
}

.header{
    background:#003366;
    color:white;
    text-align:center;
    padding:20px;
}

.login-box{
    width:450px;
    margin:60px auto;
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0px 2px 15px rgba(0,0,0,0.2);
}

.logo{
    text-align:center;
    font-size:60px;
}

h2{
    text-align:center;
    color:#003366;
}

input{
    width:100%;
    padding:12px;
    margin-top:8px;
    margin-bottom:20px;
    border:1px solid #ccc;
    border-radius:5px;
    box-sizing:border-box;
}

button{
    width:100%;
    padding:12px;
    background:#003366;
    color:white;
    border:none;
    border-radius:5px;
    cursor:pointer;
    font-size:16px;
}

button:hover{
    background:#002244;
}

.notice{
    background:#fff3cd;
    border-left:4px solid #ffc107;
    padding:10px;
    margin-bottom:20px;
}

.back{
    display:block;
    text-align:center;
    margin-top:15px;
    text-decoration:none;
    color:#003366;
    font-weight:bold;
}

</style>

</head>

<body>

<div class="header">

<h1>
PLATEAU STATE TRADERS AND MARKETERS ASSOCIATION
</h1>

<h3>
ADMIN CONTROL PANEL
</h3>

</div>

<div class="login-box">

<div class="logo">
🔐
</div>

<h2>Administrator Login</h2>

<div class="notice">

<strong>Authorized Personnel Only</strong>

<br>

Access to election management and result reports.

</div>

<form method="POST">

<label>Username</label>

<input
type="text"
name="username"
required>

<label>Password</label>

<input
type="password"
name="password"
required>

<button
type="submit"
name="login">

Login to Dashboard

</button>

</form>

<a href="online_voting/index.php" class="home-btn">
← Back to Home Page
</a>

</div>

</body>
</html>