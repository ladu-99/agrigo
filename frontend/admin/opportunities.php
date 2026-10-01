<?php
/**
 * AgriConnect - Opportunities Management (Admin)
 */
require_once __DIR__ . '/../../backend/check_session.php';
$admin = requireLogin(['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Opportunities</title>
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
                <a href="opportunities.php" class="active"><span class="nav-icon">&#9998;</span> Opportunities</a>
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
                <h1 class="page-title">Opportunities</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($admin['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="page-header">
                    <h1>Opportunities (Government, NGOs, Investors)</h1>
                    <button class="btn btn-primary" onclick="openAddModal()">+ Add Opportunity</button>
                </div>

                <div id="opportunities-list"><div class="spinner"></div></div>
            </div>

            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal-overlay" id="opp-modal">
        <div class="modal" style="max-width:700px;">
            <div class="modal-header">
                <h3 id="modal-title">Add Opportunity</h3>
                <button class="modal-close" onclick="UI.toggleModal('opp-modal', false)">&times;</button>
            </div>
            <div class="modal-body">
                <form id="opp-form">
                    <input type="hidden" id="opp-id">
                    <div class="form-group">
                        <label for="opp-title">Title *</label>
                        <input type="text" id="opp-title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="opp-description">Description *</label>
                        <textarea id="opp-description" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="opp-source">Source</label>
                            <select id="opp-source" class="form-control">
                                <option value="government">Government</option>
                                <option value="ngo">NGO</option>
                                <option value="investor">Investor</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="opp-type">Type</label>
                            <select id="opp-type" class="form-control">
                                <option value="funding">Funding</option>
                                <option value="training">Training</option>
                                <option value="grant">Grant</option>
                                <option value="partnership">Partnership</option>
                                <option value="scholarship">Scholarship</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="opp-deadline">Deadline</label>
                            <input type="date" id="opp-deadline" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="opp-status">Status</label>
                            <select id="opp-status" class="form-control">
                                <option value="active">Active</option>
                                <option value="closed">Closed</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="opp-contact">Contact Information</label>
                        <textarea id="opp-contact" class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="UI.toggleModal('opp-modal', false)">Cancel</button>
                <button class="btn btn-primary" onclick="saveOpportunity()">Save</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        async function loadOpportunities() {
            const data = await API.get('../../backend/api/admin/opportunities.php');
            const container = document.getElementById('opportunities-list');

            if (!data || data.error || !data.opportunities || data.opportunities.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No opportunities posted yet.</p></div>';
                return;
            }

            let html = '<div class="table-container"><table><thead><tr>';
            html += '<th>Title</th><th>Source</th><th>Type</th><th>Deadline</th><th>Status</th><th>Actions</th>';
            html += '</tr></thead><tbody>';

            data.opportunities.forEach(function(opp) {
                const statusClass = opp.status === 'active' ? 'badge-success' : (opp.status === 'closed' ? 'badge-warning' : 'badge-danger');
                html += '<tr>';
                html += '<td><strong>' + esc(opp.title) + '</strong></td>';
                html += '<td><span class="badge badge-info">' + esc(opp.source) + '</span></td>';
                html += '<td>' + esc(opp.type) + '</td>';
                html += '<td>' + (opp.deadline ? UI.formatDate(opp.deadline) : '-') + '</td>';
                html += '<td><span class="badge ' + statusClass + '">' + esc(opp.status) + '</span></td>';
                html += '<td class="actions">';
                html += '<button class="btn btn-outline btn-sm" onclick=\'editOpp(' + JSON.stringify(opp) + ')\'>Edit</button>';
                html += '<button class="btn btn-danger btn-sm" onclick="deleteOpp(' + opp.id + ')">Delete</button>';
                html += '</td></tr>';
            });

            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        function openAddModal() {
            document.getElementById('modal-title').textContent = 'Add Opportunity';
            document.getElementById('opp-form').reset();
            document.getElementById('opp-id').value = '';
            UI.toggleModal('opp-modal', true);
        }

        function editOpp(opp) {
            document.getElementById('modal-title').textContent = 'Edit Opportunity';
            document.getElementById('opp-id').value = opp.id;
            document.getElementById('opp-title').value = opp.title;
            document.getElementById('opp-description').value = opp.description;
            document.getElementById('opp-source').value = opp.source;
            document.getElementById('opp-type').value = opp.type;
            document.getElementById('opp-deadline').value = opp.deadline || '';
            document.getElementById('opp-status').value = opp.status;
            document.getElementById('opp-contact').value = opp.contact_info || '';
            UI.toggleModal('opp-modal', true);
        }

        async function saveOpportunity() {
            const id = document.getElementById('opp-id').value;
            const payload = {
                title: document.getElementById('opp-title').value.trim(),
                description: document.getElementById('opp-description').value.trim(),
                source: document.getElementById('opp-source').value,
                type: document.getElementById('opp-type').value,
                deadline: document.getElementById('opp-deadline').value || null,
                status: document.getElementById('opp-status').value,
                contact_info: document.getElementById('opp-contact').value.trim()
            };

            if (!payload.title || !payload.description) {
                UI.showAlert('Title and description are required.', 'warning');
                return;
            }

            if (id) payload.id = parseInt(id);

            const method = id ? 'PUT' : 'POST';
            const data = await API.request('../../backend/api/admin/opportunities.php', {
                method: method,
                body: JSON.stringify(payload)
            });

            if (data && data.success) {
                UI.showAlert(id ? 'Opportunity updated.' : 'Opportunity created.', 'success');
                UI.toggleModal('opp-modal', false);
                loadOpportunities();
            } else {
                UI.showAlert(data.error || 'Failed to save.', 'danger');
            }
        }

        async function deleteOpp(id) {
            if (!confirm('Are you sure you want to delete this opportunity?')) return;
            const data = await API.request('../../backend/api/admin/opportunities.php', {
                method: 'DELETE',
                body: JSON.stringify({ id: id })
            });
            if (data && data.success) {
                UI.showAlert('Opportunity deleted.', 'success');
                loadOpportunities();
            } else {
                UI.showAlert(data.error || 'Failed to delete.', 'danger');
            }
        }

        function esc(text) {
            if (!text) return '';
            var d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }

        loadOpportunities();
    </script>
</body>
</html>
