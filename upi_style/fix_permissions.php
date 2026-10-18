<?php
/**
 * fix_permissions.php — One-time permission fixer for cPanel
 * 
 * HOW TO USE:
 * 1. Upload this file to your website root (public_html)
 * 2. Open in browser: https://yoursite.com/fix_permissions.php
 * 3. DELETE this file after running it!
 *
 * Standard cPanel permissions:
 *   Folders  → 755 (rwxr-xr-x)
 *   PHP/HTML → 644 (rw-r--r--)
 *   Config   → 644
 *   .htaccess→ 644
 */

// Simple security: only run if accessed with the right key
// Change 'CHANGE_ME_123' to something secret before uploading
define('SECRET_KEY', 'CHANGE_ME_123');

if (!isset($_GET['key']) || $_GET['key'] !== SECRET_KEY) {
    http_response_code(403);
    die('<h2 style="color:red;">403 Forbidden — Add ?key=CHANGE_ME_123 to the URL</h2>');
}

$root = __DIR__;
$fixed_dirs  = 0;
$fixed_files = 0;
$errors      = [];
$log         = [];

// Folders to SKIP (don't touch these)
$skip_dirs = ['.git', 'node_modules', 'vendor', '.svn'];

// Recursively fix permissions
function fixPermissions($path, &$fixed_dirs, &$fixed_files, &$errors, &$log, $skip_dirs) {
    $items = scandir($path);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $full = $path . DIRECTORY_SEPARATOR . $item;
        $rel  = ltrim(str_replace(__DIR__, '', $full), DIRECTORY_SEPARATOR);
        
        if (is_dir($full)) {
            // Skip blacklisted directories
            if (in_array($item, $skip_dirs)) {
                $log[] = "⏭️ SKIPPED dir: $rel";
                continue;
            }
            
            // Set folder to 755
            if (@chmod($full, 0755)) {
                $fixed_dirs++;
                $log[] = "📁 755 → $rel/";
            } else {
                $errors[] = "❌ Cannot chmod dir: $rel/";
            }
            
            // Recurse
            fixPermissions($full, $fixed_dirs, $fixed_files, $errors, $log, $skip_dirs);
            
        } elseif (is_file($full)) {
            // Sensitive config files → 644 (never 777)
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            
            if (in_array($ext, ['php', 'html', 'htm', 'css', 'js', 'json', 'xml', 'txt', 'sql', 'md', 'htaccess', ''])) {
                $perm = 0644;
                $perm_label = '644';
            } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'mp4', 'webm', 'pdf', 'zip'])) {
                $perm = 0644;
                $perm_label = '644';
            } elseif ($ext === 'sh') {
                // Shell scripts need execute bit
                $perm = 0755;
                $perm_label = '755';
            } else {
                $perm = 0644;
                $perm_label = '644';
            }
            
            // Special: .htaccess, config files
            if ($item === '.htaccess' || $item === '.env') {
                $perm = 0644;
                $perm_label = '644';
            }
            
            if (@chmod($full, $perm)) {
                $fixed_files++;
                $log[] = "📄 $perm_label → $rel";
            } else {
                $errors[] = "❌ Cannot chmod file: $rel";
            }
        }
    }
}

// Run it
fixPermissions($root, $fixed_dirs, $fixed_files, $errors, $log, $skip_dirs);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NextPay — Fix Permissions</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: monospace; background: #0d121f; color: #e0e8f0; padding: 30px; }
  h1 { font-size: 22px; color: #2395ee; margin-bottom: 8px; }
  .subtitle { color: #8c9cb5; font-size: 13px; margin-bottom: 24px; }
  .summary { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
  .badge { padding: 12px 20px; border-radius: 8px; font-weight: bold; font-size: 14px; }
  .badge.green  { background: rgba(46,204,113,0.15); border: 1px solid rgba(46,204,113,0.3); color: #2ecc71; }
  .badge.blue   { background: rgba(35,149,238,0.15); border: 1px solid rgba(35,149,238,0.3); color: #2395ee; }
  .badge.red    { background: rgba(231,76,60,0.15);  border: 1px solid rgba(231,76,60,0.3);  color: #e74c3c; }
  .log-box { background: #161d31; border: 1px solid #242b38; border-radius: 8px; padding: 16px; max-height: 500px; overflow-y: auto; }
  .log-item { font-size: 12px; padding: 2px 0; color: #a9b7d0; border-bottom: 1px solid rgba(255,255,255,0.03); }
  .log-item.err { color: #e74c3c; }
  .warning { background: rgba(231,76,60,0.1); border: 1px solid rgba(231,76,60,0.3); border-radius: 8px; padding: 16px; margin-top: 20px; color: #e74c3c; font-size: 13px; font-weight: bold; }
</style>
</head>
<body>

<h1>✅ NextPay — Permissions Fixed!</h1>
<p class="subtitle">All folders set to 755 · All files set to 644 · cPanel compatible</p>

<div class="summary">
    <div class="badge blue">📁 Folders Fixed: <?= $fixed_dirs ?></div>
    <div class="badge green">📄 Files Fixed: <?= $fixed_files ?></div>
    <?php if (count($errors) > 0): ?>
    <div class="badge red">❌ Errors: <?= count($errors) ?></div>
    <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
<div class="log-box" style="margin-bottom: 16px;">
    <div style="color:#e74c3c; font-weight: bold; margin-bottom: 8px;">Errors (usually owner mismatch — ignore these):</div>
    <?php foreach ($errors as $e): ?>
        <div class="log-item err"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="log-box">
    <div style="color:#2395ee; font-weight: bold; margin-bottom: 8px;">Full Log (<?= count($log) ?> items):</div>
    <?php foreach ($log as $line): ?>
        <div class="log-item"><?= htmlspecialchars($line) ?></div>
    <?php endforeach; ?>
</div>

<div class="warning">
    ⚠️ IMPORTANT: Delete this file immediately after use!<br>
    Run: <code>rm /home/youraccount/public_html/fix_permissions.php</code><br>
    OR delete via cPanel File Manager.
</div>

</body>
</html>

