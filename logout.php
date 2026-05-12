<?php
session_start();
session_destroy();
header('Location: /erp/login.php');
exit;
