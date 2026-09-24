<?php
require_once __DIR__ . '/../app/config/Database.php';
require_once __DIR__ . '/../app/helpers/auth.php';

mulaiSession();
logoutUser();
header('Location: ' . baseUrlPath() . '/login/pages/login.php');
exit;
