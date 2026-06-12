<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['name'];
$message = "";

// Handle mark as read
if (isset($_GET['mark_read'])) {
    $report_id = intval($_GET['mark_read']);
    try {
        $stmt = $conn->prepare("UPDATE reports SET status = 'read', read_at = NOW() WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$report_id, $admin_id]);
        $message = "Report marked as read.";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Handle reply to chef
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reply'])) {
    $original_report_id = intval($_POST['original_report_id']);
    $reply_message = trim($_POST['reply_message']);
    $priority = $_POST['reply_priority'] ?? 'medium';
    
    if (!empty($reply_message)) {
        try {
            // Get original report details
            $stmt = $conn->prepare("SELECT sender_id, title FROM reports WHERE id = ? AND receiver_id = ?");
            $stmt->execute([$original_report_id, $admin_id]);
            $original = $stmt->fetch();
            
            if ($original) {
                // Send reply
                $reply_title = "Re: " . $original['title'];
                $stmt = $conn->prepare("INSERT INTO reports (sender_id, receiver_id, title, message, type, priority) VALUES (?, ?, ?, ?, 'admin_to_chef', ?)");
                $stmt->execute([$admin_id, $original['sender_id'], $reply_title, $reply_message, $priority]);
                
                // Mark original as replied
                $conn->prepare("UPDATE reports SET status = 'replied' WHERE id = ?")->execute([$original_report_id]);
                
                $message = "Reply sent successfully to chef!";
            }
        } catch (Exception $e) {
            $message = "Error sending reply: " . $e->getMessage();
        }
    }
}

// Handle send new report to chef
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['send_new'])) {
    $chef_id = intval($_POST['chef_id']);
    $title = trim($_POST['title']);
    $new_message = trim($_POST['message']);
    $priority = $_POST['priority'] ?? 'medium';
    
    if (!empty($title) && !empty($new_message) && $chef_id > 0) {
        try {
            $stmt = $conn->prepare("INSERT INTO reports (sender_id, receiver_id, title, message, type, priority) VALUES (?, ?, ?, ?, 'admin_to_chef', ?)");
            if ($stmt->execute([$admin_id, $chef_id, $title, $new_message, $priority])) {
                $message = "Report sent successfully to chef!";
            }
        } catch (Exception $e) {
            $message = "Error sending report: " . $e->getMessage();
        }
    }
}

// Get all chefs for dropdown
$chefs = $conn->query("SELECT id, name, email FROM users WHERE role = 'chef' ORDER BY name ASC")->fetchAll();

// Get received reports from chefs
$received_reports = $conn->prepare("
    SELECT r.*, u.name as chef_name, u.email as chef_email
    FROM reports r
    JOIN users u ON r.sender_id = u.id
    WHERE r.receiver_id = ? AND r.type = 'chef_to_admin'
    ORDER BY r.created_at DESC
");
$received_reports->execute([$admin_id]);
$received_reports = $received_reports->fetchAll();

// Get sent reports to chefs
$sent_reports = $conn->prepare("
    SELECT r.*, u.name as chef_name, u.email as chef_email
    FROM reports r
    JOIN users u ON r.receiver_id = u.id
    WHERE r.sender_id = ? AND r.type = 'admin_to_chef'
    ORDER BY r.created_at DESC
    LIMIT 10
");
$sent_reports->execute([$admin_id]);
$sent_reports = $sent_reports->fetchAll();

// Count unread reports
$unread_count = $conn->prepare("SELECT COUNT(*) FROM reports WHERE receiver_id = ? AND status = 'unread' AND type = 'chef_to_admin'");
$unread_count->execute([$admin_id]);
$unread_count = $unread_count->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
<title>Chef Reports - Admin Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
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
    background: linear-gradient(45deg, #667eea, #764ba2); 
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

.container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }

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

.tabs-container {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    justify-content: center;
}

.tab-btn {
    padding: 12px 25px;
    background: rgba(255,255,255,0.9);
    border: 2px solid transparent;
    border-radius: 25px;
    cursor: pointer;
    transition: 0.3s;
    font-weight: 500;
    text-decoration: none;
    color: #333;
}

.tab-btn.active,
.tab-btn:hover {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    border-color: rgba(255,255,255,0.3);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.main-layout {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 30px;
}

.reports-section {
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
    border-left: 5px solid #667eea;
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
    border-left: 4px solid #667eea;
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

.form-group {
    margin-bottom: 15px;
}

.form-label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #333;
}

.form-input,
.form-select,
.form-textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.9rem;
}

.form-textarea {
    resize: vertical;
    min-height: 100px;
}

.form-actions {
    display: flex;
    gap: 10px;
}

.btn-send { background: #28a745; color: white; }
.btn-cancel { background: #6c757d; color: white; }

.sidebar {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.send-report-form {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid rgba(255,255,255,0.3);
}

.form-title {
    font-size: 1.3rem;
    font-weight: bold;
    color: #333;
    margin-bottom: 20px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.priority-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.priority-option {
    position: relative;
}

.priority-radio {
    display: none;
}

.priority-label {
    display: block;
    padding: 8px;
    text-align: center;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.3s;
    font-weight: 500;
    font-size: 0.85rem;
}

.priority-radio:checked + .priority-label {
    border-color: #667eea;
    background: rgba(102,126,234,0.1);
    color: #667eea;
}

.submit-btn {
    width: 100%;
    padding: 12px;
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: 0.3s;
    box-shadow: 0 4px 15px rgba(102,126,234,0.3);
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102,126,234,0.4);
}

.recent-sent {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid rgba(255,255,255,0.3);
}

.sent-item {
    padding: 12px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    margin-bottom: 10px;
    font-size: 0.9rem;
}

.sent-title {
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.sent-meta {
    color: #666;
    font-size: 0.8rem;
}

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
    background: #667eea;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}

@media (max-width: 1024px) {
    .main-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .tabs-container {
        flex-direction: column;
    }
    
    .priority-options {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
<header>
    <h1>
        <i class="fas fa-comments"></i> 
        Chef Reports
        <?php if ($unread_count > 0): ?>
            <span style="background: #667eea; color: white; padding: 4px 10px; border-radius: 15px; font-size: 0.8rem; margin-left: 10px;">
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

    <!-- Tabs -->
    <div class="tabs-container">
        <button class="tab-btn active" onclick="showTab('received')">
            <i class="fas fa-inbox"></i> Received Reports (<?= count($received_reports) ?>)
        </button>
        <button class="tab-btn" onclick="showTab('sent')">
            <i class="fas fa-paper-plane"></i> Sent Reports (<?= count($sent_reports) ?>)
        </button>
    </div>

    <div class="main-layout">
        <!-- Received Reports Tab -->
        <div id="received-tab" class="tab-content active">
            <?php if (empty($received_reports)): ?>
                <div class="no-reports">
                    <i class="fas fa-inbox"></i>
                    <h3>No Reports Received</h3>
                    <p>You haven't received any reports from chefs yet.</p>
                </div>
            <?php else: ?>
                <div class="reports-section">
                    <?php foreach ($received_reports as $report): ?>
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
                                <i class="fas fa-chef-hat"></i>
                                From: <?= htmlspecialchars($report['chef_name']) ?> (<?= htmlspecialchars($report['chef_email']) ?>)
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
                                        <i class="fas fa-reply"></i> Reply to Chef
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Reply Form -->
                            <div class="reply-form" id="reply-form-<?= $report['id'] ?>">
                                <form method="POST">
                                    <input type="hidden" name="original_report_id" value="<?= $report['id'] ?>">
                                    
                                    <div class="form-group">
                                        <label class="form-label">Priority Level</label>
                                        <div class="priority-options">
                                            <div class="priority-option">
                                                <input type="radio" name="reply_priority" value="low" id="reply-priority-low-<?= $report['id'] ?>" class="priority-radio">
                                                <label for="reply-priority-low-<?= $report['id'] ?>" class="priority-label">Low</label>
                                            </div>
                                            <div class="priority-option">
                                                <input type="radio" name="reply_priority" value="medium" id="reply-priority-medium-<?= $report['id'] ?>" class="priority-radio" checked>
                                                <label for="reply-priority-medium-<?= $report['id'] ?>" class="priority-label">Medium</label>
                                            </div>
                                            <div class="priority-option">
                                                <input type="radio" name="reply_priority" value="high" id="reply-priority-high-<?= $report['id'] ?>" class="priority-radio">
                                                <label for="reply-priority-high-<?= $report['id'] ?>" class="priority-label">High</label>
                                            </div>
                                            <div class="priority-option">
                                                <input type="radio" name="reply_priority" value="urgent" id="reply-priority-urgent-<?= $report['id'] ?>" class="priority-radio">
                                                <label for="reply-priority-urgent-<?= $report['id'] ?>" class="priority-label">Urgent</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Reply Message</label>
                                        <textarea name="reply_message" class="form-textarea" placeholder="Write your reply to the chef..." required></textarea>
                                    </div>
                                    
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

        <!-- Sent Reports Tab -->
        <div id="sent-tab" class="tab-content">
            <?php if (empty($sent_reports)): ?>
                <div class="no-reports">
                    <i class="fas fa-paper-plane"></i>
                    <h3>No Reports Sent</h3>
                    <p>You haven't sent any reports to chefs yet.</p>
                </div>
            <?php else: ?>
                <div class="reports-section">
                    <?php foreach ($sent_reports as $report): ?>
                        <div class="report-card">
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
                                <i class="fas fa-chef-hat"></i>
                                To: <?= htmlspecialchars($report['chef_name']) ?> (<?= htmlspecialchars($report['chef_email']) ?>)
                            </div>

                            <div class="report-date">
                                <i class="fas fa-clock"></i>
                                <?= date('M j, Y g:i A', strtotime($report['created_at'])) ?>
                            </div>

                            <div class="report-message">
                                <?= nl2br(htmlspecialchars($report['message'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Send New Report Form -->
            <div class="send-report-form">
                <div class="form-title">
                    <i class="fas fa-paper-plane"></i>
                    Send Report to Chef
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Send to Chef</label>
                        <select name="chef_id" class="form-select" required>
                            <option value="">Select Chef</option>
                            <?php foreach ($chefs as $chef): ?>
                                <option value="<?= $chef['id'] ?>">
                                    <?= htmlspecialchars($chef['name']) ?> (<?= htmlspecialchars($chef['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Report Title</label>
                        <input type="text" name="title" class="form-input" placeholder="Enter report title..." required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Priority Level</label>
                        <div class="priority-options">
                            <div class="priority-option">
                                <input type="radio" name="priority" value="low" id="new-priority-low" class="priority-radio">
                                <label for="new-priority-low" class="priority-label">Low</label>
                            </div>
                            <div class="priority-option">
                                <input type="radio" name="priority" value="medium" id="new-priority-medium" class="priority-radio" checked>
                                <label for="new-priority-medium" class="priority-label">Medium</label>
                            </div>
                            <div class="priority-option">
                                <input type="radio" name="priority" value="high" id="new-priority-high" class="priority-radio">
                                <label for="new-priority-high" class="priority-label">High</label>
                            </div>
                            <div class="priority-option">
                                <input type="radio" name="priority" value="urgent" id="new-priority-urgent" class="priority-radio">
                                <label for="new-priority-urgent" class="priority-label">Urgent</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Report Message</label>
                        <textarea name="message" class="form-textarea" placeholder="Write your report message..." required></textarea>
                    </div>

                    <button type="submit" name="send_new" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> Send Report
                    </button>
                </form>
            </div>

            <!-- Recent Sent Reports -->
            <div class="recent-sent">
                <div class="form-title">
                    <i class="fas fa-history"></i>
                    Recent Sent
                </div>
                
                <?php if (empty($sent_reports)): ?>
                    <div style="text-align: center; color: #666; padding: 20px;">
                        No reports sent yet
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($sent_reports, 0, 5) as $report): ?>
                        <div class="sent-item">
                            <div class="sent-title"><?= htmlspecialchars($report['title']) ?></div>
                            <div class="sent-meta">
                                To: <?= htmlspecialchars($report['chef_name']) ?> • 
                                <?= date('M j, g:i A', strtotime($report['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}

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