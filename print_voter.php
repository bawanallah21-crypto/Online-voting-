<?php
include 'db.php';

$membership_id = $_GET['membership_id'];

$voter = $conn->query("SELECT * FROM voters
WHERE membership_id='$membership_id'");

$data = $voter->fetch_assoc();

echo "<h2>Voting Confirmation Slip</h2>";

echo "<p><strong>Name:</strong> ".$data['name']."</p>";

echo "<p><strong>Membership ID:</strong> ".$data['membership_id']."</p>";

echo "<p>Status: Vote Successfully Cast</p>";

echo "<button onclick='window.print()'>Print Report</button>";
?>