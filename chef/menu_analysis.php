<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'chef') {
    header("Location: ../login.php");
    exit;
}

$chef_id = $_SESSION['user_id'];
$chef_name = $_SESSION['name'] ?? 'Chef';

// Initialize variables with default values
$popular_items = $category_stats = $daily_orders = $low_performing = [];
$peak_hours = $customer_preferences = [];
$revenue_analysis = null;
$error_message = "";

// Get time period from URL parameter (default: 30 days)
$period = $_GET['period'] ?? '30';
switch($period) {
    case '7':
        $period_label = 'Last 7 Days';
        break;
    case '30':
        $period_label = 'Last 30 Days';
        break;
    case '90':
        $period_label = 'Last 3 Months';
        break;
    default:
        $period_label = 'Last 30 Days';
}

try {
    // Popular items with detailed metrics
    $popular_items = $conn->prepare("
        SELECT mi.id, mi.name, mi.category, mi.price as menu_price,
               COUNT(DISTINCT po.id) as order_count, 
               SUM(poi.quantity) as total_quantity,
               AVG(poi.price) as avg_selling_price, 
               SUM(poi.price * poi.quantity) as total_revenue,
               AVG(TIMESTAMPDIFF(MINUTE, po.created_at, ko.completed_at)) as avg_prep_time
        FROM pre_order_items poi
        JOIN menu_items mi ON poi.menu_item_id = mi.id
        JOIN pre_orders po ON poi.pre_order_id = po.id
        LEFT JOIN kitchen_orders ko ON po.id = ko.pre_order_id
        WHERE po.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY mi.id
        ORDER BY total_revenue DESC
        LIMIT 15
    ");
    $popular_items->execute([$period]);
    $popular_items = $popular_items->fetchAll();
    
    // Calculate revenue percentage after fetching data
    $total_revenue = 0;
    foreach ($popular_items as $item) {
        $total_revenue += $item['total_revenue'];
    }
    
    // Add revenue percentage to each item
    for ($i = 0; $i < count($popular_items); $i++) {
        if ($total_revenue > 0) {
            $popular_items[$i]['revenue_percentage'] = round(($popular_items[$i]['total_revenue'] / $total_revenue) * 100, 2);
        } else {
            $popular_items[$i]['revenue_percentage'] = 0;
        }
    }

    // Category performance with profitability
    $category_stats = $conn->prepare("
        SELECT mi.category, 
               COUNT(DISTINCT po.id) as order_count, 
               SUM(poi.quantity) as total_quantity,
               SUM(poi.price * poi.quantity) as total_revenue,
               AVG(poi.price) as avg_item_price,
               COUNT(DISTINCT mi.id) as unique_items,
               ROUND(AVG(TIMESTAMPDIFF(MINUTE, po.created_at, ko.completed_at)), 1) as avg_prep_time
        FROM pre_order_items poi
        JOIN menu_items mi ON poi.menu_item_id = mi.id
        JOIN pre_orders po ON poi.pre_order_id = po.id
        LEFT JOIN kitchen_orders ko ON po.id = ko.pre_order_id
        WHERE po.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY mi.category
        ORDER BY total_revenue DESC
    ");
    $category_stats->execute([$period]);
    $category_stats = $category_stats->fetchAll();

    // Daily performance with trends
    $daily_orders = $conn->prepare("
        SELECT DATE(po.created_at) as order_date, 
               COUNT(DISTINCT po.id) as order_count, 
               SUM(po.total_amount) as daily_revenue,
               COUNT(DISTINCT poi.menu_item_id) as unique_items_ordered,
               AVG(po.total_amount) as avg_order_value,
               DAYNAME(po.created_at) as day_name
        FROM pre_orders po
        JOIN pre_order_items poi ON po.id = poi.pre_order_id
        WHERE po.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(po.created_at)
        ORDER BY order_date DESC
    ");
    $daily_orders->execute([$period]);
    $daily_orders = $daily_orders->fetchAll();

    // Low performing and zero-order items
    $low_performing = $conn->prepare("
        SELECT mi.id, mi.name, mi.category, mi.price,
               COALESCE(SUM(poi.quantity), 0) as total_quantity,
               COALESCE(COUNT(DISTINCT po.id), 0) as order_count,
               COALESCE(SUM(poi.price * poi.quantity), 0) as total_revenue,
               DATEDIFF(NOW(), MAX(po.created_at)) as days_since_last_order
        FROM menu_items mi
        LEFT JOIN pre_order_items poi ON mi.id = poi.menu_item_id
        LEFT JOIN pre_orders po ON poi.pre_order_id = po.id AND po.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY mi.id
        HAVING total_quantity <= 3 OR total_quantity IS NULL
        ORDER BY total_quantity ASC, days_since_last_order DESC
        LIMIT 10
    ");
    $low_performing->execute([$period]);
    $low_performing = $low_performing->fetchAll();

    // Peak hours analysis
    $peak_hours = $conn->prepare("
        SELECT HOUR(po.created_at) as order_hour,
               COUNT(*) as order_count,
               SUM(po.total_amount) as hourly_revenue,
               AVG(po.total_amount) as avg_order_value
        FROM pre_orders po
        WHERE po.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY HOUR(po.created_at)
        ORDER BY order_count DESC
        LIMIT 8
    ");
    $peak_hours->execute([$period]);
    $peak_hours = $peak_hours->fetchAll();



    // Revenue and profit analysis
    $revenue_analysis = $conn->prepare("
        SELECT 
            SUM(po.total_amount) as total_revenue,
            COUNT(DISTINCT po.id) as total_orders,
            AVG(po.total_amount) as avg_order_value,
            COUNT(DISTINCT poi.menu_item_id) as unique_items_sold,
            SUM(poi.quantity) as total_items_sold,
            COUNT(DISTINCT b.user_id) as unique_customers
        FROM pre_orders po
        JOIN pre_order_items poi ON po.id = poi.pre_order_id
        JOIN bookings b ON po.booking_id = b.id
        WHERE po.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $revenue_analysis->execute([$period]);
    $revenue_analysis = $revenue_analysis->fetch();

    // Customer preferences
    $customer_preferences = $conn->prepare("
        SELECT mi.category,
               COUNT(DISTINCT b.user_id) as customer_count,
               AVG(poi.quantity) as avg_quantity_per_customer,
               COUNT(*) as total_orders
        FROM pre_order_items poi
        JOIN menu_items mi ON poi.menu_item_id = mi.id
        JOIN pre_orders po ON poi.pre_order_id = po.id
        JOIN bookings b ON po.booking_id = b.id
        WHERE po.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY mi.category
        ORDER BY customer_count DESC
    ");
    $customer_preferences->execute([$period]);
    $customer_preferences = $customer_preferences->fetchAll();

} catch (Exception $e) {
    $popular_items = $category_stats = $daily_orders = $low_performing = [];
    $peak_hours = $customer_preferences = [];
    $revenue_analysis = null;
    $error_message = "Error loading analytics: " . $e->getMessage();
    
    // Log the error for debugging
    error_log("Menu Analysis Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Menu Analysis - <?= htmlspecialchars($chef_name) ?></title>
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

.header-content h1 { 
    background: linear-gradient(45deg, #ff9a56, #ff6b35); 
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

.period-selector {
    position: relative;
}

.period-select {
    padding: 8px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 20px;
    background: white;
    font-weight: 500;
    cursor: pointer;
    transition: 0.3s;
}

.period-select:focus {
    border-color: #ff9a56;
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

/* Metrics Overview */
.metrics-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.metric-card {
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

.metric-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}

.metric-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.metric-icon.revenue { background: linear-gradient(45deg, #28a745, #20c997); }
.metric-icon.orders { background: linear-gradient(45deg, #007bff, #6610f2); }
.metric-icon.avg-order { background: linear-gradient(45deg, #ffc107, #fd7e14); }
.metric-icon.customers { background: linear-gradient(45deg, #17a2b8, #6f42c1); }
.metric-icon.categories { background: linear-gradient(45deg, #ff9a56, #ff6b35); }
.metric-icon.items { background: linear-gradient(45deg, #e83e8c, #fd7e14); }

.metric-value {
    font-size: 1.8rem;
    font-weight: bold;
    color: #333;
    line-height: 1;
}

.metric-label {
    color: #666;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Analytics Grid */
.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.full-width {
    grid-column: 1 / -1;
}

.analysis-card {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    transition: 0.3s;
}

.analysis-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

.warning-card {
    border-left: 4px solid #ffc107;
    background: rgba(255,193,7,0.05);
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

/* Simple Data Display Styles */
.daily-summary {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 20px;
}

.daily-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.daily-date {
    font-weight: 500;
    color: #333;
}

.daily-stats {
    display: flex;
    gap: 20px;
    align-items: center;
}

.daily-orders {
    color: #007bff;
    font-weight: bold;
}

.daily-revenue {
    color: #28a745;
    font-weight: bold;
}

/* Performance Lists */
.performance-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.performance-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    transition: 0.3s;
}

.performance-item:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.item-rank {
    width: 35px;
    height: 35px;
    background: linear-gradient(45deg, #ff9a56, #ff6b35);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.9rem;
}

.item-details {
    flex: 1;
}

.item-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
}

.item-meta {
    display: flex;
    gap: 10px;
    align-items: center;
}

.category-tag {
    background: rgba(255,154,86,0.1);
    color: #ff9a56;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 500;
}

.revenue-share {
    color: #28a745;
    font-size: 0.75rem;
    font-weight: 500;
}

.item-metrics {
    display: flex;
    flex-direction: column;
    gap: 5px;
    text-align: right;
}

.metric {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.metric-value {
    font-weight: bold;
    color: #333;
    font-size: 0.9rem;
}

.metric-unit {
    color: #666;
    font-size: 0.7rem;
}



.category-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.category-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
}

.category-name {
    font-weight: 600;
    color: #333;
}

.category-details {
    color: #666;
    font-size: 0.8rem;
}

.category-revenue {
    text-align: right;
}

.revenue-amount {
    font-weight: bold;
    color: #28a745;
}

.avg-price {
    color: #666;
    font-size: 0.8rem;
}

/* Peak Hours */


.peak-hours-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.peak-hour-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #f8f9fa;
    border-radius: 6px;
}

.hour-time {
    font-weight: 600;
    color: #333;
}

.hour-stats {
    display: flex;
    gap: 15px;
    font-size: 0.85rem;
}

.orders {
    color: #007bff;
    font-weight: 500;
}

.revenue {
    color: #28a745;
    font-weight: 500;
}



/* Warning Items */
.warning-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.warning-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: rgba(255,193,7,0.1);
    border-radius: 8px;
    border-left: 3px solid #ffc107;
}

.warning-icon {
    color: #ffc107;
    font-size: 1.1rem;
}

.warning-details {
    flex: 1;
}

.warning-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 2px;
}

.warning-meta {
    color: #666;
    font-size: 0.8rem;
}

.action-btn {
    width: 32px;
    height: 32px;
    border: none;
    background: #ffc107;
    color: white;
    border-radius: 50%;
    cursor: pointer;
    transition: 0.3s;
}

.action-btn:hover {
    background: #e0a800;
    transform: scale(1.1);
}

/* Error Message */
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

.no-data {
    text-align: center;
    color: #666;
    padding: 40px 20px;
}

.no-data.success {
    color: #28a745;
}

.no-data i {
    font-size: 2.5rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

.container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }

.analysis-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 30px;
}

.analysis-card {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid rgba(255,255,255,0.3);
    transition: 0.3s;
}

.analysis-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

.card-title {
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

.item-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.item-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    transition: 0.3s;
}

.item-row:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.item-info {
    flex: 1;
}

.item-name {
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.item-category {
    color: #666;
    font-size: 0.9rem;
}

.item-stats {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 5px;
}

.stat-value {
    font-weight: bold;
    color: #ff6b35;
}

.stat-label {
    font-size: 0.8rem;
    color: #666;
}

.category-row {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    gap: 15px;
    align-items: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    transition: 0.3s;
}

.category-row:hover {
    background: #e9ecef;
}

.category-name {
    font-weight: bold;
    color: #333;
}

.category-stat {
    text-align: center;
}

.category-stat-value {
    font-weight: bold;
    color: #ff6b35;
    display: block;
}

.category-stat-label {
    font-size: 0.8rem;
    color: #666;
}

.daily-chart {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.daily-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.daily-date {
    font-weight: 500;
    color: #333;
}

.daily-stats {
    display: flex;
    gap: 20px;
    align-items: center;
}

.daily-orders {
    color: #007bff;
    font-weight: bold;
}

.daily-revenue {
    color: #28a745;
    font-weight: bold;
}

.warning-item {
    background: #fff3cd !important;
    border-left: 4px solid #ffc107;
}

.warning-item .item-name {
    color: #856404;
}

.no-data {
    text-align: center;
    color: #666;
    padding: 40px 20px;
}

.no-data i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

.insights-section {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 15px;
    padding: 25px;
    margin-top: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.insight-card {
    background: linear-gradient(45deg, #ff9a56, #ff6b35);
    color: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
}

.insight-icon {
    font-size: 2rem;
    margin-bottom: 10px;
}

.insight-title {
    font-weight: bold;
    margin-bottom: 8px;
}

.insight-text {
    font-size: 0.9rem;
    opacity: 0.9;
}

/* Insights Section */
.insights-section {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 20px;
    padding: 30px;
    margin-top: 30px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.1);
}

.section-header {
    text-align: center;
    margin-bottom: 30px;
}

.section-header h2 {
    font-size: 2rem;
    background: linear-gradient(45deg, #ff9a56, #ff6b35);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.insights-subtitle {
    color: #666;
    font-size: 1.1rem;
}

.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
}

.insight-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transition: 0.3s;
    border-left: 4px solid transparent;
}

.insight-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

.revenue-insight { border-left-color: #28a745; }
.efficiency-insight { border-left-color: #007bff; }
.customer-insight { border-left-color: #17a2b8; }
.menu-insight { border-left-color: #ffc107; }

.insight-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.insight-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: white;
}

.revenue-insight .insight-icon { background: linear-gradient(45deg, #28a745, #20c997); }
.efficiency-insight .insight-icon { background: linear-gradient(45deg, #007bff, #6610f2); }
.customer-insight .insight-icon { background: linear-gradient(45deg, #17a2b8, #6f42c1); }
.menu-insight .insight-icon { background: linear-gradient(45deg, #ffc107, #fd7e14); }

.insight-title {
    font-size: 1.2rem;
    font-weight: bold;
    color: #333;
}

.insight-content {
    color: #666;
    line-height: 1.6;
}

.insight-content p {
    margin-bottom: 12px;
}

.insight-suggestion {
    background: rgba(255,154,86,0.1);
    border-left: 3px solid #ff9a56;
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 0.9rem;
    color: #333;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.insight-suggestion i {
    color: #ff9a56;
    margin-top: 2px;
}

@media (max-width: 768px) {
    .analytics-grid {
        grid-template-columns: 1fr;
    }
    
    .metrics-overview {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .header-controls {
        flex-direction: column;
        gap: 10px;
    }
    
    .insights-grid {
        grid-template-columns: 1fr;
    }
    

}
</style>
</head>
<body>
<header>
    <div class="header-content">
        <h1><i class="fas fa-utensils"></i> Menu Analysis</h1>
        <div class="header-subtitle"><?= $period_label ?> Menu Performance & Food Analytics</div>
    </div>
    <div class="header-controls">
        <div class="period-selector">
            <select onchange="changePeriod(this.value)" class="period-select">
                <option value="7" <?= $period === '7' ? 'selected' : '' ?>>Last 7 Days</option>
                <option value="30" <?= $period === '30' ? 'selected' : '' ?>>Last 30 Days</option>
                <option value="90" <?= $period === '90' ? 'selected' : '' ?>>Last 3 Months</option>
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

    <!-- Key Metrics Overview -->
    <div class="metrics-overview">
        <div class="metric-card">
            <div class="metric-icon revenue"><i class="fas fa-coins"></i></div>
            <div class="metric-content">
                <div class="metric-value">ETB <?= number_format($revenue_analysis['total_revenue'] ?? 0, 0) ?></div>
                <div class="metric-label">Total Revenue</div>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon orders"><i class="fas fa-shopping-cart"></i></div>
            <div class="metric-content">
                <div class="metric-value"><?= number_format($revenue_analysis['total_orders'] ?? 0) ?></div>
                <div class="metric-label">Total Orders</div>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon avg-order"><i class="fas fa-receipt"></i></div>
            <div class="metric-content">
                <div class="metric-value">ETB <?= number_format($revenue_analysis['avg_order_value'] ?? 0, 2) ?></div>
                <div class="metric-label">Avg Order Value</div>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon customers"><i class="fas fa-users"></i></div>
            <div class="metric-content">
                <div class="metric-value"><?= number_format($revenue_analysis['unique_customers'] ?? 0) ?></div>
                <div class="metric-label">Unique Customers</div>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon categories"><i class="fas fa-tags"></i></div>
            <div class="metric-content">
                <div class="metric-value"><?= count($category_stats) ?></div>
                <div class="metric-label">Active Categories</div>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon items"><i class="fas fa-utensils"></i></div>
            <div class="metric-content">
                <div class="metric-value"><?= count($popular_items) ?></div>
                <div class="metric-label">Popular Items</div>
            </div>
        </div>
    </div>

    <!-- Main Analytics Grid -->
    <div class="analytics-grid">
        <!-- Revenue Trend Chart -->
        <div class="chart-card full-width">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Revenue & Orders Trend</h3>

            </div>
            <div class="daily-summary">
                <?php if (!empty($daily_orders)): ?>
                    <?php foreach (array_slice($daily_orders, 0, 7) as $day): ?>
                        <div class="daily-row">
                            <div class="daily-date"><?= date('M j', strtotime($day['order_date'])) ?></div>
                            <div class="daily-stats">
                                <span class="daily-orders"><?= $day['order_count'] ?> orders</span>
                                <span class="daily-revenue">ETB <?= number_format($day['daily_revenue'], 0) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No daily data available</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Performing Items -->
        <div class="analysis-card">
            <div class="card-header">
                <h3><i class="fas fa-fire"></i> Top Performers</h3>
                <span class="card-subtitle"><?= count($popular_items) ?> items</span>
            </div>
            <?php if (empty($popular_items)): ?>
                <div class="no-data">
                    <i class="fas fa-chart-line"></i>
                    <p>No order data available</p>
                </div>
            <?php else: ?>
                <div class="performance-list">
                    <?php foreach (array_slice($popular_items, 0, 8) as $index => $item): ?>
                        <div class="performance-item">
                            <div class="item-rank">#<?= $index + 1 ?></div>
                            <div class="item-details">
                                <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="item-meta">
                                    <span class="category-tag"><?= htmlspecialchars($item['category']) ?></span>
                                    <?php if ($item['revenue_percentage']): ?>
                                        <span class="revenue-share"><?= $item['revenue_percentage'] ?>% of revenue</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="item-metrics">
                                <div class="metric">
                                    <span class="metric-value"><?= $item['total_quantity'] ?></span>
                                    <span class="metric-unit">sold</span>
                                </div>
                                <div class="metric">
                                    <span class="metric-value">ETB <?= number_format($item['total_revenue'], 0) ?></span>
                                    <span class="metric-unit">revenue</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Category Performance -->
        <div class="analysis-card">
            <div class="card-header">
                <h3><i class="fas fa-tags"></i> Category Analysis</h3>
                <span class="card-subtitle"><?= count($category_stats) ?> categories</span>
            </div>
            <?php if (empty($category_stats)): ?>
                <div class="no-data">
                    <i class="fas fa-tags"></i>
                    <p>No category data available</p>
                </div>
            <?php else: ?>

                <div class="category-list">
                    <?php foreach ($category_stats as $category): ?>
                        <div class="category-item">
                            <div class="category-info">
                                <div class="category-name"><?= htmlspecialchars($category['category']) ?></div>
                                <div class="category-details">
                                    <?= $category['unique_items'] ?> items • <?= $category['order_count'] ?> orders
                                </div>
                            </div>
                            <div class="category-revenue">
                                <div class="revenue-amount">ETB <?= number_format($category['total_revenue'], 0) ?></div>
                                <div class="avg-price">Avg: ETB <?= number_format($category['avg_item_price'], 2) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Peak Hours Analysis -->
        <div class="analysis-card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Peak Hours</h3>
                <span class="card-subtitle">Busiest times</span>
            </div>
            <?php if (empty($peak_hours)): ?>
                <div class="no-data">
                    <i class="fas fa-clock"></i>
                    <p>No peak hour data available</p>
                </div>
            <?php else: ?>

                <div class="peak-hours-list">
                    <?php foreach (array_slice($peak_hours, 0, 5) as $hour): ?>
                        <div class="peak-hour-item">
                            <div class="hour-time">
                                <?= date('g:i A', strtotime($hour['order_hour'] . ':00')) ?>
                            </div>
                            <div class="hour-stats">
                                <span class="orders"><?= $hour['order_count'] ?> orders</span>
                                <span class="revenue">ETB <?= number_format($hour['hourly_revenue'], 0) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>



        <!-- Low Performing Items -->
        <div class="analysis-card warning-card">
            <div class="card-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Needs Attention</h3>
                <span class="card-subtitle"><?= count($low_performing) ?> items</span>
            </div>
            <?php if (empty($low_performing)): ?>
                <div class="no-data success">
                    <i class="fas fa-check-circle"></i>
                    <p>All items performing well!</p>
                </div>
            <?php else: ?>
                <div class="warning-list">
                    <?php foreach ($low_performing as $item): ?>
                        <div class="warning-item">
                            <div class="warning-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="warning-details">
                                <div class="warning-title"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="warning-meta">
                                    <?= htmlspecialchars($item['category']) ?> • 
                                    <?= $item['total_quantity'] ?> orders • 
                                    <?php if ($item['days_since_last_order']): ?>
                                        Last ordered <?= $item['days_since_last_order'] ?> days ago
                                    <?php else: ?>
                                        Never ordered
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="warning-action">
                                <button class="action-btn" onclick="showItemSuggestions('<?= $item['id'] ?>')">
                                    <i class="fas fa-lightbulb"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- AI-Powered Insights -->
    <div class="insights-section">
        <div class="section-header">
            <h2><i class="fas fa-brain"></i> AI-Powered Insights & Recommendations</h2>
            <div class="insights-subtitle">Smart analytics to optimize your menu performance</div>
        </div>
        
        <div class="insights-grid">
            <div class="insight-card revenue-insight">
                <div class="insight-header">
                    <div class="insight-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="insight-title">Revenue Optimization</div>
                </div>
                <div class="insight-content">
                    <?php if (!empty($popular_items)): ?>
                        <p>Your top item "<strong><?= htmlspecialchars($popular_items[0]['name']) ?></strong>" generates <?= $popular_items[0]['revenue_percentage'] ?>% of total revenue.</p>
                        <div class="insight-suggestion">
                            <i class="fas fa-lightbulb"></i>
                            Consider creating variations or combo deals to maximize this success.
                        </div>
                    <?php else: ?>
                        <p>Start tracking orders to get revenue optimization insights.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="insight-card efficiency-insight">
                <div class="insight-header">
                    <div class="insight-icon"><i class="fas fa-chart-pie"></i></div>
                    <div class="insight-title">Menu Performance</div>
                </div>
                <div class="insight-content">
                    <?php if (!empty($category_stats)): ?>
                        <p>You have <strong><?= count($category_stats) ?> active categories</strong> with varying performance levels.</p>
                        <div class="insight-suggestion">
                            <i class="fas fa-lightbulb"></i>
                            Focus on promoting your top-performing categories and consider refreshing underperforming ones.
                        </div>
                    <?php else: ?>
                        <p>Add menu items to different categories to track performance.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="insight-card customer-insight">
                <div class="insight-header">
                    <div class="insight-icon"><i class="fas fa-users"></i></div>
                    <div class="insight-title">Customer Preferences</div>
                </div>
                <div class="insight-content">
                    <?php if (!empty($customer_preferences)): ?>
                        <p><strong><?= htmlspecialchars($customer_preferences[0]['category']) ?></strong> is the most popular category with <?= $customer_preferences[0]['customer_count'] ?> customers.</p>
                        <div class="insight-suggestion">
                            <i class="fas fa-lightbulb"></i>
                            Expand this category with seasonal specials or premium options.
                        </div>
                    <?php else: ?>
                        <p>Gather more order data to understand customer preferences.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="insight-card menu-insight">
                <div class="insight-header">
                    <div class="insight-icon"><i class="fas fa-utensils"></i></div>
                    <div class="insight-title">Menu Optimization</div>
                </div>
                <div class="insight-content">
                    <?php if (!empty($low_performing)): ?>
                        <p><strong><?= count($low_performing) ?> items</strong> need attention due to low performance.</p>
                        <div class="insight-suggestion">
                            <i class="fas fa-lightbulb"></i>
                            Consider repricing, repositioning, or replacing these items.
                        </div>
                    <?php else: ?>
                        <p>Great! All menu items are performing well.</p>
                        <div class="insight-suggestion">
                            <i class="fas fa-lightbulb"></i>
                            Consider adding new items to expand your successful menu.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Period selection
function changePeriod(period) {
    window.location.href = `menu_analysis.php?period=${period}`;
}



// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    
    // Add entrance animations
    const cards = document.querySelectorAll('.metric-card, .analysis-card, .insight-card');
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
    const ctx = document.getElementById('trendChart');
    if (!ctx) return;
    
    const dailyData = <?= json_encode($daily_orders) ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: dailyData.map(day => {
                const date = new Date(day.order_date);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }),
            datasets: [{
                label: 'Revenue',
                data: dailyData.map(day => parseFloat(day.daily_revenue)),
                borderColor: '#ff9a56',
                backgroundColor: 'rgba(255,154,86,0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Orders',
                data: dailyData.map(day => parseInt(day.order_count)),
                borderColor: '#4ecdc4',
                backgroundColor: 'rgba(78,205,196,0.1)',
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
                        text: 'Revenue (ETB)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Orders'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
}

// Category Chart
function initializeCategoryChart() {
    const ctx = document.getElementById('categoryChart');
    if (!ctx) return;
    
    const categoryData = <?= json_encode($category_stats) ?>;
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: categoryData.map(cat => cat.category),
            datasets: [{
                data: categoryData.map(cat => parseFloat(cat.total_revenue)),
                backgroundColor: [
                    '#ff9a56',
                    '#4ecdc4',
                    '#45b7d1',
                    '#96ceb4',
                    '#ffeaa7',
                    '#dda0dd'
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

// Peak Hours Chart
function initializePeakHoursChart() {
    const ctx = document.getElementById('peakHoursChart');
    if (!ctx) return;
    
    const peakData = <?= json_encode($peak_hours) ?>;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: peakData.map(hour => {
                const time = new Date();
                time.setHours(hour.order_hour, 0, 0, 0);
                return time.toLocaleTimeString('en-US', { hour: 'numeric', hour12: true });
            }),
            datasets: [{
                label: 'Orders',
                data: peakData.map(hour => parseInt(hour.order_count)),
                backgroundColor: 'rgba(255,154,86,0.8)',
                borderColor: '#ff9a56',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Orders'
                    }
                }
            }
        }
    });
}

// Item suggestions
function showItemSuggestions(itemId) {
    alert('Feature coming soon: AI-powered suggestions for improving item performance!');
}



// Auto-refresh data every 5 minutes
setInterval(() => {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 300000);

// Export functionality
function exportReport() {
    window.print();
}

// Add export button
document.addEventListener('DOMContentLoaded', function() {
    const headerControls = document.querySelector('.header-controls');
    if (headerControls) {
        const exportBtn = document.createElement('button');
        exportBtn.innerHTML = '<i class="fas fa-download"></i> Export';
        exportBtn.className = 'back-btn';
        exportBtn.style.background = 'linear-gradient(45deg, #28a745, #20c997)';
        exportBtn.onclick = exportReport;
        headerControls.insertBefore(exportBtn, headerControls.firstChild);
    }
});
</script>
</body>
</html>