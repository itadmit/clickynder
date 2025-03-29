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
    
    // קבלת נתוני ההתחברות מהטופס
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // בדיקת תקינות אימייל
    if (!isValidEmail($email)) {
        $error_message = "כתובת האימייל אינה תקינה";
    } else {
        try {
            // חיפוש המשתמש במסד הנתונים
            $query = "SELECT tenant_id, business_name, email, password FROM tenants WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            // אם נמצא משתמש
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch();
                
                // בדיקת סיסמה
                if (verifyPassword($password, $row['password'])) {
                    // יצירת סשן
                    $_SESSION['tenant_id'] = $row['tenant_id'];
                    $_SESSION['business_name'] = $row['business_name'];
                    $_SESSION['email'] = $row['email'];
                    
                    // אם "זכור אותי" סומן - יצירת עוגייה
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = time() + (30 * 24 * 60 * 60); // 30 ימים
                        
                        // עדכון הטוקן במסד הנתונים
                        $updateQuery = "UPDATE tenants SET remember_token = :token, remember_expiry = :expiry WHERE tenant_id = :id";
                        $updateStmt = $db->prepare($updateQuery);
                        $updateStmt->bindParam(":token", $token);
                        $updateStmt->bindParam(":expiry", date('Y-m-d H:i:s', $expiry));
                        $updateStmt->bindParam(":id", $row['tenant_id']);
                        $updateStmt->execute();
                        
                        // יצירת עוגייה
                        setcookie('remember_token', $token, $expiry, '/', '', false, true);
                    }
                    
                    // הפניה ללוח הבקרה
                    redirect("dashboard.php");
                } else {
                    $error_message = "סיסמה שגויה";
                }
            } else {
                $error_message = "משתמש לא נמצא";
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
    <title>קליקינדר - התחברות למערכת</title>
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
        .login-gradient {
            background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);
        }
    </style>
</head>
<body class="bg-gray-100 h-screen">
    <div class="flex flex-wrap h-full">
        <!-- טופס התחברות -->
        <div class="w-full md:w-1/2 flex justify-center items-center p-6">
            <div class="bg-white p-8 rounded-2xl shadow-lg w-full max-w-md">
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">קליקינדר</h1>
                    <p class="text-gray-600">מערכת ניהול תורים חכמה</p>
                </div>

                <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">התחברות למערכת</h2>
                
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
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    <div class="mb-6">
                        <label for="email" class="block text-gray-700 font-medium mb-2">כתובת אימייל</label>
                        <div class="relative">
                            <input type="email" id="email" name="email" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary text-right"
                                   placeholder="your@email.com" dir="ltr" required>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-2">
                            <a href="forgot-password.php" class="text-sm text-primary hover:underline">שכחתי סיסמה</a>
                            <label for="password" class="block text-gray-700 font-medium">סיסמה</label>
                        </div>
                        <div class="relative">
                            <input type="password" id="password" name="password" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                   placeholder="********" required>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                <button type="button" id="togglePassword" class="text-gray-400 focus:outline-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-6 flex items-center">
                        <input type="checkbox" id="remember" name="remember" class="w-5 h-5 text-primary border-2 border-gray-300 rounded focus:ring-primary">
                        <label for="remember" class="mr-2 text-sm text-gray-700">זכור אותי</label>
                    </div>
                    
                    <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 px-4 rounded-xl transition duration-300 flex justify-center items-center">
                        <span>התחבר למערכת</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </form>
                
                <div class="mt-8 text-center">
                    <p class="text-gray-600">אין לך חשבון עדיין?</p>
                    <a href="register.php" class="text-primary font-medium hover:underline">הירשם עכשיו</a>
                </div>
            </div>
        </div>
        
        <!-- רקע גרדיאנט עם טקסט -->
        <div class="hidden md:flex md:w-1/2 login-gradient justify-center items-center p-12">
            <div class="text-white text-center">
                <h2 class="text-4xl font-bold mb-4">ניהול עסקי חכם</h2>
                <p class="text-xl mb-6">נהל את העסק שלך ביעילות עם מערכת התורים<br>המתקדמת שלנו</p>
                
                <!-- נקודות ניווט -->
                <div class="flex justify-center space-x-2 mt-12">
                    <span class="h-3 w-3 bg-white rounded-full"></span>
                    <span class="h-3 w-3 bg-white bg-opacity-50 rounded-full"></span>
                    <span class="h-3 w-3 bg-white bg-opacity-50 rounded-full"></span>
                </div>
                
                <div class="mt-20 text-sm opacity-70">
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
    </script>
</body>
</html>