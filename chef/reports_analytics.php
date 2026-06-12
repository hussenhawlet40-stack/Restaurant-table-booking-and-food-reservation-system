<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'chef') {
    header("Location: ../login.php");
    exit;
}

$chef_id = $_SESSION['user_id'];
$chef_name = $_SESSION['name'] ?? 'Chef';

// Get time period from URL parameter (default: 7 days for reports)
$period = $_GET['period'] ?? '7';
switch($period) {
    case '1':
        $period_label = 'Today';
        break;
    case '7':
        $period_label = 'Last 7 Days';
        break;
    case '30':
        $period_label = 'Last 30 Days';
        break;
    default:
        $period_label = 'Last 7 Days';
}

try {
    // Kitchen Performance Reports
    $kitchen_performance = $conn->prepare("
        SELECT 
            DATE(ko.created_at) as report_date,
            COUNT(*) as total_orders,
            COUNT(CASE WHEN ko.preparation_status = 'served' THEN 1 END) as completed_orders,
            AVG(TIMESTAMPDIFF(MINUTE, ko.started_at, ko.completed_at)) as avg_prep_time,
            SUM(po.total_amount) as daily_revenue
        FROM kitchen_orders ko
        JOIN pre_orders po ON ko.pre_order_id = po.id
        WHERE ko.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND ko.started_at IS NOT NULL AND ko.completed_at IS NOT NULL
        GROUP BY DATE(ko.created_at)
        ORDER BY report_date DESC
    ");
    $kitchen_performance->execute([$period]);
    $kitchen_performance = $kitchen_performance->fetchAll();

    // Staff Performance (Chef-specific)
    $staff_performance = $conn->prepare("
        SELECT 
            u.name as chef_name,
            COUNT(*) as orders_handled,
            AVG(TIMESTAMPDIFF(MINUTE, ko.started_at, ko.completed_at)) as avg_prep_time,
            COUNT(CASE WHEN ko.preparation_status = 'served' THEN 1 END) as completed_orders,
            ROUND((COUNT(CASE WHEN ko.preparation_status = 'served' THEN 1 END) / COUNT(*)) * 100, 1) as completion_rate
        FROM kitchen_orders ko
        JOIN users u ON ko.chef_id = u.id
        WHERE ko.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND ko.started_at IS NOT NULL
        GROUP BY ko.chef_id, u.name
        ORDER BY completion_rate DESC, avg_prep_time ASC
    ");
    $staff_performance->execute([$period]);
    $staff_performance = $staff_performance->fetchAll();
    // Order Status Distribution
    $order_status = $conn->prepare("
        SELECT 
            ko.preparation_status,
            COUNT(*) as count
        FROM kitchen_orders ko
        WHERE ko.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY ko.preparation_status
        ORDER BY count DESC
    ");
    $order_status->execute([$period]);
    $order_status = $order_status->fetchAll();

    // Communication Reports (Messages between chef and admin)
    $communication_reports = $conn->prepare("
        SELECT 
            r.*,
            sender.name as sender_name,
            receiver.name as receiver_name,
            sender.role as sender_role,
            receiver.role as receiver_role
        FROM reports r
        JOIN users sender ON r.sender_id = sender.id
        JOIN users receiver ON r.receiver_id = receiver.id
        WHERE (r.sender_id = ? OR r.receiver_id = ?)
        AND r.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    $communication_reports->execute([$chef_id, $chef_id, $period]);
    $communication_reports = $communication_reports->fetchAll();

    // Issue Reports & Incidents
    $issue_reports = $conn->prepare("
        SELECT 
            r.*,
            sender.name as sender_name
        FROM reports r
        JOIN users sender ON r.sender_id = sender.id
        WHERE r.priority IN ('high', 'urgent')
        AND r.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY 
            CASE r.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                ELSE 3 
            END,
            r.created_at DESC
        LIMIT 10
    ");
    $issue_reports->execute([$period]);
    $issue_reports = $issue_reports->fetchAll();

    // Efficiency Metrics Summary
    $efficiency_summary = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN ko.preparation_status = 'served' THEN 1 END) as completed_orders,
            AVG(TIMESTAMPDIFF(MINUTE, ko.started_at, ko.completed_at)) as avg_prep_time,
            COUNT(CASE WHEN ko.preparation_status = 'pending' THEN 1 END) as pending_orders
        FROM kitchen_orders ko
        WHERE ko.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND ko.started_at IS NOT NULL
    ");
    $efficiency_summary->execute([$period]);
    $efficiency_summary = $efficiency_summary->fetch();
    // Revenue Analysis
    $revenue_analysis = $conn->prepare("
        SELECT 
            SUM(po.total_amount) as total_revenue,
            COUNT(DISTINCT po.id) as total_orders,
            AVG(po.total_amount) as avg_order_value,
            COUNT(DISTINCT DATE(po.created_at)) as active_days
        FROM pre_orders po
        JOIN kitchen_orders ko ON po.id = ko.pre_order_id
        WHERE po.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND ko.preparation_status = 'served'
    ");
    $revenue_analysis->execute([$period]);
    $revenue_analysis = $revenue_analysis->fetch();

} catch (Exception $e) {
    $kitchen_performance = $staff_performance = $order_status = [];
    $communication_reports = $issue_reports = [];
    $efficiency_summary = $revenue_analysis = null;
    $error_message = "Error loading reports: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Reports & Analytics - <?= htmlspecialchars($chef_name) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

.header-content h1 { 
    background: linear-gradient(45deg, #667eea, #764ba2); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
    font-size: 2rem; 
    display: flex; 
    align-items: center; 
    gap: 10px;
    margin-bottom: 5px;
}

.header-subtitle {
    color: #666;
    font-size: 0.9rem;
    font-weight: 500;
}

.header-controls {
    display: flex;
    align-items: center;
    gap: 15px;
}
.period-selector select {
    padding: 8px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 20px;
    background: white;
    font-weight: 500;
    cursor: pointer;
    transition: 0.3s;
}

.period-selector select:focus {
    border-color: #667eea;
    outline: none;
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

/* Summary Cards */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
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

.summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}

.summary-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}
.summary-icon.orders { background: linear-gradient(45deg, #667eea, #764ba2); }
.summary-icon.efficiency { background: linear-gradient(45deg, #f093fb, #f5576c); }
.summary-icon.revenue { background: linear-gradient(45deg, #4facfe, #00f2fe); }
.summary-icon.issues { background: linear-gradient(45deg, #fa709a, #fee140); }

.summary-content {
    flex: 1;
}

.summary-value {
    font-size: 1.8rem;
    font-weight: bold;
    color: #333;
    line-height: 1;
}

.summary-label {
    color: #666;
    font-size: 0.9rem;
    font-weight: 500;
}

.summary-change {
    font-size: 0.8rem;
    font-weight: 500;
    margin-top: 4px;
}

.change-positive { color: #28a745; }
.change-negative { color: #dc3545; }

/* Reports Grid */
.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.full-width {
    grid-column: 1 / -1;
}

.report-card {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    transition: 0.3s;
}

.report-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}
.card-header h3 {
    font-size: 1.2rem;
    font-weight: bold;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-subtitle {
    color: #666;
    font-size: 0.85rem;
    font-weight: 500;
}

/* Performance Table */
.performance-table {
    width: 100%;
    border-collapse: collapse;
}

.performance-table th,
.performance-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

.performance-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #333;
}

.performance-table tr:hover {
    background: #f8f9fa;
}

.metric-good { color: #28a745; font-weight: 500; }
.metric-warning { color: #ffc107; font-weight: 500; }
.metric-danger { color: #dc3545; font-weight: 500; }

/* Simple Data Display Styles */
.performance-summary, .status-summary {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 20px;
}

.daily-row, .status-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.daily-date, .status-name {
    font-weight: 500;
    color: #333;
}

.daily-stats, .status-count {
    display: flex;
    gap: 20px;
    align-items: center;
    font-weight: bold;
}

.daily-orders {
    color: #007bff;
}

.daily-revenue {
    color: #28a745;
}

.status-count {
    color: #667eea;
}

/* Communication Reports */
.communication-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
    max-height: 400px;
    overflow-y: auto;
}

.communication-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    transition: 0.3s;
}
.communication-item:hover {
    background: #e9ecef;
}

.comm-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: white;
    font-size: 0.9rem;
}

.comm-sent { background: linear-gradient(45deg, #667eea, #764ba2); }
.comm-received { background: linear-gradient(45deg, #4facfe, #00f2fe); }

.comm-content {
    flex: 1;
}

.comm-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.comm-title {
    font-weight: 600;
    color: #333;
    font-size: 0.95rem;
}

.comm-time {
    color: #666;
    font-size: 0.8rem;
}

.comm-meta {
    color: #666;
    font-size: 0.85rem;
    margin-bottom: 8px;
}

.comm-status {
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-unread { background: #fff3cd; color: #856404; }
.status-read { background: #d4edda; color: #155724; }
.status-replied { background: #cce5ff; color: #004085; }

/* Issue Reports */
.issue-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.issue-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid transparent;
}

.issue-urgent { border-left-color: #dc3545; background: rgba(220,53,69,0.05); }
.issue-high { border-left-color: #fd7e14; background: rgba(253,126,20,0.05); }

.issue-icon {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
}

.issue-urgent .issue-icon { background: #dc3545; }
.issue-high .issue-icon { background: #fd7e14; }

.issue-content {
    flex: 1;
}

.issue-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 3px;
}

.issue-meta {
    color: #666;
    font-size: 0.8rem;
}

.no-data {
    text-align: center;
    color: #666;
    padding: 40px 20px;
}

.no-data i {
    font-size: 2.5rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

.error-message {
    background: rgba(220,53,69,0.1);
    color: #dc3545;
    border: 2px solid rgba(220,53,69,0.3);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
@media (max-width: 768px) {
    .reports-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .header-controls {
        flex-direction: column;
        gap: 10px;
    }
}
</style>
</head>
<body>
<header>
    <div class="header-content">
        <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
        <div class="header-subtitle"><?= $period_label ?> Performance Reports</div>
    </div>
    <div class="header-controls">
        <div class="period-selector">
            <select onchange="changePeriod(this.value)">
                <option value="1" <?= $period === '1' ? 'selected' : '' ?>>Today</option>
                <option value="7" <?= $period === '7' ? 'selected' : '' ?>>Last 7 Days</option>
                <option value="30" <?= $period === '30' ? 'selected' : '' ?>>Last 30 Days</option>
            </select>
        </div>
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</header>

<div class="container">
    <?php if (isset($error_message)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon orders"><i class="fas fa-clipboard-list"></i></div>
            <div class="summary-content">
                <div class="summary-value"><?= $efficiency_summary['total_orders'] ?? 0 ?></div>
                <div class="summary-label">Total Orders</div>
                <div class="summary-change change-positive">
                    <?= round((($efficiency_summary['completed_orders'] ?? 0) / max(($efficiency_summary['total_orders'] ?? 1), 1)) * 100) ?>% completed
                </div>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon efficiency"><i class="fas fa-tachometer-alt"></i></div>
            <div class="summary-content">
                <div class="summary-value"><?= round($efficiency_summary['avg_prep_time'] ?? 0) ?>m</div>
                <div class="summary-label">Avg Prep Time</div>
                <div class="summary-change <?= ($efficiency_summary['avg_prep_time'] ?? 0) <= 25 ? 'change-positive' : 'change-negative' ?>">
                    <?= ($efficiency_summary['avg_prep_time'] ?? 0) <= 25 ? 'Excellent' : 'Needs improvement' ?>
                </div>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon revenue"><i class="fas fa-coins"></i></div>
            <div class="summary-content">
                <div class="summary-value">ETB <?= number_format($revenue_analysis['total_revenue'] ?? 0, 0) ?></div>
                <div class="summary-label">Total Revenue</div>
                <div class="summary-change change-positive">
                    <?= $revenue_analysis['total_orders'] ?? 0 ?> orders
                </div>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon issues"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="summary-content">
                <div class="summary-value"><?= count($issue_reports) ?></div>
                <div class="summary-label">Priority Issues</div>
                <div class="summary-change <?= count($issue_reports) == 0 ? 'change-positive' : 'change-negative' ?>">
                    <?= count($issue_reports) == 0 ? 'All clear' : 'Needs attention' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Reports Grid -->
    <div class="reports-grid">
        <!-- Kitchen Performance Chart -->
        <div class="report-card full-width">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Kitchen Performance Trend</h3>
                <span class="card-subtitle"><?= count($kitchen_performance) ?> days of data</span>
            </div>
            <?php if (empty($kitchen_performance)): ?>
                <div class="no-data">
                    <i class="fas fa-chart-line"></i>
                    <p>No performance data available for this period</p>
                </div>
            <?php else: ?>
                <div class="performance-summary">
                    <?php if (!empty($kitchen_performance)): ?>
                        <?php foreach (array_slice($kitchen_performance, 0, 7) as $day): ?>
                            <div class="daily-row">
                                <div class="daily-date"><?= date('M j', strtotime($day['report_date'])) ?></div>
                                <div class="daily-stats">
                                    <span class="daily-orders"><?= $day['completed_orders'] ?> completed</span>
                                    <span class="daily-revenue">ETB <?= number_format($day['daily_revenue'], 0) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No performance data available</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Staff Performance -->
        <div class="report-card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Staff Performance</h3>
                <span class="card-subtitle"><?= count($staff_performance) ?> chefs</span>
            </div>
            <?php if (empty($staff_performance)): ?>
                <div class="no-data">
                    <i class="fas fa-users"></i>
                    <p>No staff performance data</p>
                </div>
            <?php else: ?>
                <table class="performance-table">
                    <thead>
                        <tr>
                            <th>Chef</th>
                            <th>Orders</th>
                            <th>Avg Time</th>
                            <th>Completion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_performance as $staff): ?>
                            <tr>
                                <td><?= htmlspecialchars($staff['chef_name']) ?></td>
                                <td><?= $staff['orders_handled'] ?></td>
                                <td class="<?= $staff['avg_prep_time'] <= 25 ? 'metric-good' : ($staff['avg_prep_time'] <= 35 ? 'metric-warning' : 'metric-danger') ?>">
                                    <?= round($staff['avg_prep_time']) ?>m
                                </td>
                                <td class="<?= $staff['completion_rate'] >= 90 ? 'metric-good' : ($staff['completion_rate'] >= 75 ? 'metric-warning' : 'metric-danger') ?>">
                                    <?= $staff['completion_rate'] ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <!-- Order Status Distribution -->
        <div class="report-card">
            <div class="card-header">
                <h3><i class="fas fa-pie-chart"></i> Order Status Distribution</h3>
                <span class="card-subtitle">Current status breakdown</span>
            </div>
            <?php if (empty($order_status)): ?>
                <div class="no-data">
                    <i class="fas fa-pie-chart"></i>
                    <p>No order status data</p>
                </div>
            <?php else: ?>
                <div class="status-summary">
                    <?php if (!empty($order_status)): ?>
                        <?php foreach ($order_status as $status): ?>
                            <div class="status-row">
                                <div class="status-name"><?= ucfirst(str_replace('_', ' ', $status['preparation_status'])) ?></div>
                                <div class="status-count"><?= $status['count'] ?> orders</div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No status data available</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Communication Reports -->
        <div class="report-card">
            <div class="card-header">
                <h3><i class="fas fa-comments"></i> Recent Communications</h3>
                <span class="card-subtitle"><?= count($communication_reports) ?> messages</span>
            </div>
            <?php if (empty($communication_reports)): ?>
                <div class="no-data">
                    <i class="fas fa-comments"></i>
                    <p>No recent communications</p>
                </div>
            <?php else: ?>
                <div class="communication-list">
                    <?php foreach (array_slice($communication_reports, 0, 8) as $comm): ?>
                        <div class="communication-item">
                            <div class="comm-avatar <?= $comm['sender_id'] == $chef_id ? 'comm-sent' : 'comm-received' ?>">
                                <?= strtoupper(substr($comm['sender_name'], 0, 2)) ?>
                            </div>
                            <div class="comm-content">
                                <div class="comm-header">
                                    <div class="comm-title"><?= htmlspecialchars($comm['title']) ?></div>
                                    <div class="comm-time"><?= date('M j, g:i A', strtotime($comm['created_at'])) ?></div>
                                </div>
                                <div class="comm-meta">
                                    <?= $comm['sender_id'] == $chef_id ? 'To: ' : 'From: ' ?>
                                    <?= htmlspecialchars($comm['sender_id'] == $chef_id ? $comm['receiver_name'] : $comm['sender_name']) ?>
                                    (<?= ucfirst($comm['sender_id'] == $chef_id ? $comm['receiver_role'] : $comm['sender_role']) ?>)
                                </div>
                                <div class="comm-status status-<?= $comm['status'] ?>">
                                    <?= ucfirst($comm['status']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Priority Issues -->
        <div class="report-card">
            <div class="card-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Priority Issues</h3>
                <span class="card-subtitle"><?= count($issue_reports) ?> high priority items</span>
            </div>
            <?php if (empty($issue_reports)): ?>
                <div class="no-data">
                    <i class="fas fa-check-circle"></i>
                    <p>No priority issues - all clear!</p>
                </div>
            <?php else: ?>
                <div class="issue-list">
                    <?php foreach ($issue_reports as $issue): ?>
                        <div class="issue-item issue-<?= $issue['priority'] ?>">
                            <div class="issue-icon">
                                <i class="fas fa-<?= $issue['priority'] === 'urgent' ? 'exclamation-triangle' : 'exclamation-circle' ?>"></i>
                            </div>
                            <div class="issue-content">
                                <div class="issue-title"><?= htmlspecialchars($issue['title']) ?></div>
                                <div class="issue-meta">
                                    <?= ucfirst($issue['priority']) ?> priority • 
                                    From: <?= htmlspecialchars($issue['sender_name']) ?> • 
                                    <?= date('M j, g:i A', strtotime($issue['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
// Period selection
function changePeriod(period) {
    window.location.href = `reports_analytics.php?period=${period}`;
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    
    // Add entrance animations
    const cards = document.querySelectorAll('.summary-card, .report-card');
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

// Chart functions removed for better performance
    const ctx = document.getElementById('performanceChart');
    if (!ctx) return;
    
    const performanceData = <?= json_encode($kitchen_performance) ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: performanceData.map(day => {
                const date = new Date(day.report_date);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }),
            datasets: [{
                label: 'Completed Orders',
                data: performanceData.map(day => parseInt(day.completed_orders)),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102,126,234,0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Avg Prep Time (min)',
                data: performanceData.map(day => parseFloat(day.avg_prep_time)),
                borderColor: '#f093fb',
                backgroundColor: 'rgba(240,147,251,0.1)',
                tension: 0.4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Orders Completed'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Prep Time (minutes)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
}
// Status Chart
function initializeStatusChart() {
    const ctx = document.getElementById('statusChart');
    if (!ctx) return;
    
    const statusData = <?= json_encode($order_status) ?>;
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: statusData.map(status => status.preparation_status.replace('_', ' ').toUpperCase()),
            datasets: [{
                data: statusData.map(status => parseInt(status.count)),
                backgroundColor: [
                    '#667eea',
                    '#f093fb',
                    '#4facfe',
                    '#fa709a',
                    '#fee140'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
}

// Auto-refresh every 2 minutes
setInterval(() => {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 120000);
</script>
</body>
</html>