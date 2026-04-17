<?php
/**
 * Add New Item Page
 * Form to create new inventory items with image upload
 */

require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$userId = getCurrentUserId();
$conn = getDbConnection();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $sku = sanitize($_POST['sku'] ?? '');
    $category = sanitize($_POST['category'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $buyPrice = floatval($_POST['buy_price'] ?? 0);
    $sellPrice = floatval($_POST['sell_price'] ?? 0);
    $location = sanitize($_POST['location'] ?? '');
    $initialStock = max(0, intval($_POST['initial_stock'] ?? 0));
    $threshold = max(1, intval($_POST['threshold'] ?? 10));

    // Validation
    if (empty($name) || empty($sku) || empty($category)) {
        $error = 'Name, SKU, and category are required';
    } elseif ($buyPrice < 0 || $sellPrice < 0) {
        $error = 'Prices cannot be negative';
    } else {
        // Handle image upload
        $imagePath = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = handleFileUpload($_FILES['image']);

            if (!$uploadResult['success']) {
                $error = $uploadResult['error'];
            } else {
                $imagePath = $uploadResult['path'];
            }
        }

        if (empty($error)) {
// Insert into database
            $stmt = $conn->prepare("INSERT INTO items (user_id, sku_id, name, description, image_path, location, category, buy_price, sell_price, current_stock, threshold) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssddii", $userId, $sku, $name, $description, $imagePath, $location, $category, $buyPrice, $sellPrice, $initialStock, $threshold);

            if ($stmt->execute()) {
                header("Location: inventory.php");
                exit;
            } else {
                $error = 'Failed to add item: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

$categories = ['Accessories', 'Electronics', 'Home', 'Health', 'Beauty'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Item - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .add-item-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        .add-item-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .add-item-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
        }
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .btn-link {
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            padding: 12px 16px;
            transition: var(--transition);
        }
        .btn-link:hover {
            color: var(--text-primary);
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        .upload-area.has-preview {
            padding: 20px;
        }
        .upload-preview-container {
            position: relative;
            display: inline-block;
        }
        .upload-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: var(--radius-lg);
            object-fit: cover;
        }
        .upload-remove {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--danger-color);
            color: white;
            border: 2px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
        }
    </style>
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

            <div class="page-content">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="add-item-header">
                        <div>
                            <h1 class="add-item-title">Add New Item</h1>
                            <p class="add-item-subtitle">Enter the specifications, financials, and initial stock details for the new inventory item.</p>
                        </div>
                        <div class="header-actions">
                            <a href="inventory.php" class="btn-link">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                💾 Save Item
                            </button>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">✓ <?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <div class="analytics-section">
                        <!-- Item Specifications -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">🧩 Item Specifications</h3>
                            </div>
                            <div class="form-row">
                                <div class="form-group full-width" style="grid-column: 1 / -1;">
                                    <label for="name">Item Name</label>
                                    <input type="text" id="name" name="name" placeholder="e.g., Ergonomic Steel Chair"
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="category">Category</label>
                                    <select id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat; ?>" <?php echo (($_POST['category'] ?? '') === $cat) ? 'selected' : ''; ?>>
                                                <?php echo $cat; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="sku">SKU / Item ID</label>
                                    <input type="text" id="sku" name="sku" placeholder="e.g., FUR-CHR-001"
                                           value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>" required>
                                </div>

                                <div class="form-group full-width">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" placeholder="Brief description of the item..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Product Image -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">🖼️ Product Image</h3>
                            </div>
                            <div class="upload-area" id="uploadArea">
                                <input type="file" id="imageInput" name="image" accept="image/png,image/jpeg,image/gif,image/webp" hidden>
                                <div class="upload-icon">☁️</div>
                                <div class="upload-text">
                                    <strong>Upload a file</strong> or drag and drop
                                </div>
                                <div class="upload-hint">PNG, JPG, GIF up to 10MB</div>
                                <div class="upload-preview-container" id="previewContainer" style="display: none;">
                                    <img class="upload-preview" id="imagePreview" src="" alt="Preview">
                                    <button type="button" class="upload-remove" onclick="removeImage()">×</button>
                                </div>
                            </div>
                        </div>

                        <!-- Financials -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">💰 Financials</h3>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="buy_price">Buying Price (₹)</label>
                                    <input type="number" id="buy_price" name="buy_price" step="0.01" min="0"
                                           placeholder="₹ 0.00" value="<?php echo htmlspecialchars($_POST['buy_price'] ?? '0'); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="sell_price">Selling Price (₹)</label>
                                    <input type="number" id="sell_price" name="sell_price" step="0.01" min="0"
                                           placeholder="₹ 0.00" value="<?php echo htmlspecialchars($_POST['sell_price'] ?? '0'); ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Stock Management -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">📋 Stock Management</h3>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="location">Warehouse Location</label>
                                    <input type="text" id="location" name="location"
                                           placeholder="e.g., Warehouse A, Aisle 4, Bin B"
                                           value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="initial_stock">Initial Stock</label>
                                    <input type="number" id="initial_stock" name="initial_stock" min="0"
                                           value="<?php echo htmlspecialchars($_POST['initial_stock'] ?? '0'); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="threshold">Low Stock Alert Threshold</label>
                                    <input type="number" id="threshold" name="threshold" min="1"
                                           value="<?php echo htmlspecialchars($_POST['threshold'] ?? '10'); ?>">
                                    <div class="form-hint">Trigger 'Low Stock' warning when inventory falls below this number.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="app.js"></script>
    <script>
        // Image upload handling
        const uploadArea = document.getElementById('uploadArea');
        const imageInput = document.getElementById('imageInput');
        const previewContainer = document.getElementById('previewContainer');
        const imagePreview = document.getElementById('imagePreview');

        uploadArea.addEventListener('click', () => imageInput.click());

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                imageInput.files = files;
                handleFileSelect(files[0]);
            }
        });

        imageInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        function handleFileSelect(file) {
            if (!file.type.startsWith('image/')) {
                showToast('Please select an image file', 'error');
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.src = e.target.result;
                previewContainer.style.display = 'block';
                uploadArea.classList.add('has-preview');
                uploadArea.querySelector('.upload-icon').style.display = 'none';
                uploadArea.querySelector('.upload-text').style.display = 'none';
                uploadArea.querySelector('.upload-hint').style.display = 'none';
            };
            reader.readAsDataURL(file);
        }

        function removeImage() {
            imageInput.value = '';
            imagePreview.src = '';
            previewContainer.style.display = 'none';
            uploadArea.classList.remove('has-preview');
            uploadArea.querySelector('.upload-icon').style.display = 'block';
            uploadArea.querySelector('.upload-text').style.display = 'block';
            uploadArea.querySelector('.upload-hint').style.display = 'block';
        }
    </script>
</body>
</html>
