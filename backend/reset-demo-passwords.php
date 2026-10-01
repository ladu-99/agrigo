<?php
/**
 * AgriConnect - ONE-TIME demo password reset
 *
 * Forces known passwords on the seeded demo accounts using the SAME database
 * connection the app uses, so the login page is guaranteed to accept them.
 *
 * HOW TO USE:
 *   1. Make sure this file sits in the folder you actually serve the app from
 *      (same copy as the rest of the project).
 *   2. Open in a browser:
 *        http://localhost/Agriconnect/backend/reset-demo-passwords.php?key=agriconnect-reset-2026
 *      (adjust the path to match how you serve the app)
 *   3. Read the report, log in with the new passwords, then DELETE this file.
 *
 * This file is a development helper. Remove it once you are done.
 */

require_once __DIR__ . '/config/database.php';

$expectedKey = 'agriconnect-reset-2026';
if (($_GET['key'] ?? '') !== $expectedKey) {
    http_response_code(403);
    echo 'Forbidden. Append ?key=' . htmlspecialchars($expectedKey) . ' to the URL to run this reset.';
    exit;
}

header('Content-Type: text/html; charset=utf-8');

// email => [plaintext password to set, label]
$accounts = [
    'admin@agriconnect.ss'        => ['admin123',   'System Administrator'],
    'james.okello@agriconnect.ss' => ['officer123', 'Extension Officer (Juba County)'],
    'mary.akuien@agriconnect.ss'  => ['officer123', 'Extension Officer (Bor County)'],
    'peter.lual@agriconnect.ss'   => ['officer123', 'Extension Officer (Yei River County)'],
    'deng.atem@gmail.com'         => ['farmer123',  'Seeded demo farmer'],
];

echo '<h2>AgriConnect demo password reset</h2>';
echo '<p>PHP ' . htmlspecialchars(PHP_VERSION) . ' &middot; bcrypt self-test: ';
$testHash = password_hash('selftest', PASSWORD_BCRYPT);
echo (password_verify('selftest', $testHash) ? 'OK' : 'FAILED');
echo '</p>';

try {
    $db = getDB();

    $sel = $db->prepare('SELECT id, email, status FROM users WHERE email = ?');
    $upd = $db->prepare('UPDATE users SET password_hash = ?, status = \'active\' WHERE email = ?');

    echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-family:sans-serif;">';
    echo '<tr style="background:#eee;"><th>Email</th><th>New password</th><th>Result</th><th>Status</th></tr>';

    foreach ($accounts as $email => [$pw, $label]) {
        $sel->execute([$email]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo '<tr><td>' . htmlspecialchars($email) . '</td><td>' . htmlspecialchars($pw)
                . '</td><td style="color:#c62828;">NOT FOUND (account does not exist)</td><td>-</td></tr>';
            continue;
        }

        $newHash = password_hash($pw, PASSWORD_BCRYPT);
        $upd->execute([$newHash, $email]);

        echo '<tr><td>' . htmlspecialchars($email) . '<br><small>' . htmlspecialchars($label) . '</small></td>'
            . '<td><code>' . htmlspecialchars($pw) . '</code></td>'
            . '<td style="color:#2e7d32;">UPDATED</td>'
            . '<td>' . htmlspecialchars($row['status']) . ' &rarr; active</td></tr>';
    }
    echo '</table>';

    echo '<p style="font-family:sans-serif;"><b>Done.</b> Now log in, then <b>delete this file</b> '
        . '(<code>backend/reset-demo-passwords.php</code>).</p>';
    echo '<p style="font-family:sans-serif;color:#c62828;"><b>Security:</b> do not leave this file on a public server.</p>';
} catch (Exception $e) {
    echo '<pre style="color:#c62828;font-family:monospace;">Error: ' . htmlspecialchars($e->getMessage()) . '</pre>';
}
