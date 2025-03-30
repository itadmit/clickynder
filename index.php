<?php
// התחלת או המשך סשן
session_start();

// טעינת קבצי התצורה
require_once "config/database.php";
require_once "includes/functions.php";

// בדיקה אם המשתמש כבר מחובר
if (isLoggedIn()) {
    redirect("dashboard.php");
}

// חיבור למסד הנתונים
$database = new Database();
$db = $database->getConnection();

// קבלת מספר העסקים הרשומים במערכת
$query = "SELECT COUNT(*) as total FROM tenants";
$stmt = $db->prepare($query);
$stmt->execute();
$tenant_count = $stmt->fetch()['total'];

// קבלת מספר התורים שנקבעו במערכת
$query = "SELECT COUNT(*) as total FROM appointments";
$stmt = $db->prepare($query);
$stmt->execute();
$appointment_count = $stmt->fetch()['total'];

// לוגיקה להרשמה לניוזלטר אם נשלח הטופס
$newsletter_success = false;
$newsletter_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['subscribe'])) {
    $email = sanitizeInput($_POST['email']);
    
    if (!isValidEmail($email)) {
        $newsletter_error = "כתובת אימייל לא תקינה";
    } else {
        // בדיקה אם האימייל כבר קיים
        $query = "SELECT * FROM newsletter WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $newsletter_error = "כתובת האימייל כבר רשומה לניוזלטר";
        } else {
            // הוספה למסד הנתונים
            try {
                // בדיקה אם הטבלה קיימת
                $query = "SHOW TABLES LIKE 'newsletter'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                if ($stmt->rowCount() == 0) {
                    // יצירת הטבלה אם לא קיימת
                    $query = "CREATE TABLE newsletter (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        email VARCHAR(255) NOT NULL UNIQUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                }
                
                $query = "INSERT INTO newsletter (email) VALUES (:email)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":email", $email);
                $stmt->execute();
                
                $newsletter_success = true;
            } catch (PDOException $e) {
                $newsletter_error = "שגיאה ברישום לניוזלטר: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>קליקינדר - מערכת ניהול תורים חכמה</title>
    <!-- טעינת גופן Noto Sans Hebrew מ-Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Hebrew:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- טעינת Tailwind CSS מ-CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            light: '#f687b3', // ורוד בהיר
                            DEFAULT: '#ec4899', // ורוד
                            dark: '#db2777', // ורוד כהה
                        },
                        secondary: {
                            light: '#93c5fd', // כחול בהיר
                            DEFAULT: '#3b82f6', // כחול
                            dark: '#2563eb', // כחול כהה
                        },
                        pastel: {
                            purple: '#d8b4fe', // סגול פסטל
                            blue: '#bfdbfe',   // כחול פסטל
                            green: '#bbf7d0',  // ירוק פסטל
                            yellow: '#fef08a', // צהוב פסטל
                            orange: '#fed7aa', // כתום פסטל
                            red: '#fecaca',    // אדום פסטל
                        }
                    },
                    fontFamily: {
                        'noto': ['"Noto Sans Hebrew"', 'sans-serif'],
                    },
                    borderRadius: {
                        'xl': '1rem',
                        '2xl': '1.5rem',
                        '3xl': '2rem',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Noto Sans Hebrew', sans-serif;
        }
        .hero-gradient {
            background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        /* גלילה חלקה */
        html {
            scroll-behavior: smooth;
        }
        /* Scrollbar customization */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: #ec4899;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #db2777;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-sm sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <span class="text-2xl font-bold text-primary">קליקינדר</span>
                    </div>
    </footer>

    <script>
        // Mobile Menu Toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });
        
        // FAQ Toggles
        document.querySelectorAll('.faq-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const content = this.nextElementSibling;
                const icon = this.querySelector('i');
                
                content.classList.toggle('hidden');
                icon.classList.toggle('rotate-180');
                
                // סגירת כל האקורדיונים האחרים
                /*document.querySelectorAll('.faq-content').forEach(item => {
                    if (item !== content) {
                        item.classList.add('hidden');
                        item.previousElementSibling.querySelector('i').classList.remove('rotate-180');
                    }
                });*/
            });
        });
        
        // Smooth Scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    // סגירת תפריט מובייל אם פתוח
                    document.getElementById('mobile-menu').classList.add('hidden');
                    
                    // גלילה חלקה לאלמנט המטרה
                    window.scrollTo({
                        top: targetElement.offsetTop - 80, // קיזוז עבור ה-navbar
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>
                    <div class="hidden sm:mr-6 sm:flex sm:space-x-8 sm:space-x-reverse">
                        <a href="#features" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 transition duration-150 ease-in-out">
                            תכונות
                        </a>
                        <a href="#pricing" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 transition duration-150 ease-in-out">
                            מחירים
                        </a>
                        <a href="#faq" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 transition duration-150 ease-in-out">
                            שאלות נפוצות
                        </a>
                        <a href="#contact" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 transition duration-150 ease-in-out">
                            צור קשר
                        </a>
                    </div>
                </div>
                <div class="hidden sm:flex sm:items-center sm:mr-6">
                    <a href="login.php" class="mr-4 px-4 py-2 text-sm text-primary font-medium hover:text-primary-dark">
                        כניסה
                    </a>
                    <a href="register.php" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-primary hover:bg-primary-dark transition-colors">
                        הרשמה חינם
                    </a>
                </div>
                <div class="flex items-center sm:hidden">
                    <button id="mobile-menu-button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div id="mobile-menu" class="sm:hidden hidden">
            <div class="pt-2 pb-3 space-y-1">
                <a href="#features" class="block py-2 pr-4 pl-3 text-base font-medium text-gray-600 hover:bg-gray-50 hover:text-primary">
                    תכונות
                </a>
                <a href="#pricing" class="block py-2 pr-4 pl-3 text-base font-medium text-gray-600 hover:bg-gray-50 hover:text-primary">
                    מחירים
                </a>
                <a href="#faq" class="block py-2 pr-4 pl-3 text-base font-medium text-gray-600 hover:bg-gray-50 hover:text-primary">
                    שאלות נפוצות
                </a>
                <a href="#contact" class="block py-2 pr-4 pl-3 text-base font-medium text-gray-600 hover:bg-gray-50 hover:text-primary">
                    צור קשר
                </a>
                <div class="pt-4 pb-2 border-t border-gray-200">
                    <div class="flex items-center px-4">
                        <a href="login.php" class="block px-4 py-2 text-base font-medium text-primary hover:text-primary-dark">
                            כניסה
                        </a>
                        <a href="register.php" class="block px-4 py-2 text-base font-medium text-white bg-primary rounded-lg hover:bg-primary-dark">
                            הרשמה חינם
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-gradient text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 md:py-32">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6">ניהול תורים חכם לעסק שלך</h1>
                    <p class="text-lg md:text-xl mb-8 text-white text-opacity-90">
                        קליקינדר היא מערכת ניהול תורים מתקדמת שתעזור לך לנהל את הזמן שלך ביעילות,
                        להגדיל הכנסות ולשפר את חווית הלקוחות שלך.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="register.php" class="bg-white text-primary hover:bg-gray-100 font-bold py-3 px-6 rounded-xl text-center">
                            נסה בחינם עכשיו
                        </a>
                        <a href="#features" class="border border-white hover:bg-white hover:bg-opacity-20 font-semibold py-3 px-6 rounded-xl text-center">
                            גלה עוד
                        </a>
                    </div>
                    <div class="mt-8 flex items-center space-x-3 space-x-reverse">
                        <span class="text-white text-opacity-80">התחברו כבר</span>
                        <span class="text-2xl font-bold"><?php echo number_format($tenant_count); ?>+</span>
                        <span class="text-white text-opacity-80 mr-6">עסקים</span>
                        <span class="text-2xl font-bold"><?php echo number_format($appointment_count); ?>+</span>
                        <span class="text-white text-opacity-80">תורים</span>
                    </div>
                </div>
                <div class="flex justify-center">
                    <img src="assets/images/dashboard-preview.png" alt="תצוגה של ממשק המערכת" class="rounded-xl shadow-lg w-full max-w-md">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">למה לבחור בקליקינדר?</h2>
                <p class="text-lg text-gray-600 max-w-3xl mx-auto">
                    מערכת ניהול התורים המתקדמת שלנו נבנתה במיוחד כדי להקל על עסקים מכל הגדלים לנהל את לוח הזמנים שלהם בצורה חכמה ויעילה.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="bg-gray-50 p-6 rounded-2xl transition duration-300 feature-card">
                    <div class="w-12 h-12 bg-pastel-purple rounded-2xl flex items-center justify-center mb-4">
                        <i class="fas fa-calendar-check text-xl text-purple-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">הזמנת תורים אונליין</h3>
                    <p class="text-gray-600">
                        אפשר ללקוחות שלך לקבוע ולנהל תורים באתר שלך 24/7, ללא צורך בשיחות טלפון.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-gray-50 p-6 rounded-2xl transition duration-300 feature-card">
                    <div class="w-12 h-12 bg-pastel-blue rounded-2xl flex items-center justify-center mb-4">
                        <i class="fas fa-bell text-xl text-blue-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">תזכורות אוטומטיות</h3>
                    <p class="text-gray-600">
                        שליחת תזכורות אוטומטיות ללקוחות לפני התור באמצעות SMS או אימייל להפחתת אי-הגעות.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-gray-50 p-6 rounded-2xl transition duration-300 feature-card">
                    <div class="w-12 h-12 bg-pastel-green rounded-2xl flex items-center justify-center mb-4">
                        <i class="fas fa-chart-line text-xl text-green-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">דוחות וסטטיסטיקות</h3>
                    <p class="text-gray-600">
                        ניתוח ביצועים, מעקב אחר הכנסות וזיהוי מגמות כדי לקבל החלטות עסקיות מושכלות.
                    </p>
                </div>

                <!-- Feature 4 -->
                <div class="bg-gray-50 p-6 rounded-2xl transition duration-300 feature-card">
                    <div class="w-12 h-12 bg-pastel-yellow rounded-2xl flex items-center justify-center mb-4">
                        <i class="fas fa-users text-xl text-yellow-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">ניהול צוות ושירותים</h3>
                    <p class="text-gray-600">
                        ניהול שעות זמינות של העובדים, שיוך שירותים לאנשי צוות ספציפיים ותיאום לוחות זמנים.
                    </p>
                </div>

                <!-- Feature 5 -->
                <div class="bg-gray-50 p-6 rounded-2xl transition duration-300 feature-card">
                    <div class="w-12 h-12 bg-pastel-orange rounded-2xl flex items-center justify-center mb-4">
                        <i class="fas fa-mobile-alt text-xl text-orange-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">ממשק מותאם לנייד</h3>
                    <p class="text-gray-600">
                        גישה למערכת בכל זמן ומכל מקום, מותאמת לכל המכשירים - מחשב, טאבלט וסמארטפון.
                    </p>
                </div>

                <!-- Feature 6 -->
                <div class="bg-gray-50 p-6 rounded-2xl transition duration-300 feature-card">
                    <div class="w-12 h-12 bg-pastel-red rounded-2xl flex items-center justify-center mb-4">
                        <i class="fas fa-credit-card text-xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">תשלומים מקוונים</h3>
                    <p class="text-gray-600">
                        אפשרות לקבל מקדמות ותשלומים מלאים דרך האתר עם מערכת סליקה מאובטחת ומהירה.
                    </p>
                </div>
            </div>

            <div class="mt-16 text-center">
                <a href="register.php" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-8 rounded-xl inline-block">
                    נסה עכשיו ללא התחייבות
                </a>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">איך זה עובד?</h2>
                <p class="text-lg text-gray-600 max-w-3xl mx-auto">
                    התהליך פשוט וקל להתחלה - תוך דקות תוכלו לקבל תורים ולנהל את היומן שלכם בצורה חכמה.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Step 1 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4 text-white text-xl font-bold">1</div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">הרשמה מהירה</h3>
                    <p class="text-gray-600">
                        הירשמו למערכת, הגדירו את פרטי העסק שלכם והתחילו בחינם. ללא צורך בכרטיס אשראי.
                    </p>
                </div>

                <!-- Step 2 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4 text-white text-xl font-bold">2</div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">הגדרת שירותים וצוות</h3>
                    <p class="text-gray-600">
                        הגדירו את השירותים שאתם מציעים, אנשי הצוות והשעות הזמינות בכל יום.
                    </p>
                </div>

                <!-- Step 3 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4 text-white text-xl font-bold">3</div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">קבלת תורים אונליין</h3>
                    <p class="text-gray-600">
                        התחילו לקבל תורים באתר שלכם או דרך דף הנחיתה המובנה שאנחנו מספקים לכם.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">מחירים פשוטים וברורים</h2>
                <p class="text-lg text-gray-600 max-w-3xl mx-auto">
                    התחילו בחינם ושדרגו כשהעסק גדל. ללא חוזים ארוכים, ללא התחייבות.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Basic Plan -->
                <div class="border border-gray-200 rounded-2xl overflow-hidden transition duration-300 hover:shadow-lg">
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-2">בסיסי</h3>
                        <div class="flex items-baseline mb-4">
                            <span class="text-4xl font-bold text-gray-800">₪0</span>
                            <span class="text-gray-500 mr-2">/ לחודש</span>
                        </div>
                        <p class="text-gray-600 mb-6">מושלם לעסקים קטנים שרק מתחילים</p>
                        <ul class="space-y-3 mb-6">
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">עד 50 תורים בחודש</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">יומן תורים בסיסי</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">תזכורות במייל</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">דף נחיתה לקביעת תורים</span>
                            </li>
                            <li class="flex items-start text-gray-400">
                                <i class="fas fa-times mt-1 ml-2"></i>
                                <span>תזכורות SMS</span>
                            </li>
                            <li class="flex items-start text-gray-400">
                                <i class="fas fa-times mt-1 ml-2"></i>
                                <span>סליקת אשראי</span>
                            </li>
                            <li class="flex items-start text-gray-400">
                                <i class="fas fa-times mt-1 ml-2"></i>
                                <span>דוחות מתקדמים</span>
                            </li>
                        </ul>
                    </div>
                    <div class="px-6 pb-6">
                        <a href="register.php?plan=pro" class="block w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 px-4 rounded-xl text-center transition">
                            התחל עכשיו
                        </a>
                    </div>
                </div>
             

              

                <!-- Pro Plan -->
                <div class="border border-primary rounded-2xl overflow-hidden shadow-lg transform scale-105 z-10 bg-white">
                    <div class="bg-primary text-white text-center py-2 text-sm font-bold">הכי פופולרי</div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-2">מקצועי</h3>
                        <div class="flex items-baseline mb-4">
                            <span class="text-4xl font-bold text-gray-800">₪99</span>
                            <span class="text-gray-500 mr-2">/ לחודש</span>
                        </div>
                        <p class="text-gray-600 mb-6">מושלם לרוב העסקים</p>
                        <ul class="space-y-3 mb-6">
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">עד 300 תורים בחודש</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">יומן תורים מתקדם</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">תזכורות במייל ו-SMS</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">דף נחיתה מותאם אישית</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">סליקת אשראי</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">דוחות בסיסיים</span>
                            </li>
                            <li class="flex items-start text-gray-400">
                                <i class="fas fa-times mt-1 ml-2"></i>
                                <span>אינטגרציה עם מערכות חיצוניות</span>
                            </li>
                        </ul>
                    </div>
     


                </div>

                <!-- Business Plan -->
                <div class="border border-gray-200 rounded-2xl overflow-hidden transition duration-300 hover:shadow-lg">
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-2">עסקי</h3>
                        <div class="flex items-baseline mb-4">
                            <span class="text-4xl font-bold text-gray-800">₪249</span>
                            <span class="text-gray-500 mr-2">/ לחודש</span>
                        </div>
                        <p class="text-gray-600 mb-6">לעסקים גדולים עם צרכים מורכבים</p>
                        <ul class="space-y-3 mb-6">
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">תורים ללא הגבלה</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">כל התכונות הקיימות</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">תזכורות במייל, SMS ו-WhatsApp</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">סליקת אשראי (עמלה מופחתת)</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">דוחות מתקדמים ואנליטיקס</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">אינטגרציה עם מערכות חיצוניות</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 ml-2"></i>
                                <span class="text-gray-600">תמיכה VIP</span>
                            </li>
                        </ul>
                    </div>
                    <div class="px-6 pb-6">
                        <a href="register.php" class="block w-full bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-3 px-4 rounded-xl text-center transition">
                            התחל בחינם
                        </a>
                    </div>
                </div>
            </div>

            

            <div class="mt-10 text-center text-gray-600">
                כל התוכניות כוללות תמיכה טכנית ללא הגבלה ועדכוני תוכנה שוטפים.
                <a href="#contact" class="text-primary hover:underline">צור קשר</a> לקבלת הצעת מחיר מותאמת אישית.
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">מה לקוחותינו אומרים</h2>
                <p class="text-lg text-gray-600 max-w-3xl mx-auto">
                    אלפי עסקים כבר משתמשים בקליקינדר כדי לנהל את התורים שלהם ביעילות. הנה כמה מהחוויות שלהם.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Testimonial 1 -->
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 flex">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-6">
                        "המערכת שינתה לי את החיים! הורידה משמעותית את כמות אי-ההגעות, והלקוחות מאוד מרוצים מהיכולת לקבוע תורים באתר שלי 24/7."
                    </p>
                    <div class="flex items-center">
                        <img src="assets/images/testimonial-1.jpg" alt="תמונת פרופיל" class="w-12 h-12 rounded-full ml-4">
                        <div>
                            <h4 class="font-bold text-gray-800">מיכל לוי</h4>
                            <p class="text-gray-500 text-sm">סלון יופי, תל אביב</p>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 2 -->
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 flex">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-6">
                        "חיפשתי מערכת שתאפשר לי לנהל את הקליניקה ביעילות ובמקצועיות. קליקינדר חסכה לי זמן רב וייעלה את העסק באופן משמעותי."
                    </p>
                    <div class="flex items-center">
                        <img src="assets/images/testimonial-2.jpg" alt="תמונת פרופיל" class="w-12 h-12 rounded-full ml-4">
                        <div>
                            <h4 class="font-bold text-gray-800">ד"ר אלון כהן</h4>
                            <p class="text-gray-500 text-sm">קליניקה לפיזיותרפיה, חיפה</p>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 3 -->
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 flex">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-6">
                        "התמיכה הטכנית מדהימה! כל שאלה או בקשה נענית במהירות וביעילות. ממשק המשתמש אינטואיטיבי וקל לשימוש גם למי שפחות טכנולוגי."
                    </p>
                    <div class="flex items-center">
                        <img src="assets/images/testimonial-3.jpg" alt="תמונת פרופיל" class="w-12 h-12 rounded-full ml-4">
                        <div>
                            <h4 class="font-bold text-gray-800">נועה שפירא</h4>
                            <p class="text-gray-500 text-sm">סטודיו ליוגה, ירושלים</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">שאלות נפוצות</h2>
                <p class="text-lg text-gray-600 max-w-3xl mx-auto">
                    מצאת את התשובות לשאלות הנפוצות ביותר על המערכת שלנו
                </p>
            </div>

            <div class="max-w-3xl mx-auto">
                <div class="space-y-6">
                    <!-- Question 1 -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <button class="faq-toggle w-full flex justify-between items-center p-4 bg-gray-50 hover:bg-gray-100 focus:outline-none">
                            <span class="font-medium">האם ניתן לשלב את המערכת באתר שלי?</span>
                            <i class="fas fa-chevron-down transform transition-transform"></i>
                        </button>
                        <div class="faq-content hidden p-4 border-t border-gray-200">
                            <p class="text-gray-600">
                                בהחלט! המערכת שלנו מציעה אפשרויות שונות לשילוב באתר שלך, כולל כפתור הזמנת תור, יישומון (widget) מוטמע ו-API מלא לאינטגרציה מתקדמת. בנוסף, אנו מספקים דף נחיתה ייעודי לקביעת תורים שניתן להפנות אליו מהאתר שלך.
                            </p>
                        </div>
                    </div>

                    <!-- Question 2 -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <button class="faq-toggle w-full flex justify-between items-center p-4 bg-gray-50 hover:bg-gray-100 focus:outline-none">
                            <span class="font-medium">האם המערכת תומכת במספר עובדים/נותני שירות?</span>
                            <i class="fas fa-chevron-down transform transition-transform"></i>
                        </button>
                        <div class="faq-content hidden p-4 border-t border-gray-200">
                            <p class="text-gray-600">
                                כן, המערכת תומכת במספר בלתי מוגבל של עובדים בהתאם לחבילה שלך. אתה יכול להגדיר את שעות הזמינות של כל אחד מהם בנפרד, לשייך להם שירותים ספציפיים ולנהל את לוח הזמנים שלהם במקום אחד. כל עובד יכול לקבל גישה אישית למערכת עם הרשאות מותאמות.
                            </p>
                        </div>
                    </div>

                    <!-- Question 3 -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <button class="faq-toggle w-full flex justify-between items-center p-4 bg-gray-50 hover:bg-gray-100 focus:outline-none">
                            <span class="font-medium">איך עובד מנגנון התזכורות?</span>
                            <i class="fas fa-chevron-down transform transition-transform"></i>
                        </button>
                        <div class="faq-content hidden p-4 border-t border-gray-200">
                            <p class="text-gray-600">
                                המערכת שולחת תזכורות אוטומטיות ללקוחות לפני התור שלהם. אתה יכול להגדיר את זמן שליחת התזכורות (למשל, יום לפני או שעתיים לפני) ואת ערוץ השליחה (אימייל, SMS או WhatsApp בחבילות מתקדמות). הלקוחות יכולים לאשר את הגעתם או לבטל את התור ישירות מהתזכורת, דבר המסייע בהפחתת אי-הגעות.
                            </p>
                        </div>
                    </div>

                    <!-- Question 4 -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <button class="faq-toggle w-full flex justify-between items-center p-4 bg-gray-50 hover:bg-gray-100 focus:outline-none">
                            <span class="font-medium">האם יש התחייבות חוזית ארוכת טווח?</span>
                            <i class="fas fa-chevron-down transform transition-transform"></i>
                        </button>
                        <div class="faq-content hidden p-4 border-t border-gray-200">
                            <p class="text-gray-600">
                                לא, אנחנו מאמינים בגמישות ובחופש בחירה. התשלום הוא על בסיס חודשי ואתה יכול לבטל בכל עת ללא קנסות. אנחנו גם מציעים תוכנית שנתית עם הנחה משמעותית למי שמעוניין בחיסכון. אנחנו בטוחים שתאהב את המערכת ותישאר איתנו, אבל ללא התחייבות כלשהי.
                            </p>
                        </div>
                    </div>

                    <!-- Question 5 -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <button class="faq-toggle w-full flex justify-between items-center p-4 bg-gray-50 hover:bg-gray-100 focus:outline-none">
                            <span class="font-medium">מה קורה אם אני עובר את מכסת התורים החודשית?</span>
                            <i class="fas fa-chevron-down transform transition-transform"></i>
                        </button>
                        <div class="faq-content hidden p-4 border-t border-gray-200">
                            <p class="text-gray-600">
                                במקרה שאתה עובר את מכסת התורים החודשית בחבילה שלך, יש שתי אפשרויות: אתה יכול לשדרג לחבילה גבוהה יותר בכל עת, או לשלם תוספת קטנה עבור כל תור נוסף מעבר למכסה. אנחנו נשלח לך התראה כשאתה מתקרב למכסה כדי שתוכל להחליט מה הפתרון המתאים לך. בכל מקרה, המערכת תמשיך לעבוד ללא הפרעה.
                            </p>
                        </div>
                    </div>

                    <!-- Question 6 -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <button class="faq-toggle w-full flex justify-between items-center p-4 bg-gray-50 hover:bg-gray-100 focus:outline-none">
                            <span class="font-medium">איך עובדת מערכת התשלומים המקוונים?</span>
                            <i class="fas fa-chevron-down transform transition-transform"></i>
                        </button>
                        <div class="faq-content hidden p-4 border-t border-gray-200">
                            <p class="text-gray-600">
                                המערכת מאפשרת לך לקבל תשלומים מקוונים בעת קביעת התור. אתה יכול להגדיר תשלום מלא, תשלום מקדמה או ללא תשלום לכל שירות. אנחנו עובדים עם מגוון שערי סליקה מובילים (כולל ישראכרט, PayPal, ו-Stripe) עם אבטחה מתקדמת. עמלות הסליקה תלויות בשער התשלום ובחבילה שבחרת, עם עמלות מופחתות בחבילות הגבוהות יותר.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-primary rounded-2xl p-8 md:p-12">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl md:text-3xl font-bold text-white mb-4">הישאר מעודכן</h2>
                    <p class="text-white text-opacity-90 mb-6">
                        הירשם לניוזלטר שלנו וקבל עדכונים על תכונות חדשות, טיפים לניהול עסק וקופונים מיוחדים.
                    </p>
                    
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>#newsletter" class="sm:flex justify-center">
                        <input type="email" name="email" placeholder="הזן את האימייל שלך" required
                              class="w-full sm:w-auto px-4 py-3 rounded-lg sm:rounded-l-none sm:rounded-r-lg border-0 focus:ring-2 focus:ring-white focus:ring-opacity-50 mb-2 sm:mb-0">
                        <button type="submit" name="subscribe" 
                               class="w-full sm:w-auto bg-white text-primary hover:bg-gray-100 font-bold py-3 px-8 rounded-lg sm:rounded-r-none sm:rounded-l-lg transition-colors">
                            הירשם
                        </button>
                    </form>
                    
                    <?php if ($newsletter_success): ?>
                        <p class="mt-4 text-white bg-green-500 bg-opacity-20 p-2 rounded-lg">
                            תודה! נרשמת בהצלחה לניוזלטר שלנו.
                        </p>
                    <?php elseif (!empty($newsletter_error)): ?>
                        <p class="mt-4 text-white bg-red-500 bg-opacity-20 p-2 rounded-lg">
                            <?php echo $newsletter_error; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">צור קשר</h2>
                <p class="text-lg text-gray-600 max-w-3xl mx-auto">
                    יש לך שאלות? צוות התמיכה שלנו זמין לעזור לך בכל נושא.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <div class="bg-gray-50 rounded-2xl p-8">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">שלח לנו הודעה</h3>
                    <form>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="name" class="block text-gray-700 font-medium mb-2">שם מלא</label>
                                <input type="text" id="name" name="name" required
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                            </div>
                            <div>
                                <label for="email" class="block text-gray-700 font-medium mb-2">אימייל</label>
                                <input type="email" id="email" name="email" required
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                            </div>
                            <div>
                                <label for="subject" class="block text-gray-700 font-medium mb-2">נושא</label>
                                <input type="text" id="subject" name="subject" required
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                            </div>
                            <div>
                                <label for="message" class="block text-gray-700 font-medium mb-2">הודעה</label>
                                <textarea id="message" name="message" rows="4" required
                                         class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"></textarea>
                            </div>
                            <div>
                                <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-8 rounded-xl">
                                    שלח הודעה
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4">פרטי התקשרות</h3>
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center bg-primary bg-opacity-10 rounded-lg ml-4">
                                <i class="fas fa-map-marker-alt text-primary"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-medium text-gray-800">כתובת</h4>
                                <p class="text-gray-600">רחוב אבן גבירול 30, תל אביב</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center bg-primary bg-opacity-10 rounded-lg ml-4">
                                <i class="fas fa-phone text-primary"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-medium text-gray-800">טלפון</h4>
                                <p class="text-gray-600 dir-ltr">+972-3-123-4567</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center bg-primary bg-opacity-10 rounded-lg ml-4">
                                <i class="fas fa-envelope text-primary"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-medium text-gray-800">אימייל</h4>
                                <p class="text-gray-600 dir-ltr">support@klickinder.co.il</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">שעות פעילות</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600">ימים א'-ה'</span>
                                <span class="text-gray-800 font-medium">9:00 - 18:00</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">יום ו'</span>
                                <span class="text-gray-800 font-medium">9:00 - 13:00</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">יום ש'</span>
                                <span class="text-gray-800 font-medium">סגור</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">עקבו אחרינו</h3>
                        <div class="flex space-x-4 space-x-reverse">
                            <a href="#" class="h-10 w-10 flex items-center justify-center bg-primary bg-opacity-10 rounded-lg text-primary hover:bg-opacity-20 transition">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="h-10 w-10 flex items-center justify-center bg-primary bg-opacity-10 rounded-lg text-primary hover:bg-opacity-20 transition">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="h-10 w-10 flex items-center justify-center bg-primary bg-opacity-10 rounded-lg text-primary hover:bg-opacity-20 transition">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <a href="#" class="h-10 w-10 flex items-center justify-center bg-primary bg-opacity-10 rounded-lg text-primary hover:bg-opacity-20 transition">
                                <i class="fab fa-twitter"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-12 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-6">מוכן להתחיל?</h2>
            <p class="text-lg text-gray-600 max-w-3xl mx-auto mb-8">
                הצטרף לאלפי העסקים שכבר משתמשים בקליקינדר לניהול התורים שלהם. ללא התחייבות, ניתן לבטל בכל עת.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="register.php" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-8 rounded-xl">
                    נסה בחינם עכשיו
                </a>
                <a href="#contact" class="bg-white border-2 border-primary text-primary hover:bg-primary-light hover:text-white hover:border-primary-light font-bold py-3 px-8 rounded-xl">
                    בקש הדגמה אישית
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Logo and Description -->
                <div class="col-span-1 md:col-span-1">
                    <div class="text-2xl font-bold mb-4">קליקינדר</div>
                    <p class="text-gray-400 mb-4">
                        מערכת ניהול תורים חכמה המותאמת במיוחד לעסקים בישראל.
                    </p>
                    <div class="flex space-x-4 space-x-reverse">
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-twitter"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="text-lg font-bold mb-4">קישורים מהירים</h4>
                    <ul class="space-y-2">
                        <li><a href="#features" class="text-gray-400 hover:text-white">תכונות</a></li>
                        <li><a href="#pricing" class="text-gray-400 hover:text-white">מחירים</a></li>
                        <li><a href="#faq" class="text-gray-400 hover:text-white">שאלות נפוצות</a></li>
                        <li><a href="#contact" class="text-gray-400 hover:text-white">צור קשר</a></li>
                        <li><a href="blog.php" class="text-gray-400 hover:text-white">בלוג</a></li>
                    </ul>
                </div>

                <!-- Legal -->
                <div>
                    <h4 class="text-lg font-bold mb-4">מידע משפטי</h4>
                    <ul class="space-y-2">
                        <li><a href="terms.php" class="text-gray-400 hover:text-white">תנאי שימוש</a></li>
                        <li><a href="privacy.php" class="text-gray-400 hover:text-white">מדיניות פרטיות</a></li>
                        <li><a href="cookies.php" class="text-gray-400 hover:text-white">מדיניות עוגיות</a></li>
                        <li><a href="refund.php" class="text-gray-400 hover:text-white">מדיניות החזרים</a></li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div>
                    <h4 class="text-lg font-bold mb-4">צור קשר</h4>
                    <ul class="space-y-2">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt mt-1 ml-2"></i>
                            <span class="text-gray-400">רחוב אבן גבירול 30, תל אביב</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-phone mt-1 ml-2"></i>
                            <span class="text-gray-400 dir-ltr">+972-3-123-4567</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-envelope mt-1 ml-2"></i>
                            <span class="text-gray-400 dir-ltr">support@klickinder.co.il</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="mt-8 pt-8 border-t border-gray-700 text-gray-400 text-sm text-center">
                <p>&copy; <?php echo date('Y'); ?> קליקינדר. כל הזכויות שמורות.</p>
            </div>
        </div>
   