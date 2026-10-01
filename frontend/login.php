<?php
/**
 * AgriConnect - Login Page (Server-Rendered)
 * GET  - Display login form
 * POST - Process login, set session, redirect to dashboard
 */

require_once __DIR__ . '/../backend/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    $redirects = [
        'admin' => 'admin/dashboard.php',
        'extension_officer' => 'extension/dashboard.php',
        'farmer' => 'farmer/dashboard.php',
    ];
    $role = $_SESSION['user_role'] ?? '';
    // Retired/unsupported roles (e.g. cooperative_leader) are cleared to avoid redirect loops
    header('Location: ' . ($redirects[$role] ?? 'logout.php'));
    exit;
}

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password.';
    } else {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT u.*, eo.id as extension_officer_id
            FROM users u
            LEFT JOIN extension_officers eo ON eo.user_id = u.id
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'Invalid email or password.';
        } elseif ($user['status'] !== 'active') {
            if ($user['status'] === 'pending') {
                $error = 'Your account is awaiting approval by your extension officer. You will be able to sign in once it has been approved.';
            } else {
                $error = 'Your account is ' . $user['status'] . '. Please contact the administrator.';
            }
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } else {
            // Redirect based on role (cooperative feature removed)
            $redirects = [
                'admin' => 'admin/dashboard.php',
                'extension_officer' => 'extension/dashboard.php',
                'farmer' => 'farmer/dashboard.php',
            ];

            if (!isset($redirects[$user['role']])) {
                $error = 'Cooperative accounts are managed by your local extension officer. Please contact the extension officer for your area to handle sales, marketing and records for your group.';
            } else {
                // Login successful - set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['extension_officer_id'] = $user['extension_officer_id'];
                $_SESSION['location_id'] = $user['location_id'];

                header('Location: ' . $redirects[$user['role']]);
                exit;
            }
        }
    }
}

// Fetch active extension officers grouped by their location for the contact directory
$officersByLocation = [];
try {
    $dbo = getDB();
    $stmtO = $dbo->query("
        SELECT l.name AS location_name, u.name AS officer_name, u.email AS officer_email, u.phone AS officer_phone
        FROM extension_officers eo
        JOIN users u ON u.id = eo.user_id AND u.status = 'active' AND u.role = 'extension_officer'
        JOIN locations l ON l.id = eo.location_id
        ORDER BY l.name ASC, u.name ASC
    ");
    foreach ($stmtO->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $officersByLocation[$row['location_name']][] = $row;
    }
} catch (Exception $e) {
    $officersByLocation = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 50%, #4caf50 100%);
            padding: 2rem;
        }
        .login-container {
            background: white;
            border-radius: 16px;
            padding: 3rem;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo h1 {
            color: #2e7d32;
            font-size: 2rem;
            margin-bottom: 0.3rem;
        }
        .login-logo p {
            color: #888;
            font-size: 0.9rem;
        }
        .login-error {
            background: #ffebee;
            color: #c62828;
            padding: 0.8rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            border-left: 4px solid #c62828;
        }
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e0e0e0;
        }
        .login-footer a {
            color: #2e7d32;
            font-weight: 500;
        }
        .officer-directory {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #f1f8e9;
            border-radius: 8px;
            border: 1px solid #dcedc8;
            font-size: 0.8rem;
        }
        .officer-directory h4 {
            margin: 0 0 0.4rem 0;
            font-size: 0.9rem;
            color: #33691e;
        }
        .officer-directory .hint {
            margin: 0 0 0.7rem 0;
            color: #666;
            line-height: 1.4;
        }
        .officer-directory select {
            width: 100%;
        }
        .officer-info {
            margin-top: 0.8rem;
            background: #fff;
            border: 1px solid #c5e1a5;
            border-radius: 6px;
            padding: 0.7rem 0.8rem;
            font-size: 0.85rem;
            color: #333;
        }
        .officer-info strong { color: #2e7d32; }
        .officer-info .oi-loc { color: #2e7d32; font-size: 0.78rem; margin-bottom: 0.25rem; }
        .officer-info a { color: #2e7d32; }
        .officer-empty { margin: 0; font-style: italic; color: #777; }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-container">
            <div class="login-logo">
                <h1>AgriConnect</h1>
                <p>South Sudan Agricultural Platform</p>
            </div>

            <?php if ($error): ?>
                <div class="login-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="Enter your email"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg" style="width:100%; justify-content:center;">
                    Sign In
                </button>
            </form>

            <div class="login-footer">
                <p style="font-size:0.85rem; color:#888;">New farmer? <a href="register.php" style="color:#2e7d32; font-weight:600;">Create an account</a> &mdash; your local extension officer will approve it.</p>
                <p style="margin-top:0.5rem;"><a href="public/index.html">Back to Website</a></p>
            </div>

            <div class="officer-directory">
                <h4>Find Your Extension Officer</h4>
                <p class="hint">New farmers and cooperatives are onboarded and managed by the extension officer for their area. Choose your location to see who to contact.</p>
                <?php if (empty($officersByLocation)): ?>
                    <p class="officer-empty">No extension officers are listed yet. Please contact an administrator.</p>
                <?php else: ?>
                    <select id="officerSelect" class="form-control" onchange="showOfficer()">
                        <option value="">-- Select your location / officer --</option>
                        <?php foreach ($officersByLocation as $locationName => $officers): ?>
                            <optgroup label="<?php echo htmlspecialchars($locationName); ?>">
                                <?php foreach ($officers as $o): ?>
                                    <option value="<?php echo htmlspecialchars($o['officer_email']); ?>"
                                            data-name="<?php echo htmlspecialchars($o['officer_name']); ?>"
                                            data-email="<?php echo htmlspecialchars($o['officer_email']); ?>"
                                            data-phone="<?php echo htmlspecialchars($o['officer_phone'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($o['officer_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <div id="officerInfo" class="officer-info" style="display:none;"></div>
                <?php endif; ?>
            </div>

            <script>
                function escapeHtml(s) {
                    return String(s).replace(/[&<>"']/g, function (c) {
                        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                    });
                }
                function showOfficer() {
                    var sel = document.getElementById('officerSelect');
                    var info = document.getElementById('officerInfo');
                    if (!sel || !info) return;
                    var opt = sel.options[sel.selectedIndex];
                    if (!opt || !opt.value) { info.style.display = 'none'; info.innerHTML = ''; return; }
                    var name = opt.getAttribute('data-name') || '';
                    var email = opt.getAttribute('data-email') || '';
                    var phone = opt.getAttribute('data-phone') || '';
                    var loc = (opt.parentNode && opt.parentNode.label) ? opt.parentNode.label : '';
                    var html = '<strong>' + escapeHtml(name) + '</strong>';
                    if (loc) html += '<div class="oi-loc">' + escapeHtml(loc) + '</div>';
                    if (email) html += '<div>&#9993; <a href="mailto:' + escapeHtml(email) + '">' + escapeHtml(email) + '</a></div>';
                    if (phone) html += '<div>&#9742; ' + escapeHtml(phone) + '</div>';
                    info.innerHTML = html;
                    info.style.display = 'block';
                }
            </script>
        </div>
    </div>
</body>
</html>
