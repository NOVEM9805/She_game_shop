<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!empty($_SESSION['reset_verified_at'])) {
    redirect('auth/new-password.php');
}
redirect('auth/verify-reset-otp.php');
