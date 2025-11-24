<?php
// examples/stamp.php

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTimestampsPHP\Client;

$client = new Client();

// Create a timestamp
$otsData = $client->stamp('document.pdf');
file_put_contents('document.pdf.ots', $otsData);
echo "Timestamp created: document.pdf.ots\n";

// examples/verify.php

$client = new Client();

// Verify a timestamp
$isValid = $client->verify(
    file_get_contents('document.pdf.ots'),
    'document.pdf'
);

echo $isValid ? "Timestamp is valid!\n" : "Timestamp is invalid!\n";