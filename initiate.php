<?php
/*
* ============================================
* فایل: initiate_simple.php
* وظیفه: ایجاد اولیه دیتابیس مدیریت (نسخه ساده)
* محل قرارگیری: در پوشه databases/
* ============================================
*/

// ============================================
// شروع: تابع main
// ============================================
function main() {
    $db_path = __DIR__ . '/DB_manager.sqlite';
    
    // بررسی وجود دیتابیس
    if (file_exists($db_path)) {
        die("❌ دیتابیس از قبل وجود دارد!\nمسیر: " . $db_path . "\n");
    }
    
    try {
        // ایجاد دیتابیس
        $pdo = new PDO("sqlite:" . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // ایجاد جدول manager_pass
        $pdo->exec("
            CREATE TABLE manager_pass (
                id INTEGER PRIMARY KEY,
                password TEXT NOT NULL
            )
        ");
        $pdo->exec("INSERT INTO manager_pass (password) VALUES ('mhd_msz_nasim_toos')");
        echo "✅ جدول manager_pass ایجاد شد.\n";
        
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
        echo "✅ جدول bosses_table ایجاد شد.\n";
        
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
        echo "✅ جدول new_requests ایجاد شد.\n";
        
        echo "\n============================================\n";
        echo "✅ دیتابیس مدیریت با موفقیت ایجاد شد!\n";
        echo "🔑 رمز مدیریت: mhd_msz_nasim_toos\n";
        echo "============================================\n";
        
    } catch (PDOException $e) {
        die("❌ خطا: " . $e->getMessage() . "\n");
    }
}

// اجرای برنامه
echo "\n🚀 راه‌اندازی اولیه سیستم...\n\n";
main();
echo "\n🏁 پایان عملیات\n\n";
?>