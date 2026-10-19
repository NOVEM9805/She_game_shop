<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
unset($_SESSION['user_id']);
session_regenerate_id(true);
flash('success', 'You have been logged out.');
redirect('auth/login.php');
