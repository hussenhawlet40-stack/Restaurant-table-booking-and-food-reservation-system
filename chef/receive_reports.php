<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'chef') {
    header("Location: ../login.php");
    exit;
}

$chef_id = $_SESSION['user_id'];
$message = "";

// Handle mark as read
if (isset($_GET['mark_read'])) {
    $report_id = intval($_GET['mark_read']);
    try {
        $stmt = $conn->prepare("UPDATE reports SET status = 'read', read_at = NOW() WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$report_id, $chef_id]);
        $message = "Report marked as read.";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Handle reply
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reply'])) {
    $original_report_id = intval($_POST['original_report_id']);
    $reply_message = trim($_POST['reply_message']);
    
    if (!empty($reply_message)) {
        try {
            // Get original report details
            $stmt = $conn->prepare("SELECT sender_id, title FROM reports WHERE id = ? AND receiver_id = ?");
            $stmt->execute([$original_report_id, $chef_id]);
            $original = $stmt->fetch();
            
            if ($original) {
                // Send reply
                $reply_title = "Re: " . $original['title'];
                $stmt = $conn->prepare("INSERT INTO reports (sender_id, receiver_id, title, message, type) VALUES (?, ?, ?, ?, 'chef_to_admin')");
                $stmt->execute([$chef_id, $original['sender_id'], $reply_title, $reply_message]);
                
                // Mark original as replied
                $conn->prepare("UPDATE reports SET status = 'replied' WHERE id = ?")->execute([$original_report_id]);
                
                $message = "Reply sent successfully!";
            }
        } catch (Exception $e) {
            $message = "Error sending reply: " . $e->getMessage();
        }
    }
}

// Get received reports
$reports = $conn->prepare("
    SELECT r.*, u.name as sender_name, u.email as sender_email
    FROM reports r
    JOIN users u ON r.sender_id = u.id
    WHERE r.receiver_id = ? AND r.type IN ('admin_to_chef', 'general')
    ORDER BY r.created_at DESC
");
$reports->execute([$chef_id]);
$reports = $reports->fetchAll();

// Count unread reports
$unread_count = $conn->prepare("SELECT COUNT(*) FROM reports WHERE receiver_id = ? AND status = 'unread'");
$unread_count->execute([$chef_id]);
$unread_count = $unread_count->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
<title>Receive Reports - Chef Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #ff9a56 0%, #ff6b35 100%);
    min-height: 100vh;
}

header { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    padding: 20px 30px; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
    position: sticky; 
    top: 0; 
    z-index: 100; 
}
header h1 { 
    background: linear-gradient(45deg, #ff9a56, #ff6b35); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
    font-size: 2rem; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
}
.back-btn { 
    background: linear-gradient(45deg, #4ecdc4, #44a08d); 
    color: white; 
    padding: 12px 20px; 
    text-decoration: none; 
    border-radius: 25px; 
    font-weight: 500; 
    transition: 0.3s; 
    box-shadow: 0 4px 15px rgba(78,205,196,0.3); 
}
.back-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(78,205,196,0.4); }

.container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }

.message { 
    background: rgba(78,205,196,0.1); 
    color: #4ecdc4; 
    border: 2px solid rgba(78,205,196,0.3); 
    padding: 15px; 
    border-radius: 15px; 
    margin-bottom: 30px; 
    text-align: center; 
    font-weight: 500; 
    backdrop-filter: blur(10px); 
}

.stats-bar {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-around;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #ff6b35;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

.reports-container {
    display: grid;
    gap: 20px;
}

.report-card {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid rgba(255,255,255,0.3);
    transition: 0.3s;
    position: relative;
}

.report-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

.report-card.unread {
    border-left: 5px solid #ff6b35;
}

.report-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    gap: 15px;
}

.report-title {
    font-size: 1.2rem;
    font-weight: bold;
    color: #333;
    flex: 1;
}

.report-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 5px;
}

.priority-badge {
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.priority-low { background: #d4edda; color: #155724; }
.priority-medium { background: #fff3cd; color: #856404; }
.priority-high { background: #f8d7da; color: #721c24; }
.priority-urgent { background: #dc3545; color: white; }

.status-badge {
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-unread { background: #fff3cd; color: #856404; }
.status-read { background: #d4edda; color: #155724; }
.status-replied { background: #cce5ff; color: #004085; }

.report-sender {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.report-date {
    color: #999;
    font-size: 0.85rem;
}

.report-message {
    color: #333;
    line-height: 1.6;
    margin-bottom: 20px;
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    border-left: 4px solid #ff9a56;
}

.report-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 15px;
    border: none;
    border-radius: 20px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

.btn-read { background: #28a745; color: white; }
.btn-reply { background: #007bff; color: white; }
.btn:hover { transform: translateY(-2px); }

.reply-form {
    display: none;
    margin-top: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 2px solid #e0e0e0;
}

.reply-textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    resize: vertical;
    min-height: 100px;
    margin-bottom: 15px;
}

.form-actions {
    display: flex;
    gap: 10px;
}

.btn-send { background: #28a745; color: white; }
.btn-cancel { background: #6c757d; color: white; }

.no-reports {
    text-align: center;
    padding: 60px 20px;
    background: rgba(255,255,255,0.95);
    border-radius: 15px;
}

.no-reports i {
    font-size: 4rem;
    color: #ddd;
    margin-bottom: 20px;
}

.unread-indicator {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 12px;
    height: 12px;
    background: #ff6b35;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}

@media (max-width: 768px) {
    .stats-bar {
        flex-direction: column;
        gap: 20px;
    }
    
    .report-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .report-meta {
        align-items: flex-start;
        flex-direction: row;
        gap: 10px;
    }
}
</style>
</head>
<body>
<header>
    <h1>
        <i class="fas fa-inbox"></i> 
        Receive Reports
        <?php if ($unread_count > 0): ?>
            <span style="background: #ff6b35; color: white; padding: 4px 10px; border-radius: 15px; font-size: 0.8rem; margin-left: 10px;">
                <?= $unread_count ?> new
            </span>
        <?php endif; ?>
    </h1>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</header>

<div class="container">
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-number"><?= count($reports) ?></div>
            <div class="stat-label">Total Reports</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= $unread_count ?></div>
            <div class="stat-label">Unread</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= count(array_filter($reports, fn($r) => $r['status'] === 'read')) ?></div>
            <div class="stat-label">Read</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= count(array_filter($reports, fn($r) => $r['status'] === 'replied')) ?></div>
            <div class="stat-label">Replied</div>
        </div>
    </div>

    <?php if (empty($reports)): ?>
        <div class="no-reports">
            <i class="fas fa-inbox"></i>
            <h3>No Reports Received</h3>
            <p>You haven't received any reports from admins yet.</p>
        </div>
    <?php else: ?>
        <div class="reports-container">
            <?php foreach ($reports as $report): ?>
                <div class="report-card <?= $report['status'] === 'unread' ? 'unread' : '' ?>">
                    <?php if ($report['status'] === 'unread'): ?>
                        <div class="unread-indicator"></div>
                    <?php endif; ?>
                    
                    <div class="report-header">
                        <div class="report-title"><?= htmlspecialchars($report['title']) ?></div>
                        <div class="report-meta">
                            <div class="priority-badge priority-<?= $report['priority'] ?>">
                                <?= ucfirst($report['priority']) ?> Priority
                            </div>
                            <div class="status-badge status-<?= $report['status'] ?>">
                                <?= ucfirst($report['status']) ?>
                            </div>
                        </div>
                    </div>

                    <div class="report-sender">
                        <i class="fas fa-user"></i>
                        From: <?= htmlspecialchars($report['sender_name']) ?> (<?= htmlspecialchars($report['sender_email']) ?>)
                    </div>

                    <div class="report-date">
                        <i class="fas fa-clock"></i>
                        <?= date('M j, Y g:i A', strtotime($report['created_at'])) ?>
                        <?php if ($report['read_at']): ?>
                            • Read: <?= date('M j, Y g:i A', strtotime($report['read_at'])) ?>
                        <?php endif; ?>
                    </div>

                    <div class="report-message">
                        <?= nl2br(htmlspecialchars($report['message'])) ?>
                    </div>

                    <div class="report-actions">
                        <?php if ($report['status'] === 'unread'): ?>
                            <a href="?mark_read=<?= $report['id'] ?>" class="btn btn-read">
                                <i class="fas fa-check"></i> Mark as Read
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($report['status'] !== 'replied'): ?>
                            <button type="button" class="btn btn-reply" onclick="toggleReply(<?= $report['id'] ?>)">
                                <i class="fas fa-reply"></i> Reply
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Reply Form -->
                    <div class="reply-form" id="reply-form-<?= $report['id'] ?>">
                        <form method="POST">
                            <input type="hidden" name="original_report_id" value="<?= $report['id'] ?>">
                            <textarea name="reply_message" class="reply-textarea" placeholder="Write your reply..." required></textarea>
                            <div class="form-actions">
                                <button type="submit" name="reply" class="btn btn-send">
                                    <i class="fas fa-paper-plane"></i> Send Reply
                                </button>
                                <button type="button" class="btn btn-cancel" onclick="toggleReply(<?= $report['id'] ?>)">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleReply(reportId) {
    const form = document.getElementById(`reply-form-${reportId}`);
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
        form.querySelector('textarea').focus();
    } else {
        form.style.display = 'none';
    }
}

// Add entrance animations
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.report-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 + (index * 100));
    });
});
</script>
</body>
</html>