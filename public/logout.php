<?php
require_once __DIR__.'/../app/helpers.php';
Core\Auth::logout();
header('Location: login.php');
exit;
