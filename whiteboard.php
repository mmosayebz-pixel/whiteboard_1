<?php
/*
* ============================================
* فایل: whiteboard.php
* وظیفه: کنترلر اصلی تمام درخواست‌ها و عملیات دیتابیس
* ============================================
*/

// شروع سشن برای مدیریت لاگین
session_start();

// تنظیمات اولیه
date_default_timezone_set('Asia/Tehran');
header('Content-Type: text/html; charset=utf-8');

// ============================================
// تابع: create_database_connection
// وظیفه: ایجاد اتصال به دیتابیس SQLite
// ورودی: $db_path (مسیر فایل دیتابیس)
// خروجی: شی PDO یا false در صورت خطا
// ============================================
function create_database_connection($db_path) {
    try {
        $pdo = new PDO("sqlite:" . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("PRAGMA foreign_keys = ON");
        return $pdo;
    } catch (PDOException $e) {
        return false;
    }
}


// ============================================
// تابع: get_boss_username_by_system_name
// وظیفه: دریافت boss_username بر اساس system_name
// ورودی: $system_name
// خروجی: boss_username یا false
// ============================================
// ============================================
// تابع: get_boss_username_by_system_name
// وظیفه: دریافت boss_username بر اساس system_name
// ورودی: $system_name
// خروجی: boss_username یا false
// ============================================
function get_boss_username_by_system_name($system_name) {
    $db_path = __DIR__ . '/databases/DB_manager.sqlite';
    if (!file_exists($db_path)) {
        return false;
    }
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT boss_username FROM bosses_table WHERE system_name = ? AND status = 'active'");
        $stmt->execute([$system_name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['boss_username'];
        }
        return false;
    } catch (PDOException $e) {
        error_log("خطا در get_boss_username_by_system_name: " . $e->getMessage());
        return false;
    }
}




// ============================================
// تابع: initialize_db_manager
// وظیفه: ایجاد دیتابیس مدیریت در اولین اجرا
// ورودی: ندارد
// خروجی: true یا false
// ============================================
function initialize_db_manager() {
    $db_dir = __DIR__ . '/databases';
    if (!is_dir($db_dir)) {
        mkdir($db_dir, 0777, true);
    }
    
    $db_path = $db_dir . '/DB_manager.sqlite';
    if (file_exists($db_path)) {
        return true;
    }
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    // ایجاد جدول manager_pass
    $pdo->exec("
        CREATE TABLE manager_pass (
            id INTEGER PRIMARY KEY,
            password TEXT NOT NULL
        )
    ");
    $pdo->exec("INSERT INTO manager_pass (password) VALUES ('mhd_msz_nasim_toos')");
    
    // ایجاد جدول bosses_table
    $pdo->exec("
        CREATE TABLE bosses_table (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            system_name TEXT NOT NULL,
            boss_username TEXT NOT NULL UNIQUE,
            contact TEXT,
            status TEXT DEFAULT 'active'
        )
    ");
    
    // ایجاد جدول new_requests
    $pdo->exec("
        CREATE TABLE new_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            system_name TEXT NOT NULL,
            contact TEXT NOT NULL,
            request_date TEXT NOT NULL,
            status TEXT DEFAULT 'pending'
        )
    ");
    
    return true;
}

// ============================================
// تابع: generate_random_code
// وظیفه: تولید کد تصادفی با طول مشخص
// ورودی: $length (طول کد)
// خروجی: کد تصادفی
// ============================================
function generate_random_code($length) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $random_string;
}

// ============================================
// تابع: create_boss_database
// وظیفه: ایجاد دیتابیس برای رییس جدید
// ورودی: $system_name, $system_name
// خروجی: true یا false
// ============================================
// ============================================
// تابع: create_boss_database (نسخه اصلاح‌شده)
// وظیفه: ایجاد دیتابیس برای رییس جدید با نام system_name
// ورودی: $system_name, $boss_username
// خروجی: true یا false
// ============================================
function create_boss_database($system_name, $boss_username) {
    $db_dir = __DIR__ . '/databases';
    if (!is_dir($db_dir)) {
        mkdir($db_dir, 0777, true);
    }
    
    // نام فایل دیتابیس = system_name (نه boss_username)
    $db_path = $db_dir . '/' . $system_name . '.sqlite';
    
    // اگر دیتابیس با این نام وجود دارد، خطا بده
    if (file_exists($db_path)) {
        error_log("create_boss_database: دیتابیس با نام $system_name از قبل وجود دارد");
        return false;
    }
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    try {
        // ایجاد جدول whiteboards
        $pdo->exec("
            CREATE TABLE whiteboards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                created_date TEXT NOT NULL
            )
        ");
        
        // ایجاد جدول access_level با ساختار نرمال‌شده
        $pdo->exec("
            CREATE TABLE access_level (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                agent_name TEXT NOT NULL,
                password TEXT,
                whiteboard_name TEXT NOT NULL,
                level INTEGER DEFAULT 0
            )
        ");
        
        // ذخیره boss_username در یک جدول جداگانه برای امنیت
        $pdo->exec("
            CREATE TABLE system_info (
                id INTEGER PRIMARY KEY,
                system_name TEXT NOT NULL,
                boss_username TEXT NOT NULL
            )
        ");
        $stmt = $pdo->prepare("INSERT INTO system_info (system_name, boss_username) VALUES (?, ?)");
        $stmt->execute([$system_name, $boss_username]);
        
        return true;
    } catch (PDOException $e) {
        error_log("خطا در create_boss_database: " . $e->getMessage());
        return false;
    }
}

// ============================================
// تابع: add_whiteboard_to_boss
// وظیفه: اضافه کردن وایتبرد جدید به دیتابیس رییس
// ورودی: $system_name, $whiteboard_name
// خروجی: true یا false
// ============================================
function add_whiteboard_to_boss($system_name, $whiteboard_name) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    // بررسی وجود وایتبرد
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM whiteboards WHERE name = ?");
    $stmt->execute([$whiteboard_name]);
    if ($stmt->fetchColumn() > 0) return false;
    
    // اضافه کردن به لیست وایتبردها
    $stmt = $pdo->prepare("INSERT INTO whiteboards (name, created_date) VALUES (?, ?)");
    $stmt->execute([$whiteboard_name, date('Y-m-d H:i:s')]);
    
    // ایجاد جدول برای وایتبرد
    $table_name = 'wb_' . $whiteboard_name;
    $pdo->exec("
        CREATE TABLE $table_name (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL,
            agent TEXT NOT NULL,
            message TEXT NOT NULL
        )
    ");
    
    // اضافه کردن ستون به access_level برای همه کاربران موجود
    // ابتدا لیست کاربران را می‌گیریم
    $users = $pdo->query("SELECT DISTINCT agent_name FROM access_level")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($users as $user) {
        $stmt = $pdo->prepare("INSERT INTO access_level (agent_name, whiteboard_name, level) VALUES (?, ?, 0)");
        $stmt->execute([$user, $whiteboard_name]);
    }
    
    return true;
}

// ============================================
// تابع: add_user_to_boss
// وظیفه: اضافه کردن کاربر جدید به دیتابیس رییس
// ورودی: $system_name, $agent_name, $password
// خروجی: true یا false
// ============================================
function add_user_to_boss($system_name, $agent_name, $password) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    // بررسی وجود کاربر
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM access_level WHERE agent_name = ?");
    $stmt->execute([$agent_name]);
    if ($stmt->fetchColumn() > 0) return false;
    
    // لیست وایتبردهای موجود
    $whiteboards = $pdo->query("SELECT name FROM whiteboards")->fetchAll(PDO::FETCH_COLUMN);
    
    // اضافه کردن کاربر به access_level برای همه وایتبردها
    foreach ($whiteboards as $wb) {
        $stmt = $pdo->prepare("INSERT INTO access_level (agent_name, password, whiteboard_name, level) VALUES (?, ?, ?, 0)");
        $stmt->execute([$agent_name, $password, $wb]);
    }
    
    // ایجاد جدول log برای کاربر
    $log_table = 'log_' . $agent_name;
    $pdo->exec("
        CREATE TABLE $log_table (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL,
            whiteboard_name TEXT NOT NULL,
            message TEXT NOT NULL
        )
    ");
    
    return true;
}

// ============================================
// تابع: add_log_entry
// وظیفه: اضافه کردن ورودی به log کاربر
// ورودی: $system_name, $agent_name, $whiteboard_name, $message
// خروجی: true یا false
// ============================================
function add_log_entry($system_name, $agent_name, $whiteboard_name, $message) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    $log_table = 'log_' . $agent_name;
    $stmt = $pdo->prepare("INSERT INTO $log_table (date, whiteboard_name, message) VALUES (?, ?, ?)");
    $stmt->execute([date('Y-m-d H:i:s'), $whiteboard_name, $message]);
    
    return true;
}

// ============================================
// تابع: get_user_access_level
// وظیفه: دریافت سطح دسترسی کاربر برای یک وایتبرد
// ورودی: $system_name, $agent_name, $whiteboard_name
// خروجی: عدد سطح دسترسی (0,1,2,3) یا false
// ============================================
function get_user_access_level($system_name, $agent_name, $whiteboard_name) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("SELECT level FROM access_level WHERE agent_name = ? AND whiteboard_name = ?");
    $stmt->execute([$agent_name, $whiteboard_name]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['level'] : 0;
}

// ============================================
// تابع: verify_boss_login
// وظیفه: بررسی ورود رییس
// ورودی: $system_name, $password
// خروجی: array با اطلاعات رییس یا false
// ============================================
// ============================================
// تابع: verify_boss_login
// وظیفه: بررسی ورود رییس
// ورودی: $system_name, $password
// خروجی: array با اطلاعات رییس یا false
// ============================================
function verify_boss_login($system_name, $password) {
    $db_path = __DIR__ . '/databases/DB_manager.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("SELECT * FROM bosses_table WHERE system_name = ? AND boss_username = ? AND status = 'active'");
    $stmt->execute([$system_name, $password]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result : false;
}

// ============================================
// تابع: verify_agent_login
// وظیفه: بررسی ورود کاربر
// ورودی: $system_name, $agent_name, $password
// خروجی: true یا false
// ============================================
function verify_agent_login($system_name, $agent_name, $password) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM access_level WHERE agent_name = ? AND password = ?");
    $stmt->execute([$agent_name, $password]);
    return $stmt->fetchColumn() > 0;
}

// ============================================
// تابع: get_boss_whiteboards
// وظیفه: دریافت لیست وایتبردهای یک رییس
// ورودی: $system_name
// خروجی: array لیست وایتبردها
// ============================================
// ============================================
// تابع: get_boss_whiteboards (نسخه اصلاح‌شده)
// وظیفه: دریافت لیست وایتبردهای یک رییس
// ورودی: $system_name
// خروجی: array لیست وایتبردها
// ============================================
function get_boss_whiteboards($system_name) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return [];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return [];
    
    return $pdo->query("SELECT name FROM whiteboards ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
}

// ============================================
// تابع: get_whiteboard_messages
// وظیفه: دریافت پیام‌های یک وایتبرد
// ورودی: $system_name, $whiteboard_name
// خروجی: array پیام‌ها
// ============================================
// ============================================
// تابع: get_whiteboard_messages (نسخه اصلاح‌شده)
// وظیفه: دریافت پیام‌های یک وایتبرد
// ورودی: $system_name, $whiteboard_name
// خروجی: array پیام‌ها
// ============================================
function get_whiteboard_messages($system_name, $whiteboard_name) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return [];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return [];
    
    $table_name = 'wb_' . $whiteboard_name;
    try {
        return $pdo->query("SELECT * FROM $table_name ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// ============================================
// تابع: add_message_to_whiteboard
// وظیفه: اضافه کردن پیام به وایتبرد
// ورودی: $system_name, $whiteboard_name, $agent, $message
// خروجی: true یا false
// ============================================
function add_message_to_whiteboard($system_name, $whiteboard_name, $agent, $message) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    $table_name = 'wb_' . $whiteboard_name;
    $stmt = $pdo->prepare("INSERT INTO $table_name (date, agent, message) VALUES (?, ?, ?)");
    $stmt->execute([date('Y-m-d H:i:s'), $agent, $message]);
    
    // اضافه کردن به log کاربر
    add_log_entry($system_name, $agent, $whiteboard_name, $message);
    
    return true;
}

// ============================================
// تابع: delete_message_from_whiteboard
// وظیفه: حذف پیام از وایتبرد
// ورودی: $system_name, $whiteboard_name, $message_id
// خروجی: true یا false
// ============================================
function delete_message_from_whiteboard($system_name, $whiteboard_name, $message_id) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    $table_name = 'wb_' . $whiteboard_name;
    $stmt = $pdo->prepare("DELETE FROM $table_name WHERE id = ?");
    $stmt->execute([$message_id]);
    
    return true;
}

// ============================================
// تابع: get_boss_users
// وظیفه: دریافت لیست کاربران یک رییس
// ورودی: $system_name
// خروجی: array لیست کاربران
// ============================================
function get_boss_users($system_name) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return [];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return [];
    
    return $pdo->query("SELECT DISTINCT agent_name, password FROM access_level ORDER BY agent_name")->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================
// تابع: get_user_log
// وظیفه: دریافت log یک کاربر
// ورودی: $system_name, $agent_name
// خروجی: array ورودی‌های log
// ============================================
function get_user_log($system_name, $agent_name) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return [];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return [];
    
    $log_table = 'log_' . $agent_name;
    try {
        return $pdo->query("SELECT * FROM $log_table ORDER BY date DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// ============================================
// تابع: update_access_level
// وظیفه: به‌روزرسانی سطح دسترسی کاربر
// ورودی: $system_name, $agent_name, $whiteboard_name, $level
// خروجی: true یا false
// ============================================
function update_access_level($system_name, $agent_name, $whiteboard_name, $level) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("UPDATE access_level SET level = ? WHERE agent_name = ? AND whiteboard_name = ?");
    $stmt->execute([$level, $agent_name, $whiteboard_name]);
    
    return true;
}

// ============================================
// تابع: delete_user
// وظیفه: حذف کاربر از سیستم
// ورودی: $system_name, $agent_name
// خروجی: true یا false
// ============================================
function delete_user($system_name, $agent_name) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    // حذف از access_level
    $stmt = $pdo->prepare("DELETE FROM access_level WHERE agent_name = ?");
    $stmt->execute([$agent_name]);
    
    // حذف جدول log
    $log_table = 'log_' . $agent_name;
    try {
        $pdo->exec("DROP TABLE $log_table");
    } catch (PDOException $e) {
        // جدول وجود ندارد
    }
    
    return true;
}

// ============================================
// تابع: delete_whiteboard
// وظیفه: حذف وایتبرد
// ورودی: $system_name, $whiteboard_name
// خروجی: true یا false
// ============================================
function delete_whiteboard($system_name, $whiteboard_name) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    // حذف از لیست وایتبردها
    $stmt = $pdo->prepare("DELETE FROM whiteboards WHERE name = ?");
    $stmt->execute([$whiteboard_name]);
    
    // حذف جدول وایتبرد
    $table_name = 'wb_' . $whiteboard_name;
    try {
        $pdo->exec("DROP TABLE $table_name");
    } catch (PDOException $e) {
        // جدول وجود ندارد
    }
    
    // حذف از access_level
    $stmt = $pdo->prepare("DELETE FROM access_level WHERE whiteboard_name = ?");
    $stmt->execute([$whiteboard_name]);
    
    return true;
}

// ============================================
// تابع: clear_whiteboard
// وظیفه: پاک کردن تمام پیام‌های یک وایتبرد
// ورودی: $system_name, $whiteboard_name
// خروجی: true یا false
// ============================================
function clear_whiteboard($system_name, $whiteboard_name) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    $table_name = 'wb_' . $whiteboard_name;
    $stmt = $pdo->prepare("DELETE FROM $table_name");
    $stmt->execute();
    
    return true;
}

// ============================================
// تابع: rename_whiteboard
// وظیفه: تغییر نام وایتبرد
// ورودی: $system_name, $old_name, $new_name
// خروجی: true یا false
// ============================================
function rename_whiteboard($system_name, $old_name, $new_name) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    // بررسی وجود نام جدید
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM whiteboards WHERE name = ?");
    $stmt->execute([$new_name]);
    if ($stmt->fetchColumn() > 0) return false;
    
    // تغییر نام در لیست وایتبردها
    $stmt = $pdo->prepare("UPDATE whiteboards SET name = ? WHERE name = ?");
    $stmt->execute([$new_name, $old_name]);
    
    // تغییر نام جدول
    $old_table = 'wb_' . $old_name;
    $new_table = 'wb_' . $new_name;
    try {
        $pdo->exec("ALTER TABLE $old_table RENAME TO $new_table");
    } catch (PDOException $e) {
        return false;
    }
    
    // تغییر نام در access_level
    $stmt = $pdo->prepare("UPDATE access_level SET whiteboard_name = ? WHERE whiteboard_name = ?");
    $stmt->execute([$new_name, $old_name]);
    
    // تغییر نام در log کاربران
    $users = $pdo->query("SELECT DISTINCT agent_name FROM access_level")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($users as $user) {
        $log_table = 'log_' . $user;
        try {
            $stmt = $pdo->prepare("UPDATE $log_table SET whiteboard_name = ? WHERE whiteboard_name = ?");
            $stmt->execute([$new_name, $old_name]);
        } catch (PDOException $e) {
            // ادامه
        }
    }
    
    return true;
}

// ============================================
// تابع: get_pending_requests
// وظیفه: دریافت درخواست‌های جدید
// ورودی: ندارد
// خروجی: array درخواست‌ها
// ============================================
function get_pending_requests() {
    $db_path = __DIR__ . '/databases/DB_manager.sqlite';
    if (!file_exists($db_path)) return [];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return [];
    
    return $pdo->query("SELECT * FROM new_requests WHERE status = 'pending' ORDER BY request_date")->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================
// تابع: approve_request
// وظیفه: تایید درخواست جدید
// ورودی: $request_id
// خروجی: array با اطلاعات سیستم جدید یا false
// ============================================
// ============================================
// تابع: approve_request (نسخه اصلاح‌شده)
// وظیفه: تایید درخواست جدید و ایجاد دیتابیس با نام سیستم
// ============================================
function approve_request($request_id) {
    $db_path = __DIR__ . '/databases/DB_manager.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    // دریافت اطلاعات درخواست
    $stmt = $pdo->prepare("SELECT * FROM new_requests WHERE id = ? AND status = 'pending'");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) return false;
    
    // تولید رمز تصادفی 16 کاراکتری برای رییس
    $boss_username = generate_random_code(16);
    $system_name = $request['system_name'];
    
    // اضافه کردن به bosses_table
    $stmt = $pdo->prepare("INSERT INTO bosses_table (system_name, boss_username, contact, status) VALUES (?, ?, ?, 'active')");
    $stmt->execute([$system_name, $boss_username, $request['contact']]);
    
    // به‌روزرسانی وضعیت درخواست
    $stmt = $pdo->prepare("UPDATE new_requests SET status = 'approved' WHERE id = ?");
    $stmt->execute([$request_id]);
    
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
    // ایجاد دیتابیس با نام system_name (نه boss_username)
    $result = create_boss_database($system_name, $boss_username);
    
    if ($result) {
        return [
            'system_name' => $system_name,
            'boss_username' => $boss_username,
            'contact' => $request['contact']
        ];
    }
    return false;
}

// ============================================
// تابع: reject_request
// وظیفه: رد درخواست جدید
// ورودی: $request_id
// خروجی: true یا false
// ============================================
function reject_request($request_id) {
    $db_path = __DIR__ . '/databases/DB_manager.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("UPDATE new_requests SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$request_id]);
    
    return true;
}

// ============================================
// تابع: get_all_bosses
// وظیفه: دریافت لیست تمام سیستم‌ها
// ورودی: ندارد
// خروجی: array لیست سیستم‌ها
// ============================================
function get_all_bosses() {
    $db_path = __DIR__ . '/databases/DB_manager.sqlite';
    if (!file_exists($db_path)) return [];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return [];
    
    return $pdo->query("SELECT * FROM bosses_table ORDER BY system_name")->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================
// تابع: update_boss_username
// وظیفه: به‌روزرسانی رمز رییس
// ورودی: $boss_id, $new_username
// خروجی: true یا false
// ============================================
function update_boss_username($boss_id, $new_username) {
    $db_path = __DIR__ . '/databases/DB_manager.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("UPDATE bosses_table SET boss_username = ? WHERE id = ?");
    $stmt->execute([$new_username, $boss_id]);
    
    return true;
}

// ============================================
// تابع: verify_manager_password
// وظیفه: بررسی رمز مدیریت
// ورودی: $password
// خروجی: true یا false
// ============================================
function verify_manager_password($password) {
    $db_path = __DIR__ . '/databases/DB_manager.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("SELECT password FROM manager_pass WHERE id = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result && $result['password'] === $password;
}

// ============================================
// تابع: get_boss_databases
// وظیفه: دریافت لیست دیتابیس‌های موجود
// ورودی: ندارد
// خروجی: array لیست دیتابیس‌ها
// ============================================
// ============================================
// تابع: get_boss_databases (نسخه اصلاح‌شده)
// وظیفه: دریافت لیست دیتابیس‌های موجود (بر اساس system_name)
// ورودی: ندارد
// خروجی: array لیست دیتابیس‌ها
// ============================================
function get_boss_databases() {
    $db_dir = __DIR__ . '/databases';
    if (!is_dir($db_dir)) return [];
    
    $databases = [];
    $files = scandir($db_dir);
    foreach ($files as $file) {
        // فقط دیتابیس‌هایی که با system_name مطابقت دارند
        if ($file !== 'DB_manager.sqlite' && pathinfo($file, PATHINFO_EXTENSION) === 'sqlite') {
            $name = pathinfo($file, PATHINFO_FILENAME);
            // بررسی اینکه این نام در bosses_table وجود دارد
            $db_manager_path = __DIR__ . '/databases/DB_manager.sqlite';
            if (file_exists($db_manager_path)) {
                try {
                    $pdo = create_database_connection($db_manager_path);
                    if ($pdo) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bosses_table WHERE system_name = ?");
                        $stmt->execute([$name]);
                        if ($stmt->fetchColumn() > 0) {
                            $databases[] = $name;
                        }
                    }
                } catch (PDOException $e) {
                    // ادامه
                }
            }
        }
    }
    return $databases;
}

// ============================================
// تابع: get_whiteboard_access_matrix
// وظیفه: دریافت ماتریس دسترسی برای رییس
// ورودی: $system_name
// خروجی: array ماتریس دسترسی
// ============================================
// ============================================
// تابع: get_whiteboard_access_matrix (نسخه اصلاح‌شده)
// وظیفه: دریافت ماتریس دسترسی برای رییس
// ورودی: $system_name
// خروجی: array ماتریس دسترسی
// ============================================
function get_whiteboard_access_matrix($system_name) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return [];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return [];
    
    try {
        // دریافت لیست وایتبردها
        $whiteboards = $pdo->query("SELECT name FROM whiteboards ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        
        // دریافت لیست کاربران
        $users = $pdo->query("SELECT DISTINCT agent_name FROM access_level ORDER BY agent_name")->fetchAll(PDO::FETCH_COLUMN);
        
        $matrix = [];
        foreach ($users as $user) {
            $row = ['agent' => $user];
            // دریافت رمز کاربر
            $stmt = $pdo->prepare("SELECT password FROM access_level WHERE agent_name = ? LIMIT 1");
            $stmt->execute([$user]);
            $pass = $stmt->fetch(PDO::FETCH_ASSOC);
            $row['password'] = $pass ? $pass['password'] : '';
            
            foreach ($whiteboards as $wb) {
                if (!empty($wb)) {
                    $stmt = $pdo->prepare("SELECT level FROM access_level WHERE agent_name = ? AND whiteboard_name = ?");
                    $stmt->execute([$user, $wb]);
                    $level = $stmt->fetch(PDO::FETCH_ASSOC);
                    $row[$wb] = $level ? $level['level'] : 0;
                }
            }
            $matrix[] = $row;
        }
        
        return [
            'whiteboards' => $whiteboards,
            'users' => $matrix
        ];
    } catch (PDOException $e) {
        error_log("خطا در get_whiteboard_access_matrix: " . $e->getMessage());
        return [];
    }
}

// ============================================
// تابع: get_whiteboard_messages_by_date
// وظیفه: دریافت پیام‌های بین دو تاریخ
// ورودی: $system_name, $start_date, $end_date
// خروجی: array پیام‌ها
// ============================================
function get_whiteboard_messages_by_date($system_name, $start_date, $end_date) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return [];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return [];
    
    $whiteboards = $pdo->query("SELECT name FROM whiteboards")->fetchAll(PDO::FETCH_COLUMN);
    $all_messages = [];
    
    foreach ($whiteboards as $wb) {
        $table_name = 'wb_' . $wb;
        try {
            $stmt = $pdo->prepare("SELECT date, agent, message FROM $table_name WHERE date BETWEEN ? AND ? ORDER BY date");
            $stmt->execute([$start_date, $end_date]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($messages as $msg) {
                $msg['whiteboard'] = $wb;
                $all_messages[] = $msg;
            }
        } catch (PDOException $e) {
            // ادامه
        }
    }
    
    return $all_messages;
}

// ============================================
// تابع: add_agent_request
// وظیفه: ثبت درخواست اتصال کاربر
// ورودی: $system_name, $agent_name
// خروجی: true یا false
// ============================================
function add_agent_request($system_name, $agent_name) {
    // این تابع برای مدیریت درخواست‌های اتصال کاربران است
    // در این نسخه ساده، مستقیماً کاربر را اضافه می‌کنیم
    $password = generate_random_code(8);
    return add_user_to_boss($system_name, $agent_name, $password);
}

// ============================================
// تابع: handle_agent_request
// وظیفه: پردازش درخواست اتصال کاربر (قبول/رد)
// ورودی: $system_name, $agent_name, $action
// خروجی: true یا false
// ============================================
function handle_agent_request($system_name, $agent_name, $action) {
    if ($action === 'approve') {
        $password = generate_random_code(8);
        return add_user_to_boss($system_name, $agent_name, $password);
    }
    return true; // رد کردن
}

// ============================================
// تابع: get_system_name_by_boss_username
// وظیفه: دریافت system_name بر اساس boss_username
// ورودی: $boss_username
// خروجی: system_name یا false
// ============================================
function get_system_name_by_boss_username($boss_username) {
    $db_path = __DIR__ . '/databases/DB_manager.sqlite';
    if (!file_exists($db_path)) {
        return false;
    }
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT system_name FROM bosses_table WHERE boss_username = ? AND status = 'active'");
        $stmt->execute([$boss_username]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['system_name'];
        }
        return false;
    } catch (PDOException $e) {
        error_log("خطا در get_system_name_by_boss_username: " . $e->getMessage());
        return false;
    }
}

// ============================================
// شروع پردازش درخواست‌ها
// ============================================
initialize_db_manager();

// پردازش اکشن‌های مختلف
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// اگر درخواست AJAX باشد
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'verify_manager':
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $result = verify_manager_password($password);
            echo json_encode(['success' => $result]);
            break;
            
        case 'get_requests':
            $requests = get_pending_requests();
            echo json_encode(['success' => true, 'data' => $requests]);
            break;
            
        case 'approve_request':
            $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
            $result = approve_request($request_id);
            echo json_encode(['success' => $result !== false, 'data' => $result]);
            break;
            
        case 'reject_request':
            $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
            $result = reject_request($request_id);
            echo json_encode(['success' => $result]);
            break;
			
			
case 'get_whiteboards':
    // اولویت 1: system_name از POST
    $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
    
    // اولویت 2: system_name از SESSION
    if (empty($system_name)) {
        $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
    }
    
    // اولویت 3: تبدیل boss_username به system_name
    if (empty($system_name)) {
        $boss_username = isset($_POST['boss_username']) ? $_POST['boss_username'] : '';
        if (!empty($boss_username)) {
            $system_name = get_system_name_by_boss_username($boss_username);
        }
    }
    
    if (empty($system_name)) {
        echo json_encode(['success' => false, 'error' => 'نام سیستم مشخص نیست']);
        break;
    }
    
    $whiteboards = get_boss_whiteboards($system_name);
    echo json_encode(['success' => true, 'data' => $whiteboards]);
    break;			
			
			
case 'add_request':
    $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
    $contact = isset($_POST['contact']) ? $_POST['contact'] : '';
    
    if (!$system_name || !$contact) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات کامل نیست']);
        break;
    }
    
    $db_path = __DIR__ . '/databases/DB_manager.sqlite';
    $pdo = create_database_connection($db_path);
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'خطا در اتصال به دیتابیس']);
        break;
    }
    
    $stmt = $pdo->prepare("INSERT INTO new_requests (system_name, contact, request_date, status) VALUES (?, ?, ?, 'pending')");
    $result = $stmt->execute([$system_name, $contact, date('Y-m-d H:i:s')]);
    
    echo json_encode(['success' => $result]);
    break;
	
	
            
        case 'get_bosses':
            $bosses = get_all_bosses();
            echo json_encode(['success' => true, 'data' => $bosses]);
            break;
            
        case 'update_boss':
            $boss_id = isset($_POST['boss_id']) ? intval($_POST['boss_id']) : 0;
            $new_username = isset($_POST['new_username']) ? $_POST['new_username'] : '';
            $result = update_boss_username($boss_id, $new_username);
            echo json_encode(['success' => $result]);
            break;
            
        case 'verify_boss':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $result = verify_boss_login($system_name, $password);
            if ($result) {
                $_SESSION['boss_username'] = $result['boss_username'];
                $_SESSION['system_name'] = $result['system_name'];
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                echo json_encode(['success' => false]);
            }
            break;
            
// ============================================
// اکشن: verify_agent
// وظیفه: بررسی ورود کاربر با نام سیستم (system_name)
// ============================================
case 'verify_agent':
    $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';  // تغییر نام متغیر
    $agent_name = isset($_POST['agent_name']) ? $_POST['agent_name'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    error_log("verify_agent: system_name=$system_name, agent=$agent_name, pass=$password");
    
    if (empty($system_name) || empty($agent_name) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات کامل نیست']);
        break;
    }
    
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
    // ابتدا boss_username را از روی system_name پیدا کن
    //$boss_username = get_boss_username_by_system_name($system_name);
    
    if (!$system_name) {
        echo json_encode(['success' => false, 'error' => 'سیستم یافت نشد']);
        break;
    }
    
    $result = verify_agent_login($system_name, $agent_name, $password);
    
    if ($result) {
        $_SESSION['boss_username'] = $boss_username;
        $_SESSION['system_name'] = $system_name;
        $_SESSION['agent_name'] = $agent_name;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'نام کاربری یا رمز اشتباه است']);
    }
    break;
            
case 'get_whiteboards':
    $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : (isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '');
    
    if (empty($system_name)) {
        echo json_encode(['success' => false, 'error' => 'نام سیستم وارد نشده']);
        break;
    }
    
    $whiteboards = get_boss_whiteboards($system_name);
    echo json_encode(['success' => true, 'data' => $whiteboards]);
    break;
            
case 'get_messages':
    $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : (isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '');
    $whiteboard_name = isset($_POST['whiteboard_name']) ? $_POST['whiteboard_name'] : '';
    
    if (empty($system_name) || empty($whiteboard_name)) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات کامل نیست']);
        break;
    }
    
    $messages = get_whiteboard_messages($system_name, $whiteboard_name);
    echo json_encode(['success' => true, 'data' => $messages]);
    break;
            
        case 'add_message':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : (isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '');
            $whiteboard_name = isset($_POST['whiteboard_name']) ? $_POST['whiteboard_name'] : '';
            $agent = isset($_POST['agent']) ? $_POST['agent'] : (isset($_SESSION['agent_name']) ? $_SESSION['agent_name'] : '');
            $message = isset($_POST['message']) ? $_POST['message'] : '';
            
            // بررسی سطح دسترسی
            $level = get_user_access_level($system_name, $agent, $whiteboard_name);
            if ($level < 2) {
                echo json_encode(['success' => false, 'error' => 'دسترسی کافی ندارید']);
                break;
            }
            
            $result = add_message_to_whiteboard($system_name, $whiteboard_name, $agent, $message);
            echo json_encode(['success' => $result]);
            break;
            
        case 'delete_message':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : (isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '');
            $whiteboard_name = isset($_POST['whiteboard_name']) ? $_POST['whiteboard_name'] : '';
            $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
            $agent = isset($_POST['agent']) ? $_POST['agent'] : (isset($_SESSION['agent_name']) ? $_SESSION['agent_name'] : '');
            
            // بررسی سطح دسترسی
            $level = get_user_access_level($system_name, $agent, $whiteboard_name);
            if ($level < 3) {
                echo json_encode(['success' => false, 'error' => 'دسترسی کافی ندارید']);
                break;
            }
            
            $result = delete_message_from_whiteboard($system_name, $whiteboard_name, $message_id);
            echo json_encode(['success' => $result]);
            break;
            
        case 'create_whiteboard':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : (isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '');
            $whiteboard_name = isset($_POST['whiteboard_name']) ? $_POST['whiteboard_name'] : '';
            $agent = isset($_POST['agent']) ? $_POST['agent'] : (isset($_SESSION['agent_name']) ? $_SESSION['agent_name'] : '');
            
            $result = add_whiteboard_to_boss($system_name, $whiteboard_name);
            if ($result) {
                // تنظیم سطح دسترسی برای سازنده به 2
                update_access_level($system_name, $agent, $whiteboard_name, 2);
                // ثبت در log
                add_log_entry($system_name, $agent, $whiteboard_name, "وایتبرد جدید با نام $whiteboard_name");
            }
            echo json_encode(['success' => $result]);
            break;
            
// ============================================
// اکشن: get_access_matrix
// وظيفه: دريافت ماتريس دسترسي براي رييس
// ============================================
case 'get_access_matrix':
    $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
    
    if (empty($system_name)) {
        echo json_encode(['success' => false, 'error' => 'نام رييس وارد نشده']);
        break;
    }
    
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) {
        echo json_encode(['success' => false, 'error' => 'ديتابيس رييس وجود ندارد']);
        break;
    }
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'خطا در اتصال به ديتابيس']);
        break;
    }
    
    try {
        // دريافت ليست وايتبردها
        $whiteboards = $pdo->query("SELECT name FROM whiteboards ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        
        // دريافت ليست کاربران
        $users = $pdo->query("SELECT DISTINCT agent_name FROM access_level ORDER BY agent_name")->fetchAll(PDO::FETCH_COLUMN);
        
        $matrix = [];
        foreach ($users as $user) {
            $row = ['agent' => $user];
            // دريافت رمز کاربر
            $stmt = $pdo->prepare("SELECT password FROM access_level WHERE agent_name = ? LIMIT 1");
            $stmt->execute([$user]);
            $pass = $stmt->fetch(PDO::FETCH_ASSOC);
            $row['password'] = $pass ? $pass['password'] : '';
            
            foreach ($whiteboards as $wb) {
                // اگر نام وايتبرد خالي نباشد
                if (!empty($wb)) {
                    $stmt = $pdo->prepare("SELECT level FROM access_level WHERE agent_name = ? AND whiteboard_name = ?");
                    $stmt->execute([$user, $wb]);
                    $level = $stmt->fetch(PDO::FETCH_ASSOC);
                    $row[$wb] = $level ? $level['level'] : 0;
                }
            }
            $matrix[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'whiteboards' => $whiteboards,
                'users' => $matrix
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;
            
        case 'update_access':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : (isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '');
            $agent_name = isset($_POST['agent_name']) ? $_POST['agent_name'] : '';
            $whiteboard_name = isset($_POST['whiteboard_name']) ? $_POST['whiteboard_name'] : '';
            $level = isset($_POST['level']) ? intval($_POST['level']) : 0;
            
            $result = update_access_level($system_name, $agent_name, $whiteboard_name, $level);
            echo json_encode(['success' => $result]);
            break;
            
        case 'delete_user':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : (isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '');
            $agent_name = isset($_POST['agent_name']) ? $_POST['agent_name'] : '';
            
            $result = delete_user($system_name, $agent_name);
            echo json_encode(['success' => $result]);
            break;
            
        case 'delete_whiteboard':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : (isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '');
            $whiteboard_name = isset($_POST['whiteboard_name']) ? $_POST['whiteboard_name'] : '';
            
            $result = delete_whiteboard($system_name, $whiteboard_name);
            echo json_encode(['success' => $result]);
            break;
            
        case 'clear_whiteboard':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : (isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '');
            $whiteboard_name = isset($_POST['whiteboard_name']) ? $_POST['whiteboard_name'] : '';
            
            $result = clear_whiteboard($system_name, $whiteboard_name);
            echo json_encode(['success' => $result]);
            break;
            
        case 'rename_whiteboard':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : (isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '');
            $old_name = isset($_POST['old_name']) ? $_POST['old_name'] : '';
            $new_name = isset($_POST['new_name']) ? $_POST['new_name'] : '';
            
            $result = rename_whiteboard($system_name, $old_name, $new_name);
            echo json_encode(['success' => $result]);
            break;
            
        case 'get_user_log':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : (isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '');
            $agent_name = isset($_POST['agent_name']) ? $_POST['agent_name'] : '';
            
            $log = get_user_log($system_name, $agent_name);
            echo json_encode(['success' => true, 'data' => $log]);
            break;
            
        case 'get_messages_by_date':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : (isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '');
            $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
            $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
            
            $messages = get_whiteboard_messages_by_date($system_name, $start_date, $end_date);
            echo json_encode(['success' => true, 'data' => $messages]);
            break;
            
        case 'add_agent_request':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : (isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '');
            $agent_name = isset($_POST['agent_name']) ? $_POST['agent_name'] : '';
            
            $result = add_agent_request($system_name, $agent_name);
            echo json_encode(['success' => $result]);
            break;
            
        case 'get_boss_databases':
            $databases = get_boss_databases();
            echo json_encode(['success' => true, 'data' => $databases]);
            break;
            
        case 'logout':
            session_destroy();
            echo json_encode(['success' => true]);
            break;
			
// ============================================
// اکشن: get_whiteboards_for_agent
// وظیفه: دریافت لیست وایتبردهای مجاز برای یک کاربر خاص
// ورودی: system_name, agent_name
// خروجی: JSON با لیست وایتبردهای مجاز
// ============================================
// ============================================
// اکشن: get_whiteboards_for_agent (نسخه کامل)
// وظیفه: دریافت لیست وایتبردهای مجاز برای یک کاربر خاص
// ============================================
// ============================================
// اکشن: get_whiteboards_for_agent (نسخه اصلاح‌شده)
// وظیفه: دریافت لیست وایتبردهای مجاز برای یک کاربر خاص
// ============================================
case 'get_whiteboards_for_agent':
    $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
    $agent_name = isset($_POST['agent_name']) ? $_POST['agent_name'] : '';
    
    if (empty($system_name) || empty($agent_name)) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات کامل نیست']);
        break;
    }
    
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) {
        echo json_encode(['success' => false, 'error' => 'دیتابیس رییس وجود ندارد']);
        break;
    }
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'خطا در اتصال به دیتابیس']);
        break;
    }
    
    try {
        // دریافت وایتبردهایی که کاربر به آنها دسترسی دارد (level > 0)
        $stmt = $pdo->prepare("SELECT whiteboard_name, level FROM access_level WHERE agent_name = ? AND level > 0 AND whiteboard_name != ''");
        $stmt->execute([$agent_name]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $whiteboards = [];
        foreach ($results as $row) {
            $whiteboards[] = [
                'name' => $row['whiteboard_name'],
                'level' => $row['level']
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $whiteboards]);
    } catch (PDOException $e) {
        error_log("خطا در get_whiteboards_for_agent: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;
	
	
// ============================================
// اکشن: add_user_by_boss
// وظیفه: اضافه کردن کاربر جدید توسط رییس
// ورودی: system_name, agent_name, password (اختیاری)
// خروجی: JSON با وضعیت موفقیت
// ============================================
// ============================================
// اکشن: add_user_by_boss (نسخه اصلاح‌شده)
// ============================================
case 'add_user_by_boss':
    $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
    $agent_name = isset($_POST['agent_name']) ? $_POST['agent_name'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($password)) {
        $password = generate_random_code(8);
    }
    
    error_log("add_user_by_boss: system_name=$system_name, agent=$agent_name, pass=$password");
    
    if (empty($system_name) || empty($agent_name)) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات کامل نیست']);
        break;
    }
    
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) {
        echo json_encode(['success' => false, 'error' => 'دیتابیس رییس وجود ندارد']);
        break;
    }
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'خطا در اتصال به دیتابیس']);
        break;
    }
    
    try {
        // بررسی وجود کاربر
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM access_level WHERE agent_name = ?");
        $stmt->execute([$agent_name]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'این کاربر قبلاً وجود دارد']);
            break;
        }
        
        // دریافت لیست وایتبردهای موجود
        $whiteboards = $pdo->query("SELECT name FROM whiteboards")->fetchAll(PDO::FETCH_COLUMN);
        
        $pdo->beginTransaction();
        
        if (empty($whiteboards)) {
            $stmt = $pdo->prepare("INSERT INTO access_level (agent_name, password, whiteboard_name, level) VALUES (?, ?, '', 0)");
            $stmt->execute([$agent_name, $password]);
        } else {
            foreach ($whiteboards as $wb) {
                $stmt = $pdo->prepare("INSERT INTO access_level (agent_name, password, whiteboard_name, level) VALUES (?, ?, ?, 0)");
                $stmt->execute([$agent_name, $password, $wb]);
            }
        }
        
        // ایجاد جدول log برای کاربر
        $log_table = 'log_' . $agent_name;
        $pdo->exec("
            CREATE TABLE $log_table (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date TEXT NOT NULL,
                whiteboard_name TEXT NOT NULL,
                message TEXT NOT NULL
            )
        ");
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'password' => $password,
            'message' => 'کاربر با موفقیت اضافه شد. رمز: ' . $password
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("خطا در add_user_by_boss: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'خطای دیتابیس: ' . $e->getMessage()]);
    }
    break;
	
// ============================================
// اکشن: get_boss_username
// وظیفه: دریافت boss_username بر اساس system_name
// ============================================
case 'get_boss_username':
    $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
    
    if (empty($system_name)) {
        echo json_encode(['success' => false, 'error' => 'نام سیستم وارد نشده']);
        break;
    }
    
    $boss_username = get_boss_username_by_system_name($system_name);
    
    if ($system_name) {
        echo json_encode(['success' => true, 'data' => ['system_name' => $system_name]]);
    } else {
        echo json_encode(['success' => false, 'error' => 'سیستم یافت نشد']);
    }
    break;
	
            
        default:
            echo json_encode(['success' => false, 'error' => 'اکشن نامعتبر']);
            break;
    }
    exit;
}

// اگر فرم سنتی باشد یا صفحه اصلی
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم گزارش‌نویسی گروهی</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>سیستم گزارش‌نویسی گروهی تحت وب</h1>
        <div class="menu">
            <a href="manager.html" class="btn">مدیریت سایت</a>
            <a href="boss.html" class="btn">ورود رییس</a>
            <a href="agent.html" class="btn">ورود کاربر</a>
        </div>
    </div>
</body>
</html>