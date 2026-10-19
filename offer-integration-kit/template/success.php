<?php
/**
 * BINOM success.php — fires FB Pixel Lead (+ PageView).
 * Pixel from ?pixel=… or _tk_pixel cookie / localStorage fallback.
 */
$projectName = isset($_GET['project']) ? htmlspecialchars($_GET['project']) : "Project";
$pixelId     = isset($_GET['pixel'])   ? htmlspecialchars($_GET['pixel'])   : "";
// Optional: set lang/dir per geo (ar/he/de/en…)
$html_lang = "ar";
$html_dir  = "rtl"; // use "ltr" for DE/EN
?>
<!doctype html>
<html lang="<?php echo $html_lang; ?>" dir="<?php echo $html_dir; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Thank you — request received</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Arabic', sans-serif;
            background-color: #0a0f1a;
            background-image:
                radial-gradient(circle at top left, rgba(8, 102, 255, 0.1) 0%, transparent 40%),
                radial-gradient(circle at bottom right, rgba(8, 102, 255, 0.05) 0%, transparent 50%);
            color: #fff;
        }
        .box-panel {
            background: rgba(20, 30, 50, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }
        .highlight { color: #0866FF; }
    </style>

    <?php if (!empty($pixelId) && $pixelId !== "{pixel}" && $pixelId !== "{external_id}" && preg_match('/^\d{5,20}$/', $pixelId)): ?>
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '<?php echo $pixelId; ?>');
        fbq('track', 'PageView');
        fbq('track', 'Lead');
    </script>
    <noscript>
        <img height="1" width="1" style="display:none"
             src="https://www.facebook.com/tr?id=<?php echo $pixelId; ?>&ev=Lead&noscript=1"/>
    </noscript>
    <?php endif; ?>

<script data-binom-fb-lead="1">
/* BINOM_FB_LEAD_FALLBACK — if ?pixel= missing, restore from _tk_pixel */
(function(){
  function readStore(k){
    try {
      var u = new URL(location.href);
      var fromUrl = u.searchParams.get(k);
      if (fromUrl && !/^\{.+\}$/.test(fromUrl)) return fromUrl;
    } catch (e) {}
    try {
      var ls = localStorage.getItem("_tk_" + k);
      if (ls && !/^\{.+\}$/.test(ls)) return ls;
    } catch (e) {}
    try {
      var m = document.cookie.match(new RegExp("(?:^|; )_tk_" + k + "=([^;]*)"));
      if (m) return decodeURIComponent(m[1]);
    } catch (e) {}
    return "";
  }
  var pixel = readStore("pixel");
  if (!pixel || !/^\d{5,20}$/.test(pixel)) return;
  if (window.fbq) return;
  !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
  n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,'script',
  'https://connect.facebook.net/en_US/fbevents.js');
  fbq('init', pixel);
  fbq('track', 'PageView');
  fbq('track', 'Lead');
})();
</script>
</head>
<body class="antialiased min-h-screen flex flex-col justify-center">
<main class="flex flex-col items-center justify-center p-4 sm:p-6 relative z-10">
  <div class="w-full max-w-3xl mx-auto text-center">
    <h1 class="text-4xl font-extrabold mb-4">Request received <span class="highlight">successfully</span></h1>
    <p class="text-lg text-gray-300">
      Your seat for <span class="font-bold text-white"><?php echo $projectName; ?></span> is reserved.
      A specialist will call you shortly.
    </p>
    <div class="box-panel p-8 mt-12 text-left">
      <p class="text-sm text-gray-400 mb-2">[ Next ]</p>
      <h3 class="text-xl font-bold text-[#0866FF] mb-2">Keep your phone nearby</h3>
      <p class="text-gray-300 text-sm">Answer unknown numbers — that is your personal manager.</p>
    </div>
  </div>
</main>
</body>
</html>
