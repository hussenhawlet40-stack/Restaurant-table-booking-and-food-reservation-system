<?php
//view menu
session_start();
require_once "../connection.php";

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_name = $_SESSION['name'] ?? 'User';

// Check if category is selected
$selected_category = $_GET['category'] ?? '';

// Get all food categories with counts
$categories = $conn->query("
    SELECT category, COUNT(*) as count 
    FROM menu_items 
    WHERE category != 'Drink' 
    GROUP BY category 
    ORDER BY category
")->fetchAll(PDO::FETCH_ASSOC);

// If category is selected, fetch items from that category
$items = [];
if (!empty($selected_category)) {
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE category = ? ORDER BY name ASC");
    $stmt->execute([$selected_category]);
    $items = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Food Menu - Restaurant Booking</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
}

/* Animated Background Particles */
.particles {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
}

.particle {
    position: absolute;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    animation: float 8s ease-in-out infinite;
}

.particle:nth-child(1) { width: 15px; height: 15px; left: 15%; animation-delay: 0s; }
.particle:nth-child(2) { width: 20px; height: 20px; left: 25%; animation-delay: 1s; }
.particle:nth-child(3) { width: 12px; height: 12px; left: 35%; animation-delay: 2s; }
.particle:nth-child(4) { width: 18px; height: 18px; left: 45%; animation-delay: 3s; }
.particle:nth-child(5) { width: 16px; height: 16px; left: 55%; animation-delay: 4s; }
.particle:nth-child(6) { width: 22px; height: 22px; left: 65%; animation-delay: 5s; }
.particle:nth-child(7) { width: 14px; height: 14px; left: 75%; animation-delay: 0.5s; }
.particle:nth-child(8) { width: 19px; height: 19px; left: 85%; animation-delay: 1.5s; }

@keyframes float {
    0%, 100% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
    10%, 90% { opacity: 1; }
    50% { transform: translateY(-100px) rotate(180deg); }
}

/* Header */
header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    color: #333;
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 0;
    z-index: 100;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.mobile-menu-btn {
    display: none;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #667eea;
    cursor: pointer;
    padding: 10px;
    border-radius: 8px;
    transition: 0.3s;
}

.mobile-menu-btn:hover {
    background: rgba(102,126,234,0.1);
}

header h2 {
    margin: 0;
    font-size: 24px;
    background: linear-gradient(45deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Mobile Menu Styles */
.mobile-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: 0.3s;
}

.mobile-overlay.active {
    opacity: 1;
    visibility: visible;
}

.mobile-nav {
    position: fixed;
    top: 0;
    left: -100%;
    width: 280px;
    height: 100vh;
    background: rgba(255,255,255,0.98);
    backdrop-filter: blur(20px);
    z-index: 1000;
    padding: 20px;
    box-shadow: 2px 0 20px rgba(0,0,0,0.1);
    transition: 0.3s ease;
    overflow-y: auto;
}

.mobile-nav.active {
    left: 0;
}

.mobile-nav-close {
    display: block;
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #667eea;
    cursor: pointer;
    padding: 5px;
}

.mobile-nav-header {
    margin-bottom: 30px;
    padding-top: 40px;
    text-align: center;
}

.mobile-nav-header h3 {
    background: linear-gradient(45deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 1.2rem;
}

.mobile-nav a {
    display: block;
    color: #333;
    text-decoration: none;
    padding: 15px 20px;
    margin-bottom: 10px;
    border-radius: 10px;
    transition: 0.3s;
    background: rgba(102,126,234,0.1);
    border: 1px solid rgba(102,126,234,0.2);
}

.mobile-nav a:hover {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    transform: translateX(5px);
}

.mobile-nav a i {
    margin-right: 10px;
    width: 20px;
}

nav {
    display: flex;
    gap: 20px;
    align-items: center;
}

nav a {
    color: #333;
    text-decoration: none;
    font-weight: 500;
    padding: 10px 15px;
    border-radius: 25px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

nav a:before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, #667eea, #764ba2);
    transition: left 0.3s ease;
    z-index: -1;
}

nav a:hover:before {
    left: 0;
}

nav a:hover {
    color: white;
    transform: translateY(-2px);
}

.logout-btn {
    background: linear-gradient(45deg, #ff6b6b, #ee5a24);
    padding: 10px 20px;
    color: #fff !important;
    border-radius: 25px;
    text-decoration: none;
    box-shadow: 0 4px 15px rgba(238, 90, 36, 0.3);
    transition: all 0.3s ease;
}

.logout-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(238, 90, 36, 0.4);
}

/* Main Container */
.container {
    max-width: 1400px;
    margin: auto;
    padding: 40px 30px;
}

/* Page Title */
.page-title {
    text-align: center;
    margin-bottom: 50px;
}

.title-text {
    font-size: 3rem;
    font-weight: bold;
    background: linear-gradient(45deg, #fff, #f8f9fa);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 15px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    animation: glow 2s ease-in-out infinite alternate;
}

.title-subtitle {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.2rem;
    font-weight: 300;
}

@keyframes glow {
    from { filter: drop-shadow(0 0 5px rgba(255,255,255,0.3)); }
    to { filter: drop-shadow(0 0 20px rgba(255,255,255,0.6)); }
}

/* Search and Filter Section */
.search-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 40px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.search-container {
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
}

.search-box {
    flex: 1;
    min-width: 250px;
    position: relative;
}

.search-input {
    width: 100%;
    padding: 15px 50px 15px 20px;
    border: 2px solid #e0e0e0;
    border-radius: 25px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
}

.search-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 20px rgba(102, 126, 234, 0.2);
}

.search-icon {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    color: #667eea;
    font-size: 1.2rem;
}

.sort-options {
    display: flex;
    gap: 10px;
    align-items: center;
}

.sort-btn {
    padding: 12px 20px;
    border: 2px solid #667eea;
    background: transparent;
    color: #667eea;
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.sort-btn.active,
.sort-btn:hover {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

/* Categories Grid */
.categories-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 30px;
    margin-top: 30px;
}

.category-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    transition: all 0.4s ease;
    border: 1px solid rgba(255, 255, 255, 0.3);
    cursor: pointer;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 20px;
}

.category-card:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(45deg, #667eea, #764ba2);
    border-radius: 20px 20px 0 0;
}

.category-card:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
}

.category-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(45deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    flex-shrink: 0;
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
}

.category-info {
    flex: 1;
}

.category-info h3 {
    font-size: 1.5rem;
    color: #333;
    margin-bottom: 8px;
    font-weight: 600;
}

.category-info p {
    color: #667eea;
    font-weight: 500;
    margin-bottom: 10px;
    font-size: 0.9rem;
}

.category-description {
    color: #666;
    font-size: 0.95rem;
    line-height: 1.5;
}

.category-arrow {
    color: #667eea;
    font-size: 1.5rem;
    transition: transform 0.3s ease;
}

.category-card:hover .category-arrow {
    transform: translateX(10px);
}

.back-to-categories {
    margin-bottom: 20px;
}

.back-category-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    color: #667eea;
    padding: 12px 20px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.back-category-btn:hover {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
}

/* Menu Grid */
.menu-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 30px;
    margin-top: 30px;
}

.menu-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    transition: all 0.4s ease;
    border: 1px solid rgba(255, 255, 255, 0.3);
    position: relative;
    transform-style: preserve-3d;
}

.menu-card:hover {
    transform: translateY(-15px) rotateX(5deg);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
}

.menu-card:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(45deg, #667eea, #764ba2);
    border-radius: 20px 20px 0 0;
}

.menu-image-container {
    position: relative;
    overflow: hidden;
    height: 250px;
}

.menu-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: all 0.4s ease;
}

.menu-card:hover img {
    transform: scale(1.1);
}

.price-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(45deg, #28a745, #20c997);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 1.1rem;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    backdrop-filter: blur(10px);
}

.menu-info {
    padding: 25px;
}

.menu-info h4 {
    margin: 0 0 15px 0;
    font-size: 1.4rem;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.category-tag {
    display: inline-block;
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-bottom: 15px;
}

.menu-description {
    color: #666;
    font-size: 0.95rem;
    line-height: 1.6;
    margin-bottom: 20px;
}

.menu-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.add-to-cart-btn {
    flex: 1;
    padding: 12px;
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 15px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    position: relative;
    overflow: hidden;
}

.add-to-cart-btn:before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.add-to-cart-btn:hover:before {
    left: 100%;
}

.add-to-cart-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.favorite-btn {
    width: 45px;
    height: 45px;
    border: 2px solid #ff6b6b;
    background: transparent;
    color: #ff6b6b;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.favorite-btn:hover,
.favorite-btn.active {
    background: #ff6b6b;
    color: white;
    transform: scale(1.1);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: rgba(255, 255, 255, 0.8);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.6;
}

.empty-title {
    font-size: 1.5rem;
    margin-bottom: 10px;
}

.empty-text {
    font-size: 1rem;
    opacity: 0.8;
}

/* Back Button */
.back-btn {
    position: fixed;
    bottom: 30px;
    left: 30px;
    width: 60px;
    height: 60px;
    background: linear-gradient(45deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    font-size: 1.5rem;
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    transition: all 0.3s ease;
    z-index: 1000;
}

.back-btn:hover {
    transform: scale(1.1) translateY(-5px);
    box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
}

/* Cart Counter */
.cart-counter {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    background: linear-gradient(45deg, #28a745, #20c997);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    box-shadow: 0 10px 25px rgba(40, 167, 69, 0.4);
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 1000;
}

.cart-counter:hover {
    transform: scale(1.1) translateY(-5px);
    box-shadow: 0 15px 35px rgba(40, 167, 69, 0.5);
}

.cart-count {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff6b6b;
    color: white;
    border-radius: 50%;
    width: 25px;
    height: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: bold;
}

/* Responsive Design */
@media (max-width: 768px) {
    .title-text {
        font-size: 2rem;
    }
    
    .categories-container {
        grid-template-columns: 1fr;
    }
    
    .category-card {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .category-icon {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }
    
    .menu-container {
        grid-template-columns: 1fr;
    }
    
    .search-container {
        flex-direction: column;
        align-items: stretch;
    }
    
    .sort-options {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .mobile-menu-btn {
        display: block;
    }
    
    nav {
        display: none;
    }
    
    header {
        flex-direction: row;
        text-align: left;
    }
    
    .back-btn, .cart-counter {
        bottom: 20px;
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
    }
    
    .back-btn {
        left: 20px;
    }
    
    .cart-counter {
        right: 20px;
    }
}

/* Loading Animation */
.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
</head>
<body>

<div class="particles">
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
</div>

<header>
    <div class="header-left">
        <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <h2>
            <i class="fas fa-utensils"></i>
            Welcome, <?php echo htmlspecialchars($user_name); ?>
        </h2>
    </div>
    <nav>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="View_Tables.php"><i class="fas fa-chair"></i> Tables</a>
        <a href="view_menu.php"><i class="fas fa-utensils"></i> Food Menu</a>
        <a href="view_drink_menu.php"><i class="fas fa-cocktail"></i> Drinks</a>
        <a href="write_comment.php"><i class="fas fa-comment"></i> Comments</a>
        <a class="logout-btn" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</header>

<!-- Mobile Navigation -->
<div class="mobile-nav" id="mobileNav">
    <button class="mobile-nav-close" onclick="toggleMobileMenu()">
        <i class="fas fa-times"></i>
    </button>
    
    <div class="mobile-nav-header">
        <h3>Restaurant Menu</h3>
    </div>
    
    <a href="dashboard.php">
        <i class="fas fa-home"></i> Dashboard
    </a>
    
    <a href="View_Tables.php">
        <i class="fas fa-chair"></i> Tables
    </a>
    
    <a href="view_menu.php">
        <i class="fas fa-utensils"></i> Food Menu
    </a>
    
    <a href="view_drink_menu.php">
        <i class="fas fa-cocktail"></i> Drinks
    </a>
    
    <a href="write_comment.php">
        <i class="fas fa-comment"></i> Comments
    </a>
    
    <a href="../logout.php" style="background: linear-gradient(45deg, #ff6b6b, #ee5a24); color: white; margin-top: 20px;">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileMenu()"></div>

<div class="container">
    <!-- Page Title -->
    <div class="page-title">
        <h1 class="title-text">
            <?php if (empty($selected_category)): ?>
                Food Menu Categories
            <?php else: ?>
                <?= htmlspecialchars($selected_category) ?>
            <?php endif; ?>
        </h1>
        <p class="title-subtitle">
            <?php if (empty($selected_category)): ?>
                Choose a category to explore our delicious offerings
            <?php else: ?>
                Discover our chef's carefully crafted <?= strtolower(htmlspecialchars($selected_category)) ?>
            <?php endif; ?>
        </p>
    </div>

    <?php if (empty($selected_category)): ?>
        <!-- Categories Grid -->
        <div class="categories-container">
            <?php if (count($categories) > 0): ?>
                <?php foreach ($categories as $category): ?>
                    <div class="category-card" onclick="selectCategory('<?= urlencode($category['category']) ?>')">
                        <div class="category-icon">
                            <i class="fas fa-<?php 
                                $cat = strtolower($category['category']);
                                if (strpos($cat, 'local') !== false) echo 'landmark';
                                elseif (strpos($cat, 'continental') !== false) echo 'globe';
                                elseif (strpos($cat, 'dessert') !== false) echo 'birthday-cake';
                                else echo 'utensils';
                            ?>"></i>
                        </div>
                        <div class="category-info">
                            <h3><?= htmlspecialchars($category['category']) ?></h3>
                            <p><?= $category['count'] ?> item<?= $category['count'] > 1 ? 's' : '' ?> available</p>
                            <div class="category-description">
                                <?php 
                                $cat = strtolower($category['category']);
                                if (strpos($cat, 'local') !== false) {
                                    echo "Traditional Ethiopian and local specialties";
                                } elseif (strpos($cat, 'continental') !== false) {
                                    echo "International cuisine and fusion dishes";
                                } elseif (strpos($cat, 'dessert') !== false) {
                                    echo "Sweet treats and delightful desserts";
                                } else {
                                    echo "Delicious food items";
                                }
                                ?>
                            </div>
                        </div>
                        <div class="category-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div class="empty-title">No Food Categories Available</div>
                    <div class="empty-text">Our chefs are preparing something amazing. Please check back later!</div>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Back to Categories Button -->
        <div class="back-to-categories">
            <a href="view_menu.php" class="back-category-btn">
                <i class="fas fa-arrow-left"></i> Back to Categories
            </a>
        </div>

        <!-- Search and Filter Section -->
        <div class="search-section">
            <div class="search-container">
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="Search in <?= htmlspecialchars($selected_category) ?>..." id="searchInput">
                    <i class="fas fa-search search-icon"></i>
                </div>
                <div class="sort-options">
                    <button class="sort-btn active" onclick="sortMenu('name')">Name</button>
                    <button class="sort-btn" onclick="sortMenu('price-low')">Price ↑</button>
                    <button class="sort-btn" onclick="sortMenu('price-high')">Price ↓</button>
                </div>
            </div>
        </div>

        <?php if(count($items) > 0): ?>
        <div class="menu-container" id="menuContainer">
        <?php foreach($items as $i): ?>
        <div class="menu-card" data-name="<?php echo strtolower(htmlspecialchars($i['name'])); ?>" data-price="<?php echo $i['price']; ?>">
            <div class="menu-image-container">
                <?php 
                $img_path = "../uploads/menu_items/default.png";
                if(!empty($i['image']) && file_exists("../uploads/menu_items/".$i['image'])){
                    $img_path = "../uploads/menu_items/".$i['image'];
                }
                ?>
                <img src="<?php echo $img_path; ?>" alt="<?php echo htmlspecialchars($i['name']); ?>">
                <div class="price-badge">
                    ETB <?php echo number_format($i['price'], 2); ?>
                </div>
            </div>
            
            <div class="menu-info">
                <h4>
                    <i class="fas fa-utensils"></i>
                    <?php echo htmlspecialchars($i['name']); ?>
                </h4>
                
                <div class="category-tag">
                    <i class="fas fa-tag"></i>
                    <?php echo htmlspecialchars($i['category']); ?>
                </div>
                
                <div class="menu-description">
                    A delicious and carefully prepared dish made with the finest ingredients, crafted by our expert chefs to deliver an unforgettable dining experience.
                </div>
                
                <div class="menu-actions">
                    <button class="add-to-cart-btn" onclick="addToCart('<?php echo $i['id']; ?>', '<?php echo htmlspecialchars($i['name']); ?>', <?php echo $i['price']; ?>)">
                        <i class="fas fa-plus"></i>
                        Add to Order
                    </button>
                    <button class="favorite-btn" onclick="toggleFavorite(this)">
                        <i class="fas fa-heart"></i>
                    </button>
                </div>
            </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="empty-title">No Items in This Category</div>
                <div class="empty-text">This category is currently empty. Please try another category!</div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Back to Dashboard Button -->
<a href="dashboard.php" class="back-btn" title="Back to Dashboard">
    <i class="fas fa-arrow-left"></i>
</a>

<script>
// Mobile menu toggle function
function toggleMobileMenu() {
    const mobileNav = document.getElementById('mobileNav');
    const overlay = document.getElementById('mobileOverlay');
    
    if (!mobileNav || !overlay) {
        console.error('Mobile menu elements not found');
        return;
    }
    
    mobileNav.classList.toggle('active');
    overlay.classList.toggle('active');
}

// Handle window resize for mobile menu
window.addEventListener('resize', function() {
    const mobileNav = document.getElementById('mobileNav');
    const overlay = document.getElementById('mobileOverlay');
    
    if (window.innerWidth > 768 && mobileNav && overlay) {
        mobileNav.classList.remove('active');
        overlay.classList.remove('active');
    }
});

// Add click event listeners to mobile menu items
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuItems = document.querySelectorAll('.mobile-nav a');
    mobileMenuItems.forEach(item => {
        item.addEventListener('click', function() {
            // Close mobile menu when clicking on menu items
            if (window.innerWidth <= 768) {
                setTimeout(() => {
                    toggleMobileMenu();
                }, 200);
            }
        });
    });
});
</script>

</body>
</html>

<!-- Cart Counter -->
<div class="cart-counter" onclick="viewCart()" title="View Cart">
    <i class="fas fa-shopping-cart"></i>
    <div class="cart-count" id="cartCount">0</div>
</div>

<script>
// Cart functionality
let cart = JSON.parse(localStorage.getItem('restaurantCart')) || [];
let favorites = JSON.parse(localStorage.getItem('restaurantFavorites')) || [];

// Update cart counter
function updateCartCounter() {
    const cartCount = document.getElementById('cartCount');
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    cartCount.textContent = totalItems;
    
    if (totalItems > 0) {
        cartCount.style.display = 'flex';
    } else {
        cartCount.style.display = 'none';
    }
}

// Category selection function
function selectCategory(category) {
    window.location.href = `view_menu.php?category=${category}`;
}

// Add to cart function
function addToCart(id, name, price) {
    const existingItem = cart.find(item => item.id === id);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: id,
            name: name,
            price: price,
            quantity: 1
        });
    }
    
    localStorage.setItem('restaurantCart', JSON.stringify(cart));
    updateCartCounter();
    
    // Show success animation
    const button = event.target.closest('.add-to-cart-btn');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i> Added!';
    button.style.background = 'linear-gradient(45deg, #28a745, #20c997)';
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.style.background = 'linear-gradient(45deg, #667eea, #764ba2)';
    }, 1500);
}

// Toggle favorite function
function toggleFavorite(button) {
    const card = button.closest('.menu-card');
    const itemName = card.getAttribute('data-name');
    
    if (favorites.includes(itemName)) {
        favorites = favorites.filter(fav => fav !== itemName);
        button.classList.remove('active');
    } else {
        favorites.push(itemName);
        button.classList.add('active');
    }
    
    localStorage.setItem('restaurantFavorites', JSON.stringify(favorites));
}

// Search functionality
document.getElementById('searchInput').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const cards = document.querySelectorAll('.menu-card');
    
    cards.forEach(card => {
        const itemName = card.getAttribute('data-name');
        if (itemName.includes(searchTerm)) {
            card.style.display = 'block';
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        } else {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.display = 'none';
            }, 300);
        }
    });
});

// Sort functionality
function sortMenu(type) {
    const container = document.getElementById('menuContainer');
    const cards = Array.from(container.querySelectorAll('.menu-card'));
    
    // Update active button
    document.querySelectorAll('.sort-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    cards.sort((a, b) => {
        switch(type) {
            case 'name':
                return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
            case 'price-low':
                return parseFloat(a.getAttribute('data-price')) - parseFloat(b.getAttribute('data-price'));
            case 'price-high':
                return parseFloat(b.getAttribute('data-price')) - parseFloat(a.getAttribute('data-price'));
            default:
                return 0;
        }
    });
    
    // Animate and reorder
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            container.appendChild(card);
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 50);
    });
}

// View cart function
function viewCart() {
    if (cart.length === 0) {
        alert('Your cart is empty! Add some delicious items first.');
        return;
    }
    
    let cartSummary = 'Your Cart:\n\n';
    let total = 0;
    
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        cartSummary += `${item.name} x${item.quantity} - ETB ${itemTotal.toFixed(2)}\n`;
        total += itemTotal;
    });
    
    cartSummary += `\nTotal: ETB ${total.toFixed(2)}`;
    cartSummary += '\n\nWould you like to proceed to checkout?';
    
    if (confirm(cartSummary)) {
        // Redirect to checkout or booking page
        window.location.href = 'pre_order.php';
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    updateCartCounter();
    
    // Load favorites
    favorites.forEach(favName => {
        const card = document.querySelector(`[data-name="${favName}"]`);
        if (card) {
            card.querySelector('.favorite-btn').classList.add('active');
        }
    });
    
    // Animate cards on load (both category and menu cards)
    const categoryCards = document.querySelectorAll('.category-card');
    const menuCards = document.querySelectorAll('.menu-card');
    const allCards = [...categoryCards, ...menuCards];
    
    allCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 150);
    });
    
    // Add click animation to menu cards
    menuCards.forEach(card => {
        card.addEventListener('click', function(e) {
            if (!e.target.closest('.menu-actions')) {
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            }
        });
    });
    
    // Add hover effect to category cards
    categoryCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-10px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
});

// Add floating particles effect
function createFloatingParticle() {
    const particle = document.createElement('div');
    particle.style.cssText = `
        position: fixed;
        width: 3px;
        height: 3px;
        background: rgba(255, 255, 255, 0.5);
        border-radius: 50%;
        pointer-events: none;
        z-index: 1000;
        animation: floatUp 5s linear forwards;
    `;
    
    particle.style.left = Math.random() * window.innerWidth + 'px';
    particle.style.bottom = '-10px';
    
    document.body.appendChild(particle);
    
    setTimeout(() => {
        particle.remove();
    }, 5000);
}

// Create particles periodically
setInterval(createFloatingParticle, 4000);

// Add CSS for floating particles
const style = document.createElement('style');
style.textContent = `
    @keyframes floatUp {
        0% {
            transform: translateY(0) rotate(0deg);
            opacity: 1;
        }
        100% {
            transform: translateY(-100vh) rotate(360deg);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>

</body>
</html>
