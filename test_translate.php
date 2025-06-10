<?php
// test_translate.php

require_once __DIR__ . '/vendor/autoload.php';

$apiKey = 'AIzaSyBrvy2qMWQ9rIjDMzVzqGZ2Tn7npUsY1fQ';
$text = 'Hello, world!';
$targetLang = 'ru';

$url = "https://translation.googleapis.com/language/translate/v2?key=" . $apiKey;
$data = [
    'q' => $text,
    'source' => 'en',
    'target' => $targetLang,
    'format' => 'text'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Response: " . $response . "\n";