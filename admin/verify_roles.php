<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$message = "";
$error = "";

// Handle role change
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['change_role'])) {
    $user_id = intval($_POST['user_id']);
    $new_role = $_POST['new_role'];
    
    // Validate role
    if (in_array($new_role, ['user', 'chef', 'admin'])) {
        try {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            if ($stmt->execute([$new_role, $user_id])) {
                $message = "User role updated successfully to " . ucfirst($new_role);
            } else {
                $error = "Failed to update user role";
            }
        } catch (Exception $e) {
            $error = "Error updating role: " . $e->getMessage();
        }
    } else {
        $error = "Invalid role selected";
    }
}

// Handle user deletion
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    
    // Prevent deleting current admin
    if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account";
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $message = "User deleted successfully";
            } else {
                $error = "Failed to delete user";
            }
        } catch (Exception $e) {
            $error = "Error deleting user: " . $e->getMessage();
        }
    }
}

// Get all users
try {
    $users = $conn->query("
        SELECT u.*, 
               COUNT(DISTINCT b.id) as total_bookings,
               COUNT(DISTINCT po.id) as total_orders,
               COALESCE(SUM(po.total_amount), 0) as total_spent
        FROM users u
        LEFT JOIN bookings b ON u.id = b.user_id
        LEFT JOIN pre_orders po ON b.id = po.booking_id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ")->fetchAll();
} catch (Exception $e) {
    $users = [];
    $error = "Error loading users: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
<title>User Management - Admin Dashboard</title>
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

.error { 
    background: rgba(220,53,69,0.1); 
    color: #dc3545; 
    border: 2px solid rgba(220,53,69,0.3); 
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transition: 0.3s;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-icon.users { background: linear-gradient(45deg, #667eea, #764ba2); }
.stat-icon.chefs { background: linear-gradient(45deg, #f093fb, #f5576c); }
.stat-icon.admins { background: linear-gradient(45deg, #4facfe, #00f2fe); }

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: bold;
    color: #333;
    line-height: 1;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
    font-weight: 500;
}

.users-table {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid rgba(255,255,255,0.3);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.table-title {
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.users-grid {
    display: grid;
    gap: 15px;
}

.user-card {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    gap: 20px;
    align-items: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    transition: 0.3s;
}

.user-card:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.user-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.user-name {
    font-weight: bold;
    color: #333;
    font-size: 1.1rem;
}

.user-email {
    color: #666;
    font-size: 0.9rem;
}

.user-meta {
    color: #999;
    font-size: 0.8rem;
}

.role-badge {
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
}

.role-user { background: #e3f2fd; color: #1976d2; }
.role-chef { background: #fff3e0; color: #f57c00; }
.role-admin { background: #f3e5f5; color: #7b1fa2; }

.user-stats {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    text-align: center;
}

.stat-number {
    font-weight: bold;
    color: #333;
}

.stat-text {
    font-size: 0.8rem;
    color: #666;
}

.user-actions {
    display: flex;
    gap: 10px;
}

.role-select {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 0.9rem;
}

.btn {
    padding: 8px 12px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.8rem;
    font-weight: 500;
    transition: 0.3s;
}

.btn-update { background: #28a745; color: white; }
.btn-delete { background: #dc3545; color: white; }
.btn:hover { transform: translateY(-1px); }

.no-users {
    text-align: center;
    color: #666;
    padding: 40px 20px;
}

.no-users i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .user-card {
        grid-template-columns: 1fr;
        gap: 15px;
        text-align: center;
    }
    
    .user-actions {
        justify-content: center;
    }
}
</style>
</head>
<body>
<header>
    <h1><i class="fas fa-users-cog"></i> User Management</h1>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</header>

<div class="container">
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- User Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon users"><i class="fas fa-users"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= count(array_filter($users, function($u) { return $u['role'] === 'user'; })) ?></div>
                <div class="stat-label">Regular Users</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon chefs"><i class="fas fa-chef-hat"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= count(array_filter($users, function($u) { return $u['role'] === 'chef'; })) ?></div>
                <div class="stat-label">Chefs</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon admins"><i class="fas fa-shield-alt"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= count(array_filter($users, function($u) { return $u['role'] === 'admin'; })) ?></div>
                <div class="stat-label">Administrators</div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="users-table">
        <div class="table-header">
            <div class="table-title">
                <i class="fas fa-list"></i>
                All Users (<?= count($users) ?>)
            </div>
        </div>

        <?php if (empty($users)): ?>
            <div class="no-users">
                <i class="fas fa-users"></i>
                <h3>No Users Found</h3>
                <p>No users are registered in the system.</p>
            </div>
        <?php else: ?>
            <div class="users-grid">
                <?php foreach ($users as $user): ?>
                    <div class="user-card">
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                            <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                            <div class="user-meta">
                                Joined: <?= date('M j, Y', strtotime($user['created_at'])) ?>
                            </div>
                        </div>
                        
                        <div class="role-badge role-<?= $user['role'] ?>">
                            <?= ucfirst($user['role']) ?>
                        </div>
                        
                        <div class="user-stats">
                            <div class="stat-number"><?= $user['total_bookings'] ?></div>
                            <div class="stat-text">Bookings</div>
                        </div>
                        
                        <div class="user-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <select name="new_role" class="role-select" onchange="this.form.submit()">
                                    <option value="">Change Role</option>
                                    <option value="user" <?= $user['role'] === 'user' ? 'disabled' : '' ?>>User</option>
                                    <option value="chef" <?= $user['role'] === 'chef' ? 'disabled' : '' ?>>Chef</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'disabled' : '' ?>>Admin</option>
                                </select>
                                <input type="hidden" name="change_role" value="1">
                            </form>
                            
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" name="delete_user" class="btn btn-delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Add entrance animations
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stat-card, .user-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.05}s, transform 0.6s ease ${index * 0.05}s`;
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 50 + (index * 50));
    });
});
</script>
</body>
</html>