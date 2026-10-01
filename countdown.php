<?php
session_start();

require_once 'db.php';

/*     TIME ZONE    */
date_default_timezone_set('Africa/Lagos');


/*     CHECK LOGIN   */
if (!isset($_SESSION['membership_id']) || empty($_SESSION['membership_id'])) {
    header("Location: login.php");
    exit();
}

$membership_id = $_SESSION['membership_id'];


/*     GET VOTER INFORMATION       */
$stmt = $conn->prepare("
    SELECT name, membership_id, has_voted
    FROM voters
    WHERE membership_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("s", $membership_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    session_destroy();

    header("Location: login.php");
    exit();
}

$voter = $result->fetch_assoc();

$voter_name = $voter['name'];
$membership_id = $voter['membership_id'];
$has_voted = (int)$voter['has_voted'];

$stmt->close();


/*        PREVENT A VOTER WHO HAS ALREADY VOTED FROM ENTERING AGAIN       */
if ($has_voted === 1) {
    header("Location: results.php");
    exit();
}


/*            GET THE CURRENT ELECTION        */
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

} else {

    /*     No active election. Check whether a future election exists.     */
    $next_sql = "
        SELECT id, start_time, end_time
        FROM election_time
        WHERE start_time > NOW()
        ORDER BY start_time ASC
        LIMIT 1
    ";

    $next_result = $conn->query($next_sql);

    if ($next_result && $next_result->num_rows > 0) {
        $election = $next_result->fetch_assoc();
    }
}


/*    DETERMINE ELECTION STATUS         */
$election_status = 'unavailable';
$election_start_timestamp = 0;
$election_end_timestamp = 0;

if ($election) {

    $election_start_timestamp = strtotime($election['start_time']);
    $election_end_timestamp = strtotime($election['end_time']);

    $now = time();

    if ($now < $election_start_timestamp) {
        $election_status = 'not_started';
    } elseif ($now >= $election_start_timestamp && $now < $election_end_timestamp) {
        $election_status = 'active';
    } else {
        $election_status = 'ended';
    }
}


/*    ONLY START THE 30-SECOND COUNTDOWN WHEN THE ELECTION IS ACTIVE      */
$allow_countdown = ($election_status === 'active');

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Voting Instructions</title>

<style>

/*           GENERAL          */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f1f5f9;
    color: #222;
}




.header {
    background: #064579;
    color: white;
    text-align: center;
    padding: 22px 15px;
}

.header h1 {
    margin: 0;
    font-size: 22px;
    line-height: 1.4;
}

.header p {
    margin: 7px 0 0;
    font-size: 14px;
}




.container {
    width: 92%;
    max-width: 750px;
    margin: 25px auto 40px;
}




.voter-card {
    background: white;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 5px solid #064579;
}

.voter-card div {
    margin: 7px 0;
    line-height: 1.5;
}

.voter-card strong {
    color: #064579;
}




.instruction-card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.instruction-card h2 {
    text-align: center;
    color: #064579;
    margin-top: 0;
    margin-bottom: 20px;
}

.intro {
    line-height: 1.7;
    font-size: 15px;
}




.instructions {
    padding-left: 22px;
    line-height: 1.7;
}

.instructions li {
    margin-bottom: 10px;
}




.warning {
    background: #fff3cd;
    border: 1px solid #ffe69c;
    color: #664d03;
    padding: 20px;
    border-radius: 7px;
    margin-top: 20px;
    line-height: 1.6;
}




.countdown-section {
    text-align: center;
    margin-top: 30px;
    padding-top: 25px;
    border-top: 1px solid #ddd;
}

.countdown-title {
    font-size: 16px;
    font-weight: bold;
    color: #064579;
    margin-bottom: 10px;
}

#timer {
    font-size: 65px;
    font-weight: bold;
    color: #d62828;
    line-height: 1;
    margin: 15px 0;
}

.countdown-text {
    color: #555;
    font-size: 14px;
}




.status-box {
    text-align: center;
    padding: 25px;
    margin-top: 25px;
    border-radius: 10px;
    background: #eef6ff;
    border: 1px solid #b9d8f5;
}

.status-box strong {
    display: block;
    color: #064579;
    font-size: 18px;
    margin-bottom: 8px;
}




.footer {
    text-align: center;
    color: #777;
    font-size: 12px;
    margin-top: 25px;
}




@media (max-width: 600px) {

    .header h1 {
        font-size: 18px;
    }

    .instruction-card {
        padding: 20px;
    }

    #timer {
        font-size: 55px;
    }

}

</style>

</head>


<body>




<div class="header">

    <h1>
        PLATEAU STATE TRADERS AND MARKETERS ASSOCIATION
    </h1>

    <p>
        ONLINE VOTING SYSTEM
    </p>

</div>


<div class="container">




<div class="voter-card">

    <div>
        <strong>Voter's Name:</strong><br>
        <?php
        echo htmlspecialchars(
            $voter_name,
            ENT_QUOTES,
            'UTF-8'
        );
        ?>
    </div>

    <div>
        <strong>Member ID:</strong><br>
        <?php
        echo htmlspecialchars(
            $membership_id,
            ENT_QUOTES,
            'UTF-8'
        );
        ?>
    </div>

</div>




<div class="instruction-card">

    <h2>
        PLEASE READ BEFORE VOTING
    </h2>

    <div class="intro">

        <p>
            <strong>Dear Voter,</strong>
        </p>

        <p>
            You are about to participate in the
            <strong>Plateau State Traders and Marketers
            Association Online Election.</strong>
        </p>

        <p>
            Please carefully read and understand the
            following instructions before proceeding to
            the voting page.
        </p>

    </div>


    <ol class="instructions">

        <li>
            You will have
            <strong>10 minutes</strong>
            to complete and submit your vote once the
            voting page opens.
        </li>

        <li>
            Select
            <strong>one candidate for each position</strong>
            according to your choice.
        </li>

        <li>
            Carefully review your selections before
            submitting your vote.
        </li>

        <li>
            Once your vote has been submitted,
            <strong>you cannot vote again.</strong>
        </li>

        <li>
            Do not refresh the voting page unnecessarily
            while completing your ballot.
        </li>

    </ol>


    <div class="warning">

        <strong>Important:</strong>

        Your vote should represent your personal choice.
        Do not allow another person to vote on your behalf.

    </div>


<?php if ($election_status === 'active'): ?>

    

    <div class="countdown-section">

        <div class="countdown-title">
            VOTING PAGE WILL OPEN IN
        </div>

        <div id="timer">
            5
        </div>

        <div class="countdown-text">
            Please wait. You will automatically be
            taken to the voting page when the countdown
            reaches zero.
        </div>

    </div>


<?php elseif ($election_status === 'not_started'): ?>

    <div class="status-box">

        <strong>
            ELECTION HAS NOT STARTED
        </strong>

        Please return when the scheduled election
        begins.

    </div>


<?php elseif ($election_status === 'ended'): ?>

    

    <div class="status-box">

        <strong>
            ELECTION HAS ENDED
        </strong>

        Voting is currently closed.

    </div>


<?php else: ?>

    <!-- =================================================
         NO ELECTION
    ================================================= -->

    <div class="status-box">

        <strong>
            NO ACTIVE ELECTION
        </strong>

        There is currently no active election.

    </div>

<?php endif; ?>


</div>


<div class="footer">
    Secure Online Voting System
</div>


</div>


<?php if ($allow_countdown): ?>

<script>



(function () {

    let remaining = 30;

    const timer = document.getElementById("timer");

    if (!timer) {
        return;
    }


    /*         Show 30 immediately.      */
    timer.textContent = remaining;


    /*      Start countdown.        */
    const countdown = setInterval(function () {

        remaining--;

        if (remaining <= 0) {

            clearInterval(countdown);

            timer.textContent = "GO";

            /*       Small delay makes "GO" visible before redirect.        */
            setTimeout(function () {

                window.location.href = "vote.php";

            }, 300);

            return;
        }


        timer.textContent = remaining;

    }, 1000);

})();

</script>

<?php endif; ?>


</body>

</html>
