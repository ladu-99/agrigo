<?php
/**
 * AgriConnect - Farmer Registration (Server-Rendered)
 *
 * Farmers create their own account here. The account is created with status
 * 'pending' and is linked to the extension officer for the selected location.
 * The officer approves it from the Farmers page; once approved (status -> active)
 * the farmer can sign in and will receive resources and notifications.
 *
 * Pending farmers cannot log in (see login.php / check_session.php, which require
 * status = 'active'), so they naturally only start receiving resources and
 * notifications after approval.
 */

require_once __DIR__ . '/../backend/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already signed in, send them to the login page (which routes by role).
if (isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = false;
$assignedOfficerName = null;

// Default form values (repopulated on validation errors)
$old = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'location_id' => '',
    'farm_size' => '',
    'farm_size_unit' => 'acres',
    'farming_type' => '',
];

$farmSizeUnits = ['acres' => 'Acres', 'hectares' => 'Hectares', 'square_meters' => 'Square meters'];

// Locations that currently have an active extension officer (registration is routed through them)
$locations = [];
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT l.id, l.name
        FROM locations l
        WHERE EXISTS (
            SELECT 1
            FROM extension_officers eo
            JOIN users u ON u.id = eo.user_id
            WHERE eo.location_id = l.id AND u.role = 'extension_officer' AND u.status = 'active'
        )
        ORDER BY l.name ASC
    ");
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $locations = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name'] = trim($_POST['name'] ?? '');
    $old['email'] = strtolower(trim($_POST['email'] ?? ''));
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['location_id'] = (int)($_POST['location_id'] ?? 0);
    $old['farm_size'] = trim($_POST['farm_size'] ?? '');
    $old['farm_size_unit'] = $_POST['farm_size_unit'] ?? 'acres';
    $old['farming_type'] = trim($_POST['farming_type'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($old['name'] === '') {
        $errors[] = 'Please enter your full name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($old['phone'] === '') {
        $errors[] = 'Please enter your phone number.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (!array_key_exists($old['farm_size_unit'], $farmSizeUnits)) {
        $old['farm_size_unit'] = 'acres';
    }
    if ($old['farm_size'] !== '' && !is_numeric($old['farm_size'])) {
        $errors[] = 'Farm size must be a number.';
    }

    // Validate location + resolve the officer who will handle the approval
    $officer = null;
    if ($old['location_id'] <= 0) {
        $errors[] = 'Please select your location (county/area).';
    } else {
        try {
            $stmt = $db->prepare("
                SELECT eo.id AS eo_id, u.name AS eo_name
                FROM extension_officers eo
                JOIN users u ON u.id = eo.user_id
                WHERE eo.location_id = ? AND u.role = 'extension_officer' AND u.status = 'active'
                ORDER BY eo.id ASC
                LIMIT 1
            ");
            $stmt->execute([$old['location_id']]);
            $officer = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$officer) {
                $errors[] = 'No extension officer is available for the selected location. Please choose another area.';
            }
        } catch (Exception $e) {
            $errors[] = 'Unable to verify your location right now. Please try again.';
        }
    }

    // Email uniqueness
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$old['email']]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists. Try signing in instead.';
            }
        } catch (Exception $e) {
            $errors[] = 'Unable to check your details right now. Please try again.';
        }
    }

    if (empty($errors) && $officer) {
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $farmSize = ($old['farm_size'] !== '') ? (float)$old['farm_size'] : null;
            $farmingType = ($old['farming_type'] !== '') ? $old['farming_type'] : null;

            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO users (name, email, phone, password_hash, role, location_id, status)
                VALUES (?, ?, ?, ?, 'farmer', ?, 'pending')
            ");
            $stmt->execute([$old['name'], $old['email'], $old['phone'], $passwordHash, $old['location_id']]);
            $userId = $db->lastInsertId();

            $stmt = $db->prepare("
                INSERT INTO farmers (user_id, location_id, farm_size, farm_size_unit, farming_type, assigned_extension_officer_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $old['location_id'], $farmSize, $old['farm_size_unit'], $farmingType, $officer['eo_id']]);

            $db->commit();

            $success = true;
            $assignedOfficerName = $officer['eo_name'];

            // Reset form values for display
            $old = [
                'name' => '',
                'email' => '',
                'phone' => '',
                'location_id' => '',
                'farm_size' => '',
                'farm_size_unit' => 'acres',
                'farming_type' => '',
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $errors[] = 'Something went wrong while creating your account. Please try again.';
        }
    }
}

function old_val($key) {
    global $old;
    return htmlspecialchars($old[$key] ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Farmer Registration</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 50%, #4caf50 100%);
            padding: 2rem;
        }
        .auth-card {
            background: #fff;
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .auth-header { text-align: center; margin-bottom: 1.5rem; }
        .auth-header h1 { color: #2e7d32; font-size: 1.9rem; margin-bottom: 0.2rem; }
        .auth-header p { color: #888; font-size: 0.95rem; }
        .auth-error {
            background: #ffebee;
            color: #c62828;
            padding: 0.8rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.88rem;
            border-left: 4px solid #c62828;
        }
        .auth-error ul { margin: 0; padding-left: 1.1rem; }
        .auth-success {
            background: #e8f5e9;
            color: #1b5e20;
            padding: 1.2rem 1.3rem;
            border-radius: 8px;
            border-left: 4px solid #2e7d32;
            font-size: 0.92rem;
            line-height: 1.5;
        }
        .auth-success h3 { margin: 0 0 0.6rem; font-size: 1.1rem; color: #1b5e20; }
        .auth-success p { margin: 0 0 0.7rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .field-note { font-size: 0.75rem; color: #888; margin-top: 0.3rem; }
        .auth-footer { text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e0e0e0; font-size: 0.88rem; }
        .auth-footer a { color: #2e7d32; font-weight: 600; }
        @media (max-width: 480px) { .form-row { grid-template-columns: 1fr; gap: 0; } }
    </style>
</head>
<body>
    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-header">
                <h1>AgriConnect</h1>
                <p>Farmer Account Registration</p>
            </div>

            <?php if ($success): ?>
                <div class="auth-success">
                    <h3>Registration submitted</h3>
                    <p>Thank you. Your account has been created and is <strong>awaiting approval</strong> by your local extension officer<?php echo $assignedOfficerName ? ' (<strong>' . htmlspecialchars($assignedOfficerName) . '</strong>)' : ''; ?>.</p>
                    <p>Once your officer approves the registration, you will be able to sign in to access resources and notifications.</p>
                    <p><a href="login.php" class="btn btn-primary" style="display:inline-flex; text-decoration:none;">Go to Login</a></p>
                </div>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="auth-error">
                        <ul>
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (empty($locations)): ?>
                    <div class="auth-error">
                        Online registration is not available yet for your area. Please contact your extension officer or administrator to be registered.
                    </div>
                <?php else: ?>
                    <form method="POST" action="register.php" novalidate>
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Deng Akol" value="<?php echo old_val('name'); ?>" required autofocus>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" value="<?php echo old_val('email'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone" class="form-control" placeholder="e.g. +211 9..." value="<?php echo old_val('phone'); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" class="form-control" placeholder="At least 6 characters" required>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="location_id">Location / Area</label>
                            <select id="location_id" name="location_id" class="form-control" required>
                                <option value="">-- Select your location --</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?php echo (int)$loc['id']; ?>" <?php echo ((int)$old['location_id'] === (int)$loc['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loc['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-note">Your registration is sent to the extension officer for this area for approval.</p>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="farm_size">Farm Size <span style="color:#aaa;">(optional)</span></label>
                                <input type="number" step="0.01" min="0" id="farm_size" name="farm_size" class="form-control" placeholder="e.g. 2.5" value="<?php echo old_val('farm_size'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="farm_size_unit">Unit</label>
                                <select id="farm_size_unit" name="farm_size_unit" class="form-control">
                                    <?php foreach ($farmSizeUnits as $val => $label): ?>
                                        <option value="<?php echo $val; ?>" <?php echo $old['farm_size_unit'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="farming_type">Farming Type <span style="color:#aaa;">(optional)</span></label>
                            <input type="text" id="farming_type" name="farming_type" class="form-control" placeholder="e.g. Maize, vegetables, livestock" value="<?php echo old_val('farming_type'); ?>">
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="width:100%; justify-content:center; margin-top:0.5rem;">
                            Submit for Approval
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Sign in</a></p>
                <p style="margin-top:0.5rem;"><a href="public/index.html">Back to Website</a></p>
            </div>
        </div>
    </div>
</body>
</html>
