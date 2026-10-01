<?php
/**
 * AgriConnect - Extension Officer Marketplace (PHP Session Check)
 * Interactive features (add product, record sale) use AJAX via API.
 */
require_once __DIR__ . '/../../backend/check_session.php';
$officer = requireLogin(['extension_officer']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect - Marketplace</title>
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
                <a href="cooperatives.php"><span class="nav-icon">&#9881;</span> Cooperatives</a>
                <a href="marketplace.php" class="active"><span class="nav-icon">&#9733;</span> Marketplace</a>
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
                <h1 class="page-title">Marketplace</h1>
                <div class="header-actions">
                    <span style="font-weight:500; font-size:0.9rem;"><?php echo e($officer['name']); ?></span>
                    <a href="../logout.php" class="btn btn-outline btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="page-header">
                    <h1>Agricultural Products Marketplace</h1>
                    <button class="btn btn-primary" onclick="openAddModal()">+ Add Product</button>
                </div>
                <div id="listings-container"><div class="spinner"></div></div>
            </div>

            <footer class="app-footer"><p>&copy; 2026 AgriConnect - South Sudan Agricultural Platform</p></footer>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal-overlay" id="product-modal">
        <div class="modal" style="max-width:650px;">
            <div class="modal-header">
                <h3 id="modal-title">Add Agricultural Product</h3>
                <button class="modal-close" onclick="UI.toggleModal('product-modal', false)">&times;</button>
            </div>
            <div class="modal-body">
                <form id="product-form" enctype="multipart/form-data">
                    <input type="hidden" id="product-id">
                    <div class="form-group">
                        <label for="product-name">Product Name *</label>
                        <input type="text" id="product-name" class="form-control" placeholder="e.g., Maize Grain, Fresh Tomatoes" required>
                    </div>
                    <div class="form-group">
                        <label for="product-description">Description</label>
                        <textarea id="product-description" class="form-control" rows="2" placeholder="Brief description..."></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="product-quantity">Quantity *</label>
                            <input type="number" id="product-quantity" class="form-control" placeholder="e.g., 500" min="0.01" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="product-unit">Quantity Unit</label>
                            <select id="product-unit" class="form-control">
                                <option value="kg">Kilograms (kg)</option><option value="bag">Bag</option>
                                <option value="crate">Crate</option><option value="ton">Ton</option>
                                <option value="piece">Piece</option><option value="litre">Litre</option>
                                <option value="bunch">Bunch</option><option value="sack">Sack</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="product-price">Price (SSP) *</label>
                            <input type="number" id="product-price" class="form-control" placeholder="Price in SSP" min="0.01" step="0.01" required>
                            <small class="text-muted">Amount in South Sudanese Pound (SSP)</small>
                        </div>
                        <div class="form-group">
                            <label for="product-type">Product Type</label>
                            <select id="product-type" class="form-control">
                                <option value="crop">Crop</option><option value="grain">Grain</option>
                                <option value="vegetable">Vegetable</option><option value="fruit">Fruit</option>
                                <option value="livestock">Livestock</option><option value="dairy">Dairy</option>
                                <option value="fish">Fish</option><option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="product-seller-type">Seller Type</label>
                            <select id="product-seller-type" class="form-control">
                                <option value="farmer">Farmer</option><option value="cooperative">Cooperative</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Product Photo</label>
                        <div class="file-upload" id="photo-upload-area" onclick="document.getElementById('product-photo').click()">
                            <div class="upload-icon">&#128247;</div>
                            <div class="upload-text">Click to upload product photo</div>
                            <div class="upload-hint">JPG, PNG or WebP. Max 5MB.</div>
                            <input type="file" id="product-photo" accept="image/jpeg,image/png,image/gif,image/webp" onchange="handlePhotoSelect(this)">
                        </div>
                        <div id="photo-preview" style="display:none; margin-top:0.5rem;">
                            <img id="preview-img" src="" alt="Preview" style="max-width:200px; max-height:150px; border-radius:6px; border:1px solid #e0e0e0;">
                            <button type="button" class="btn btn-sm btn-outline" onclick="removePhoto()" style="margin-top:0.3rem;">Remove Photo</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="UI.toggleModal('product-modal', false)">Cancel</button>
                <button class="btn btn-primary" onclick="saveProduct()">Save Product</button>
            </div>
        </div>
    </div>

    <!-- Record Sale Modal -->
    <div class="modal-overlay" id="sale-modal">
        <div class="modal" style="max-width:500px;">
            <div class="modal-header">
                <h3>Record Sale</h3>
                <button class="modal-close" onclick="UI.toggleModal('sale-modal', false)">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="sale-listing-id">
                <div class="card" style="background:#f8f9fa; margin-bottom:1rem;">
                    <div style="font-weight:600;" id="sale-product-name">-</div>
                    <div class="text-muted" style="font-size:0.85rem;" id="sale-product-info">-</div>
                </div>
                <div class="form-group">
                    <label for="sale-quantity">Quantity Sold *</label>
                    <input type="number" id="sale-quantity" class="form-control" placeholder="e.g., 50" min="0.01" step="0.01" required>
                    <small class="text-muted" id="sale-qty-hint"></small>
                </div>
                <div class="form-group">
                    <label for="sale-price">Sale Price per Unit (SSP) *</label>
                    <input type="number" id="sale-price" class="form-control" placeholder="Price per unit" min="0.01" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="sale-buyer">Buyer Name</label>
                    <input type="text" id="sale-buyer" class="form-control" placeholder="Optional - walk-in">
                </div>
                <div class="form-group">
                    <label for="sale-contact">Buyer Contact</label>
                    <input type="text" id="sale-contact" class="form-control" placeholder="Optional - phone or email">
                </div>
                <div class="form-group">
                    <label for="sale-date">Sale Date</label>
                    <input type="date" id="sale-date" class="form-control">
                </div>
                <div class="form-group">
                    <label for="sale-notes">Notes</label>
                    <textarea id="sale-notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                </div>
                <div class="card" style="background:#e8f5e9; margin-top:0.5rem;">
                    <strong>Estimated Total: </strong><span id="sale-total">0</span> <span>SSP</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="UI.toggleModal('sale-modal', false)">Cancel</button>
                <button class="btn btn-primary" onclick="recordSale()">Record Sale</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
    <script>
        const user = <?php echo json_encode(['id' => $officer['id'], 'name' => $officer['name'], 'role' => $officer['role'], 'extension_officer_id' => $officer['extension_officer_id']]); ?>;

        let allListings = [];

        async function loadListings() {
            const data = await API.get('../../backend/api/extension/market-listings.php');
            const container = document.getElementById('listings-container');

            if (!data || data.error || !data.listings || data.listings.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">&#9733;</div><p>No products listed yet. Click "Add Product" to list agricultural products.</p></div>';
                return;
            }

            allListings = data.listings;
            let html = '<div class="grid-3">';
            data.listings.forEach(function(item) {
                const statusClass = item.status === 'active' ? 'badge-success' : (item.status === 'sold' ? 'badge-warning' : 'badge-danger');
                const photoSrc = item.product_photo ? '../../backend/uploads/' + item.product_photo : '../assets/images/placeholder.png';

                html += '<div class="product-card">';
                html += '<img class="product-image" src="' + esc(photoSrc) + '" alt="' + esc(item.product_name) + '" onerror="this.src=\'../assets/images/placeholder.png\'">';
                html += '<div class="product-info">';
                html += '<div class="product-name">' + esc(item.product_name) + '</div>';
                html += '<div class="product-price">' + formatSSP(item.price_ssp) + ' <span class="currency">SSP</span></div>';
                html += '<div class="product-meta"><span>Qty: ' + item.quantity + ' ' + esc(item.quantity_unit) + '</span>';
                html += '<span class="badge ' + statusClass + '">' + esc(item.status) + '</span></div>';
                html += '<div class="product-meta" style="margin-top:0.3rem;">';
                html += '<span>' + esc(item.product_type) + '</span>';
                html += '<span>' + esc(item.location_name || '') + '</span></div>';
                html += '<div style="margin-top:0.5rem; display:flex; gap:0.3rem; flex-wrap:wrap;">';
                if (item.status === 'active' && item.quantity > 0) {
                    html += '<button class="btn btn-sm btn-success" onclick="openSaleModal(' + item.id + ')">Record Sale</button>';
                }
                html += '<button class="btn btn-sm btn-danger" onclick="deleteListing(' + item.id + ')">Remove</button>';
                html += '</div></div></div>';
            });
            html += '</div>';
            container.innerHTML = html;
        }

        function openAddModal() {
            document.getElementById('modal-title').textContent = 'Add Agricultural Product';
            document.getElementById('product-form').reset();
            document.getElementById('product-id').value = '';
            document.getElementById('photo-preview').style.display = 'none';
            document.getElementById('photo-upload-area').style.display = 'block';
            UI.toggleModal('product-modal', true);
        }

        function handlePhotoSelect(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.size > 5 * 1024 * 1024) { UI.showAlert('Photo must be less than 5MB.', 'warning'); input.value = ''; return; }
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-img').src = e.target.result;
                    document.getElementById('photo-preview').style.display = 'block';
                    document.getElementById('photo-upload-area').style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        }

        function removePhoto() {
            document.getElementById('product-photo').value = '';
            document.getElementById('photo-preview').style.display = 'none';
            document.getElementById('photo-upload-area').style.display = 'block';
        }

        async function saveProduct() {
            const name = document.getElementById('product-name').value.trim();
            const quantity = document.getElementById('product-quantity').value;
            const price = document.getElementById('product-price').value;
            if (!name || !quantity || !price) { UI.showAlert('Product name, quantity, and price in SSP are required.', 'warning'); return; }

            const formData = new FormData();
            formData.append('product_name', name);
            formData.append('description', document.getElementById('product-description').value.trim());
            formData.append('quantity', quantity);
            formData.append('quantity_unit', document.getElementById('product-unit').value);
            formData.append('price_ssp', price);
            formData.append('product_type', document.getElementById('product-type').value);
            formData.append('seller_type', document.getElementById('product-seller-type').value);
            const photoInput = document.getElementById('product-photo');
            if (photoInput.files && photoInput.files[0]) formData.append('product_photo', photoInput.files[0]);

            const token = Auth.getToken();
            const headers = {};
            if (token) headers['Authorization'] = 'Bearer ' + token;

            try {
                const response = await fetch('../../backend/api/extension/market-listings.php', { method: 'POST', headers: headers, body: formData });
                const data = await response.json();
                if (data.success) { UI.showAlert('Product added!', 'success'); UI.toggleModal('product-modal', false); loadListings(); }
                else { UI.showAlert(data.error || 'Failed to add product.', 'danger'); }
            } catch (error) { UI.showAlert('Network error.', 'danger'); }
        }

        function openSaleModal(listingId) {
            const item = allListings.find(function(l) { return l.id === listingId; });
            if (!item) return;
            document.getElementById('sale-listing-id').value = listingId;
            document.getElementById('sale-product-name').textContent = item.product_name;
            document.getElementById('sale-product-info').textContent = 'Available: ' + item.quantity + ' ' + item.quantity_unit + ' @ ' + formatSSP(item.price_ssp) + ' SSP/' + item.quantity_unit;
            document.getElementById('sale-quantity').value = '';
            document.getElementById('sale-quantity').max = item.quantity;
            document.getElementById('sale-qty-hint').textContent = 'Available: ' + item.quantity + ' ' + item.quantity_unit;
            document.getElementById('sale-price').value = item.price_ssp;
            document.getElementById('sale-buyer').value = '';
            document.getElementById('sale-contact').value = '';
            document.getElementById('sale-date').value = new Date().toISOString().split('T')[0];
            document.getElementById('sale-notes').value = '';
            document.getElementById('sale-total').textContent = '0';
            UI.toggleModal('sale-modal', true);
        }

        document.getElementById('sale-quantity').addEventListener('input', updateSaleTotal);
        document.getElementById('sale-price').addEventListener('input', updateSaleTotal);
        function updateSaleTotal() {
            const qty = parseFloat(document.getElementById('sale-quantity').value) || 0;
            const price = parseFloat(document.getElementById('sale-price').value) || 0;
            document.getElementById('sale-total').textContent = formatSSP(qty * price);
        }

        async function recordSale() {
            const listingId = parseInt(document.getElementById('sale-listing-id').value);
            const quantitySold = parseFloat(document.getElementById('sale-quantity').value);
            const salePrice = parseFloat(document.getElementById('sale-price').value);
            if (!quantitySold || quantitySold <= 0) { UI.showAlert('Enter the quantity sold.', 'warning'); return; }
            if (!salePrice || salePrice <= 0) { UI.showAlert('Enter the sale price per unit.', 'warning'); return; }

            const payload = {
                listing_id: listingId, quantity_sold: quantitySold, sale_price: salePrice,
                buyer_name: document.getElementById('sale-buyer').value.trim(),
                buyer_contact: document.getElementById('sale-contact').value.trim(),
                sale_date: document.getElementById('sale-date').value,
                notes: document.getElementById('sale-notes').value.trim()
            };
            const data = await API.request('../../backend/api/extension/sales.php', { method: 'POST', body: JSON.stringify(payload) });
            if (data && data.success) { UI.showAlert('Sale recorded! Total: ' + formatSSP(data.total_amount) + ' SSP', 'success'); UI.toggleModal('sale-modal', false); loadListings(); }
            else { UI.showAlert(data.error || 'Failed to record sale.', 'danger'); }
        }

        async function deleteListing(id) {
            if (!confirm('Remove this product listing?')) return;
            const data = await API.request('../../backend/api/extension/market-listings.php', { method: 'DELETE', body: JSON.stringify({ id: id }) });
            if (data && data.success) { UI.showAlert('Listing removed.', 'success'); loadListings(); }
        }

        function formatSSP(amount) { return new Intl.NumberFormat('en-SS').format(amount); }
        function esc(text) { if (!text) return ''; var d = document.createElement('div'); d.textContent = text; return d.innerHTML; }

        loadListings();
    </script>
</body>
</html>
