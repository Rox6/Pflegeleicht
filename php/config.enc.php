<?php
// Encrypted database credentials
// Use this to encrypt: [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes("ProbandoProbando!"))
// Use this to decrypt: base64_decode($encoded);

return [
  'host' => base64_decode('ZGF0YWJhc2UtNTAxODc1NTcyMi53ZWJzcGFjZS1ob3N0LmNvbQ=='),
  'db'   => base64_decode('ZGJzMTQ4MzE1NTg='),
  'user' => base64_decode('ZGJ1NDg5NjU4NQ=='),
  'pass' => base64_decode('UHJvYmFuZG9Qcm9iYW5kbyE='),
];
