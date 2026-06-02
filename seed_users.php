<?php
require_once 'config.php';

$users = [
    ['username' => 'admin', 'password' => 'admin123'],
    ['username' => 'doctor1', 'password' => 'doctor123'],
    ['username' => 'nurse1', 'password' => 'nurse123'],
    
];

foreach ($users as $user) {
    $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?) ON DUPLICATE KEY UPDATE password = ?");
    $stmt->bind_param("sss", $user['username'], $hashed_password, $hashed_password);
    
    if ($stmt->execute()) {
        echo "User '{$user['username']}' ready.<br>";
    } else {
        echo "Error for '{$user['username']}': " . $stmt->error . "<br>";
    }
    $stmt->close();
}
echo "<br>All users seeded successfully.";
?>