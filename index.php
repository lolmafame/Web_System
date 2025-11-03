<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management System</title>
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
        }

        /* Header & Navigation */
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        nav {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }

        .nav-links a:hover {
            opacity: 0.8;
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.5rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: white;
            color: #667eea;
            font-weight: 600;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-secondary:hover {
            background: white;
            color: #667eea;
        }

        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            flex-direction: column;
            cursor: pointer;
            gap: 0.3rem;
        }

        .menu-toggle span {
            width: 25px;
            height: 3px;
            background: white;
            transition: all 0.3s;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6rem 2rem;
            text-align: center;
        }

        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Events Section */
        .events-section {
            max-width: 1200px;
            margin: 4rem auto;
            padding: 0 2rem;
        }

        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-header h2 {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .section-header p {
            color: #666;
            font-size: 1.1rem;
        }

        /* Event Filters */
        .event-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .filter-input {
            flex: 1;
            min-width: 250px;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .filter-select {
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            min-width: 150px;
        }

        /* Event Grid */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .event-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }

        .event-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }

        .event-content {
            padding: 1.5rem;
        }

        .event-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #f0f0f0;
            color: #667eea;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }

        .event-title {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .event-details {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .event-detail-item {
            margin-bottom: 0.5rem;
        }

        .event-capacity {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: #f8f8f8;
            border-radius: 5px;
        }

        .btn-register {
            width: 100%;
            padding: 0.75rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn-register:hover {
            background: #764ba2;
        }

        .btn-register:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Features Section */
        .features {
            background: #f8f8f8;
            padding: 4rem 2rem;
        }

        .features-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        /* Contact Section */
        .contact-section {
            max-width: 1200px;
            margin: 4rem auto;
            padding: 0 2rem;
        }

        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-top: 2rem;
        }

        .contact-info {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .contact-info h3 {
            color: #667eea;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .contact-item {
            display: flex;
            align-items: start;
            margin-bottom: 1.5rem;
        }

        .contact-icon {
            font-size: 1.5rem;
            margin-right: 1rem;
            min-width: 30px;
        }

        .contact-details h4 {
            margin-bottom: 0.25rem;
            color: #333;
        }

        .contact-details p {
            color: #666;
            line-height: 1.6;
        }

        .contact-form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .contact-form h3 {
            color: #667eea;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .btn-submit {
            width: 100%;
            padding: 0.75rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn-submit:hover {
            background: #764ba2;
        }

        /* Footer */
        footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 2rem;
        }

        footer p {
            margin-bottom: 0.5rem;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            /* Navigation */
            nav {
                padding: 0 1rem;
                flex-wrap: wrap;
            }

            .logo {
                font-size: 1.2rem;
            }

            .menu-toggle {
                display: flex;
            }

            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                gap: 0;
                margin-top: 1rem;
            }

            .nav-links.active {
                display: flex;
            }

            .nav-links li {
                width: 100%;
                text-align: center;
                padding: 0.75rem 0;
                border-top: 1px solid rgba(255,255,255,0.2);
            }

            .auth-buttons {
                width: 100%;
                margin-top: 1rem;
                justify-content: center;
            }

            .auth-buttons.mobile-hidden {
                display: none;
            }

            .auth-buttons.mobile-visible {
                display: flex;
            }

            /* Hero */
            .hero {
                padding: 3rem 1rem;
            }

            .hero h1 {
                font-size: 2rem;
            }

            .hero p {
                font-size: 1rem;
            }

            /* Sections */
            .events-section {
                margin: 2rem auto;
                padding: 0 1rem;
            }

            .section-header h2 {
                font-size: 1.8rem;
            }

            .section-header p {
                font-size: 1rem;
            }

            /* Event Filters */
            .event-filters {
                flex-direction: column;
            }

            .filter-input,
            .filter-select {
                width: 100%;
                min-width: auto;
            }

            /* Event Grid */
            .events-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            /* Features */
            .features {
                padding: 2rem 1rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .feature-card {
                padding: 1.5rem;
            }

            .feature-icon {
                font-size: 2.5rem;
            }

            /* Contact Section */
            .contact-section {
                margin: 2rem auto;
                padding: 0 1rem;
            }

            .contact-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .contact-info,
            .contact-form {
                padding: 1.5rem;
            }
        }

        /* Tablet Responsive Styles */
        @media (min-width: 769px) and (max-width: 1024px) {
            nav {
                padding: 0 1.5rem;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .events-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 1.5rem;
            }

            .features-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
        }

        /* Small Desktop */
        @media (min-width: 1025px) and (max-width: 1280px) {
            .events-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* Extra Small Mobile */
        @media (max-width: 480px) {
            .logo {
                font-size: 1rem;
            }

            .btn {
                padding: 0.4rem 1rem;
                font-size: 0.9rem;
            }

            .hero {
                padding: 2rem 1rem;
            }

            .hero h1 {
                font-size: 1.5rem;
            }

            .hero p {
                font-size: 0.9rem;
            }

            .section-header h2 {
                font-size: 1.5rem;
            }

            .event-image {
                height: 150px;
                font-size: 2.5rem;
            }

            .event-content {
                padding: 1rem;
            }

            .event-title {
                font-size: 1.1rem;
            }
        }

        /* Touch device hover adjustments */
        @media (hover: none) {
            .event-card:hover {
                transform: none;
            }

            .btn-primary:hover,
            .btn-register:hover {
                transform: none;
            }

            .btn-secondary:hover {
                background: transparent;
                color: white;
            }
        }
    </style>
</head>
<body>
    <!-- Header & Navigation -->
    <header>
        <nav>
            <div class="logo">🎟️ EventHub</div>
            <div class="menu-toggle" id="menuToggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <ul class="nav-links" id="navLinks">
                <li><a href="#home">Home</a></li>
                <li><a href="#events">Browse Events</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            <div class="auth-buttons" id="authButtons">
                <a href="login.php" class="btn btn-secondary">Login</a>
                <a href="register.php" class="btn btn-primary">Sign Up</a>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <h1>Discover & Join Amazing Events</h1>
        <p>Browse seminars, workshops, and conferences. Register easily and receive instant confirmation for events you want to attend.</p>
        <a href="#events" class="btn btn-primary">Browse Events</a>
    </section>

    <!-- Events Browsing Section -->
    <section class="events-section" id="events">
        <div class="section-header">
            <h2>Upcoming Events</h2>
            <p>Find and register for events that interest you</p>
        </div>

        <!-- Event Filters -->
        <div class="event-filters">
            <input type="text" class="filter-input" placeholder="Search events..." id="searchInput">
            <select class="filter-select" id="typeFilter">
                <option value="">All Types</option>
                <option value="seminar">Seminar</option>
                <option value="workshop">Workshop</option>
                <option value="conference">Conference</option>
            </select>
            <select class="filter-select" id="dateFilter">
                <option value="">All Dates</option>
                <option value="today">Today</option>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
            </select>
        </div>

        <!-- Events Grid -->
        <div class="events-grid">
            <!-- Sample Event Card 1 -->
            <div class="event-card">
                <div class="event-image">📊</div>
                <div class="event-content">
                    <span class="event-type">Conference</span>
                    <h3 class="event-title">Digital Marketing Summit 2025</h3>
                    <div class="event-details">
                        <div class="event-detail-item">📅 November 15, 2025</div>
                        <div class="event-detail-item">🕒 9:00 AM - 5:00 PM</div>
                        <div class="event-detail-item">📍 Grand Convention Center</div>
                    </div>
                    <div class="event-capacity">
                        <span>Available Slots:</span>
                        <strong>45/100</strong>
                    </div>
                    <button class="btn-register">Register Now</button>
                </div>
            </div>

            <!-- Sample Event Card 2 -->
            <div class="event-card">
                <div class="event-image">💻</div>
                <div class="event-content">
                    <span class="event-type">Workshop</span>
                    <h3 class="event-title">Web Development Bootcamp</h3>
                    <div class="event-details">
                        <div class="event-detail-item">📅 November 20, 2025</div>
                        <div class="event-detail-item">🕒 10:00 AM - 4:00 PM</div>
                        <div class="event-detail-item">📍 Tech Hub Innovation Center</div>
                    </div>
                    <div class="event-capacity">
                        <span>Available Slots:</span>
                        <strong>12/30</strong>
                    </div>
                    <button class="btn-register">Register Now</button>
                </div>
            </div>

            <!-- Sample Event Card 3 -->
            <div class="event-card">
                <div class="event-image">🎓</div>
                <div class="event-content">
                    <span class="event-type">Seminar</span>
                    <h3 class="event-title">AI & Machine Learning Fundamentals</h3>
                    <div class="event-details">
                        <div class="event-detail-item">📅 November 25, 2025</div>
                        <div class="event-detail-item">🕒 2:00 PM - 6:00 PM</div>
                        <div class="event-detail-item">📍 University Auditorium</div>
                    </div>
                    <div class="event-capacity">
                        <span>Available Slots:</span>
                        <strong>0/50</strong>
                    </div>
                    <button class="btn-register" disabled>Fully Booked</button>
                </div>
            </div>

            <!-- Sample Event Card 4 -->
            <div class="event-card">
                <div class="event-image">🎨</div>
                <div class="event-content">
                    <span class="event-type">Workshop</span>
                    <h3 class="event-title">UI/UX Design Masterclass</h3>
                    <div class="event-details">
                        <div class="event-detail-item">📅 December 1, 2025</div>
                        <div class="event-detail-item">🕒 1:00 PM - 5:00 PM</div>
                        <div class="event-detail-item">📍 Creative Studio</div>
                    </div>
                    <div class="event-capacity">
                        <span>Available Slots:</span>
                        <strong>18/25</strong>
                    </div>
                    <button class="btn-register">Register Now</button>
                </div>
            </div>

            <!-- Sample Event Card 5 -->
            <div class="event-card">
                <div class="event-image">💼</div>
                <div class="event-content">
                    <span class="event-type">Conference</span>
                    <h3 class="event-title">Business Innovation Forum</h3>
                    <div class="event-details">
                        <div class="event-detail-item">📅 December 5, 2025</div>
                        <div class="event-detail-item">🕒 8:00 AM - 6:00 PM</div>
                        <div class="event-detail-item">📍 International Convention Hall</div>
                    </div>
                    <div class="event-capacity">
                        <span>Available Slots:</span>
                        <strong>80/150</strong>
                    </div>
                    <button class="btn-register">Register Now</button>
                </div>
            </div>

            <!-- Sample Event Card 6 -->
            <div class="event-card">
                <div class="event-image">🔬</div>
                <div class="event-content">
                    <span class="event-type">Seminar</span>
                    <h3 class="event-title">Data Science for Beginners</h3>
                    <div class="event-details">
                        <div class="event-detail-item">📅 December 10, 2025</div>
                        <div class="event-detail-item">🕒 3:00 PM - 7:00 PM</div>
                        <div class="event-detail-item">📍 Science Park Lecture Hall</div>
                    </div>
                    <div class="event-capacity">
                        <span>Available Slots:</span>
                        <strong>35/40</strong>
                    </div>
                    <button class="btn-register">Register Now</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section" id="contact">
        <div class="section-header">
            <h2>Contact Us</h2>
            <p>Have questions? Get in touch with us</p>
        </div>

        <div class="contact-container">
            <!-- Contact Information -->
            <div class="contact-info">
                <h3>Get In Touch</h3>
                
                <div class="contact-item">
                    <div class="contact-icon">📍</div>
                    <div class="contact-details">
                        <h4>Address</h4>
                        <p>University of Caloocan City<br>
                        Biglang Awa Street, Caloocan City<br>
                        Metro Manila, Philippines 1400</p>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon">📧</div>
                    <div class="contact-details">
                        <h4>Email</h4>
                        <p>events@ucc.edu.ph<br>
                        support@eventhub.ucc.edu.ph</p>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon">📞</div>
                    <div class="contact-details">
                        <h4>Phone</h4>
                        <p>+63 2 8961-5497<br>
                        +63 917 123 4567 (Mobile)</p>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon">🕒</div>
                    <div class="contact-details">
                        <h4>Office Hours</h4>
                        <p>Monday - Friday: 8:00 AM - 5:00 PM<br>
                        Saturday: 9:00 AM - 12:00 PM<br>
                        Sunday: Closed</p>
                    </div>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="contact-form">
                <h3>Send us a Message</h3>
                <form id="contactForm">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>

                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Send Message</button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <p>&copy; 2025 Event Management System - University of Caloocan City</p>
        <p>Developed by Wayne Harty Bas & CJ Villarba</p>
    </footer>

    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const navLinks = document.getElementById('navLinks');
        const authButtons = document.getElementById('authButtons');

        menuToggle.addEventListener('click', function() {
            navLinks.classList.toggle('active');
        });

        // Close menu when clicking on a link
        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('active');
            });
        });

        // Simple search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const eventCards = document.querySelectorAll('.event-card');
            
            eventCards.forEach(card => {
                const title = card.querySelector('.event-title').textContent.toLowerCase();
                if (title.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Type filter functionality
        document.getElementById('typeFilter').addEventListener('change', function(e) {
            const filterValue = e.target.value.toLowerCase();
            const eventCards = document.querySelectorAll('.event-card');
            
            eventCards.forEach(card => {
                const type = card.querySelector('.event-type').textContent.toLowerCase();
                if (filterValue === '' || type === filterValue) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Register button click handler
        document.querySelectorAll('.btn-register').forEach(button => {
            button.addEventListener('click', function() {
                if (!this.disabled) {
                    alert('Please login or sign up to register for this event.');
                    window.location.href = 'login.php';
                }
            });
        });

        // Contact form submission
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value;
            
            // Simulate form submission
            alert(`Thank you, ${name}! Your message has been sent successfully.\n\nWe'll respond to ${email} within 24-48 hours.`);
            
            // Reset form
            this.reset();
        });
    </script>
</body>
</html>