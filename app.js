/**
 * Inventory Management System - Main JavaScript
 * Handles chart rendering, AJAX requests, search filtering, and UI interactions
 */

// Placeholder data URI (SVG)
const PLACEHOLDER_IMAGE = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22%3E%3Crect width=%22200%22 height=%22200%22 fill=%22%23f0f0f0%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-family=%22Arial%22 font-size=%2220%22 fill=%22%23999%22%3ENo Image%3C/text%3E%3C/svg%3E';

// ============================================
// Toast Notifications
// ============================================
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;

    container.appendChild(toast);

    // Auto remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ============================================
// Donut Chart Rendering
// ============================================
const CHART_COLORS = [
    '#0d6efd', // Blue
    '#3d8bfd', // Light Blue
    '#b3d7ff', // Pale Blue
    '#6c757d', // Gray
    '#adb5bd'  // Light Gray
];

const CATEGORY_COLORS = {
    'Electronics': '#0d6efd',
    'Accessories': '#3d8bfd',
    'Home': '#b3d7ff',
    'Health': '#6c757d',
    'Beauty': '#adb5bd'
};

function renderDonutChart(categories, total) {
    const chartEl = document.getElementById('categoryChart');
    const legendEl = document.getElementById('categoryLegend');

    if (!chartEl || !legendEl) return;

    // Sort categories by stock count
    categories.sort((a, b) => b.stock_count - a.stock_count);

    // Calculate percentages and build gradient
    let currentPercent = 0;
    const gradientParts = [];
    const legendHtml = [];

    categories.forEach((cat, index) => {
        const percent = (cat.stock_count / total) * 100;
        const color = CATEGORY_COLORS[cat.category] || CHART_COLORS[index % CHART_COLORS.length];

        gradientParts.push(`${color} ${currentPercent}% ${currentPercent + percent}%`);
        currentPercent += percent;

        legendHtml.push(`
            <div class="legend-item">
                <div class="legend-item-left">
                    <div class="legend-color" style="background: ${color}"></div>
                    <span>${cat.category}</span>
                </div>
                <div class="legend-item-right">${Math.round(percent)}%</div>
            </div>
        `);
    });

    // Create conic gradient for donut chart
    const gradient = `conic-gradient(${gradientParts.join(', ')})`;
    chartEl.style.background = gradient;

    // Add inner white circle for donut effect
    chartEl.style.position = 'relative';
    const innerSize = '60%';
    chartEl.innerHTML = `
        <div style="position: absolute; top: 20%; left: 20%; right: 20%; bottom: 20%; background: white; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <div class="donut-percent">${Math.round((categories[0]?.stock_count / total) * 100) || 0}%</div>
            <div class="donut-label">${categories[0]?.category || 'N/A'}</div>
        </div>
    `;

    // Update legend
    legendEl.innerHTML = legendHtml.join('');
}

// ============================================
// Inventory Management
// ============================================
let currentPage = 1;
let currentSearch = '';
let currentCategory = 'all';
let currentStockStatus = 'all';
let currentItems = [];

async function loadInventoryItems(page = 1) {
    const tableBody = document.getElementById('inventoryTableBody');
    const tableInfo = document.getElementById('tableInfo');
    const pagination = document.getElementById('pagination');

    if (!tableBody) return;

    // Show loading state
    tableBody.innerHTML = `
        <tr>
            <td colspan="8" class="loading">
                <div class="spinner"></div>
            </td>
        </tr>
    `;

    try {
        const params = new URLSearchParams({
            action: 'get_items',
            page: page,
            search: currentSearch,
            category: currentCategory,
            stock_status: currentStockStatus
        });

        const response = await fetch(`inventory.php?${params}`);
        const data = await response.json();

        if (data.success) {
            currentPage = data.page;
            currentItems = data.items;
            renderInventoryTable(data.items, tableBody);
            updateTableInfo(data.total, data.page, tableInfo);
            renderPagination(data.totalPages, data.page, pagination);
        } else {
            showToast('Failed to load items', 'error');
        }
    } catch (error) {
        console.error('Error loading items:', error);
        showToast('Error loading items', 'error');
    }
}

function renderInventoryTable(items, tbody) {
    if (items.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8">
                    <div class="empty-state">
                        <div class="empty-state-icon">📦</div>
                        <div class="empty-state-title">No Items Found</div>
                        <div class="empty-state-desc">Try adjusting your filters or add a new item</div>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = items.map(item => {
        const stockStatus = item.current_stock < item.threshold ? 'danger' :
                           item.current_stock < item.threshold * 1.5 ? 'warning' : 'success';
        const stockLabel = item.current_stock < item.threshold ? 'Low Stock' :
                          item.current_stock < item.threshold * 1.5 ? 'Running Low' : 'In Stock';

        const imageSrc = item.image_path ? item.image_path : PLACEHOLDER_IMAGE;

        return `
            <tr onclick="openItemDetails(${item.id})" style="cursor: pointer;">
                <td>
                    <div class="table-item">
                        <img src="${imageSrc}"
                             alt="${escapeHtml(item.name)}"
                             class="table-item-img"
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22%3E%3Crect width=%22200%22 height=%22200%22 fill=%22%23f0f0f0%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-family=%22Arial%22 font-size=%2220%22 fill=%22%23999%22%3ENo Image%3C/text%3E%3C/svg%3E'">
                    </div>
                </td>
                <td>${item.name || '-'}</td>
                <td>${escapeHtml(item.sku_id)}</td>
                <td>${escapeHtml(item.category)}</td>
                <td>${escapeHtml(item.location) || '—'}</td>
                <td>
                    <div class="price-display">
                        <span class="price-sell">₹${parseFloat(item.sell_price).toFixed(2)}</span>
                        <span class="price-buy">₹${parseFloat(item.buy_price).toFixed(2)}</span>
                    </div>
                </td>
                <td>
                    <div style="font-weight: 700; color: ${item.current_stock < item.threshold ? 'var(--danger-color)' : 'var(--text-primary)'}">
                        ${item.current_stock}
                    </div>
                    <div style="font-size: 11px; color: var(--text-muted);">Min: ${item.threshold}</div>
                </td>
                <td onclick="event.stopPropagation()">
                    <div class="table-actions">
                        <button class="btn-icon success" onclick="openRestockModal(${item.id})" title="Restock">
                            ➕
                        </button>
                        <button class="btn-icon" onclick="openItemDetails(${item.id})" title="View Details">
                            👁️
                        </button>
                        <button class="btn-icon success" onclick="openSellModal(${item.id})" title="Sell/Discard">
                            🛒
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function updateTableInfo(total, page, infoEl) {
    if (!infoEl) return;

    const start = (page - 1) * 10 + 1;
    const end = Math.min(page * 10, total);

    if (total === 0) {
        infoEl.textContent = 'No items';
    } else {
        infoEl.textContent = `Showing ${start} to ${end} of ${total} entries`;
    }
}

function renderPagination(totalPages, currentPage, paginationEl) {
    if (!paginationEl) return;

    if (totalPages <= 1) {
        paginationEl.innerHTML = '';
        return;
    }

    let html = '';

    // Previous button
    html += `<button onclick="loadInventoryItems(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>‹</button>`;

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            html += `<button onclick="loadInventoryItems(${i})" class="${i === currentPage ? 'active' : ''}">${i}</button>`;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            html += `<button disabled>...</button>`;
        }
    }

    // Next button
    html += `<button onclick="loadInventoryItems(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>›</button>`;

    paginationEl.innerHTML = html;
}

// ============================================
// Item Details Side Panel
// ============================================
async function openItemDetails(itemId) {
    try {
        const response = await fetch(`inventory.php?action=get_item&id=${itemId}`);
        const data = await response.json();

        if (data.success) {
            const item = data.item;
            const panelBody = document.getElementById('sidePanelBody');
            const overlay = document.getElementById('sidePanelOverlay');

            const profit = item.sell_price - item.buy_price;
            const profitPercent = item.buy_price > 0 ? ((profit / item.buy_price) * 100) : 0;

            const imageSrc = item.image_path ? item.image_path : PLACEHOLDER_IMAGE;

            panelBody.innerHTML = `
                <div style="display: flex; gap: 16px; align-items: flex-start; margin-bottom: 16px;">
                    <img src="${imageSrc}"
                         alt="${escapeHtml(item.name)}"
                         class="side-panel-image"
                         style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px;"
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22120%22%3E%3Crect width=%22120%22 height=%22120%22 fill=%22%23f0f0f0%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-family=%22Arial%22 font-size=%2214%22 fill=%22%23999%22%3ENo Image%3C/text%3E%3C/svg%3E'">
                    <div style="flex: 1;">
                        <h2 style="font-size: 20px; margin: 0 0 8px 0;">${escapeHtml(item.name)}</h2>
                        <p style="color: var(--text-secondary); font-size: 13px; margin: 0;">
                            ${escapeHtml(item.description) || 'No description available'}
                        </p>
                    </div>
                </div>

                <div class="detail-row">
                    <span class="detail-label">SKU</span>
                    <span class="detail-value">${escapeHtml(item.sku_id)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Category</span>
                    <span class="detail-value">${escapeHtml(item.category)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Location</span>
                    <span class="detail-value">${escapeHtml(item.location) || '—'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Buy Price</span>
                    <span class="detail-value">₹${parseFloat(item.buy_price).toFixed(2)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Sell Price</span>
                    <span class="detail-value">₹${parseFloat(item.sell_price).toFixed(2)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Profit Margin</span>
                    <span class="detail-value ${profit < 0 ? 'danger' : ''}">
                        ₹${profit.toFixed(2)} (${profitPercent.toFixed(1)}%)
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Current Stock</span>
                    <span class="detail-value ${item.current_stock < item.threshold ? 'danger' : ''}">
                        ${item.current_stock} units
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Reorder Threshold</span>
                    <span class="detail-value">${item.threshold} units</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Created</span>
                    <span class="detail-value">${new Date(item.created_at).toLocaleDateString()}</span>
                </div>
                
                <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                    <button class="btn btn-danger" style="width: 100%;" onclick="confirmDeleteItem(${item.id})">
                        🗑️ Delete Item
                    </button>
                </div>
            `;

            overlay.classList.add('active');
        } else {
            showToast('Item not found', 'error');
        }
    } catch (error) {
        console.error('Error loading item details:', error);
        showToast('Error loading item details', 'error');
    }
}

function closeSidePanel() {
    document.getElementById('sidePanelOverlay').classList.remove('active');
}

// ============================================
// Restock Modal
// ============================================
let restockItemId = null;

function openRestockModal(itemId) {
    restockItemId = itemId;
    document.getElementById('restockItemId').value = itemId;
    document.getElementById('restockQuantity').value = 1;
    
    const item = currentItems.find(i => i.id === itemId);
    if (item) {
        document.getElementById('restockBuyPrice').value = item.buy_price;
    }
    
    updateRestockCalculations();
    document.getElementById('restockModal').classList.add('active');
}

function updateRestockCalculations() {
    const quantity = parseInt(document.getElementById('restockQuantity').value) || 0;
    const buyPrice = parseFloat(document.getElementById('restockBuyPrice').value) || 0;
    const totalCost = quantity * buyPrice;
    
    document.getElementById('restockUnitCost').textContent = '₹' + buyPrice.toFixed(2);
    document.getElementById('restockCalcQty').textContent = quantity;
    document.getElementById('restockCalcPrice').textContent = buyPrice.toFixed(2);
    document.getElementById('restockTotalCost').textContent = '₹' + totalCost.toFixed(2);
}

function closeRestockModal() {
    document.getElementById('restockModal').classList.remove('active');
    restockItemId = null;
}

async function confirmRestock() {
    if (!restockItemId) return;

    const quantity = parseInt(document.getElementById('restockQuantity').value);

    if (quantity < 1) {
        showToast('Quantity must be at least 1', 'error');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('id', restockItemId);
        formData.append('quantity', quantity);

        const response = await fetch('inventory.php?action=restock', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showToast(data.message, 'success');
            closeRestockModal();
            loadInventoryItems(currentPage);
        } else {
            showToast(data.error || 'Failed to restock', 'error');
        }
    } catch (error) {
        console.error('Error restocking:', error);
        showToast('Error restocking item', 'error');
    }
}

// ============================================
// Sell Item
// ============================================
let sellItemData = null;

async function openSellModal(itemId) {
    try {
        const response = await fetch(`inventory.php?action=get_item&id=${itemId}`);
        const data = await response.json();

        if (data.success) {
            sellItemData = data.item;
            document.getElementById('sellItemId').value = itemId;
            document.getElementById('sellQuantity').value = 1;
            document.getElementById('sellQuantity').max = data.item.current_stock;

            const imageSrc = data.item.image_path ? data.item.image_path : PLACEHOLDER_IMAGE;
            document.getElementById('sellItemInfo').innerHTML = `
                <img src="${imageSrc}" alt="${escapeHtml(data.item.name)}" onerror="this.src='${PLACEHOLDER_IMAGE}'">
                <div class="sell-item-details">
                    <h4>${escapeHtml(data.item.name)}</h4>
                    <p>SKU: ${escapeHtml(data.item.sku_id)} | Stock: ${data.item.current_stock}</p>
                </div>
            `;

            document.getElementById('sellModal').classList.add('active');
            updateSellCalculations();
        } else {
            showToast('Item not found', 'error');
        }
    } catch (error) {
        console.error('Error loading item:', error);
        showToast('Error loading item', 'error');
    }
}

function closeSellModal() {
    document.getElementById('sellModal').classList.remove('active');
    sellItemData = null;
}

function updateSellCalculations() {
    if (!sellItemData) return;

    const quantity = Math.min(
        Math.max(1, parseInt(document.getElementById('sellQuantity').value) || 1),
        sellItemData.current_stock
    );
    document.getElementById('sellQuantity').value = quantity;

    const sellPrice = parseFloat(sellItemData.sell_price);
    const buyPrice = parseFloat(sellItemData.buy_price);
    const revenue = quantity * sellPrice;
    const profit = quantity * (sellPrice - buyPrice);

    document.getElementById('sellAvailableStock').textContent = sellItemData.current_stock;
    document.getElementById('sellUnitPrice').textContent = `₹${sellPrice.toFixed(2)}`;
    document.getElementById('sellUnitCost').textContent = `₹${buyPrice.toFixed(2)}`;
    document.getElementById('sellCalcQty').textContent = quantity;
    document.getElementById('sellCalcPrice').textContent = sellPrice.toFixed(2);
    document.getElementById('sellRevenue').textContent = `₹${revenue.toFixed(2)}`;
    document.getElementById('sellProfit').textContent = `₹${profit.toFixed(2)}`;

    const profitEl = document.getElementById('sellProfit');
    profitEl.classList.toggle('negative', profit < 0);
}

async function confirmSell() {
    if (!sellItemData) return;

    const quantity = parseInt(document.getElementById('sellQuantity').value);

    if (quantity < 1) {
        showToast('Quantity must be at least 1', 'error');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('id', sellItemData.id);
        formData.append('quantity', quantity);

        const response = await fetch('inventory.php?action=sell', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showToast(`Sold ${quantity} unit(s)! Revenue: ₹${data.revenue.toFixed(2)}, Profit: ₹${data.profit.toFixed(2)}`, 'success');
            closeSellModal();
            loadInventoryItems(currentPage);
        } else {
            showToast(data.error || 'Failed to process sale', 'error');
        }
    } catch (error) {
        console.error('Error selling item:', error);
        showToast('Error processing sale', 'error');
    }
}

async function confirmDiscard() {
    if (!sellItemData) return;

    const quantity = parseInt(document.getElementById('sellQuantity').value);

    if (!confirm(`Discard ${quantity} unit(s)? This will remove stock without earning revenue.`)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('id', sellItemData.id);
        formData.append('quantity', quantity);

        const response = await fetch('inventory.php?action=discard', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showToast(data.message, 'success');
            closeSellModal();
            loadInventoryItems(currentPage);
        } else {
            showToast(data.error || 'Failed to discard stock', 'error');
        }
    } catch (error) {
        console.error('Error discarding item:', error);
        showToast('Error discarding stock', 'error');
    }
}

// ============================================
// Delete Item
// ============================================
async function deleteItem(itemId) {
    try {
        const formData = new FormData();
        formData.append('id', itemId);

        const response = await fetch('inventory.php?action=delete', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showToast(data.message, 'success');
            closeSidePanel();
            loadInventoryItems(currentPage);
        } else {
            showToast(data.error || 'Failed to delete item', 'error');
        }
    } catch (error) {
        console.error('Error deleting item:', error);
        showToast('Error deleting item', 'error');
    }
}

async function confirmDeleteItem(itemId) {
    if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
        await deleteItem(itemId);
    }
}

// ============================================
// Search and Filter Handlers
// ============================================
function setupSearchAndFilters() {
    // Search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            currentSearch = e.target.value.trim();
            debounceTimer = setTimeout(() => loadInventoryItems(1), 300);
        });
    }

    // Category filter
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', (e) => {
            currentCategory = e.target.value;
            loadInventoryItems(1);
        });
    }

    // Stock status filter
    const stockStatusFilter = document.getElementById('stockStatusFilter');
    if (stockStatusFilter) {
        stockStatusFilter.addEventListener('change', (e) => {
            currentStockStatus = e.target.value;
            loadInventoryItems(1);
        });
    }
}

// ============================================
// Utility Functions
// ============================================
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// Initialize on DOM Ready
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    setupSearchAndFilters();

    // Close modals on overlay click
    const sidePanelOverlay = document.getElementById('sidePanelOverlay');
    if (sidePanelOverlay) {
        sidePanelOverlay.addEventListener('click', (e) => {
            if (e.target === sidePanelOverlay) {
                closeSidePanel();
            }
        });
    }

    const restockModal = document.getElementById('restockModal');
    if (restockModal) {
        restockModal.addEventListener('click', (e) => {
            if (e.target === restockModal) {
                closeRestockModal();
            }
        });
    }

    const sellModal = document.getElementById('sellModal');
    if (sellModal) {
        sellModal.addEventListener('click', (e) => {
            if (e.target === sellModal) {
                closeSellModal();
            }
        });
    }

    // Global search (redirect to inventory)
    const globalSearch = document.getElementById('globalSearch');
    if (globalSearch) {
        globalSearch.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && e.target.value.trim()) {
                window.location.href = `inventory.php?search=${encodeURIComponent(e.target.value.trim())}`;
            }
        });
    }
});
