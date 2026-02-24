<?php

// Test database connection
try {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_DATABASE') ?: 'nulumbung';
    $username = getenv('DB_USERNAME') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$database",
        $username,
        $password
    );
    
    $result = $pdo->query('SELECT 1 as status')->fetch(PDO::FETCH_ASSOC);
    
    echo "✓ Database connection successful\n";
    echo "  Host: $host\n";
    echo "  Port: $port\n";
    echo "  Database: $database\n";
    echo "  Result: " . json_encode($result) . "\n";
} catch (PDOException $e) {
    echo "✗ Database connection failed\n";
    echo "  Error: " . $e->getMessage() . "\n";
    exit(1);
}
