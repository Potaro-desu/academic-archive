<?php
require_once __DIR__.'/../config/database.php';
$password=password_hash('ArchiveDemo2026!',PASSWORD_DEFAULT);
$accounts=[
 ['College Administrator','admin@college.edu','admin'],
 ['Demo Student','student@college.edu','student'],
 ['Demo Faculty','faculty@college.edu','faculty']
];
$stmt=$pdo->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),password_hash=VALUES(password_hash),role=VALUES(role)');
foreach($accounts as [$name,$email,$role]) $stmt->execute([$name,$email,$password,$role]);
echo "Demo accounts are ready. Password: ArchiveDemo2026!\\n";
