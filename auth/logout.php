<?php
require_once __DIR__ . '/../includes/auth.php';
doLogout();
redirect(BASE_URL . '/auth/login.php');
