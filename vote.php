<?php
session_start();
require_once __DIR__ . '/db.php';

date_default_timezone_set('Africa/Lagos');

/* =========================================================
   DATABASE CHECK
========================================================= */
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed. Please check db.php.');
}

/* =========================================================
   CHECK LOGIN
========================================================= */
if (empty($_SESSION['membership_id'])) {
    header('Location: login.php');
    exit();
}

$membership_id = $_SESSION['membership_id'];

/* =========================================================
   GET VOTER
   Table: voters
   Columns: voter_id, name, membership_id, has_voted
========================================================= */
$stmt = $conn->prepare(
    'SELECT voter_id, name, membership_id, has_voted
     FROM voters
     WHERE membership_id = ?
     LIMIT 1'
);

if (!$stmt) {
    die('Database error: ' . htmlspecialchars($conn->error));
}

$stmt->bind_param('s', $membership_id);
$stmt->execute();

$result = $stmt->get_result();
$voter = $result->fetch_assoc();

$stmt->close();

if (!$voter) {
    session_destroy();
    header('Location: login.php');
    exit();
}

$membership_id = $voter['membership_id'];

/* =========================================================
   CHECK WHETHER VOTER HAS ALREADY VOTED
========================================================= */
if ((int)$voter['has_voted'] === 1) {
    unset($_SESSION['vote_start_time']);

    header('Location: login.php?voted=1');
    exit();
}

/* Also check the votes table using voter_id */
$stmt = $conn->prepare(
    'SELECT vote_id
     FROM votes
     WHERE voter_id = ?
     LIMIT 1'
);

if ($stmt) {
    $stmt->bind_param('i', $voter['voter_id']);
    $stmt->execute();

    $vote_result = $stmt->get_result();

    if ($vote_result->num_rows > 0) {
        $stmt->close();

        unset($_SESSION['vote_start_time']);

        header('Location: login.php?voted=1');
        exit();
    }

    $stmt->close();
}

/* =========================================================
   GET ACTIVE ELECTION
   Table: election_time
   Columns: id, start_time, end_time
========================================================= */
$election = null;

$election_sql = "
    SELECT id, start_time, end_time
    FROM election_time
    WHERE NOW() >= start_time
      AND NOW() < end_time
    ORDER BY id DESC
    LIMIT 1
";

$election_result = $conn->query($election_sql);

if ($election_result && $election_result->num_rows > 0) {
    $election = $election_result->fetch_assoc();
}

if (!$election) {
    header('Location: login.php?election=ended');
    exit();
}

$election_start = strtotime($election['start_time']);
$election_end   = strtotime($election['end_time']);
$current_time   = time();

if ($current_time < $election_start) {
    header('Location: login.php?election=not_started');
    exit();
}

if ($current_time >= $election_end) {
    header('Location: login.php?election=ended');
    exit();
}

/* =========================================================
   10-MINUTE VOTER COUNTDOWN
   The timer starts when the voter reaches vote.php.
========================================================= */
if (!isset($_SESSION['vote_start_time'])) {
    $_SESSION['vote_start_time'] = time();
}

$vote_start_time = (int)$_SESSION['vote_start_time'];
$vote_deadline   = $vote_start_time + (10 * 60);

$remaining_voter_seconds = max(0, $vote_deadline - time());

if ($remaining_voter_seconds <= 0) {
    unset($_SESSION['vote_start_time']);

    header('Location: login.php?timeout=1');
    exit();
}

/* =========================================================
   POSITION HIERARCHY
========================================================= */
$position_order = [
    'President',
    'Vice President',
    'Secretary General',
    'Treasurer',
    'Organizing Secretary',
    'Public Relations Officer (PRO)',
    'Financial Secretary',
    'Auditor'
];

/* =========================================================
   GET POSITIONS
========================================================= */
$positions = [];

$positions_result = $conn->query(
    'SELECT DISTINCT position FROM candidates'
);

if (!$positions_result) {
    die('Unable to load candidate positions: ' .
        htmlspecialchars($conn->error));
}

while ($row = $positions_result->fetch_assoc()) {
    $positions[] = $row['position'];
}

/* =========================================================
   SORT POSITIONS
========================================================= */
usort(
    $positions,
    function ($a, $b) use ($position_order) {
        $a_index = array_search($a, $position_order, true);
        $b_index = array_search($b, $position_order, true);

        if ($a_index === false) {
            $a_index = 999;
        }

        if ($b_index === false) {
            $b_index = 999;
        }

        return $a_index <=> $b_index;
    }
);

/* =========================================================
   ESCAPE HELPER
========================================================= */
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Online Voting System</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f8;
    color: #222;
}

/* HEADER */
.header {
    background: #064579;
    color: #fff;
    text-align: center;
    padding: 18px 10px;
}

.header h1 {
    margin: 0;
    font-size: 22px;
}

.header p {
    margin: 7px 0 0;
    font-size: 14px;
}

/* VOTER INFORMATION */
.voter-info {
    background: #fff;
    max-width: 1100px;
    margin: 15px auto 0;
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}

.voter-details {
    display: flex;
    flex-direction: column;
    gap: 5px;
    font-size: 14px;
}

.voter-details strong {
    color: #064579;
}

.logout {
    background: #dc3545;
    color: #fff;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 13px;
}

/* ONLY TIMER */
.timer-area {
    max-width: 900px;
    margin: 15px auto;
    padding: 0 15px;
}

.voter-timer {
    background: #fff0f0;
    border: 1px solid #f0b0b0;
    color: #c40000;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
}

.voter-timer-title {
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 5px;
}

.timer-number {
    font-size: 24px;
    font-weight: bold;
}

/* MAIN */
.container {
    max-width: 900px;
    margin: 20px auto;
    padding: 0 15px;
}

/* POSITION */
.position-box {
    background: #fff;
    margin-bottom: 18px;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}

.position-title {
    color: #064579;
    border-bottom: 2px solid #064579;
    padding-bottom: 8px;
    margin: 0 0 12px;
    font-size: 19px;
}

/* CANDIDATE */
.candidate {
    margin: 8px 0;
}

.candidate label {
    display: block;
    background: #f8f9fa;
    border: 1px solid #ddd;
    padding: 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

.candidate label:hover {
    background: #eef6ff;
}

.candidate input[type="radio"] {
    margin-right: 8px;
}

/* NO INTEREST */
.no-interest-option label {
    background: #fff8e1;
    border: 1px dashed #c28a00;
}

.no-interest-option label:hover {
    background: #fff3cd;
}

.no-interest-note {
    display: block;
    margin: 5px 0 0 24px;
    font-size: 12px;
    color: #6b5b2a;
}

/* SUBMIT */
.submit-area {
    text-align: center;
    margin: 30px 0 50px;
}

.submit-btn {
    background: #064579;
    color: #fff;
    border: none;
    padding: 14px 35px;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
}

.submit-btn:hover {
    background: #04345c;
}

.submit-btn:disabled {
    opacity: .6;
    cursor: not-allowed;
}

/* MOBILE */
@media (max-width: 600px) {
    .header h1 {
        font-size: 19px;
    }

    .voter-info {
        margin: 10px;
    }

    .voter-details {
        width: 100%;
    }

    .logout {
        align-self: flex-start;
    }

    .timer-area {
        padding: 0 10px;
    }

    .timer-number {
        font-size: 22px;
    }
}

/* =========================================================
   VOTE CONFIRMATION MODAL
========================================================= */
.vote-modal {
    display:none;
    position:fixed;
    inset:0;
    z-index:9999;
}

.vote-modal.show {
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
}

.vote-modal-overlay {
    position:absolute;
    inset:0;
    background:rgba(0,0,0,.65);
}

.vote-modal-content {
    position:relative;
    z-index:2;
    width:min(650px, 100%);
    max-height:90vh;
    overflow-y:auto;
    background:#fff;
    border-radius:12px;
    padding:28px;
    box-shadow:0 20px 60px rgba(0,0,0,.3);
}

.vote-modal-content h2 {
    margin:0 35px 16px 0;
    text-align:center;
    color:#003366;
    font-size:24px;
}

.vote-modal-close {
    position:absolute;
    top:10px;
    right:14px;
    border:0;
    background:transparent;
    font-size:32px;
    color:#666;
    cursor:pointer;
}

.confirm-greeting {
    font-size:17px;
    line-height:1.6;
    margin-bottom:10px;
}

.confirm-intro {
    margin:8px 0 12px;
    font-weight:bold;
}

.vote-summary {
    border:1px solid #ddd;
    border-radius:8px;
    overflow:hidden;
}

.summary-row {
    display:flex;
    gap:12px;
    padding:11px 14px;
    border-bottom:1px solid #eee;
    line-height:1.4;
}

.summary-row:last-child {
    border-bottom:0;
}

.summary-position {
    width:45%;
    font-weight:bold;
    color:#003366;
}

.summary-candidate {
    width:55%;
}

.confirm-warning {
    margin-top:16px;
    padding:12px;
    background:#fff4d6;
    border-left:4px solid #e0a800;
    line-height:1.5;
}

.confirm-actions {
    display:flex;
    gap:12px;
    margin-top:20px;
}

.confirm-actions button {
    flex:1;
    border:0;
    padding:13px 16px;
    border-radius:7px;
    font-weight:bold;
    cursor:pointer;
}

.edit-vote-btn {
    background:#e9ecef;
    color:#333;
}

.confirm-submit-btn {
    background:#159447;
    color:#fff;
}

.confirm-submit-btn:disabled {
    background:#888;
    cursor:not-allowed;
}

body.modal-open {
    overflow:hidden;
}

@media(max-width:600px) {
    .vote-modal-content {
        padding:22px 16px;
    }

    .summary-row {
        display:block;
    }

    .summary-position,
    .summary-candidate {
        display:block;
        width:100%;
    }

    .summary-candidate {
        margin-top:3px;
    }

    .confirm-actions {
        flex-direction:column;
    }
}

</style>
</head>

<body>

<div class="header">
    <h1>PLATEAU STATE TRADERS AND MARKETERS ASSOCIATION</h1>
    <p>ONLINE VOTING SYSTEM</p>
</div>

<!-- VOTER DETAILS -->
<div class="voter-info">

    <div class="voter-details">
        <div>
            <strong>Member ID:</strong>
            <?= e($voter['membership_id']); ?>
        </div>

        <div>
            <strong>Name:</strong>
            <?= e($voter['name']); ?>
        </div>
    </div>

    <a href="logout.php" class="logout">Logout</a>

</div>

<!-- ONLY THE 10-MINUTE COUNTDOWN -->
<div class="timer-area">

    <div class="voter-timer">

        <div class="voter-timer-title">
            YOUR VOTING TIME REMAINING
        </div>

        <div class="timer-number">
            <span id="voterMinutes">
                <?= floor($remaining_voter_seconds / 60); ?>
            </span>m

            <span id="voterSeconds">
                <?= str_pad($remaining_voter_seconds % 60, 2, '0', STR_PAD_LEFT); ?>
            </span>s
        </div>

    </div>

</div>

<!-- VOTING FORM -->
<div class="container">

<form
    action="submit_vote.php"
    method="POST"
    id="voteForm"
>

    <input
        type="hidden"
        name="membership_id"
        value="<?= e($membership_id); ?>"
    >

    <?php foreach ($positions as $position): ?>

        <div class="position-box">

            <h2 class="position-title">
                <?= e($position); ?>
            </h2>

            <?php
            $candidate_stmt = $conn->prepare(
                'SELECT candidate_id, name
                 FROM candidates
                 WHERE position = ?
                   AND status = ?
                 ORDER BY candidate_id ASC'
            );

            if (!$candidate_stmt) {
                die('Unable to load candidates: ' .
                    htmlspecialchars($conn->error));
            }

            $active_status = 'Active';

            $candidate_stmt->bind_param(
                'ss',
                $position,
                $active_status
            );

            $candidate_stmt->execute();

            $candidate_result = $candidate_stmt->get_result();

            while ($candidate = $candidate_result->fetch_assoc()):
            ?>

                <div class="candidate">

                    <label>

                        <input
                            type="radio"
                            name="vote[<?= e($position); ?>]"
                            value="<?= e($candidate['candidate_id']); ?>"
                            data-candidate-name="<?= e($candidate['name']); ?>"
                            required
                        >

                        <strong>
                            <?= e($candidate['name']); ?>
                        </strong>

                    </label>

                </div>

            <?php endwhile; ?>

            <!-- NO INTEREST -->
            <div class="candidate no-interest-option">

                <label>

                    <input
                        type="radio"
                        name="vote[<?= e($position); ?>]"
                        value="NO_INTEREST"
                        required
                    >

                    <strong>No Interest</strong>

                    <span class="no-interest-note">
                        I have no interest in voting for any candidate for this position.
                    </span>

                </label>

            </div>

            <?php $candidate_stmt->close(); ?>

        </div>

    <?php endforeach; ?>

    <div class="submit-area">

        <button
            type="submit"
            class="submit-btn"
            id="submitButton"
        >
            CAST MY VOTE
        </button>

    </div>

</form>

</div>

<!-- =========================================================
     VOTE CONFIRMATION POPUP
========================================================= -->
<div id="voteConfirmModal" class="vote-modal" aria-hidden="true">
    <div class="vote-modal-overlay"></div>

    <div class="vote-modal-content" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
        <button type="button" class="vote-modal-close" id="closeVoteModal" aria-label="Close">&times;</button>

        <h2 id="confirmTitle">CONFIRM YOUR VOTE</h2>

        <div class="confirm-greeting" id="confirmGreeting"></div>

        <p class="confirm-intro">You selected the following candidates:</p>

        <div id="voteSummary" class="vote-summary"></div>

        <div class="confirm-warning">
            Please review your selections carefully. Your vote will be recorded only after you click <strong>CONFIRM &amp; SUBMIT VOTE</strong>.
        </div>

        <div class="confirm-actions">
            <button type="button" class="edit-vote-btn" id="editVoteButton">GO BACK &amp; EDIT</button>
            <button type="button" class="confirm-submit-btn" id="confirmSubmitButton">CONFIRM &amp; SUBMIT VOTE</button>
        </div>
    </div>
</div>

<script>
/* =========================================================
   10-MINUTE VOTER COUNTDOWN
========================================================= */

let voterRemaining = <?= (int)$remaining_voter_seconds; ?>;
let timerInterval = null;
let submitting = false;

const minutesElement = document.getElementById('voterMinutes');
const secondsElement = document.getElementById('voterSeconds');
const submitButton = document.getElementById('submitButton');
const voteForm = document.getElementById('voteForm');

function updateTimer() {

    if (voterRemaining <= 0) {

        voterRemaining = 0;

        minutesElement.textContent = '0';
        secondsElement.textContent = '00';

        if (timerInterval) {
            clearInterval(timerInterval);
        }

        submitButton.disabled = true;

        alert(
            'Your 10-minute voting period has expired. You will be returned to the login page.'
        );

        window.location.href = 'login.php?timeout=1';

        return;
    }

    const minutes = Math.floor(voterRemaining / 60);
    const seconds = voterRemaining % 60;

    minutesElement.textContent = minutes;
    secondsElement.textContent =
        String(seconds).padStart(2, '0');

    voterRemaining--;
}

/* Start immediately, then every second */
updateTimer();

timerInterval = setInterval(updateTimer, 1000);

/* =========================================================
   PREVENT SUBMISSION AFTER TIME EXPIRES
========================================================= */

/* =========================================================
   VOTE CONFIRMATION SUMMARY
========================================================= */

const voteModal = document.getElementById('voteConfirmModal');
const voteSummary = document.getElementById('voteSummary');
const confirmGreeting = document.getElementById('confirmGreeting');
const closeVoteModal = document.getElementById('closeVoteModal');
const editVoteButton = document.getElementById('editVoteButton');
const confirmSubmitButton = document.getElementById('confirmSubmitButton');

const voterName = <?= json_encode($voter['name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
}

function openVoteConfirmation() {

    if (voterRemaining <= 0) {
        alert('Your voting time has expired.');
        window.location.href = 'login.php?timeout=1';
        return;
    }

    const selectedVotes = voteForm.querySelectorAll('input[type="radio"][name^="vote["]:checked');

    voteSummary.innerHTML = '';

    selectedVotes.forEach(function(radio) {
        const position = radio.name.replace(/^vote\[/, '').replace(/\]$/, '');
        const candidateName = radio.value === 'NO_INTEREST'
            ? 'No Interest'
            : (radio.getAttribute('data-candidate-name') || 'Selected Candidate');

        const row = document.createElement('div');
        row.className = 'summary-row';
        row.innerHTML =
            '<span class="summary-position">' + escapeHtml(position) + ':</span>' +
            '<span class="summary-candidate">' + escapeHtml(candidateName) + '</span>';

        voteSummary.appendChild(row);
    });

    confirmGreeting.innerHTML =
        'Dear <strong>' + escapeHtml(voterName) + '</strong>, you voted for:';

    voteModal.classList.add('show');
    voteModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
}

function closeVoteConfirmation() {
    voteModal.classList.remove('show');
    voteModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
}

voteForm.addEventListener('submit', function(event) {

    event.preventDefault();

    if (voterRemaining <= 0) {
        alert('Your voting time has expired.');
        window.location.href = 'login.php?timeout=1';
        return;
    }

    if (submitting) {
        return;
    }

    /* HTML required fields are checked before this submit event in normal browsers. */
    if (!voteForm.checkValidity()) {
        voteForm.reportValidity();
        return;
    }

    openVoteConfirmation();
});

closeVoteModal.addEventListener('click', closeVoteConfirmation);
editVoteButton.addEventListener('click', closeVoteConfirmation);

document.querySelector('.vote-modal-overlay').addEventListener('click', closeVoteConfirmation);

confirmSubmitButton.addEventListener('click', function() {

    if (voterRemaining <= 0) {
        closeVoteConfirmation();
        alert('Your voting time has expired.');
        window.location.href = 'login.php?timeout=1';
        return;
    }

    if (submitting) {
        return;
    }

    submitting = true;
    confirmSubmitButton.disabled = true;
    editVoteButton.disabled = true;
    submitButton.disabled = true;
    confirmSubmitButton.textContent = 'SUBMITTING...';

    /* This is the only point at which the form is actually submitted. */
    voteForm.submit();
});
</script>

</body>
</html>
