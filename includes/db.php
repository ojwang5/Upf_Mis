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
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS branches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            code TEXT NOT NULL UNIQUE,
            location TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
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
            active INTEGER NOT NULL DEFAULT 1
        );
        CREATE TABLE IF NOT EXISTS daily_status (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
            date TEXT NOT NULL,
            status TEXT NOT NULL CHECK(status IN ('present','awol','leave','sick')),
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
    ");

    $count = (int)$pdo->query("SELECT COUNT(*) FROM branches")->fetchColumn();
    if ($count === 0) {
        seed_data($pdo);
    }
}

function migrate(PDO $pdo): void {
    $v = (int)$pdo->query("PRAGMA user_version")->fetchColumn();

    if ($v < 1) {
        // v1: ensure 'officer' allowed in users.role; add report workflow fields; create leave_requests + notifications
        // foreign_keys must be toggled outside a transaction
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
            $pdo->exec("INSERT INTO users_new (id, username, password_hash, full_name, role, branch_id) SELECT id, username, password_hash, full_name, role, branch_id FROM users");
            $pdo->exec("DROP TABLE users");
            $pdo->exec("ALTER TABLE users_new RENAME TO users");

            // Report workflow columns
            $cols = [];
            foreach ($pdo->query("PRAGMA table_info(reports)") as $c) $cols[$c['name']] = true;
            if (!isset($cols['status']))       $pdo->exec("ALTER TABLE reports ADD COLUMN status TEXT NOT NULL DEFAULT 'approved'");
            if (!isset($cols['reviewed_by']))  $pdo->exec("ALTER TABLE reports ADD COLUMN reviewed_by INTEGER");
            if (!isset($cols['reviewed_at']))  $pdo->exec("ALTER TABLE reports ADD COLUMN reviewed_at TEXT");
            if (!isset($cols['review_notes'])) $pdo->exec("ALTER TABLE reports ADD COLUMN review_notes TEXT");

            // Leave requests
            $pdo->exec("CREATE TABLE IF NOT EXISTS leave_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
                branch_id INTEGER NOT NULL REFERENCES branches(id),
                leave_type TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                reason TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending_manager',
                submitted_by INTEGER REFERENCES users(id),
                submitted_at TEXT NOT NULL,
                manager_reviewed_by INTEGER REFERENCES users(id),
                manager_reviewed_at TEXT,
                manager_notes TEXT,
                admin_reviewed_by INTEGER REFERENCES users(id),
                admin_reviewed_at TEXT,
                admin_notes TEXT
            )");

            // Notifications
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

            $pdo->exec("PRAGMA user_version = 1");
            $pdo->exec("COMMIT");
            $pdo->exec("PRAGMA foreign_keys = ON");
        } catch (Throwable $e) {
            $pdo->exec("ROLLBACK");
            $pdo->exec("PRAGMA foreign_keys = ON");
            throw $e;
        }
    }

    // Seed an officer account if none exists
    $hasOfficer = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='officer'")->fetchColumn();
    if ($hasOfficer === 0) {
        $kla = $pdo->query("SELECT id FROM branches WHERE code='KLA'")->fetchColumn();
        if ($kla) {
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, branch_id) VALUES (?,?,?,?,?)");
            $stmt->execute(['kofc', password_hash('kofc123', PASSWORD_DEFAULT), 'Kampala Field Officer', 'officer', (int)$kla]);
        }
    }
}

function seed_data(PDO $pdo): void {
    $branches = [
        ['Kampala HQ', 'KLA', 'Kampala'],
        ['Rwizi', 'RWZ', 'Mbarara'],
        ['N.Kyoga', 'NKY', 'Lira'],
    ];
    $stmt = $pdo->prepare("INSERT INTO branches (name, code, location) VALUES (?,?,?)");
    foreach ($branches as $b) { $stmt->execute($b); }

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
    $ustmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, branch_id) VALUES (?,?,?,?,?)");
    foreach ($users as $u) {
        $ustmt->execute([$u[0], password_hash($u[1], PASSWORD_DEFAULT), $u[2], $u[3], $u[4]]);
    }

    $ranks = ['Constable','Corporal','Sergeant','Inspector','ASP','SP'];
    $first = ['John','Mary','Peter','Grace','Samuel','Esther','David','Joyce','Robert','Sarah','Moses','Ruth','James','Agnes','Paul','Joan'];
    $last = ['Okello','Nakato','Mugisha','Akello','Kato','Namukasa','Wasswa','Nakimera','Opio','Kintu','Bwambale','Nabukenya'];
    $estmt = $pdo->prepare("INSERT INTO employees (service_no, full_name, gender, rank, branch_id, phone) VALUES (?,?,?,?,?,?)");
    $sn = 10001;
    foreach ($bIds as $code => $bid) {
        for ($i = 0; $i < 12; $i++) {
            $g = $i % 3 === 0 ? 'F' : 'M';
            $name = $first[array_rand($first)] . ' ' . $last[array_rand($last)];
            $rank = $ranks[array_rand($ranks)];
            $phone = '+25670' . random_int(1000000, 9999999);
            $estmt->execute(['UPF' . $sn++, $name, $g, $rank, $bid, $phone]);
        }
    }

    $today = date('Y-m-d');
    $statuses = ['present','present','present','present','present','awol','leave','sick'];
    $dstmt = $pdo->prepare("INSERT INTO daily_status (employee_id, date, status, notes, recorded_by) VALUES (?,?,?,?,1)");
    foreach ($pdo->query("SELECT id FROM employees") as $e) {
        $dstmt->execute([(int)$e['id'], $today, $statuses[array_rand($statuses)], null]);
    }
}
