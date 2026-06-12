<?php
//view admin responses to my comments
session_start();
require_once "../connection.php";

// Redirect if user not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['name'];

// Ensure comments table has rating column
try {
    $columns = $conn->query("SHOW COLUMNS FROM comments LIKE 'rating'");
    if ($columns->rowCount() == 0) {
        $conn->exec("ALTER TABLE comments ADD COLUMN rating INT DEFAULT 5 AFTER username");
    }
} catch (Exception $e) {
    // Column might already exist
}

// Create replies table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS comment_replies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            admin_id INT NOT NULL,
            admin_name VARCHAR(100) NOT NULL,
            reply_message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {
    // Table might already exist
}

// Fetch only current user's comments with reply counts
$comments_query = "
    SELECT c.*, 
           COUNT(cr.id) as reply_count
    FROM comments c 
    LEFT JOIN comment_replies cr ON c.id = cr.comment_id 
    WHERE c.user_id = ?
    GROUP BY c.id 
    ORDER BY c.created_at DESC
";
$stmt = $conn->prepare($comments_query);
$stmt->execute([$user_id]);
$my_comments = $stmt->fetchAll();

// Function to get replies for a comment
function getReplies($conn, $comment_id) {
    $stmt = $conn->prepare("SELECT * FROM comment_replies WHERE comment_id = ? ORDER BY created_at ASC");
    $stmt->execute([$comment_id]);
    return $stmt->fetchAll();
}

// Count total responses received
$total_responses = 0;
foreach ($my_comments as $comment) {
    $total_responses += $comment['reply_count'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Admin Responses - Restaurant Booking</title>
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

        /* Mobile Navigation */
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

        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .page-title {
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

        .page-subtitle {
            color: #666;
            font-size: 1.1rem;
        }

        .stats-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-item {
            padding: 20px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .write-review-btn {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }

        .write-review-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        .responses-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .comment-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
        }

        .comment-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .comment-card.has-response {
            border-left-color: #10b981;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .comment-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .user-info h4 {
            margin: 0;
            color: #333;
            font-size: 1.1rem;
        }

        .comment-date {
            color: #999;
            font-size: 0.9rem;
        }

        .comment-rating {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stars {
            color: #ffd700;
            font-size: 1.2rem;
        }

        .rating-number {
            background: #667eea;
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .comment-body {
            margin: 20px 0;
            color: #555;
            line-height: 1.6;
            font-size: 1rem;
        }

        .response-status {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
            padding: 10px 15px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .response-status.has-response {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .response-status.no-response {
            background: rgba(255, 193, 7, 0.1);
            color: #f59e0b;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .replies-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .replies-header {
            font-size: 1rem;
            font-weight: 600;
            color: #10b981;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .reply-item {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 3px solid #10b981;
            position: relative;
        }

        .reply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .reply-admin {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #10b981;
        }

        .admin-badge {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .reply-date {
            color: #999;
            font-size: 0.85rem;
        }

        .reply-text {
            color: #555;
            line-height: 1.5;
            font-size: 0.95rem;
        }

        .no-comments {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-comments i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }

        .no-comments h3 {
            margin-bottom: 10px;
            color: #555;
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

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            nav {
                display: none;
            }
            
            .container {
                padding: 20px 15px;
            }
            
            .page-title {
                font-size: 2rem;
                flex-direction: column;
                gap: 10px;
            }
            
            .comment-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .comment-rating {
                align-self: flex-end;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .back-btn {
                bottom: 20px;
                left: 20px;
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="header-left">
        <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <h2>
            <i class="fas fa-reply-all"></i>
            Welcome, <?php echo htmlspecialchars($username); ?>
        </h2>
    </div>
    <nav>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="View_Tables.php"><i class="fas fa-chair"></i> Tables</a>
        <a href="view_menu.php"><i class="fas fa-utensils"></i> Food</a>
        <a href="view_drink_menu.php"><i class="fas fa-cocktail"></i> Drinks</a>
        <a href="write_comment.php"><i class="fas fa-comment"></i> Write Review</a>
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
        <i class="fas fa-comment"></i> Write Review
    </a>
    
    <a href="../logout.php" style="background: linear-gradient(45deg, #ff6b6b, #ee5a24); color: white; margin-top: 20px;">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileMenu()"></div>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-reply-all"></i>
            My Admin Responses
        </h1>
        <p class="page-subtitle">View restaurant responses to your reviews and feedback</p>
        
        <a href="write_comment.php" class="write-review-btn">
            <i class="fas fa-pen"></i>
            Write New Review
        </a>
    </div>

    <div class="stats-card">
        <h3>Your Review Statistics</h3>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number"><?= count($my_comments) ?></div>
                <div class="stat-label">Total Reviews</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $total_responses ?></div>
                <div class="stat-label">Admin Responses</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= count($my_comments) - array_sum(array_column($my_comments, 'reply_count')) > 0 ? count($my_comments) - count(array_filter($my_comments, function($c) { return $c['reply_count'] > 0; })) : 0 ?></div>
                <div class="stat-label">Pending Responses</div>
            </div>
        </div>
    </div>

    <div class="responses-section">
        <h2 class="section-title">
            <i class="fas fa-comments"></i>
            Your Reviews & Responses (<?= count($my_comments) ?>)
        </h2>
        
        <?php if (empty($my_comments)): ?>
            <div class="no-comments">
                <i class="fas fa-comment-slash"></i>
                <h3>No reviews yet</h3>
                <p>You haven't written any reviews yet. Share your dining experience!</p>
                <a href="write_comment.php" class="write-review-btn" style="margin-top: 20px;">
                    <i class="fas fa-pen"></i>
                    Write Your First Review
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($my_comments as $comment): ?>
                <div class="comment-card <?= $comment['reply_count'] > 0 ? 'has-response' : '' ?>">
                    <div class="comment-header">
                        <div class="comment-user">
                            <div class="user-avatar">
                                <?= strtoupper(substr($comment['username'], 0, 1)) ?>
                            </div>
                            <div class="user-info">
                                <h4><?= htmlspecialchars($comment['username']) ?> (You)</h4>
                                <div class="comment-date">
                                    <?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <div class="comment-rating">
                            <div class="stars">
                                <?php 
                                $rating = isset($comment['rating']) ? intval($comment['rating']) : 5;
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating) {
                                        echo '<i class="fas fa-star"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <span class="rating-number"><?= $rating ?>/5</span>
                        </div>
                    </div>
                    
                    <div class="comment-body">
                        <?= nl2br(htmlspecialchars($comment['message'])) ?>
                    </div>
                    
                    <!-- Response Status -->
                    <div class="response-status <?= $comment['reply_count'] > 0 ? 'has-response' : 'no-response' ?>">
                        <?php if ($comment['reply_count'] > 0): ?>
                            <i class="fas fa-check-circle"></i>
                            Restaurant has responded (<?= $comment['reply_count'] ?> response<?= $comment['reply_count'] > 1 ? 's' : '' ?>)
                        <?php else: ?>
                            <i class="fas fa-clock"></i>
                            Waiting for restaurant response
                        <?php endif; ?>
                    </div>
                    
                    <!-- Admin Replies -->
                    <?php 
                    $replies = getReplies($conn, $comment['id']);
                    if (!empty($replies)): 
                    ?>
                        <div class="replies-section">
                            <div class="replies-header">
                                <i class="fas fa-reply-all"></i> 
                                Restaurant Response<?= count($replies) > 1 ? 's' : '' ?>
                            </div>
                            <?php foreach ($replies as $reply): ?>
                                <div class="reply-item">
                                    <div class="reply-header">
                                        <div class="reply-admin">
                                            <i class="fas fa-user-shield"></i>
                                            <?= htmlspecialchars($reply['admin_name']) ?>
                                            <span class="admin-badge">Restaurant Staff</span>
                                        </div>
                                        <div class="reply-date">
                                            <?= date('M j, Y g:i A', strtotime($reply['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="reply-text">
                                        <?= nl2br(htmlspecialchars($reply['reply_message'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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
    
    // Add entrance animations
    const cards = document.querySelectorAll('.comment-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 + (index * 100));
    });
    
    // Highlight cards with responses
    const cardsWithResponses = document.querySelectorAll('.comment-card.has-response');
    cardsWithResponses.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.borderLeftColor = '#10b981';
            this.style.borderLeftWidth = '6px';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.borderLeftColor = '#10b981';
            this.style.borderLeftWidth = '4px';
        });
    });
});
</script>

</body>
</html>