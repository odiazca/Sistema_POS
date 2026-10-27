<?php
require_once __DIR__ . '/includes/auth.php';
header('Location: ' . BASE_URL . (usuario() ? '/dashboard.php' : '/login.php'));
