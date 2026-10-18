<?php
// link_upi.php - UPI linking flow (mock integration)

require_once 'db.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$step = $_POST['step'] ?? 'select_partner';
$partner = $_POST['partner'] ?? '';
$otp = $_POST['otp'] ?? '';
$selected_upi = $_POST['selected_upi'] ?? '';

function render_header() {
    echo "<!DOCTYPE html><html lang=\"en\"><head>";
    echo "<meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">";
    echo "<title>Link UPI</title>";
    echo "<link href=\"https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap\" rel=\"stylesheet\">";
    echo "<link rel=\"stylesheet\" href=\"upi_style.css\">";
    echo "</head><body>";
}

function render_footer() {
    echo "</body></html>";
}

render_header();

switch ($step) {
    case 'select_partner':
        ?>
        <div class="upi-card">
            <h1>Link UPI</h1>
            <form method="POST">
                <input type="hidden" name="step" value="select_upi">
                <label for="partner">Select Partner:</label>
                <select name="partner" id="partner" required>
                    <option value="">--Choose--</option>
                    <option>PhonePe</option>
                    <option>Google Pay</option>
                    <option>Paytm</option>
                    <option>BHIM</option>
                </select>
                <button type="submit" class="btn-primary">Link UPI</button>
            </form>
        </div>
        <?php
        break;
    case 'select_upi':
        require_once 'upi_mock.php';
        $upi_ids = fetch_upi_ids($partner, 'user-mobile-placeholder');
        ?>
        <div class="upi-card">
            <h2>Select UPI ID</h2>
            <form method="POST">
                <input type="hidden" name="step" value="link_upi">
                <input type="hidden" name="partner" value="<?=htmlspecialchars($partner)?>">
                <?php foreach ($upi_ids as $id): ?>
                    <div class="upi-option">
                        <input type="radio" name="selected_upi" value="<?=htmlspecialchars($id)?>" required>
                        <span><?=htmlspecialchars($id)?></span>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="btn-primary">Link Selected UPI</button>
            </form>
        </div>
        <?php
        break;
    case 'link_upi':
        // Persist selected UPI ID to database (using upi_accounts table)
        $stmt = $pdo->prepare("INSERT INTO upi_accounts (user_id, partner, name, upi_no, status) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE upi_no = VALUES(upi_no)");
        $stmt->execute([$user_id, $partner, $partner . ' Account', $selected_upi, 'Active']);
        ?>
        <div class="upi-card">
            <h2>Success!</h2>
            <p>UPI ID <strong><?=htmlspecialchars($selected_upi)?></strong> has been linked to your account.</p>
            <a href="home.php" class="btn-primary">Back to Home</a>
        </div>
        <?php
        break;
    default:
        echo '<div class="upi-card"><p class="error">Invalid step.</p></div>';
}

render_footer();
?>

