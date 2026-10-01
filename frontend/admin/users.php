<?php
/**
 * AgriConnect - Users Management (Admin)
 */
require_once __DIR__ . '/../../backend/check_session.php';
$admin = requireLogin(['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Users</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>AgriConnect</h2>
                <span class="subtitle">Admin Panel</span>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><span class="nav-icon">&#9632;</span> Dashboard</a>
                <a href="extension-officers.php"><span class="nav-icon">&#9733;</span> Extension Officers</a>
                <a href="users.php" class="active"><span class="nav-icon">&#9787;</span> Users</a>
                <a href="locations.php"><span class="nav-icon">&#9873;</span> Locations</a>
                <a href="opportunities.php"><span class="nav-icon">&#9998;</span> Opportunities</a>
                <a href="success-stories.php"><span class="nav-icon">&#10004;</span> Success Stories</a>
                                <a href="messages.php"><span class="nav-icon">&#9993;</span> Messages</a>
                                <a href="reports.php"><span class="nav-icon">&#9998;</span> Reports</a>
                                <a href="trash.php"><span class="nav-icon">&#128465;</span> Trash</a>
            </nav>
            <div class="sidebar-user">
                <div class="user-name"><?php echo e($admin['name']); ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-header">
                <h1 class="page-title">All Users</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($admin['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="page-header">
                    <h1>Users</h1>
                    <div class="d-flex gap-1">
                        <select id="filter-role" class="form-control" style="width:auto;" onchange="loadUsers()">
                            <option value="">All Roles</option>
                            <option value="farmer">Farmers</option>
                            <option value="extension_officer">Extension Officers</option>
                            <option value="cooperative_leader">Cooperative Leaders</option>
                            <option value="admin">Admins</option>
                        </select>
                        <input type="text" id="filter-search" class="form-control" style="width:200px;" placeholder="Search users..." onkeyup="loadUsers()">
                    </div>
                </div>

                <div id="users-list">
                    <div class="spinner"></div>
                </div>
            </div>

            <footer class="app-footer">
                <p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p>
            </footer>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        async function loadUsers() {
            const role = document.getElementById('filter-role').value;
            const search = document.getElementById('filter-search').value;
            const params = {};
            if (role) params.role = role;
            if (search) params.search = search;

            const data = await API.get('../../backend/api/admin/users.php?' + new URLSearchParams(params).toString());
            const container = document.getElementById('users-list');

            if (!data || data.error || !data.users || data.users.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No users found.</p></div>';
                return;
            }

            let html = '<div class="table-container"><table><thead><tr>';
            html += '<th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Location</th><th>Status</th><th>Joined</th>';
            html += '</tr></thead><tbody>';

            data.users.forEach(function(u) {
                const roleLabel = u.role.replace('_', ' ');
                const badgeClass = u.status === 'active' ? 'badge-success' : (u.status === 'suspended' ? 'badge-danger' : 'badge-warning');
                html += '<tr>';
                html += '<td>' + esc(u.name) + '</td>';
                html += '<td>' + esc(u.email) + '</td>';
                html += '<td>' + esc(u.phone || '-') + '</td>';
                html += '<td><span class="badge badge-info">' + esc(roleLabel) + '</span></td>';
                html += '<td>' + esc(u.location_name || '-') + '</td>';
                html += '<td><span class="badge ' + badgeClass + '">' + esc(u.status) + '</span></td>';
                html += '<td>' + UI.formatDate(u.created_at) + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        function esc(text) {
            if (!text) return '';
            var d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }

        loadUsers();
    </script>
</body>
</html>
