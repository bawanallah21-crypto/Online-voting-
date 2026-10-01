<?php
require_once 'db.php';

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

/* Get actual positions from candidates table */
$position_result = $conn->query("
    SELECT DISTINCT position
    FROM candidates
");

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
    <meta http-equiv="refresh" content="10">

    <style>
        *{box-sizing:border-box}
        body{
            font-family:Arial,sans-serif;
            background:#f4f6f9;
            margin:0;
            padding:20px;
        }
        h1{
            text-align:center;
            color:#003366;
            margin-bottom:5px;
        }
        .subtitle{
            text-align:center;
            color:#555;
            margin-bottom:25px;
        }
        .position-box{
            max-width:1000px;
            margin:0 auto 25px;
            background:#fff;
            padding:20px;
            border-radius:12px;
            box-shadow:0 2px 10px rgba(0,0,0,.12);
        }
        h2{
            color:#003366;
            border-bottom:2px solid #003366;
            padding-bottom:8px;
        }
        table{
            width:100%;
            border-collapse:collapse;
        }
        th,td{
            border:1px solid #ccc;
            padding:12px;
            text-align:center;
        }
        th{
            background:#003366;
            color:#fff;
        }
        .winner{
            background:#d4edda;
            font-weight:bold;
        }
        .no-interest{
            background:#fff3cd;
        }
        .footer{
            text-align:center;
            color:#777;
            margin:25px;
        }
        @media(max-width:600px){
            body{padding:10px}
            h1{font-size:22px}
            h2{font-size:19px}
            th,td{padding:8px;font-size:14px}
        }
    </style>
</head>
<body>

<h1>PLATEAU STATE TRADERS AND MARKETERS ASSOCIATION</h1>
<div class="subtitle">ONLINE ELECTION RESULTS</div>

<?php foreach ($positions as $position): ?>

<?php
$sql = "
    SELECT
        c.candidate_id,
        c.name,
        COUNT(v.vote_id) AS total_votes
    FROM candidates c
    LEFT JOIN votes v
        ON c.candidate_id = v.candidate_id
       AND v.position = ?
    WHERE c.position = ?
      AND c.status = 'Active'
    GROUP BY c.candidate_id, c.name
    ORDER BY total_votes DESC, c.candidate_id ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Results query error: " . $conn->error);
}

$stmt->bind_param("ss", $position, $position);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
$highest = 0;

while ($row = $result->fetch_assoc()) {
    $row['total_votes'] = (int)$row['total_votes'];
    $data[] = $row;

    if ($row['total_votes'] > $highest) {
        $highest = $row['total_votes'];
    }
}

$stmt->close();

/* Count No Interest votes for this position */
$null_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM votes
    WHERE position = ?
      AND candidate_id IS NULL
");

$null_votes = 0;

if ($null_stmt) {
    $null_stmt->bind_param("s", $position);
    $null_stmt->execute();
    $null_result = $null_stmt->get_result();
    $null_row = $null_result->fetch_assoc();
    $null_votes = (int)$null_row['total'];
    $null_stmt->close();
}
?>

<div class="position-box">
    <h2><?php echo htmlspecialchars($position, ENT_QUOTES, 'UTF-8'); ?></h2>

    <table>
        <tr>
            <th>S/N</th>
            <th>Candidate Name</th>
            <th>Total Votes</th>
            <th>Status</th>
        </tr>

        <?php if (empty($data)): ?>
            <tr>
                <td colspan="4">No active candidates found.</td>
            </tr>
        <?php else: ?>

            <?php $sn = 1; ?>

            <?php foreach ($data as $row): ?>
                <?php
                $is_winner = ($highest > 0 && $row['total_votes'] === $highest);
                ?>
                <tr class="<?php echo $is_winner ? 'winner' : ''; ?>">
                    <td><?php echo $sn++; ?></td>
                    <td><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $row['total_votes']; ?></td>
                    <td><?php echo $is_winner ? 'WINNER' : ''; ?></td>
                </tr>
            <?php endforeach; ?>

        <?php endif; ?>

        <tr class="no-interest">
            <td colspan="2"><strong>No Interest</strong></td>
            <td><?php echo $null_votes; ?></td>
            <td></td>
        </tr>
    </table>
</div>

<?php endforeach; ?>

<div class="footer">
    Results refresh automatically every 10 seconds.
</div>

</body>
</html>
