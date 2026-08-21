<?php
require __DIR__ . '/../config/session.php';
session_destroy();
header('Location: /auth/login.php');
