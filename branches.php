<?php
// התחלת או המשך סשן
session_start();

// טעינת קבצי התצורה
require_once "config/database.php";
require_once "includes/functions.php";

// הגדרת קבוע שמאפשר גישה לקבצי ה-includes
define('ACCESS_ALLOWED', true);

// בדיקה האם המשתמש מחובר
if (!isLoggedIn()) {
    redirect("login.php");
}

// כותרת העמוד
$page_title = "ניהול סניפים";

// יצירת חיבור למסד הנתונים
$database = new Database();
$db = $database->getConnection();

// קבלת ה-tenant_id מהסשן
$tenant_id = $_SESSION['tenant_id'];

// בדיקת מגבלת מסלול
$query = "SELECT s.max_branches 
          FROM subscriptions s 
          INNER JOIN tenant_subscriptions ts ON s.id = ts.subscription_id 
          WHERE ts.tenant_id = :tenant_id1 
          AND ts.subscription_id = (
              SELECT subscription_id 
              FROM tenant_subscriptions 
              WHERE tenant_id = :tenant_id2 
              ORDER BY id DESC 
              LIMIT 1
          )";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id1", $tenant_id);
$stmt->bindParam(":tenant_id2", $tenant_id);
$stmt->execute();
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);
$max_branches = $subscription['max_branches'] ?? 1;

// שליפת רשימת הסניפים
$query = "SELECT * FROM branches WHERE tenant_id = :tenant_id ORDER BY is_main DESC, name";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
$current_branches_count = count($branches);

include_once "includes/header.php";
include_once "includes/sidebar.php";
?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto pb-10">
    <!-- Top Navigation Bar -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-800">ניהול סניפים</h1>
            
            <?php if ($current_branches_count < $max_branches): ?>
            <div class="flex items-center space-x-4 space-x-reverse">
                <a href="#" class="relative inline-flex items-center" id="addBranchBtn">
                    <span class="bg-primary hover:bg-primary-dark text-white text-sm px-4 py-2 rounded-lg transition duration-300 inline-flex items-center">
                        <i class="fas fa-plus ml-2"></i>
                        <span>הוסף סניף</span>
                    </span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Branches Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Subscription Info -->
        <div class="bg-white shadow-sm rounded-2xl p-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold mb-2">מגבלת סניפים במסלול</h2>
                    <p class="text-gray-600">
                        סניפים פעילים: <?php echo $current_branches_count; ?> מתוך <?php echo $max_branches; ?>
                    </p>
                </div>
                <?php if ($current_branches_count >= $max_branches): ?>
                <div class="text-sm text-gray-500">
                    <a href="subscription.php" class="text-primary hover:text-primary-dark">
                        <i class="fas fa-arrow-up ml-1"></i>
                        שדרג מסלול להגדלת מספר הסניפים
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Branches List -->
        <div class="bg-white shadow-sm rounded-2xl p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 bg-gray-50 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">שם הסניף</th>
                            <th class="px-6 py-3 bg-gray-50 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">כתובת</th>
                            <th class="px-6 py-3 bg-gray-50 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">טלפון</th>
                            <th class="px-6 py-3 bg-gray-50 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">סטטוס</th>
                            <th class="px-6 py-3 bg-gray-50 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">פעולות</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($branches as $branch): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($branch['name']); ?>
                                        <?php if ($branch['is_main']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-2">
                                                ראשי
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($branch['address'] ?? '-'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($branch['phone'] ?? '-'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($branch['is_active']): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        פעיל
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        לא פעיל
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if (!$branch['is_main']): ?>
                                <button class="text-primary hover:text-primary-dark ml-3" onclick="editBranch(<?php echo $branch['branch_id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="text-red-600 hover:text-red-800" onclick="deleteBranch(<?php echo $branch['branch_id']; ?>)">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Modal for adding/editing branch -->
<div id="branchModal" class="fixed inset-0 bg-gray-800 bg-opacity-50 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-lg max-w-2xl w-full p-6 max-h-90vh overflow-y-auto">
        <div class="flex justify-between items-center border-b pb-4 mb-4">
            <h3 class="text-xl font-bold text-gray-800" id="modalTitle">הוספת סניף חדש</h3>
            <button class="text-gray-400 hover:text-gray-600" onclick="closeBranchModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="branchForm" onsubmit="return saveBranch(event)">
            <input type="hidden" id="branchId" name="branchId" value="">
            
            <div class="mb-4">
                <label for="branchName" class="block text-sm font-medium text-gray-700 mb-1">שם הסניף *</label>
                <input type="text" id="branchName" name="branchName" required
                    class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            
            <div class="mb-4">
                <label for="branchAddress" class="block text-sm font-medium text-gray-700 mb-1">כתובת</label>
                <input type="text" id="branchAddress" name="branchAddress"
                    class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            
            <div class="mb-4">
                <label for="branchPhone" class="block text-sm font-medium text-gray-700 mb-1">טלפון</label>
                <input type="tel" id="branchPhone" name="branchPhone"
                    class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            
            <div class="mb-4">
                <label for="branchEmail" class="block text-sm font-medium text-gray-700 mb-1">דוא"ל</label>
                <input type="email" id="branchEmail" name="branchEmail"
                    class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            
            <div class="mb-4">
                <label for="branchDescription" class="block text-sm font-medium text-gray-700 mb-1">תיאור</label>
                <textarea id="branchDescription" name="branchDescription" rows="3"
                    class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
            </div>
            
            <div class="mb-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" id="branchIsActive" name="branchIsActive" class="form-checkbox h-5 w-5 text-primary" checked>
                    <span class="mr-2 text-sm text-gray-700">סניף פעיל</span>
                </label>
            </div>
            
            <div class="flex justify-end mt-6 gap-2">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white py-2 px-4 rounded-xl transition duration-300">
                    <i class="fas fa-save ml-2"></i>
                    שמור סניף
                </button>
                
                <button type="button" onclick="closeBranchModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 px-4 rounded-xl transition duration-300">
                    ביטול
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // הוספת מאזין לכפתור הוספת סניף
    document.getElementById('addBranchBtn')?.addEventListener('click', function() {
        openBranchModal();
    });
});

function openBranchModal(branchId = null) {
    const modal = document.getElementById('branchModal');
    const form = document.getElementById('branchForm');
    const title = document.getElementById('modalTitle');
    
    // איפוס הטופס
    form.reset();
    document.getElementById('branchId').value = '';
    
    if (branchId) {
        title.textContent = 'עריכת סניף';
        // טעינת פרטי הסניף
        fetch(`api/get_branch.php?id=${branchId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const branch = data.branch;
                    document.getElementById('branchId').value = branch.branch_id;
                    document.getElementById('branchName').value = branch.name;
                    document.getElementById('branchAddress').value = branch.address || '';
                    document.getElementById('branchPhone').value = branch.phone || '';
                    document.getElementById('branchEmail').value = branch.email || '';
                    document.getElementById('branchDescription').value = branch.description || '';
                    document.getElementById('branchIsActive').checked = branch.is_active == 1;
                }
            });
    } else {
        title.textContent = 'הוספת סניף חדש';
    }
    
    modal.classList.remove('hidden');
}

function closeBranchModal() {
    document.getElementById('branchModal').classList.add('hidden');
}

function saveBranch(event) {
    event.preventDefault();
    
    const formData = {
        branch_id: document.getElementById('branchId').value,
        name: document.getElementById('branchName').value,
        address: document.getElementById('branchAddress').value,
        phone: document.getElementById('branchPhone').value,
        email: document.getElementById('branchEmail').value,
        description: document.getElementById('branchDescription').value,
        is_active: document.getElementById('branchIsActive').checked ? 1 : 0
    };
    
    fetch('api/save_branch.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeBranchModal();
            window.location.reload();
        } else {
            alert('שגיאה בשמירת הסניף: ' + data.message);
        }
    })
    .catch(error => {
        alert('שגיאה בשליחת הבקשה: ' + error.message);
    });
    
    return false;
}

function editBranch(branchId) {
    openBranchModal(branchId);
}

function deleteBranch(branchId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק את הסניף?')) {
        return;
    }
    
    fetch('api/delete_branch.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ branch_id: branchId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('שגיאה במחיקת הסניף: ' + data.message);
        }
    })
    .catch(error => {
        alert('שגיאה בשליחת הבקשה: ' + error.message);
    });
}
</script>

<?php include_once "includes/footer.php"; ?> 