<?php
//view comment with ratings and replies
session_start();
require_once "../connection.php";

// Admin Auth
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = "";

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

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply'])) {
    $comment_id = intval($_POST['comment_id']);
    $reply_message = trim($_POST['reply_message']);
    $admin_id = $_SESSION['user_id'];
    $admin_name = $_SESSION['name'];
    
    if (!empty($reply_message)) {
        try {
            $stmt = $conn->prepare("INSERT INTO comment_replies (comment_id, admin_id, admin_name, reply_message) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$comment_id, $admin_id, $admin_name, $reply_message])) {
                $message = "Reply posted successfully!";
            } else {
                $message = "Error posting reply!";
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Handle reply deletion
if (isset($_GET['delete_reply'])) {
    $reply_id = intval($_GET['delete_reply']);
    try {
        $stmt = $conn->prepare("DELETE FROM comment_replies WHERE id = ?");
        if ($stmt->execute([$reply_id])) {
            $message = "Reply deleted successfully!";
        }
    } catch (Exception $e) {
        $message = "Error deleting reply!";
    }
}

// Ensure comments table has rating column
try {
    $columns = $conn->query("SHOW COLUMNS FROM comments LIKE 'rating'");
    if ($columns->rowCount() == 0) {
        $conn->exec("ALTER TABLE comments ADD COLUMN rating INT DEFAULT 5 AFTER username");
    }
} catch (Exception $e) {
    // Column might already exist
}

// Fetch comments with ratings and replies
$comments_query = "
    SELECT c.*, 
           COUNT(cr.id) as reply_count
    FROM comments c 
    LEFT JOIN comment_replies cr ON c.id = cr.comment_id 
    GROUP BY c.id 
    ORDER BY c.created_at DESC
";
$comments = $conn->query($comments_query)->fetchAll();

// Function to get replies for a comment
function getReplies($conn, $comment_id) {
    $stmt = $conn->prepare("SELECT * FROM comment_replies WHERE comment_id = ? ORDER BY created_at ASC");
    $stmt->execute([$comment_id]);
    return $stmt->fetchAll();
}

// Calculate average rating
$avg_rating = 0;
$total_ratings = 0;
foreach ($comments as $comment) {
    if (isset($comment['rating']) && $comment['rating'] > 0) {
        $avg_rating += $comment['rating'];
        $total_ratings++;
    }
}
$avg_rating = $total_ratings > 0 ? round($avg_rating / $total_ratings, 1) : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Comments & Ratings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            min-height: 100vh;
            padding: 20px;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h2 { 
            color: #333; 
            font-size: 2rem;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stats-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .avg-rating {
            font-size: 2.5rem;
            color: #ffd700;
            margin: 10px 0;
        }
        
        .rating-info {
            color: #666;
            font-size: 1.1rem;
        }
        
        .comments-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        
        table th, table td { 
            padding: 15px; 
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th { 
            background: linear-gradient(45deg, #667eea, #764ba2); 
            color: white; 
            font-weight: 600;
        }
        
        tr:hover { 
            background: rgba(102, 126, 234, 0.05); 
        }
        
        .stars {
            color: #ffd700;
            font-size: 1.2rem;
            margin-bottom: 5px;
        }
        
        .rating-number {
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .message-cell {
            max-width: 300px;
            word-wrap: break-word;
        }
        
        .back-btn { 
            text-decoration: none; 
            background: linear-gradient(45deg, #667eea, #764ba2); 
            color: white; 
            padding: 12px 20px; 
            border-radius: 25px; 
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .no-comments {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 1.1rem;
        }
        
        .comment-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .comment-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
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
        
        .comment-body {
            margin: 20px 0;
            color: #555;
            line-height: 1.6;
            font-size: 1rem;
        }
        
        .comment-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .reply-btn {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .reply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            background: linear-gradient(45deg, #5a67d8, #6b46c1);
        }
        
        .reply-count {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .reply-form {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .reply-form.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .reply-textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            transition: all 0.3s ease;
        }
        
        .reply-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.2);
        }
        
        .reply-form-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .submit-reply-btn {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .submit-reply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }
        
        .cancel-reply-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .cancel-reply-btn:hover {
            background: #5a6268;
        }
        
        .replies-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .replies-header {
            font-size: 1rem;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .reply-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 3px solid #667eea;
        }
        
        .reply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .reply-admin {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #667eea;
        }
        
        .reply-date {
            color: #999;
            font-size: 0.85rem;
        }
        
        .reply-text {
            color: #555;
            line-height: 1.5;
        }
        
        .delete-reply-btn {
            background: #ff6b6b;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 15px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        
        .delete-reply-btn:hover {
            background: #ee5a24;
        }
        
        .success-message {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 2px solid rgba(16, 185, 129, 0.3);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            table, thead, tbody, th, td, tr {
                display: block;
            }
            
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            tr {
                border: 1px solid #ccc;
                margin-bottom: 10px;
                padding: 10px;
                border-radius: 10px;
                background: white;
            }
            
            td {
                border: none;
                position: relative;
                padding: 10px 10px 10px 35%;
            }
            
            td:before {
                content: attr(data-label) ": ";
                position: absolute;
                left: 6px;
                width: 30%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: bold;
                color: #667eea;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <h2><i class="fas fa-comments"></i> User Comments & Ratings</h2>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<?php if ($message): ?>
    <div class="success-message">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($total_ratings > 0): ?>
<div class="stats-card">
    <h3>Overall Rating</h3>
    <div class="avg-rating">
        <?php 
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= floor($avg_rating)) {
                echo '<i class="fas fa-star"></i>';
            } elseif ($i <= $avg_rating) {
                echo '<i class="fas fa-star-half-alt"></i>';
            } else {
                echo '<i class="far fa-star"></i>';
            }
        }
        ?>
    </div>
    <div class="rating-info">
        Average: <?= $avg_rating ?>/5 stars (<?= $total_ratings ?> ratings)
    </div>
</div>
<?php endif; ?>

<div class="comments-container">
    <?php if (empty($comments)): ?>
        <div class="no-comments">
            <i class="fas fa-comment-slash" style="font-size: 3rem; color: #ddd; margin-bottom: 20px;"></i>
            <p>No comments yet. Encourage customers to share their feedback!</p>
            <p style="margin-top: 10px; color: #999; font-size: 0.9rem;">
                When customers write reviews, you'll be able to reply to them here.
            </p>
        </div>
    <?php else: ?>
        <div style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; border: 1px solid rgba(16, 185, 129, 0.2);">
            <i class="fas fa-info-circle"></i> 
            <strong>Admin Instructions:</strong> Click the "Reply to Customer" button below each review to respond to customers.
        </div>
        <?php foreach ($comments as $comment): ?>
            <div class="comment-card">
                <div class="comment-header">
                    <div class="comment-user">
                        <div class="user-avatar">
                            <?= strtoupper(substr($comment['username'], 0, 1)) ?>
                        </div>
                        <div class="user-info">
                            <h4><?= htmlspecialchars($comment['username']) ?></h4>
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
                
                <div class="comment-actions">
                    <button class="reply-btn" onclick="toggleReplyForm(<?= $comment['id'] ?>)">
                        <i class="fas fa-reply"></i> Reply to Customer
                    </button>
                    <?php if ($comment['reply_count'] > 0): ?>
                        <span class="reply-count">
                            <i class="fas fa-comments"></i> <?= $comment['reply_count'] ?> 
                            <?= $comment['reply_count'] == 1 ? 'Reply' : 'Replies' ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Reply Form -->
                <div class="reply-form" id="replyForm<?= $comment['id'] ?>">
                    <form method="POST">
                        <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                        <textarea name="reply_message" class="reply-textarea" 
                                  placeholder="Write your reply to <?= htmlspecialchars($comment['username']) ?>..." 
                                  required></textarea>
                        <div class="reply-form-actions">
                            <button type="submit" name="submit_reply" class="submit-reply-btn">
                                <i class="fas fa-paper-plane"></i> Post Reply
                            </button>
                            <button type="button" class="cancel-reply-btn" onclick="toggleReplyForm(<?= $comment['id'] ?>)">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Existing Replies -->
                <?php 
                $replies = getReplies($conn, $comment['id']);
                if (!empty($replies)): 
                ?>
                    <div class="replies-section">
                        <div class="replies-header">
                            <i class="fas fa-reply-all"></i> 
                            <?= count($replies) ?> <?= count($replies) == 1 ? 'Reply' : 'Replies' ?>
                        </div>
                        <?php foreach ($replies as $reply): ?>
                            <div class="reply-item">
                                <div class="reply-header">
                                    <div class="reply-admin">
                                        <i class="fas fa-user-shield"></i>
                                        <?= htmlspecialchars($reply['admin_name']) ?>
                                        <span style="font-weight: normal; color: #999;">(Admin)</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="reply-date">
                                            <?= date('M j, Y g:i A', strtotime($reply['created_at'])) ?>
                                        </div>
                                        <button class="delete-reply-btn" 
                                                onclick="deleteReply(<?= $reply['id'] ?>)"
                                                title="Delete Reply">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

<script>
function toggleReplyForm(commentId) {
    const form = document.getElementById('replyForm' + commentId);
    const allForms = document.querySelectorAll('.reply-form');
    
    // Close all other forms
    allForms.forEach(f => {
        if (f.id !== 'replyForm' + commentId) {
            f.classList.remove('show');
        }
    });
    
    // Toggle current form
    form.classList.toggle('show');
    
    // Focus on textarea if opening
    if (form.classList.contains('show')) {
        const textarea = form.querySelector('textarea');
        setTimeout(() => textarea.focus(), 100);
    }
}

function deleteReply(replyId) {
    if (confirm('Are you sure you want to delete this reply?')) {
        window.location.href = '?delete_reply=' + replyId;
    }
}

// Auto-hide success message after 5 seconds
setTimeout(() => {
    const message = document.querySelector('.success-message');
    if (message) {
        message.style.opacity = '0';
        message.style.transform = 'translateY(-20px)';
        setTimeout(() => message.remove(), 300);
    }
}, 5000);
</script>

</body>
</html>
