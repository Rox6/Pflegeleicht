<?php
$cfg = [
  'host' => '127.0.0.1',
  'db'   => 'test_contact',   
  'user' => 'root',
  'pass' => '1234',               
  'port' => 3306,
];

$dsn = "mysql:host={$cfg['host']};dbname={$cfg['db']};port={$cfg['port']};charset=utf8mb4";
$opt = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];
$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $opt);
