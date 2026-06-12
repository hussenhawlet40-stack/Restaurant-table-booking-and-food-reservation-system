<?php
//view drink menu
session_start();
require_once "../connection.php";

// Redirect if user not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_name = $_SESSION['name'] ?? 'User';

// Fetch all drink items
$drinks = $conn->query("SELECT * FROM menu_items WHERE category='Drink' ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<title>Drink Menu - Restaurant Booking</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
}

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

@keyframes float {
    0%, 100% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
    10%, 90% { opacity: 1; }
    50% { transform: translateY(-100px) rotate(180deg); }
}

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
    color: #74b9ff;
    cursor: pointer;
    padding: 10px;
    border-radius: 8px;
    transition: 0.3s;
}

.mobile-menu-btn:hover {
    background: rgba(116,185,255,0.1);
}

header h2 {
    margin: 0;
    font-size: 24px;
    background: linear-gradient(45deg, #74b9ff, #0984e3);
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
    color: #74b9ff;
    cursor: pointer;
    padding: 5px;
}

.mobile-nav-header {
    margin-bottom: 30px;
    padding-top: 40px;
    text-align: center;
}

.mobile-nav-header h3 {
    background: linear-gradient(45deg, #74b9ff, #0984e3);
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
    background: rgba(116,185,255,0.1);
    border: 1px solid rgba(116,185,255,0.2);
}

.mobile-nav a:hover {
    background: linear-gradient(45deg, #74b9ff, #0984e3);
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
    background: linear-gradient(45deg, #74b9ff, #0984e3);
    transition: left 0.3s ease;
    z-index: -1;
}

nav a:hover:before { left: 0; }
nav a:hover { color: white; transform: translateY(-2px); }

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

.container {
    max-width: 1400px;
    margin: auto;
    padding: 40px 30px;
}

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

.search-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 40px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
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
    border-color: #74b9ff;
    box-shadow: 0 0 20px rgba(116, 185, 255, 0.2);
}

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
    background: linear-gradient(45deg, #74b9ff, #0984e3);
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

.menu-card:hover img { transform: scale(1.1); }

.price-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(45deg, #00b894, #00a085);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 1.1rem;
    box-shadow: 0 4px 15px rgba(0, 184, 148, 0.3);
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
    background: rgba(116, 185, 255, 0.1);
    color: #74b9ff;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-bottom: 15px;
}

.add-to-cart-btn {
    width: 100%;
    padding: 12px;
    background: linear-gradient(45deg, #74b9ff, #0984e3);
    color: white;
    border: none;
    border-radius: 15px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(116, 185, 255, 0.3);
}

.add-to-cart-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(116, 185, 255, 0.4);
}

.back-btn {
    position: fixed;
    bottom: 30px;
    left: 30px;
    width: 60px;
    height: 60px;
    background: linear-gradient(45deg, #74b9ff, #0984e3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    font-size: 1.5rem;
    box-shadow: 0 10px 25px rgba(116, 185, 255, 0.4);
    transition: all 0.3s ease;
    z-index: 1000;
}

.back-btn:hover {
    transform: scale(1.1) translateY(-5px);
    box-shadow: 0 15px 35px rgba(116, 185, 255, 0.5);
}

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

@media (max-width: 768px) {
    .title-text { font-size: 2rem; }
    .menu-container { grid-template-columns: 1fr; }
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
    .back-btn { bottom: 20px; left: 20px; width: 50px; height: 50px; font-size: 1.2rem; }
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
</div>

<header>
    <div class="header-left">
        <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <h2>
            <i class="fas fa-cocktail"></i>
            Welcome, <?php echo htmlspecialchars($user_name); ?>
        </h2>
    </div>
    <nav>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="View_Tables.php"><i class="fas fa-chair"></i> Tables</a>
        <a href="view_menu.php"><i class="fas fa-utensils"></i> Food</a>
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
        <i class="fas fa-utensils"></i> Food
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
    <div class="page-title">
        <h1 class="title-text">Refreshing Beverages</h1>
        <p class="title-subtitle">Quench your thirst with our premium drink selection</p>
    </div>

    <div class="search-section">
        <input type="text" class="search-input" placeholder="Search for your favorite drinks..." id="searchInput">
    </div>

    <?php if(count($drinks) > 0): ?>
        <div class="menu-container" id="menuContainer">
            <?php foreach($drinks as $d): ?>
            <div class="menu-card" data-name="<?php echo strtolower(htmlspecialchars($d['name'])); ?>">
                <div class="menu-image-container">
                    <?php 
                    $img_path = "../uploads/menu_items/default.png";
                    if(!empty($d['image']) && file_exists("../uploads/menu_items/".$d['image'])){
                        $img_path = "../uploads/menu_items/".$d['image'];
                    }
                    ?>
                    <img src="<?php echo $img_path; ?>" alt="<?php echo htmlspecialchars($d['name']); ?>">
                    <div class="price-badge">
                        ETB <?php echo number_format($d['price'], 2); ?>
                    </div>
                </div>
                
                <div class="menu-info">
                    <h4>
                        <i class="fas fa-glass-cheers"></i>
                        <?php echo htmlspecialchars($d['name']); ?>
                    </h4>
                    
                    <div class="category-tag">
                        <i class="fas fa-tag"></i>
                        <?php echo htmlspecialchars($d['category']); ?>
                    </div>
                    
                    <button class="add-to-cart-btn" onclick="addToDrinkCart('<?php echo $d['id']; ?>', '<?php echo htmlspecialchars($d['name']); ?>', <?php echo $d['price']; ?>)">
                        <i class="fas fa-plus"></i>
                        Add to Order
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-cocktail"></i>
            </div>
            <div>No drinks available at the moment.</div>
        </div>
    <?php endif; ?>
</div>

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

// Search functionality
document.getElementById('searchInput').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const drinkItems = document.querySelectorAll('.drink-item');
    
    drinkItems.forEach(item => {
        const drinkName = item.querySelector('.drink-name').textContent.toLowerCase();
        const drinkDescription = item.querySelector('.drink-description').textContent.toLowerCase();
        
        if (drinkName.includes(searchTerm) || drinkDescription.includes(searchTerm)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
});
</script>

</body>
</html>

<script>
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

// Add to cart function
function addToDrinkCart(id, name, price) {
    let cart = JSON.parse(localStorage.getItem('restaurantCart')) || [];
    
    const existingItem = cart.find(item => item.id === id);
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({ id: id, name: name, price: price, quantity: 1, type: 'drink' });
    }
    
    localStorage.setItem('restaurantCart', JSON.stringify(cart));
    
    // Show success animation
    const button = event.target.closest('.add-to-cart-btn');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i> Added!';
    button.style.background = 'linear-gradient(45deg, #00b894, #00a085)';
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.style.background = 'linear-gradient(45deg, #74b9ff, #0984e3)';
    }, 1500);
}

// Initialize page animations
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.menu-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 150);
    });
});
</script>

</body>
</html>
