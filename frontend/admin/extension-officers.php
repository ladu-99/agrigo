<?php
/**
 * AgriConnect - Extension Officers Management (Admin)
 */
require_once __DIR__ . '/../../backend/check_session.php';
$admin = requireLogin(['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Extension Officers</title>
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
                <a href="extension-officers.php" class="active"><span class="nav-icon">&#9733;</span> Extension Officers</a>
                <a href="users.php"><span class="nav-icon">&#9787;</span> Users</a>
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
                <div class="d-flex align-center gap-2">
                    <h1 class="page-title">Extension Officers</h1>
                </div>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($admin['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="page-header">
                    <h1>Extension Officers</h1>
                    <button class="btn btn-primary" onclick="openAddModal()">+ Add Extension Officer</button>
                </div>

                <div id="officers-list">
                    <div class="spinner"></div>
                </div>
            </div>

            <footer class="app-footer">
                <p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p>
            </footer>
        </div>
    </div>

    <!-- Add/Edit Officer Modal -->
    <div class="modal-overlay" id="officer-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modal-title">Add Extension Officer</h3>
                <button class="modal-close" onclick="UI.toggleModal('officer-modal', false)">&times;</button>
            </div>
            <div class="modal-body">
                <form id="officer-form">
                    <input type="hidden" id="officer-id">
                    <div class="form-group">
                        <label for="officer-name">Full Name *</label>
                        <input type="text" id="officer-name" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="officer-email">Email *</label>
                            <input type="email" id="officer-email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="officer-phone">Phone</label>
                            <input type="text" id="officer-phone" class="form-control" placeholder="+211...">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="officer-location">Location (State/County) *</label>
                            <select id="officer-location" class="form-control" required>
                                <option value="">Select Location</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="officer-specialization">Specialization</label>
                            <select id="officer-specialization" class="form-control">
                                <option value="">Select Specialization</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="password-group">
                        <label for="officer-password">Password *</label>
                        <input type="password" id="officer-password" class="form-control" placeholder="Minimum 6 characters">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="UI.toggleModal('officer-modal', false)">Cancel</button>
                <button class="btn btn-primary" onclick="saveOfficer()">Save Officer</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        let locations = [];
        let specializations = [];

        async function init() {
            await Promise.all([loadOfficers(), loadLocations(), loadSpecializations()]);
        }

        async function loadOfficers() {
            const data = await API.get('../../backend/api/admin/extension-officers.php');
            const container = document.getElementById('officers-list');

            if (!data || data.error) {
                container.innerHTML = '<div class="alert alert-danger">Failed to load extension officers.</div>';
                return;
            }

            if (!data.officers || data.officers.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">&#9733;</div><p>No extension officers registered yet.</p></div>';
                return;
            }

            let html = '<div class="table-container"><table><thead><tr>';
            html += '<th>Name</th><th>Email</th><th>Phone</th><th>Location</th><th>Specialization</th><th>Status</th><th>Actions</th>';
            html += '</tr></thead><tbody>';

            data.officers.forEach(function(officer) {
                html += '<tr>';
                html += '<td>' + escapeHtml(officer.name) + '</td>';
                html += '<td>' + escapeHtml(officer.email) + '</td>';
                html += '<td>' + escapeHtml(officer.phone || '-') + '</td>';
                html += '<td>' + escapeHtml(officer.location_name || '-') + '</td>';
                html += '<td>' + escapeHtml(officer.specialization_name || '-') + '</td>';
                html += '<td><span class="badge badge-' + (officer.status === 'active' ? 'success' : 'danger') + '">' + escapeHtml(officer.status) + '</span></td>';
                html += '<td class="actions">';
                html += '<button class="btn btn-outline btn-sm" onclick="editOfficer(' + officer.id + ', \'' + escapeHtml(officer.name) + '\', \'' + escapeHtml(officer.email) + '\', \'' + escapeHtml(officer.phone || '') + '\', ' + officer.location_id + ', ' + (officer.specialization_id || 'null') + ', \'' + officer.status + '\')">Edit</button>';
                html += '<button class="btn btn-sm ' + (officer.status === 'active' ? 'btn-danger' : 'btn-success') + '" onclick="toggleStatus(' + officer.user_id + ', \'' + (officer.status === 'active' ? 'inactive' : 'active') + '\')">' + (officer.status === 'active' ? 'Deactivate' : 'Activate') + '</button>';
                html += '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        async function loadLocations() {
            const data = await API.get('../../backend/api/admin/locations.php');
            if (data && data.locations) {
                locations = data.locations;
                const select = document.getElementById('officer-location');
                locations.forEach(function(loc) {
                    const opt = document.createElement('option');
                    opt.value = loc.id;
                    opt.textContent = loc.name + ' (' + loc.type + ')';
                    select.appendChild(opt);
                });
            }
        }

        async function loadSpecializations() {
            const data = await API.get('../../backend/api/admin/specializations.php');
            if (data && data.specializations) {
                specializations = data.specializations;
                const select = document.getElementById('officer-specialization');
                specializations.forEach(function(spec) {
                    const opt = document.createElement('option');
                    opt.value = spec.id;
                    opt.textContent = spec.name;
                    select.appendChild(opt);
                });
            }
        }

        function openAddModal() {
            document.getElementById('modal-title').textContent = 'Add Extension Officer';
            document.getElementById('officer-form').reset();
            document.getElementById('officer-id').value = '';
            document.getElementById('password-group').style.display = 'block';
            UI.toggleModal('officer-modal', true);
        }

        function editOfficer(id, name, email, phone, locationId, specId, status) {
            document.getElementById('modal-title').textContent = 'Edit Extension Officer';
            document.getElementById('officer-id').value = id;
            document.getElementById('officer-name').value = name;
            document.getElementById('officer-email').value = email;
            document.getElementById('officer-phone').value = phone;
            document.getElementById('officer-location').value = locationId;
            if (specId) document.getElementById('officer-specialization').value = specId;
            document.getElementById('password-group').style.display = 'none';
            UI.toggleModal('officer-modal', true);
        }

        async function saveOfficer() {
            const id = document.getElementById('officer-id').value;
            const payload = {
                name: document.getElementById('officer-name').value.trim(),
                email: document.getElementById('officer-email').value.trim(),
                phone: document.getElementById('officer-phone').value.trim(),
                location_id: document.getElementById('officer-location').value,
                specialization_id: document.getElementById('officer-specialization').value || null
            };

            if (!id) {
                payload.password = document.getElementById('officer-password').value;
            }

            if (!payload.name || !payload.email || !payload.location_id) {
                UI.showAlert('Please fill in all required fields.', 'warning');
                return;
            }

            if (!id && (!payload.password || payload.password.length < 6)) {
                UI.showAlert('Password must be at least 6 characters.', 'warning');
                return;
            }

            let url = '../../backend/api/admin/extension-officers.php';
            const method = id ? 'PUT' : 'POST';
            if (id) payload.id = id;

            const data = await API.request(url, {
                method: method,
                body: JSON.stringify(payload)
            });

            if (data && data.success) {
                UI.showAlert(id ? 'Officer updated successfully.' : 'Officer added successfully.', 'success');
                UI.toggleModal('officer-modal', false);
                loadOfficers();
            } else {
                UI.showAlert(data.error || 'Failed to save officer.', 'danger');
            }
        }

        async function toggleStatus(userId, newStatus) {
            if (!UI.confirm('Are you sure you want to ' + (newStatus === 'active' ? 'activate' : 'deactivate') + ' this officer?')) return;

            const data = await API.request('../../backend/api/admin/extension-officers.php', {
                method: 'PUT',
                body: JSON.stringify({ user_id: userId, status: newStatus })
            });

            if (data && data.success) {
                UI.showAlert('Officer status updated.', 'success');
                loadOfficers();
            } else {
                UI.showAlert(data.error || 'Failed to update status.', 'danger');
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        init();
    </script>
</body>
</html>
