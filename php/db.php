<?php
// Database configuration and connection
$cfg = [
  'host' => 'database-5018755722.webspace-host.com',
  'db'   => 'dbs14831558',
  'user' => 'dbu4896585',
  'pass' => 'ProbandoProbando!',
];

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
