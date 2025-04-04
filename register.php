<?php
// התחלת או המשך סשן
session_start();

// טעינת קבצי התצורה
require_once "config/database.php";
require_once "includes/functions.php";

// אם המשתמש כבר מחובר - הפנייה ללוח הבקרה
if (isLoggedIn()) {
    redirect("dashboard.php");
}

// משתנים להודעות שגיאה והצלחה
$error_message = "";
$success_message = "";

// בדיקה האם הטופס נשלח
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // קבלת נתונים מהטופס
    $business_name = sanitizeInput($_POST['business_name']);
    $slug = sanitizeInput($_POST['slug']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // וידוא שכל השדות החובה מולאו
    if (empty($business_name) || empty($slug) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $error_message = "כל שדות החובה חייבים להיות מלאים";
    } 
    // בדיקת תקינות אימייל
    elseif (!isValidEmail($email)) {
        $error_message = "כתובת האימייל אינה תקינה";
    }
    // בדיקה שהסיסמאות תואמות
    elseif ($password !== $confirm_password) {
        $error_message = "הסיסמאות אינן תואמות";
    }
    // בדיקת אורך סיסמה
    elseif (strlen($password) < 6) {
        $error_message = "הסיסמה חייבת להכיל לפחות 6 תווים";
    }
    // בדיקה שהסלאג מכיל רק אותיות לטיניות קטנות, מספרים ומקפים
    elseif (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        $error_message = "הסלאג יכול להכיל רק אותיות לטיניות קטנות, מספרים ומקפים";
    }
    else {
        try {
            // בדיקה האם האימייל כבר קיים במערכת
            $query = "SELECT email FROM tenants WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error_message = "כתובת האימייל כבר קיימת במערכת";
            } else {
                // בדיקה האם הסלאג כבר קיים
                $query = "SELECT slug FROM tenants WHERE slug = :slug";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":slug", $slug);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $error_message = "הסלאג כבר קיים במערכת, אנא בחר סלאג אחר";
                } else {
                    // הצפנת הסיסמה
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // הכנסת המשתמש החדש למסד הנתונים
                    $query = "INSERT INTO tenants (business_name, slug, email, password, phone) 
                              VALUES (:business_name, :slug, :email, :password, :phone)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":business_name", $business_name);
                    $stmt->bindParam(":slug", $slug);
                    $stmt->bindParam(":email", $email);
                    $stmt->bindParam(":password", $hashed_password);
                    $stmt->bindParam(":phone", $phone);
                    
                    if ($stmt->execute()) {
                        // קבלת ה-ID של המשתמש שנוצר
                        $tenant_id = $db->lastInsertId();
                        
                        // הוספת המשתמש למסלול הבסיסי
                        $query = "INSERT INTO tenant_subscriptions (tenant_id, subscription_id) 
                                 SELECT :tenant_id, id FROM subscriptions WHERE name = 'מסלול בסיסי'";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(":tenant_id", $tenant_id);
                        $stmt->execute();
                        
                        // יצירת הגדרות ברירת מחדל לעסק
                        // שעות פעילות ברירת מחדל
                        $default_hours = [
                            ["0", "09:00:00", "17:00:00", true],  // יום ראשון
                            ["1", "09:00:00", "17:00:00", true],  // יום שני
                            ["2", "09:00:00", "17:00:00", true],  // יום שלישי
                            ["3", "09:00:00", "17:00:00", true],  // יום רביעי
                            ["4", "09:00:00", "17:00:00", true],  // יום חמישי
                            ["5", "09:00:00", "14:00:00", true],  // יום שישי
                            ["6", null, null, false]               // יום שבת - סגור
                        ];
                        
                        $query = "INSERT INTO business_hours (tenant_id, day_of_week, open_time, close_time, is_open) 
                                  VALUES (:tenant_id, :day_of_week, :open_time, :close_time, :is_open)";
                        $stmt = $db->prepare($query);
                        
                        foreach ($default_hours as $hours) {
                            $stmt->bindParam(":tenant_id", $tenant_id);
                            $stmt->bindParam(":day_of_week", $hours[0]);
                            $stmt->bindParam(":open_time", $hours[1]);
                            $stmt->bindParam(":close_time", $hours[2]);
                            $stmt->bindParam(":is_open", $hours[3], PDO::PARAM_BOOL);
                            $stmt->execute();
                        }
                        
                        // הוספת בעל העסק גם כאיש צוות
                        $query = "INSERT INTO staff (tenant_id, name, email, phone, position, is_active) 
                                  VALUES (:tenant_id, :name, :email, :phone, 'בעל העסק', 1)";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(":tenant_id", $tenant_id);
                        $stmt->bindParam(":name", $business_name);
                        $stmt->bindParam(":email", $email);
                        $stmt->bindParam(":phone", $phone);
                        $stmt->execute();
                        
                        // הוספת תבניות הודעות ברירת מחדל
                        $default_templates = [
                            [
                                'booking_confirmation', 
                                'אישור קביעת תור ב' . $business_name, 
                                'שלום {first_name},

תודה שקבעת תור ב' . $business_name . '.

פרטי התור:
סוג טיפול: {service_name}
תאריך: {appointment_date}
שעה: {appointment_time}
נותן שירות: {staff_name}

מחכים לראותך!
צוות ' . $business_name . '
טל: ' . $phone,
                                true
                            ],
                            [
                                'appointment_reminder', 
                                'תזכורת לתור ב' . $business_name, 
                                'שלום {first_name},

תזכורת לגבי התור שנקבע מחר ב' . $business_name . '.

פרטי התור:
סוג טיפול: {service_name}
תאריך: {appointment_date}
שעה: {appointment_time}
נותן שירות: {staff_name}

נא לאשר את הגעתך בקישור הבא: {confirmation_link}

במידה ונבצר ממך להגיע, נא להודיע לנו בהקדם.

להתראות!
צוות ' . $business_name,
                                true
                            ]
                        ];
                        
                        $query = "INSERT INTO message_templates (tenant_id, type, subject, message_text, is_default) 
                                  VALUES (:tenant_id, :type, :subject, :message_text, :is_default)";
                        $stmt = $db->prepare($query);
                        
                        foreach ($default_templates as $template) {
                            $stmt->bindParam(":tenant_id", $tenant_id);
                            $stmt->bindParam(":type", $template[0]);
                            $stmt->bindParam(":subject", $template[1]);
                            $stmt->bindParam(":message_text", $template[2]);
                            $stmt->bindParam(":is_default", $template[3], PDO::PARAM_BOOL);
                            $stmt->execute();
                        }
                        
                        // הגדרת סשן והפניה לדף הבא
                        $_SESSION['tenant_id'] = $tenant_id;
                        $_SESSION['business_name'] = $business_name;
                        $_SESSION['email'] = $email;
                        $_SESSION['slug'] = $slug;
                        
                        // הודעת הצלחה והפניה לדף הבא
                        $success_message = "הרשמה בוצעה בהצלחה! מיד תועבר לדף הבא.";
                        header("Refresh: 2; URL=setup-business.php");
                    } else {
                        $error_message = "שגיאה בהרשמה, אנא נסה שנית";
                    }
                }
            }
        } catch (PDOException $e) {
            $error_message = "שגיאת מערכת: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>קליקינדר - הרשמה למערכת</title>
    <!-- טעינת גופן Noto Sans Hebrew מ-Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Hebrew:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- טעינת Tailwind CSS מ-CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
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
        .register-gradient {
            background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex flex-wrap min-h-screen">
        <!-- טופס הרשמה -->
        <div class="w-full md:w-1/2 flex justify-center items-center p-6">
            <div class="bg-white p-8 rounded-2xl shadow-lg w-full max-w-md">
                <div class="text-center mb-6">
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">קליקינדר</h1>
                    <p class="text-gray-600">מערכת ניהול תורים חכמה</p>
                </div>

                <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">הרשמה לבעלי עסק</h2>
                
                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6" role="alert">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-5">
                    <!-- פרטי עסק -->
                    <div>
                        <label for="business_name" class="block text-gray-700 font-medium mb-2">שם העסק</label>
                        <input type="text" id="business_name" name="business_name" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               placeholder="שם העסק שלך" value="<?php echo isset($_POST['business_name']) ? htmlspecialchars($_POST['business_name']) : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="slug" class="block text-gray-700 font-medium mb-2">כתובת אתר ייחודית</label>
                        <div class="flex items-center">
                            <input type="text" id="slug" name="slug" 
                                   class="flex-grow px-4 py-3 border-2 border-l-0 border-gray-300 rounded-r-xl focus:outline-none focus:border-primary"
                                   placeholder="your-business" dir="ltr" value="<?php echo isset($_POST['slug']) ? htmlspecialchars($_POST['slug']) : ''; ?>" required>
                            <span class="bg-gray-100 text-gray-500 px-3 py-3 border-2 border-r-0 border-gray-300 rounded-l-xl">
                                klickinder.com/
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">רק אותיות באנגלית קטנות, מספרים ומקפים</p>
                    </div>

                    <!-- פרטי משתמש -->
                    <div>
                        <label for="email" class="block text-gray-700 font-medium mb-2">כתובת אימייל</label>
                        <input type="email" id="email" name="email" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               placeholder="your@email.com" dir="ltr" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="phone" class="block text-gray-700 font-medium mb-2">מספר טלפון</label>
                        <input type="tel" id="phone" name="phone" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               placeholder="050-1234567" dir="ltr" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-gray-700 font-medium mb-2">סיסמה</label>
                        <div class="relative">
                            <input type="password" id="password" name="password" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                   placeholder="לפחות 6 תווים" required>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                <button type="button" id="togglePassword" class="text-gray-400 focus:outline-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">* הסיסמה צריכה להכיל לפחות 6 תווים</div>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-gray-700 font-medium mb-2">אימות סיסמה</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               placeholder="הקלד שוב את הסיסמה" required>
                    </div>
                    
                    <div class="flex items-start">
                        <input type="checkbox" id="terms" name="terms" class="w-5 h-5 mt-1 text-primary border-2 border-gray-300 rounded focus:ring-primary" required>
                        <label for="terms" class="mr-2 text-sm text-gray-700">
                            אני מסכים/ה ל<a href="terms.php" class="text-primary hover:underline">תנאי השימוש</a> ול<a href="privacy.php" class="text-primary hover:underline">מדיניות הפרטיות</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 px-4 rounded-xl transition duration-300">
                        הרשם עכשיו
                    </button>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-gray-600">כבר יש לך חשבון?</p>
                    <a href="login.php" class="text-primary font-medium hover:underline">התחבר כאן</a>
                </div>
            </div>
        </div>
        
        <!-- רקע גרדיאנט עם מידע -->
        <div class="hidden md:flex md:w-1/2 register-gradient justify-center items-center p-12">
            <div class="text-white max-w-md">
                <h2 class="text-4xl font-bold mb-8">הצטרף למהפכת ניהול התורים</h2>
                
                <div class="space-y-6">
                    <div class="flex items-start">
                        <div class="bg-white bg-opacity-20 p-2 rounded-xl">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="mr-4">
                            <h3 class="text-xl font-bold mb-1">חסוך זמן יקר</h3>
                            <p>ניהול תורים אוטומטי ללא צורך בניהול ידני</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="bg-white bg-opacity-20 p-2 rounded-xl">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="mr-4">
                            <h3 class="text-xl font-bold mb-1">ממשק ידידותי</h3>
                            <p>ממשק קל ונוח שיאפשר ללקוחות שלך לקבוע תורים בקלות מכל מכשיר</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="bg-white bg-opacity-20 p-2 rounded-xl">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </div>
                        <div class="mr-4">
                            <h3 class="text-xl font-bold mb-1">תזכורות חכמות</h3>
                            <p>תזכורות אוטומטיות ללקוחות יום לפני התור למניעת אי-הגעות</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-12 text-center">
                    <p class="text-xl font-bold">יותר מ-1,000 עסקים כבר משתמשים בקליקינדר</p>
                </div>
                
                <div class="mt-8 text-sm opacity-70 text-center">
                    <p>כל הזכויות שמורות © קליקינדר 2025</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Change the icon based on password visibility
            this.innerHTML = type === 'password' 
                ? '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>';
        });
        
        // Check if passwords match
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('הסיסמאות אינן תואמות');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Validate slug - only allow alphanumeric characters and hyphens
        document.getElementById('slug').addEventListener('input', function() {
            this.value = this.value.replace(/[^a-z0-9-]/g, '').toLowerCase();
        });
        
        // Auto-generate slug from business name
        document.getElementById('business_name').addEventListener('input', function() {
            const slugInput = document.getElementById('slug');
            // Only auto-generate if user hasn't manually changed the slug
            if (!slugInput.dataset.userModified) {
                // Convert to lowercase, replace spaces with hyphens, remove special chars
                let slug = this.value.toLowerCase()
                    .replace(/\s+/g, '-')          // Replace spaces with -
                    .replace(/[^\w\-]+/g, '')      // Remove special chars
                    .replace(/\-\-+/g, '-')        // Replace multiple - with single -
                    .replace(/^-+/, '')            // Trim - from start
                    .replace(/-+$/, '');           // Trim - from end
                
                // Replace Hebrew characters with English transliteration
                const hebrewToEnglish = {
                    'א': 'a', 'ב': 'b', 'ג': 'g', 'ד': 'd', 'ה': 'h', 'ו': 'v',
                    'ז': 'z', 'ח': 'ch', 'ט': 't', 'י': 'y', 'כ': 'k', 'ל': 'l',
                    'מ': 'm', 'נ': 'n', 'ס': 's', 'ע': 'a', 'פ': 'p', 'צ': 'tz',
                    'ק': 'k', 'ר': 'r', 'ש': 'sh', 'ת': 't'
                };
                
                for (let char in hebrewToEnglish) {
                    slug = slug.replace(new RegExp(char, 'g'), hebrewToEnglish[char]);
                }
                
                slugInput.value = slug;
            }
        });
        
        // Mark slug as user-modified once user edits it manually
        document.getElementById('slug').addEventListener('input', function() {
            this.dataset.userModified = "true";
        });
    </script>
</body>
</html>