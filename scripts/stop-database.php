<?php

// Called after the PowerShell wrapper verifies the owned process and data directory.
$root = dirname(__DIR__);
$admin = json_decode(file_get_contents($root.'/storage/local/mysql-admin.json'), true, 512, JSON_THROW_ON_ERROR);
$pdo = new PDO('mysql:host=127.0.0.1;port=3311', $admin['username'], $admin['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SHUTDOWN');
echo "PBL MySQL shutdown requested.\n";
