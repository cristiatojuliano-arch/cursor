<?php
/**
 * ============================================================
 *  АНТИФРОД v1.0 — Универсальный модуль защиты лендингов
 * ============================================================
 *
 *  УСТАНОВКА:
 *  1. Скопируй antifraud.php в папку лендинга (рядом с send.php)
 *  2. В send.php ПЕРВОЙ строкой после <?php добавь:
 *     require_once __DIR__ . '/antifraud.php';
 *  3. Готово. Папка antifraud_data/ создастся автоматически.
 *
 *  ЧТО ДЕЛАЕТ:
 *  - IP rate-limit: 1 заявка в час с одного IP
 *  - Дубль телефона: 1 заявка в 24ч на один номер
 *  - Логирование: antifraud_data/fraud.log
 *  - Telegram-уведомление при блокировке (🚫)
 *  - Стелс: заблокированный юзер видит success.php (не знает что заблочен)
 *  - Автоочистка старых файлов (раз в ~100 запросов)
 *
 *  НАСТРОЙКИ (меняй ниже):
 */

// ======================== НАСТРОЙКИ ========================

// Telegram — куда слать алерты о блокировке
$AF_TG_TOKEN   = 'TELEGRAM_BOT_TOKEN';
$AF_TG_CHAT_ID = 'TELEGRAM_CHAT_ID';

// Таймауты
$AF_IP_COOLDOWN    = 3600;   // секунд — 1 заявка в час с одного IP
$AF_PHONE_COOLDOWN = 86400;  // секунд — 1 заявка в 24ч на один номер

// Редирект при блокировке (stealth — фродер думает что всё ок)
$AF_REDIRECT_PAGE  = '/landers/SLUG/success.php';

// Имя проекта для редиректа (можно менять под оффер)
$AF_PROJECT_NAME   = '';

// Поля формы — какие POST-ключи искать для телефона
$AF_PHONE_FIELDS   = ['full_number', 'full_phone', 'fullPhone', 'phone'];

// Поля формы — имя (для лога и ТГ)
$AF_NAME_FIELDS    = ['first_name', 'fname', 'name'];
$AF_LNAME_FIELDS   = ['last_name', 'lname'];

// ======================== КОД (НЕ ТРОГАТЬ) ========================

// Директории хранения
$_af_dir       = __DIR__ . '/antifraud_data';
$_af_ip_dir    = $_af_dir . '/ip';
$_af_phone_dir = $_af_dir . '/phone';
$_af_log_file  = $_af_dir . '/fraud.log';

// Создаём папки
if (!is_dir($_af_ip_dir))    mkdir($_af_ip_dir, 0755, true);
if (!is_dir($_af_phone_dir)) mkdir($_af_phone_dir, 0755, true);

// .htaccess защита папки от прямого доступа
$_af_htaccess = $_af_dir . '/.htaccess';
if (!file_exists($_af_htaccess)) {
    file_put_contents($_af_htaccess, "Deny from all\n");
}

/**
 * Определяем реальный IP (CloudFlare, прокси, напрямую)
 */
function _af_getClientIP() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',  // CloudFlare
        'HTTP_X_REAL_IP',         // Nginx proxy
        'HTTP_X_FORWARDED_FOR',   // Общий прокси
    ];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Ищем телефон в POST
 */
function _af_getPhone($fields) {
    foreach ($fields as $f) {
        if (!empty($_POST[$f])) {
            return preg_replace('/\D/', '', $_POST[$f]);
        }
    }
    return '';
}

/**
 * Ищем имя в POST
 */
function _af_getName($name_fields, $lname_fields) {
    $fname = '';
    foreach ($name_fields as $f) {
        if (!empty($_POST[$f])) { $fname = $_POST[$f]; break; }
    }
    $lname = '';
    foreach ($lname_fields as $f) {
        if (!empty($_POST[$f])) { $lname = $_POST[$f]; break; }
    }
    return trim($fname . ' ' . $lname) ?: '?';
}

// --- Собираем данные ---
$_af_client_ip   = _af_getClientIP();
$_af_phone_clean = _af_getPhone($AF_PHONE_FIELDS);
$_af_is_fraud    = false;
// --- BINOM_EMPTY_PHONE_GUARD: empty/short phone never reaches CPA or Telegram ---
if (strlen($_af_phone_clean) < 8) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_POST['is_ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'error' => 'phone']);
        exit;
    }
    $__back = is_file(__DIR__ . '/index.html') ? 'index.html' : (is_file(__DIR__ . '/index.php') ? 'index.php' : './');
    header('Location: ' . $__back);
    exit;
}

$_af_reason      = '';

// --- Проверка IP ---
$_af_ip_hash = md5($_af_client_ip);
$_af_ip_file = $_af_ip_dir . '/' . $_af_ip_hash;

if (file_exists($_af_ip_file)) {
    $_af_last_time = (int)file_get_contents($_af_ip_file);
    $_af_diff = time() - $_af_last_time;
    if ($_af_diff < $AF_IP_COOLDOWN) {
        $_af_is_fraud = true;
        $_af_reason   = "IP rate-limit ({$_af_client_ip}): повтор через {$_af_diff}с (лимит {$AF_IP_COOLDOWN}с)";
    }
}

// --- Проверка телефона ---
if (!$_af_is_fraud && strlen($_af_phone_clean) >= 7) {
    $_af_phone_hash = md5($_af_phone_clean);
    $_af_phone_file = $_af_phone_dir . '/' . $_af_phone_hash;
    if (file_exists($_af_phone_file)) {
        $_af_last_phone = (int)file_get_contents($_af_phone_file);
        $_af_phone_diff = time() - $_af_last_phone;
        if ($_af_phone_diff < $AF_PHONE_COOLDOWN) {
            $_af_is_fraud = true;
            $_af_reason   = "Дубль телефона: +{$_af_phone_clean} (повтор через {$_af_phone_diff}с, лимит {$AF_PHONE_COOLDOWN}с)";
        }
    }
}

// --- Если фрод — блокируем ---
if ($_af_is_fraud) {

    // Лог в файл
    $_af_log_line = date('Y-m-d H:i:s')
        . " | BLOCKED"
        . " | " . $_af_reason
        . " | Phone: +{$_af_phone_clean}"
        . " | Name: " . _af_getName($AF_NAME_FIELDS, $AF_LNAME_FIELDS)
        . " | UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? '')
        . "\n";
    file_put_contents($_af_log_file, $_af_log_line, FILE_APPEND | LOCK_EX);

    // Telegram уведомление
    if ($AF_TG_TOKEN && $AF_TG_CHAT_ID) {
        $_af_name_display = _af_getName($AF_NAME_FIELDS, $AF_LNAME_FIELDS);
        $_af_email = htmlspecialchars($_POST['email'] ?? '?');
        $_af_domain = $_SERVER['HTTP_HOST'] ?? 'n/a';

        $_af_tg_text = "🚫 <b>АНТИФРОД: Заявка заблокирована</b>\n\n"
            . "⚠️ <b>Причина:</b> " . htmlspecialchars($_af_reason) . "\n"
            . "💻 <b>IP:</b> {$_af_client_ip}\n"
            . "📞 <b>Тел:</b> +{$_af_phone_clean}\n"
            . "👤 <b>Имя:</b> " . htmlspecialchars($_af_name_display) . "\n"
            . "📧 <b>Email:</b> {$_af_email}\n"
            . "🌐 <b>Домен:</b> {$_af_domain}\n"
            . "🕐 <b>Время:</b> " . date('Y-m-d H:i:s');

        @file_get_contents(
            "https://api.telegram.org/bot{$AF_TG_TOKEN}/sendMessage?"
            . "chat_id=" . urlencode($AF_TG_CHAT_ID)
            . "&parse_mode=html"
            . "&text=" . urlencode($_af_tg_text)
        );
    }

    // Stealth редирект — фродер не узнает что заблочен
    $_af_pixel   = $_POST['pixel'] ?? '';
    $_af_project = $AF_PROJECT_NAME ?: ($_POST['project'] ?? 'offer');
    $_af_redirect = $AF_REDIRECT_PAGE
        . '?pixel=' . urlencode($_af_pixel)
        . '&project=' . urlencode($_af_project);

    // Поддержка AJAX-отправки
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        || isset($_POST['is_ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'redirect' => $_af_redirect, 'redirectUrl' => $_af_redirect]);
        exit;
    }

    header("Location: {$_af_redirect}");
    exit();
}

// --- Прошёл проверку — записываем таймстампы ---
file_put_contents($_af_ip_file, time());

if (strlen($_af_phone_clean) >= 7) {
    $_af_phone_hash = $_af_phone_hash ?? md5($_af_phone_clean);
    $_af_phone_file = $_af_phone_file ?? $_af_phone_dir . '/' . $_af_phone_hash;
    file_put_contents($_af_phone_file, time());
}

// --- Автоочистка старых файлов (раз в ~100 запросов) ---
if (mt_rand(1, 100) === 1) {
    foreach ([$_af_ip_dir, $_af_phone_dir] as $_af_clean_dir) {
        $_af_files = glob($_af_clean_dir . '/*');
        if ($_af_files) {
            foreach ($_af_files as $_af_f) {
                if (is_file($_af_f) && (time() - filemtime($_af_f)) > max($AF_IP_COOLDOWN, $AF_PHONE_COOLDOWN) * 2) {
                    @unlink($_af_f);
                }
            }
        }
    }
}

// Экспортируем IP для использования в send.php (не нужно повторно определять)
$client_ip = $_af_client_ip;

// ============================================================
//  Если дошли сюда — заявка чистая, send.php продолжает работу
// ============================================================