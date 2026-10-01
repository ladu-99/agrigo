<?php
/**
 * AgriConnect - Success Stories Management (Admin)
 */
require_once __DIR__ . '/../../backend/check_session.php';
$admin = requireLogin(['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Success Stories</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header"><h2>AgriConnect</h2><span class="subtitle">Admin Panel</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><span class="nav-icon">&#9632;</span> Dashboard</a>
                <a href="extension-officers.php"><span class="nav-icon">&#9733;</span> Extension Officers</a>
                <a href="users.php"><span class="nav-icon">&#9787;</span> Users</a>
                <a href="locations.php"><span class="nav-icon">&#9873;</span> Locations</a>
                <a href="opportunities.php"><span class="nav-icon">&#9998;</span> Opportunities</a>
                <a href="success-stories.php" class="active"><span class="nav-icon">&#10004;</span> Success Stories</a>
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
                <h1 class="page-title">Success Stories</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($admin['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="page-header">
                    <h1>Success Stories</h1>
                    <div class="d-flex gap-1">
                        <select id="filter-status" class="form-control" style="width:auto;" onchange="loadStories()">
                            <option value="">All Stories</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>

                <div id="stories-list"><div class="spinner"></div></div>
            </div>

            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        async function loadStories() {
            const status = document.getElementById('filter-status').value;
            const url = '../../backend/api/admin/success-stories.php' + (status ? '?status=' + status : '');
            const data = await API.get(url);
            const container = document.getElementById('stories-list');

            if (!data || data.error || !data.stories || data.stories.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No stories found.</p></div>';
                return;
            }

            let html = '';
            data.stories.forEach(function(story) {
                const statusClass = story.status === 'approved' ? 'badge-success' : (story.status === 'pending' ? 'badge-warning' : 'badge-danger');
                html += '<div class="card">';
                html += '<div class="d-flex justify-between align-center">';
                html += '<div>';
                html += '<h3>' + esc(story.title) + '</h3>';
                html += '<p class="text-muted" style="font-size:0.85rem;">By ' + esc(story.farmer_name) + ' | ' + esc(story.location_name || 'N/A') + ' | ' + UI.formatDate(story.created_at) + '</p>';
                html += '</div>';
                html += '<span class="badge ' + statusClass + '">' + esc(story.status) + '</span>';
                html += '</div>';
                html += '<p style="margin-top:0.5rem;">' + esc(story.content.substring(0, 300)) + (story.content.length > 300 ? '...' : '') + '</p>';
                if (story.status === 'pending') {
                    html += '<div class="d-flex gap-1 mt-1">';
                    html += '<button class="btn btn-success btn-sm" onclick="reviewStory(' + story.id + ', \'approve\')">Approve</button>';
                    html += '<button class="btn btn-danger btn-sm" onclick="reviewStory(' + story.id + ', \'reject\')">Reject</button>';
                    html += '</div>';
                }
                html += '</div>';
            });

            container.innerHTML = html;
        }

        async function reviewStory(id, action) {
            const data = await API.request('../../backend/api/admin/success-stories.php', {
                method: 'PUT',
                body: JSON.stringify({ id: id, action: action })
            });

            if (data && data.success) {
                UI.showAlert('Story ' + (action === 'approve' ? 'approved' : 'rejected') + '.', 'success');
                loadStories();
            } else {
                UI.showAlert(data.error || 'Failed.', 'danger');
            }
        }

        function esc(text) {
            if (!text) return '';
            var d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }

        loadStories();
    </script>
</body>
</html>
