<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

require_once '../db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection error. Please check db.php.");
}

$position_order = [
    'President',
    'Vice President',
    'Secretary General',
    'Treasurer',
    'Organizing Secretary',
    'Public Relations Officer (PRO)',
    'Public Relation Officer (PRO)',
    'Financial Secretary',
    'Auditor'
];

$position_result = $conn->query("SELECT DISTINCT position FROM candidates");

if (!$position_result) {
    die("Unable to load positions: " . $conn->error);
}

$positions = [];

while ($row = $position_result->fetch_assoc()) {
    $positions[] = $row['position'];
}

usort($positions, function($a, $b) use ($position_order) {
    $ai = array_search($a, $position_order, true);
    $bi = array_search($b, $position_order, true);

    if ($ai === false) $ai = 999;
    if ($bi === false) $bi = 999;

    return $ai <=> $bi;
});
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Election Results</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{box-sizing:border-box}
body{
    font-family:Arial,sans-serif;background:#f2f2f2;padding:20px;margin:0
}
.container{
    max-width:1100px;margin:auto;background:#fff;padding:25px;border-radius:12px
}
h1{
    text-align:center;color:#003366;margin:0;font-size:26px
}
.report-header{
    text-align:center;border-bottom:3px double #003366;padding:10px 10px 18px;margin-bottom:25px
}
.report-title{
    font-size:18px;font-weight:700;margin-top:8px;color:#444;letter-spacing:.5px
}
h2{
    color:#003366;border-bottom:2px solid #003366;padding-bottom:8px
}
table{
    width:100%;border-collapse:collapse;margin-bottom:30px
}
th,td{
    border:1px solid #ccc;padding:10px;text-align:center
}
th{
    background:#003366;color:#fff
}
.winner{
    background:#d4edda;font-weight:bold
}
.no-interest{
    background:#fff3cd
}
button{
    padding:12px 20px;background:#003366;color:#fff;border:0;cursor:pointer;border-radius:5px
}


@media(max-width:600px){
    th,td{font-size:13px;padding:7px
}}
@media print{
    body{
        background:#fff;padding:0
    }
    .container{
        box-shadow:none;border-radius:0;max-width:none
    }
    button{
        display:none
    }
    img,.logo,.site-logo,.print-logo,.brand-logo{display:none!important
}
}
</style>
</head>

<body>
<div class="container">

<div class="report-header">
    <h1>PLATEAU STATE TRADERS AND MARKETERS ASSOCIATION</h1>
    <div class="report-title">OFFICIAL ONLINE ELECTION RESULTS</div>
</div>

<?php foreach ($positions as $position): ?>

<?php
$stmt = $conn->prepare("
    SELECT c.candidate_id, c.name, COUNT(v.vote_id) AS total_votes
    FROM candidates c
    LEFT JOIN votes v
        ON c.candidate_id = v.candidate_id
       AND v.position = ?
    WHERE c.position = ?
      AND c.status = 'Active'
    GROUP BY c.candidate_id, c.name
    ORDER BY total_votes DESC, c.candidate_id ASC
");

if (!$stmt) {
    die("Results query error: " . $conn->error);
}

$stmt->bind_param("ss", $position, $position);
$stmt->execute();
$result = $stmt->get_result();

$all = [];
$highest = 0;

while ($row = $result->fetch_assoc()) {
    $row['total_votes'] = (int)$row['total_votes'];
    $all[] = $row;

    if ($row['total_votes'] > $highest) {
        $highest = $row['total_votes'];
    }
}

$stmt->close();

$null_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM votes
    WHERE position = ?
      AND candidate_id IS NULL
");

$invalid_votes = 0;

if ($null_stmt) {
    $null_stmt->bind_param("s", $position);
    $null_stmt->execute();
    $null_result = $null_stmt->get_result();
    $null_row = $null_result->fetch_assoc();
    $invalid_votes = (int)$null_row['total'];
    $null_stmt->close();
}
?>

<h2><?php echo htmlspecialchars($position, ENT_QUOTES, 'UTF-8'); ?></h2>

<table>
<tr>
    <th>S/N</th>
    <th>Candidate</th>
    <th>Total Votes</th>
    <th>Status</th>
</tr>

<?php $sn = 1; ?>

<?php foreach ($all as $row): ?>
<?php $winner = ($highest > 0 && $row['total_votes'] === $highest); ?>

<tr class="<?php echo $winner ? 'winner' : ''; ?>">
    <td><?php echo $sn++; ?></td>
    <td><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo $row['total_votes']; ?></td>
    <td><?php echo $winner ? 'WINNER' : ''; ?></td>
</tr>

<?php endforeach; ?>

<tr class="no-interest">
    <td colspan="2"><strong>No Interest</strong></td>
    <td><?php echo $invalid_votes; ?></td>
    <td></td>
</tr>

</table>

<?php endforeach; ?>


</div>
</body>
</html>
