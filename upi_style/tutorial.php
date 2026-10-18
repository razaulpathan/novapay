<?php
// tutorial.php - Video tutorials page
require_once 'db.php';
requireLogin();

$support_link = getSetting('support_telegram_vip') ?: 'https://t.me/NextPay_vip';

// Convert regular YouTube URL to embed URL if needed
function makeEmbedUrl($url) {
    if (empty($url)) return '';
    if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $url, $m))
        return 'https://www.youtube.com/embed/' . $m[1] . '?autoplay=0&rel=0';
    if (preg_match('/youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/', $url, $m))
        return 'https://www.youtube.com/embed/' . $m[1] . '?autoplay=0&rel=0';
    return $url;
}

// Load videos from new table (with fallback to old settings key)
$tutorial_videos = [];
try {
    $stmt = $pdo->query("SELECT * FROM tutorial_videos WHERE is_active = 1 ORDER BY sort_order ASC, created_at ASC");
    $tutorial_videos = $stmt->fetchAll();
} catch (PDOException $e) { /* table may not exist yet */ }

// Fallback to single legacy setting
if (empty($tutorial_videos)) {
    $legacy_url   = getSetting('tutorial_video_url') ?: '';
    $legacy_title = getSetting('tutorial_video_title') ?: 'Platform Tutorial';
    if ($legacy_url) {
        $tutorial_videos = [[
            'id'          => 0,
            'title'       => $legacy_title,
            'description' => '',
            'video_url'   => $legacy_url,
            'thumbnail'   => '',
        ]];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NextPay - Tutorial Video</title>
  <style>
    * {
      box-sizing: border-box;
    }

    :root {
      --blue: #2395ee;
      --deep-blue: #07588f;
      --soft-blue: #f8fcff;
      --line: #e8f1f7;
      --muted: #b8c0c8;
      --bg-color: #f7f9fc;
      --text-dark: #333333;
    }

    body {
      margin: 0;
      min-height: 100vh;
      background: #eef2f5;
      color: var(--text-dark);
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      display: flex;
      justify-content: center;
      align-items: flex-start;
    }

    .screen {
      width: min(100%, 574px);
      min-height: 100vh;
      background: #ffffff;
      position: relative;
      padding-bottom: 80px; /* Space for fixed bottom nav */
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
      display: flex;
      flex-direction: column;
    }

    /* Header styling */
    .header {
      height: 56px;
      display: flex;
      align-items: center;
      padding: 0 16px;
      border-bottom: 1px solid #f5f7fa;
      position: sticky;
      top: 0;
      background: #ffffff;
      z-index: 10;
    }

    .header-back {
      color: #2c3e50;
      text-decoration: none;
      display: flex;
      align-items: center;
      cursor: pointer;
    }

    .header-back svg {
      width: 24px;
      height: 24px;
    }

    .header-title {
      font-size: 18px;
      font-weight: 800;
      color: #2c3e50;
      margin: 0 auto;
      transform: translateX(-12px); /* Centering offset */
    }

    .content {
      padding: 16px;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    /* Video Player Container */
    .video-player-container {
      width: 100%;
      aspect-ratio: 16 / 9;
      background: linear-gradient(135deg, #2c3e50 0%, #0f171e 100%);
      border-radius: 12px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    /* Big Center Play Overlay */
    .play-overlay {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 70px;
      height: 70px;
      background: rgba(255, 255, 255, 0.85);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: 0 4px 10px rgba(0,0,0,0.2);
      transition: transform 0.2s, background-color 0.2s;
      z-index: 5;
    }

    .play-overlay:hover {
      transform: translate(-50%, -50%) scale(1.06);
      background: #ffffff;
    }

    .play-overlay:active {
      transform: translate(-50%, -50%) scale(0.96);
    }

    .play-overlay svg {
      width: 28px;
      height: 28px;
      color: #000000;
      margin-left: 4px; /* Align visual center of play arrow */
    }

    /* Video controls block at bottom */
    .video-controls {
      width: 100%;
      background: linear-gradient(0deg, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0) 100%);
      padding: 12px 16px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-top: auto;
      z-index: 4;
    }

    .control-top-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      color: #ffffff;
      font-size: 13px;
      font-weight: bold;
      font-family: Arial, sans-serif;
    }

    .time-display {
      opacity: 0.95;
    }

    .controls-right-side {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .control-icon {
      cursor: pointer;
      opacity: 0.85;
      display: flex;
      align-items: center;
      transition: opacity 0.2s;
    }

    .control-icon:hover {
      opacity: 1;
    }

    .control-icon svg {
      width: 20px;
      height: 20px;
    }

    /* Seekbar styling */
    .seekbar-container {
      width: 100%;
      height: 4px;
      background: rgba(255, 255, 255, 0.3);
      border-radius: 2px;
      position: relative;
      cursor: pointer;
    }

    .seekbar-progress {
      width: 15%; /* Mock progress */
      height: 100%;
      background: var(--blue);
      border-radius: 2px;
      position: relative;
    }

    .seekbar-handle {
      width: 12px;
      height: 12px;
      background: #ffffff;
      border-radius: 50%;
      position: absolute;
      right: -6px;
      top: -4px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }

    /* Floating Chat Button */
    .fab {
      position: absolute;
      bottom: 24px;
      right: 18px;
      width: 48px;
      height: 48px;
      background: #3a6bf6;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      box-shadow: 0 4px 12px rgba(58, 107, 246, 0.4);
      cursor: pointer;
      z-index: 10;
    }

    .fab svg {
      width: 24px;
      height: 24px;
    }
  </style>
</head>
<body>
  <div class="screen">
    
    <!-- Header title -->
    <header class="header">
      <a href="mine.php" class="header-back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </a>
      <h1 class="header-title">Tutorial Videos</h1>
    </header>

    <div class="content">

      <?php if (empty($tutorial_videos)): ?>
      <!-- No videos notice -->
      <div style="text-align:center; padding:40px 16px;">
        <div style="font-size:52px; margin-bottom:12px;">🎬</div>
        <h3 style="font-size:18px;font-weight:800;color:#2c3e50;margin:0 0 8px;">No Tutorial Videos Yet</h3>
        <p style="font-size:13px;color:#7f8c8d;margin:0;">Your admin hasn't added any tutorial videos yet.<br>Check back later or contact support.</p>
      </div>

      <?php else: ?>
      <!-- Video list -->
      <div style="display:flex;flex-direction:column;gap:20px;">
        <?php foreach ($tutorial_videos as $vid):
            $embed = makeEmbedUrl($vid['video_url']);
            $isYoutube = strpos($embed, 'youtube.com/embed') !== false;
        ?>
        <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,0.06);overflow:hidden;">
          <!-- Embedded Player -->
          <div style="width:100%;aspect-ratio:16/9;background:#0f171e;position:relative;overflow:hidden;">
            <?php if ($isYoutube): ?>
            <iframe src="<?= htmlspecialchars($embed) ?>"
              width="100%" height="100%"
              style="border:none;display:block;"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen></iframe>
            <?php elseif ($embed): ?>
            <video src="<?= htmlspecialchars($embed) ?>" controls
              style="width:100%;height:100%;background:#000;display:block;">
              Your browser does not support the video tag.
            </video>
            <?php else: ?>
            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#475569;font-size:36px;">🎬</div>
            <?php endif; ?>
          </div>

          <!-- Video info -->
          <div style="padding:14px 16px;">
            <h3 style="font-size:16px;font-weight:800;color:#1e293b;margin:0 0 4px;"><?= htmlspecialchars($vid['title']) ?></h3>
            <?php if (!empty($vid['description'])): ?>
            <p style="font-size:13px;color:#64748b;margin:0 0 10px;line-height:1.5;"><?= nl2br(htmlspecialchars($vid['description'])) ?></p>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($vid['video_url']) ?>" target="_blank"
               style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#2395ee;font-weight:700;text-decoration:none;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
              Open in YouTube
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Support Link -->
      <a href="<?= htmlspecialchars($support_link) ?>" target="_blank"
         style="display:block;text-align:center;padding:14px;background:#2395ee;color:#fff;border-radius:10px;text-decoration:none;font-size:15px;font-weight:700;margin-top:20px;">
        📞 Contact Customer Support
      </a>

    </div>

    <!-- Floating Chat Button -->
    <div class="fab" onclick="location.href='customer.php'">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        <circle cx="9" cy="10" r="1"></circle>
        <circle cx="13" cy="10" r="1"></circle>
        <circle cx="17" cy="10" r="1"></circle>
      </svg>
    </div>

  </div>
</body>
</html>

