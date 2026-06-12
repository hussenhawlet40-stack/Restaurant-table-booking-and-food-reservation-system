<?php
// Load restaurant settings
require_once 'includes/restaurant_config.php';
$restaurant_settings = getRestaurantSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - Adabina Restaurant</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            overflow-x: hidden;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #434652ff, #d6baf2ff);
            backdrop-filter: blur(15px);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            padding: 15px 0;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .logo {
            font-size: 2.4rem;
            font-weight: bold;
            color: white;
           text-shadow: 0 0 10px black;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 30px;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .mobile-menu-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        /* Mobile Navigation Overlay */
        .mobile-nav-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .mobile-nav-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .mobile-nav {
            position: fixed;
            top: 0;
            right: -100%;
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea, #764ba2);
            z-index: 1000;
            padding: 20px;
            box-shadow: -2px 0 20px rgba(0,0,0,0.1);
            transition: right 0.3s ease;
            overflow-y: auto;
        }

        .mobile-nav.active {
            right: 0;
        }

        .mobile-nav-close {
            display: block;
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .mobile-nav-close:hover {
            background: rgba(255,255,255,0.1);
        }

        .mobile-nav-header {
            margin-bottom: 30px;
            padding-top: 40px;
            text-align: center;
        }

        .mobile-nav-header h3 {
            color: white;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .mobile-nav-menu {
            list-style: none;
            padding: 0;
        }

        .mobile-nav-menu li {
            margin-bottom: 10px;
        }

        .mobile-nav-menu a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 15px 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            font-weight: 500;
        }

        .mobile-nav-menu a:hover,
        .mobile-nav-menu a.active {
            background: rgba(255,255,255,0.2);
            transform: translateX(5px);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.2)), url('https://images.unsplash.com/photo-1414235077428-338989a2e8c0?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            position: relative;
        }

        .hero-content h1 {
            font-size: 4rem;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #7b3f00, #6f4e37, #c9a24d, #2a1506);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }   transition: all 0.3s ease;
        

        .hero:hover .hero-content h1 {
            transform: scale(1.05);
            text-shadow: 3px 3px 10px rgba(0,0,0,0.8);
        }

        .hero-content p {
            font-size: 1.3rem;
            margin-bottom: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            color: #FFFDD0;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.8);
            font-weight: 500;
            line-height: 1.6;
            text-align: center;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s ease;
        }

        .hero:hover .hero-content p {
            opacity: 1;
            transform: translateY(0);
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(45deg, #344c3d);
            color: white;
            padding: 15px 40px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(255,107,107,0.3);
            position: relative;
            overflow: hidden;
        }

        .cta-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(255,107,107,0.4);
            background: linear-gradient(45deg, #738a6e);
        }

        .cta-button:hover::before {
            left: 100%;
        }

        /* Section Styles */
        .section {
            padding: 100px 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .section-title {
            text-align: center;
            font-size: 3rem;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #738A6E, #344C3D);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-subtitle {
            text-align: center;
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 60px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* About Section */
        #about {
            background: linear-gradient(135deg, #f2f8f1ff);
        }

        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .about-text {
            font-size: 1.1rem;
            line-height: 1.8;

            
        }

        .about-text h3 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: #344C3D;
           
        }

        .about-image {
            text-align: center;
        }

        .about-image img {
            max-width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 60px;
        }

        .stat-item {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: #344c3d;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1.1rem;
            color: #666;
        }

        /* Services Section */
        #services {
            background: white;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
        }

        .service-card {
            background: linear-gradient(135deg, #f2f8f1ff);
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .service-card:hover {
            transform: translateY(-10px);
            border-color: #344c3d;
            box-shadow: 0 20px 40px rgba(102,126,234,0.2);
        }

        .service-icon {
            font-size: 3rem;
            color: #344c3d;
            margin-bottom: 20px;
        }

        .service-card h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #333;
        }

        .service-card p {
            color: #666;
            line-height: 1.6;
        }

        /* Location Section */
        #location {
            background: linear-gradient(135deg, #8ea58c , #f2f8f1ff);
            color: black;
        }

        .location-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .location-info h3 {
            font-size: 1.8rem;
            margin-bottom: 30px;
        }

        .location-details {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .location-item {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1.1rem;
        }

        .location-item i {
            font-size: 1.3rem;
            width: 30px;
        }

        .map-container {
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Footer */
        .footer {
            background: #2c3e50;
            color: white;
            padding: 60px 0 30px;
            text-align: center;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-section h3 {
            margin-bottom: 20px;
            color: #e6ece9ff;
        }

        .footer-section p,
        .footer-section a {
            color: #bdc3c7;
            text-decoration: none;
            line-height: 1.6;
        }

        .footer-section a:hover {
            color: white;
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }

        .social-links a {
            display: inline-block;
            width: 50px;
            height: 50px;
            background: #1f1c1cff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            transform: translateY(-3px);
            background: #344c3d;
        }

        .footer-bottom {
            border-top: 1px solid #34495e;
            padding-top: 20px;
            color: #95a5a6;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }

            .nav-menu {
                display: none;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }

            .section-title {
                font-size: 2rem;
            }

            .about-content,
            .location-content {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .stats {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }

        /* Smooth Scrolling */
        html {
            scroll-behavior: smooth;
        }

        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-on-scroll {
            animation: fadeInUp 0.8s ease-out;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="nav-container">
            <a href="#" class="logo">
                <i class="fas fa-utensils"></i>
                <?= htmlspecialchars($restaurant_settings['restaurant_name']) ?>
            </a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="#about" class="active">About</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#location">Location</a></li>
                    <li><a href="login.php">Sign In</a></li>
                </ul>
            </nav>
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Mobile Navigation -->
    <div class="mobile-nav-overlay" id="mobileOverlay"></div>
    <div class="mobile-nav" id="mobileNav">
        <button class="mobile-nav-close" id="mobileNavClose">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="mobile-nav-header">
            <h3><?= htmlspecialchars($restaurant_settings['restaurant_name']) ?></h3>
        </div>
        
        <ul class="mobile-nav-menu">
            <li><a href="#about" class="mobile-nav-link">About</a></li>
            <li><a href="#services" class="mobile-nav-link">Services</a></li>
            <li><a href="#location" class="mobile-nav-link">Location</a></li>
            <li><a href="login.php">Sign In</a></li>
        </ul>
    </div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Welcome to <?= htmlspecialchars($restaurant_settings['restaurant_name']) ?></h1>
            <p><?= htmlspecialchars($restaurant_settings['description']) ?></p>
            <a href="#about" class="cta-button">
                <i class="fas fa-arrow-down"></i> Discover Our Story
            </a>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="section">
        <div class="container">
            <h2 class="section-title">About <?= htmlspecialchars($restaurant_settings['restaurant_name']) ?></h2>
           
            
            <div class="about-content">
                <div class="about-text">
                    <h3>Our Story</h3>
                    <p>Adabina Restaurant shares the rich culinary heritage of Ethiopia. "Adabina" means "end of Meskel Festival" in Gurage, reflecting our connection to Ethiopian cultural celebrations.</p>
                    
                    <p>Founded on September 25, 2004, Adabina Restaurant is led by our head chef, Chef Hawlet, who brings over 15 years of culinary experience. We draw inspiration from Ethiopia's central region, particularly the Gurage area where our name originates.</p>
                    
                    <p>Using traditional cooking methods, imported spices, and family recipes, every dish tells a story of Ethiopian culture and warm hospitality.</p>
                </div>
                <div class="about-image">
                    <img src="chef.png" alt="Chef Hawlet presenting authentic Ethiopian cuisine" style="max-width: 100%; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); height: 80vh;">
                </div>
            </div>

            <div class="stats">
                <div class="stat-item">
                    <div class="stat-number">5000+</div>
                    <div class="stat-label">Happy Customers</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">50+</div>
                    <div class="stat-label">Authentic Dishes</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">20</div>
                    <div class="stat-label">Years of Excellence</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="section">
        <div class="container">
            <h2 class="section-title">Our Services</h2>
            <p class="section-subtitle">Complete dining experience with various services</p>
            
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h3>Dine-In Experience</h3>
                    <p>Enjoy authentic Ethiopian cuisine in our beautifully decorated restaurant with traditional coffee ceremony and live cultural music on weekends.</p>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <h3>Online Ordering</h3>
                    <p>Pre-order your favorite dishes online and skip the wait. Easy customization and scheduling for pickup or delivery.</p>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Table Reservations</h3>
                    <p>Reserve your table in advance through our online booking system. Perfect for special occasions and business meetings.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Location Section -->
    <section id="location" class="section">
        <div class="container">
            <h2 class="section-title" style="color: white;">Visit Us</h2>
            <p class="section-subtitle" style="color: black;">Find us in the heart of the city, easily accessible and welcoming</p>
            
            <div class="location-content">
                <div class="location-info">
                    <h3><?= htmlspecialchars($restaurant_settings['restaurant_name']) ?> Location</h3>
                    <div class="location-details">
                        <div class="location-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?= htmlspecialchars($restaurant_settings['location']) ?></span>
                        </div>
                        <div class="location-item">
                            <i class="fas fa-phone"></i>
                            <span><?= htmlspecialchars($restaurant_settings['phone']) ?></span>
                        </div>
                        <div class="location-item">
                            <i class="fas fa-envelope"></i>
                            <span><?= htmlspecialchars($restaurant_settings['email']) ?></span>
                        </div>
                        <div class="location-item">
                            <i class="fas fa-clock"></i>
                            <span><?= formatOperatingHours($restaurant_settings['opening_time'], $restaurant_settings['closing_time']) ?></span>
                        </div>
                        <div class="location-item">
                            <i class="fas fa-wifi"></i>
                            <span>Free WiFi for all guests</span>
                        </div>
                    </div>
                </div>
                <div class="map-container">
                    <div style="text-align: center;">
                        <i class="fas fa-map" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.7;"></i>
                        <p>Interactive Map Coming Soon</p>
                        <p style="margin-top: 10px; opacity: 0.8;">Located in the heart of Gurage region, Central Ethiopia</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><?= htmlspecialchars($restaurant_settings['restaurant_name']) ?></h3>
                    <p>Authentic Ethiopian cuisine served with love and tradition. Experience the rich flavors and warm hospitality that make Ethiopian food special.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($restaurant_settings['location']) ?></p>
                    <p><i class="fas fa-phone"></i> <?= htmlspecialchars($restaurant_settings['phone']) ?></p>
                    <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($restaurant_settings['email']) ?></p>
                    <p><i class="fas fa-clock"></i> <?= formatOperatingHours($restaurant_settings['opening_time'], $restaurant_settings['closing_time']) ?></p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 <?= htmlspecialchars($restaurant_settings['restaurant_name']) ?>. All rights reserved. | Bringing Ethiopian culture to your table.</p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu state
        let mobileMenuOpen = false;

        // Mobile menu toggle function
        function toggleMobileMenu() {
            console.log('Toggle mobile menu called');
            const mobileNav = document.getElementById('mobileNav');
            const overlay = document.getElementById('mobileOverlay');
            
            if (!mobileNav || !overlay) {
                console.error('Mobile menu elements not found');
                return;
            }
            
            mobileMenuOpen = !mobileMenuOpen;
            console.log('Mobile menu open:', mobileMenuOpen);
            
            if (mobileMenuOpen) {
                mobileNav.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
                console.log('Mobile menu opened');
            } else {
                mobileNav.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
                console.log('Mobile menu closed');
            }
        }

        // Close mobile menu function
        function closeMobileMenu() {
            console.log('Close mobile menu called');
            const mobileNav = document.getElementById('mobileNav');
            const overlay = document.getElementById('mobileOverlay');
            
            if (mobileNav && overlay) {
                mobileNav.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
                mobileMenuOpen = false;
                console.log('Mobile menu force closed');
            }
        }

        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');
            
            // Mobile menu button
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            console.log('Mobile menu button:', mobileMenuBtn);
            
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Mobile menu button clicked!');
                    toggleMobileMenu();
                });
                console.log('Mobile menu button event listener added');
            } else {
                console.error('Mobile menu button not found!');
            }

            // Mobile menu close button
            const mobileNavClose = document.getElementById('mobileNavClose');
            if (mobileNavClose) {
                mobileNavClose.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Close button clicked');
                    closeMobileMenu();
                });
                console.log('Close button event listener added');
            }

            // Mobile overlay
            const mobileOverlay = document.getElementById('mobileOverlay');
            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Overlay clicked');
                    closeMobileMenu();
                });
                console.log('Overlay event listener added');
            }

            // Mobile nav links
            const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');
            mobileNavLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    console.log('Mobile nav link clicked');
                    setTimeout(() => {
                        closeMobileMenu();
                    }, 300);
                });
            });

            // Smooth scrolling for navigation links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Close mobile menu when window is resized to desktop
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    closeMobileMenu();
                }
            });
        });

        // Active navigation highlighting
        window.addEventListener('scroll', () => {
            const sections = document.querySelectorAll('.section');
            const navLinks = document.querySelectorAll('.nav-menu a');
            
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (scrollY >= (sectionTop - 200)) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${current}`) {
                    link.classList.add('active');
                }
            });
        });

        // Animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-on-scroll');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.service-card, .stat-item').forEach(el => {
            observer.observe(el);
        });
    </script>
</body>
</html>