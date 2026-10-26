<?php
// test_sqlite.php

// Check if the SQLite3 class exists
if (!class_exists('SQLite3')) {
    echo "SQLite3 extension is NOT enabled\n";
    exit(1);
}

try {
    $db = new SQLite3(':memory:');
    echo "SQLite3 extension is enabled and working!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
