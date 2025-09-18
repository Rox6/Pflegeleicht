<?php
$cfg = [
  'host' => 'database-5018641644.webspace-host.com',
  'db'   => 'dbs14775351',   
  'user' => 'dbu747924',
  'pass' => 'ProbandoProbando!',               
];

$dsn = "mysql:host=".$cfg['host'].";dbname=".$cfg['db'].";charset=utf8mb4";

$opt = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $opt);
