<?php
// tgbot/bot.php - Telegram Group Assistant Webhook Bot
require_once '../db.php';

// Fetch settings dynamically from the database
$bot_token  = getSetting('telegram_bot_token');
$bot_status = getSetting('telegram_bot_status'); // 1 = Active, 0 = Inactive/Off
$bot_secret = getSetting('telegram_bot_secret') ?: 'SECRET_BOT_TOKEN_HERE';

// Exit immediately if the bot is turned off or not configured
if ($bot_status !== '1' || empty($bot_token)) {
    header("HTTP/1.1 503 Service Unavailable");
    echo "Telegram Bot service is currently deactivated/turned off.";
    exit;
}

// Verify secret token from URL query parameters (webhook security)
if (!isset($_GET['token']) || $_GET['token'] !== $bot_secret) {
    header("HTTP/1.1 403 Forbidden");
    echo "Access denied: Invalid webhook token.";
    exit;
}

// Read raw POST data from Telegram API
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    exit;
}

// 1. Process Callback Queries (for Admin approval/rejection inline buttons)
if (isset($update["callback_query"])) {
    $callback_query = $update["callback_query"];
    $callback_id = $callback_query["id"];
    $from_chat_id = $callback_query["message"]["chat"]["id"];
    $message_id = $callback_query["message"]["message_id"];
    $data = $callback_query["data"];
    
    // Check if data matches approve_buy_ or reject_buy_
    if (strpos($data, 'approve_buy_') === 0 || strpos($data, 'reject_buy_') === 0) {
        $order_id = intval(substr($data, strrpos($data, '_') + 1));
        $action = (strpos($data, 'approve_buy_') === 0) ? 'approve' : 'reject';
        $status_msg = '';
        
        try {
            // Fetch buy order details
            $stmt = $pdo->prepare("SELECT * FROM buy_orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            
            if (!$order) {
                $status_msg = "Order not found.";
            } elseif ($order['status'] !== 'Pending') {
                $status_msg = "Already processed: " . $order['status'];
            } else {
                if ($action === 'approve') {
                    $pdo->beginTransaction();
                    
                    // Update order status to Success
                    $updateStmt = $pdo->prepare("UPDATE buy_orders SET status = 'Success' WHERE id = ?");
                    $updateStmt->execute([$order_id]);
                    
                    // Add itokens to user balance, and reward to user profit
                    $userStmt = $pdo->prepare("UPDATE users SET itoken_balance = itoken_balance + ?, today_profit = today_profit + ? WHERE id = ?");
                    $userStmt->execute([$order['itokens'], $order['reward'], $order['user_id']]);
                    
                    // Unconditionally trigger automatic token selling & Admin telegram alert
                    triggerAutoSell($pdo, $order_id);
                    
                    // Distribute multi-level commission to referrer chain
                    distributeMultiLevelCommission($order['user_id'], $order['price']);
                    
                    $pdo->commit();
                    $status_msg = "Approved ✅ (Tokens added & Auto-Sell placed)";
                } else {
                    // Update order status to Cancelled
                    $updateStmt = $pdo->prepare("UPDATE buy_orders SET status = 'Cancelled' WHERE id = ?");
                    $updateStmt->execute([$order_id]);
                    $status_msg = "Rejected ❌";
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $status_msg = "Error: " . $e->getMessage();
        }
        
        // Update the original message text on Telegram to reflect the decision
        $orig_text = $callback_query["message"]["text"];
        $updated_text = $orig_text . "\n\n📋 <b>Decision:</b> {$status_msg}";
        
        // Edit Message Text via Telegram API
        $url = "https://api.telegram.org/bot{$bot_token}/editMessageText";
        $post_fields = [
            'chat_id' => $from_chat_id,
            'message_id' => $message_id,
            'text' => $updated_text,
            'parse_mode' => 'HTML'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
    }
    exit;
}

// 2. Process regular group/chat messages
if (!isset($update["message"])) {
    exit;
}

$message = $update["message"];
$chat_id = $message["chat"]["id"];
$text = isset($message["text"]) ? trim($message["text"]) : '';

// Helper to post messages back to Telegram
function sendTelegramMessage($chat_id, $text, $token) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    
    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// Helper to mask phone numbers
function maskPhone($phone) {
    if (strlen($phone) >= 8) {
        return substr($phone, 0, 4) . '••••' . substr($phone, -2);
    }
    return '••••';
}

// Check for Welcome Event (New Chat Member)
if (isset($message["new_chat_members"])) {
    foreach ($message["new_chat_members"] as $new_member) {
        $first_name = htmlspecialchars($new_member["first_name"]);
        $welcome = "👋 <b>Welcome to NextPay Official Group, <a href='tg://user?id={$new_member['id']}'>{$first_name}</a>!</b>\n\n";
        $welcome .= "📈 Start earning money by selling tokens and inviting friends!\n";
        $welcome .= "🎥 View tutorial guides using <code>/tutorial</code> command.\n";
        $welcome .= "📣 Connect with online support desk via <code>/support</code> command.";
        sendTelegramMessage($chat_id, $welcome, $bot_token);
    }
    exit;
}

// Group Spam Guard Link Blocker
if (!empty($text)) {
    $group_chat_id = getSetting('telegram_group_chat_id');
    
    // Scan if the message is in the configured group chat
    if (!empty($group_chat_id) && strval($chat_id) === strval($group_chat_id)) {
        $is_spam = false;
        
        // Detect url patterns or invite links
        if (preg_match('/(https?:\/\/|www\.|t\.me)/i', $text)) {
            $is_spam = true;
        }
        
        if ($is_spam) {
            // Delete Message API
            $url = "https://api.telegram.org/bot{$bot_token}/deleteMessage";
            $post_fields = [
                'chat_id' => $chat_id,
                'message_id' => $message["message_id"]
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            curl_close($ch);
            exit;
        }
    }
}

  // Handle commands (both slash commands and plain text format as shown in screenshots)
$command = '';
$arg = '';

$clean_text = trim($text);
if (strpos($clean_text, '/') === 0) {
    $parts = explode(' ', $clean_text);
    $command = strtolower($parts[0]);
    $arg = isset($parts[1]) ? trim($parts[1]) : '';
} else {
    // Parse commands like: Uid 623898735, s 623898735, b 623898735
    $parts = preg_split('/\s+/', $clean_text, 2);
    $cmd_candidate = strtolower($parts[0]);
    $arg_candidate = isset($parts[1]) ? trim($parts[1]) : '';
    
    if ($cmd_candidate === 'uid' || $cmd_candidate === 's') {
        $command = '/s';
        $arg = $arg_candidate;
    } elseif ($cmd_candidate === 'b') {
        $command = '/b';
        $arg = $arg_candidate;
    }
}

if (!empty($command)) {
    switch ($command) {
        
        // s or Uid check - Payout & Account details check matching the screenshot
        case '/s':
        case '/uid':
            if (empty($arg) || !is_numeric($arg)) {
                sendTelegramMessage($chat_id, "❌ Usage: <code>Uid &lt;user_id&gt;</code> or <code>s &lt;user_id&gt;</code>\nExample: <code>Uid 623898735</code>", $bot_token);
            } else {
                $uid = intval($arg);
                $stmt = $pdo->prepare("SELECT phone, itoken_balance, order_min, order_max, status FROM users WHERE id = ?");
                $stmt->execute([$uid]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Fetch sum of paying sells
                    $stmtSells = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM sell_orders WHERE user_id = ? AND status = 'Paying'");
                    $stmtSells->execute([$uid]);
                    $selling_itokens = floatval($stmtSells->fetchColumn());
                    
                    // Fetch partner counts
                    $stmtUpi = $pdo->prepare("SELECT partner, status, stopped FROM upi_accounts WHERE user_id = ?");
                    $stmtUpi->execute([$uid]);
                    $upis = $stmtUpi->fetchAll();
                    
                    $linked_partners = [];
                    $locked_count = 0;
                    $unlink_count = 0;
                    
                    foreach ($upis as $u) {
                        if (intval($u['stopped']) === 1) {
                            $locked_count++;
                        }
                        if ($u['status'] === 'UnLink') {
                            $unlink_count++;
                        } else {
                            $linked_partners[] = strtolower($u['partner']);
                        }
                    }
                    
                    $partners = ['mobikwik', 'paytm', 'airtel', 'phonepe'];
                    $unlinked_list = [];
                    foreach ($partners as $p) {
                        if (!in_array($p, $linked_partners)) {
                            $unlinked_list[] = "{$p}(0)";
                        }
                    }
                    $unlinked_upi_count = 4 - count($linked_partners);
                    
                    // Calculate success rate
                    $stmtTotalSells = $pdo->prepare("SELECT COUNT(*) FROM sell_orders WHERE user_id = ?");
                    $stmtTotalSells->execute([$uid]);
                    $total_sells = intval($stmtTotalSells->fetchColumn());
                    
                    $stmtSuccessSells = $pdo->prepare("SELECT COUNT(*) FROM sell_orders WHERE user_id = ? AND status = 'Success'");
                    $stmtSuccessSells->execute([$uid]);
                    $success_sells = intval($stmtSuccessSells->fetchColumn());
                    
                    $success_rate = ($total_sells > 0) ? round(($success_sells / $total_sells) * 100) : 0;
                    
                    // Last sell order details
                    $stmtLastSell = $pdo->prepare("SELECT id, created_at FROM sell_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
                    $stmtLastSell->execute([$uid]);
                    $last_sell = $stmtLastSell->fetch();
                    
                    $last_sell_str = $last_sell ? "{$last_sell['id']}\nCreate Time: {$last_sell['created_at']}" : "None\nCreate Time: -";
                    
                    // Unusual sell orders (failed/cancelled)
                    $stmtUnusual = $pdo->prepare("SELECT id, amount, created_at FROM sell_orders WHERE user_id = ? AND status = 'Failed' ORDER BY created_at DESC LIMIT 3");
                    $stmtUnusual->execute([$uid]);
                    $unusuals = $stmtUnusual->fetchAll();
                    
                    $unusuals_str = '';
                    if (empty($unusuals)) {
                        $unusuals_str = "None";
                    } else {
                        foreach ($unusuals as $u) {
                            $unusuals_str .= "Order ID: #{$u['id']} | Amount: ₹{$u['amount']} | Date: {$u['created_at']}\n";
                        }
                    }
                    
                    // Build exact message response template matching user screenshot
                    $reply = "IToken: {$user['itoken_balance']}\n";
                    $reply .= "Selling IToken: {$selling_itokens}\n\n";
                    $reply .= "Min~Max Order Size: {$user['order_min']}~{$user['order_max']}\n";
                    $reply .= "Locked UPI: {$locked_count}\n";
                    $reply .= "UnLinked UPI: {$unlinked_upi_count}\n";
                    $reply .= "Please relink the following UPI immediately :\n";
                    $reply .= implode(",", $unlinked_list) . ",\n";
                    $reply .= "Rate(success): {$success_rate}%\n";
                    $reply .= "The success rate is very low. Please check whether the UPI is limited!\n";
                    $reply .= "The last sell order:\n";
                    $reply .= "{$last_sell_str}\n\n";
                    $reply .= "The unusual sell orders:\n";
                    $reply .= $unusuals_str;
                    
                    sendTelegramMessage($chat_id, $reply, $bot_token);
                } else {
                    sendTelegramMessage($chat_id, "❌ User with UID <code>{$uid}</code> not found.", $bot_token);
                }
            }
            break;
            
        // b check - Buy order pending & last purchase check matching screenshot
        case '/b':
            if (empty($arg) || !is_numeric($arg)) {
                sendTelegramMessage($chat_id, "❌ Usage: <code>b &lt;user_id&gt;</code>\nExample: <code>b 623898735</code>", $bot_token);
            } else {
                $uid = intval($arg);
                $stmtPending = $pdo->prepare("SELECT order_no, price FROM buy_orders WHERE user_id = ? AND status = 'Pending' LIMIT 1");
                $stmtPending->execute([$uid]);
                $pending = $stmtPending->fetch();
                
                if ($pending) {
                    $reply = "📥 Pending purchase order:\n";
                    $reply .= "Order No: {$pending['order_no']}\n";
                    $reply .= "Order Amount: {$pending['price']}";
                } else {
                    $reply = "You have no pending orders. Please make a purchase first !\n\n";
                    
                    // Fetch last purchase order (Success or Cancelled)
                    $stmtLastBuy = $pdo->prepare("SELECT order_no, price FROM buy_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
                    $stmtLastBuy->execute([$uid]);
                    $last_buy = $stmtLastBuy->fetch();
                    
                    $reply .= "Last Purchase Order\n";
                    $reply .= "**************************\n";
                    if ($last_buy) {
                        $reply .= "Order No: {$last_buy['order_no']}\n";
                        $reply .= "Order Amount: {$last_buy['price']}";
                    } else {
                        $reply .= "Order No: -\n";
                        $reply .= "Order Amount: -";
                    }
                }
                
                sendTelegramMessage($chat_id, $reply, $bot_token);
            }
            break;

        // Command 2: /bh <order_no> - Buy Order Status Checker
        case '/bh':
            if (empty($arg)) {
                sendTelegramMessage($chat_id, "❌ Usage: <code>/bh &lt;order_number&gt;</code>\nExample: <code>/bh 5404562692049925</code>", $bot_token);
            } else {
                $order_no = $arg;
                $stmt = $pdo->prepare("SELECT b.*, u.phone FROM buy_orders b JOIN users u ON b.user_id = u.id WHERE b.order_no = ?");
                $stmt->execute([$order_no]);
                $order = $stmt->fetch();
                
                if ($order) {
                    $phone = maskPhone($order['phone']);
                    $status_label = '⏳ Pending Review';
                    if ($order['status'] === 'Success') $status_label = '✅ Successful Payout';
                    if ($order['status'] === 'Cancelled') $status_label = '❌ Cancelled';
                    
                    $utr_proof = !empty($order['utr']) ? "<code>{$order['utr']}</code>" : "<i>None Submitted</i>";
                    
                    $reply = "📥 <b>Buy Order Status (#{$order_no})</b>\n";
                    $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
                    $reply .= "👤 <b>User:</b> <code>{$phone}</code>\n";
                    $reply .= "💵 <b>Amount Paid:</b> <code>₹ {$order['price']}</code>\n";
                    $reply .= "💎 <b>ITokens Added:</b> <code>{$order['itokens']}</code>\n";
                    $reply .= "🔎 <b>UTR Proof:</b> {$utr_proof}\n";
                    $reply .= "🕒 <b>Date:</b> <code>{$order['created_at']}</code>\n";
                    $reply .= "⚡ <b>Status:</b> <b>{$status_label}</b>";
                    
                    sendTelegramMessage($chat_id, $reply, $bot_token);
                } else {
                    sendTelegramMessage($chat_id, "❌ Buy order <code>{$order_no}</code> not found in system.", $bot_token);
                }
            }
            break;

        // Command 3: /sh <uid> - Sell History Dashboard
        case '/sh':
            if (empty($arg) || !is_numeric($arg)) {
                sendTelegramMessage($chat_id, "❌ Usage: <code>/sh &lt;user_id&gt;</code>\nExample: <code>/sh 1024</code>", $bot_token);
            } else {
                $uid = intval($arg);
                $stmtUser = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
                $stmtUser->execute([$uid]);
                $user = $stmtUser->fetch();
                
                if ($user) {
                    $phone = maskPhone($user['phone']);
                    
                    // Fetch recent 3 sell orders
                    $stmtSells = $pdo->prepare("SELECT amount, status, created_at FROM sell_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
                    $stmtSells->execute([$uid]);
                    $sells = $stmtSells->fetchAll();
                    
                    $reply = "📤 <b>Recent Sell History (UID: {$uid})</b>\n";
                    $reply .= "👤 <b>User:</b> <code>{$phone}</code>\n";
                    $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
                    
                    if (empty($sells)) {
                        $reply .= "<i>No sell records found for this user.</i>";
                    } else {
                        foreach ($sells as $idx => $s) {
                            $status_label = '⏳ Paying';
                            if ($s['status'] === 'Success') $status_label = '✅ Success';
                            if ($s['status'] === 'Failed') $status_label = '❌ Failed';
                            
                            $reply .= ($idx + 1) . ". <b>Amount:</b> <code>₹ {$s['amount']}</code> | <b>Status:</b> {$status_label}\n";
                            $reply .= "   <b>Date:</b> <code>{$s['created_at']}</code>\n\n";
                        }
                    }
                    sendTelegramMessage($chat_id, $reply, $bot_token);
                } else {
                    sendTelegramMessage($chat_id, "❌ User with UID <code>{$uid}</code> not found.", $bot_token);
                }
            }
            break;

        // Command 4: /rate - Check Active USDT Exchange Rate
        case '/rate':
            $rate = getSetting('exchange_rate_usdt') ?: '106.7';
            $reply = "📊 <b>NextPay Exchange Rates</b>\n";
            $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
            $reply .= "🪙 <b>1 USDT =</b> <code>{$rate} IToken / Rupees</code>\n";
            $reply .= "💳 <b>1 IToken =</b> <code>1.00 INR (Rupee)</code>\n";
            $reply .= "💡 <i>Rate is adjusted dynamically by administrative controls.</i>";
            sendTelegramMessage($chat_id, $reply, $bot_token);
            break;

        // Command 5: /rules - Display Deposit/Withdrawal rules
        case '/rules':
            $night_ratio = getSetting('buy_ratio_nighttime') ?: '3.0';
            $reply = "📢 <b>NextPay Trading Guidelines & Limits</b>\n";
            $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
            $reply .= "➡️ <b>Min Buy Order:</b> <code>₹ 100</code>\n";
            $reply .= "➡️ <b>Max Buy Order:</b> <code>₹ 100,000</code>\n";
            $reply .= "📈 <b>Night-Time Reward Bonus:</b> <code>+{$night_ratio}%</code>\n";
            $reply .= "📤 <b>Automatic Sell matching:</b> Enabled for active accounts\n";
            $reply .= "🔒 <i>Verify payments with correct 12-digit UTR codes to avoid block actions.</i>";
            sendTelegramMessage($chat_id, $reply, $bot_token);
            break;

        // Command 6: /support - Dynamic Support Details
        case '/support':
            $telegram_channel = getSetting('support_telegram_channel') ?: 'https://t.me/NextPay_channel';
            $telegram_vip     = getSetting('support_telegram_vip') ?: 'https://t.me/NextPay_vip';
            $telegram_csr     = getSetting('support_telegram_csr') ?: 'https://t.me/NextPay_csr';
            
            $reply = "🎧 <b>NextPay Help Center Handles</b>\n";
            $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
            $reply .= "📣 <b>Official TG Channel:</b> <a href='{$telegram_channel}'>Join Channel</a>\n";
            $reply .= "💎 <b>TG VIP Lounge:</b> <a href='{$telegram_vip}'>Join VIP Group</a>\n";
            $reply .= "💬 <b>Customer Care CSR:</b> <a href='{$telegram_csr}'>Connect Now</a>\n\n";
            $reply .= "💡 <i>Tap links above to chat with official administrators.</i>";
            sendTelegramMessage($chat_id, $reply, $bot_token);
            break;

        // Command 8: /daily <uid> - Check-in rewards
        case '/daily':
            if (empty($arg) || !is_numeric($arg)) {
                sendTelegramMessage($chat_id, "❌ Usage: <code>/daily &lt;user_id&gt;</code>\nExample: <code>/daily 1024</code>", $bot_token);
            } else {
                $uid = intval($arg);
                $stmt = $pdo->prepare("SELECT last_checkin, status FROM users WHERE id = ?");
                $stmt->execute([$uid]);
                $user = $stmt->fetch();
                
                if ($user) {
                    if ($user['status'] === 'Blocked') {
                        sendTelegramMessage($chat_id, "❌ Verification Failed: This account is currently blocked.", $bot_token);
                    } else {
                        $now = time();
                        $last_time = !empty($user['last_checkin']) ? strtotime($user['last_checkin']) : 0;
                        
                        if (($now - $last_time) < 86400) {
                            $hours_left = ceil((86400 - ($now - $last_time)) / 3600);
                            sendTelegramMessage($chat_id, "⏳ Already checked in today. Please try again in {$hours_left} hours.", $bot_token);
                        } else {
                            // Reward user with 5 ITokens
                            $reward = 5.00;
                            $pdo->beginTransaction();
                            $update = $pdo->prepare("UPDATE users SET itoken_balance = itoken_balance + ?, last_checkin = NOW() WHERE id = ?");
                            $update->execute([$reward, $uid]);
                            $pdo->commit();
                            
                            sendTelegramMessage($chat_id, "🎁 <b>Daily Check-in Success!</b>\nUID <code>{$uid}</code> just received <b>+5.00 IToken</b> bonus balance!", $bot_token);
                        }
                    }
                } else {
                    sendTelegramMessage($chat_id, "❌ User with UID <code>{$uid}</code> not found.", $bot_token);
                }
            }
            break;

        // Command 9: /leaderboard - Promoters Leaderboard
        case '/leaderboard':
            // Fetch top 5 agents sorted by balance
            $stmt = $pdo->prepare("SELECT id, phone, itoken_balance FROM users WHERE role = 'user' ORDER BY itoken_balance DESC LIMIT 5");
            $stmt->execute();
            $leaders = $stmt->fetchAll();
            
            $reply = "🏆 <b>Top Promoters Leaderboard</b>\n";
            $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
            
            if (empty($leaders)) {
                $reply .= "<i>No active agents found.</i>";
            } else {
                $medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];
                foreach ($leaders as $idx => $l) {
                    $phone = maskPhone($l['phone']);
                    $reply .= "{$medals[$idx]} <b>UID {$l['id']}</b> ({$phone}) - <code>{$l['itoken_balance']} IToken</code>\n";
                }
            }
            sendTelegramMessage($chat_id, $reply, $bot_token);
            break;

        // Command 10: /tutorial - Help Guides
        case '/tutorial':
            $reply = "🎥 <b>NextPay Step-By-Step Guides</b>\n";
            $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
            $reply .= "1️⃣ <b>How to Buy ITokens:</b> <a href='https://youtube.com/NextPay_buy_tutorial'>Watch Video</a>\n";
            $reply .= "2️⃣ <b>How to Link UPI:</b> <a href='https://youtube.com/NextPay_upi_tutorial'>Watch Video</a>\n";
            $reply .= "3️⃣ <b>How to Sell ITokens:</b> <a href='https://youtube.com/NextPay_sell_tutorial'>Watch Video</a>\n\n";
            $reply .= "💡 <i>Follow tutorials above to operate safely without errors.</i>";
            sendTelegramMessage($chat_id, $reply, $bot_token);
            break;

        // Command 7: /help - Show commands menu
        case '/help':
        default:
            $reply = "🤖 <b>NextPay Group Assistant Bot Commands</b>\n";
            $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
            $reply .= "ℹ️ <code>/help</code> - Show this menu\n";
            $reply .= "👤 <code>/b &lt;uid&gt;</code> - Check balance & active stats\n";
            $reply .= "📥 <code>/bh &lt;order_no&gt;</code> - Check buy order status & UTR\n";
            $reply .= "📤 <code>/sh &lt;uid&gt;</code> - Check recent sell history list\n";
            $reply .= "🎁 <code>/daily &lt;uid&gt;</code> - Claim daily 5 IToken bonus\n";
            $reply .= "🏆 <code>/leaderboard</code> - View top promoter rankings\n";
            $reply .= "🎥 <code>/tutorial</code> - View video help tutorials\n";
            $reply .= "📊 <code>/rate</code> - Get live USDT rate exchange\n";
            $reply .= "📢 <code>/rules</code> - Read deposit/withdrawal boundaries\n";
            $reply .= "🎧 <code>/support</code> - Link to Telegram support desks\n";
            sendTelegramMessage($chat_id, $reply, $bot_token);
            break;
    }
}
?>

