<?php
session_start();
require_once 'db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection error. Please check db.php.");
}

/*      GET VOTER. Prefer the session membership ID. Also accept membership_id from POST, so this remains compatible with the existing vote.php.   */
$membership_id = '';

if (isset($_SESSION['membership_id']) && $_SESSION['membership_id'] !== '') {
    $membership_id = trim($_SESSION['membership_id']);
} elseif (isset($_POST['membership_id']) && $_POST['membership_id'] !== '') {
    $membership_id = trim($_POST['membership_id']);
}

if ($membership_id === '') {
    die("Invalid request: voter membership ID is missing.");
}

/*                Find voter safely         */


$stmt = $conn->prepare("
    SELECT voter_id, name, membership_id, has_voted
    FROM voters
    WHERE membership_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("s", $membership_id);
$stmt->execute();
$voter_result = $stmt->get_result();
$voter = $voter_result->fetch_assoc();
$stmt->close();

if (!$voter) {
    die("Voter not found.");
}

$voter_id = (int)$voter['voter_id'];

if ((int)$voter['has_voted'] === 1) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Already Voted</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body{font-family:Arial,sans-serif;background:#f4f6f9;margin:0;padding:30px}
            .box{max-width:500px;margin:80px auto;background:#fff;padding:35px;border-radius:12px;
                 text-align:center;box-shadow:0 4px 18px rgba(0,0,0,.15)}
            h2{color:#a00000}
            .btn{display:inline-block;background:#0066cc;color:#fff;text-decoration:none;
                 padding:12px 25px;border-radius:6px;margin-top:15px}
        </style>
    </head>
    <body>
        <div class="box">
            <h2>You Have Already Voted</h2>
            <p>Your vote has already been recorded.</p>
            <a class="btn" href="login.php">Return to Login</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/*                  READ VOTES         */


$posted_votes = isset($_POST['vote']) && is_array($_POST['vote'])
    ? $_POST['vote']
    : [];



/*           POSITION HIERARCHY             */


$position_order = [
    'President',
    'Vice President',
    'Secretary General',
    'Treasurer',
    'Financial Secretary',
    'Organizing Secretary',
    'Public Relations Officer (PRO)',
    'Public Relation Officer (PRO)',
    'Auditor'
];

/*           GET POSITIONS FROM DATABASE          */

$position_result = $conn->query("
    SELECT DISTINCT position
    FROM candidates
    WHERE status = 'Active'
");

if (!$position_result) {
    die("Unable to load election positions: " . $conn->error);
}

$positions = [];

while ($row = $position_result->fetch_assoc()) {
    $positions[] = $row['position'];
}

/* Sort using the election hierarchy */
usort($positions, function($a, $b) use ($position_order) {
    $a_index = array_search($a, $position_order, true);
    $b_index = array_search($b, $position_order, true);

    if ($a_index === false) $a_index = 999;
    if ($b_index === false) $b_index = 999;

    return $a_index <=> $b_index;
});

if (empty($positions)) {
    die("No active election positions were found.");
}

/*            START TRANSACTION          */



$conn->begin_transaction();

try {

    $insert = $conn->prepare("
        INSERT INTO votes (voter_id, candidate_id, position, vote_time)
        VALUES (?, ?, ?, NOW())
    ");

    if (!$insert) {
        throw new Exception("Unable to prepare vote insertion: " . $conn->error);
    }

    $recorded = 0;

    foreach ($positions as $position) {

        /*
        | First try the current format:
        | $_POST['vote'][$position]
        */



        $candidate_value = null;
        $found = false;

        if (array_key_exists($position, $posted_votes)) {
            $candidate_value = $posted_votes[$position];
            $found = true;
        } else {
            
        
        /*
            | Compatibility with older vote.php field names.
            */



            $field_name = preg_replace('/[^A-Za-z0-9]/', '_', $position);

            if (isset($_POST[$field_name])) {
                $candidate_value = $_POST[$field_name];
                $found = true;
            }
        }

        /*
        | Every position must have a selection.
        */


        if (!$found || $candidate_value === '') {
            throw new Exception(
                "Please select a candidate or No Interest for: " . $position
            );
        }

        /*
        | NO INTEREST is stored as NULL candidate_id.
        */


        $candidate_id = null;

        if (strtoupper(trim((string)$candidate_value)) !== 'NULL'
            && strtoupper(trim((string)$candidate_value)) !== 'NO_INTEREST'
            && trim((string)$candidate_value) !== '') {

            if (!ctype_digit((string)$candidate_value)) {
                throw new Exception("Invalid candidate selection for: " . $position);
            }

            $candidate_id = (int)$candidate_value;

            /*
            | Make sure the candidate really belongs to this position
            | and is active.
            */

            $check = $conn->prepare("
                SELECT candidate_id
                FROM candidates
                WHERE candidate_id = ?
                  AND position = ?
                  AND status = 'Active'
                LIMIT 1
            ");

            if (!$check) {
                throw new Exception("Candidate validation error: " . $conn->error);
            }

            $check->bind_param("is", $candidate_id, $position);
            $check->execute();
            $check_result = $check->get_result();
            $valid_candidate = $check_result->fetch_assoc();
            $check->close();

            if (!$valid_candidate) {
                throw new Exception(
                    "Invalid candidate selected for: " . $position
                );
            }
        }

        /*
        | Bind NULL correctly.
        | candidate_id is an integer when a candidate is selected and
        | NULL when the voter chooses No Interest.
        */


        if ($candidate_id === null) {
            $null_candidate = null;
            $insert->bind_param("iis", $voter_id, $null_candidate, $position);
        } else {
            $insert->bind_param("iis", $voter_id, $candidate_id, $position);
        }

        if (!$insert->execute()) {
            throw new Exception(
                "Unable to record vote for " . $position . ": " . $insert->error
            );
        }

        $recorded++;
    }

    $insert->close();

    /*
    | Mark voter as having voted only after every position was recorded.
    */


    $update = $conn->prepare("
        UPDATE voters
        SET has_voted = 1
        WHERE voter_id = ?
    ");

    if (!$update) {
        throw new Exception("Unable to update voter status: " . $conn->error);
    }

    $update->bind_param("i", $voter_id);

    if (!$update->execute()) {
        throw new Exception("Unable to update voter status: " . $update->error);
    }

    $update->close();

    $conn->commit();

    /*
    | Keep membership_id available for the printable voter report.
    */

    
    $_SESSION['last_voter_id'] = $voter_id;
    $_SESSION['last_membership_id'] = $membership_id;
    $_SESSION['last_voter_name'] = $voter['name'];

    /*
    | The voting session can now end.
    | We save the report information before destroying the session.
    */
    session_destroy();

} catch (Throwable $e) {

    $conn->rollback();

    die(
        "<div style='font-family:Arial;padding:30px;color:#a00000'>" .
        "<h2>Vote Not Recorded</h2>" .
        "<p>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>" .
        "<p><a href='javascript:history.back()'>Go Back</a></p>" .
        "</div>"
    );
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Vote Submitted Successfully</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{box-sizing:border-box}
        body{
            font-family:Arial,sans-serif;
            background:#f4f6f9;
            margin:0;
            padding:20px;
        }
        .container{
            max-width:600px;
            margin:80px auto;
            background:#fff;
            padding:40px 30px;
            border-radius:14px;
            text-align:center;
            box-shadow:0 4px 20px rgba(0,0,0,.15);
        }
        h1{color:#087b35;font-size:28px}
        p{font-size:17px;color:#444;line-height:1.6}
        .btn{
            display:inline-block;
            padding:13px 24px;
            margin:10px 6px;
            background:#0066cc;
            color:#fff;
            text-decoration:none;
            border-radius:6px;
        }
        .login{background:#555}
    </style>
</head>
<body>
<div class="container">
    <h1>Vote Submitted Successfully</h1>
    <p>Your vote has been recorded successfully.</p>
    <p>Thank you for participating in the election.</p>

    <a class="btn"
       href="print_voter_report.php?membership_id=<?php echo urlencode($membership_id); ?>">
        Print Voting Slip
    </a>

    <a class="btn login" href="login.php">
        Return to Login
    </a>
</div>
</body>
</html>
