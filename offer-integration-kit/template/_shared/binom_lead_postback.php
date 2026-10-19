<?php
/* On production tracker this posts to the Binom host IP. Keep as deployed on server. */
?>
<?php
/**
 * Binom lead conversion postback.
 * Safe to include from any lander send.php - does not touch AlterCPA/Telegram/CRM.
 * Sets $binom_pb_debug for optional TG debug lines.
 *
 * Priority (CRITICAL for multi-campaign domains):
 *   1) POST clickid / click_id / subid from the lander form
 *   2) $subid already resolved by send.php
 *   3) GET clickid
 *   4) cookie bcid LAST — bare-domain / DEFAULT hits can overwrite it
 */
if (!defined('BINOM_LEAD_POSTBACK_LOADED')) {
    define('BINOM_LEAD_POSTBACK_LOADED', true);

    $binom_pick = static function ($v) {
        $v = trim((string)$v);
        if ($v === '' || $v[0] === '{' || !preg_match('/^[A-Za-z0-9_-]{6,64}$/', $v)) {
            return '';
        }
        return $v;
    };

    $clickid = '';
    foreach ([
        $_POST['clickid'] ?? null,
        $_POST['click_id'] ?? null,
        $_POST['subid'] ?? null,
        $subid ?? null,
        $_GET['clickid'] ?? null,
        $_GET['subid'] ?? null,
        $_COOKIE['bcid'] ?? null,
    ] as $cand) {
        $clickid = $binom_pick($cand);
        if ($clickid !== '') {
            break;
        }
    }

    $binom_pb_debug = 'skip';
    if ($clickid !== '') {
        $binom_pb = 'http://158.94.211.56/click?cnv_id=' . urlencode($clickid) . '&cnv_status=lead&payout=0';
        $ch_b = curl_init();
        curl_setopt($ch_b, CURLOPT_URL, $binom_pb);
        curl_setopt($ch_b, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_b, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch_b, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch_b, CURLOPT_FOLLOWLOCATION, true);
        @curl_exec($ch_b);
        $binom_pb_code = (int)curl_getinfo($ch_b, CURLINFO_HTTP_CODE);
        $binom_pb_err = curl_error($ch_b);
        curl_close($ch_b);
        $binom_pb_debug = 'http=' . $binom_pb_code . ' id=' . $clickid . ($binom_pb_err ? (' err=' . $binom_pb_err) : '');
    }
}
