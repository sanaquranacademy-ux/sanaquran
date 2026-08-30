<?php
session_start();
require_once 'db.php';

$success_msg = '';
$err_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'trial_register') {
    $name = trim($_POST['student_name']);
    $guardian = trim($_POST['guardian_name']);
    $phone = trim($_POST['phone']);
    $country = trim($_POST['country']);
    $course = $_POST['course'];
    $class_time = $_POST['class_time'];
    $joining_date = date('Y-m-d');

    if (!empty($name) && !empty($phone)) {
        $stmt = $conn->prepare("INSERT INTO students (name, guardian_name, phone, country, course, class_time, joining_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("sssssss", $name, $guardian, $phone, $country, $course, $class_time, $joining_date);
        if ($stmt->execute()) {
            $success_msg = "Registration successful! Our team will contact you on WhatsApp shortly.";
        } else {
            $err_msg = "Something went wrong. Please try again or contact via WhatsApp.";
        }
    } else {
        $err_msg = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SANA - Online Quran House</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: #f8faf9; color: #1e293b; line-height: 1.6; }

        /* ======== NAVBAR ======== */
        .navbar {
            background: #ffffff;
            padding: 16px 45px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 12px rgba(16, 185, 129, 0.08);
            border-bottom: 1px solid #e6f4ea;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .brand-logo { width: 38px; height: 38px; background: #10b981; color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; }
        .brand-text h2 { font-size: 19px; font-weight: 800; color: #065f46; letter-spacing: 0.5px; margin: 0; }
        .brand-text p { font-size: 10px; color: #059669; font-weight: 700; letter-spacing: 1px; margin: 0; }

        .nav-links { display: flex; align-items: center; gap: 18px; }
        .nav-link { text-decoration: none; color: #475569; font-weight: 600; font-size: 14px; transition: 0.2s; }
        .nav-link:hover { color: #059669; }
        
        .btn-nav-portal {
            background: #ecfdf5;
            color: #059669;
            padding: 7px 16px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            border: 1px solid #a7f3d0;
            transition: 0.2s;
        }
        .btn-nav-portal:hover { background: #10b981; color: white; }

        .btn-nav-login {
            background: #059669;
            color: white;
            padding: 8px 18px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            transition: 0.2s;
            box-shadow: 0 4px 10px rgba(5, 150, 105, 0.2);
        }
        .btn-nav-login:hover { background: #047857; transform: translateY(-1px); }

        /* ======== HERO SECTION ======== */
        .hero {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 50%, #ecfdf5 100%);
            padding: 60px 45px 80px 45px;
            position: relative;
            overflow: hidden;
        }
        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 45px;
            align-items: center;
        }

        .hero-text h1 {
            font-size: 42px;
            line-height: 1.25;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 20px;
        }
        .hero-text h1 span {
            color: #059669;
            background: linear-gradient(135deg, #059669, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-text p {
            font-size: 16px;
            color: #475569;
            margin-bottom: 30px;
            max-width: 520px;
        }

        .hero-buttons { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; }
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 13px 26px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 14.5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 6px 18px rgba(16, 185, 129, 0.35);
            transition: 0.2s;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(16, 185, 129, 0.45); }
        
        .btn-whatsapp {
            background: #25d366;
            color: white;
            padding: 13px 24px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 14.5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 6px 18px rgba(37, 211, 102, 0.25);
            transition: 0.2s;
        }
        .btn-whatsapp:hover { background: #1ebd5a; transform: translateY(-2px); }

        /* ======== REGISTRATION FORM CARD ======== */
        .form-card {
            background: #ffffff;
            padding: 30px 32px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.1);
            border: 1.5px solid #d1fae5;
        }
        .form-header { margin-bottom: 20px; }
        .form-header h3 { font-size: 20px; font-weight: 800; color: #065f46; margin-bottom: 4px; }
        .form-header p { font-size: 12px; color: #059669; font-weight: 600; }

        .form-group { margin-bottom: 14px; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 11px 16px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #1e293b;
            font-size: 13.5px;
            outline: none;
            transition: 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #10b981;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 13px;
            border: none;
            border-radius: 10px;
            font-size: 14.5px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 6px;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        }
        .btn-submit:hover { opacity: 0.95; transform: translateY(-1px); }

        /* ======== SECTION WRAPPER & HEADINGS ======== */
        .section { padding: 75px 45px; max-width: 1200px; margin: 0 auto; }
        .section-header { text-align: center; margin-bottom: 50px; }
        .section-header h2 { font-size: 32px; font-weight: 800; color: #065f46; margin-bottom: 10px; }
        .section-header p { font-size: 15px; color: #64748b; max-width: 600px; margin: 0 auto; }

        /* ======== COURSES GRID ======== */
        .courses-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 25px; }
        .course-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 28px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .course-card:hover {
            transform: translateY(-5px);
            border-color: #10b981;
            box-shadow: 0 12px 30px rgba(16, 185, 129, 0.12);
        }
        .course-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: #ecfdf5;
            color: #059669;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 18px;
        }
        .course-card h3 { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 10px; }
        .course-card p { font-size: 13.5px; color: #64748b; margin-bottom: 20px; line-height: 1.5; }
        .course-btn {
            background: #f0fdf4;
            color: #047857;
            text-decoration: none;
            padding: 9px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            text-align: center;
            border: 1px solid #a7f3d0;
            transition: 0.2s;
        }
        .course-card:hover .course-btn { background: #10b981; color: white; border-color: #10b981; }

        /* ======== WHY CHOOSE US ======== */
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; }
        .feature-box {
            background: #ffffff;
            padding: 25px;
            border-radius: 16px;
            border: 1px solid #d1fae5;
            text-align: center;
        }
        .feature-box .feat-icon { font-size: 32px; margin-bottom: 12px; }
        .feature-box h4 { font-size: 16px; font-weight: 700; color: #065f46; margin-bottom: 6px; }
        .feature-box p { font-size: 13px; color: #64748b; }

        /* ======== CONTACT & FOOTER ======== */
        .contact-strip {
            background: linear-gradient(135deg, #065f46 0%, #047857 100%);
            color: white;
            border-radius: 20px;
            padding: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 60px;
        }
        .contact-strip h3 { font-size: 24px; font-weight: 800; margin-bottom: 6px; }
        .contact-strip p { font-size: 14px; opacity: 0.9; }

        .footer {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            padding: 30px 45px;
            text-align: center;
            color: #64748b;
            font-size: 13.5px;
        }

        .alert-success { background: #dcfce7; color: #15803d; padding: 10px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600; margin-bottom: 15px; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #b91c1c; padding: 10px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600; margin-bottom: 15px; border: 1px solid #fca5a5; }

        @media(max-width: 900px) {
            .hero-container { grid-template-columns: 1fr; }
            .navbar { padding: 14px 20px; }
            .hero { padding: 40px 20px; }
            .section { padding: 50px 20px; }
            .hero-text h1 { font-size: 32px; }
            .contact-strip { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<div class="navbar">
    <a href="index.php" class="brand">
        <div class="brand-logo">📖</div>
        <div class="brand-text">
            <h2>SANA</h2>
            <p>ONLINE QURAN HOUSE</p>
        </div>
    </a>
    
    <div class="nav-links">
        <a href="#courses" class="nav-link">Courses</a>
        <a href="#whyus" class="nav-link">Why Choose Us</a>
        <a href="#contact" class="nav-link">Contact</a>
       <a href="parent_login.php" class="btn-nav-portal">👨‍👩‍👦 Parent Portal</a>
        <a href="login.php" class="btn-nav-login">Portal Login →</a>
    </div>
</div>

<!-- Hero Section with Form -->
<div class="hero">
    <div class="hero-container">
        <div class="hero-text">
            <h1>Learn Holy Quran with Proper <span>Tajweed & Tarteel</span> Online</h1>
            <p>1-on-1 personalized live classes for kids and adults worldwide. Experienced male & female Quran tutors with flexible timings.</p>
            
            <div class="hero-buttons">
                <a href="#trial" class="btn-primary">Book 3-Days Free Trial</a>
                <a href="https://api.whatsapp.com/send?phone=971527194855&text=Assalam-o-Alaikum%20I%20want%20to%20join%20Quran%20Classes" target="_blank" class="btn-whatsapp">💬 WhatsApp: +971 52 719 4855</a>
            </div>
        </div>

        <!-- Registration Card -->
        <div class="form-card" id="trial">
            <div class="form-header">
                <h3>Book Free Trial Class</h3>
                <p>● No registration fee • Free 3-Days evaluation</p>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert-success"><?php echo $success_msg; ?></div>
            <?php elseif ($err_msg): ?>
                <div class="alert-error"><?php echo $err_msg; ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="trial_register">
                
                <div class="form-group">
                    <input type="text" name="student_name" placeholder="Student Full Name" required>
                </div>
                
                <div class="form-group">
                    <input type="text" name="guardian_name" placeholder="Parent / Guardian Name">
                </div>
                
                <div class="form-group">
                    <input type="text" name="phone" placeholder="WhatsApp Number (with Country Code)" required>
                </div>
                
                <div class="form-group">
                    <input type="text" name="country" placeholder="Your Country (e.g. UAE, UK, USA)">
                </div>
                
                <div class="form-group">
                    <select name="course">
                        <option value="Norani Qaida">Norani Qaida (Beginners)</option>
                        <option value="Nazra Quran">Nazra Quran with Tajweed</option>
                        <option value="Hifz Quran">Quran Memorization (Hifz)</option>
                        <option value="Tafseer-e-Quran">Tafseer & Quran Translation</option>
                        <option value="Islamic Studies">Islamic Studies, Namaz & Daily Duas</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <input type="text" name="class_time" placeholder="Preferred Class Timing (e.g. 7:00 PM)">
                </div>

                <button type="submit" class="btn-submit">Submit Registration</button>
            </form>
        </div>
    </div>
</div>

<!-- Courses Section -->
<div class="section" id="courses">
    <div class="section-header">
        <h2>Our Featured Online Courses</h2>
        <p>Comprehensive curriculum designed for kids, beginners, and advanced learners under qualified scholars.</p>
    </div>

    <div class="courses-grid">
        <div class="course-card">
            <div>
                <div class="course-icon">📗</div>
                <h3>Norani Qaida for Kids</h3>
                <p>Fundamental foundation course focusing on Arabic letters pronunciation (Makharij) and basic reading rules.</p>
            </div>
            <a href="#trial" class="course-btn">Enroll Course →</a>
        </div>

        <div class="course-card">
            <div>
                <div class="course-icon">📖</div>
                <h3>Nazra Quran with Tajweed</h3>
                <p>Complete recitation of the Holy Quran with precise Tajweed principles, rhythm, and fluency.</p>
            </div>
            <a href="#trial" class="course-btn">Enroll Course →</a>
        </div>

        <div class="course-card">
            <div>
                <div class="course-icon">🕌</div>
                <h3>Quran Memorization (Hifz)</h3>
                <p>Step-by-step Hifz-ul-Quran program with revision tracking, Sabaq, and Sabqi monitoring tools.</p>
            </div>
            <a href="#trial" class="course-btn">Enroll Course →</a>
        </div>

        <div class="course-card">
            <div>
                <div class="course-icon">✨</div>
                <h3>Islamic Studies & Duas</h3>
                <p>Learn daily Sunnah prayers, Kalmas, complete Salah method, Wudu rules, and Islamic morals for kids.</p>
            </div>
            <a href="#trial" class="course-btn">Enroll Course →</a>
        </div>
    </div>
</div>

<!-- Why Choose Us Section -->
<div class="section" id="whyus" style="background: #ffffff; border-radius: 24px; border: 1px solid #ecfdf5; margin-bottom: 60px;">
    <div class="section-header">
        <h2>Why Choose Sana Quran House?</h2>
        <p>We provide a dedicated, secure, and interactive digital learning atmosphere for your family.</p>
    </div>

    <div class="features-grid">
        <div class="feature-box">
            <div class="feat-icon">👨‍🏫</div>
            <h4>1-on-1 Dedicated Classes</h4>
            <p>Individual attention to each student to ensure faster learning and proper correction.</p>
        </div>

        <div class="feature-box">
            <div class="feat-icon">🧕</div>
            <h4>Male & Female Tutors</h4>
            <p>Certified, polite, and English/Urdu speaking male and female teachers available.</p>
        </div>

        <div class="feature-box">
            <div class="feat-icon">⏰</div>
            <h4>Flexible Class Timings</h4>
            <p>Choose class timings according to your local country time zone (24/7 availability).</p>
        </div>

        <div class="feature-box">
            <div class="feat-icon">📊</div>
            <h4>Parent Portal & Reports</h4>
            <p>Track your child's daily Sabaq, attendance, and exam reports online anytime.</p>
        </div>
    </div>
</div>

<!-- Contact Banner & Info -->
<div class="section" id="contact" style="padding-top: 0;">
    <div class="contact-strip">
        <div>
            <h3>Have Questions? Speak Directly With Us</h3>
            <p>Reach out on WhatsApp or email for instant admission support & fee details.</p>
        </div>
        <a href="https://api.whatsapp.com/send?phone=971527194855&text=Assalam-o-Alaikum%20Need%20info%20about%20classes" target="_blank" class="btn-whatsapp" style="background: white; color: #065f46;">💬 Chat on WhatsApp</a>
    </div>
</div>

<!-- Footer -->
<div class="footer">
    <p>© <?php echo date('Y'); ?> <strong>Sana Online Quran House</strong>. All Rights Reserved.</p>
    <p style="margin-top: 6px; font-size: 12px;">Contact: +971 52 719 4855 | WhatsApp Support Available 24/7</p>
</div>

</body>
</html>