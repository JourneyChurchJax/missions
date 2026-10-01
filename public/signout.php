<?php
require __DIR__ . '/inc/bootstrap.php';
$_SESSION = [];
session_destroy();
header('Location: /signin.php');
exit;
