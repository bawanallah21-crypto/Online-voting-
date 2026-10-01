<?php
session_start();

/* Load database connection */
require_once __DIR__ . '/db.php';

/* =========================================================
   LEADING CANDIDATES SUMMARY
   Displays the candidate currently having the highest votes
   for each election position.
========================================================= */

$position_order = [
    'President',
    'Vice President',
    'Secretary General',
    'Treasurer',
    'Organizing Secretary',
    'Public Relation Officer (PRO)',
    'Financial Secretary',
    'Auditor'
];

$leading_candidates = [];

foreach ($position_order as $position) {

    $stmt = $conn->prepare("
        SELECT
            c.name,
            COUNT(v.vote_id) AS total_votes
        FROM candidates c
        LEFT JOIN votes v
            ON v.candidate_id = c.candidate_id
           AND v.position = ?
        WHERE c.position = ?
          AND c.status = 'Active'
        GROUP BY c.candidate_id, c.name
        ORDER BY total_votes DESC, c.candidate_id ASC
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("ss", $position, $position);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row) {
            $leading_candidates[] = [
                'position' => $position,
                'name' => $row['name'],
                'votes' => (int)$row['total_votes']
            ];
        } else {
            $leading_candidates[] = [
                'position' => $position,
                'name' => 'No votes yet',
                'votes' => 0
            ];
        }

        $stmt->close();
    }
}


/* Nigeria time */
date_default_timezone_set('Africa/Lagos');

/* Make sure db.php created $conn */
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection error: $conn was not created by db.php.');
}

/* =====================================================
   GET THE ACTIVE ELECTION, OR THE NEXT SCHEDULED ELECTION
   Table: election_time
   Columns: id, start_time, end_time
===================================================== */
$election = null;

/* First: find an election currently running */
$sql_active = "
    SELECT id, start_time, end_time
    FROM election_time
    WHERE start_time <= NOW()
      AND end_time > NOW()
    ORDER BY id DESC
    LIMIT 1
";

$active_result = $conn->query($sql_active);

if ($active_result && $active_result->num_rows > 0) {
    $election = $active_result->fetch_assoc();
} else {
    /* If none is running, find the next election */
    $sql_next = "
        SELECT id, start_time, end_time
        FROM election_time
        WHERE start_time > NOW()
        ORDER BY start_time ASC
        LIMIT 1
    ";

    $next_result = $conn->query($sql_next);

    if ($next_result && $next_result->num_rows > 0) {
        $election = $next_result->fetch_assoc();
    }
}

/* Default values */
$election_start_timestamp = 0;
$election_end_timestamp = 0;
$election_status = 'none';

if ($election) {
    $election_start_timestamp = strtotime($election['start_time']);
    $election_end_timestamp = strtotime($election['end_time']);

    $now_timestamp = time();

    if ($now_timestamp < $election_start_timestamp) {
        $election_status = 'upcoming';
    } elseif ($now_timestamp < $election_end_timestamp) {
        $election_status = 'active';
    } else {
        $election_status = 'ended';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Plateau State Traders and Marketers Association - Online Voting System</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #222;
}

.header {
    background: #003366;
    color: white;
    text-align: center;
    padding: 25px 15px;
}

.header h1 {
    margin: 0;
    font-size: 25px;
}

.header h3 {
    margin: 8px 0 0;
}

.banner {
    text-align: center;
    padding: 40px 20px;
    background: white;
}

.banner h2 {
    color: #003366;
    margin-top: 0;
}

.banner p {
    font-size: 18px;
    color: #555;
}

.container {
    width: 90%;
    max-width: 1000px;
    margin: 20px auto;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.btn {
    display: inline-block;
    padding: 15px 30px;
    margin: 10px 5px;
    text-decoration: none;
    color: white;
    border-radius: 5px;
    font-weight: bold;
}

.vote-btn { background: #28a745; }
.result-btn { background: #007bff; }
.admin-btn { background: #dc3545; }

.election-timer {
    width: 90%;
    max-width: 700px;
    background: #003366;
    color: white;
    text-align: center;
    padding: 20px;
    margin: 25px auto 0;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.15);
}

.timer-title {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 8px;
}

.timer-status {
    font-size: 14px;
    margin-bottom: 10px;
    opacity: 0.95;
}

.timer-countdown {
    font-size: 32px;
    font-weight: bold;
    letter-spacing: 1px;
    line-height: 1.4;
}

.info-table {
    width: 100%;
    border-collapse: collapse;
}

.info-table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

.footer {
    background: #003366;
    color: white;
    text-align: center;
    padding: 15px;
    margin-top: 30px;
    line-height: 1.6;
}

@media (max-width: 600px) {
    .header h1 {
        font-size: 19px;
    }

    .banner {
        padding: 30px 15px;
    }

    .banner p {
        font-size: 16px;
    }

    .btn {
        display: block;
        margin: 10px auto;
        max-width: 280px;
    }

    .election-timer {
        width: 100%;
    }

    .timer-countdown {
        font-size: 24px;
    }

    .info-table td {
        display: block;
        width: 100%;
    }

    .info-table td:first-child {
        padding-bottom: 2px;
    }
}



/* =========================================================
   LEADING CANDIDATES SIDE PANEL
========================================================= */
.page-layout {
    width: 94%;
    max-width: 1400px;
    margin: 25px auto;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    gap: 22px;
    align-items: start;
}

.main-content {
    min-width: 0;
}

.leading-side-panel {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 3px 14px rgba(0,0,0,.12);
    overflow: hidden;
    position: sticky;
    top: 20px;
}

.leading-side-header {
    background: #003;
    color: #ffffff;
    padding: 18px 20px;
}

.leading-side-header h2 {
    margin: 0 0 5px;
    font-size: 20px;
}

.leading-side-header p {
    margin: 0;
    font-size: 12px;
    opacity: .9;
}

.leading-side-list {
    padding: 8px 16px;
}

.leading-side-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px 12px;
    padding: 13px 2px;
    border-bottom: 1px solid #e8e8e8;
}

.leading-side-row:last-child {
    border-bottom: none;
}

.leading-side-position {
    grid-column: 1 / -1;
    font-size: 13px;
    font-weight: 700;
    color: #003366;
}

.leading-side-candidate {
    font-size: 14px;
    font-weight: 600;
    color: #222;
}

.leading-side-votes {
    font-size: 13px;
    font-weight: 700;
    color: #159447;
    white-space: nowrap;
    text-align: right;
}

.leading-side-footer {
    background: #f7f8fa;
    padding: 12px 16px;
    text-align: center;
    color: #777;
    font-size: 11px;
    line-height: 1.5;
}

@media (max-width: 900px) {
    .page-layout {
        grid-template-columns: 1fr;
    }

    .leading-side-panel {
        position: static;
        order: 2;
    }

    .main-content {
        order: 1;
    }
}

</style>

</head>
<body>

<div class="header">
    <h1>PLATEAU STATE TRADERS AND MARKETERS ASSOCIATION</h1>
    <h3>ONLINE VOTING SYSTEM</h3>
</div>

<div class="banner">

    <h2>Welcome to the Official Election Portal</h2>

    <p>Secure, Transparent and Convenient Electronic Voting Platform</p>

    <a href="login.php" class="btn vote-btn">🗳 Proceed to Vote</a>

    <a href="results.php" class="btn result-btn">📊 View Results</a>

    <a href="admin/admin_login.php" class="btn admin-btn">🔐 Admin Login</a>

    <div class="election-timer">

        <div class="timer-title">ELECTION TIME REMAINING</div>

        <div id="electionStatus" class="timer-status">
            <?php
            if ($election_status === 'active') {
                echo 'ELECTION IS CURRENTLY RUNNING';
            } elseif ($election_status === 'upcoming') {
                echo 'ELECTION STARTS IN';
            } elseif ($election_status === 'ended') {
                echo 'ELECTION ENDED';
            } else {
                echo 'ELECTION STATUS';
            }
            ?>
        </div>

        <div id="electionCountdown" class="timer-countdown">
            <?php
            if ($election_status === 'none') {
                echo 'NO ELECTION SCHEDULED';
            } elseif ($election_status === 'ended') {
                echo 'ELECTION ENDED';
            } else {
                echo 'Loading...';
            }
            ?>
        </div>

    </div>
</div>

<div class="page-layout">

    <main class="main-content">

        <div class="container" style="width:100%; max-width:none; margin:0;">

            <div class="card">
                <h2>Election Information</h2>

                <table class="info-table">
                    <tr>
                        <td><strong>Election Name:</strong></td>
                        <td>PSTAMA General Election</td>
                    </tr>
                    <tr>
                        <td><strong>Voting Method:</strong></td>
                        <td>Online Electronic Voting</td>
                    </tr>
                    <tr>
                        <td><strong>Voter Eligibility:</strong></td>
                        <td>Registered Association Members</td>
                    </tr>
                    <tr>
                        <td><strong>Voting Duration:</strong></td>
                        <td>10 Minutes Per Voter Session</td>
                    </tr>
                    <tr>
                        <td><strong>Security:</strong></td>
                        <td>One Member, One Vote</td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <h2>System Features</h2>
                <ul>
                    <li>Secure Member Authentication</li>
                    <li>Multiple Position Voting</li>
                    <li>Automatic Vote Counting</li>
                    <li>Election Result Reporting</li>
                    <li>Printable Reports</li>
                    <li>Invalid Vote Tracking</li>
                    <li>Automatic Session Timeout</li>
                    <li>Professional Election Dashboard</li>
                </ul>
            </div>

        </div>

    </main>

    <aside class="leading-side-panel">
        <div class="leading-side-header">
            <h2>CURRENT LEADING CANDIDATES</h2>
            <p>Current vote count for each election position</p>
        </div>

        <div class="leading-side-list">

            <?php foreach ($leading_candidates as $leader): ?>

                <div class="leading-side-row">

                    <div class="leading-side-position">
                        <?= htmlspecialchars(
                            $leader['position'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </div>

                    <div class="leading-side-candidate">
                        <?= htmlspecialchars(
                            $leader['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </div>

                    <div class="leading-side-votes">
                        <?= (int)$leader['votes']; ?>
                        <?= ((int)$leader['votes'] === 1)
                            ? 'vote'
                            : 'votes'; ?>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

        <div class="leading-side-footer">
            Results are based on votes currently recorded in the system.
        </div>
    </aside>

</div>

<div class="footer">
    &copy; <?php echo date('Y'); ?> Plateau State Traders and Marketers Association
    <br>
    Online Voting System | ISAH YUSUF ALIYU
</div>

<script>
/* =====================================================
   ELECTION COUNTDOWN
===================================================== */

const electionStart = <?php echo (int)($election_start_timestamp * 1000); ?>;
const electionEnd = <?php echo (int)($election_end_timestamp * 1000); ?>;

function updateElectionCountdown() {

    const status = document.getElementById('electionStatus');
    const countdown = document.getElementById('electionCountdown');

    if (!status || !countdown) {
        return;
    }

    const now = Date.now();

    /* No election was found */
    if (!electionStart || !electionEnd) {
        status.textContent = 'ELECTION STATUS';
        countdown.textContent = 'NO ELECTION SCHEDULED';
        return;
    }

    /* Election has not started */
    if (now < electionStart) {

        const difference = electionStart - now;

        const days = Math.floor(difference / (1000 * 60 * 60 * 24));
        const hours = Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((difference % (1000 * 60)) / 1000);

        status.textContent = 'ELECTION STARTS IN';
        countdown.textContent =
            days + 'd ' +
            hours + 'h ' +
            minutes + 'm ' +
            seconds + 's';

        return;
    }

    /* Election is running */
    if (now < electionEnd) {

        const difference = electionEnd - now;

        const days = Math.floor(difference / (1000 * 60 * 60 * 24));
        const hours = Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((difference % (1000 * 60)) / 1000);

        status.textContent = 'ELECTION TIME REMAINING';
        countdown.textContent =
            days + 'd ' +
            hours + 'h ' +
            minutes + 'm ' +
            seconds + 's';

        return;
    }

    /* Election has ended */
    status.textContent = 'ELECTION STATUS';
    countdown.textContent = 'ELECTION ENDED';
}

updateElectionCountdown();
setInterval(updateElectionCountdown, 1000);
</script>


</body>
</html>
