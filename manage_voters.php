<?php
session_start();

if(!isset($_SESSION['admin'])){
    header("Location: admin_login.php");
    exit();
}

require_once '../db.php';
?>

<!DOCTYPE html>
<html>
<head>
<title>Manage Voters</title>

<style>

body{
    font-family:Arial;
    background:#f4f6f9;
    margin:0;
}

.header{
    background:#003366;
    color:white;
    padding:20px;
    text-align:center;
}

.container{
    width:95%;
    margin:20px auto;
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,.1);
}

input{
    width:100%;
    padding:10px;
    margin-bottom:10px;
}

button{
    background:#003366;
    color:white;
    border:none;
    padding:10px 20px;
    cursor:pointer;
    border-radius:5px;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

th{
    background:#003366;
    color:white;
}

th,td{
    border:1px solid #ddd;
    padding:10px;
    text-align:center;
}

.delete{
    background:red;
    color:white;
    padding:6px 10px;
    text-decoration:none;
    border-radius:5px;
}

.status-voted{
    color:green;
    font-weight:bold;
}

.status-not{
    color:red;
    font-weight:bold;
}

</style>
</head>
<body>

<div class="header">
<h1>Manage Voters</h1>
</div>

<div class="container">

<h2>Add New Voter</h2>

<form action="add_voter.php" method="POST">

<input type="text"
name="membership_id"
placeholder="Membership ID"
required>

<input type="text"
name="fullname"
placeholder="Full Name"
required>

<button type="submit">
Register Voter
</button>

</form>

<hr>

<h2>Registered Voters</h2>

<table>

<tr>
<th>ID</th>
<th>Membership ID</th>
<th>Name</th>
<th>Voting Status</th>
<th>Action</th>
</tr>

<?php

$result = $conn->query("
SELECT *
FROM voters
ORDER BY voter_id DESC
");

while($row = $result->fetch_assoc()){

$status = ($row['has_voted']==1)
? "<span class='status-voted'>CASTED</span>"
: "<span class='status-not'>NOT CASTED</span>";

echo "
<tr>

<td>{$row['voter_id']}</td>

<td>{$row['membership_id']}</td>

<td>{$row['name']}</td>

<td>$status</td>

<td>
<a class='delete'
href='delete_voter.php?id={$row['voter_id']}'
onclick='return confirm(\"Delete voter?\")'>
Delete
</a>
</td>

</tr>
";
}
?>

</table>

</div>

</body>
</html>