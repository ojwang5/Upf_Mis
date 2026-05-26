<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (!is_dir(dirname(DB_PATH))) {
            mkdir(dirname(DB_PATH), 0775, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        init_schema($pdo);
        migrate($pdo);
    }
    return $pdo;
}

function init_schema(PDO $pdo): void {
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS branches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            code TEXT NOT NULL UNIQUE,
            location TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT,
            password_hash TEXT NOT NULL,
            full_name TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('admin','manager','officer')),
            branch_id INTEGER REFERENCES branches(id)
        );
        CREATE TABLE IF NOT EXISTS employees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            service_no TEXT NOT NULL UNIQUE,
            full_name TEXT NOT NULL,
            gender TEXT NOT NULL CHECK(gender IN ('M','F')),
            rank TEXT NOT NULL,
            branch_id INTEGER NOT NULL REFERENCES branches(id),
            phone TEXT,
            email TEXT NOT NULL,
            active INTEGER NOT NULL DEFAULT 1
        );
        CREATE TABLE IF NOT EXISTS daily_status (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
            date TEXT NOT NULL,
            status TEXT NOT NULL CHECK(status IN ('present','awol','leave','sick','onleave')),
            notes TEXT,
            recorded_by INTEGER REFERENCES users(id),
            UNIQUE(employee_id, date)
        );
        CREATE TABLE IF NOT EXISTS reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            branch_id INTEGER REFERENCES branches(id),
            date TEXT NOT NULL,
            generated_by INTEGER REFERENCES users(id),
            generated_at TEXT NOT NULL,
            summary_json TEXT NOT NULL
        );

        -- Admin/audit trail table (used by includes/audit.php and public/admin-audit.php)
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            actor_user_id INTEGER REFERENCES users(id),
            actor_role TEXT,
            action TEXT NOT NULL,
            target_type TEXT,
            target_id TEXT,
            ip_address TEXT,
            user_agent TEXT,
            meta_json TEXT,
            created_at TEXT NOT NULL
        );
SQL
    );

    $count = (int)$pdo->query("SELECT COUNT(*) FROM branches")->fetchColumn();
    if ($count === 0) {
        seed_data($pdo);
    }
}

function migrate(PDO $pdo): void {
    $v = (int)$pdo->query("PRAGMA user_version")->fetchColumn();

    // v3: add login 2FA OTP storage
    if ($v < 3) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_otps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            otp_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            last_attempt_at TEXT,
            ip_address TEXT
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_login_otps_user_id_created_at ON login_otps(user_id, created_at)");
        $pdo->exec("PRAGMA user_version = 3");
    }


    // Post-v1/legacy migrations.
    $hasLeaveRequests = (bool)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='leave_requests'")
        ->fetchColumn();
    if ($hasLeaveRequests) {
        $cols = [];
        foreach ($pdo->query("PRAGMA table_info(leave_requests)") as $c) {
            $cols[$c['name']] = true;
        }
        if (!isset($cols['expires_notified_at'])) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN expires_notified_at TEXT");
        }
        if (!isset($cols['expiry_renewal_status'])) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN expiry_renewal_status TEXT NOT NULL DEFAULT 'none'");
        }
        if (!isset($cols['destination'])) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN destination TEXT");
        }
    }

    // v2: add required email column to employees
    if ($v < 2) {
        $cols = [];
        foreach ($pdo->query("PRAGMA table_info(employees)") as $c) {
            $cols[$c['name']] = true;
        }
        if (!isset($cols['email'])) {
            // Add as nullable first, then backfill, then enforce NOT NULL.
            // SQLite ALTER TABLE limitations mean NOT NULL enforcement may not be fully guaranteed
            // across versions; this is still a safe upgrade for existing DBs.
            $pdo->exec("ALTER TABLE employees ADD COLUMN email TEXT");
            $pdo->exec("UPDATE employees SET email = '' WHERE email IS NULL");
        }
        $pdo->exec("PRAGMA user_version = 2");
    }

    if ($v < 1) {
        // v1 base migration: users role constraint, add reports workflow fields, create leave_requests + notifications
        $pdo->exec("PRAGMA foreign_keys = OFF");
        $pdo->exec("BEGIN");
        try {
            $pdo->exec("CREATE TABLE users_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                full_name TEXT NOT NULL,
                role TEXT NOT NULL CHECK(role IN ('admin','manager','officer')),
                branch_id INTEGER REFERENCES branches(id)
            )");
            $pdo->exec("INSERT INTO users_new (id, username, password_hash, full_name, role, branch_id)
                SELECT id, username, password_hash, full_name, role, branch_id FROM users");
            $pdo->exec("DROP TABLE users");
            $pdo->exec("ALTER TABLE users_new RENAME TO users");

            $cols = [];
            foreach ($pdo->query("PRAGMA table_info(reports)") as $c) {
                $cols[$c['name']] = true;
            }
            if (!isset($cols['status']))       $pdo->exec("ALTER TABLE reports ADD COLUMN status TEXT NOT NULL DEFAULT 'approved'");
            if (!isset($cols['reviewed_by']))  $pdo->exec("ALTER TABLE reports ADD COLUMN reviewed_by INTEGER");
            if (!isset($cols['reviewed_at']))  $pdo->exec("ALTER TABLE reports ADD COLUMN reviewed_at TEXT");
            if (!isset($cols['review_notes'])) $pdo->exec("ALTER TABLE reports ADD COLUMN review_notes TEXT");

            $pdo->exec("CREATE TABLE IF NOT EXISTS leave_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
                branch_id INTEGER NOT NULL REFERENCES branches(id),
                leave_type TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                reason TEXT NOT NULL,
                destination TEXT,
                status TEXT NOT NULL DEFAULT 'pending_manager',
                submitted_by INTEGER REFERENCES users(id),
                submitted_at TEXT NOT NULL,
                manager_reviewed_by INTEGER REFERENCES users(id),
                manager_reviewed_at TEXT,
                manager_notes TEXT,
                admin_reviewed_by INTEGER REFERENCES users(id),
                admin_reviewed_at TEXT,
                admin_notes TEXT,
                expires_notified_at TEXT,
                expiry_renewal_status TEXT NOT NULL DEFAULT 'none'
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                link TEXT,
                kind TEXT NOT NULL DEFAULT 'info',
                audience TEXT NOT NULL,
                target_user_id INTEGER REFERENCES users(id),
                target_role TEXT,
                target_branch_id INTEGER REFERENCES branches(id),
                created_by INTEGER REFERENCES users(id),
                created_at TEXT NOT NULL
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS notification_reads (
                notification_id INTEGER NOT NULL REFERENCES notifications(id) ON DELETE CASCADE,
                user_id INTEGER NOT NULL,
                read_at TEXT NOT NULL,
                PRIMARY KEY (notification_id, user_id)
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS officer_suspensions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
                branch_id INTEGER NOT NULL REFERENCES branches(id),
                reason TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT,
                status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','ended')),
                created_at TEXT NOT NULL,
                created_by INTEGER REFERENCES users(id)
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS officer_disciplinary (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
                branch_id INTEGER NOT NULL REFERENCES branches(id),
                reason TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT,
                status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','closed')),
                created_at TEXT NOT NULL,
                created_by INTEGER REFERENCES users(id)
            )");


            $pdo->exec("PRAGMA user_version = 1");
            $pdo->exec("COMMIT");
            $pdo->exec("PRAGMA foreign_keys = ON");

        } catch (Throwable $e) {
            $pdo->exec("ROLLBACK");
            $pdo->exec("PRAGMA foreign_keys = ON");
            throw $e;
        }
    }
}

function seed_data(PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = :t LIMIT 1");
        $stmt->execute([':t' => $table]);
        return (bool)$stmt->fetchColumn();
    };

    $branches = [
        ['Kampala HQ', 'KLA', 'Kampala'],
        ['Rwizi', 'RWZ', 'Mbarara'],
        ['N.Kyoga', 'NKY', 'Lira'],
    ];
    $stmt = $pdo->prepare("INSERT INTO branches (name, code, location) VALUES (?,?,?)");
    foreach ($branches as $b) {
        $stmt->execute($b);
    }

    $bIds = [];
    foreach ($pdo->query("SELECT id, code FROM branches") as $r) {
        $bIds[$r['code']] = (int)$r['id'];
    }

    $users = [
        ['admin', 'admin123', 'System Administrator', 'admin', null],
        ['kmgr', 'kmgr123', 'Kampala HQ Manager', 'manager', $bIds['KLA']],
        ['rmgr', 'rmgr123', 'Rwizi Manager', 'manager', $bIds['RWZ']],
        ['nmgr', 'nmgr123', 'N.Kyoga Manager', 'manager', $bIds['NKY']],
    ];

    // Seed users with placeholder emails (admin will be updated from the Users UI)
    $ustmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role, branch_id) VALUES (?,?,?,?,?,?)");
    foreach ($users as $u) {
        $email = $u[0] . '@example.com';
        $ustmt->execute([
            $u[0],
            $email,
            password_hash($u[1], PASSWORD_DEFAULT),
            $u[2],
            $u[3],
            $u[4],
        ]);
    }

    $ranks = ['PC','CPL','SGT','S/SGT','HC','HCM','AIP','IP','ASP','SP','SSP','ACP','CP','SCP','AIGP','DIGP','IGP'];
    $first = ['John','Mary','Peter','Grace','Samuel','Esther','David','Joyce','Robert','Sarah','Moses','Ruth','James','Agnes','Paul','Joan'];
    $last = ['Okello','Nakato','Mugisha','Akello','Kato','Namukasa','Wasswa','Nakimera','Opio','Kintu','Bwambale','Nabukenya'];
    $estmt = $pdo->prepare("INSERT INTO employees (service_no, full_name, gender, rank, branch_id, phone, email) VALUES (?,?,?,?,?,?,?)");
    $sn = 10001;
    foreach ($bIds as $code => $bid) {
        for ($i = 0; $i < 12; $i++) {
            $g = $i % 3 === 0 ? 'F' : 'M';
            $name = $first[array_rand($first)] . ' ' . $last[array_rand($last)];
            $rank = $ranks[array_rand($ranks)];
            $phone = '+25670' . random_int(1000000, 9999999);
            $email = strtolower(preg_replace('/[^a-z0-9]+/','.', trim($name))) . '.' . $sn . '@example.com';
            $estmt->execute(['UPF' . $sn++, $name, $g, $rank, $bid, $phone, $email]);
        }
    }

    $today = date('Y-m-d');
    $statuses = ['present','present','present','present','present','awol','leave','sick','onleave'];
    $dstmt = $pdo->prepare("INSERT INTO daily_status (employee_id, date, status, notes, recorded_by) VALUES (?,?,?,?,1)");
    foreach ($pdo->query("SELECT id FROM employees") as $e) {
        $dstmt->execute([(int)$e['id'], $today, $statuses[array_rand($statuses)], null]);
    }

    // Seed sample suspensions & disciplinary records.
    // Older/partial DBs may not have these tables yet; guard to avoid fatal errors.
    $now = date('Y-m-d H:i:s');
    $empIds = $pdo->query("SELECT id, branch_id FROM employees")->fetchAll();

    $hasSuspensions = $tableExists($pdo, 'officer_suspensions');
    $hasDisciplinary = $tableExists($pdo, 'officer_disciplinary');

    $susStmt = null;
    if ($hasSuspensions) {
        $susStmt = $pdo->prepare(
            "INSERT INTO officer_suspensions (employee_id, branch_id, reason, start_date, end_date, status, created_at, created_by) VALUES (?,?,?,?,?,?,?,?)"
        );
    }

    $disStmt = null;
    if ($hasDisciplinary) {
        $disStmt = $pdo->prepare(
            "INSERT INTO officer_disciplinary (employee_id, branch_id, reason, start_date, end_date, status, created_at, created_by) VALUES (?,?,?,?,?,?,?,?)"
        );
    }

    $createdByVal = $pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetchColumn();
    $createdBy = $createdByVal !== false ? (int)$createdByVal : null;

    foreach ($empIds as $i => $row) {
        $eid = (int)$row['id'];
        $bid = (int)$row['branch_id'];

        if ($susStmt && ($i % 9 === 0)) {
            $susStmt->execute([
                $eid,
                $bid,
                'Interdiction pending investigation',
                $today,
                null,
                'active',
                $now,
                $createdBy,
            ]);
        }

        if ($disStmt && ($i % 11 === 0)) {
            $disStmt->execute([
                $eid,
                $bid,
                'Disciplinary action (case review)',
                $today,
                null,
                'active',
                $now,
                $createdBy,
            ]);
        }
    }
}





