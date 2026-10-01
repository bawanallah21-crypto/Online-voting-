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
<title>Manage Candidates</title>

<style>

body{
    font-family:Arial;
    background:#f4f6f9;
    padding:20px;
}

.container{
    width:95%;
    margin:auto;
    background:white;
    padding:20px;
    border-radius:10px;
}

h2{
    color:#003366;
}

input,select{
    width:100%;
    padding:10px;
    margin:5px 0;
}

button{
    background:#003366;
    color:white;
    border:none;
    padding:10px 20px;
    cursor:pointer;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

table,th,td{
    border:1px solid #ccc;
}

th{
    background:#003366;
    color:white;
}

th,td{
    padding:10px;
    text-align:center;
}

.delete{
    background:red;
    color:white;
    padding:5px 10px;
    text-decoration:none;
    border-radius:5px;
}

</style>
</head>
<body>

<div class="container">

<h2>Add New Candidate</h2>

<form action="add_candidate.php" method="POST">

<input type="text"
name="name"
placeholder="Candidate Name"
required>

<select name="position" required>

<option value="">Select Position</option>

<option>President</option>
<option>Vice President</option>
<option>Secretary General</option>
<option>Treasurer</option>
<option>Organizing Secretary</option>
<option>Public Relation Officer (PRO)</option>
<option>Financial Secretary</option>
<option>Auditor</option>

</select>

<button type="submit">
Add Candidate
</button>

</form>

<hr>

<h2>All Candidates</h2>

<table>

<tr>
<th>ID</th>
<th>Name</th>
<th>Position</th>
<th>Action</th>
</tr>

<?php

$result = $conn->query("
SELECT *
FROM candidates
ORDER BY position,name
");

while($row = $result->fetch_assoc()){

echo "
<tr>

<td>{$row['candidate_id']}</td>

<td>{$row['name']}</td>

<td>{$row['position']}</td>

<td>

<a class='delete'
href='delete_candidate.php?id={$row['candidate_id']}'
onclick='return confirm(\"Delete Candidate?\")'>
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