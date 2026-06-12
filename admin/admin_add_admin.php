<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_admin_id = $_SESSION['user_id'];
$message = "";

// Handle actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    // Add new admin or chef
    if ($action === 'add_admin') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = trim($_POST['password']);
        $role = $_POST['role'] ?? 'admin';
        
        if (strlen($password) < 6) {
            $message = "Password must be at least 6 characters long.";
        } elseif (!preg_match('/^(\+251[79]|09|07)\d{8}$/', $phone)) {
            $message = "Please enter a valid Ethiopian phone number (09xxxxxxxx, 07xxxxxxxx, +2519xxxxxxxx, or +2517xxxxxxxx).";
        } elseif (!in_array($role, ['admin', 'chef'])) {
            $message = "Invalid role selected.";
        } else {
            $check = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $check->execute([$email]);
            
            if ($check->rowCount() > 0) {
                $message = "Email already exists!";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?,?,?,?,?)");
                
                if ($stmt->execute([$name, $email, $phone, $hashed, $role])) {
                    $role_name = ucfirst($role);
                    $message = "$role_name added successfully! Login credentials: $email";
                    
                    // Additional setup for chef role
                    if ($role === 'chef') {
                        $new_chef_id = $conn->lastInsertId();
                        
                        // Ensure chef-specific tables exist
                        try {
                            $conn->exec("CREATE TABLE IF NOT EXISTS kitchen_orders (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                pre_order_id INT NOT NULL,
                                chef_id INT,
                                preparation_status ENUM('pending', 'in_progress', 'ready', 'served') DEFAULT 'pending',
                                estimated_time INT DEFAULT 30,
                                actual_time INT NULL,
                                chef_notes TEXT,
                                started_at TIMESTAMP NULL,
                                completed_at TIMESTAMP NULL,
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                            )");
                            
                            $conn->exec("CREATE TABLE IF NOT EXISTS reports (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                sender_id INT NOT NULL,
                                receiver_id INT NOT NULL,
                                title VARCHAR(200) NOT NULL,
                                message TEXT NOT NULL,
                                type ENUM('chef_to_admin', 'admin_to_chef', 'general') DEFAULT 'general',
                                priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                                status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                read_at TIMESTAMP NULL
                            )");
                            
                            $message .= " Chef dashboard and kitchen management system ready.";
                        } catch (Exception $e) {
                            $message .= " Note: Some chef features may need database setup.";
                        }
                    }
                } else {
                    $message = "Failed to add $role.";
                }
            }
        }
    }
    
    // Update admin
    if ($action === 'update_admin') {
        $admin_id = intval($_POST['admin_id']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $new_password = trim($_POST['new_password']);
        
        if ($admin_id > 0) {
            if (!preg_match('/^(\+251[79]|09|07)\d{8}$/', $phone)) {
                $message = "Please enter a valid Ethiopian phone number (09xxxxxxxx, 07xxxxxxxx, +2519xxxxxxxx, or +2517xxxxxxxx).";
            } elseif (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    $message = "Password must be at least 6 characters long.";
                } else {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, password=? WHERE id=? AND role='admin'");
                    if ($stmt->execute([$name, $email, $phone, $hashed, $admin_id])) {
                        $message = "Admin updated successfully!";
                    } else {
                        $message = "Failed to update admin.";
                    }
                }
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=? AND role='admin'");
                if ($stmt->execute([$name, $email, $phone, $admin_id])) {
                    $message = "Admin updated successfully!";
                } else {
                    $message = "Failed to update admin.";
                }
            }
        }
    }
    
    // Delete admin
    if ($action === 'delete_admin') {
        $admin_id = intval($_POST['admin_id']);
        
        if ($admin_id === $current_admin_id) {
            $message = "You cannot delete your own account!";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND role='admin'");
            if ($stmt->execute([$admin_id])) {
                $message = "Admin deleted successfully!";
            } else {
                $message = "Failed to delete admin.";
            }
        }
    }
}

// Get all admins and chefs
$admins = $conn->query("SELECT * FROM users WHERE role IN ('admin', 'chef') ORDER BY role ASC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<title>Staff Management - Admin & Chef</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script src="../js/ethiopian-phone-validation.js"></script>
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
.message.error { 
    background: rgba(244,67,54,0.1); 
    color: #f44336; 
    border-color: rgba(244,67,54,0.3); 
}

.admin-sections { 
    display: grid; 
    grid-template-columns: 1fr 1fr; 
    gap: 30px; 
}

.section-card { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(15px); 
    border-radius: 20px; 
    padding: 30px; 
    box-shadow: 0 10px 40px rgba(0,0,0,0.1); 
    border: 1px solid rgba(255,255,255,0.2); 
}

.section-title { 
    font-size: 1.5rem; 
    font-weight: 700; 
    color: #333; 
    margin-bottom: 25px; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
}
.section-title i { color: #667eea; }

.form-group { margin-bottom: 20px; }
.form-label { 
    display: block; 
    margin-bottom: 8px; 
    font-weight: 600; 
    color: #333; 
}
.form-input { 
    width: 100%; 
    padding: 12px 15px; 
    border: 2px solid rgba(102,126,234,0.2); 
    border-radius: 10px; 
    font-size: 1rem; 
    transition: 0.3s; 
    background: rgba(255,255,255,0.8); 
}
.form-input:focus { 
    outline: none; 
    border-color: #667eea; 
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1); 
}

.btn { 
    padding: 12px 25px; 
    border: none; 
    border-radius: 10px; 
    cursor: pointer; 
    font-size: 1rem; 
    font-weight: 600; 
    transition: all 0.3s ease; 
    text-decoration: none; 
    display: inline-flex; 
    align-items: center; 
    gap: 8px; 
    text-transform: uppercase; 
    letter-spacing: 0.5px; 
}
.btn:hover { transform: translateY(-2px); }
.btn-primary { 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    color: white; 
    box-shadow: 0 4px 15px rgba(102,126,234,0.3); 
}
.btn-danger { 
    background: linear-gradient(45deg, #f44336, #d32f2f); 
    color: white; 
    box-shadow: 0 4px 15px rgba(244,67,54,0.3); 
}
.btn-warning { 
    background: linear-gradient(45deg, #ff9800, #f57c00); 
    color: white; 
    box-shadow: 0 4px 15px rgba(255,152,0,0.3); 
}

.admins-list { max-height: 500px; overflow-y: auto; }
.admin-item { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    padding: 20px; 
    margin-bottom: 15px; 
    background: rgba(102,126,234,0.05); 
    border-radius: 15px; 
    border: 1px solid rgba(102,126,234,0.1); 
    transition: 0.3s; 
}
.admin-item:hover { 
    background: rgba(102,126,234,0.1); 
    transform: translateX(5px); 
}

.admin-info h4 { 
    color: #333; 
    margin-bottom: 5px; 
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.role-badge {
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.role-admin {
    background: skyblue;
    color: white;
}

.role-chef {
    background: skyblue;
    color: white;
}
.admin-info p { 
    color: #666; 
    font-size: 0.9rem; 
    margin-bottom: 3px; 
}
.admin-badge { 
    background: linear-gradient(45deg, skyblue, skyblue); 
    color: white; 
    padding: 4px 12px; 
    border-radius: 15px; 
    font-size: 0.8rem; 
    font-weight: 500; 
}
.current-admin { 
    background: linear-gradient(45deg, skyblue, skyblue); 
}

.admin-actions { 
    display: flex; 
    gap: 10px; 
}
.btn-sm { 
    padding: 8px 15px; 
    font-size: 0.9rem; 
}

.edit-form { 
    display: none; 
    margin-top: 15px; 
    padding: 20px; 
    background: rgba(255,255,255,0.8); 
    border-radius: 10px; 
    border: 2px solid rgba(102,126,234,0.2); 
}

.no-admins { 
    text-align: center; 
    padding: 40px; 
    color: #666; 
}

/* Three Dot Menu for Staff Management */
.staff-dropdown {
    position: relative;
    display: inline-block;
}

.staff-menu-btn {
    background: transparent;
    color: #667eea;
    border: none;
    padding: 8px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.2rem;
    transition: all 0.3s ease;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.staff-menu-btn:hover {
    color: #764ba2;
    transform: scale(1.2);
}

.staff-dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    bottom: 45px;
    background: rgba(255,255,255,0.98);
    backdrop-filter: blur(20px);
    min-width: 200px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
    border-radius: 15px;
    z-index: 1000;
    border: 1px solid rgba(102,126,234,0.2);
    overflow: hidden;
}

.staff-dropdown-content.show {
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

.staff-dropdown-item {
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
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
}

.staff-dropdown-item:hover {
    background: rgba(102,126,234,0.1);
    color: #667eea;
    transform: translateX(-5px);
}

.staff-dropdown-item.edit-item:hover {
    background: linear-gradient(45deg, #4ecdc4, #44a08d);
    color: white;
}

.staff-dropdown-item.view-item:hover {
    background: linear-gradient(45deg, #3b82f6, #60a5fa);
    color: white;
}

.staff-dropdown-item.delete-item:hover {
    background: linear-gradient(45deg, #ff6b6b, #ee5a24);
    color: white;
}

.staff-dropdown-item:last-child {
    border-bottom: none;
}

.staff-dropdown-item i {
    width: 18px;
    text-align: center;
    font-size: 1.1rem;
}

.dropdown-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(102,126,234,0.3), transparent);
    margin: 8px 0;
}

@media (max-width: 1024px) { 
    .admin-sections { grid-template-columns: 1fr; } 
}
@media (max-width: 768px) { 
    header { flex-direction: column; text-align: center; gap: 15px; }
    .admin-item { flex-direction: column; text-align: center; gap: 15px; }
    .admin-actions { justify-content: center; }
    .staff-menu-btn {
        width: 30px;
        height: 30px;
        font-size: 1rem;
    }
    
    .staff-dropdown-content {
        min-width: 160px;
        left: 0;
    }
}
</style>
</head>
<body>

<header>
    <h1><i class="fas fa-users-cog"></i> Staff Management</h1>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</header>

<div class="container">
    <?php if ($message): ?>
        <div class="message <?= strpos($message, 'successfully') !== false ? '' : 'error' ?>">
            <i class="fas fa-<?= strpos($message, 'successfully') !== false ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="admin-sections">
        <!-- Add New Admin/Chef -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-user-plus"></i>
                Add New Admin/Chef
            </h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_admin">
                
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-input" placeholder="Enter full name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" placeholder="Enter email address" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-input" placeholder="09xxxxxxxx, 07xxxxxxxx, +2519xxxxxxxx, +2517xxxxxxxx" required>
                    <small style="color: #666; font-size: 0.9rem;">Ethiopian format: 09xxxxxxxx, 07xxxxxxxx, +2519xxxxxxxx, or +2517xxxxxxxx</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-input" required>
                        <option value="admin">Admin</option>
                        <option value="chef">Chef</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="Enter password (min 6 characters)" required minlength="6">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Admin
                </button>
            </form>
        </div>

        <!-- Existing Admins -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-users"></i>
                Current Staff (<?= count($admins) ?>)
            </h2>
            
            <div class="admins-list">
                <?php if (empty($admins)): ?>
                    <div class="no-admins">
                        <i class="fas fa-user-times" style="font-size: 2rem; margin-bottom: 10px;"></i>
                        <p>No admins found</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($admins as $admin): ?>
                        <div class="admin-item">
                            <div class="admin-info">
                                <h4>
                                    <?= htmlspecialchars($admin['name']) ?>
                                    <span class="role-badge role-<?= $admin['role'] ?>">
                                        <?= ucfirst($admin['role']) ?>
                                    </span>
                                </h4>
                                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($admin['email']) ?></p>
                                <p><i class="fas fa-phone"></i> <?= htmlspecialchars($admin['phone'] ?? 'No phone number') ?></p>
                                <p><i class="fas fa-calendar"></i> Joined: <?= date('M d, Y', strtotime($admin['created_at'])) ?></p>
                                <span class="admin-badge <?= $admin['id'] == $current_admin_id ? 'current-admin' : '' ?>">
                                    <?= $admin['id'] == $current_admin_id ? 'Current Admin' : 'Admin' ?>
                                </span>
                            </div>
                            
                            <div class="admin-actions">
                                <div class="staff-dropdown">
                                    <button class="staff-menu-btn" onclick="toggleStaffMenu(<?= $admin['id'] ?>)" title="Staff Options">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="staff-dropdown-content" id="staffDropdown<?= $admin['id'] ?>">
                                        <div class="dropdown-header">
                                            <i class="fas fa-cog"></i> Staff Options
                                        </div>
                                        <button class="staff-dropdown-item edit-item" onclick="toggleEditForm(<?= $admin['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                            Edit Staff
                                        </button>
                                        <button class="staff-dropdown-item view-item" onclick="viewStaffDetails(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['name']) ?>', '<?= htmlspecialchars($admin['email']) ?>', '<?= htmlspecialchars($admin['phone'] ?? 'No phone') ?>', '<?= ucfirst($admin['role']) ?>', '<?= date('M d, Y', strtotime($admin['created_at'])) ?>')">
                                            <i class="fas fa-eye"></i>
                                            View Details
                                        </button>
                                        <?php if ($admin['id'] != $current_admin_id): ?>
                                        <div class="dropdown-divider"></div>
                                        <button class="staff-dropdown-item delete-item" onclick="confirmStaffDelete(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['name']) ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                            Delete Staff
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Edit Form -->
                            <div class="edit-form" id="editForm<?= $admin['id'] ?>">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_admin">
                                    <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                                    
                                    <div class="form-group">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($admin['name']) ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($admin['email']) ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" name="phone" class="form-input" value="<?= htmlspecialchars($admin['phone'] ?? '') ?>" placeholder="09xxxxxxxx, 07xxxxxxxx, +2519xxxxxxxx, +2517xxxxxxxx" required>
                                        <small style="color: #666; font-size: 0.9rem;">Ethiopian format: 09xxxxxxxx, 07xxxxxxxx, +2519xxxxxxxx, or +2517xxxxxxxx</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">New Password (leave blank to keep current)</label>
                                        <input type="password" name="new_password" class="form-input" placeholder="Enter new password" minlength="6">
                                    </div>
                                    
                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                        <button type="button" class="btn btn-warning btn-sm" onclick="toggleEditForm(<?= $admin['id'] ?>)">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Hidden Delete Form -->
                    <form id="deleteStaffForm" method="POST" style="display: none;">
                        <input type="hidden" name="action" value="delete_admin">
                        <input type="hidden" name="admin_id" id="deleteStaffId">
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Three-dot staff menu toggle
function toggleStaffMenu(staffId) {
    const dropdown = document.getElementById('staffDropdown' + staffId);
    const allDropdowns = document.querySelectorAll('.staff-dropdown-content');
    
    // Close all other dropdowns
    allDropdowns.forEach(dd => {
        if (dd.id !== 'staffDropdown' + staffId) {
            dd.classList.remove('show');
        }
    });
    
    // Toggle current dropdown
    dropdown.classList.toggle('show');
}

// View staff details function
function viewStaffDetails(id, name, email, phone, role, joinDate) {
    const details = `
👤 STAFF DETAILS

📋 Staff Information:
• Staff ID: #${id}
• Name: ${name}
• Role: ${role}
• Email: ${email}
• Phone: ${phone}
• Joined: ${joinDate}

This staff member has ${role.toLowerCase()} privileges and access to the system.
    `;
    
    alert(details);
    
    // Close the dropdown after viewing
    const dropdown = document.getElementById('staffDropdown' + id);
    dropdown.classList.remove('show');
}

// Enhanced delete confirmation for staff
function confirmStaffDelete(staffId, staffName) {
    const confirmed = confirm(`⚠️ WARNING!\n\nAre you absolutely sure you want to delete "${staffName}"?\n\nThis action cannot be undone and will permanently remove:\n• The staff member from the system\n• All their access privileges\n• Their login credentials\n\nClick OK to proceed with deletion, or Cancel to keep the staff member.`);
    
    if (confirmed) {
        document.getElementById('deleteStaffId').value = staffId;
        document.getElementById('deleteStaffForm').submit();
    }
    
    // Close the dropdown
    const dropdown = document.getElementById('staffDropdown' + staffId);
    dropdown.classList.remove('show');
}

// Close dropdown when clicking outside
window.addEventListener('click', function(event) {
    if (!event.target.matches('.staff-menu-btn') && !event.target.closest('.staff-menu-btn')) {
        const dropdowns = document.querySelectorAll('.staff-dropdown-content');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
});

function toggleEditForm(adminId) {
    const form = document.getElementById('editForm' + adminId);
    form.style.display = form.style.display === 'none' || form.style.display === '' ? 'block' : 'none';
    
    // Close the dropdown when opening edit form
    const dropdown = document.getElementById('staffDropdown' + adminId);
    dropdown.classList.remove('show');
}

// Add entrance animations
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.section-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.2}s, transform 0.6s ease ${index * 0.2}s`;
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 + (index * 200));
    });
    
    // Ethiopian phone validation is handled by the external library
});
</script>
</body>
</html>
