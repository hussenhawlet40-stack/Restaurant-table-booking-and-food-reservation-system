<?php
//write comment with star rating
session_start();
require_once "../connection.php";

// Redirect if user not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['name'];
$success = "";
$error = "";

// Ensure comments table has rating column
try {
    $columns = $conn->query("SHOW COLUMNS FROM comments LIKE 'rating'");
    if ($columns->rowCount() == 0) {
        $conn->exec("ALTER TABLE comments ADD COLUMN rating INT DEFAULT 5 AFTER username");
    }
} catch (Exception $e) {
    // Column might already exist or other error
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $msg = trim($_POST['message']);
    $rating = intval($_POST['rating'] ?? 5);

    if ($msg == "") {
        $error = "Message cannot be empty.";
    } elseif ($rating < 1 || $rating > 5) {
        $error = "Please select a valid rating (1-5 stars).";
    } else {
        $stmt = $conn->prepare("INSERT INTO comments (user_id, username, rating, message) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $username, $rating, $msg])) {
            $success = "Your feedback with $rating star rating has been sent to the admin.";
        } else {
            $error = "Failed to send message.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Write Comment - Restaurant Booking</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
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

        .main-content {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 80px);
            padding: 40px 0;
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

        @keyframes float {
            0%, 100% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10%, 90% { opacity: 1; }
            50% { transform: translateY(-100px) rotate(180deg); }
        }

        .container {
            max-width: 600px;
            width: 90%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .title {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .subtitle {
            color: #666;
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 1rem;
        }

        textarea {
            width: 100%;
            height: 150px;
            padding: 15px;
            font-size: 16px;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            font-family: inherit;
            resize: vertical;
            transition: all 0.3s ease;
            background: white;
        }

        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.2);
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .submit-btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .submit-btn:hover:before { left: 100%; }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .message {
            margin: 20px 0;
            padding: 15px;
            border-radius: 10px;
            font-weight: 500;
            text-align: center;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .error {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

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

        /* Star Rating Styles */
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
            gap: 5px;
            margin: 15px 0;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            font-size: 2.5rem;
            color: #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #ffd700;
            transform: scale(1.1);
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
        }

        .star-rating label:hover {
            animation: starPulse 0.3s ease;
        }

        @keyframes starPulse {
            0% { transform: scale(1.1); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1.1); }
        }

        .rating-text {
            text-align: center;
            font-size: 1.1rem;
            font-weight: 500;
            color: #667eea;
            margin-top: 10px;
            transition: all 0.3s ease;
        }

        @media (max-width: 768px) {
            .container { width: 95%; padding: 30px 20px; }
            .title { font-size: 2rem; }
            .back-btn { bottom: 20px; left: 20px; width: 50px; height: 50px; font-size: 1.2rem; }
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
            .star-rating label { font-size: 2rem; }
        }
    </style>
</head>
<body>

<div class="particles">
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
            <i class="fas fa-comment-dots"></i>
            Welcome, <?php echo htmlspecialchars($username); ?>
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

<div class="main-content">
    <div class="container">
    <div class="header">
        <h1 class="title">
            <i class="fas fa-comment-dots"></i>
            Share Your Feedback
        </h1>
        <p class="subtitle">We value your opinion and would love to hear from you</p>
    </div>

    <?php if ($success): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <?= $success ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="commentForm">
        <div class="form-group">
            <label class="form-label">
                <i class="fas fa-star"></i>
                Rate Your Experience
            </label>
            <div class="star-rating">
                <input type="radio" name="rating" value="5" id="star5" checked>
                <label for="star5" class="star">★</label>
                <input type="radio" name="rating" value="4" id="star4">
                <label for="star4" class="star">★</label>
                <input type="radio" name="rating" value="3" id="star3">
                <label for="star3" class="star">★</label>
                <input type="radio" name="rating" value="2" id="star2">
                <label for="star2" class="star">★</label>
                <input type="radio" name="rating" value="1" id="star1">
                <label for="star1" class="star">★</label>
            </div>
            <div class="rating-text" id="ratingText">Excellent (5 stars)</div>
        </div>

        <div class="form-group">
            <label class="form-label">
                <i class="fas fa-pen"></i>
                Your Message
            </label>
            <textarea 
                name="message" 
                placeholder="Share your dining experience, suggestions, or any feedback you'd like us to know..."
                required
                id="messageTextarea"
            ></textarea>
        </div>
        
        <button type="submit" class="submit-btn" id="submitBtn">
            <i class="fas fa-paper-plane"></i>
            Send Feedback
        </button>
    </form>
</div>
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

// Star rating functionality
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.star-rating input[type="radio"]');
    const labels = document.querySelectorAll('.star-rating label');
    
    labels.forEach((label, index) => {
        label.addEventListener('mouseenter', function() {
            // Highlight stars up to this one
            for (let i = 0; i <= index; i++) {
                labels[i].style.color = '#ffd700';
            }
            // Reset stars after this one
            for (let i = index + 1; i < labels.length; i++) {
                labels[i].style.color = '#ddd';
            }
        });
        
        label.addEventListener('click', function() {
            // Set permanent color for selected rating
            const rating = this.getAttribute('for').replace('star', '');
            for (let i = 0; i < rating; i++) {
                labels[i].style.color = '#ffd700';
            }
            for (let i = rating; i < labels.length; i++) {
                labels[i].style.color = '#ddd';
            }
        });
    });
    
    // Reset on mouse leave
    document.querySelector('.star-rating').addEventListener('mouseleave', function() {
        const checkedStar = document.querySelector('.star-rating input[type="radio"]:checked');
        if (checkedStar) {
            const rating = checkedStar.value;
            for (let i = 0; i < rating; i++) {
                labels[i].style.color = '#ffd700';
            }
            for (let i = rating; i < labels.length; i++) {
                labels[i].style.color = '#ddd';
            }
        } else {
            labels.forEach(label => {
                label.style.color = '#ddd';
            });
        }
    });
});
</script>

</body>
</html>

<script>
// Star rating functionality
const ratingInputs = document.querySelectorAll('input[name="rating"]');
const ratingText = document.getElementById('ratingText');

const ratingTexts = {
    1: 'Poor (1 star)',
    2: 'Fair (2 stars)', 
    3: 'Good (3 stars)',
    4: 'Very Good (4 stars)',
    5: 'Excellent (5 stars)'
};

ratingInputs.forEach(input => {
    input.addEventListener('change', function() {
        const rating = this.value;
        ratingText.textContent = ratingTexts[rating];
        
        // Add animation effect
        ratingText.style.transform = 'scale(1.1)';
        setTimeout(() => {
            ratingText.style.transform = 'scale(1)';
        }, 200);
    });
});

document.getElementById('commentForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('submitBtn');
    const textarea = document.getElementById('messageTextarea');
    const selectedRating = document.querySelector('input[name="rating"]:checked');
    
    if (textarea.value.trim() === '') {
        e.preventDefault();
        textarea.focus();
        textarea.style.borderColor = '#dc3545';
        return;
    }
    
    if (!selectedRating) {
        e.preventDefault();
        alert('Please select a rating');
        return;
    }
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    btn.disabled = true;
});

// Auto-resize textarea
document.getElementById('messageTextarea').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.max(150, this.scrollHeight) + 'px';
    this.style.borderColor = '#e0e0e0';
});

// Character counter (optional)
const textarea = document.getElementById('messageTextarea');
textarea.addEventListener('input', function() {
    const maxLength = 500;
    const currentLength = this.value.length;
    
    if (currentLength > maxLength) {
        this.value = this.value.substring(0, maxLength);
    }
});
</script>

</body>
</html>
