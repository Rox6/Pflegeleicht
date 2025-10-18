<?php
// Encrypted database credentials
// Use this to encrypt: [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes("your_pwd"))


return [
  'host' => base64_decode('ZGF0YWJhc2UtNTAxODc1NTcyMi53ZWJzcGFjZS1ob3N0LmNvbQ=='),
  'db'   => base64_decode('ZGJzMTQ4MzE1NTg='), 
  'user' => base64_decode('ZGJ1NDg5NjU4NQ=='),
  'pass' => base64_decode('UHJvYmFuZG9Qcm9iYW5kbyE='),
  'SMTP_PASS' => base64_decode('VGVzdCNUZXN0MyMjIzM='),
];
//Example: 
// $host = "database-5018641644.webspace-host.com";
// $user = "dbu4896585";
// $pass = "ProbandoProbando!";
// $db   = "dbs14831558";
