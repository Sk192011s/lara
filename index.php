<?php
// --- CONFIGURATION ---
$ADMIN_PASSWORD = getenv('ADMIN_PASSWORD') ?: "mysecretpassword123";
$DEFAULT_R2_DOMAIN = "https://pub-xxx.r2.dev"; // R2 Domain ထည့်ပါ (နောက်ဆုံး / မပါရ)

// Link Parameters
$PARAM_KEY = "download";
$DOWNLOAD_FLAG = "dl";

// 1. AUTHENTICATION CHECK
if (!isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] !== 'admin' || 
    $_SERVER['PHP_AUTH_PW'] !== $ADMIN_PASSWORD) {
    header('WWW-Authenticate: Basic realm="Admin Access"');
    header('HTTP/1.0 401 Unauthorized');
    exit('Unauthorized');
}

// 2. PROXY LOGIC
if (isset($_GET[$PARAM_KEY])) {
    $targetUrl = $_GET[$PARAM_KEY];
    $shouldDownload = isset($_GET[$DOWNLOAD_FLAG]) && $_GET[$DOWNLOAD_FLAG] === 'true';

    // Smart URL Handling
    if (strpos($targetUrl, 'http') !== 0) {
        if (strpos($targetUrl, '/') !== 0) $targetUrl = '/' . $targetUrl;
        $targetUrl = $DEFAULT_R2_DOMAIN . $targetUrl;
    }

    $ch = curl_init();
    
    // Headers to send to R2
    $headers = [
        "User-Agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36"
    ];

    if (isset($_SERVER['HTTP_RANGE'])) {
        $headers[] = "Range: " . $_SERVER['HTTP_RANGE'];
    }

    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_ENCODING, ""); 

    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) {
        $len = strlen($header);
        $header = trim($header);
        if (empty($header)) return $len;
        $lowerHeader = strtolower($header);
        if (strpos($lowerHeader, 'content-type:') === 0 || 
            strpos($lowerHeader, 'content-length:') === 0 ||
            strpos($lowerHeader, 'content-range:') === 0 ||
            strpos($lowerHeader, 'accept-ranges:') === 0) {
            header($header);
        }
        return $len;
    });

    header("Access-Control-Allow-Origin: *");
    header("Accept-Ranges: bytes");

    if ($shouldDownload) {
        $filename = basename(parse_url($targetUrl, PHP_URL_PATH));
        header("Content-Disposition: attachment; filename=\"$filename\"");
    } else {
        header("Content-Disposition: inline");
    }

    curl_exec($ch);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode) http_response_code($httpCode);
    
    curl_close($ch);
    exit;
}
?>

<!DOCTYPE html>
<html><head><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Laravel Cloud Gen</title>
<style>body{padding:20px;font-family:sans-serif}input,button{width:100%;padding:12px;margin:10px 0}button{background:#007bff;color:#fff;border:none}</style></head><body>
<h3>Laravel Cloud Proxy</h3>
<input type="text" id="r2" placeholder="Filename">
<button onclick="gen()">Generate</button>
<input type="text" id="out">
<script>
    function gen() {
        const val = document.getElementById('r2').value.trim();
        // Current URL is the proxy endpoint
        const url = window.location.href.split('?')[0];
        document.getElementById('out').value = url + "?<?php echo $PARAM_KEY; ?>=" + encodeURIComponent(val);
    }
</script></body></html>
