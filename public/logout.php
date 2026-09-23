<?php
require_once __DIR__ . '/../bootstrap.php';
Core\Auth::logout();
header('Location: login.php');
exit;
