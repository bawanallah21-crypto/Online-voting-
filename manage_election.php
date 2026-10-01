<?php
session_start();

if(!isset($_SESSION['admin'])){
    header("Location: admin_login.php");
    exit();
}

require_once '../db.php';

if(isset($_POST['save'])){

$start = $_POST['start_time'];
$end = $_POST['end_time'];

$conn->query("DELETE FROM election_time");

$conn->query("
INSERT INTO election_time(
start_time,
end_time
)
VALUES(
'$start',
'$end'
)
");

echo "<script>alert('Election Time Updated');</script>";
}

$data = $conn->query("
SELECT *
FROM election_time
LIMIT 1
");

$row = $data->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
<title>Election Timer</title>

<style>

body{
    font-family:Arial;
    background:#f4f6f9;
    margin:0;
}

.header{
    background:#003366;
    color:white;
    text-align:center;
    padding:20px;
}

.container{
    width:60%;
    margin:30px auto;
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,.1);
}

input{
    width:100%;
    padding:12px;
    margin-bottom:15px;
}

button{
    background:#003366;
    color:white;
    border:none;
    padding:12px 20px;
    cursor:pointer;
    border-radius:5px;
}

.status{
    text-align:center;
    font-size:25px;
    margin-top:20px;
    font-weight:bold;
}

.open{
    color:green;
}

.closed{
    color:red;
}

</style>
</head>
<body>

<div class="header">
<h1>Election Timer Management</h1>
</div>

<div class="container">

<form method="POST">

<label>Election Start Time</label>

<input type="datetime-local"
name="start_time"
value="<?php echo isset($row['start_time']) ? date('Y-m-d\TH:i',strtotime($row['start_time'])) : ''; ?>"
required>

<label>Election End Time</label>

<input type="datetime-local"
name="end_time"
value="<?php echo isset($row['end_time']) ? date('Y-m-d\TH:i',strtotime($row['end_time'])) : ''; ?>"
required>

<button name="save">
Save Election Time
</button>

</form>

<?php

if(isset($row['end_time'])){

if(time() < strtotime($row['end_time'])){

echo "<div class='status open'>
🟢 ELECTION OPEN
</div>";

}else{

echo "<div class='status closed'>
🔴 ELECTION CLOSED
</div>";
}
}
?>

</div>

</body>
</html>