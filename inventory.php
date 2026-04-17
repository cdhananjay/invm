<?php
/**
 * Master Inventory Page
 * List, filter, search, and manage all inventory items
 */

require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$userId = getCurrentUserId();
$conn = getDbConnection();

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    switch ($_GET['action']) {
        case 'get_items':
            $search = sanitize($_GET['search'] ?? '');
            $category = sanitize($_GET['category'] ?? '');
            $stockStatus = sanitize($_GET['stock_status'] ?? '');
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = 10;
            $offset = ($page - 1) * $limit;

            $where = ["i.user_id = ?"];
            $params = [$userId];
            $types = "i";

            if ($search) {
                $where[] = "(i.name LIKE ? OR i.sku_id LIKE ?)";
                $searchParam = "%{$search}%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $types .= "ss";
            }

            if ($category && $category !== 'all') {
                $where[] = "i.category = ?";
                $params[] = $category;
                $types .= "s";
            }

            if ($stockStatus === 'low') {
                $where[] = "i.current_stock < i.threshold";
            } elseif ($stockStatus === 'sufficient') {
                $where[] = "i.current_stock >= i.threshold";
            }

            $whereClause = implode(' AND ', $where);

            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM items i WHERE {$whereClause}";
            $stmt = $conn->prepare($countQuery);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();

            // Get items
            $itemsQuery = "SELECT i.id, i.user_id, i.sku_id, i.name, i.description, i.image_path, i.location, i.category, i.buy_price, i.sell_price, i.current_stock, i.threshold, i.created_at FROM items i WHERE {$whereClause} ORDER BY i.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";

            $stmt = $conn->prepare($itemsQuery);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            echo json_encode([
                'success' => true,
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'totalPages' => ceil($total / $limit)
            ]);
            exit;

        case 'get_item':
            $itemId = intval($_GET['id'] ?? 0);
            $stmt = $conn->prepare("SELECT * FROM items WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $itemId, $userId);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($item) {
                echo json_encode(['success' => true, 'item' => $item]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Item not found']);
            }
            exit;

        case 'restock':
            $itemId = intval($_POST['id'] ?? 0);
            $quantity = max(1, intval($_POST['quantity'] ?? 1));

            $stmt = $conn->prepare("UPDATE items SET current_stock = current_stock + ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("iii", $quantity, $itemId, $userId);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Item restocked successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to restock item']);
            }
            $stmt->close();
            exit;

        case 'sell':
            $itemId = intval($_POST['id'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? 1);

            // Get item details
            $stmt = $conn->prepare("SELECT name, sell_price, buy_price, current_stock FROM items WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $itemId, $userId);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$item) {
                echo json_encode(['success' => false, 'error' => 'Item not found']);
                exit;
            }

            if ($item['current_stock'] < $quantity) {
                echo json_encode(['success' => false, 'error' => 'Insufficient stock. Available: ' . $item['current_stock']]);
                exit;
            }

            // Start transaction
            $conn->begin_transaction();

            try {
                // Reduce stock
                $stmt = $conn->prepare("UPDATE items SET current_stock = current_stock - ? WHERE id = ?");
                $stmt->bind_param("ii", $quantity, $itemId);
                $stmt->execute();
                $stmt->close();

                // Update user revenue and profit
                $revenue = $item['sell_price'] * $quantity;
                $profit = ($item['sell_price'] - $item['buy_price']) * $quantity;

                $stmt = $conn->prepare("UPDATE users SET total_revenue = total_revenue + ?, total_profit = total_profit + ? WHERE id = ?");
                $stmt->bind_param("ddi", $revenue, $profit, $userId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Sold ' . $quantity . ' unit(s) of ' . $item['name'],
                    'revenue' => $revenue,
                    'profit' => $profit,
                    'quantity' => $quantity
                ]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => 'Failed to process sale']);
            }
            exit;

        case 'discard':
            $itemId = intval($_POST['id'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? 1);

            // Get item details
            $stmt = $conn->prepare("SELECT name, current_stock FROM items WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $itemId, $userId);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$item) {
                echo json_encode(['success' => false, 'error' => 'Item not found']);
                exit;
            }

            if ($item['current_stock'] < $quantity) {
                echo json_encode(['success' => false, 'error' => 'Insufficient stock. Available: ' . $item['current_stock']]);
                exit;
            }

            // Reduce stock (discarding doesn't add revenue/profit)
            $stmt = $conn->prepare("UPDATE items SET current_stock = current_stock - ? WHERE id = ?");
            $stmt->bind_param("ii", $quantity, $itemId);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Discarded ' . $quantity . ' unit(s) of ' . $item['name'],
                    'quantity' => $quantity
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to discard stock']);
            }
            $stmt->close();
            exit;

        case 'delete':
            $itemId = intval($_POST['id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM items WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $itemId, $userId);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete item']);
            }
            $stmt->close();
            exit;
    }
}

// Get categories for filter
$categories = ['Accessories', 'Electronics', 'Home', 'Health', 'Beauty'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">SA</div>
                <div class="sidebar-brand">
                    <h1><?php echo APP_NAME; ?></h1>
                    <span>Inventory Hub</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <span class="icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="inventory.php" class="nav-item active">
                    <span class="icon">📦</span>
                    <span>Inventory</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <span class="icon">⚙️</span>
                    <span>Settings</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="search-bar">
                    <span class="search-icon">🔍</span>
                    <input type="text" placeholder="Search inventory..." id="searchInput">
                </div>

                <div class="header-actions">
                    <div class="user-menu" onclick="window.location.href='settings.php'">
                        <div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                            <div class="user-role"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest'); ?></div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-content">
                <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1 class="page-title">Master Inventory</h1>
                        <p class="page-subtitle">Manage and track all stock items across locations.</p>
                    </div>
                    <a href="add_item.php" class="btn btn-primary">+ Add New Item</a>
                </div>

                <!-- Filters -->
                <div class="table-container">
                    <div class="table-filters">
                        <div class="filter-group">
                            <label class="filter-label">Category</label>
                            <select class="filter-select" id="categoryFilter">
                                <option value="all">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Stock Status</label>
                            <select class="filter-select" id="stockStatusFilter">
                                <option value="all">All Statuses</option>
                                <option value="low">Low Stock</option>
                                <option value="sufficient">Sufficient Stock</option>
                            </select>
                        </div>
                    </div>

                    <!-- Table -->
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Item Name</th>
                                <th>SKU / ID</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th>Price (Buy/Sell)</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTableBody">
                            <tr>
                                <td colspan="7" class="loading">
                                    <div class="spinner"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="table-footer">
                        <div class="table-info" id="tableInfo">Showing 0 entries</div>
                        <div class="pagination" id="pagination"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Item Details Side Panel -->
    <div class="side-panel-overlay" id="sidePanelOverlay">
        <div class="side-panel">
            <div class="side-panel-header">
                <h2 class="side-panel-title">Item Details</h2>
                <button class="modal-close" onclick="closeSidePanel()">×</button>
            </div>
            <div class="side-panel-body" id="sidePanelBody">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Restock Modal -->
    <div class="modal-overlay" id="restockModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Restock Item</h3>
                <button class="modal-close" onclick="closeRestockModal()">×</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="restockItemId">
                <input type="hidden" id="restockBuyPrice">
                <div class="form-group">
                    <label for="restockQuantity">Quantity to Add</label>
                    <input type="number" id="restockQuantity" value="1" min="1" class="form-control" onchange="updateRestockCalculations()" oninput="updateRestockCalculations()">
                </div>
                <div class="restock-cost-display">
                    <div class="cost-row">
                        <span class="cost-label">Cost per Unit</span>
                        <span class="cost-value" id="restockUnitCost">₹0.00</span>
                    </div>
                    <div class="cost-row highlight">
                        <span class="cost-label">Total Cost (<span id="restockCalcQty">0</span> × ₹<span id="restockCalcPrice">0.00</span>)</span>
                        <span class="cost-value" id="restockTotalCost">₹0.00</span>
                    </div>
                </div>
                <div class="form-actions" style="justify-content: flex-end; gap: 12px;">
                    <button class="btn btn-secondary" onclick="closeRestockModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="confirmRestock()">Restock</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sell/Discard Modal -->
    <div class="modal-overlay" id="sellModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Sell or Discard Stock</h3>
                <button class="modal-close" onclick="closeSellModal()">×</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="sellItemId">
                <div class="sell-item-info" id="sellItemInfo"></div>
                <div class="form-group">
                    <label for="sellQuantity">Quantity to Sell</label>
                    <input type="number" id="sellQuantity" value="1" min="1" class="form-control" onchange="updateSellCalculations()" oninput="updateSellCalculations()">
                </div>
                <div class="sell-calculations">
                    <div class="calc-row">
                        <span class="calc-label">Available Stock</span>
                        <span class="calc-value" id="sellAvailableStock">—</span>
                    </div>
                    <div class="calc-row">
                        <span class="calc-label">Selling Price (per unit)</span>
                        <span class="calc-value" id="sellUnitPrice">₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span class="calc-label">Buying Price (per unit)</span>
                        <span class="calc-value" id="sellUnitCost">₹0.00</span>
                    </div>
                    <div class="calc-row highlight">
                        <span class="calc-label">Revenue (<span id="sellCalcQty">0</span> × ₹<span id="sellCalcPrice">0.00</span>)</span>
                        <span class="calc-value" id="sellRevenue">₹0.00</span>
                    </div>
                    <div class="calc-row highlight profit">
                        <span class="calc-label">Profit (Revenue - Cost)</span>
                        <span class="calc-value" id="sellProfit">₹0.00</span>
                    </div>
                </div>
                <div class="form-actions" style="justify-content: flex-end; gap: 12px;">
                    <button class="btn btn-danger" onclick="confirmDiscard()">Discard</button>
                    <button class="btn btn-success" onclick="confirmSell()">Sell</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script src="app.js"></script>
    <script>
        const CATEGORIES = <?php echo json_encode($categories); ?>;
        document.addEventListener('DOMContentLoaded', function() {
            loadInventoryItems();
        });
    </script>
</body>
</html>
