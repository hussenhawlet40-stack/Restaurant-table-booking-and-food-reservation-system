<?php 
//manage menu
session_start(); 
require_once "../connection.php";

// Only Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$message = "";

// Handle Add Menu Item
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_item'])) {
    $category = trim($_POST['menu_type']);
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);

    // Handle Image Upload
    $image_name = "";
    if (!empty($_FILES['image']['name'])) {
        $upload_dir = "../uploads/menu_items/";
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

        $image_name = time() . "_" . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
    }

    $stmt = $conn->prepare("INSERT INTO menu_items (name, category, price, image) VALUES (?,?,?,?)");
    if ($stmt->execute([$name, $category, $price, $image_name])) {
        $message = "Item added successfully!";
    } else {
        $message = "Error adding item!";
    }
}

// Fetch item for editing
$edit_item = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle Update Menu Item
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_item'])) {
    $id = intval($_POST['item_id']);
    $category = trim($_POST['menu_type']);
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $old_image = $_POST['old_image'];

    $image_name = $old_image;

    // Handle new image upload
    if (!empty($_FILES['image']['name'])) {
        $upload_dir = "../uploads/menu_items/";
        $image_name = time() . "_" . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);

        // Delete old image
        if ($old_image && file_exists("../uploads/menu_items/" . $old_image)) {
            unlink("../uploads/menu_items/" . $old_image);
        }
    }

    $stmt = $conn->prepare("UPDATE menu_items SET name=?, category=?, price=?, image=? WHERE id=?");
    if ($stmt->execute([$name, $category, $price, $image_name, $id])) {
        $message = "Item updated successfully!";
        header("Location: manage_menu.php"); // Redirect to clear edit mode
        exit;
    } else {
        $message = "Error updating item!";
    }
}

// Handle Delete Menu Item
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    $stmt = $conn->prepare("SELECT image FROM menu_items WHERE id = ?");
    $stmt->execute([$delete_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item && !empty($item['image']) && file_exists("../uploads/menu_items/" . $item['image'])) {
        unlink("../uploads/menu_items/" . $item['image']);
    }

    $stmt = $conn->prepare("DELETE FROM menu_items WHERE id = ?");
    if ($stmt->execute([$delete_id])) {
        $message = "Item deleted successfully!";
    }
}

// Handle category filter
$category_filter = $_GET['category'] ?? '';
$where_clause = '';
$params = [];

if (!empty($category_filter)) {
    $where_clause = "WHERE category = ?";
    $params[] = $category_filter;
}

// Fetch all items
$sql = "SELECT * FROM menu_items $where_clause ORDER BY category, name";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Get category counts
$category_counts = $conn->query("
    SELECT category, COUNT(*) as count 
    FROM menu_items 
    GROUP BY category 
    ORDER BY category
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html>
<head>
<title>Manage Menu Items</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: Arial; 
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); 
    min-height: 100vh; 
    padding: 20px; 
}
header { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    padding: 20px; 
    border-radius: 15px; 
    margin-bottom: 30px; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
}
header h1 { 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
    font-size: 2rem; 
}
.back-btn { 
    background: linear-gradient(45deg, #4ecdc4, #44a08d); 
    color: white; 
    padding: 12px 20px; 
    text-decoration: none; 
    border-radius: 25px; 
    transition: 0.3s; 
}
.back-btn:hover { transform: translateY(-2px); }
.container { max-width: 1200px; margin: 0 auto; }
.form-section { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    padding: 30px; 
    border-radius: 15px; 
    margin-bottom: 30px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
}
.form-title { 
    text-align: center; 
    font-size: 1.5rem; 
    color: #333; 
    margin-bottom: 25px; 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
}
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
.form-group { margin-bottom: 20px; }
.form-label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; }
.form-input, .form-select { 
    width: 100%; 
    padding: 12px; 
    border: 2px solid #eee; 
    border-radius: 10px; 
    font-size: 16px; 
    transition: 0.3s; 
}
.form-input:focus, .form-select:focus { 
    outline: none; 
    border-color: #667eea; 
    box-shadow: 0 0 10px rgba(102,126,234,0.3); 
}
.btn { 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    color: white; 
    padding: 12px 25px; 
    border: none; 
    border-radius: 10px; 
    cursor: pointer; 
    font-size: 16px; 
    font-weight: 500; 
    transition: 0.3s; 
    text-decoration: none; 
    display: inline-block; 
}
.btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
.btn-delete { background: linear-gradient(45deg, #ff6b6b, #ee5a24); }
.btn-edit { background: linear-gradient(45deg, #4ecdc4, #44a08d); }
.menu-actions { 
    display: flex; 
    gap: 10px; 
    justify-content: flex-end; 
}

/* Three Dot Menu for Menu Items */
.menu-dropdown {
    position: relative;
    display: inline-block;
}

.menu-btn {
    background: transparent;
    color: #667eea;
    border: none;
    padding: 8px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.2rem;
    transition: all 0.3s ease;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.menu-btn:hover {
    color: #764ba2;
    transform: scale(1.2);
}

.menu-dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    bottom: 45px;
    background: rgba(255,255,255,0.98);
    backdrop-filter: blur(20px);
    min-width: 200px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
    border-radius: 15px;
    z-index: 1000;
    border: 1px solid rgba(102,126,234,0.2);
    overflow: hidden;
}

.menu-dropdown-content.show {
    display: block;
    animation: dropdownFadeIn 0.3s ease;
}

@keyframes dropdownFadeIn {
    from { opacity: 0; transform: translateY(10px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.dropdown-header {
    padding: 15px 20px;
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    font-weight: 600;
    text-align: center;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.menu-dropdown-item {
    display: block;
    color: #333;
    padding: 15px 20px;
    text-decoration: none;
    transition: all 0.3s ease;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.95rem;
    font-weight: 500;
}

.menu-dropdown-item:hover {
    background: rgba(102,126,234,0.1);
    color: #667eea;
    transform: translateX(-5px);
}

.menu-dropdown-item.edit-item:hover {
    background: linear-gradient(45deg, #4ecdc4, #44a08d);
    color: white;
}

.menu-dropdown-item.view-item:hover {
    background: linear-gradient(45deg, #3b82f6, #60a5fa);
    color: white;
}

.menu-dropdown-item.delete-item:hover {
    background: linear-gradient(45deg, #ff6b6b, #ee5a24);
    color: white;
}

.menu-dropdown-item:last-child {
    border-bottom: none;
}

.menu-dropdown-item i {
    width: 18px;
    text-align: center;
    font-size: 1.1rem;
}

.dropdown-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(102,126,234,0.3), transparent);
    margin: 8px 0;
}
.message { 
    padding: 15px; 
    border-radius: 10px; 
    margin-bottom: 20px; 
    text-align: center; 
    font-weight: 500; 
    background: rgba(78,205,196,0.1); 
    color: #4ecdc4; 
    border: 2px solid rgba(78,205,196,0.3); 
}
.menu-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, 380px); 
    gap: 20px; 
    justify-content: center;
}
.menu-card { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    border-radius: 15px; 
    padding: 20px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
    transition: 0.3s; 
    width: 380px;
    height: 480px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.menu-card:hover { transform: translateY(-5px); }
.menu-image { 
    width: 100%; 
    height: 200px; 
    object-fit: cover; 
    border-radius: 10px; 
    margin-bottom: 15px; 
    background: #f0f0f0; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    color: #999; 
    font-size: 3rem;
    overflow: hidden;
    border: 2px solid #e0e0e0;
    flex-shrink: 0;
}
.menu-info h3 { color: #333; margin-bottom: 10px; }
.menu-details { margin-bottom: 15px; }
.detail-item { 
    display: flex; 
    justify-content: space-between; 
    margin-bottom: 5px; 
    color: #666; 
}
.category-badge { 
    padding: 6px 14px; 
    border-radius: 25px; 
    font-size: 0.85rem; 
    font-weight: 600; 
    text-transform: uppercase; 
    letter-spacing: 0.5px; 
    display: inline-block; 
}
.category-local { background: linear-gradient(45deg, #ff9800, #f57c00); color: white; }
.category-continental { background: linear-gradient(45deg, #4caf50, #388e3c); color: white; }
.category-desserts { background: linear-gradient(45deg, #e91e63, #c2185b); color: white; }
.category-drink { background: linear-gradient(45deg, #2196f3, #1976d2); color: white; }
.category-food { background: linear-gradient(45deg, #ff9800, #f57c00); color: white; }
.price-tag { 
    font-size: 1.2rem; 
    font-weight: bold; 
    color: #4caf50; 
}
.category-filters { 
    display: flex; 
    flex-wrap: wrap; 
    gap: 15px; 
    justify-content: center; 
}
.filter-btn { 
    background: rgba(255,255,255,0.8); 
    color: #333; 
    padding: 12px 20px; 
    border-radius: 25px; 
    text-decoration: none; 
    font-weight: 500; 
    transition: all 0.3s ease; 
    border: 2px solid transparent; 
    display: flex; 
    align-items: center; 
    gap: 8px; 
}
.filter-btn:hover { 
    background: rgba(102,126,234,0.1); 
    border-color: #667eea; 
    transform: translateY(-2px); 
}
.filter-btn.active { 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    color: white; 
    box-shadow: 0 5px 15px rgba(102,126,234,0.3); 
}
.filter-btn .count { 
    background: rgba(255,255,255,0.2); 
    padding: 2px 8px; 
    border-radius: 12px; 
    font-size: 0.8rem; 
    font-weight: 600; 
}
.filter-btn.active .count { 
    background: rgba(255,255,255,0.3); 
}

@media (max-width: 768px) { 
    .form-grid { grid-template-columns: 1fr; } 
    header { flex-direction: column; text-align: center; gap: 15px; }
    .category-filters { flex-direction: column; }
    .filter-btn { justify-content: center; }
    .menu-grid { 
        grid-template-columns: 1fr; 
        gap: 20px;
    }
    .menu-card {
        width: 100%;
        max-width: 380px;
        margin: 0 auto;
    }
}
</style>
</head>
<body>
<header>
    <h1><i class="fas fa-utensils"></i> Manage Menu Items</h1>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</header>

<div class="container">
    <?php if ($message): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <!-- ADD/EDIT FORM -->
    <div class="form-section">
        <h2 class="form-title">
            <i class="fas fa-<?= $edit_item ? 'edit' : 'plus' ?>"></i>
            <?= $edit_item ? "Edit Menu Item" : "Add New Menu Item" ?>
        </h2>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="menu_type" class="form-select" required>
                        <option value="">-- Select Category --</option>
                        <optgroup label="🍽️ Food Categories">
                            <option value="Local & Cultural Dishes" <?= $edit_item && $edit_item['category'] == 'Local & Cultural Dishes' ? 'selected' : '' ?>>🏛️ Local & Cultural Dishes</option>
                            <option value="Continental & Fusion Dishes" <?= $edit_item && $edit_item['category'] == 'Continental & Fusion Dishes' ? 'selected' : '' ?>>🌍 Continental & Fusion Dishes</option>
                            <option value="Desserts" <?= $edit_item && $edit_item['category'] == 'Desserts' ? 'selected' : '' ?>>🍰 Desserts</option>
                        </optgroup>
                        <optgroup label="🥤 Beverages">
                            <option value="Drink" <?= $edit_item && $edit_item['category'] == 'Drink' ? 'selected' : '' ?>>🥤 Drinks & Beverages</option>
                        </optgroup>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Item Name</label>
                    <input type="text" name="name" class="form-input" required
                           value="<?= htmlspecialchars($edit_item['name'] ?? '') ?>"
                           placeholder="e.g., Grilled Chicken, Fresh Juice">
                </div>

                <div class="form-group">
                    <label class="form-label">Price (ETB)</label>
                    <input type="number" step="0.01" name="price" class="form-input" required min="0"
                           value="<?= $edit_item['price'] ?? '' ?>"
                           placeholder="0.00">
                </div>

                <div class="form-group">
                    <label class="form-label">Item Image</label>
                    <input type="file" name="image" class="form-input" accept="image/*">
                    <?php if ($edit_item && $edit_item['image']): ?>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            Current: <?= htmlspecialchars($edit_item['image']) ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($edit_item): ?>
                <input type="hidden" name="item_id" value="<?= $edit_item['id'] ?>">
                <input type="hidden" name="old_image" value="<?= $edit_item['image'] ?>">
            <?php endif; ?>

            <button type="submit" name="<?= $edit_item ? 'update_item' : 'add_item' ?>" class="btn">
                <i class="fas fa-<?= $edit_item ? 'save' : 'plus' ?>"></i>
                <?= $edit_item ? 'Update Menu Item' : 'Add Menu Item' ?>
            </button>
            
            <?php if ($edit_item): ?>
                <a href="manage_menu.php" class="btn" style="background: #6c757d; margin-left: 10px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- CATEGORY FILTER -->
    <div class="form-section">
        <h2 class="form-title">
            <i class="fas fa-filter"></i> Filter by Category
        </h2>
        
        <div class="category-filters">
            <a href="manage_menu.php" class="filter-btn <?= empty($category_filter) ? 'active' : '' ?>">
                <i class="fas fa-th"></i> All Categories 
                <span class="count"><?= array_sum($category_counts) ?></span>
            </a>
            
            <a href="?category=Local & Cultural Dishes" class="filter-btn <?= $category_filter === 'Local & Cultural Dishes' ? 'active' : '' ?>">
                <i class="fas fa-landmark"></i> Local & Cultural 
                <span class="count"><?= $category_counts['Local & Cultural Dishes'] ?? 0 ?></span>
            </a>
            
            <a href="?category=Continental & Fusion Dishes" class="filter-btn <?= $category_filter === 'Continental & Fusion Dishes' ? 'active' : '' ?>">
                <i class="fas fa-globe"></i> Continental & Fusion 
                <span class="count"><?= $category_counts['Continental & Fusion Dishes'] ?? 0 ?></span>
            </a>
            
            <a href="?category=Desserts" class="filter-btn <?= $category_filter === 'Desserts' ? 'active' : '' ?>">
                <i class="fas fa-birthday-cake"></i> Desserts 
                <span class="count"><?= $category_counts['Desserts'] ?? 0 ?></span>
            </a>
            
            <a href="?category=Drink" class="filter-btn <?= $category_filter === 'Drink' ? 'active' : '' ?>">
                <i class="fas fa-cocktail"></i> Drinks & Beverages 
                <span class="count"><?= $category_counts['Drink'] ?? 0 ?></span>
            </a>
        </div>
    </div>

    <!-- MENU ITEMS SECTION -->
    <div class="form-section">
        <h2 class="form-title">
            <i class="fas fa-<?php 
                if ($category_filter) {
                    $cat = strtolower($category_filter);
                    if (strpos($cat, 'local') !== false) echo 'landmark';
                    elseif (strpos($cat, 'continental') !== false) echo 'globe';
                    elseif (strpos($cat, 'dessert') !== false) echo 'birthday-cake';
                    elseif (strpos($cat, 'drink') !== false) echo 'cocktail';
                    else echo 'utensils';
                } else {
                    echo 'th';
                }
            ?>"></i>
            <?= $category_filter ? htmlspecialchars($category_filter) : 'All Menu Items' ?>
            <span style="font-size: 1rem; opacity: 0.7;">(<?= count($items) ?> items)</span>
        </h2>
        
        <div class="menu-grid">
            <?php if (empty($items)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 60px;">
                    <i class="fas fa-<?= $category_filter ? 'search' : 'utensils' ?>" style="font-size: 4rem; color: #ddd; margin-bottom: 20px;"></i>
                    <h3 style="color: #666;">
                        <?= $category_filter ? 'No items in this category' : 'No menu items found' ?>
                    </h3>
                    <p style="color: #999;">
                        <?= $category_filter ? 'Try adding items to this category or select a different category' : 'Add your first menu item using the form above' ?>
                    </p>
                </div>
            <?php else: ?>
            <?php foreach ($items as $i): ?>
                <div class="menu-card">
                    <div class="menu-image">
                        <?php if ($i['image'] && file_exists("../uploads/menu_items/".$i['image'])): ?>
                            <img src="../uploads/menu_items/<?= htmlspecialchars($i['image']) ?>" alt="Menu Item" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
                        <?php else: ?>
                            <i class="fas fa-<?php 
                                $cat = strtolower($i['category']);
                                if (strpos($cat, 'local') !== false) echo 'landmark';
                                elseif (strpos($cat, 'continental') !== false) echo 'globe';
                                elseif (strpos($cat, 'dessert') !== false) echo 'birthday-cake';
                                elseif (strpos($cat, 'drink') !== false) echo 'cocktail';
                                else echo 'utensils';
                            ?>"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="menu-info">
                        <h3><?= htmlspecialchars($i['name']) ?></h3>
                        
                        <div class="menu-details">
                            <div class="detail-item">
                                <span><i class="fas fa-tag"></i> Category:</span>
                                <span class="category-badge category-<?php 
                                    $cat = strtolower($i['category']);
                                    if (strpos($cat, 'local') !== false) echo 'local';
                                    elseif (strpos($cat, 'continental') !== false) echo 'continental';
                                    elseif (strpos($cat, 'dessert') !== false) echo 'desserts';
                                    elseif (strpos($cat, 'drink') !== false) echo 'drink';
                                    else echo 'food';
                                ?>">
                                    <?= htmlspecialchars($i['category']) ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span><i class="fas fa-coins"></i> Price:</span>
                                <span class="price-tag">ETB <?= number_format($i['price'], 2) ?></span>
                            </div>
                            <div class="detail-item">
                                <span><i class="fas fa-hashtag"></i> ID:</span>
                                <span>#<?= $i['id'] ?></span>
                            </div>
                        </div>
                        
                        <div class="menu-actions">
                            <div class="menu-dropdown">
                                <button class="menu-btn" onclick="toggleMenuDropdown(<?= $i['id'] ?>)" title="Menu Options">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="menu-dropdown-content" id="menuDropdown<?= $i['id'] ?>">
                                    <div class="dropdown-header">
                                        <i class="fas fa-cog"></i> Menu Options
                                    </div>
                                    <a href="?edit_id=<?= $i['id'] ?>" class="menu-dropdown-item edit-item">
                                        <i class="fas fa-edit"></i>
                                        Edit Item
                                    </a>
                                    <a href="#" class="menu-dropdown-item view-item" onclick="viewMenuDetails(<?= $i['id'] ?>, '<?= htmlspecialchars($i['name']) ?>', '<?= htmlspecialchars($i['category']) ?>', '<?= $i['price'] ?>')">
                                        <i class="fas fa-eye"></i>
                                        View Details
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a href="?delete_id=<?= $i['id'] ?>" class="menu-dropdown-item delete-item" 
                                       onclick="return confirmMenuDelete('<?= htmlspecialchars($i['name']) ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                        Delete Item
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Three-dot menu toggle for menu items
function toggleMenuDropdown(itemId) {
    const dropdown = document.getElementById('menuDropdown' + itemId);
    const allDropdowns = document.querySelectorAll('.menu-dropdown-content');
    
    // Close all other dropdowns
    allDropdowns.forEach(dd => {
        if (dd.id !== 'menuDropdown' + itemId) {
            dd.classList.remove('show');
        }
    });
    
    // Toggle current dropdown
    dropdown.classList.toggle('show');
}

// View menu item details function
function viewMenuDetails(id, name, category, price) {
    const details = `
🍽️ MENU ITEM DETAILS

📋 Item Information:
• Item ID: #${id}
• Name: ${name}
• Category: ${category}
• Price: ETB ${parseFloat(price).toFixed(2)}

This menu item is available for ordering and belongs to the ${category} category.
    `;
    
    alert(details);
    
    // Close the dropdown after viewing
    const dropdown = document.getElementById('menuDropdown' + id);
    dropdown.classList.remove('show');
}

// Enhanced delete confirmation for menu items
function confirmMenuDelete(itemName) {
    return confirm(`⚠️ WARNING!\n\nAre you absolutely sure you want to delete "${itemName}"?\n\nThis action cannot be undone and will permanently remove:\n• The menu item from the system\n• The item image (if any)\n• All references to this item\n\nClick OK to proceed with deletion, or Cancel to keep the item.`);
}

// Close dropdown when clicking outside
window.addEventListener('click', function(event) {
    if (!event.target.matches('.menu-btn') && !event.target.closest('.menu-btn')) {
        const dropdowns = document.querySelectorAll('.menu-dropdown-content');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
});

// Prevent accidental form resubmission
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>
</body>
</html>
