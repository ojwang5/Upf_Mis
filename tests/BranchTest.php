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

    public function testDeleteBranchWithEmployeesAllowed(): void
    {
        $id = create_branch($this->pdo, 'Staffed Branch', 'STF', 'City');
        // create an employee assigned to branch
        $stmt = $this->pdo->prepare("INSERT INTO employees (service_no, full_name, gender, rank, branch_id) VALUES (?,?,?,?,?)");
        $stmt->execute(['UPF999', 'Test Person', 'M', 'Constable', $id]);

        delete_branch($this->pdo, $id);

        $b = get_branch($this->pdo, $id);
        $this->assertNull($b);

        $empCount = $this->pdo->query('SELECT COUNT(*) FROM employees WHERE branch_id = ' . (int)$id)->fetchColumn();
        $this->assertSame('0', (string)$empCount);
    }


    public function testCascadeDeleteBranchWithEmployees(): void
    {
        $id = create_branch($this->pdo, 'Staffed Branch', 'STF2', 'City');

        // create an employee assigned to branch
        $stmt = $this->pdo->prepare("INSERT INTO employees (service_no, full_name, gender, rank, branch_id) VALUES (?,?,?,?,?)");
        $stmt->execute(['UPF1000', 'Test Person 2', 'M', 'Constable', $id]);
        $employeeId = (int)$this->pdo->query('SELECT id FROM employees ORDER BY id DESC LIMIT 1')->fetchColumn();

        // create dependent rows
        $this->pdo->exec("INSERT INTO daily_status (employee_id, date, status, notes, recorded_by) VALUES ($employeeId, '2020-01-01', 'present', NULL, NULL)");
        $this->pdo->exec("INSERT INTO leave_requests (employee_id, branch_id, leave_type, start_date, end_date, reason, destination, status, submitted_by, submitted_at) VALUES ($employeeId, $id, 'annual', '2020-01-01', '2020-01-02', 'Reason', 'X', 'pending_manager', NULL, '2020-01-01')");

        delete_branch($this->pdo, $id, 'cascade');

        $b = get_branch($this->pdo, $id);
        $this->assertNull($b);

        $emp = $this->pdo->query('SELECT COUNT(*) FROM employees WHERE id = ' . (int)$employeeId)->fetchColumn();
        $this->assertSame('0', (string)$emp);
    }

}
