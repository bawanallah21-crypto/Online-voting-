<?php
session_start();
require_once '../db.php';

date_default_timezone_set('Africa/Lagos');

/* =========================================================
   ADMIN ACCESS
========================================================= */
if (!isset($_SESSION['admin']) || empty($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection error. Please check db.php.');
}

/* =========================================================
   POSITION ORDER
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

$message = '';
$error = '';
$voter = null;
$position_candidates = [];

/* =========================================================
   SEARCH VOTER
========================================================= */
$search_membership_id = trim((string)($_GET['membership_id'] ?? $_POST['membership_id'] ?? ''));

if ($search_membership_id !== '') {
    $stmt = $conn->prepare(
        'SELECT voter_id, name, membership_id, has_voted
         FROM voters
         WHERE membership_id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        $error = 'Unable to search for the voter: ' . $conn->error;
    } else {
        $stmt->bind_param('s', $search_membership_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $voter = $result->fetch_assoc();
        $stmt->close();

        if (!$voter) {
            $error = 'No registered voter was found with Membership ID: ' . htmlspecialchars($search_membership_id, ENT_QUOTES, 'UTF-8');
        }
    }
}

/* =========================================================
   LOAD ACTIVE CANDIDATES
========================================================= */
$stmt = $conn->prepare(
    "SELECT candidate_id, name, position
     FROM candidates
     WHERE status = 'Active'
     ORDER BY candidate_id ASC"
);

if (!$stmt) {
    die('Candidate query error: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}

$stmt->execute();
$result = $stmt->get_result();

while ($candidate = $result->fetch_assoc()) {
    $position = trim((string)$candidate['position']);

    if (!isset($position_candidates[$position])) {
        $position_candidates[$position] = [];
    }

    $position_candidates[$position][] = $candidate;
}
$stmt->close();

/* =========================================================
   RECORD MANUAL VOTE
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_manual_vote'])) {

    $posted_voter_id = (int)($_POST['voter_id'] ?? 0);
    $posted_membership_id = trim((string)($_POST['membership_id'] ?? ''));

    if ($posted_voter_id <= 0 || $posted_membership_id === '') {
        $error = 'Please search for a valid voter first.';
    } else {

        /* Lock the voter record so the same voter cannot be recorded twice. */
        $conn->begin_transaction();

        try {
            $lock = $conn->prepare(
                'SELECT voter_id, name, membership_id, has_voted
                 FROM voters
                 WHERE voter_id = ?
                 LIMIT 1
                 FOR UPDATE'
            );

            if (!$lock) {
                throw new Exception('Unable to lock voter record.');
            }

            $lock->bind_param('i', $posted_voter_id);
            $lock->execute();
            $locked_result = $lock->get_result();
            $locked_voter = $locked_result->fetch_assoc();
            $lock->close();

            if (!$locked_voter) {
                throw new Exception('Voter record was not found.');
            }

            if ((int)$locked_voter['has_voted'] === 1) {
                throw new Exception('This voter has already voted. The manual vote was not recorded.');
            }

            /* Also check the votes table, using voter_id as the project schema requires. */
            $existing = $conn->prepare(
                'SELECT vote_id
                 FROM votes
                 WHERE voter_id = ?
                 LIMIT 1'
            );

            if (!$existing) {
                throw new Exception('Unable to check existing votes.');
            }

            $existing->bind_param('i', $posted_voter_id);
            $existing->execute();
            $existing_result = $existing->get_result();
            $already_exists = $existing_result->num_rows > 0;
            $existing->close();

            if ($already_exists) {
                throw new Exception('A vote already exists for this voter. The manual vote was not recorded.');
            }

            /* Collect exactly one selection for every position. */
            $submitted_votes = [];

            foreach ($position_order as $position) {
                $field = 'vote_' . md5($position);

                if (!isset($_POST[$field])) {
                    throw new Exception('Please select an option for ' . $position . '.');
                }

                $value = trim((string)$_POST[$field]);

                /* The existing voting system stores No Interest as candidate_id 0. */
                if ($value === 'NO_INTEREST') {
                    $submitted_votes[$position] = 0;
                } elseif (ctype_digit($value) && (int)$value > 0) {
                    $submitted_votes[$position] = (int)$value;
                } else {
                    throw new Exception('Invalid selection for ' . $position . '.');
                }
            }

            /* Verify every selected candidate belongs to the correct position and is active. */
            foreach ($submitted_votes as $position => $candidate_id) {
                if ($candidate_id === 0) {
                    continue;
                }

                $verify = $conn->prepare(
                    "SELECT candidate_id
                     FROM candidates
                     WHERE candidate_id = ?
                       AND position = ?
                       AND status = 'Active'
                     LIMIT 1"
                );

                if (!$verify) {
                    throw new Exception('Unable to verify candidate selection.');
                }

                $verify->bind_param('is', $candidate_id, $position);
                $verify->execute();
                $verify_result = $verify->get_result();
                $valid_candidate = $verify_result->fetch_assoc();
                $verify->close();

                if (!$valid_candidate) {
                    throw new Exception('Invalid candidate selection for ' . $position . '.');
                }
            }

            /* Insert all eight position votes as one transaction. */
            $insert = $conn->prepare(
                'INSERT INTO votes (voter_id, candidate_id, position, vote_time)
                 VALUES (?, ?, ?, NOW())'
            );

            if (!$insert) {
                throw new Exception('Unable to prepare vote recording: ' . $conn->error);
            }

            foreach ($submitted_votes as $position => $candidate_id) {
                $insert->bind_param('iis', $posted_voter_id, $candidate_id, $position);

                if (!$insert->execute()) {
                    throw new Exception('Could not save vote for ' . $position . ': ' . $insert->error);
                }
            }

            $insert->close();

            /* Mark the voter as having voted. */
            $update = $conn->prepare(
                'UPDATE voters
                 SET has_voted = 1
                 WHERE voter_id = ?'
            );

            if (!$update) {
                throw new Exception('Unable to update voter status.');
            }

            $update->bind_param('i', $posted_voter_id);

            if (!$update->execute()) {
                throw new Exception('Unable to update voter voting status.');
            }

            $update->close();
            $conn->commit();

            $message = 'Manual vote recorded successfully for ' . $locked_voter['name'] . ' (Membership ID: ' . $locked_voter['membership_id'] . ').';

            /* Show the voter as already voted after successful recording. */
            $voter = $locked_voter;
            $voter['has_voted'] = 1;
            $search_membership_id = $locked_voter['membership_id'];

        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e->getMessage();

            /* Reload voter details for the form. */
            $stmt = $conn->prepare(
                'SELECT voter_id, name, membership_id, has_voted
                 FROM voters
                 WHERE voter_id = ?
                 LIMIT 1'
            );
            if ($stmt) {
                $stmt->bind_param('i', $posted_voter_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $voter = $result->fetch_assoc();
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manual Vote Entry - PSTAMA</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,sans-serif;background:#f4f6f9;color:#222}
.header{background:#003366;color:#fff;padding:22px 15px;text-align:center}
.header h1{margin:0;font-size:24px}
.header p{margin:7px 0 0;font-size:14px}
.container{width:94%;max-width:1000px;margin:25px auto}
.card{background:#fff;border-radius:10px;padding:24px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,.08)}
h2{margin-top:0;color:#003366}
.search-row{display:flex;gap:10px;flex-wrap:wrap}
.search-row input{flex:1;min-width:220px;padding:13px;border:1px solid #ccc;border-radius:6px;font-size:16px}
button,.btn{border:0;background:#003366;color:#fff;padding:13px 20px;border-radius:6px;cursor:pointer;font-weight:bold;text-decoration:none;display:inline-block}
.btn-secondary{background:#6c757d}
.success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;padding:14px;border-radius:7px;margin-bottom:20px}
.error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;padding:14px;border-radius:7px;margin-bottom:20px}
.voter-info{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.info-box{background:#f7f9fc;border:1px solid #e3e7ed;border-radius:7px;padding:14px}
.info-box small{display:block;color:#666;margin-bottom:5px}
.info-box strong{font-size:17px}
.status-voted{color:#b00020;font-weight:bold}
.status-not-voted{color:#16803c;font-weight:bold}
.position-card{border:1px solid #ddd;border-radius:8px;margin-bottom:15px;overflow:hidden}
.position-title{background:#003366;color:#fff;padding:13px 16px;font-size:17px;font-weight:bold}
.options{padding:14px 16px}
.option{display:block;border-bottom:1px solid #eee;padding:10px 4px}
.option:last-child{border-bottom:0}
.option input{margin-right:10px;transform:scale(1.1)}
.no-interest{color:#8a5a00;font-weight:bold}
.submit-area{text-align:center;padding-top:8px}
.submit-area button{background:#16803c;font-size:17px;padding:14px 28px}
.note{font-size:13px;color:#666;margin-top:10px}
.footer{text-align:center;color:#777;padding:20px}
@media(max-width:700px){.voter-info{grid-template-columns:1fr}.header h1{font-size:19px}.search-row{flex-direction:column}.search-row input, .search-row button{width:100%}}
</style>
</head>
<body>

<div class="header">
    <h1>PLATEAU STATE TRADERS AND MARKETERS ASSOCIATION</h1>
    <p>ADMINISTRATION — MANUAL VOTE ENTRY</p>
</div>

<div class="container">

<?php if ($message !== ''): ?>
    <div class="success">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="error">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Search Registered Voter</h2>
    <p>Enter the voter's Membership ID to load the member before recording a manual vote.</p>

    <form method="get" action="manual_vote.php" class="search-row">
        <input type="text" name="membership_id" value="<?= htmlspecialchars($search_membership_id, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter Membership ID" required>
        <button type="submit">Search Voter</button>
    </form>
</div>

<?php if ($voter): ?>

<div class="card">
    <h2>Voter Information</h2>
    <div class="voter-info">
        <div class="info-box">
            <small>Voter Name</small>
            <strong><?= htmlspecialchars($voter['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
        <div class="info-box">
            <small>Membership ID</small>
            <strong><?= htmlspecialchars($voter['membership_id'], ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
        <div class="info-box">
            <small>Voting Status</small>
            <?php if ((int)$voter['has_voted'] === 1): ?>
                <strong class="status-voted">ALREADY VOTED</strong>
            <?php else: ?>
                <strong class="status-not-voted">NOT YET VOTED</strong>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ((int)$voter['has_voted'] === 0 && $message === ''): ?>
<div class="card">
    <h2>Record Manual Vote</h2>
    <p>Select one option for every position. <strong>No Interest</strong> may be selected when the voter does not wish to choose a candidate.</p>

    <form method="post" action="manual_vote.php" onsubmit="return confirmManualVote();">
        <input type="hidden" name="voter_id" value="<?= (int)$voter['voter_id']; ?>">
        <input type="hidden" name="membership_id" value="<?= htmlspecialchars($voter['membership_id'], ENT_QUOTES, 'UTF-8'); ?>">

        <?php foreach ($position_order as $position): ?>
            <div class="position-card">
                <div class="position-title"><?= htmlspecialchars($position, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="options">
                    <?php if (!empty($position_candidates[$position])): ?>
                        <?php foreach ($position_candidates[$position] as $candidate): ?>
                            <label class="option">
                                <input type="radio" name="vote_<?= md5($position); ?>" value="<?= (int)$candidate['candidate_id']; ?>" required>
                                <?= htmlspecialchars($candidate['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <label class="option no-interest">
                        <input type="radio" name="vote_<?= md5($position); ?>" value="NO_INTEREST" required>
                        No Interest
                    </label>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="submit-area">
            <button type="submit" name="record_manual_vote" value="1">Record Manual Vote</button>
            <div class="note">This action records the vote in the same votes table used by electronic voting and marks the voter as having voted.</div>
        </div>
    </form>
</div>
<?php endif; ?>

<?php endif; ?>

<div class="card" style="text-align:center">
    <a class="btn btn-secondary" href="admin_dashboard.php">Back to Admin Dashboard</a>
    <a class="btn" href="manual_vote.php">Search Another Voter</a>
</div>

</div>

<div class="footer">
    PSTAMA Online Voting System — Manual Vote Administration
</div>

<script>
function confirmManualVote(){
    return confirm(
        'Are you sure you want to record this manual vote?\n\n' +
        'After submission, the voter will be marked as having voted and cannot vote again.'
    );
}
</script>

</body>
</html>
