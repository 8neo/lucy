<?php
// ─── Configuration ────────────────────────────────────────────────
$uploadFolder = __DIR__ . '/files';
$disallowedExtensions = [
    // Executables & Installers
    'exe', 'msi', 'bat', 'cmd', 'ps1', 'sh', 'bin', 'com', 'scr', 'pif', 'jar', 'apk', 'run', 'app',

    // Scripts & Code
    'php', 'asp', 'aspx', 'jsp', 'cgi', 'pl', 'py', 'rb', 'vb', 'vbs', 'wsf', 'js', 'ts', 'html', 'htm',
    'cs', 'java', 'c', 'cpp', 'h', 'hpp', 'swift', 'go', 'rs', 'asm',

    // Archives & Compressed
    'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'zst', 'cab', 'arj', 'ace', 'iso', 'dmg', 'lzma', 'zpaq', 'paq8', 'kgb',

    // Extensions & Add-ons
    'crx', 'xpi', 'oex', 'safariextz',

    // System & Config
    'sys', 'dll', 'ini', 'cfg', 'reg', 'drv', 'vxd', 'ocx', 'bak', 'sav', 'dat', 'tmp', 'log', 'ldb',

    // Dev/Project Files
    'sln', 'proj', 'csproj', 'vbproj', 'make', 'cmake', 'gradle', 'xcodeproj', 'workspace',

    // Databases
    'db', 'sqlite', 'sql', 'dbf', 'mdb', 'accdb', 'ndf', 'ldf',

    // Virtual Disks
    'vmdk', 'vdi', 'vhd', 'vhdx', 'qcow2', 'img', 'nrg', 'toast',

    // Other
    'eml', 'msg', 'torrent', 'ics', 'lnk', 'desktop', 'url',
];

$maxContentLength = 128 * 1024 * 1024; // 128MB
$uploadCooldownSeconds = 5;

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// ─── Quick Upload Entrypoint ──────────────────────────────────────
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_GET['upload']) || strtok($_SERVER['REQUEST_URI'], '?') === '/upload')
) {
    handleUpload();
}

// ─── Routing ──────────────────────────────────────────────────────
$requestUri = strtok($_SERVER['REQUEST_URI'], '?');

if (preg_match('#^/files/([\w\d\.\-_]+)$#', $requestUri, $m)) {
    serveFile($m[1]);
} elseif (preg_match('#^/static/(.*)$#', $requestUri, $m)) {
    serveStaticFile($m[1]);
} else {
    renderTemplate($requestUri);
}

// ─── Handlers ─────────────────────────────────────────────────────
function handleUpload()
{
    global $uploadFolder, $disallowedExtensions, $maxContentLength, $uploadCooldownSeconds;

    $ip = $_SERVER['REMOTE_ADDR'];
    $cooldownFile = sys_get_temp_dir() . "/cooldown_" . md5($ip);

    // Cooldown check
    if (file_exists($cooldownFile)) {
        $last = (int) file_get_contents($cooldownFile);
        $wait = $uploadCooldownSeconds - (time() - $last);
        if ($wait > 0) {
            jsonError("Please wait {$wait} seconds before uploading again.", 429);
        }
    }

    // File sanity check
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        jsonError("No file provided.", 400);
    }
    $file = $_FILES['file'];
    if ($file['size'] > $maxContentLength) {
        jsonError("File exceeds maximum allowed size.", 400);
    }
    $orig = $file['name'];
    if (!$orig) {
        jsonError("No selected file.", 400);
    }

    // Extension block
    if (isDisallowedExtension($orig)) {
        jsonError("File type not allowed.", 400);
    }

    // Save the file
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $newName = generateFilename($ext);
    $dst = "{$uploadFolder}/{$newName}";
    if (!move_uploaded_file($file['tmp_name'], $dst)) {
        jsonError("Failed to save the uploaded file.", 500);
    }

    // Cooldown stamp
    file_put_contents($cooldownFile, time());

    jsonResponse([
        "success" => true,
        "url" => "/files/{$newName}"
    ]);
}

function serveFile($fn)
{
    global $uploadFolder;
    $p = "{$uploadFolder}/{$fn}";
    if (!file_exists($p)) {
        http_response_code(404);
        echo "File not found";
        exit;
    }
    $mime = mime_content_type($p);
    header("Content-Type: {$mime}");
    readfile($p);
    exit;
}

function serveStaticFile($path)
{
    $full = __DIR__ . "/static/{$path}";
    if (!file_exists($full)) {
        http_response_code(404);
        echo "Static file not found";
        exit;
    }
    $mime = mime_content_type($full);
    header("Content-Type: {$mime}");
    readfile($full);
    exit;
}

function renderTemplate($page)
{
    $routes = ['/' => 'index', '/index' => 'index', '/faq' => 'faq', '/terms' => 'terms'];
    if (isset($routes[$page])) {
        $t = $routes[$page];
    } elseif (str_ends_with($page, '.html')) {
        $t = basename($page, '.html');
    } else {
        $t = basename($page);
    }
    $file = __DIR__ . "/{$t}.html";
    if (!file_exists($file)) {
        http_response_code(404);
        echo "404 Not Found";
        exit;
    }
    readfile($file);
    exit;
}

// ─── Helpers ─────────────────────────────────────────────────────
function generateFilename($ext)
{
    global $uploadFolder;
    do {
        $n = bin2hex(random_bytes(4));
        $f = "{$n}.{$ext}";
    } while (file_exists("{$uploadFolder}/{$f}"));
    return $f;
}

function jsonError($msg, $code = 400)
{
    http_response_code($code);
    jsonResponse(["error" => $msg]);
}

function jsonResponse($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function isDisallowedExtension($filename)
{
    global $disallowedExtensions;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $disallowedExtensions);
}


?>