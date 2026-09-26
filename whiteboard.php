<?php
/*
* ============================================
* فایل: whiteboard.php (نسخه نهایی با پیام‌رسان)
* ============================================
*/

// ============================================
// تنظیمات اولیه و Session
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

ini_set('session.gc_maxlifetime', 2592000);
ini_set('session.cookie_lifetime', 2592000);

session_start();
date_default_timezone_set('Asia/Tehran');

// ============================================
// توابع کمکی
// ============================================

function create_database_connection($db_path) {
    try {
        $pdo = new PDO("sqlite:" . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("PRAGMA foreign_keys = ON");
        return $pdo;
    } catch (PDOException $e) {
        error_log("خطا در create_database_connection: " . $e->getMessage());
        return false;
    }
}

function generate_random_code($length) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $random_string;
}

// ============================================
// توابع مربوط به سیستم (قبلی)
// ============================================

function get_system_name_by_boss_username($boss_username) {
    $db_path = __DIR__ . '/databases/DB_manager.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("SELECT system_name FROM bosses_table WHERE boss_username = ? AND status = 'active'");
        $stmt->execute([$boss_username]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['system_name'] : false;
    } catch (PDOException $e) {
        error_log("خطا در get_system_name_by_boss_username: " . $e->getMessage());
        return false;
    }
}

function get_boss_username_by_system_name($system_name) {
    $db_path = __DIR__ . '/databases/DB_manager.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("SELECT boss_username FROM bosses_table WHERE system_name = ? AND status = 'active'");
        $stmt->execute([$system_name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['boss_username'] : false;
    } catch (PDOException $e) {
        error_log("خطا در get_boss_username_by_system_name: " . $e->getMessage());
        return false;
    }
}

function verify_agent_login($system_name, $agent_name, $password) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) {
        error_log("verify_agent_login: دیتابیس وجود ندارد - " . $db_path);
        return false;
    }
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM access_level WHERE agent_name = ? AND password = ?");
        $stmt->execute([$agent_name, $password]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("خطا در verify_agent_login: " . $e->getMessage());
        return false;
    }
}

function verify_boss_login($system_name, $password) {
    $db_path = __DIR__ . '/databases/DB_manager.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM bosses_table WHERE system_name = ? AND boss_username = ? AND status = 'active'");
        $stmt->execute([$system_name, $password]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("خطا در verify_boss_login: " . $e->getMessage());
        return false;
    }
}

function get_boss_whiteboards($system_name) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return [];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return [];
    
    try {
        return $pdo->query("SELECT name FROM whiteboards ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("خطا در get_boss_whiteboards: " . $e->getMessage());
        return [];
    }
}

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

function add_message_to_whiteboard($system_name, $whiteboard_name, $agent, $message) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    $table_name = 'wb_' . $whiteboard_name;
    try {
        $stmt = $pdo->prepare("INSERT INTO $table_name (date, agent, message) VALUES (?, ?, ?)");
        $stmt->execute([date('Y-m-d H:i:s'), $agent, $message]);
        
        $log_table = 'log_' . $agent;
        $stmt = $pdo->prepare("INSERT INTO $log_table (date, whiteboard_name, message) VALUES (?, ?, ?)");
        $stmt->execute([date('Y-m-d H:i:s'), $whiteboard_name, $message]);
        
        return true;
    } catch (PDOException $e) {
        error_log("خطا در add_message_to_whiteboard: " . $e->getMessage());
        return false;
    }
}

function get_whiteboard_access_matrix($system_name) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return ['whiteboards' => [], 'users' => []];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return ['whiteboards' => [], 'users' => []];
    
    try {
        $whiteboards = $pdo->query("SELECT name FROM whiteboards ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        $users = $pdo->query("SELECT DISTINCT agent_name FROM access_level ORDER BY agent_name")->fetchAll(PDO::FETCH_COLUMN);
        
        $matrix = [];
        foreach ($users as $user) {
            $row = ['agent' => $user];
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
        
        return ['whiteboards' => $whiteboards, 'users' => $matrix];
    } catch (PDOException $e) {
        error_log("خطا در get_whiteboard_access_matrix: " . $e->getMessage());
        return ['whiteboards' => [], 'users' => []];
    }
}

function add_user_to_boss($system_name, $agent_name, $password) {
    if (empty($password)) {
        $password = generate_random_code(8);
    }
    
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM access_level WHERE agent_name = ?");
        $stmt->execute([$agent_name]);
        if ($stmt->fetchColumn() > 0) return false;
        
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
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("خطا در add_user_to_boss: " . $e->getMessage());
        return false;
    }
}

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
// توابع جدید: سیستم پیام‌رسان (Reminders)
// ============================================

function create_reminders_table($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS reminders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sender TEXT NOT NULL,
                receiver TEXT NOT NULL,
                message TEXT NOT NULL,
                whiteboards TEXT,
                send_time TEXT NOT NULL,
                dead_time TEXT NOT NULL,
                is_read INTEGER DEFAULT 0,
                is_deleted INTEGER DEFAULT 0
            )
        ");
        return true;
    } catch (PDOException $e) {
        error_log("خطا در create_reminders_table: " . $e->getMessage());
        return false;
    }
}

function send_reminder($system_name, $sender, $receiver, $message, $whiteboards, $dead_time) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return ['success' => false, 'error' => 'دیتابیس وجود ندارد'];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
    
    // ایجاد جدول اگر وجود نداشته باشد
    create_reminders_table($pdo);
    
    // بررسی وجود گیرنده
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM access_level WHERE agent_name = ?");
    $stmt->execute([$receiver]);
    if ($stmt->fetchColumn() == 0) {
        return ['success' => false, 'error' => 'گیرنده وجود ندارد'];
    }
    
    // بررسی dead_time
    $now = time();
    $dead_timestamp = strtotime($dead_time);
    if ($dead_timestamp <= $now) {
        return ['success' => false, 'error' => 'زمان انقضا باید بزرگتر از زمان فعلی باشد'];
    }
    
    // تبدیل لیست وایتبردها به رشته
    $whiteboards_str = is_array($whiteboards) ? implode(',', $whiteboards) : $whiteboards;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO reminders (sender, receiver, message, whiteboards, send_time, dead_time)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$sender, $receiver, $message, $whiteboards_str, date('Y-m-d H:i:s'), $dead_time]);
        
        // ثبت در log فرستنده
        $log_table = 'log_' . $sender;
        $log_message = "ارسال یادآوری به $receiver: $message";
        $stmt = $pdo->prepare("INSERT INTO $log_table (date, whiteboard_name, message) VALUES (?, ?, ?)");
        $stmt->execute([date('Y-m-d H:i:s'), 'سیستم', $log_message]);
        
        return ['success' => true, 'id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        error_log("خطا در send_reminder: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function get_user_reminders($system_name, $username) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return ['success' => false, 'error' => 'دیتابیس وجود ندارد'];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
    
    // ایجاد جدول اگر وجود نداشته باشد
    create_reminders_table($pdo);
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM reminders 
            WHERE receiver = ? AND is_deleted = 0 
            ORDER BY dead_time ASC
        ");
        $stmt->execute([$username]);
        $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // محاسبه زمان باقیمانده برای هر پیام
        $now = time();
        foreach ($reminders as &$row) {
            $dead_timestamp = strtotime($row['dead_time']);
            $remaining = $dead_timestamp - $now;
            
            if ($remaining <= 0) {
                $row['remaining'] = '⏰ زمان گذشته';
            } else {
                $days = floor($remaining / 86400);
                $hours = floor(($remaining % 86400) / 3600);
                $minutes = floor(($remaining % 3600) / 60);
                $row['remaining'] = $days . ' روز ' . $hours . ' ساعت ' . $minutes . ' دقیقه';
            }
            
            // تبدیل لیست وایتبردها به آرایه
            $row['whiteboards_list'] = !empty($row['whiteboards']) ? explode(',', $row['whiteboards']) : [];
        }
        
        return ['success' => true, 'data' => $reminders];
    } catch (PDOException $e) {
        error_log("خطا در get_user_reminders: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function delete_reminder($system_name, $reminder_id, $username) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return ['success' => false, 'error' => 'دیتابیس وجود ندارد'];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
    
    try {
        // فقط اگر گیرنده یا فرستنده کاربر فعلی باشد یا رییس باشد
        $stmt = $pdo->prepare("
            UPDATE reminders SET is_deleted = 1 
            WHERE id = ? AND (receiver = ? OR sender = ?)
        ");
        $stmt->execute([$reminder_id, $username, $username]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'شما دسترسی به این پیام ندارید'];
        }
    } catch (PDOException $e) {
        error_log("خطا در delete_reminder: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function get_all_reminders_for_boss($system_name) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return ['success' => false, 'error' => 'دیتابیس وجود ندارد'];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
    
    create_reminders_table($pdo);
    
    try {
        $stmt = $pdo->query("
            SELECT * FROM reminders WHERE is_deleted = 0 ORDER BY send_time DESC
        ");
        $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $now = time();
        foreach ($reminders as &$row) {
            $dead_timestamp = strtotime($row['dead_time']);
            $remaining = $dead_timestamp - $now;
            $row['remaining'] = ($remaining > 0) ? floor($remaining / 3600) . ' ساعت' : '⏰ گذشته';
            $row['whiteboards_list'] = !empty($row['whiteboards']) ? explode(',', $row['whiteboards']) : [];
        }
        
        return ['success' => true, 'data' => $reminders];
    } catch (PDOException $e) {
        error_log("خطا در get_all_reminders_for_boss: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function delete_reminder_by_boss($system_name, $reminder_id) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return ['success' => false, 'error' => 'دیتابیس وجود ندارد'];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
    
    try {
        $stmt = $pdo->prepare("UPDATE reminders SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$reminder_id]);
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function get_users_list($system_name) {
    $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
    if (!file_exists($db_path)) return [];
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return [];
    
    try {
        return $pdo->query("SELECT DISTINCT agent_name FROM access_level ORDER BY agent_name")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

function initialize_db_manager() {
    $db_dir = __DIR__ . '/databases';
    if (!is_dir($db_dir)) {
        mkdir($db_dir, 0777, true);
    }
    
    $db_path = $db_dir . '/DB_manager.sqlite';
    if (file_exists($db_path)) return true;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    try {
        $pdo->exec("
            CREATE TABLE manager_pass (
                id INTEGER PRIMARY KEY,
                password TEXT NOT NULL
            )
        ");
        $pdo->exec("INSERT INTO manager_pass (password) VALUES ('mhd_msz_nasim_toos')");
        
        $pdo->exec("
            CREATE TABLE bosses_table (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                system_name TEXT NOT NULL,
                boss_username TEXT NOT NULL UNIQUE,
                contact TEXT,
                status TEXT DEFAULT 'active'
            )
        ");
        
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
    } catch (PDOException $e) {
        error_log("خطا در initialize_db_manager: " . $e->getMessage());
        return false;
    }
}

function create_boss_database($system_name, $boss_username) {
    $db_dir = __DIR__ . '/databases';
    if (!is_dir($db_dir)) {
        mkdir($db_dir, 0777, true);
    }
    
    $db_path = $db_dir . '/' . $system_name . '.sqlite';
    if (file_exists($db_path)) return false;
    
    $pdo = create_database_connection($db_path);
    if (!$pdo) return false;
    
    try {
        $pdo->exec("
            CREATE TABLE whiteboards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                created_date TEXT NOT NULL
            )
        ");
        
        $pdo->exec("
            CREATE TABLE access_level (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                agent_name TEXT NOT NULL,
                password TEXT,
                whiteboard_name TEXT NOT NULL,
                level INTEGER DEFAULT 0
            )
        ");
        
        $pdo->exec("
            CREATE TABLE system_info (
                id INTEGER PRIMARY KEY,
                system_name TEXT NOT NULL,
                boss_username TEXT NOT NULL
            )
        ");
        $stmt = $pdo->prepare("INSERT INTO system_info (system_name, boss_username) VALUES (?, ?)");
        $stmt->execute([$system_name, $boss_username]);
        
        // ایجاد جدول reminders
        create_reminders_table($pdo);
        
        return true;
    } catch (PDOException $e) {
        error_log("خطا در create_boss_database: " . $e->getMessage());
        return false;
    }
}

// ============================================
// شروع پردازش درخواست‌ها
// ============================================
initialize_db_manager();

$action = isset($_POST['action']) ? $_POST['action'] : '';

// ============================================
// پردازش درخواست‌های AJAX
// ============================================
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    $response = ['success' => false, 'error' => 'اکشن نامعتبر'];
    
    switch ($action) {
        // ============================================
        // اکشن‌های مدیریت
        // ============================================
        case 'verify_manager':
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $db_path = __DIR__ . '/databases/DB_manager.sqlite';
            if (file_exists($db_path)) {
                $pdo = create_database_connection($db_path);
                if ($pdo) {
                    $stmt = $pdo->prepare("SELECT password FROM manager_pass WHERE id = 1");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $response = ['success' => $result && $result['password'] === $password];
                }
            }
            break;
        
        case 'get_requests':
            $db_path = __DIR__ . '/databases/DB_manager.sqlite';
            if (file_exists($db_path)) {
                $pdo = create_database_connection($db_path);
                if ($pdo) {
                    try {
                        $stmt = $pdo->query("SELECT * FROM new_requests WHERE status = 'pending' ORDER BY request_date");
                        $response = ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
                    } catch (PDOException $e) {
                        $response = ['success' => false, 'error' => $e->getMessage()];
                    }
                }
            }
            break;
        
        case 'add_request':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $contact = isset($_POST['contact']) ? $_POST['contact'] : '';
            
            if (empty($system_name) || empty($contact)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $db_path = __DIR__ . '/databases/DB_manager.sqlite';
            if (!file_exists($db_path)) {
                $response = ['success' => false, 'error' => 'دیتابیس مدیریت وجود ندارد'];
                break;
            }
            
            $pdo = create_database_connection($db_path);
            if (!$pdo) {
                $response = ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("INSERT INTO new_requests (system_name, contact, request_date, status) VALUES (?, ?, ?, 'pending')");
                $result = $stmt->execute([$system_name, $contact, date('Y-m-d H:i:s')]);
                $response = ['success' => $result];
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            break;
        
        case 'approve_request':
            $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
            
            $db_path = __DIR__ . '/databases/DB_manager.sqlite';
            if (!file_exists($db_path)) {
                $response = ['success' => false, 'error' => 'دیتابیس مدیریت وجود ندارد'];
                break;
            }
            
            $pdo = create_database_connection($db_path);
            if (!$pdo) {
                $response = ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT * FROM new_requests WHERE id = ? AND status = 'pending'");
                $stmt->execute([$request_id]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$request) {
                    $response = ['success' => false, 'error' => 'درخواست یافت نشد'];
                    break;
                }
                
                $boss_username = generate_random_code(16);
                $system_name = $request['system_name'];
                
                $stmt = $pdo->prepare("INSERT INTO bosses_table (system_name, boss_username, contact, status) VALUES (?, ?, ?, 'active')");
                $stmt->execute([$system_name, $boss_username, $request['contact']]);
                
                $stmt = $pdo->prepare("UPDATE new_requests SET status = 'approved' WHERE id = ?");
                $stmt->execute([$request_id]);
                
                create_boss_database($system_name, $boss_username);
                
                $response = ['success' => true, 'data' => ['system_name' => $system_name, 'boss_username' => $boss_username]];
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            break;
        
        case 'reject_request':
            $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
            
            $db_path = __DIR__ . '/databases/DB_manager.sqlite';
            if (!file_exists($db_path)) {
                $response = ['success' => false, 'error' => 'دیتابیس مدیریت وجود ندارد'];
                break;
            }
            
            $pdo = create_database_connection($db_path);
            if (!$pdo) {
                $response = ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE new_requests SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$request_id]);
                $response = ['success' => true];
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            break;
        
        case 'get_bosses':
            $db_path = __DIR__ . '/databases/DB_manager.sqlite';
            if (!file_exists($db_path)) {
                $response = ['success' => false, 'error' => 'دیتابیس مدیریت وجود ندارد'];
                break;
            }
            
            $pdo = create_database_connection($db_path);
            if (!$pdo) {
                $response = ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
                break;
            }
            
            try {
                $stmt = $pdo->query("SELECT * FROM bosses_table ORDER BY system_name");
                $response = ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            break;
        
        case 'update_boss':
            $boss_id = isset($_POST['boss_id']) ? intval($_POST['boss_id']) : 0;
            $new_username = isset($_POST['new_username']) ? $_POST['new_username'] : '';
            
            $db_path = __DIR__ . '/databases/DB_manager.sqlite';
            if (!file_exists($db_path)) {
                $response = ['success' => false, 'error' => 'دیتابیس مدیریت وجود ندارد'];
                break;
            }
            
            $pdo = create_database_connection($db_path);
            if (!$pdo) {
                $response = ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE bosses_table SET boss_username = ? WHERE id = ?");
                $stmt->execute([$new_username, $boss_id]);
                $response = ['success' => true];
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            break;
        
        // ============================================
        // اکشن‌های ورود
        // ============================================
        case 'verify_boss':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            
            $result = verify_boss_login($system_name, $password);
            if ($result) {
                $_SESSION['system_name'] = $system_name;
                $_SESSION['boss_username'] = $result['boss_username'];
                $_SESSION['login_time'] = time();
                $response = ['success' => true, 'data' => $result];
            } else {
                $response = ['success' => false, 'error' => 'نام سیستم یا رمز اشتباه است'];
            }
            break;
        
        case 'verify_agent':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $agent_name = isset($_POST['agent_name']) ? $_POST['agent_name'] : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            
            if (empty($system_name) || empty($agent_name) || empty($password)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $result = verify_agent_login($system_name, $agent_name, $password);
            if ($result) {
                $_SESSION['system_name'] = $system_name;
                $_SESSION['agent_name'] = $agent_name;
                $_SESSION['login_time'] = time();
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'error' => 'نام کاربری یا رمز اشتباه است'];
            }
            break;
        
        // ============================================
        // اکشن‌های وایتبرد
        // ============================================
        case 'get_whiteboards':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            if (empty($system_name)) {
                $boss_username = isset($_POST['boss_username']) ? $_POST['boss_username'] : '';
                if (!empty($boss_username)) {
                    $system_name = get_system_name_by_boss_username($boss_username);
                }
            }
            
            if (empty($system_name)) {
                $response = ['success' => false, 'error' => 'نام سیستم مشخص نیست'];
                break;
            }
            
            $whiteboards = get_boss_whiteboards($system_name);
            $response = ['success' => true, 'data' => $whiteboards];
            break;
        
        case 'get_whiteboards_for_agent':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $agent_name = isset($_POST['agent_name']) ? $_POST['agent_name'] : '';
            
            if (empty($system_name) || empty($agent_name)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
            if (!file_exists($db_path)) {
                $response = ['success' => false, 'error' => 'دیتابیس رییس وجود ندارد'];
                break;
            }
            
            $pdo = create_database_connection($db_path);
            if (!$pdo) {
                $response = ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT whiteboard_name, level FROM access_level WHERE agent_name = ? AND level > 0 AND whiteboard_name != ''");
                $stmt->execute([$agent_name]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $whiteboards = [];
                foreach ($results as $row) {
                    $whiteboards[] = ['name' => $row['whiteboard_name'], 'level' => $row['level']];
                }
                $response = ['success' => true, 'data' => $whiteboards];
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            break;
        
        case 'get_messages':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $whiteboard_name = isset($_POST['whiteboard_name']) ? $_POST['whiteboard_name'] : '';
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name) || empty($whiteboard_name)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $messages = get_whiteboard_messages($system_name, $whiteboard_name);
            $response = ['success' => true, 'data' => $messages];
            break;
        
        case 'add_message':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $whiteboard_name = isset($_POST['whiteboard_name']) ? $_POST['whiteboard_name'] : '';
            $agent = isset($_POST['agent']) ? $_POST['agent'] : '';
            $message = isset($_POST['message']) ? $_POST['message'] : '';
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name) || empty($whiteboard_name) || empty($agent) || empty($message)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $result = add_message_to_whiteboard($system_name, $whiteboard_name, $agent, $message);
            $response = ['success' => $result];
            break;
        
        case 'delete_message':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $whiteboard_name = isset($_POST['whiteboard_name']) ? $_POST['whiteboard_name'] : '';
            $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name) || empty($whiteboard_name) || !$message_id) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
            if (!file_exists($db_path)) {
                $response = ['success' => false, 'error' => 'دیتابیس رییس وجود ندارد'];
                break;
            }
            
            $pdo = create_database_connection($db_path);
            if (!$pdo) {
                $response = ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
                break;
            }
            
            try {
                $table_name = 'wb_' . $whiteboard_name;
                $stmt = $pdo->prepare("DELETE FROM $table_name WHERE id = ?");
                $stmt->execute([$message_id]);
                $response = ['success' => true];
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            break;
        
        // ============================================
        // اکشن‌های مدیریت کاربران
        // ============================================
        case 'get_access_matrix':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name)) {
                $response = ['success' => false, 'error' => 'نام سیستم وارد نشده'];
                break;
            }
            
            $matrix = get_whiteboard_access_matrix($system_name);
            $response = ['success' => true, 'data' => $matrix];
            break;
        
        case 'update_access':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $agent_name = isset($_POST['agent_name']) ? $_POST['agent_name'] : '';
            $whiteboard_name = isset($_POST['whiteboard_name']) ? $_POST['whiteboard_name'] : '';
            $level = isset($_POST['level']) ? intval($_POST['level']) : 0;
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name) || empty($agent_name) || empty($whiteboard_name)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
            if (!file_exists($db_path)) {
                $response = ['success' => false, 'error' => 'دیتابیس رییس وجود ندارد'];
                break;
            }
            
            $pdo = create_database_connection($db_path);
            if (!$pdo) {
                $response = ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE access_level SET level = ? WHERE agent_name = ? AND whiteboard_name = ?");
                $stmt->execute([$level, $agent_name, $whiteboard_name]);
                $response = ['success' => true];
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            break;
        
        case 'add_user_by_boss':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $agent_name = isset($_POST['agent_name']) ? $_POST['agent_name'] : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name) || empty($agent_name)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            if (empty($password)) {
                $password = generate_random_code(8);
            }
            
            $result = add_user_to_boss($system_name, $agent_name, $password);
            if ($result) {
                $response = ['success' => true, 'password' => $password, 'message' => 'کاربر با موفقیت اضافه شد. رمز: ' . $password];
            } else {
                $response = ['success' => false, 'error' => 'خطا در اضافه کردن کاربر'];
            }
            break;
        
        case 'delete_user':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $agent_name = isset($_POST['agent_name']) ? $_POST['agent_name'] : '';
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name) || empty($agent_name)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
            if (!file_exists($db_path)) {
                $response = ['success' => false, 'error' => 'دیتابیس رییس وجود ندارد'];
                break;
            }
            
            $pdo = create_database_connection($db_path);
            if (!$pdo) {
                $response = ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("DELETE FROM access_level WHERE agent_name = ?");
                $stmt->execute([$agent_name]);
                $log_table = 'log_' . $agent_name;
                $pdo->exec("DROP TABLE $log_table");
                $response = ['success' => true];
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            break;
        
        // ============================================
        // اکشن‌های مدیریت وایتبرد
        // ============================================
        case 'create_whiteboard':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $whiteboard_name = isset($_POST['whiteboard_name']) ? $_POST['whiteboard_name'] : '';
            $agent = isset($_POST['agent']) ? $_POST['agent'] : '';
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name) || empty($whiteboard_name)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
            if (!file_exists($db_path)) {
                $response = ['success' => false, 'error' => 'دیتابیس رییس وجود ندارد'];
                break;
            }
            
            $pdo = create_database_connection($db_path);
            if (!$pdo) {
                $response = ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM whiteboards WHERE name = ?");
                $stmt->execute([$whiteboard_name]);
                if ($stmt->fetchColumn() > 0) {
                    $response = ['success' => false, 'error' => 'نام تکراری است'];
                    break;
                }
                
                $stmt = $pdo->prepare("INSERT INTO whiteboards (name, created_date) VALUES (?, ?)");
                $stmt->execute([$whiteboard_name, date('Y-m-d H:i:s')]);
                
                $table_name = 'wb_' . $whiteboard_name;
                $pdo->exec("
                    CREATE TABLE $table_name (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        date TEXT NOT NULL,
                        agent TEXT NOT NULL,
                        message TEXT NOT NULL
                    )
                ");
                
                $users = $pdo->query("SELECT DISTINCT agent_name FROM access_level")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($users as $user) {
                    $stmt = $pdo->prepare("INSERT INTO access_level (agent_name, whiteboard_name, level) VALUES (?, ?, 0)");
                    $stmt->execute([$user, $whiteboard_name]);
                }
                
                if (!empty($agent) && $agent !== 'boss') {
                    $stmt = $pdo->prepare("UPDATE access_level SET level = 2 WHERE agent_name = ? AND whiteboard_name = ?");
                    $stmt->execute([$agent, $whiteboard_name]);
                }
                
                $response = ['success' => true];
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            break;
        
        case 'delete_whiteboard':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $whiteboard_name = isset($_POST['whiteboard_name']) ? $_POST['whiteboard_name'] : '';
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name) || empty($whiteboard_name)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
            if (!file_exists($db_path)) {
                $response = ['success' => false, 'error' => 'دیتابیس رییس وجود ندارد'];
                break;
            }
            
            $pdo = create_database_connection($db_path);
            if (!$pdo) {
                $response = ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("DELETE FROM whiteboards WHERE name = ?");
                $stmt->execute([$whiteboard_name]);
                
                $table_name = 'wb_' . $whiteboard_name;
                $pdo->exec("DROP TABLE $table_name");
                
                $stmt = $pdo->prepare("DELETE FROM access_level WHERE whiteboard_name = ?");
                $stmt->execute([$whiteboard_name]);
                
                $response = ['success' => true];
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            break;
        
        case 'clear_whiteboard':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $whiteboard_name = isset($_POST['whiteboard_name']) ? $_POST['whiteboard_name'] : '';
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name) || empty($whiteboard_name)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
            if (!file_exists($db_path)) {
                $response = ['success' => false, 'error' => 'دیتابیس رییس وجود ندارد'];
                break;
            }
            
            $pdo = create_database_connection($db_path);
            if (!$pdo) {
                $response = ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
                break;
            }
            
            try {
                $table_name = 'wb_' . $whiteboard_name;
                $stmt = $pdo->prepare("DELETE FROM $table_name");
                $stmt->execute();
                $response = ['success' => true];
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            break;
        
        case 'rename_whiteboard':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $old_name = isset($_POST['old_name']) ? $_POST['old_name'] : '';
            $new_name = isset($_POST['new_name']) ? $_POST['new_name'] : '';
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name) || empty($old_name) || empty($new_name)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
            if (!file_exists($db_path)) {
                $response = ['success' => false, 'error' => 'دیتابیس رییس وجود ندارد'];
                break;
            }
            
            $pdo = create_database_connection($db_path);
            if (!$pdo) {
                $response = ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM whiteboards WHERE name = ?");
                $stmt->execute([$new_name]);
                if ($stmt->fetchColumn() > 0) {
                    $response = ['success' => false, 'error' => 'نام جدید تکراری است'];
                    break;
                }
                
                $stmt = $pdo->prepare("UPDATE whiteboards SET name = ? WHERE name = ?");
                $stmt->execute([$new_name, $old_name]);
                
                $old_table = 'wb_' . $old_name;
                $new_table = 'wb_' . $new_name;
                $pdo->exec("ALTER TABLE $old_table RENAME TO $new_table");
                
                $stmt = $pdo->prepare("UPDATE access_level SET whiteboard_name = ? WHERE whiteboard_name = ?");
                $stmt->execute([$new_name, $old_name]);
                
                $users = $pdo->query("SELECT DISTINCT agent_name FROM access_level")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($users as $user) {
                    $log_table = 'log_' . $user;
                    try {
                        $stmt = $pdo->prepare("UPDATE $log_table SET whiteboard_name = ? WHERE whiteboard_name = ?");
                        $stmt->execute([$new_name, $old_name]);
                    } catch (PDOException $e) {
                        continue;
                    }
                }
                
                $response = ['success' => true];
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            break;
        
        // ============================================
        // اکشن‌های گزارش‌گیری
        // ============================================
        case 'get_user_log':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $agent_name = isset($_POST['agent_name']) ? $_POST['agent_name'] : '';
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name) || empty($agent_name)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $log = get_user_log($system_name, $agent_name);
            $response = ['success' => true, 'data' => $log];
            break;
        
        case 'get_messages_by_date':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
            $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name) || empty($start_date) || empty($end_date)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $db_path = __DIR__ . '/databases/' . $system_name . '.sqlite';
            if (!file_exists($db_path)) {
                $response = ['success' => false, 'error' => 'دیتابیس رییس وجود ندارد'];
                break;
            }
            
            $pdo = create_database_connection($db_path);
            if (!$pdo) {
                $response = ['success' => false, 'error' => 'خطا در اتصال به دیتابیس'];
                break;
            }
            
            try {
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
                        continue;
                    }
                }
                $response = ['success' => true, 'data' => $all_messages];
            } catch (PDOException $e) {
                $response = ['success' => false, 'error' => $e->getMessage()];
            }
            break;
        
        // ============================================
        // اکشن‌های کمکی
        // ============================================
        case 'get_available_systems_for_agent':
            $db_path = __DIR__ . '/databases/DB_manager.sqlite';
            if (!file_exists($db_path)) {
                $response = ['success' => true, 'data' => []];
                break;
            }
            
            $pdo = create_database_connection($db_path);
            if (!$pdo) {
                $response = ['success' => true, 'data' => []];
                break;
            }
            
            try {
                $stmt = $pdo->query("SELECT system_name FROM bosses_table WHERE status = 'active'");
                $response = ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)];
            } catch (PDOException $e) {
                $response = ['success' => true, 'data' => []];
            }
            break;
        
        case 'get_boss_username':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            if (empty($system_name)) {
                $response = ['success' => false, 'error' => 'نام سیستم وارد نشده'];
                break;
            }
            
            $boss_username = get_boss_username_by_system_name($system_name);
            if ($boss_username) {
                $response = ['success' => true, 'data' => ['boss_username' => $boss_username]];
            } else {
                $response = ['success' => false, 'error' => 'سیستم یافت نشد'];
            }
            break;
        
        // ============================================
        // اکشن‌های جدید: سیستم پیام‌رسان
        // ============================================
        
        // دریافت لیست کاربران برای ارسال پیام
        case 'get_users_list':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name)) {
                $response = ['success' => false, 'error' => 'نام سیستم مشخص نیست'];
                break;
            }
            
            $users = get_users_list($system_name);
            $response = ['success' => true, 'data' => $users];
            break;
        
        // ارسال پیام جدید
        case 'send_reminder':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $receiver = isset($_POST['receiver']) ? $_POST['receiver'] : '';
            $message = isset($_POST['message']) ? $_POST['message'] : '';
            $whiteboards = isset($_POST['whiteboards']) ? $_POST['whiteboards'] : '';
            $dead_time = isset($_POST['dead_time']) ? $_POST['dead_time'] : '';
            $sender = isset($_SESSION['agent_name']) ? $_SESSION['agent_name'] : '';
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name) || empty($receiver) || empty($message) || empty($dead_time)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            // اگر sender خالی است (برای رییس)
            if (empty($sender)) {
                $sender = 'رییس';
            }
            
            $result = send_reminder($system_name, $sender, $receiver, $message, $whiteboards, $dead_time);
            $response = $result;
            break;
        
        // دریافت پیام‌های کاربر
        case 'get_reminders':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $username = isset($_POST['username']) ? $_POST['username'] : '';
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            if (empty($username)) {
                $username = isset($_SESSION['agent_name']) ? $_SESSION['agent_name'] : '';
            }
            
            if (empty($system_name) || empty($username)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $result = get_user_reminders($system_name, $username);
            $response = $result;
            break;
        
        // حذف پیام توسط کاربر
        case 'delete_reminder':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $reminder_id = isset($_POST['reminder_id']) ? intval($_POST['reminder_id']) : 0;
            $username = isset($_SESSION['agent_name']) ? $_SESSION['agent_name'] : '';
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name) || !$reminder_id || empty($username)) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $result = delete_reminder($system_name, $reminder_id, $username);
            $response = $result;
            break;
        
        // دریافت همه پیام‌ها (برای رییس)
        case 'get_all_reminders':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name)) {
                $response = ['success' => false, 'error' => 'نام سیستم مشخص نیست'];
                break;
            }
            
            $result = get_all_reminders_for_boss($system_name);
            $response = $result;
            break;
        
        // حذف پیام توسط رییس
        case 'delete_reminder_by_boss':
            $system_name = isset($_POST['system_name']) ? $_POST['system_name'] : '';
            $reminder_id = isset($_POST['reminder_id']) ? intval($_POST['reminder_id']) : 0;
            
            if (empty($system_name)) {
                $system_name = isset($_SESSION['system_name']) ? $_SESSION['system_name'] : '';
            }
            
            if (empty($system_name) || !$reminder_id) {
                $response = ['success' => false, 'error' => 'اطلاعات کامل نیست'];
                break;
            }
            
            $result = delete_reminder_by_boss($system_name, $reminder_id);
            $response = $result;
            break;
        
        // ============================================
        // خروج از سیستم
        // ============================================
        case 'logout':
            session_destroy();
            $response = ['success' => true];
            break;
        
        // ============================================
        // default
        // ============================================
        default:
            $response = ['success' => false, 'error' => 'اکشن "' . $action . '" پشتیبانی نمی‌شود'];
            break;
    }
    
    echo json_encode($response);
    exit;
}

// ============================================
// صفحه اصلی (اگر درخواست AJAX نباشد)
// ============================================
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
            <a href="manager.html" class="btn btn-primary">مدیریت سایت</a>
            <a href="boss.html" class="btn btn-success">ورود رییس</a>
            <a href="agent.html" class="btn btn-primary">ورود کاربر</a>
        </div>
    </div>
</body>
</html>