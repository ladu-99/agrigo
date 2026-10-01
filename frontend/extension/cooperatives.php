<?php
/**
 * AgriConnect - Cooperatives (Extension Officer)
 */
require_once __DIR__ . '/../../backend/check_session.php';
$officer = requireLogin(['extension_officer']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Cooperatives</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header"><h2>AgriConnect</h2><span class="subtitle">Extension Officer Portal</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><span class="nav-icon">&#9632;</span> Dashboard</a>
                <a href="resources.php"><span class="nav-icon">&#9654;</span> Resources</a>
                <a href="announcements.php"><span class="nav-icon">&#9993;</span> Announcements</a>
                <a href="farmers.php"><span class="nav-icon">&#9787;</span> Farmers</a>
                <a href="cooperatives.php" class="active"><span class="nav-icon">&#9881;</span> Cooperatives</a>
                <a href="marketplace.php"><span class="nav-icon">&#9733;</span> Marketplace</a>
                <a href="sales.php"><span class="nav-icon">&#9830;</span> Sales Records</a>
                                <a href="inquiries.php"><span class="nav-icon">&#9990;</span> Inquiries</a>
                                                <a href="reports.php"><span class="nav-icon">&#9998;</span> Reports</a>
            </nav>
            <div class="sidebar-user">
                <div class="user-name"><?php echo e($officer['name']); ?></div>
                <div class="user-role">Extension Officer</div>
            </div>
        </aside>
        <div class="main-content">
            <header class="top-header">
                <h1 class="page-title">Cooperatives</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($officer['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>
            <div class="content-area">
                <div class="page-header">
                    <h1>Assigned Cooperatives</h1>
                    <button class="btn btn-primary" onclick="openNewModal()">+ New Cooperative</button>
                </div>
                <div id="coop-container"><div class="spinner"></div></div>
            </div>
            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>

    <div class="modal-overlay" id="coop-modal">
        <div class="modal">
            <div class="modal-header">
                <h3>Create Cooperative</h3>
                <button class="modal-close" onclick="UI.toggleModal('coop-modal', false)">&times;</button>
            </div>
            <div class="modal-body">
                <form id="coop-form" onsubmit="return false;">
                    <div class="form-group">
                        <label for="coop-name">Cooperative Name *</label>
                        <input type="text" id="coop-name" class="form-control" placeholder="e.g. Nyayo Farmers Group" required>
                    </div>
                    <div class="form-group">
                        <label for="coop-location">Location *</label>
                        <select id="coop-location" class="form-control"></select>
                    </div>
                    <div class="form-group">
                        <label for="coop-leader">Leader (farmer) *</label>
                        <select id="coop-leader" class="form-control"></select>
                    </div>
                    <div class="form-group">
                        <label for="coop-members">Members</label>
                        <select id="coop-members" class="form-control" multiple size="6"></select>
                        <small class="text-muted" style="font-size:0.78rem;">Hold Ctrl / Cmd to select multiple farmers. The leader is added automatically.</small>
                    </div>
                    <div class="form-group">
                        <label for="coop-desc">Description</label>
                        <textarea id="coop-desc" class="form-control" rows="3" placeholder="Optional notes about this cooperative"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="UI.toggleModal('coop-modal', false)">Cancel</button>
                <button class="btn btn-primary" id="coop-save-btn" onclick="saveCooperative()">Create Cooperative</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="add-member-modal">
        <div class="modal">
            <div class="modal-header">
                <h3>Add Member</h3>
                <button class="modal-close" onclick="UI.toggleModal('add-member-modal', false)">&times;</button>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="margin-bottom:0.8rem;">Cooperative: <strong id="am-coop-name"></strong></p>
                <div class="form-group">
                    <label for="am-mode">Member type</label>
                    <select id="am-mode" class="form-control" onchange="toggleAmMode()">
                        <option value="existing">Registered farmer (existing account)</option>
                        <option value="new">New member &mdash; not yet registered (offline)</option>
                    </select>
                </div>
                <div class="form-group am-existing">
                    <label for="am-farmer">Farmer *</label>
                    <select id="am-farmer" class="form-control"></select>
                </div>
                <div class="am-new" style="display:none;">
                    <div class="form-group">
                        <label for="am-name">Full name *</label>
                        <input type="text" id="am-name" class="form-control" placeholder="e.g. Akol Mary">
                    </div>
                    <div class="form-group">
                        <label for="am-phone">Phone</label>
                        <input type="text" id="am-phone" class="form-control" placeholder="Optional, e.g. +2119...">
                    </div>
                </div>
                <div class="form-group">
                    <label for="am-role">Role</label>
                    <select id="am-role" class="form-control">
                        <option value="member" selected>Member</option>
                        <option value="deputy">Deputy</option>
                        <option value="treasurer">Treasurer</option>
                        <option value="secretary">Secretary</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="UI.toggleModal('add-member-modal', false)">Cancel</button>
                <button class="btn btn-primary" id="am-add-btn" onclick="addMember()">Add Member</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script>
        let farmerOptions = [];
        let coopData = [];

        async function loadCooperatives() {
            const data = await API.get('../../backend/api/extension/cooperatives.php');
            const container = document.getElementById('coop-container');

            if (!data || data.error || !data.cooperatives || data.cooperatives.length === 0) {
                coopData = [];
                container.innerHTML = '<div class="empty-state"><p>No cooperatives in your area yet. Click "New Cooperative" to create one.</p></div>';
                return;
            }
            coopData = data.cooperatives;

            let html = '';
            data.cooperatives.forEach(function(c) {
                html += '<div class="card">';
                html += '<div class="d-flex justify-between align-center">';
                html += '<h3>' + esc(c.name) + '</h3>';
                html += '<div class="d-flex gap-1 align-center"><span class="badge badge-success">' + (c.members_count || 0) + ' members</span>';
                html += '<button class="btn btn-outline btn-sm" onclick="openAddMemberModal(' + c.id + ')">+ Add Member</button></div>';
                html += '</div>';
                html += '<p>' + esc(c.description || 'No description') + '</p>';
                html += '<p class="text-muted" style="font-size:0.85rem;">Leader: ' + esc(c.leader_name) + ' | ' + esc(c.leader_phone || '') + ' | ' + esc(c.location_name || '') + '</p>';
                if (c.members && c.members.length > 0) {
                    html += '<div style="margin-top:0.8rem;"><strong>Members:</strong><div class="table-container" style="margin-top:0.3rem;"><table><thead><tr><th>Name</th><th>Role</th><th>Phone</th></tr></thead><tbody>';
                    c.members.forEach(function(m) {
                        html += '<tr><td>' + esc(m.name) + '</td><td><span class="badge badge-info">' + esc(m.role) + '</span></td><td>' + esc(m.phone || '-') + '</td></tr>';
                    });
                    html += '</tbody></table></div></div>';
                }
                html += '</div>';
            });
            container.innerHTML = html;
        }

        async function loadFormData() {
            // Locations (public API returns an open list of locations)
            try {
                const mk = await API.get('../../backend/api/public/marketplace.php?limit=500');
                const locSel = document.getElementById('coop-location');
                locSel.innerHTML = '<option value="">-- Select location --</option>';
                if (mk && mk.locations) {
                    mk.locations.forEach(function(l) {
                        locSel.innerHTML += '<option value="' + l.id + '">' + esc(l.name) + '</option>';
                    });
                }
            } catch (e) { /* ignore */ }

            // Farmers assigned to this officer
            const data = await API.get('../../backend/api/extension/farmers.php');
            farmerOptions = [];
            const leaderSel = document.getElementById('coop-leader');
            const memberSel = document.getElementById('coop-members');
            leaderSel.innerHTML = '<option value="">-- Select leader --</option>';
            memberSel.innerHTML = '';
            if (data && data.farmers) {
                data.farmers.forEach(function(f) {
                    if (!f.user_id) return;
                    farmerOptions.push({ id: f.user_id, name: f.name });
                    leaderSel.innerHTML += '<option value="' + f.user_id + '">' + esc(f.name) + (f.phone ? ' (' + esc(f.phone) + ')' : '') + '</option>';
                    memberSel.innerHTML += '<option value="' + f.user_id + '">' + esc(f.name) + '</option>';
                });
            }
        }

        function openNewModal() {
            document.getElementById('coop-form').reset();
            UI.toggleModal('coop-modal', true);
        }

        async function saveCooperative() {
            const name = document.getElementById('coop-name').value.trim();
            const locationId = document.getElementById('coop-location').value;
            const leaderId = document.getElementById('coop-leader').value;
            const desc = document.getElementById('coop-desc').value.trim();
            const memberIds = Array.from(document.getElementById('coop-members').selectedOptions).map(o => parseInt(o.value));

            if (!name) { UI.showAlert('Cooperative name is required', 'error'); return; }
            if (!locationId) { UI.showAlert('Please select a location', 'error'); return; }
            if (!leaderId) { UI.showAlert('Please select a leader', 'error'); return; }

            const btn = document.getElementById('coop-save-btn');
            btn.disabled = true; btn.textContent = 'Creating...';
            try {
                const res = await API.post('../../backend/api/extension/cooperatives.php', {
                    name: name,
                    description: desc,
                    location_id: parseInt(locationId),
                    leader_id: parseInt(leaderId),
                    member_ids: memberIds
                });
                if (res && res.success) {
                    UI.toggleModal('coop-modal', false);
                    UI.showAlert('Cooperative created', 'success');
                    loadCooperatives();
                } else {
                    UI.showAlert((res && res.error) ? res.error : 'Failed to create cooperative', 'error');
                }
            } catch (e) {
                UI.showAlert('Failed to create cooperative', 'error');
            } finally {
                btn.disabled = false; btn.textContent = 'Create Cooperative';
            }
        }

        let amCoopId = null;
        function toggleAmMode() {
            const mode = document.getElementById('am-mode').value;
            document.querySelectorAll('.am-existing').forEach(el => el.style.display = mode === 'existing' ? '' : 'none');
            document.querySelectorAll('.am-new').forEach(el => el.style.display = mode === 'new' ? '' : 'none');
        }

        async function openAddMemberModal(coopId) {
            const coop = coopData.find(c => String(c.id) === String(coopId));
            if (!coop) { UI.showAlert('Cooperative not found', 'error'); return; }
            if (!farmerOptions.length) await loadFormData();
            amCoopId = coopId;
            document.getElementById('am-coop-name').textContent = coop.name;
            const existing = new Set((coop.members || []).map(m => String(m.user_id)));
            const sel = document.getElementById('am-farmer');
            sel.innerHTML = '';
            let count = 0;
            farmerOptions.forEach(function(f) {
                if (existing.has(String(f.id))) return;
                sel.innerHTML += '<option value="' + f.id + '">' + esc(f.name) + '</option>';
                count++;
            });
            // Reset fields
            document.getElementById('am-name').value = '';
            document.getElementById('am-phone').value = '';
            document.getElementById('am-role').value = 'member';
            // If there are no unregistered-eligible farmers to pick, default to manual entry
            document.getElementById('am-mode').value = count > 0 ? 'existing' : 'new';
            toggleAmMode();
            UI.toggleModal('add-member-modal', true);
        }

        async function addMember() {
            const mode = document.getElementById('am-mode').value;
            const role = document.getElementById('am-role').value;
            const payload = { action: 'add_member', cooperative_id: parseInt(amCoopId), role: role };

            if (mode === 'new') {
                const name = document.getElementById('am-name').value.trim();
                const phone = document.getElementById('am-phone').value.trim();
                if (!name) { UI.showAlert('Enter the member name', 'error'); return; }
                payload.member_name = name;
                payload.member_phone = phone;
            } else {
                const userId = document.getElementById('am-farmer').value;
                if (!userId) { UI.showAlert('Please select a farmer', 'error'); return; }
                payload.user_id = parseInt(userId);
            }

            const btn = document.getElementById('am-add-btn');
            btn.disabled = true; btn.textContent = 'Adding...';
            try {
                const res = await API.post('../../backend/api/extension/cooperatives.php', payload);
                if (res && res.success) {
                    UI.toggleModal('add-member-modal', false);
                    UI.showAlert(res.message || 'Member added', 'success');
                    loadCooperatives();
                } else {
                    UI.showAlert((res && res.error) ? res.error : 'Failed to add member', 'error');
                }
            } catch (e) {
                UI.showAlert('Failed to add member', 'error');
            } finally {
                btn.disabled = false; btn.textContent = 'Add Member';
            }
        }

        function esc(t) { if (!t) return ''; var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        loadFormData();
        loadCooperatives();
    </script>
</body>
</html>
