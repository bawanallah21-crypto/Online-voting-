<?php
include 'db.php';

$date = date("d-m-Y h:i A");

$membership_id = $_GET['membership_id'];

$voter = $conn->query("
SELECT * FROM voters
WHERE membership_id='$membership_id'
");

$data = $voter->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
<title>Voting Confirmation Slip</title>

<style>

body{
    font-family:Arial;
    margin:40px;
    background:#f4f6f9;
}

.container{
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0px 2px 8px rgba(0,0,0,0.2);
}

h1,h2{
    text-align:center;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

table, th, td{
    border:1px solid black;
}

th, td{
    padding:12px;
    text-align:left;
}

.print-btn{
    margin-top:20px;
    padding:12px 20px;
    background:#003366;
    color:white;
    border:none;
    cursor:pointer;
    border-radius:5px;
}

.signature{
    margin-top:80px;
}

@media print{

    .print-btn{
        display:none;
    }
}

</style>

</head>

<body>

<div class="container">

<h1>
PLATEAU STATE TRADERS AND MARKETERS ASSOCIATION
</h1>

<h2>
VOTING CONFIRMATION REPORT
</h2>

<p>
<strong>Date Generated:</strong>
<?php echo $date; ?>
</p>

<table>

<tr>
<th>Full Name</th>
<td><?php echo $data['name']; ?></td>
</tr>

<tr>
<th>Membership ID</th>
<td><?php echo $data['membership_id']; ?></td>
</tr>

<tr>
<th>Voting Status</th>
<td>Vote Successfully Casted</td>
</tr>

<tr>
<th>Election Session</th>
<td>2026 General Election</td>
</tr>

</table>

<div style="margin-top:60px;">
<br>
<img src="sign.png" width="250px" height="200px">
</img>
<br>
_________________________    <br>
Electoral Officer Signature


</div>

<button class="print-btn"
onclick="window.print()">

Print Confirmation Slip

</button>

</div>

</body>
</html>