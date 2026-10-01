<?php
session_start();
include 'db.php';

if(isset($_POST['login'])){

    $membership_id = trim($_POST['membership_id']);
    $password = trim($_POST['password']);

    $sql = "SELECT * FROM voters
            WHERE membership_id='$membership_id'
            AND password='$password'";

    $result = $conn->query($sql);

    if($result->num_rows > 0){

        $voter = $result->fetch_assoc();

        if($voter['has_voted'] == 1){

            echo "<script>
            alert('You have already voted.');
            </script>";

        }else{

            $_SESSION['membership_id'] = $membership_id;

header("Location: countdown.php");
exit();
        }

    }else{

        echo "<script>
        alert('Invalid Membership ID or Password');
        </script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Voter Login</title>

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

.login-container{
    width:420px;
    margin:50px auto;
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0px 2px 15px rgba(0,0,0,0.2);
}

.logo{
    text-align:center;
    font-size:60px;
    margin-bottom:10px;
}

h2{
    text-align:center;
    color:#003366;
}

.subtitle{
    text-align:center;
    color:#666;
    margin-bottom:25px;
}

label{
    font-weight:bold;
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

.login-btn{
    width:100%;
    padding:12px;
    background:#003366;
    color:white;
    border:none;
    border-radius:5px;
    font-size:16px;
    cursor:pointer;
}

.login-btn:hover{
    background:#002244;
}

.home-btn{
    display:block;
    text-align:center;
    margin-top:15px;
    text-decoration:none;
    color:#003366;
    font-weight:bold;
}

.footer{
    text-align:center;
    margin-top:20px;
    color:#666;
    font-size:14px;
}

.notice{
    background:#eaf4ff;
    border-left:4px solid #003366;
    padding:10px;
    margin-bottom:20px;
    font-size:14px;
}

</style>

</head>

<body>

<div class="header">

<h1>
PLATEAU STATE TRADERS AND MARKETERS ASSOCIATION
</h1>

<h3>
ONLINE VOTING SYSTEM
</h3>

</div>

<div class="login-container">

<div class="logo">
🗳️
</div>

<h2>Voter Login</h2>

<p class="subtitle">
Enter your Membership ID and Password
</p>

<div class="notice">
<strong>Important:</strong><br>
Each registered member can vote only once.
You have 10 minutes to complete your voting session.
</div>

<form method="POST">

<label>Membership ID</label>

<input
type="text"
name="membership_id"
placeholder="Enter Membership ID"
required>

<label>Password</label>

<input
type="password"
name="password"
placeholder="Enter Password"
required>

<button
type="submit"
name="login"
class="login-btn">

Login to Vote

</button>

</form>

<a href="index.php" class="home-btn">
← Back to Home Page
</a>

<div class="footer">

PSTAMA Election Portal<br>

© <?php echo date("Y"); ?>

</div>

</div>

</body>
</html>