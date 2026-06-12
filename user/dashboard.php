<?php
//user dashboard
session_start();
require_once "../connection.php";

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_name = $_SESSION['name'] ?? 'User';
$user_id = $_SESSION['user_id'];

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

// Fetch recent admin responses to current user's comments
$recent_responses_query = "
    SELECT cr.*, c.message as original_comment, c.rating, c.created_at as comment_date
    FROM comment_replies cr
    JOIN comments c ON cr.comment_id = c.id
    WHERE c.user_id = ?
    ORDER BY cr.created_at DESC
    LIMIT 3
";
$stmt = $conn->prepare($recent_responses_query);
$stmt->execute([$user_id]);
$recent_responses = $stmt->fetchAll();

// Count total responses received
$total_responses_query = "
    SELECT COUNT(*) FROM comment_replies cr
    JOIN comments c ON cr.comment_id = c.id
    WHERE c.user_id = ?
";
$stmt = $conn->prepare($total_responses_query);
$stmt->execute([$user_id]);
$total_responses = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
<title>User Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #ccd2efff 0%, #79dbecff );
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
}

/* Animated Background Particles */
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
    animation: float 6s ease-in-out infinite;
}

.particle:nth-child(1) { width: 20px; height: 20px; left: 10%; animation-delay: 0s; }
.particle:nth-child(2) { width: 15px; height: 15px; left: 20%; animation-delay: 1s; }
.particle:nth-child(3) { width: 25px; height: 25px; left: 30%; animation-delay: 2s; }
.particle:nth-child(4) { width: 18px; height: 18px; left: 40%; animation-delay: 3s; }
.particle:nth-child(5) { width: 22px; height: 22px; left: 50%; animation-delay: 4s; }
.particle:nth-child(6) { width: 16px; height: 16px; left: 60%; animation-delay: 5s; }
.particle:nth-child(7) { width: 24px; height: 24px; left: 70%; animation-delay: 0.5s; }
.particle:nth-child(8) { width: 19px; height: 19px; left: 80%; animation-delay: 1.5s; }

@keyframes float {
    0%, 100% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
    10%, 90% { opacity: 1; }
    50% { transform: translateY(-100px) rotate(180deg); }
}

/* Header */
header { 
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
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
    color: #1d4c55ff, #197ba5ff;
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
    background: linear-gradient(45deg, #1d4c55ff, #197ba5ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
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
    background: linear-gradient(45deg, #1d4c55ff, #197ba5ff);
    transition: left 0.3s ease;
    z-index: -1;
}

nav a:hover:before {
    left: 0;
}

nav a:hover { 
    color: white;
    transform: translateY(-2px);
}

/* User Actions Dropdown */
.user-actions-dropdown {
    position: relative;
}

.user-actions-btn {
    background: rgba(102,126,234,0.1);
    border: 1px solid rgba(102,126,234,0.2);
    color: #1d4c55ff, #197ba5ff;
    padding: 10px 15px;
    border-radius: 25px;
    cursor: pointer;
    font-size: 1rem;
    transition: 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
}

.user-actions-btn:hover {
    background: linear-gradient(45deg, #1d4c55ff, #197ba5ff);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102,126,234,0.3);
}

.user-dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 15px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    border: 1px solid rgba(0,0,0,0.1);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    min-width: 280px;
    max-height: 0;
    overflow: hidden;
}

.user-dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(5px);
    max-height: 500px;
}

.user-dropdown-header {
    padding: 15px 20px;
    background: linear-gradient(45deg, #1d4c55ff, #197ba5ff);
    color: white;
    font-weight: bold;
    border-top-left-radius: 15px;
    border-top-right-radius: 15px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.user-dropdown-item {
    display: block;
    padding: 12px 20px;
    color: #333;
    text-decoration: none;
    transition: 0.2s;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-dropdown-item:hover {
    background: rgba(102,126,234,0.1);
    color: #1d4c55ff, #197ba5ff;
    padding-left: 25px;
}

.user-dropdown-item:last-child {
    border-bottom: none;
    border-bottom-left-radius: 15px;
    border-bottom-right-radius: 15px;
}

.user-dropdown-item i {
    width: 20px;
    color: #1d4c55ff, #197ba5ff;
}

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

/* Main Container */
.main-layout {
    display: flex;
    max-width: 1600px;
    margin: auto;
    gap: 30px;
    padding: 40px 30px;
}

.container { 
    flex: 1;
    min-width: 0;
}

/* Right Sidebar */
.right-sidebar {
    width: 350px;
    display: flex;
    flex-direction: column;
    gap: 25px;
    position: sticky;
    top: 120px;
    height: fit-content;
}

/* Live Clock Widget */
.clock-widget {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    border-radius: 20px;
    padding: 30px;
    text-align: center;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
    position: relative;
    overflow: hidden;
}

.clock-widget:before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: conic-gradient(from 0deg, transparent, rgba(102, 126, 234, 0.1), transparent);
    animation: clockGlow 8s linear infinite;
}

@keyframes clockGlow {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.clock-content {
    position: relative;
    z-index: 2;
}

.clock-time {
    font-size: 2.5rem;
    font-weight: bold;
    background: linear-gradient(45deg, #1d4c55ff, #197ba5ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 10px;
    font-family: 'Courier New', monospace;
}

.clock-date {
    color: #666;
    font-size: 1.1rem;
    margin-bottom: 15px;
}

.clock-icon {
    font-size: 2rem;
    color: #1d4c55ff, #197ba5ff;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* Weather Widget */
.weather-widget {
    background: linear-gradient(135deg, #74b9ff, #0984e3);
    border-radius: 20px;
    padding: 25px;
    color: white;
    text-align: center;
    box-shadow: 0 15px 35px rgba(116, 185, 255, 0.3);
    position: relative;
    overflow: hidden;
}

.weather-widget:before {
    content: '';
    position: absolute;
    top: -10px;
    right: -10px;
    width: 100px;
    height: 100px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    animation: weatherFloat 6s ease-in-out infinite;
}

@keyframes weatherFloat {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-20px); }
}

.weather-icon {
    font-size: 3rem;
    margin-bottom: 15px;
    animation: weatherSpin 10s linear infinite;
}

@keyframes weatherSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.weather-temp {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.weather-desc {
    opacity: 0.9;
    font-size: 1.1rem;
}

/* Promotional Banner */
.promo-banner {
    background: linear-gradient(135deg, #fd79a8, #e84393);
    border-radius: 20px;
    padding: 25px;
    color: white;
    text-align: center;
    box-shadow: 0 15px 35px rgba(253, 121, 168, 0.3);
    position: relative;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
}

.promo-banner:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(253, 121, 168, 0.4);
}

.promo-banner:before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.promo-banner:hover:before {
    left: 100%;
}

.promo-title {
    font-size: 1.3rem;
    font-weight: bold;
    margin-bottom: 10px;
}

.promo-text {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-bottom: 15px;
}

.promo-code {
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 15px;
    border-radius: 15px;
    font-weight: bold;
    display: inline-block;
}

/* Admin Responses Widget */
.admin-responses-widget {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.responses-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.responses-header span:first-of-type {
    font-size: 1.2rem;
    font-weight: 600;
    color: #333;
}

.response-count {
    background: linear-gradient(45deg, #10b981, #34d399);
    color: white;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.9rem;
    font-weight: 600;
}

.response-item {
    padding: 15px 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.response-item:last-child {
    border-bottom: none;
}

.response-item:hover {
    background: rgba(16, 185, 129, 0.05);
    border-radius: 10px;
    padding-left: 10px;
    padding-right: 10px;
}

.response-admin {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.response-admin i {
    color: #10b981;
    font-size: 1rem;
}

.response-admin span:first-of-type {
    font-weight: 600;
    color: #333;
    font-size: 0.9rem;
}

.admin-badge {
    background: linear-gradient(45deg, #10b981, #34d399);
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 500;
}

.response-text {
    color: #555;
    font-size: 0.9rem;
    line-height: 1.4;
    margin-bottom: 8px;
}

.response-date {
    color: #999;
    font-size: 0.8rem;
}

.view-all-responses {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
    text-align: center;
}

.view-all-responses a {
    background: linear-gradient(45deg, #1d4c55ff, #197ba5ff);
    color: white;
    padding: 10px 20px;
    border-radius: 20px;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.view-all-responses a:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

/* Quick Stats Mini Cards */
.mini-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.mini-stat-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
}

.mini-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
}

.mini-stat-icon {
    font-size: 1.5rem;
    margin-bottom: 10px;
    background: linear-gradient(45deg, #1d4c55ff, #197ba5ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.mini-stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.mini-stat-label {
    font-size: 0.8rem;
    color: #666;
}

/* Recent Activity Feed */
.activity-feed {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.activity-title {
    font-size: 1.3rem;
    font-weight: bold;
    color: #333;
    margin-bottom: 20px;
    text-align: center;
}

.activity-item {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-item:hover {
    background: rgba(102, 126, 234, 0.05);
    border-radius: 10px;
    padding-left: 10px;
}

.activity-icon {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: linear-gradient(45deg, #1d4c55ff, #197ba5ff);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    margin-right: 15px;
    font-size: 0.9rem;
}

.activity-text {
    flex: 1;
    font-size: 0.9rem;
    color: #666;
    line-height: 1.4;
}

/* Floating Action Button */
.floating-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    background: linear-gradient(45deg, #1d4c55ff, #197ba5ff);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 1000;
    animation: floatBounce 3s ease-in-out infinite;
}

@keyframes floatBounce {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.floating-btn:hover {
    transform: scale(1.1) translateY(-5px);
    box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
}

/* Welcome Section */
.welcome-section {
    text-align: center;
    margin-bottom: 50px;
}

.welcome-title {
    font-size: 3.5rem;
    font-weight: bold;
    background: linear-gradient(45deg, #636009ff , #767263ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 20px;
    text-shadow: 2px 2px 4px rgba(226, 217, 217, 0.3);
    animation: glow 2s ease-in-out infinite alternate;
}

@keyframes glow {
    from { filter: drop-shadow(0 0 5px rgba(255,255,255,0.3)); }
    to { filter: drop-shadow(0 0 20px rgba(255,255,255,0.6)); }
}

/* Center Image Section */
.image-section {
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 50px 0;
    position: relative;
}

.group-img {
    width: 400px;
    height: 400px;
    border-radius: 50%;
    object-fit: cover;
    animation: rotateImg 20s linear infinite;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    border: 8px solid rgba(255,255,255,0.2);
    position: relative;
    z-index: 2;
}

.image-glow {
    position: absolute;
    width: 420px;
    height: 420px;
    border-radius: 50%;
    background: conic-gradient(from 0deg, #1d4c55ff, #197ba5ff , #1d4c55ff);
    animation: rotateGlow 15s linear infinite reverse;
    z-index: 1;
}

@keyframes rotateImg {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}

@keyframes rotateGlow {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}

/* Quick Actions Cards */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    margin-top: 60px;
}

.action-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 30px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.2);
    position: relative;
    overflow: hidden;
}

.action-card:before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    transition: left 0.3s ease;
}

.action-card:hover:before {
    left: 0;
}

.action-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

.action-icon {
    font-size: 3rem;
    margin-bottom: 20px;
    background: linear-gradient(45deg, #1d4c55ff, #197ba5ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.action-title {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 15px;
    color: #333;
}

.action-description {
    color: #666;
    margin-bottom: 25px;
    line-height: 1.6;
}

.action-btn {
    display: inline-block;
    padding: 12px 25px;
    background: linear-gradient(45deg, #1d4c55ff, #197ba5ff);
    color: white;
    text-decoration: none;
    border-radius: 25px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

/* Stats Section */
.stats-section {
    margin-top: 60px;
    text-align: center;
}

.stats-title {
    font-size: 2rem;
    color: white;
    margin-bottom: 30px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 25px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    background: rgba(255, 255, 255, 0.15);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    color: white;
    margin-bottom: 10px;
}

.stat-label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1rem;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .main-layout {
        flex-direction: column;
    }
    
    .right-sidebar {
        width: 100%;
        position: static;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        display: grid;
    }
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
    color: #1d4c55ff, #197ba5ff;
    cursor: pointer;
    padding: 5px;
}

.mobile-nav-header {
    margin-bottom: 30px;
    padding-top: 40px;
    text-align: center;
}

.mobile-nav-header h3 {
    background: linear-gradient(45deg, #1d4c55ff, #197ba5ff);
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
    background: linear-gradient(45deg, #1d4c55ff, #197ba5ff);
    color: white;
    transform: translateX(5px);
}

.mobile-nav a i {
    margin-right: 10px;
    width: 20px;
}

@media (max-width: 768px) {
    .welcome-title {
        font-size: 2.5rem;
    }
    
    .group-img, .image-glow {
        width: 300px;
        height: 300px;
    }
    
    .quick-actions {
        grid-template-columns: 1fr;
    }
    
    .mini-stats {
        grid-template-columns: 1fr;
    }
    
    .right-sidebar {
        grid-template-columns: 1fr;
    }
    
    .mobile-menu-btn {
        display: block;
    }
    
    nav {
        display: none;
    }
    
    .user-actions-dropdown {
        display: none;
    }
    
    header {
        flex-direction: row;
        text-align: left;
    }
    
    .floating-btn {
        bottom: 20px;
        right: 20px;
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
        <h2>Welcome, <?php echo htmlspecialchars($user_name); ?></h2>
    </div>
    <nav>
        <a href="../about.php"><i class="fas fa-home"></i> Home</a>
        <a href="View_Tables.php"><i class="fas fa-chair"></i> View Tables</a>
        <a href="view_menu.php"><i class="fas fa-utensils"></i> Food Menu</a>
        <a href="view_drink_menu.php"><i class="fas fa-cocktail"></i> Drinks</a>
        
        <!-- Three Dot Menu for User Actions -->
        <div class="user-actions-dropdown">
            <button class="user-actions-btn" onclick="toggleUserActionsDropdown()">
                <i class="fas fa-ellipsis-v"></i>
                <span>My Account</span>
            </button>
            
            <div class="user-dropdown-menu" id="userDropdownMenu">
                <div class="user-dropdown-header">
                    <i class="fas fa-user-circle"></i> My Account
                </div>
                
                <a href="my_bookings.php" class="user-dropdown-item">
                    <i class="fas fa-calendar-check"></i>
                    My Bookings
                </a>
                
                <a href="my_orders.php" class="user-dropdown-item">
                    <i class="fas fa-receipt"></i>
                    My Orders
                </a>
                
                <a href="write_comment.php" class="user-dropdown-item">
                    <i class="fas fa-star"></i>
                    Write Review
                </a>
                
                <a href="my_admin_responses.php" class="user-dropdown-item">
                    <i class="fas fa-reply-all"></i>
                    Admin Responses
                    <?php if ($total_responses > 0): ?>
                        <span style="background: #ff6b6b; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; margin-left: 10px;">
                            <?= $total_responses ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        
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
    
    <a href="../about.php">
        <i class="fas fa-home"></i> Home
    </a>
    
    <a href="View_Tables.php">
        <i class="fas fa-chair"></i> View Tables
    </a>
    
    <a href="view_menu.php">
        <i class="fas fa-utensils"></i> Food Menu
    </a>
    
    <a href="view_drink_menu.php">
        <i class="fas fa-cocktail"></i> Drinks
    </a>
    
    <!-- Mobile Account Section -->
    <div style="margin: 20px 0; padding: 15px 0; border-top: 2px solid rgba(102,126,234,0.2); border-bottom: 2px solid rgba(102,126,234,0.2);">
        <h4 style="color: #667eea; margin-bottom: 15px; text-align: center; font-size: 1rem;">
            <i class="fas fa-user-circle"></i> My Account
        </h4>
        
        <a href="my_bookings.php">
            <i class="fas fa-calendar-check"></i> My Bookings
        </a>
        
        <a href="my_orders.php">
            <i class="fas fa-receipt"></i> My Orders
        </a>
        
        <a href="write_comment.php">
            <i class="fas fa-star"></i> Write Review
        </a>
        
        <a href="my_admin_responses.php">
            <i class="fas fa-reply-all"></i> Admin Responses
            <?php if ($total_responses > 0): ?>
                <span style="background: #ff6b6b; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; margin-left: 10px;">
                    <?= $total_responses ?>
                </span>
            <?php endif; ?>
        </a>
    </div>
    
    <a href="../logout.php" style="background: linear-gradient(45deg, #ff6b6b, #ee5a24); color: white; margin-top: 20px;">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileMenu()"></div></header>
</invoke>

<div class="particles">
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
</div>

<div class="main-layout">
    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1 class="welcome-title">WELCOME TO ADABINA RESTAURANT</h1>
        </div>

        <!-- Center Image with Glow Effect -->
        <div class="image-section">
            <div class="image-glow"></div>
            <img src="weygud.jpg" alt="Restaurant Image" class="group-img">
        </div>

        <!-- Quick Actions Cards -->
        <div class="quick-actions">
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-chair"></i>
                </div>
                <h3 class="action-title">Reserve Tables</h3>
                <p class="action-description">Browse and book available tables for your dining experience</p>
                <a href="View_Tables.php" class="action-btn">View Tables</a>
            </div>

            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3 class="action-title">Pre-Order Food</h3>
                <p class="action-description">Order food in advance for your table booking</p>
                <a href="pre_order.php" class="action-btn">Pre-Order</a>
            </div>

            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <h3 class="action-title">Food Menu</h3>
                <p class="action-description">Explore our delicious food menu</p>
                <a href="view_menu.php" class="action-btn">Browse Food</a>
            </div>

            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-cocktail"></i>
                </div>
                <h3 class="action-title">Beverages</h3>
                <p class="action-description">Discover our refreshing drinks selection</p>
                <a href="view_drink_menu.php" class="action-btn">View Drinks</a>
            </div>

            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h3 class="action-title">My Account</h3>
                <p class="action-description">Manage bookings, orders, reviews and view responses</p>
                <button class="action-btn" onclick="toggleUserActionsDropdown()" style="border: none; cursor: pointer;">Access Account</button>
            </div>

            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-history"></i>
                </div>
                <h3 class="action-title">Order History</h3>
                <p class="action-description">View your complete dining history</p>
                <a href="order_history.php" class="action-btn">View History</a>
            </div>
        </div>

        <!-- Restaurant Stats -->
        <div class="stats-section">
            <h2 class="stats-title">Why Choose Us?</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Happy Customers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">50+</div>
                    <div class="stat-label">Menu Items</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">15+</div>
                    <div class="stat-label">Table Options</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">5★</div>
                    <div class="stat-label">Average Rating</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Sidebar with Beautiful Widgets -->
    <div class="right-sidebar">
        <!-- Live Clock Widget -->
        <div class="clock-widget">
            <div class="clock-content">
                <div class="clock-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="clock-time" id="current-time">12:00:00</div>
                <div class="clock-date" id="current-date">Today</div>
            </div>
        </div>

        <!-- Weather Widget -->
        <div class="weather-widget">
            <div class="weather-icon">
                <i class="fas fa-sun"></i>
            </div>
            <div class="weather-temp">24°C</div>
            <div class="weather-desc">Perfect dining weather!</div>
        </div>

        <!-- Mini Stats Grid -->
        <div class="mini-stats">
            <div class="mini-stat-card">
                <div class="mini-stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="mini-stat-number">12</div>
                <div class="mini-stat-label">Today's Bookings</div>
            </div>
            <div class="mini-stat-card">
                <div class="mini-stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="mini-stat-number">4.8</div>
                <div class="mini-stat-label">Rating</div>
            </div>
        </div>

        <!-- Admin Responses Widget -->
        <?php if ($total_responses > 0): ?>
        <div class="admin-responses-widget">
            <div class="responses-header">
                <i class="fas fa-reply-all"></i>
                <span>Restaurant Responses</span>
                <span class="response-count"><?= $total_responses ?></span>
            </div>
            
            <?php if (!empty($recent_responses)): ?>
                <?php foreach ($recent_responses as $response): ?>
                <div class="response-item">
                    <div class="response-admin">
                        <i class="fas fa-user-shield"></i>
                        <span><?= htmlspecialchars($response['admin_name']) ?></span>
                        <span class="admin-badge">Staff</span>
                    </div>
                    <div class="response-text">
                        <?= htmlspecialchars(substr($response['reply_message'], 0, 80)) ?><?= strlen($response['reply_message']) > 80 ? '...' : '' ?>
                    </div>
                    <div class="response-date">
                        <?= date('M j, g:i A', strtotime($response['created_at'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="view-all-responses">
                    <a href="my_admin_responses.php">
                        <i class="fas fa-eye"></i> View All Responses
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- Promotional Banner -->
        <div class="promo-banner">
            <div class="promo-title">🎉 Special Offer!</div>
            <div class="promo-text">Get 20% off on your next order when you book a table today!</div>
            <div class="promo-code">Code: SAVE20</div>
        </div>
        <?php endif; ?>

        <!-- Recent Activity Feed -->
        <div class="activity-feed">
            <div class="activity-title">Recent Activity</div>
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="activity-text">New pasta dish added to menu</div>
            </div>
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-chair"></i>
                </div>
                <div class="activity-text">VIP table now available</div>
            </div>
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-gift"></i>
                </div>
                <div class="activity-text">Weekend special menu launched</div>
            </div>
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="activity-text">5-star review received!</div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Action Button -->
<div class="floating-btn" onclick="scrollToTop()">
    <i class="fas fa-arrow-up"></i>
</div>

<script>
// Live Clock Functionality
function updateClock() {
    const now = new Date();
    
    // Format time
    const timeOptions = {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    };
    const timeString = now.toLocaleTimeString('en-US', timeOptions);
    
    // Format date
    const dateOptions = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    };
    const dateString = now.toLocaleDateString('en-US', dateOptions);
    
    // Update DOM elements
    const timeElement = document.getElementById('current-time');
    const dateElement = document.getElementById('current-date');
    
    if (timeElement) timeElement.textContent = timeString;
    if (dateElement) dateElement.textContent = dateString;
}

// Smooth scroll to top function
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Initialize clock and set interval
document.addEventListener('DOMContentLoaded', function() {
    updateClock();
    setInterval(updateClock, 1000);
    
    // Add click animations to cards
    const cards = document.querySelectorAll('.action-card, .mini-stat-card, .stat-card');
    cards.forEach(card => {
        card.addEventListener('click', function() {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
    
    // Weather icon rotation on hover
    const weatherIcon = document.querySelector('.weather-icon');
    if (weatherIcon) {
        weatherIcon.addEventListener('mouseenter', function() {
            this.style.animationDuration = '2s';
        });
        weatherIcon.addEventListener('mouseleave', function() {
            this.style.animationDuration = '10s';
        });
    }
    
    // Promo banner click effect
    const promoBanner = document.querySelector('.promo-banner');
    if (promoBanner) {
        promoBanner.addEventListener('click', function() {
            // Copy promo code to clipboard
            const promoCode = 'SAVE20';
            navigator.clipboard.writeText(promoCode).then(function() {
                // Show temporary notification
                const originalText = promoBanner.querySelector('.promo-code').textContent;
                promoBanner.querySelector('.promo-code').textContent = 'Copied!';
                promoBanner.style.background = 'linear-gradient(135deg, #00b894, #00a085)';
                
                setTimeout(() => {
                    promoBanner.querySelector('.promo-code').textContent = originalText;
                    promoBanner.style.background = 'linear-gradient(135deg, #fd79a8, #e84393)';
                }, 2000);
            });
        });
    }
    
    // Add click event listeners to mobile menu items
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
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe elements for animation
    const animatedElements = document.querySelectorAll('.action-card, .stat-card, .right-sidebar > *');
    animatedElements.forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(el);
    });
});

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

// User Actions Dropdown toggle function
function toggleUserActionsDropdown() {
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    
    userDropdownMenu.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const userDropdownContainer = document.querySelector('.user-actions-dropdown');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    
    // Close user actions dropdown
    if (userDropdownContainer && !userDropdownContainer.contains(event.target)) {
        userDropdownMenu.classList.remove('show');
    }
});

// Handle window resize for mobile menu
window.addEventListener('resize', function() {
    const mobileNav = document.getElementById('mobileNav');
    const overlay = document.getElementById('mobileOverlay');
    
    if (window.innerWidth > 768 && mobileNav && overlay) {
        mobileNav.classList.remove('active');
        overlay.classList.remove('active');
    }
});

// Add some interactive particle effects
function createFloatingParticle() {
    const particle = document.createElement('div');
    particle.className = 'floating-particle';
    particle.style.cssText = `
        position: fixed;
        width: 4px;
        height: 4px;
        background: rgba(255, 255, 255, 0.6);
        border-radius: 50%;
        pointer-events: none;
        z-index: 1000;
        animation: floatUp 4s linear forwards;
    `;
    
    const startX = Math.random() * window.innerWidth;
    particle.style.left = startX + 'px';
    particle.style.bottom = '-10px';
    
    document.body.appendChild(particle);
    
    setTimeout(() => {
        particle.remove();
    }, 4000);
}

// Create floating particles periodically
setInterval(createFloatingParticle, 3000);

// Add CSS for floating particles
const style = document.createElement('style');
style.textContent = `
    @keyframes floatUp {
        0% {
            transform: translateY(0) rotate(0deg);
            opacity: 1;
        }
        100% {
            transform: translateY(-100vh) rotate(360deg);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>

</body>
</html>
