<?php require __DIR__.'/app/bootstrap.php'; if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;} check_csrf(); $_SESSION=[]; session_destroy(); go('login.php');
