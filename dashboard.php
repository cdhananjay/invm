<?php
/**
 * Dashboard Page
 * Shows inventory metrics, stats, and charts
 */

require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$userId = getCurrentUserId();
$conn = getDbConnection();

// Fetch dashboard metrics
$metricsQuery = "SELECT
    COALESCE(SUM(current_stock), 0) as total_units,
    COALESCE(SUM(CASE WHEN current_stock < threshold THEN 1 ELSE 0 END), 0) as action_required,
    COALESCE(SUM(current_stock * buy_price), 0) as inventory_value
    FROM items WHERE user_id = ?";
$stmt = $conn->prepare($metricsQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$metrics = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Calculate net margin percentage
$netMargin = 0;
if ($user['total_revenue'] > 0) {
    $netMargin = ($user['total_profit'] / $user['total_revenue']) * 100;
}

// Fetch category distribution for chart
$categoryQuery = "SELECT category, SUM(current_stock) as stock_count, SUM(current_stock * buy_price) as value
    FROM items WHERE user_id = ? GROUP BY category ORDER BY stock_count DESC";
$stmt = $conn->prepare($categoryQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate total for percentage
$totalStock = array_sum(array_column($categories, 'stock_count'));

// Fetch recent low stock items
$lowStockQuery = "SELECT id, name, sku_id, current_stock, threshold, image_path
    FROM items WHERE user_id = ? AND current_stock < threshold ORDER BY current_stock ASC LIMIT 5";
$stmt = $conn->prepare($lowStockQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$lowStockItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
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
                <a href="dashboard.php" class="nav-item active">
                    <span class="icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="inventory.php" class="nav-item">
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
                <div></div>
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
                <div class="page-header">
                    <h1 class="page-title">Overview</h1>
                    <p class="page-subtitle">Real-time inventory metrics and architectural ledger.</p>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">
                            Total Units
                            <span>📦</span>
                        </div>
                        <div class="stat-value"><?php echo number_format($metrics['total_units']); ?></div>
                        <div class="stat-change">
                            <span>📈</span> Inventory count
                        </div>
                    </div>

                    <div class="stat-card danger">
                        <div class="stat-label">
                            Action Required
                            <span>⚠️</span>
                        </div>
                        <div class="stat-value"><?php echo number_format($metrics['action_required']); ?></div>
                        <div class="stat-change">Items critically low (&lt; threshold)</div>
                    </div>

                    <div class="stat-card primary">
                        <div class="stat-label">
                            YTD Revenue
                            <span>💰</span>
                        </div>
                        <div class="stat-value"><?php echo formatLargeNumber($user['total_revenue']); ?></div>
                        <div class="stat-change">
                            <span>📈</span> Total sales
                        </div>
                    </div>

                    <div class="stat-card success">
                        <div class="stat-label">
                            Total Profit
                            <span>📈</span>
                        </div>
                        <div class="stat-value"><?php echo formatLargeNumber($user['total_profit']); ?></div>
                        <div class="stat-change">
                            <span>✅</span> Net earnings
                        </div>
                    </div>

                    <div class="stat-card success">
                        <div class="stat-label">
                            Net Margin
                            <span></span>
                        </div>
                        <div class="stat-value"><?php echo number_format($netMargin, 1); ?>%</div>
                        <div class="stat-change">Operating strictly optimal</div>
                    </div>
                </div>

                <!-- Analytics Section -->
                <div class="analytics-section">
                    <!-- Category Distribution Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Asset Allocation</h3>
                            <p class="chart-subtitle">Inventory units by primary category</p>
                        </div>
                        <div class="chart-container">
                            <?php if ($totalStock > 0): ?>
                                <div class="donut-chart" id="categoryChart"
                                     data-categories='<?php echo json_encode($categories); ?>'
                                     data-total='<?php echo $totalStock; ?>'>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">📊</div>
                                    <div class="empty-state-title">No Data Yet</div>
                                    <div class="empty-state-desc">Add items to see category distribution</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($totalStock > 0): ?>
                        <div class="chart-legend" id="categoryLegend"></div>
                        <?php endif; ?>
                    </div>

                    <!-- Low Stock Alert -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Low Stock Alerts</h3>
                            <p class="chart-subtitle">Items requiring immediate attention</p>
                        </div>
                        <?php if (count($lowStockItems) > 0): ?>
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <?php foreach ($lowStockItems as $item): ?>
                                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-color); border-radius: var(--radius);">
                                        <img src="<?php echo $item['image_path'] ?: 'assets/placeholder.png'; ?>"
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             style="width: 48px; height: 48px; border-radius: var(--radius); object-fit: cover;">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: var(--text-primary);">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </div>
                                            <div style="font-size: 12px; color: var(--text-muted);">
                                                SKU: <?php echo htmlspecialchars($item['sku_id']); ?>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-weight: 700; color: var(--danger-color);">
                                                <?php echo $item['current_stock']; ?> units
                                            </div>
                                            <div style="font-size: 11px; color: var(--text-muted);">
                                                Threshold: <?php echo $item['threshold']; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">✅</div>
                                <div class="empty-state-title">All Stocked Up</div>
                                <div class="empty-state-desc">No items below threshold</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="app.js"></script>
    <script>
        // Render category chart
        document.addEventListener('DOMContentLoaded', function() {
            const chartEl = document.getElementById('categoryChart');
            if (chartEl) {
                const categories = JSON.parse(chartEl.dataset.categories);
                const total = parseInt(chartEl.dataset.total);
                renderDonutChart(categories, total);
            }
        });
    </script>
</body>
</html>
