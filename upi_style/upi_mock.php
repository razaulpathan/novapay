<?php
// upi_mock.php - Mock UPI partner integration for demo purposes
// No real external API calls; all functions return static data.

// Fixed mock OTP for simplicity (could be randomized).
const MOCK_OTP = '123456';

/**
 * Simulate sending an OTP to the user's registered mobile number.
 * In a real implementation this would call the partner's API.
 * Here we simply return the mock OTP.
 */
function send_otp(string $partner, string $mobile): string {
    // Log the request for debugging (you can view server logs).
    error_log("[UPI MOCK] send_otp called for partner {$partner}, mobile {$mobile}");
    return MOCK_OTP;
}

/**
 * Verify the OTP submitted by the user.
 * Returns true only if the OTP matches the mock constant.
 */
function verify_otp(string $partner, string $otp): bool {
    error_log("[UPI MOCK] verify_otp called for partner {$partner}, otp {$otp}");
    return $otp === MOCK_OTP;
}

/**
 * Return a static list of dummy UPI IDs that belong to the mobile number.
 * In production this would query the partner's API.
 */
function fetch_upi_ids(string $partner, string $mobile): array {
    error_log("[UPI MOCK] fetch_upi_ids called for partner {$partner}, mobile {$mobile}");
    // Mock data – feel free to expand the list.
    return [
        "abc@oksbi",
        "xyz@ybl",
        "user@upi"
    ];
}
?>

