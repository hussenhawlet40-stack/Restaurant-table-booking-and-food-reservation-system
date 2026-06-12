
<?php
//manage table
session_start();
require_once "../connection.php";

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$message = "";

// Check for session message (from redirects)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

/* ================= ADD TABLE ================= */
if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['add_table'])) {
    $label = trim($_POST['table_label']);
    $capacity = intval($_POST['capacity']);
    $location = trim($_POST['location']);
    $status = trim($_POST['status']);

    $image_name = "";
    if (!empty($_FILES['table_image']['name'])) {
        $upload_dir = "../uploads/tables/";
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

        $image_name = time() . '_' . $_FILES['table_image']['name'];
        move_uploaded_file($_FILES['table_image']['tmp_name'], $upload_dir . $image_name);
    }

    try {
        $stmt = $conn->prepare(
            "INSERT INTO restaurant_tables (table_label, capacity, location, status, image)
             VALUES (?,?,?,?,?)"
        );
        $stmt->execute([$label, $capacity, $location, $status, $image_name]);
        $_SESSION['message'] = "Table added successfully!";
        header("Location: manage_tables.php");
        exit;
    } catch (Exception $e) {
        $message = "Error adding table: " . $e->getMessage();
    }
}

/* ================= DELETE TABLE ================= */
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);

    $img = $conn->prepare("SELECT image FROM restaurant_tables WHERE id=?");
    $img->execute([$id]);
    $row = $img->fetch();

    if ($row && $row['image'] && file_exists("../uploads/tables/".$row['image'])) {
        unlink("../uploads/tables/".$row['image']);
    }

    $conn->prepare("DELETE FROM restaurant_tables WHERE id=?")->execute([$id]);
    $_SESSION['message'] = "Table deleted successfully!";
    header("Location: manage_tables.php");
    exit;
}

/* ================= FETCH TABLE FOR EDIT ================= */
$edit_table = null;
if (isset($_GET['edit_id'])) {
    $id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM restaurant_tables WHERE id=?");
    $stmt->execute([$id]);
    $edit_table = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ================= UPDATE TABLE ================= */
if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['update_table'])) {
    $id = intval($_POST['table_id']);
    $label = trim($_POST['table_label']);
    $capacity = intval($_POST['capacity']);
    $location = trim($_POST['location']);
    $status = trim($_POST['status']);
    $old_image = $_POST['old_image'];

    $image_name = $old_image;

    if (!empty($_FILES['table_image']['name'])) {
        $upload_dir = "../uploads/tables/";
        $image_name = time() . '_' . $_FILES['table_image']['name'];
        move_uploaded_file($_FILES['table_image']['tmp_name'], $upload_dir . $image_name);

        if ($old_image && file_exists("../uploads/tables/".$old_image)) {
            unlink("../uploads/tables/".$old_image);
        }
    }

    try {
        $stmt = $conn->prepare(
            "UPDATE restaurant_tables
             SET table_label=?, capacity=?, location=?, status=?, image=?
             WHERE id=?"
        );
        $stmt->execute([$label, $capacity, $location, $status, $image_name, $id]);
        $_SESSION['message'] = "Table updated successfully!";
        header("Location: manage_tables.php");
        exit;
    } catch (Exception $e) {
        $message = "Error updating table: " . $e->getMessage();
    }
}

/* ================= FETCH ALL TABLES BY LOCATION ================= */
// Get tables organized by location
$locations = ['Main Hall', 'VIP', 'Patio', 'Group'];
$tables_by_location = [];


foreach ($locations as $location) {
    try {
        $stmt = $conn->prepare("SELECT * FROM restaurant_tables WHERE location = ? ORDER BY table_label ASC");
        $stmt->execute([$location]);
        $tables_by_location[$location] = $stmt->fetchAll();
    } catch (Exception $e) {
        $tables_by_location[$location] = [];
    }
}

// Get all tables for backward compatibility
$tables = $conn->query("SELECT * FROM restaurant_tables ORDER BY location, id DESC")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
<title>Manage Tables</title>
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
.btn-edit { background: linear-gradient(45deg, #4ecdc4, #44a08d); }
.btn-delete { background: linear-gradient(45deg, #ff6b6b, #ee5a24); }
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
/* Location Tabs */
.location-tabs { 
    display: flex; 
    justify-content: center; 
    gap: 10px; 
    margin-bottom: 20px; 
    flex-wrap: wrap;
}

/* Seat Filter Tabs */
.seat-filter-tabs { 
    display: flex; 
    justify-content: center; 
    gap: 10px; 
    margin-bottom: 30px; 
    flex-wrap: wrap;
}

.seat-filter-btn { 
    padding: 10px 20px; 
    background: rgba(255,255,255,0.8); 
    color: #333; 
    border: 2px solid rgba(255,152,0,0.3); 
    border-radius: 20px; 
    cursor: pointer; 
    transition: 0.3s; 
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    font-size: 0.9rem;
}

.seat-filter-btn:hover { 
    background: rgba(255,152,0,0.1); 
    transform: translateY(-2px);
    border-color: rgba(255,152,0,0.5);
}

.seat-filter-btn.active {
    background: linear-gradient(45deg, #ff9800, #f57c00); 
    color: white; 
    border-color: #ff9800;
}

.tab-btn { 
    padding: 12px 25px; 
    background: rgba(255,255,255,0.2); 
    color: #333; 
    border: 2px solid rgba(102,126,234,0.3); 
    border-radius: 25px; 
    cursor: pointer; 
    transition: 0.3s; 
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}
.tab-btn:hover { 
    background: rgba(102,126,234,0.1); 
    transform: translateY(-2px);
    border-color: rgba(102,126,234,0.5);
}
.tab-btn.active {

background: linear-gradient(45deg, #667eea, #764ba2); 
    color: white; 
    border-color: #667eea;
}

/* Location Content */
.location-content { 
    display: none; 
}
.location-content.active { 
    display: block; 
}
.location-section { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    padding: 30px; 
    border-radius: 15px; 
    margin-bottom: 30px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
}
.location-title { 
    font-size: 1.5rem; 
    color: #333; 
    margin-bottom: 20px; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
    text-align: center;
    justify-content: center;
}
.table-count { 
    font-size: 1rem; 
    color: #666; 
    font-weight: normal; 
}
.no-tables { 
    text-align: center; 
    padding: 40px; 
    color: #666; 
}
.no-tables i { 
    font-size: 2rem; 
    margin-bottom: 10px; 
    color: #ddd; 
}



.tables-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, 500px); 
    gap: 30px; 
    justify-content: center;
}
.table-card { 
    width: 500px;
    height: 550px;
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    border-radius: 20px; 
    padding: 25px; 
    box-shadow: 0 15px 40px rgba(0,0,0,0.15); 
    transition: 0.3s; 
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
}
.table-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,0,0,0.2); }
.table-image { 
    width: 100%; 
    height: 250px; 
    object-fit: cover; 
    border-radius: 15px; 
    margin-bottom: 20px; 
    background: #f0f0f0; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    color: #999; 
    font-size: 4rem; 
    flex-shrink: 0;
    overflow: hidden;
    border: 2px solid #e0e0e0;
}
.table-info { 
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.table-info h3 { 
    color: #333; 
    margin-bottom: 15px; 
    font-size: 1.5rem;
    text-align: center;
    background: linear-gradient(45deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 600;
}
.table-details { 
    margin-bottom: 20px; 
    flex: 1;
}
.detail-item { 
    display: flex; 
    justify-content: space-between; 
    margin-bottom: 12px; 
    color: #666; 
    font-size: 1.1rem;
    align-items: center;
    padding: 8px 0;
}
.detail-label {
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}
.detail-label i {
    font-size: 1.2rem;
    color: #667eea;
}
.detail-value {
    font-weight: 600;
    color: #333;
    font-size: 1.1rem;
}



.table-actions { 
    display: flex; 
    justify-content: flex-start;
    align-items: center;
    margin-top: auto;
    padding-top: 20px;
    gap: 15px;
}

/* Status Badge */
.status-badge {
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-left: auto;
}

.status-occupied {
    background: linear-gradient(45deg, #ef4444, #f87171);
    color: white;
}

.status-reserved {
    background: linear-gradient(45deg, #f59e0b, #fbbf24);
    color: white;
}

/* Three Dot Menu for Tables */
.table-menu-dropdown {
    position: absolute;
    top: 15px;
    right: 15px;
    z-index: 10;
}

.table-menu-btn {
    background: transparent;
    color: #667eea;
    border: none;
    padding: 8px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.2rem;
    transition: all 0.3s ease;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.table-menu-btn:hover {
    color: #764ba2;
    transform: scale(1.1);
}

.table-dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    top: 45px;
    background: rgba(255,255,255,0.98);
    backdrop-filter: blur(20px);
    min-width: 200px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
    border-radius: 15px;
    z-index: 1000;
    border: 1px solid rgba(102,126,234,0.2);
    overflow: hidden;
}

.table-dropdown-content.show {
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

.table-dropdown-item {
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

.table-dropdown-item:hover {
    background: rgba(102,126,234,0.1);
    color: #667eea;
    transform: translateX(-5px);
}

.table-dropdown-item.edit-item:hover {
    background: linear-gradient(45deg, #4ecdc4, #44a08d);
    color: white;
}

.table-dropdown-item.view-item:hover {
    background: linear-gradient(45deg, #3b82f6, #60a5fa);
    color: white;
}

.table-dropdown-item.delete-item:hover {
    background: linear-gradient(45deg, #ff6b6b, #ee5a24);
    color: white;
}

.table-dropdown-item:last-child {
    border-bottom: none;
}

.table-dropdown-item i {
    width: 18px;
    text-align: center;
    font-size: 1.1rem;
}

.dropdown-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(102,126,234,0.3), transparent);
    margin: 8px 0;
}
@media (max-width: 768px) { 
    .form-grid { grid-template-columns: 1fr; } 
    .tables-grid { 
        grid-template-columns: 1fr; 
        gap: 20px;
    }
    .table-card {
        width: 100%;
        max-width: 450px;
        height: 500px;
        margin: 0 auto;
        padding: 20px;
    }
    .table-image {
        height: 200px;
    }
    .table-info h3 {
        font-size: 1.3rem;
        margin-bottom: 12px;
    }
    .detail-item {
        font-size: 1rem;
        margin-bottom: 10px;
        padding: 6px 0;
    }
    .detail-label i {
        font-size: 1.1rem;
    }
    .detail-value {
        font-size: 1rem;
    }
    .table-menu-btn {
        width: 32px;
        height: 32px;
        font-size: 1.1rem;
    }
    
    .table-dropdown-content {
        min-width: 180px;
        left: 0;
    }
    header { flex-direction: column; text-align: center; gap: 15px; }
    .location-tabs { gap: 5px; }
    .tab-btn { 
        padding: 10px 15px; 
        font-size: 0.9rem; 
    }
    .table-count {
        font-size: 0.8rem;
    }
}

@media (max-width: 480px) {
    .table-card {
        max-width: 350px;
        height: 450px;
        padding: 15px;
    }
    .table-image {
        height: 160px;
    }
    .table-info h3 {
        font-size: 1.2rem;
    }
    .detail-item {
        font-size: 0.95rem;
    }
    .table-dropdown-content {
        min-width: 160px;
    }
}
</style>
</head>

<body>
<header>
    <h1><i class="fas fa-chair"></i> Manage Tables</h1>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</header>

<div class="container">
    <?php if ($message): ?>
        <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
    <?php endif; ?>
    


    <!-- ADD/EDIT FORM -->
    <div class="form-section">
        <h2 class="form-title">
            <i class="fas fa-<?= $edit_table ? 'edit' : 'plus' ?>"></i>
            <?= $edit_table ? "Edit Table" : "Add New Table" ?>
        </h2>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Table Label</label>
                    <input type="text" name="table_label" class="form-input" required
                           value="<?= htmlspecialchars($edit_table['table_label'] ?? '') ?>" 
                           placeholder="e.g., VIP Table 1">
                </div>

                <div class="form-group">
                    <label class="form-label">Capacity (Seats)</label>
                    <input type="number" name="capacity" class="form-input" required min="1" max="20"
                           value="<?= $edit_table['capacity'] ?? '' ?>" 
                           placeholder="Number of seats">
                </div>

                <div class="form-group">
                    <label class="form-label">Location</label>
                    <select name="location" class="form-select" required>
                        <option value="">Select Location</option>
                        <option value="Main Hall" <?= ($edit_table['location'] ?? '') === 'Main Hall' ? 'selected' : '' ?>>Main Hall</option>
                        <option value="VIP" <?= ($edit_table['location'] ?? '') === 'VIP' ? 'selected' : '' ?>>VIP</option>
                        <option value="Patio" <?= ($edit_table['location'] ?? '') === 'Patio' ? 'selected' : '' ?>>Patio</option>
                        <option value="Group" <?= ($edit_table['location'] ?? '') === 'Group' ? 'selected' : '' ?>>Group</option>
                    </select>
                </div>


<div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="Available" <?= isset($edit_table) && $edit_table['status']=="Available"?"selected":"" ?>>Available</option>
                        <option value="Occupied" <?= isset($edit_table) && $edit_table['status']=="Occupied"?"selected":"" ?>>Occupied</option>
                        <option value="Reserved" <?= isset($edit_table) && $edit_table['status']=="Reserved"?"selected":"" ?>>Reserved</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Table Image</label>
                    <input type="file" name="table_image" class="form-input" accept="image/*">
                    <?php if ($edit_table && $edit_table['image']): ?>
                        <small style="color: #666;">Current: <?= $edit_table['image'] ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($edit_table): ?>
                <input type="hidden" name="table_id" value="<?= $edit_table['id'] ?>">
                <input type="hidden" name="old_image" value="<?= $edit_table['image'] ?>">
            <?php endif; ?>

            <button type="submit" name="<?= $edit_table ? 'update_table' : 'add_table' ?>" class="btn">
                <i class="fas fa-<?= $edit_table ? 'save' : 'plus' ?>"></i>
                <?= $edit_table ? 'Update Table' : 'Add Table' ?>
            </button>
            
            <?php if ($edit_table): ?>
                <a href="manage_tables.php" class="btn" style="background: #6c757d; margin-left: 10px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- LOCATION TABS -->
    <div class="location-tabs">
        <button class="tab-btn active" onclick="showLocation('Main Hall')">
            <i class="fas fa-home"></i> Main Hall
            <span class="table-count">(<?php echo count($tables_by_location['Main Hall']); ?>)</span>
        </button>
        <button class="tab-btn" onclick="showLocation('VIP')">
            <i class="fas fa-crown"></i> VIP
            <span class="table-count">(<?php echo count($tables_by_location['VIP']); ?>)</span>
        </button>
        <button class="tab-btn" onclick="showLocation('Patio')">
            <i class="fas fa-tree"></i> Patio
            <span class="table-count">(<?php echo count($tables_by_location['Patio']); ?>)</span>
        </button>
        <button class="tab-btn" onclick="showLocation('Group')">
            <i class="fas fa-users"></i> Group
            <span class="table-count">(<?php echo count($tables_by_location['Group']); ?>)</span>
        </button>
    </div>

    <!-- SEAT FILTER BUTTONS - Only for Main Hall -->
    <div class="seat-filter-tabs" id="seatFilterTabs">
        <button class="seat-filter-btn active" onclick="filterBySeats('all')">
            <i class="fas fa-th"></i> All Tables
        </button>
        <button class="seat-filter-btn" onclick="filterBySeats('2')">
            <i class="fas fa-chair"></i> 2 Seats
        </button>
        <button class="seat-filter-btn" onclick="filterBySeats('4')">
            <i class="fas fa-couch"></i> 4 Seats
        </button>
        <button class="seat-filter-btn" onclick="filterBySeats('other')">
            <i class="fas fa-users"></i> Other Sizes
        </button>
    </div>

<!-- TABLES BY LOCATION -->
    <?php foreach ($locations as $location): ?>
        <div id="<?php echo $location; ?>" class="location-content <?php echo $location === 'Main Hall' ? 'active' : ''; ?>">
            <div class="location-section">
                <h2 class="location-title">
                    <i class="fas fa-<?php echo $location === 'Main Hall' ? 'home' : ($location === 'VIP' ? 'crown' : ($location === 'Patio' ? 'tree' : 'users')); ?>"></i>
                    <?php echo $location; ?> Tables
                </h2>
            
            <?php if (empty($tables_by_location[$location])): ?>
                <div class="no-tables">
                    <i class="fas fa-chair"></i>
                    <p>No tables in <?php echo $location; ?></p>
                    <small>Add tables using the form above</small>
                </div>
            <?php else: ?>
                <!-- Simple Grid Layout for all locations -->
                <div class="tables-grid">
                    <?php foreach ($tables_by_location[$location] as $table): ?>
                        <div class="table-card" data-seats="<?php echo $table['capacity']; ?>">
                            <!-- Three Dot Menu at Top Right -->
                            <div class="table-menu-dropdown">
                                <button class="table-menu-btn" onclick="toggleTableMenu(<?php echo $table['id']; ?>)" title="Table Options">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="table-dropdown-content" id="tableDropdown<?php echo $table['id']; ?>">
                                    <div class="dropdown-header">
                                        <i class="fas fa-cog"></i> Table Options
                                    </div>
                                    <a href="?edit_id=<?php echo $table['id']; ?>" class="table-dropdown-item edit-item">
                                        <i class="fas fa-edit"></i>
                                        Edit Table
                                    </a>
                                    <a href="#" class="table-dropdown-item view-item" onclick="viewTableDetails(<?php echo $table['id']; ?>, '<?php echo htmlspecialchars($table['table_label']); ?>', '<?php echo $table['capacity']; ?>', '<?php echo $table['location']; ?>', '<?php echo $table['status']; ?>')">
                                        <i class="fas fa-eye"></i>
                                        View Details
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a href="?delete_id=<?php echo $table['id']; ?>" class="table-dropdown-item delete-item" 
                                       onclick="return confirmDelete('<?php echo htmlspecialchars($table['table_label']); ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                        Delete Table
                                    </a>
                                </div>
                            </div>

                            <div class="table-image">
                                <?php if ($table['image'] && file_exists("../uploads/tables/".$table['image'])): ?>
                                    <img src="../uploads/tables/<?php echo htmlspecialchars($table['image']); ?>" alt="Table Image" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
                                <?php else: ?>
                                    <i class="fas fa-chair"></i>
                                <?php endif; ?>
                            </div>
                            
                            <div class="table-info">
                                <h3><?php echo htmlspecialchars($table['table_label']); ?></h3>
                                
                                <div class="table-details">
                                    <div class="detail-item">
                                        <span class="detail-label">
                                            <i class="fas fa-hashtag"></i> Table ID:
                                        </span>
                                        <span class="detail-value">#<?php echo $table['id']; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">
                                            <i class="fas fa-users"></i> Capacity:
                                        </span>
                                        <span class="detail-value"><?php echo $table['capacity']; ?> seats</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">
                                            <i class="fas fa-map-marker-alt"></i> Location:
                                        </span>
                                        <span class="detail-value"><?php echo $table['location']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="table-actions">
                                    <!-- Status Badge - Only show if not Available -->
                                    <?php if ($table['status'] !== 'Available'): ?>
                                    <div class="status-badge status-<?php echo strtolower($table['status']); ?>">
                                        <i class="fas fa-<?php echo $table['status'] === 'Occupied' ? 'user' : 'clock'; ?>"></i>
                                        <?php echo $table['status']; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
// Seat filtering functionality
function filterBySeats(seatCount) {
    // Remove active class from all seat filter buttons
    const seatBtns = document.querySelectorAll('.seat-filter-btn');
    seatBtns.forEach(btn => btn.classList.remove('active'));
    
    // Add active class to clicked button
    event.target.classList.add('active');
    
    // Get all table cards in the currently active location
    const activeLocation = document.querySelector('.location-content.active');
    const tableCards = activeLocation.querySelectorAll('.table-card');
    
    tableCards.forEach(card => {
        const cardSeats = card.getAttribute('data-seats');
        let shouldShow = false;
        
        if (seatCount === 'all') {
            shouldShow = true;
        } else if (seatCount === '2') {
            shouldShow = cardSeats === '2';
        } else if (seatCount === '4') {
            shouldShow = cardSeats === '4';
        } else if (seatCount === 'other') {
            shouldShow = cardSeats !== '2' && cardSeats !== '4';
        }
        
        if (shouldShow) {
            card.style.display = 'flex';
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
        } else {
            card.style.display = 'none';
        }
    });
    
    // Update the display message if no tables match
    updateNoTablesMessage(activeLocation, seatCount);
}

// Update no tables message based on filter
function updateNoTablesMessage(locationElement, seatCount) {
    const visibleCards = locationElement.querySelectorAll('.table-card[style*="display: flex"], .table-card:not([style*="display: none"])');
    const noTablesDiv = locationElement.querySelector('.no-tables');
    const tablesGrid = locationElement.querySelector('.tables-grid');
    
    if (visibleCards.length === 0 && tablesGrid) {
        if (!noTablesDiv) {
            const newNoTablesDiv = document.createElement('div');
            newNoTablesDiv.className = 'no-tables';
            newNoTablesDiv.innerHTML = `
                <i class="fas fa-chair"></i>
                <p>No ${seatCount === 'all' ? '' : (seatCount === 'other' ? 'other sized' : seatCount + '-seat')} tables found</p>
                <small>Try a different filter or add more tables</small>
            `;
            tablesGrid.parentNode.appendChild(newNoTablesDiv);
        }
        tablesGrid.style.display = 'none';
    } else if (tablesGrid) {
        tablesGrid.style.display = 'grid';
        const tempNoTablesDiv = locationElement.querySelector('.no-tables:not(.original-no-tables)');
        if (tempNoTablesDiv) {
            tempNoTablesDiv.remove();
        }
    }
}

// Three-dot table menu toggle
function toggleTableMenu(tableId) {
    const dropdown = document.getElementById('tableDropdown' + tableId);
    const allDropdowns = document.querySelectorAll('.table-dropdown-content');
    
    // Close all other dropdowns
    allDropdowns.forEach(dd => {
        if (dd.id !== 'tableDropdown' + tableId) {
            dd.classList.remove('show');
        }
    });
    
    // Toggle current dropdown
    dropdown.classList.toggle('show');
}

// View table details function
function viewTableDetails(id, label, capacity, location, status) {
    const details = `
🪑 TABLE DETAILS

📋 Table Information:
• Table ID: #${id}
• Label: ${label}
• Capacity: ${capacity} seats
• Location: ${location}
• Status: ${status}

This table is currently ${status.toLowerCase()} and can accommodate up to ${capacity} guests in the ${location} area.
    `;
    
    alert(details);
    
    // Close the dropdown after viewing
    const dropdown = document.getElementById('tableDropdown' + id);
    dropdown.classList.remove('show');
}

// Close dropdown when clicking outside
window.addEventListener('click', function(event) {
    if (!event.target.matches('.table-menu-btn') && !event.target.closest('.table-menu-btn')) {
        const dropdowns = document.querySelectorAll('.table-dropdown-content');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
});

// Location tab functionality
function showLocation(locationName) {
    // Hide all location contents
    const contents = document.querySelectorAll('.location-content');
    contents.forEach(content => content.classList.remove('active'));
    
    // Remove active class from all tabs
    const tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    // Show selected location content
    const selectedContent = document.getElementById(locationName);
    if (selectedContent) {
        selectedContent.classList.add('active');
    }
    
    // Add active class to clicked tab
    const clickedTab = event.target.closest('.tab-btn');
    if (clickedTab) {
        clickedTab.classList.add('active');
    }
    
    // Show/hide seat filter buttons based on location
    const seatFilterTabs = document.getElementById('seatFilterTabs');
    if (locationName === 'Main Hall') {
        seatFilterTabs.style.display = 'flex';
    } else {
        seatFilterTabs.style.display = 'none';
    }
    
    // Reset seat filter to "All Tables" when switching locations
    const seatBtns = document.querySelectorAll('.seat-filter-btn');
    seatBtns.forEach(btn => btn.classList.remove('active'));
    seatBtns[0].classList.add('active'); // First button is "All Tables"
    
    // Show all tables in the new location
    if (selectedContent) {
        const tableCards = selectedContent.querySelectorAll('.table-card');
        tableCards.forEach(card => {
            card.style.display = 'flex';
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
        });
        
        // Remove any temporary no-tables messages
        const tempNoTablesDiv = selectedContent.querySelector('.no-tables:not(.original-no-tables)');
        if (tempNoTablesDiv) {
            tempNoTablesDiv.remove();
        }
        
        const tablesGrid = selectedContent.querySelector('.tables-grid');
        if (tablesGrid) {
            tablesGrid.style.display = 'grid';
        }
    }
}

// Enhanced delete confirmation
function confirmDelete(tableName) {
    return confirm(`⚠️ WARNING!\n\nAre you absolutely sure you want to delete "${tableName}"?\n\nThis action cannot be undone and will permanently remove:\n• The table from the system\n• All associated bookings\n• The table image (if any)\n\nClick OK to proceed with deletion, or Cancel to keep the table.`);
}

// Prevent accidental form resubmission
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>



</body>
</html>