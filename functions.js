/*
* ============================================
* فایل: functions.js
* وظیفه: توابع مشترک بین همه صفحات
* ============================================
*/

// ============================================
// تابع: formatDate
// وظیفه: فرمت کردن تاریخ
// ورودی: dateString (رشته تاریخ)
// خروجی: تاریخ فرمت شده
// ============================================
function formatDate(dateString) {
    if (!dateString) return '-';
    var date = new Date(dateString);
    return date.toLocaleString('fa-IR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// ============================================
// تابع: showAlert
// وظیفه: نمایش هشدار با استایل مناسب
// ورودی: message, type ('error', 'success', 'info')
// خروجی: ندارد
// ============================================
function showAlert(message, type) {
    var alertDiv = document.createElement('div');
    alertDiv.className = type || 'info';
    alertDiv.textContent = message;
    alertDiv.style.padding = '10px';
    alertDiv.style.margin = '10px 0';
    alertDiv.style.borderRadius = '5px';
    
    document.body.prepend(alertDiv);
    
    setTimeout(function() {
        alertDiv.style.opacity = '0';
        setTimeout(function() {
            alertDiv.remove();
        }, 300);
    }, 3000);
}

// ============================================
// تابع: validateEmail
// وظیفه: اعتبارسنجی ایمیل
// ورودی: email
// خروجی: true یا false
// ============================================
function validateEmail(email) {
    var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// ============================================
// تابع: validatePhone
// وظیفه: اعتبارسنجی شماره تماس (ایرانی)
// ورودی: phone
// خروجی: true یا false
// ============================================
function validatePhone(phone) {
    var re = /^09[0-9]{9}$/;
    return re.test(phone);
}

// ============================================
// تابع: generateRandomPassword
// وظیفه: تولید رمز تصادفی
// ورودی: length (طول رمز)
// خروجی: رمز تصادفی
// ============================================
function generateRandomPassword(length) {
    var chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    var password = '';
    for (var i = 0; i < length; i++) {
        password += chars[Math.floor(Math.random() * chars.length)];
    }
    return password;
}

// ============================================
// تابع: getUrlParameter
// وظیفه: دریافت پارامتر از URL
// ورودی: name (نام پارامتر)
// خروجی: مقدار پارامتر یا null
// ============================================
function getUrlParameter(name) {
    var url = window.location.search.substring(1);
    var params = url.split('&');
    for (var i = 0; i < params.length; i++) {
        var param = params[i].split('=');
        if (param[0] === name) {
            return decodeURIComponent(param[1]);
        }
    }
    return null;
}

// ============================================
// تابع: copyToClipboard
// وظیفه: کپی متن در کلیپ‌بورد
// ورودی: text
// خروجی: ندارد
// ============================================
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            showAlert('متن کپی شد!', 'success');
        }).catch(function() {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        showAlert('متن کپی شد!', 'success');
    } catch (e) {
        showAlert('خطا در کپی متن', 'error');
    }
    document.body.removeChild(textarea);
}

// ============================================
// تابع: confirmAction
// وظیفه: نمایش پیام تأیید
// ورودی: message
// خروجی: true یا false
// ============================================
function confirmAction(message) {
    return confirm(message || 'آیا از انجام این عمل مطمئن هستید؟');
}

// ============================================
// تابع: escapeHtml
// وظیفه: جلوگیری از XSS
// ورودی: text
// خروجی: متن ایمن
// ============================================
function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// تابع: getCurrentDateTime
// وظیفه: دریافت تاریخ و زمان فعلی
// ورودی: ندارد
// خروجی: رشته تاریخ و زمان
// ============================================
function getCurrentDateTime() {
    var now = new Date();
    return now.toISOString().slice(0, 19).replace('T', ' ');
}

// ============================================
// تابع: truncateText
// وظیفه: کوتاه کردن متن
// ورودی: text, maxLength
// خروجی: متن کوتاه شده
// ============================================
function truncateText(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

// ============================================
// تابع: isMobileDevice
// وظیفه: تشخیص دستگاه موبایل
// ورودی: ندارد
// خروجی: true یا false
// ============================================
function isMobileDevice() {
    return window.innerWidth <= 768;
}