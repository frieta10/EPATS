<?php
require_once __DIR__ . '/../config.php';
startDbSession();
session_destroy();
header('Location: ' . BASE_URL . '/admin/login.php');
exit;
