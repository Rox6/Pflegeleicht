<?php
// Database configuration and connection (encrypted)
$cfg = require __DIR__ . '/config.enc.php';

// Build DSN string for MySQL connection
$dsn = "mysql:host=".$cfg['host'].";dbname=".$cfg['db'].";charset=utf8mb4";

// PDO configuration options
$opt = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

// Create PDO database connection
$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $opt);
