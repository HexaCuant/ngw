<?php
// Simulate AJAX POST to open_character_ajax for character id 8
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['char_action'] = 'open_character_ajax';
$_POST['char_id'] = $argv[1] ?? 8;
$_GET['option'] = 1;
// Run controller
require __DIR__ . '/public/index.php';
