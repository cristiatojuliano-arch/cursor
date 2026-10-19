<?php
/**
 * BINOM offer send.php — template
 *
 * BEFORE USE fill CONFIG block below per offer.
 * Do not remove markers: BINOM_EMPTY_PHONE_GUARD, BINOM_PIXEL_COOKIE_FALLBACK,
 * BINOM_ACPA_UTM_ALIASES, BINOM_LEAD_POSTBACK.
 */
require_once __DIR__ . '/antifraud.php';

// ===================== CONFIG (per offer) =====================
$tg_token   = "TELEGRAM_BOT_TOKEN";
$tg_chat_id = "TELEGRAM_CHAT_ID";

$acpa_offer_id   = "ACPA_OFFER_ID";          // e.g. "15"
$acpa_offer_name = "OFFER_NAME [GEO-lang]";  // e.g. "Afaq Capital [IL-ar]"
$acpa_domain     = "sachreport.pro";
$acpa_sub5       = "OfferGeoTag";            // e.g. "AfaqCapitalIL"
$acpa_country    = "IL";                     // ISO-2 for CRM
$default_buyer   = "Hex";                    // fallback if buyer_mark empty

$project_name    = "Offer Project Name";     // shown on success.php
$lander_slug     = "xx-yy---offer-slug";     // folder under /landers/
$success_label   = "";                       // optional suffix in TG, e.g. " (Quiz)"

// Keitaro mirror (optional; leave as-is if used)
$keitaro_ip   = "158.94.211.64";
$keitaro_path = "c00fd30";

// Buyer mark → ACPA key + flow (extend as needed)
$buyers_config = [
    'Verona'  => ['key' => 'ACPA_KEY_VERONA',  'flow' => 'FLOW_ID'],
    'Nyx'     => ['key' => 'ACPA_KEY_NYX',     'flow' => 'FLOW_ID'],
    'Orion'   => ['key' => 'ACPA_KEY_ORION',   'flow' => 'FLOW_ID'],
    'Bristol' => ['key' => 'ACPA_KEY_BRISTOL', 'flow' => 'FLOW_ID'],
    'Hex'     => ['key' => 'ACPA_KEY_HEX',     'flow' => 'FLOW_ID'],
];
// =================== END CONFIG ===============================

if (!function_exists('_binom_reject_empty_phone')) {
    function _binom_reject_empty_phone($raw) {
        $digits = preg_replace('/\D+/', '', (string)$raw);
        if (strlen($digits) >= 8) {
            return;
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'error' => 'phone']);
            exit;
        }
        $back = is_file(__DIR__ . '/index.html') ? 'index.html' : (is_file(__DIR__ . '/index.php') ? 'index.php' : './');
        header('Location: ' . $back);
        exit;
    }
}

$fname  = $_POST['first_name'] ?? $_POST['firstname'] ?? $_POST['fname'] ?? 'Not specified';
$lname  = $_POST['last_name'] ?? $_POST['lastname'] ?? $_POST['lname'] ?? '';
$email  = $_POST['email'] ?? 'Not specified';

$raw_phone = $_POST['full_number'] ?? $_POST['full_phone'] ?? $_POST['phone'] ?? '';
$phone = $raw_phone ? '+' . ltrim($raw_phone, '+') : 'Not specified';
_binom_reject_empty_phone($phone ?? ($raw_phone ?? '')); // BINOM_EMPTY_PHONE_GUARD

$pixel = $_POST['pixel'] ?? '';
// BINOM_PIXEL_COOKIE_FALLBACK
if ($pixel === '' || $pixel === '{pixel}') {
    $pixel = trim(rawurldecode((string)($_COOKIE['_tk_pixel'] ?? $_COOKIE['pixel'] ?? '')));
}
if ($pixel === '{pixel}') { $pixel = ''; }

$subid         = $_POST['subid'] ?? $_POST['clickid'] ?? $_COOKIE['subid'] ?? '';
$campaign_name = $_POST['utm_campaign'] ?? $_POST['campaign_name'] ?? 'n/a';
$adset_name    = $_POST['utm_content'] ?? $_POST['adset_name'] ?? 'n/a';
$ad_name       = $_POST['utm_term'] ?? $_POST['ad_name'] ?? 'n/a';
$placement     = $_POST['utm_source'] ?? $_POST['placement'] ?? 'n/a';

$ip     = $_SERVER['REMOTE_ADDR'] ?? '';
$ua     = $_SERVER['HTTP_USER_AGENT'] ?? ($_POST['ua'] ?? '');
$domain = $_POST['domain'] ?? $_SERVER['HTTP_HOST'] ?? 'n/a';

$raw_buyer  = trim($_POST['buyer_mark'] ?? $_POST['buyer'] ?? $_GET['buyer'] ?? '');
if ($raw_buyer === '' && !empty($_COOKIE['_tk_buyer'])) {
    $raw_buyer = trim(rawurldecode((string)$_COOKIE['_tk_buyer']));
}
$buyer_mark = ucfirst(strtolower($raw_buyer));

if (array_key_exists($buyer_mark, $buyers_config)) {
    $acpa_key  = $buyers_config[$buyer_mark]['key'];
    $acpa_flow = $buyers_config[$buyer_mark]['flow'];
    $current_buyer_name = $buyer_mark;
} else {
    $acpa_key  = $buyers_config[$default_buyer]['key'] ?? '';
    $acpa_flow = $buyers_config[$default_buyer]['flow'] ?? '';
    $current_buyer_name = $default_buyer . " (Default/No mark)";
}

$acpa_url = "https://{$acpa_domain}/api/wm/push.json?id={$acpa_key}";
$acpa_data = [
    'offer'         => $acpa_offer_id,
    'flow'          => $acpa_flow,
    'ip'            => $ip,
    'name'          => $fname,
    'last'          => $lname,
    'phone'         => $phone,
    'email'         => $email,
    'ua'            => $ua,
    'utm_source'    => $placement,
    'utm_campaign'  => $campaign_name,
    'utm_content'   => $adset_name,
    'utm_term'      => $ad_name,
    // BINOM_ACPA_UTM_ALIASES
    'source'        => $placement,
    'campaign_name' => $campaign_name,
    'adset_name'    => $adset_name,
    'ad_name'       => $ad_name,
    'sub1'          => $subid,
    'sub4'          => 'fb',
    'sub5'          => $acpa_sub5,
    'country'       => $acpa_country,
];

$ch = curl_init($acpa_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($acpa_data));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$acpa_response = curl_exec($ch);
curl_close($ch);

$safe_acpa_response = htmlspecialchars(trim($acpa_response ?: 'No response'));
$tg_text = "🔥 <b>New Lead!</b>\n\n"
         . "👤 <b>Buyer:</b> {$current_buyer_name}\n"
         . "📦 <b>Offer:</b> {$acpa_offer_name}{$success_label}\n"
         . "🌐 <b>Domain:</b> {$domain}\n"
         . "--------------------------------\n"
         . "📝 <b>Name:</b> " . htmlspecialchars(trim($fname . ' ' . $lname)) . "\n"
         . "📞 <b>Phone:</b> " . htmlspecialchars($phone) . "\n"
         . "✉️ <b>Email:</b> " . htmlspecialchars($email) . "\n"
         . "💻 <b>IP:</b> {$ip}\n"
         . "--------------------------------\n"
         . "📊 <b>Campaign:</b> " . htmlspecialchars($campaign_name) . "\n"
         . "📍 <b>Placement:</b> " . htmlspecialchars($placement) . "\n"
         . "🔗 <b>SubID:</b> " . htmlspecialchars($subid) . "\n"
         . "--------------------------------\n"
         . "🔑 <i>Flow ID:</i> {$acpa_flow}\n"
         . "🐛 <b>Debug:</b> <code>{$safe_acpa_response}</code>";

@file_get_contents("https://api.telegram.org/bot{$tg_token}/sendMessage?chat_id={$tg_chat_id}&parse_mode=html&text=" . urlencode($tg_text));

// --- BINOM_LEAD_POSTBACK (do not remove) ---
// On tracker host this file lives at landers/_shared/binom_lead_postback.php
$__pb = __DIR__ . '/_shared/binom_lead_postback.php';
if (!is_file($__pb)) {
    $__pb = '/app/public/landers/_shared/binom_lead_postback.php';
}
if (is_file($__pb)) {
    require_once $__pb;
}

if (!empty($subid) && $subid != '{subid}' && strlen($subid) > 5) {
    $keitaro_url = "http://{$keitaro_ip}/{$keitaro_path}/postback?subid=" . urlencode($subid) . "&status=lead&payout=0";
    $ch_k = curl_init();
    curl_setopt($ch_k, CURLOPT_URL, $keitaro_url);
    curl_setopt($ch_k, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_k, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch_k, CURLOPT_FOLLOWLOCATION, true);
    @curl_exec($ch_k);
    curl_close($ch_k);
}

$redirect_url = "/landers/{$lander_slug}/success.php?pixel=" . urlencode($pixel) . "&project=" . urlencode($project_name);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status'      => 'success',
    'redirectUrl' => $redirect_url,
    'redirect'    => $redirect_url,
]);
exit;
