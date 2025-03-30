<?php
/**
 * Booking System - Header Template
 * 
 * מאוכסן ב: booking/templates/header.php
 * תבנית ראש העמוד, כוללת את ה-head וחלק עליון של הדף
 */

// וידוא שהקובץ לא נטען ישירות
if (!defined('ACCESS_ALLOWED') && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: ../../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="tenant-id" content="<?php echo $tenant_id; ?>">
    <meta name="default-staff-id" content="<?php echo isset($staff[0]) ? $staff[0]['staff_id'] : '1'; ?>">
    <?php if (!empty($tenant['address'])): ?>
    <meta name="business-address" content="<?php echo htmlspecialchars($tenant['address']); ?>">
    <?php endif; ?>
    
    <title><?php echo htmlspecialchars($tenant['business_name']); ?> - קביעת תור</title>
    
    <!-- גופנים -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Hebrew:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- סגנונות חיצוניים -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">
    
    <!-- סקריפטים חיצוניים -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/locales/he.js"></script>
    
    <!-- הגדרות Tailwind -->
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
    
    <!-- CSS מותאם אישית -->
    <link rel="stylesheet" href="assets/css/booking.css">
</head>
<body class="bg-gray-50">
    <!-- Hero Section - תמונת רקע ופרטי עסק -->
    <div class="hero-section flex flex-col justify-center items-center text-white text-center px-4 relative" 
         style="background-color: #000000d6; background-image: url('../<?php echo !empty($tenant['cover_image_path']) ? htmlspecialchars($tenant['cover_image_path']) : '../assets/img/default-cover.jpg'; ?>');">
        
        <?php if (!empty($tenant['logo_path'])): ?>
            <div class="mb-6">
                <img src="../<?php echo htmlspecialchars($tenant['logo_path']); ?>" alt="<?php echo htmlspecialchars($tenant['business_name']); ?>" class="w-24 h-24 rounded-full object-cover border-4 border-white">
            </div>
        <?php endif; ?>
        
        <h1 class="text-4xl md:text-5xl font-bold mb-2"><?php echo htmlspecialchars($tenant['business_name']); ?></h1>
        
        <?php if (!empty($tenant['business_description'])): ?>
            <p class="text-lg max-w-2xl mb-8"><?php echo htmlspecialchars($tenant['business_description']); ?></p>
        <?php endif; ?>
        
        <button id="bookNowBtn" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-8 rounded-xl transition duration-300">
            קבע תור עכשיו
        </button>
    </div>
    
    <!-- Main Content -->
    <div class="max-w-6xl mx-auto px-4 py-12">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">