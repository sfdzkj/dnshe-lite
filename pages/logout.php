<?php
$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
logout($cfg);
header('Location: index.php?page=login');
