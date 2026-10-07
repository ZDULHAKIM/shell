<?php
/**
 * System Maintenance Tool - File Extractor
 * @version 10.0 - Auto Flatten All Modes
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__));
}

$start_time = microtime(TRUE);

class FileExtractor {
    public $work_dir = '.';
    public $archives = array();
    public static $process_status = '';
    public static $delete_status = '';
    public static $self_delete_status = '';
    private $allowed_extensions = array('zip', 'gz', 'tar', 'tar.gz');
    private $script_name = '';

    public function __construct() {
        $this->script_name = basename($_SERVER['PHP_SELF']);
        $this->scanArchives();
        $this->handleRequest();
    }

    private function scanArchives() {
        if ($handle = opendir($this->work_dir)) {
            while (($item = readdir($handle)) !== FALSE) {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                $name = strtolower($item);
                if (in_array($ext, $this->allowed_extensions) || 
                    (strpos($name, '.tar.gz') !== false) ||
                    (strpos($name, '.tgz') !== false)) {
                    $this->archives[] = $item;
                }
            }
            closedir($handle);

            if(!empty($this->archives)) {
                self::$process_status = '<span style="color:#27ae60;">✓ ' . count($this->archives) . ' archive(s) ready for extraction</span>';
            } else {
                self::$process_status = '<span style="color:#e74c3c;">✗ No archive files found (.zip, .gz, .tar, .tar.gz)</span>';
            }
        }
    }

    private function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_file']) && isset($_POST['action']) && $_POST['action'] === 'extract') {
            $selected = strip_tags($_POST['archive_file']);
            $extract_mode = isset($_POST['extract_mode']) ? $_POST['extract_mode'] : 'here';
            $custom_folder = isset($_POST['custom_folder']) ? trim(strip_tags($_POST['custom_folder'])) : '';
            
            if (in_array($selected, $this->archives)) {
                $this->extractArchive($selected, $extract_mode, $custom_folder);
            } else {
                self::$process_status = '<span style="color:#e74c3c;">✗ Invalid file selected</span>';
            }
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file']) && isset($_POST['action']) && $_POST['action'] === 'delete') {
            $selected = strip_tags($_POST['delete_file']);
            if (in_array($selected, $this->archives)) {
                $this->deleteZipFile($selected);
            } else {
                self::$delete_status = '<span style="color:#e74c3c;">✗ Invalid file selected</span>';
            }
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_script') {
            $this->deleteScript();
        }
    }

    private function extractArchive($archive, $extract_mode, $custom_folder) {
        $ext = strtolower(pathinfo($archive, PATHINFO_EXTENSION));
        $name = strtolower($archive);

        if (strpos($name, '.tar.gz') !== false || strpos($name, '.tgz') !== false) {
            $this->extractTarGz($archive, $extract_mode, $custom_folder);
        } elseif ($ext === 'zip') {
            $this->extractZip($archive, $extract_mode, $custom_folder);
        } elseif ($ext === 'gz') {
            $this->extractGz($archive, $extract_mode, $custom_folder);
        } elseif ($ext === 'tar') {
            $this->extractTar($archive, $extract_mode, $custom_folder);
        } else {
            self::$process_status = '<span style="color:#e74c3c;">✗ Unsupported archive format</span>';
        }
    }

    private function extractZip($archive, $extract_mode, $custom_folder) {
        if(!class_exists('ZipArchive')) {
            self::$process_status = '<span style="color:#e74c3c;">✗ ZipArchive not available on this server</span>';
            return;
        }

        $zip = new ZipArchive;
        $destination = $this->work_dir;
        
        // Tentukan BASE path ekstraksi
        $base_path = $destination;
        $is_custom_folder = false;
        
        if ($extract_mode === 'folder' && !empty($custom_folder)) {
            $base_path = rtrim($destination, '/') . '/' . trim($custom_folder, '/');
            $is_custom_folder = true;
            if (!is_dir($base_path)) {
                mkdir($base_path, 0755, true);
            }
        }

        if ($zip->open($archive) === TRUE) {
            if(!is_writable($base_path . '/') && $base_path !== '.') {
                self::$process_status = '<span style="color:#e74c3c;">✗ Cannot write to ' . htmlspecialchars($base_path) . '</span>';
                $zip->close();
                return;
            }
            
            // Cari root folder di dalam zip
            $root_folders = array();
            $has_root = false;
            $root_name = '';
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                $clean = rtrim($entry, '/');
                $parts = explode('/', $clean);
                if (count($parts) > 1 && !empty($parts[0])) {
                    $root = $parts[0];
                    if (!in_array($root, $root_folders)) {
                        $root_folders[] = $root;
                    }
                    $has_root = true;
                    $root_name = $root;
                }
            }
            
            // ============================================================
            // AUTO FLATTEN - HILANGKAN FOLDER UTAMA UNTUK SEMUA MODE
            // ============================================================
            if ($has_root) {
                $count = 0;
                
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->getNameIndex($i);
                    if (substr($entry, -1) === '/') continue;
                    
                    // Hapus root folder dari path
                    $new_path = preg_replace('/^' . preg_quote($root_name, '/') . '\//', '', $entry);
                    if (empty($new_path)) continue;
                    
                    // Buat direktori jika perlu
                    $dir = dirname($new_path);
                    if ($dir !== '.' && !empty($dir)) {
                        $full_dir = rtrim($base_path, '/') . '/' . $dir;
                        if (!is_dir($full_dir)) {
                            mkdir($full_dir, 0755, true);
                        }
                    }
                    
                    $target = rtrim($base_path, '/') . '/' . $new_path;
                    $content = $zip->getFromName($entry);
                    if ($content !== false) {
                        file_put_contents($target, $content);
                        $count++;
                    }
                }
                $zip->close();
                
                $location = ($base_path === $destination) ? 'root directory' : 'folder <strong>' . htmlspecialchars($custom_folder) . '</strong>';
                self::$process_status = '<span style="color:#27ae60;">✓ Extract success! ' . $count . ' file(s) to ' . $location . ' (auto flatten - tanpa folder ' . htmlspecialchars($root_name) . ')</span>';
                return;
            }
            
            // ============================================================
            // EXTRACT NORMAL (Jika tidak ada root folder)
            // ============================================================
            $zip->extractTo($base_path);
            $zip->close();
            
            $total_files = $this->countExtractedFiles($base_path);
            $location = ($base_path === $destination) ? 'root directory' : '<strong>' . htmlspecialchars($custom_folder) . '</strong>';
            self::$process_status = '<span style="color:#27ae60;">✓ Successfully extracted ' . $total_files . ' file(s) to ' . $location . '</span>';
            
        } else {
            self::$process_status = '<span style="color:#e74c3c;">✗ Failed to open archive file</span>';
        }
    }

    private function extractGz($archive, $extract_mode, $custom_folder) {
        if(!function_exists('gzopen')) {
            self::$process_status = '<span style="color:#e74c3c;">✗ Gzip support not enabled on this server</span>';
            return;
        }

        $destination = $this->work_dir;
        $filename = pathinfo($archive, PATHINFO_FILENAME);
        
        $base_path = $destination;
        
        if ($extract_mode === 'folder' && !empty($custom_folder)) {
            $base_path = rtrim($destination, '/') . '/' . trim($custom_folder, '/');
            if (!is_dir($base_path)) {
                mkdir($base_path, 0755, true);
            }
        }
        
        $target_path = rtrim($base_path, '/') . '/' . $filename;
        
        $gz_handle = gzopen($archive, "rb");
        $file_handle = fopen($target_path, "w");

        if ($gz_handle && $file_handle) {
            while ($chunk = gzread($gz_handle, 4096)) {
                fwrite($file_handle, $chunk, strlen($chunk));
            }
            gzclose($gz_handle);
            fclose($file_handle);

            if(file_exists($target_path)) {
                $location = ($base_path === $destination) ? 'root directory' : '<strong>' . htmlspecialchars($custom_folder) . '</strong>';
                self::$process_status = '<span style="color:#27ae60;">✓ Successfully extracted: ' . htmlspecialchars($filename) . ' to ' . $location . '</span>';
            } else {
                self::$process_status = '<span style="color:#e74c3c;">✗ Extraction failed</span>';
            }
        } else {
            self::$process_status = '<span style="color:#e74c3c;">✗ Cannot process gzip file</span>';
        }
    }

    private function extractTar($archive, $extract_mode, $custom_folder) {
        if(!class_exists('PharData')) {
            self::$process_status = '<span style="color:#e74c3c;">✗ PharData not available on this server</span>';
            return;
        }

        $destination = $this->work_dir;
        $base_path = $destination;
        
        if ($extract_mode === 'folder' && !empty($custom_folder)) {
            $base_path = rtrim($destination, '/') . '/' . trim($custom_folder, '/');
            if (!is_dir($base_path)) {
                mkdir($base_path, 0755, true);
            }
        }

        try {
            $tar = new PharData($archive);
            $tar->extractTo($base_path);
            $total_files = $this->countExtractedFiles($base_path);
            $location = ($base_path === $destination) ? 'root directory' : '<strong>' . htmlspecialchars($custom_folder) . '</strong>';
            self::$process_status = '<span style="color:#27ae60;">✓ Successfully extracted ' . $total_files . ' file(s) to ' . $location . '</span>';
        } catch (Exception $e) {
            self::$process_status = '<span style="color:#e74c3c;">✗ Failed to extract tar: ' . $e->getMessage() . '</span>';
        }
    }

    private function extractTarGz($archive, $extract_mode, $custom_folder) {
        if(!class_exists('PharData')) {
            self::$process_status = '<span style="color:#e74c3c;">✗ PharData not available on this server</span>';
            return;
        }

        $destination = $this->work_dir;
        $base_path = $destination;
        
        if ($extract_mode === 'folder' && !empty($custom_folder)) {
            $base_path = rtrim($destination, '/') . '/' . trim($custom_folder, '/');
            if (!is_dir($base_path)) {
                mkdir($base_path, 0755, true);
            }
        }

        try {
            $tar = new PharData($archive);
            $tar->extractTo($base_path);
            $total_files = $this->countExtractedFiles($base_path);
            $location = ($base_path === $destination) ? 'root directory' : '<strong>' . htmlspecialchars($custom_folder) . '</strong>';
            self::$process_status = '<span style="color:#27ae60;">✓ Successfully extracted ' . $total_files . ' file(s) to ' . $location . '</span>';
        } catch (Exception $e) {
            if (function_exists('shell_exec')) {
                $cmd = 'tar -xzf ' . escapeshellarg($archive) . ' -C ' . escapeshellarg($base_path) . ' 2>&1';
                $output = shell_exec($cmd);
                if (file_exists($base_path) && $this->countExtractedFiles($base_path) > 0) {
                    $total_files = $this->countExtractedFiles($base_path);
                    $location = ($base_path === $destination) ? 'root directory' : '<strong>' . htmlspecialchars($custom_folder) . '</strong>';
                    self::$process_status = '<span style="color:#27ae60;">✓ Successfully extracted ' . $total_files . ' file(s) to ' . $location . '</span>';
                } else {
                    self::$process_status = '<span style="color:#e74c3c;">✗ Failed to extract tar.gz: ' . htmlspecialchars($output) . '</span>';
                }
            } else {
                self::$process_status = '<span style="color:#e74c3c;">✗ Failed to extract tar.gz: ' . $e->getMessage() . '</span>';
            }
        }
    }

    private function deleteZipFile($zipFile) {
        $file_path = $this->work_dir . '/' . $zipFile;
        
        if (file_exists($file_path) && is_file($file_path)) {
            if (unlink($file_path)) {
                self::$delete_status = '<span style="color:#27ae60;">✓ Successfully deleted: ' . htmlspecialchars($zipFile) . '</span>';
                $this->archives = array_diff($this->archives, [$zipFile]);
                $this->archives = array_values($this->archives);
            } else {
                self::$delete_status = '<span style="color:#e74c3c;">✗ Failed to delete: ' . htmlspecialchars($zipFile) . '</span>';
            }
        } else {
            self::$delete_status = '<span style="color:#e74c3c;">✗ File not found: ' . htmlspecialchars($zipFile) . '</span>';
        }
    }

    private function deleteScript() {
        $script_path = __FILE__;
        
        if (file_exists($script_path)) {
            self::$self_delete_status = '<span style="color:#27ae60; font-weight:bold;">✓ Script will be deleted...</span>';
            register_shutdown_function(function() use ($script_path) {
                if (file_exists($script_path)) {
                    @unlink($script_path);
                }
            });
        } else {
            self::$self_delete_status = '<span style="color:#e74c3c;">✗ Script file not found</span>';
        }
    }

    private function countExtractedFiles($dir) {
        $count = 0;
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    $count++;
                }
            }
        }
        return $count;
    }
}

$extractor = new FileExtractor;
$execution_time = microtime(TRUE) - $start_time;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Extractor</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f0f2f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            padding: 30px 20px;
            line-height: 1.5;
        }
        .container { max-width: 800px; margin: 0 auto; }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
        }
        .card-header h1 { font-size: 24px; font-weight: 600; margin-bottom: 5px; }
        .card-header p { opacity: 0.9; font-size: 14px; }
        .card-body { padding: 30px; }
        
        .note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #856404;
        }
        .warning {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #721c24;
        }
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }
        select, input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            background: #fff;
        }
        select:focus, input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        .radio-group {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            padding: 5px 0;
        }
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 0;
        }
        .radio-group input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover { background: #c82333; }
        
        .status {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .status-delete {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .status-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #7f8c8d;
            margin-bottom: 8px;
        }
        .status-content { color: #2c3e50; word-break: break-word; }
        
        .info-bar {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 8px;
            font-size: 13px;
            color: #2c3e50;
            margin-top: 15px;
        }
        .info-bar div { margin-bottom: 3px; }
        
        .archive-list {
            background: #f7f9fc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            max-height: 300px;
            overflow-y: auto;
        }
        .archive-item {
            padding: 10px;
            border-bottom: 1px solid #e1e8ed;
            font-size: 14px;
            font-family: monospace;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .archive-item:last-child { border-bottom: none; }
        .archive-name { display: flex; align-items: center; gap: 8px; }
        .btn-small {
            padding: 4px 12px;
            font-size: 12px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-small:hover { background: #c82333; }
        .text-muted { color: #95a5a6; font-size: 12px; }
        .divider { border-top: 1px solid #eee; margin: 20px 0; }
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 15px; color: #2c3e50; }
        
        .badge-auto {
            display: inline-block;
            background: #17a2b8;
            color: white;
            padding: 1px 12px;
            border-radius: 12px;
            font-size: 10px;
            margin-left: 5px;
        }
        
        #customFolderGroup {
            display: none;
        }
        
        @media (max-width: 600px) {
            .card-body { padding: 20px; }
            .card-header h1 { font-size: 20px; }
            .archive-item { flex-direction: column; gap: 10px; align-items: flex-start; }
            .radio-group { gap: 15px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h1>📦 File Extractor</h1>
            <p>Extract archive files - Auto Flatten</p>
        </div>
        
        <div class="card-body">
            <div class="note">
                💡 <strong>Auto Flatten:</strong> Semua file akan diekstrak LANGSUNG tanpa folder utama!
                <br>• <strong>Extract Here</strong> → Isi ZIP langsung ke root directory (tanpa folder)
                <br>• <strong>Extract to Folder</strong> → Isi ZIP langsung ke folder custom (tanpa folder di dalamnya)
            </div>
            
            <?php if (!empty($extractor->archives)): ?>
                <div class="section-title">📁 Available Archives</div>
                <div class="archive-list">
                    <?php foreach ($extractor->archives as $archive): 
                        $size = file_exists($archive) ? round(filesize($archive) / 1024, 2) : 0;
                    ?>
                        <div class="archive-item">
                            <div class="archive-name">
                                📦 <?php echo htmlspecialchars($archive); ?>
                                <?php if($size > 0): ?>
                                    <span class="text-muted">(<?php echo $size; ?> KB)</span>
                                <?php endif; ?>
                            </div>
                            <form method="POST" style="margin:0;" onsubmit="return confirm('Delete <?php echo addslashes($archive); ?>?')">
                                <input type="hidden" name="delete_file" value="<?php echo htmlspecialchars($archive); ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn-small">🗑️ Delete</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="note" style="background:#e8f4f8; color:#2c3e50;">
                    📂 No archive files found in current directory.
                </div>
            <?php endif; ?>
            
            <div class="divider"></div>
            
            <!-- ============================================================
            EXTRACT FORM - SIMPLE
            ============================================================ -->
            <div class="section-title">📤 Extract Archive</div>
            <form method="POST" action="">
                <!-- Pilih File -->
                <div class="form-group">
                    <label>Select Archive:</label>
                    <select name="archive_file" required>
                        <option value="">-- Select file --</option>
                        <?php foreach ($extractor->archives as $archive): ?>
                            <option value="<?php echo htmlspecialchars($archive); ?>"><?php echo htmlspecialchars($archive); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Mode Extract -->
                <div class="form-group">
                    <label>Extract Mode:</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="extract_mode" value="here" checked onclick="toggleFolder()">
                            📂 Extract Here <span class="badge-auto">Auto Flatten</span>
                        </label>
                        <label>
                            <input type="radio" name="extract_mode" value="folder" onclick="toggleFolder()">
                            📁 Extract to Folder <span class="badge-auto">Auto Flatten</span>
                        </label>
                    </div>
                </div>
                
                <!-- Custom Folder -->
                <div class="form-group" id="customFolderGroup">
                    <label>Folder Name:</label>
                    <input type="text" name="custom_folder" placeholder="contoh: awawawa" value="">
                    <div style="color:#95a5a6;font-size:12px;margin-top:5px;">
                        📁 Isi ZIP akan langsung masuk ke folder ini (tanpa folder di dalamnya)
                    </div>
                </div>
                
                <input type="hidden" name="action" value="extract">
                <button type="submit" class="btn btn-primary" style="margin-top:5px;">🚀 Extract</button>
            </form>
            
            <!-- Status -->
            <?php if ($extractor::$process_status && $extractor::$process_status != '<span style="color:#27ae60;">✓ ' . count($extractor->archives) . ' archive(s) ready for extraction</span>'): ?>
                <div class="status">
                    <div class="status-label">Extract Status</div>
                    <div class="status-content"><?php echo $extractor::$process_status; ?></div>
                </div>
            <?php endif; ?>
            
            <?php if ($extractor::$delete_status): ?>
                <div class="status status-delete">
                    <div class="status-label">Delete Status</div>
                    <div class="status-content"><?php echo $extractor::$delete_status; ?></div>
                </div>
            <?php endif; ?>
            
            <div class="divider"></div>
            
            <!-- Danger Zone -->
            <div class="section-title">⚠️ Danger Zone</div>
            <div class="warning">
                ⚠️ <strong>Warning:</strong> Menghapus script ini akan membuat Anda tidak bisa mengakses tool ini lagi.
            </div>
            
            <form method="POST" action="" onsubmit="return confirm('⚠️ PERINGATAN!⚠️\n\nYakin ingin menghapus script ini?')">
                <input type="hidden" name="action" value="delete_script">
                <button type="submit" class="btn btn-danger">🗑️ Delete This Tool</button>
            </form>
            
            <?php if ($extractor::$self_delete_status): ?>
                <div class="status status-delete" style="margin-top:15px;">
                    <div class="status-label">Self Delete Status</div>
                    <div class="status-content"><?php echo $extractor::$self_delete_status; ?></div>
                </div>
            <?php endif; ?>
            
            <div class="info-bar">
                <div>⏱️ Execution time: <?php echo round($execution_time * 1000, 2); ?> ms</div>
                <div>📂 Current directory: <?php echo realpath($extractor->work_dir); ?></div>
                <div>🔒 PHP Version: <?php echo PHP_VERSION; ?></div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleFolder() {
    var mode = document.querySelector('input[name="extract_mode"]:checked');
    var folderGroup = document.getElementById('customFolderGroup');
    
    if (mode.value === 'folder') {
        folderGroup.style.display = 'block';
    } else {
        folderGroup.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleFolder();
});
</script>
</body>
</html>
