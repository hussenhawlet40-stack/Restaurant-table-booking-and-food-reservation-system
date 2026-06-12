<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'chef') {
    header("Location: ../login.php");
    exit;
}

$chef_id = $_SESSION['user_id'];
$chef_name = $_SESSION['name'];
$message = "";

// Handle report submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST['title'] ?? '');
    $report_message = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $admin_id = intval($_POST['admin_id'] ?? 0);
    
    if (empty($title) || empty($report_message)) {
        $message = "Please fill in all required fields.";
    } elseif ($admin_id <= 0) {
        $message = "Please select an admin to send the report to.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO reports (sender_id, receiver_id, title, message, type, priority) VALUES (?, ?, ?, ?, 'chef_to_admin', ?)");
            if ($stmt->execute([$chef_id, $admin_id, $title, $report_message, $priority])) {
                $message = "Report sent successfully to admin!";
                // Clear form
                $title = $report_message = '';
                $priority = 'medium';
                $admin_id = 0;
            } else {
                $message = "Failed to send report. Please try again.";
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Get all admins
$admins = $conn->query("SELECT id, name, email FROM users WHERE role = 'admin' ORDER BY name ASC")->fetchAll();

// Get recent sent reports
$recent_reports = $conn->prepare("
    SELECT r.*, u.name as admin_name 
    FROM reports r 
    JOIN users u ON r.receiver_id = u.id 
    WHERE r.sender_id = ? 
    ORDER BY r.created_at DESC 
    LIMIT 10
");
$recent_reports->execute([$chef_id]);
$recent_reports = $recent_reports->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<title>Send Report to Admin - Chef Dashboard</title>
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

.main-layout {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 30px;
}

.report-form {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid rgba(255,255,255,0.3);
}

.form-title {
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
    margin-bottom: 25px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.form-input,
.form-select,
.form-textarea {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: #ff9a56;
    box-shadow: 0 0 20px rgba(255,154,86,0.2);
}

.form-textarea {
    resize: vertical;
    min-height: 120px;
}

.priority-options {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
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
    padding: 10px;
    text-align: center;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.3s;
    font-weight: 500;
}

.priority-radio:checked + .priority-label {
    border-color: #ff9a56;
    background: rgba(255,154,86,0.1);
    color: #ff6b35;
}

.priority-low { color: #28a745; }
.priority-medium { color: #ffc107; }
.priority-high { color: #fd7e14; }
.priority-urgent { color: #dc3545; }

.submit-btn {
    width: 100%;
    padding: 15px;
    background: linear-gradient(45deg, #ff9a56, #ff6b35);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 1.1rem;
    font-weight: 500;
    cursor: pointer;
    transition: 0.3s;
    box-shadow: 0 4px 15px rgba(255,154,86,0.3);
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255,154,86,0.4);
}

.recent-reports {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid rgba(255,255,255,0.3);
    height: fit-content;
}

.reports-title {
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

.report-item {
    padding: 15px;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    margin-bottom: 15px;
    transition: 0.3s;
}

.report-item:hover {
    border-color: #ff9a56;
    box-shadow: 0 4px 15px rgba(255,154,86,0.1);
}

.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.report-title {
    font-weight: bold;
    color: #333;
    font-size: 0.95rem;
}

.report-priority {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.report-meta {
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 8px;
}

.report-status {
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-unread { background: #fff3cd; color: #856404; }
.status-read { background: #d4edda; color: #155724; }
.status-replied { background: #cce5ff; color: #004085; }

.no-reports {
    text-align: center;
    color: #666;
    padding: 40px 20px;
}

.no-reports i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

@media (max-width: 1024px) {
    .main-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .priority-options {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
</head>
<body>
<header>
    <h1><i class="fas fa-paper-plane"></i> Send Report to Admin</h1>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</header>

<div class="container">
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="main-layout">
        <!-- Report Form -->
        <div class="report-form">
            <div class="form-title">
                <i class="fas fa-edit"></i>
                Create New Report
            </div>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Send to Admin *</label>
                    <select name="admin_id" class="form-select" required>
                        <option value="">Select Admin</option>
                        <?php foreach ($admins as $admin): ?>
                            <option value="<?= $admin['id'] ?>" <?= (isset($_POST['admin_id']) && $_POST['admin_id'] == $admin['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($admin['name']) ?> (<?= htmlspecialchars($admin['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Report Title *</label>
                    <input type="text" name="title" class="form-input" placeholder="Enter report title..." 
                           value="<?= htmlspecialchars($title ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Priority Level</label>
                    <div class="priority-options">
                        <div class="priority-option">
                            <input type="radio" name="priority" value="low" id="priority-low" class="priority-radio" 
                                   <?= (isset($priority) && $priority === 'low') ? 'checked' : '' ?>>
                            <label for="priority-low" class="priority-label priority-low">
                                <i class="fas fa-circle"></i> Low
                            </label>
                        </div>
                        <div class="priority-option">
                            <input type="radio" name="priority" value="medium" id="priority-medium" class="priority-radio" 
                                   <?= (!isset($priority) || $priority === 'medium') ? 'checked' : '' ?>>
                            <label for="priority-medium" class="priority-label priority-medium">
                                <i class="fas fa-circle"></i> Medium
                            </label>
                        </div>
                        <div class="priority-option">
                            <input type="radio" name="priority" value="high" id="priority-high" class="priority-radio" 
                                   <?= (isset($priority) && $priority === 'high') ? 'checked' : '' ?>>
                            <label for="priority-high" class="priority-label priority-high">
                                <i class="fas fa-circle"></i> High
                            </label>
                        </div>
                        <div class="priority-option">
                            <input type="radio" name="priority" value="urgent" id="priority-urgent" class="priority-radio" 
                                   <?= (isset($priority) && $priority === 'urgent') ? 'checked' : '' ?>>
                            <label for="priority-urgent" class="priority-label priority-urgent">
                                <i class="fas fa-exclamation-circle"></i> Urgent
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Report Message *</label>
                    <textarea name="message" class="form-textarea" placeholder="Write your detailed report here..." required><?= htmlspecialchars($report_message ?? '') ?></textarea>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Send Report
                </button>
            </form>
        </div>

        <!-- Recent Reports -->
        <div class="recent-reports">
            <div class="reports-title">
                <i class="fas fa-history"></i>
                Recent Reports
            </div>

            <?php if (empty($recent_reports)): ?>
                <div class="no-reports">
                    <i class="fas fa-inbox"></i>
                    <p>No reports sent yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_reports as $report): ?>
                    <div class="report-item">
                        <div class="report-header">
                            <div class="report-title"><?= htmlspecialchars($report['title']) ?></div>
                            <div class="report-priority priority-<?= $report['priority'] ?>">
                                <?= ucfirst($report['priority']) ?>
                            </div>
                        </div>
                        <div class="report-meta">
                            To: <?= htmlspecialchars($report['admin_name']) ?> • 
                            <?= date('M j, Y g:i A', strtotime($report['created_at'])) ?>
                        </div>
                        <div class="report-status status-<?= $report['status'] ?>">
                            <?= ucfirst($report['status']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Add entrance animations
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.report-form, .recent-reports');
    elements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(30px)';
        element.style.transition = `opacity 0.6s ease ${index * 0.2}s, transform 0.6s ease ${index * 0.2}s`;
        
        setTimeout(() => {
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, 100 + (index * 200));
    });

    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const title = document.querySelector('input[name="title"]').value.trim();
        const message = document.querySelector('textarea[name="message"]').value.trim();
        const adminId = document.querySelector('select[name="admin_id"]').value;

        if (!title || !message || !adminId) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });
});
</script>
</body>
</html>