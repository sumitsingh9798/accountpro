<?php
// Run once from CLI: php setup.php
// Creates the first admin user against the company row seeded by schema.sql.
declare(strict_types=1);
require __DIR__ . '/config/db.php';

$company = $pdo->query("SELECT id, name FROM companies ORDER BY id LIMIT 1")->fetch();
if (!$company) { die("No company found — run database/schema.sql first.\n"); }

fwrite(STDOUT, "Setting up admin user for company: {$company['name']}\n");
fwrite(STDOUT, "Admin name: "); $name = trim(fgets(STDIN));
fwrite(STDOUT, "Admin email: "); $email = trim(fgets(STDIN));
fwrite(STDOUT, "Admin password: "); $pass = trim(fgets(STDIN));

$hash = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (company_id, name, email, password_hash, role) VALUES (?,?,?,?,'admin')");
$stmt->execute([$company['id'], $name, $email, $hash]);

echo "Admin user created. Log in at /auth/login.php\n";
