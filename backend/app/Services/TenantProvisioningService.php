<?php

require_once __DIR__ . '/../Config/masterDatabase.php';
require_once __DIR__ . '/../Security/Hash.php';
require_once __DIR__ . '/../Security/AES.php';

class TenantProvisioningService
{
    private PDO $master;
    private AES $aes;

    // Path to the shared schema file
    private const SCHEMA_FILE = __DIR__ . '/../Database/tenantSchema.sql';

    public function __construct()
    {
        $this->master = MasterDatabase::getConnection();
        $this->aes    = new AES();
    }

    public function register(array $data): array
{
    error_log("STEP 1: register() started");

    $companyName = trim($data['company_name']);
    $adminName   = trim($data['admin_name']);
    $adminEmail  = strtolower(trim($data['admin_email']));
    $password    = $data['password'];
    $planType    = $data['plan_type'] ?? 'free';
    $theme       = $data['theme'] ?? 'dark';

    error_log("STEP 2: Data received");

    // 1. Generate subdomain
    $subdomain = $this->generateSubdomain($companyName);

    if ($this->subdomainExists($subdomain)) {
        $subdomain = $subdomain . rand(1000, 9999);
    }

    error_log("STEP 3: Subdomain = " . $subdomain);

    // 2. Generate tenant DB name
    $dbName = 'tenant_' . $subdomain . '_db';

    error_log("STEP 4: Tenant DB Name = " . $dbName);

    // 3. Insert into master tenants table
    $stmt = $this->master->prepare("
        INSERT INTO tenants
            (company_name, subdomain, db_name, theme, plan_type, admin_email, is_active)
        VALUES
            (:company_name, :subdomain, :db_name, :theme, :plan_type, :admin_email, 1)
    ");

    $stmt->execute([
        ':company_name' => $companyName,
        ':subdomain'    => $subdomain,
        ':db_name'      => $dbName,
        ':theme'        => $theme,
        ':plan_type'    => $planType,
        ':admin_email'  => $adminEmail,
    ]);

    error_log("STEP 5: Tenant inserted into master database");

    $tenantId = (int)$this->master->lastInsertId();

    // 4. Create tenant database
    $this->createTenantDatabase($dbName);

    error_log("STEP 6: Tenant database created");

    // 5. Import schema
    $this->runSchemaFromFile($dbName);

    error_log("STEP 7: Schema imported");

    // 6. Create admin user
    $this->createAdminUser($dbName, $adminName, $adminEmail, $password);

    error_log("STEP 8: Admin user created");

    return [
        'tenant_id' => $tenantId,
        'subdomain' => $subdomain,
        'db_name'   => $dbName,
        'workspace' => $subdomain . '.localhost:3000',
        'login_url' => 'http://' . $subdomain . '.localhost:3000/login',
    ];
}
    // ── Private Helpers ──────────────────────────────────────

    private function generateSubdomain(string $companyName): string
    {
        $subdomain = strtolower($companyName);
        $subdomain = preg_replace('/[^a-z0-9]/', '', $subdomain);
        return substr($subdomain, 0, 30);
    }

    private function subdomainExists(string $subdomain): bool
    {
        $stmt = $this->master->prepare(
            "SELECT COUNT(id) FROM tenants WHERE subdomain = ?"
        );
        $stmt->execute([$subdomain]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function getRootConnection(): PDO
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3307';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';

        return new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    private function getTenantConnection(string $dbName): PDO
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3307';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';

        return new PDO(
            "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    private function createTenantDatabase(string $dbName): void
    {
        $pdo      = $this->getRootConnection();
        $safeName = preg_replace('/[^a-z0-9_]/', '', $dbName);

        $pdo->exec("
            CREATE DATABASE IF NOT EXISTS `{$safeName}`
            CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci
        ");
    }

    private function runSchemaFromFile(string $dbName): void
    {
        if (!file_exists(self::SCHEMA_FILE)) {
            throw new RuntimeException('Schema file not found: ' . self::SCHEMA_FILE);
        }

        $sql = file_get_contents(self::SCHEMA_FILE);
        $pdo = $this->getTenantConnection($dbName);

        // Strip comments line-by-line first
        $lines = explode("\n", $sql);
        $cleanLines = array_filter($lines, function($line) {
            $trimmed = trim($line);
            return !str_starts_with($trimmed, '--') && !str_starts_with($trimmed, '#');
        });
        $cleanSql = implode("\n", $cleanLines);

        // Split on semicolons, skip empty lines
        $statements = array_filter(
            array_map('trim', explode(';', $cleanSql)),
            fn($s) => !empty($s)
        );

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }

    private function createAdminUser(
        string $dbName,
        string $adminName,
        string $adminEmail,
        string $password
    ): void {
        $pdo = $this->getTenantConnection($dbName);

        $nameParts = explode(' ', $adminName, 2);
        $firstName = $nameParts[0];
        $lastName  = $nameParts[1] ?? '';

        $emailHash    = hash('sha256', strtolower($adminEmail));
        $passwordHash = Hash::make($password);

        // Get admin role
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'admin' LIMIT 1");
        $stmt->execute();
        $role = $stmt->fetch();

        if (!$role) {
            throw new RuntimeException('Admin role not found in tenant schema.');
        }

        // Insert user — no tenant_id column anymore
        $stmt = $pdo->prepare("
            INSERT INTO users
                (role_id, first_name, last_name, email, email_hash, password_hash, is_active)
            VALUES
                (:role_id, :first_name, :last_name, :email, :email_hash, :password_hash, 1)
        ");

        $stmt->execute([
            ':role_id'       => $role['id'],
            ':first_name'    => $this->aes->encrypt($firstName),
            ':last_name'     => $this->aes->encrypt($lastName),
            ':email'         => $this->aes->encrypt($adminEmail),
            ':email_hash'    => $emailHash,
            ':password_hash' => $passwordHash,
        ]);

        $userId = (int)$pdo->lastInsertId();

        // Insert into staff — no tenant_id column
        $pdo->prepare("
            INSERT INTO staff (user_id, is_active) VALUES (:user_id, 1)
        ")->execute([':user_id' => $userId]);
    }
}