<?php
declare(strict_types=1);

// Load Composer autoload if available; otherwise provide a lightweight
// PHPUnit TestCase stub so the editor/linter doesn't complain when
// vendor dependencies aren't installed yet.
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    require_once __DIR__ . '/phpunit_stub.php';
}

require_once __DIR__ . '/../includes/branch.php';
require_once __DIR__ . '/../includes/db.php';

final class BranchTest extends \PHPUnit\Framework\TestCase
{
    private PDO $pdo;

    public function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // initialize schema into memory DB
        init_schema($this->pdo);
    }

    public function testCreateAndFindBranch(): void
    {
        $id = create_branch($this->pdo, 'Test Branch', 'TST', 'Testville');
        $this->assertIsInt($id);
        $b = get_branch($this->pdo, $id);
        $this->assertNotNull($b);
        $this->assertSame('TST', $b['code']);
    }

    public function testCannotDeleteBranchWithEmployees(): void
    {
        $id = create_branch($this->pdo, 'Staffed Branch', 'STF', 'City');
        // create an employee assigned to branch
        $stmt = $this->pdo->prepare("INSERT INTO employees (service_no, full_name, gender, rank, branch_id) VALUES (?,?,?,?,?)");
        $stmt->execute(['UPF999', 'Test Person', 'M', 'Constable', $id]);

        $this->expectException(Exception::class);
        delete_branch($this->pdo, $id);
    }
}
