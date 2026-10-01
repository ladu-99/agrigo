<?php
/**
 * AgriConnect - Locations Management (Admin)
 */
require_once __DIR__ . '/../../backend/check_session.php';
$admin = requireLogin(['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Locations</title>
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
                <a href="locations.php" class="active"><span class="nav-icon">&#9873;</span> Locations</a>
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
                <h1 class="page-title">Locations</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($admin['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="page-header">
                    <h1>Location Management</h1>
                    <button class="btn btn-primary" onclick="openAddModal()">+ Add Location</button>
                </div>

                <div id="locations-list"><div class="spinner"></div></div>
            </div>

            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>

    <!-- Add Location Modal -->
    <div class="modal-overlay" id="location-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modal-title">Add Location</h3>
                <button class="modal-close" onclick="UI.toggleModal('location-modal', false)">&times;</button>
            </div>
            <div class="modal-body">
                <form id="location-form">
                    <input type="hidden" id="location-id">
                    <div class="form-group">
                        <label for="location-name">Location Name *</label>
                        <input type="text" id="location-name" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="location-type">Type *</label>
                            <select id="location-type" class="form-control" required>
                                <option value="state">State</option>
                                <option value="county">County</option>
                                <option value="payam">Payam</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="location-parent">Parent Location</label>
                            <select id="location-parent" class="form-control">
                                <option value="">None (Top Level)</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="UI.toggleModal('location-modal', false)">Cancel</button>
                <button class="btn btn-primary" onclick="saveLocation()">Save</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        let allLocations = [];

        async function loadLocations() {
            const data = await API.get('../../backend/api/admin/locations.php');
            const container = document.getElementById('locations-list');

            if (!data || data.error || !data.locations || data.locations.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No locations found.</p></div>';
                return;
            }

            allLocations = data.locations;

            // Populate parent dropdown
            const parentSelect = document.getElementById('location-parent');
            parentSelect.innerHTML = '<option value="">None (Top Level)</option>';
            allLocations.forEach(function(loc) {
                const opt = document.createElement('option');
                opt.value = loc.id;
                opt.textContent = loc.name + ' (' + loc.type + ')';
                parentSelect.appendChild(opt);
            });

            // Group by type
            const states = allLocations.filter(l => l.type === 'state');
            const counties = allLocations.filter(l => l.type === 'county');
            const payams = allLocations.filter(l => l.type === 'payam');

            let html = '';

            html += '<div class="card"><div class="card-header"><h3>States (' + states.length + ')</h3></div>';
            html += '<div class="table-container"><table><thead><tr><th>Name</th><th>Counties</th><th>Actions</th></tr></thead><tbody>';
            states.forEach(function(s) {
                const countyCount = counties.filter(c => c.parent_id == s.id).length;
                html += '<tr><td><strong>' + esc(s.name) + '</strong></td>';
                html += '<td>' + countyCount + ' counties</td>';
                html += '<td class="actions"><button class="btn btn-outline btn-sm" onclick="editLocation(' + s.id + ',\'' + esc(s.name) + '\',\'' + s.type + '\',' + (s.parent_id || 'null') + ')">Edit</button></td></tr>';
            });
            html += '</tbody></table></div></div>';

            if (counties.length > 0) {
                html += '<div class="card"><div class="card-header"><h3>Counties (' + counties.length + ')</h3></div>';
                html += '<div class="table-container"><table><thead><tr><th>Name</th><th>State</th><th>Payams</th><th>Actions</th></tr></thead><tbody>';
                counties.forEach(function(c) {
                    const payamCount = payams.filter(p => p.parent_id == c.id).length;
                    html += '<tr><td>' + esc(c.name) + '</td>';
                    html += '<td>' + esc(c.parent_name || '-') + '</td>';
                    html += '<td>' + payamCount + '</td>';
                    html += '<td class="actions"><button class="btn btn-outline btn-sm" onclick="editLocation(' + c.id + ',\'' + esc(c.name) + '\',\'' + c.type + '\',' + (c.parent_id || 'null') + ')">Edit</button></td></tr>';
                });
                html += '</tbody></table></div></div>';
            }

            if (payams.length > 0) {
                html += '<div class="card"><div class="card-header"><h3>Payams (' + payams.length + ')</h3></div>';
                html += '<div class="table-container"><table><thead><tr><th>Name</th><th>County</th><th>Actions</th></tr></thead><tbody>';
                payams.forEach(function(p) {
                    html += '<tr><td>' + esc(p.name) + '</td>';
                    html += '<td>' + esc(p.parent_name || '-') + '</td>';
                    html += '<td class="actions"><button class="btn btn-outline btn-sm" onclick="editLocation(' + p.id + ',\'' + esc(p.name) + '\',\'' + p.type + '\',' + (p.parent_id || 'null') + ')">Edit</button></td></tr>';
                });
                html += '</tbody></table></div></div>';
            }

            container.innerHTML = html;
        }

        function openAddModal() {
            document.getElementById('modal-title').textContent = 'Add Location';
            document.getElementById('location-form').reset();
            document.getElementById('location-id').value = '';
            UI.toggleModal('location-modal', true);
        }

        function editLocation(id, name, type, parentId) {
            document.getElementById('modal-title').textContent = 'Edit Location';
            document.getElementById('location-id').value = id;
            document.getElementById('location-name').value = name;
            document.getElementById('location-type').value = type;
            document.getElementById('location-parent').value = parentId || '';
            UI.toggleModal('location-modal', true);
        }

        async function saveLocation() {
            const id = document.getElementById('location-id').value;
            const payload = {
                name: document.getElementById('location-name').value.trim(),
                type: document.getElementById('location-type').value,
                parent_id: document.getElementById('location-parent').value || null
            };

            if (!payload.name) {
                UI.showAlert('Name is required.', 'warning');
                return;
            }

            if (id) payload.id = id;

            const method = id ? 'PUT' : 'POST';
            const data = await API.request('../../backend/api/admin/locations.php', {
                method: method,
                body: JSON.stringify(payload)
            });

            if (data && data.success) {
                UI.showAlert(id ? 'Location updated.' : 'Location created.', 'success');
                UI.toggleModal('location-modal', false);
                loadLocations();
            } else {
                UI.showAlert(data.error || 'Failed to save.', 'danger');
            }
        }

        function esc(text) {
            if (!text) return '';
            var d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }

        loadLocations();
    </script>
</body>
</html>
