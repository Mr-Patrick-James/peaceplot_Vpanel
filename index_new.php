<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: public/dashboard.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$error = '';
$stats = [
    'total_burials' => 0,
    'total_lots' => 0,
    'total_sections' => 0,
    'total_images' => 0
];

// Get statistics from database
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        // Get total burials
        $stmt = $conn->query("SELECT COUNT(*) as count FROM burial_records");
        $result = $stmt->fetch();
        $stats['total_burials'] = $result['count'] ?? 0;
        
        // Get total lots
        $stmt = $conn->query("SELECT COUNT(*) as count FROM cemetery_lots");
        $result = $stmt->fetch();
        $stats['total_lots'] = $result['count'] ?? 0;
        
        // Get total sections
        $stmt = $conn->query("SELECT COUNT(*) as count FROM sections");
        $result = $stmt->fetch();
        $stats['total_sections'] = $result['count'] ?? 0;
        
        // Get total images
        $stmt = $conn->query("SELECT COUNT(*) as count FROM burial_images");
        $result = $stmt->fetch();
        $stats['total_images'] = $result['count'] ?? 0;
    }
} catch (Exception $e) {
    // Silently fail - stats will show 0
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            try {
                $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1");
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                $user = $stmt->fetch();
                
                // Note: In production, use password_hash() and password_verify()
                // For now, simple comparison (as per seed data)
                if ($user && $user['password_hash'] === $password) {
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Update last login
                    $updateStmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id");
                    $updateStmt->bindParam(':id', $user['id']);
                    $updateStmt->execute();
                    
                    // Redirect to dashboard
                    header('Location: public/dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid username or password';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        } else {
            $error = 'Database connection failed';
        }
    } else {
        $error = 'Please enter both username and password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PeacePlot - Cemetery Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2f6df6;
            --primary-dark: #1e4fd6;
            --primary-light: #e8f0fe;
            --secondary: #10b981;
            --accent: #8b5cf6;
            --text: #1c2736;
            --text-light: #4b5563;
            --muted: #6b7a90;
            --border: #e4edf6;
            --background: #f9fafb;
            --card-bg: #ffffff;
            --error: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --radius: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        /* Header Styles */
        header {
            background: linear-gradient(135deg, #1e3a8a 0%, #2f6df6 100%);
            color: white;
            padding: 1.5rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-lg);
        }
        
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }
        
        .logo-icon i {
            font-size: 1.5rem;
        }
        
        .logo-text h1 {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }
        
        .logo-text p {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        nav ul {
            display: flex;
            list-style: none;
            gap: 2rem;
        }
        
        nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: opacity 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        nav a:hover {
            opacity: 0.8;
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('assets/images/cemetery.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 6rem 2rem;
            text-align: center;
            position: relative;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero h2 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }
        
        .hero p {
            font-size: 1.25rem;
            margin-bottom: 2.5rem;
            opacity: 0.9;
        }
        
        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 1rem 2rem;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            backdrop-filter: blur(10px);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        /* Stats Section */
        .stats {
            background: white;
            padding: 4rem 2rem;
            margin-top: -3rem;
            position: relative;
            z-index: 10;
        }
        
        .stats-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }
        
        .stat-icon {
            width: 64px;
            height: 64px;
            background: var(--primary-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        
        .stat-icon i {
            font-size: 1.75rem;
            color: var(--primary);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 1rem;
            color: var(--text-light);
            font-weight: 500;
        }
        
        /* About Section */
        .about {
            padding: 6rem 2rem;
            background: var(--background);
        }
        
        .about-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }
        
        .about-content h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            color: var(--text);
        }
        
        .about-content p {
            font-size: 1.125rem;
            color: var(--text-light);
            margin-bottom: 1.5rem;
        }
        
        .about-image {
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-xl);
        }
        
        .about-image img {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 0.5s;
        }
        
        .about-image:hover img {
            transform: scale(1.05);
        }
        
        /* Features Section */
        .features {
            padding: 6rem 2rem;
            background: white;
        }
        
        .section-header {
            text-align: center;
            max-width: 800px;
            margin: 0 auto 4rem;
        }
        
        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            color: var(--text);
        }
        
        .section-header p {
            font-size: 1.125rem;
            color: var(--text-light);
        }
        
        .features-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }
        
        .feature-icon {
            width: 56px;
            height: 56px;
            background: var(--primary-light);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        
        .feature-icon i {
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        .feature-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text);
        }
        
        .feature-card p {
            color: var(--text-light);
            margin-bottom: 1.5rem;
        }
        
        /* Login Section */
        .login-section {
            padding: 6rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .login-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }
        
        .login-info {
            color: white;
        }
        
        .login-info h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
        }
        
        .login-info p {
            font-size: 1.125rem;
            opacity: 0.9;
            margin-bottom: 2rem;
        }
        
        .login-form-container {
            background: white;
            border-radius: var(--radius-xl);
            padding: 3rem;
            box-shadow: var(--shadow-xl);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-header h3 {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 0.5rem;
        }
        
        .form-header p {
            color: var(--text-light);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text);
        }
        
        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(47, 109, 246, 0.1);
        }
        
        .error-message {
            background: #fee;
            color: var(--error);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            border-left: 4px solid var(--error);
        }
        
        .btn-login {
            width: 100%;
            padding: 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        /* Footer */
        footer {
            background: var(--text);
            color: white;
            padding: 4rem 2rem 2rem;
        }
        
        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
        }
        
        .footer-column h4 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        
        .footer-column ul {
            list-style: none;
        }
        
        .footer-column ul li {
            margin-bottom: 0.75rem;
        }
        
        .footer-column a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .footer-column a:hover {
            color: white;
        }
        
        .footer-bottom {
            max-width: 1200px;
            margin: 3rem auto 0;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.875rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            nav ul {
                gap: 1rem;
            }
            
            .hero h2 {
                font-size: 2.5rem;
            }
            
            .hero p {
                font-size: 1.125rem;
            }
            
            .about-container,
            .login-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .hero {
                padding: 4rem 1rem;
            }
            
            .hero h2 {
                font-size: 2rem;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-church"></i>
                </div>
                <div class="logo-text">
                    <h1>PeacePlot</h1>
                    <p>Cemetery Management System</p>
                </div>
            </div>
            <nav>
                <ul>
                    <li><a href="#home"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="#about"><i class="fas fa-info-circle"></i> About</a></li>
                    <li><a href="#features"><i class="fas fa-star"></i> Features</a></li>
                    <li><a href="#login"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <h2>Honoring Memories, Preserving History</h2>
            <p>PeacePlot Cemetery Management System provides comprehensive digital management for cemetery records, burial plots, and historical documentation with modern technology and compassionate care.</p>
            <div class="cta-buttons">
                <a href="#login" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Access System
                </a>
                <a href="#about" class="btn btn-secondary">
                    <i class="fas fa-book-open"></i> Learn More
                </a>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-cross"></i>
                </div>
                <div class="stat-number"><?php echo number_format($stats['total_burials']); ?></div>
                <div class="stat-label">Burial Records</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="stat-number"><?php echo number_format($stats['total_lots']); ?></div>
                <div class="stat-label">Cemetery Lots</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-number"><?php echo number_format($stats['total_sections']); ?></div>
                <div class="stat-label">Sections</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-images"></i>
                </div>
                <div class="stat-number"><?php echo number_format($stats['total_images']); ?></div>
                <div class="stat-label">Memorial Images</div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about" id="about">
        <div class="about-container">
            <div class="about-content">
                <h2>Our Sacred Grounds</h2>
                <p>Established in 1850, PeacePlot Cemetery has been serving our community for over 170 years. Our beautiful grounds provide a peaceful resting place for generations of families.</p>
                <p>With meticulously maintained gardens, historic monuments, and a serene chapel, we offer a respectful environment for remembrance and reflection.</p>
                <p>Our modern management system ensures that every burial record, plot location, and historical detail is preserved with the utmost care and accuracy.</p>
                <a href="#features" class="btn btn-primary">
                    <i class="fas fa-eye"></i> View Features
                </a>
            </div>
            <div class="about-image">
                <img src="assets/images/Hero/Church.jpg" alt="PeacePlot Cemetery Map">
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="section-header">
            <h2>Comprehensive Management Features</h2>
            <p>Our system provides everything needed for modern cemetery management</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-database"></i>
                </div>
                <h3>Digital Records</h3>
                <p>Complete digitalization of burial records with searchable database and secure cloud backup.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h3>Interactive Mapping</h3>
                <p>Visual plot management with GPS coordinates, section layouts, and availability tracking.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-camera"></i>
                </div>
                <h3>Image Management</h3>
                <p>Upload and organize memorial photos, grave markers, and historical documentation.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Reporting & Analytics</h3>
                <p>Generate comprehensive reports, statistics, and historical analysis for better decision making.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-history"></i>
                </div>
                <h3>Historical Archive</h3>
                <p>Preserve historical records with version control and audit trails for all changes.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Secure Access</h3>
                <p>Role-based permissions, encrypted data, and secure authentication for authorized personnel.</p>
            </div>
        </div>
    </section>

    <!-- Login Section -->
    <section class="login-section" id="login">
        <div class="login-container">
            <div class="login-info">
                <h2>Access the Management System</h2>
                <p>Authorized personnel can log in to manage cemetery records, update burial information, and access administrative features.</p>
                <p>Our secure system ensures that sensitive information is protected while providing easy access for authorized staff and administrators.</p>
                <div class="feature-list">
                    <p><i class="fas fa-check-circle"></i> Secure authentication</p>
                    <p><i class="fas fa-check-circle"></i> Role-based access control</p>
                    <p><i class="fas fa-check-circle"></i> Encrypted data transmission</p>
                    <p><i class="fas fa-check-circle"></i> Activity logging</p>
                </div>
            </div>
            <div class="login-form-container">
                <div class="form-header">
                    <h3>Sign In</h3>
                    <p>Enter your credentials to access the system</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            placeholder="Enter your username"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            required
                            autofocus
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Enter your password"
                            required
                        >
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-container">
            <div class="footer-column">
                <h4>PeacePlot Cemetery</h4>
                <p>123 Memorial Lane<br>
                Serenity Valley, SV 12345<br>
                United States</p>
                <p><i class="fas fa-phone"></i> (555) 123-4567</p>
                <p><i class="fas fa-envelope"></i> info@peaceplot.com</p>
            </div>
            <div class="footer-column">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="#home"><i class="fas fa-chevron-right"></i> Home</a></li>
                    <li><a href="#about"><i class="fas fa-chevron-right"></i> About Us</a></li>
                    <li><a href="#features"><i class="fas fa-chevron-right"></i> Features</a></li>
                    <li><a href="#login"><i class="fas fa-chevron-right"></i> Login</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h4>Services</h4>
                <ul>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Burial Services</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Plot Management</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Historical Records</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Memorial Services</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h4>Connect With Us</h4>
                <ul>
                    <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                    <li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li>
                    <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                    <li><a href="#"><i class="fab fa-linkedin"></i> LinkedIn</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> PeacePlot Cemetery Management System. All rights reserved.</p>
            <p>Designed with compassion and modern technology</p>
        </div>
    </footer>

    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.stat-card, .feature-card, .about-image, .login-form-container').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            observer.observe(el);
        });
    </script>
</body>
</html>