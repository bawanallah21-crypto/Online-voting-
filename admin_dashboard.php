<?php
session_start();

if(!isset($_SESSION['admin'])){
    header("Location: admin_login.php");
    exit();
}

require_once '../db.php';

$totalVoters = $conn->query("
SELECT COUNT(*) AS total
FROM voters
")->fetch_assoc()['total'];

$totalVotesCast = $conn->query("
SELECT COUNT(DISTINCT voter_id) AS total
FROM votes
")->fetch_assoc()['total'];

$turnout = 0;

if($totalVoters > 0){
    $turnout = round(($totalVotesCast/$totalVoters)*100);
}

$votesRemaining = $totalVoters - $totalVotesCast;

$status = "OPEN";

if($conn->query("SHOW TABLES LIKE 'election_time'")->num_rows > 0){

    $checkTime = $conn->query("
    SELECT * FROM election_time
    LIMIT 1
    ");

    if($checkTime->num_rows > 0){

        $time = $checkTime->fetch_assoc();

        $end_time = strtotime($time['end_time']);

        if(time() > $end_time){
            $status = "CLOSED";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Admin Dashboard</title>

<style>

body{
    margin:0;
    font-family:Arial,sans-serif;
    background:#f4f6f9;
}

.header{
    background:#003366;
    color:white;
    padding:30px;
    text-align:center;
}

.header h1{
    margin:0;
}

.topbar{
    background:white;
    padding:15px;
    text-align:right;
    box-shadow:0 2px 5px rgba(0,0,0,0.1);
}

.logout{
    background:red;
    color:white;
    padding:10px 20px;
    text-decoration:none;
    border-radius:5px;
}

.container{
    width:95%;
    margin:auto;
    padding:20px;
}

.stats{
    display:flex;
    flex-wrap:wrap;
    gap:20px;
}

.card{
    flex:1;
    min-width:220px;
    background:white;
    padding:25px;
    border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
    text-align:center;
}

.card h1{
    color:#003366;
    margin:0;
}

.card p{
    font-size:18px;
}

.section-title{
    margin-top:40px;
    color:#003366;
}

.buttons{
    display:flex;
    flex-wrap:wrap;
    gap:20px;
    margin-top:20px;
}

.btn{
    width:220px;
    height:90px;
    background:#003366;
    color:white;
    text-decoration:none;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    font-weight:bold;
    transition:0.3s;
}

.btn:hover{
    background:#0055aa;
}

.status-open{
    color:green;
    font-weight:bold;
    font-size:30px;
}

.status-close{
    color:red;
    font-weight:bold;
    font-size:30px;
}

footer{
    margin-top:50px;
    background:white;
    padding:20px;
    text-align:center;
}

</style>

</head>

<body>

<div class="header">

<h1>
PLATEAU STATE TRADERS AND MARKETERS ASSOCIATION
</h1>

<h2>
ONLINE VOTING SYSTEM - ADMIN DASHBOARD
</h2>

</div>

<div class="topbar">

Welcome:
<b><?php echo $_SESSION['admin']; ?></b>

&nbsp;&nbsp;

<a href="admin_logout.php" class="logout">
Logout
</a>

</div>


<div class="container">

<div class="stats">



<div class="card">
<h1><?php echo $totalVoters; ?></h1>
<p>Total Registered Voters</p>
</div>

<div class="card">
<h1><?php echo $totalVotesCast; ?></h1>
<p>Total Votes Casted</p>
</div>

<div class="card">
<h1><?php echo $turnout; ?>%</h1>
<p>Voter Turnout</p>
</div>

<div class="card">
<h1><?php echo $votesRemaining; ?></h1>
<p>Votes Remaining</p>
</div>
<div class="card">

<p>Total Votes Casted</p>
</div>
</div>

<h2 class="section-title">
Election Status
</h2>

<div class="card">

<?php
if($status=="OPEN"){
    echo "<div class='status-open'>OPEN</div>";
}else{
    echo "<div class='status-close'>CLOSED</div>";
}
?>

</div>

<h2 class="section-title">
Election Management
</h2>

<div class="buttons">
    <a href="election_settings.php" class="btn">
🗳 Election Settings
</a>


<a href="admin_results.php" class="btn">
📊 View Election Results
</a>

<a href="print_results.php" class="btn">
🖨 Print Results Report
</a>

<a href="manage_candidates.php" class="btn">
👥 Manage Candidates
</a>

<a href="manage_voters.php" class="btn">
🧑 Manage Voters
</a>

<a href="manual_vote.php" class="btn">
🗳️ Manual Vote / Record Vote
</a>

<a href="manage_election.php" class="btn">
⏰ Election Timer
</a>

</div>

</div>

<footer>

Plateau State Traders and Marketers Association<br>

Online Voting System © <?php echo date('Y'); ?>

</footer>

</body>
</html>