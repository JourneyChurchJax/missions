<?php
// Database: one SQLite file kept OUTSIDE public_html (missions.journeychurch.org/data/missions.sqlite).
// To move to MySQL later, add 'db' => ['dsn' => 'mysql:host=...;dbname=...', 'user' => ..., 'pass' => ...] to config.php.

const SCHEMA_VERSION = 1;

function data_dir(): string {
    $dir = dirname(__DIR__, 2) . '/data';
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    return $dir;
}

// Site settings that live outside the database (so they survive switching databases)
function site_settings(): array {
    $f = data_dir() . '/settings.json';
    return is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
}
function save_site_settings(array $s): void {
    file_put_contents(data_dir() . '/settings.json', json_encode($s + site_settings(), JSON_PRETTY_PRINT), LOCK_EX);
}
function demo_on(): bool { return !empty(site_settings()['demo']); }
function demo_db_path(): string { return data_dir() . '/demo.sqlite'; }

// Real data: journey.sqlite (or MySQL from config). Demo data: demo.sqlite, a separate file the Settings switch turns on.
function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo) return $pdo;
    if (demo_on()) {
        $pdo = new PDO('sqlite:' . demo_db_path());
        $pdo->exec('PRAGMA journal_mode = WAL;');
    } elseif (!empty($config['db']['dsn'])) {
        $pdo = new PDO($config['db']['dsn'], $config['db']['user'] ?? null, $config['db']['pass'] ?? null);
    } else {
        $pdo = new PDO('sqlite:' . data_dir() . '/journey.sqlite');
        $pdo->exec('PRAGMA journal_mode = WAL;');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    migrate($pdo);
    return $pdo;
}

function driver(): string { return db()->getAttribute(PDO::ATTR_DRIVER_NAME); }

// Tables as plain lists so the same definition works on SQLite and MySQL.
function schema(): array {
    return [
        'meta' => ['k' => 'key', 'v' => 'text'],
        'trips' => ['id' => 'pk', 'slug' => 'str', 'name' => 'str', 'public_name' => 'str', 'city' => 'str', 'country' => 'str',
            'partner' => 'str', 'start_date' => 'date', 'end_date' => 'date', 'description' => 'text', 'qualifications' => 'text',
            'cost_per_person' => 'money', 'max_team' => 'int', 'app_deadline' => 'date', 'status' => 'str', 'group_name' => 'str',
            'passport_valid_through' => 'date', 'created_at' => 'datetime'],
        'people' => ['id' => 'pk', 'first_name' => 'str', 'preferred_name' => 'str', 'last_name' => 'str', 'email' => 'str', 'phone' => 'str',
            'birth_date' => 'date', 'gender' => 'str', 'address' => 'str', 'city' => 'str', 'state' => 'str', 'zip' => 'str', 'shirt' => 'str',
            'passport_name' => 'str', 'passport_number' => 'str', 'passport_country' => 'str', 'passport_issued' => 'date', 'passport_expires' => 'date',
            'nationality' => 'str', 'ec1_name' => 'str', 'ec1_rel' => 'str', 'ec1_phone' => 'str', 'ec1_email' => 'str',
            'ec2_name' => 'str', 'ec2_rel' => 'str', 'ec2_phone' => 'str', 'health' => 'text', 'diet' => 'text', 'allergies' => 'text',
            'meds' => 'text', 'other' => 'text', 'notes' => 'text', 'pco_id' => 'str', 'tags' => 'str', 'verified_at' => 'datetime', 'created_at' => 'datetime'],
        'members' => ['id' => 'pk', 'trip_id' => 'int', 'person_id' => 'int', 'role' => 'str', 'traveling' => 'bool', 'goal' => 'money',
            'raised' => 'money', 'confirmation' => 'str', 'room' => 'str', 'seat' => 'str', 'created_at' => 'datetime'],
        'tasks' => ['id' => 'pk', 'trip_id' => 'int', 'title' => 'str', 'description' => 'text', 'type' => 'str', 'due_date' => 'date',
            'minors_only' => 'bool', 'allow_self' => 'bool', 'created_at' => 'datetime'],
        'task_done' => ['id' => 'pk', 'task_id' => 'int', 'person_id' => 'int', 'done_at' => 'datetime', 'file_id' => 'int'],
        'goals' => ['id' => 'pk', 'trip_id' => 'int', 'due_date' => 'date', 'kind' => 'str', 'amount' => 'money'],
        'meetings' => ['id' => 'pk', 'trip_id' => 'int', 'title' => 'str', 'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'location' => 'str', 'address' => 'str', 'notes' => 'text'],
        'attendance' => ['id' => 'pk', 'meeting_id' => 'int', 'person_id' => 'int', 'present' => 'bool'],
        'files' => ['id' => 'pk', 'trip_id' => 'int', 'person_id' => 'int', 'title' => 'str', 'note' => 'str', 'original' => 'str',
            'mime' => 'str', 'size' => 'int', 'path' => 'str', 'url' => 'str', 'visible' => 'bool', 'must_ack' => 'bool', 'kind' => 'str', 'created_at' => 'datetime'],
        'file_acks' => ['id' => 'pk', 'file_id' => 'int', 'person_id' => 'int', 'opened_at' => 'datetime', 'acked_at' => 'datetime'],
        'guide' => ['id' => 'pk', 'trip_id' => 'int', 'section' => 'str', 'body' => 'text', 'updated_at' => 'datetime'],
        'itinerary' => ['id' => 'pk', 'trip_id' => 'int', 'day' => 'date', 'time' => 'str', 'title' => 'str', 'detail' => 'str'],
        'flights' => ['id' => 'pk', 'trip_id' => 'int', 'direction' => 'str', 'airline' => 'str', 'flight_no' => 'str', 'from_code' => 'str',
            'to_code' => 'str', 'departs_at' => 'datetime', 'arrives_at' => 'datetime', 'notes' => 'str'],
        'announcements' => ['id' => 'pk', 'trip_id' => 'int', 'title' => 'str', 'body' => 'text', 'author' => 'str', 'created_at' => 'datetime'],
        'budget' => ['id' => 'pk', 'trip_id' => 'int', 'description' => 'str', 'type' => 'str', 'vendor' => 'str', 'unit_cost' => 'money',
            'qty' => 'int', 'per_traveler' => 'bool', 'est_date' => 'date'],
        'activity' => ['id' => 'pk', 'trip_id' => 'int', 'who' => 'str', 'what' => 'str', 'created_at' => 'datetime'],
    ];
}

function col_sql(string $type, string $drv): string {
    $my = $drv === 'mysql';
    return match ($type) {
        'pk' => $my ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT',
        'key' => $my ? 'VARCHAR(100) PRIMARY KEY' : 'TEXT PRIMARY KEY',
        'int' => $my ? 'INT NULL' : 'INTEGER',
        'str' => $my ? 'VARCHAR(255) NULL' : 'TEXT',
        'text' => $my ? 'TEXT NULL' : 'TEXT',
        'date' => $my ? 'DATE NULL' : 'TEXT',
        'datetime' => $my ? 'DATETIME NULL' : 'TEXT',
        'bool' => $my ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
        'money' => $my ? 'DECIMAL(10,2) NULL' : 'NUMERIC',
    };
}

function migrate(PDO $pdo): void {
    $drv = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $pdo->exec('CREATE TABLE IF NOT EXISTS meta (k ' . col_sql('key', $drv) . ', v ' . col_sql('text', $drv) . ')');
    $v = (int)($pdo->query("SELECT v FROM meta WHERE k = 'schema'")->fetchColumn() ?: 0);
    if ($v >= SCHEMA_VERSION) return;
    foreach (schema() as $table => $cols) {
        $defs = [];
        foreach ($cols as $c => $t) $defs[] = "$c " . col_sql($t, $drv);
        $pdo->exec("CREATE TABLE IF NOT EXISTS $table (" . implode(', ', $defs) . ')');
        // Add any columns that are new since the table was created
        $have = $drv === 'mysql'
            ? array_column($pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_ASSOC), 'Field')
            : array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach ($cols as $c => $t) {
            if (!in_array($c, $have, true) && $t !== 'pk' && $t !== 'key') $pdo->exec("ALTER TABLE $table ADD COLUMN $c " . col_sql($t, $drv));
        }
    }
    $pdo->prepare($drv === 'mysql' ? 'REPLACE INTO meta (k, v) VALUES (?, ?)' : 'INSERT OR REPLACE INTO meta (k, v) VALUES (?, ?)')->execute(['schema', (string)SCHEMA_VERSION]);
    if ($v === 0) { require_once __DIR__ . '/seed.php'; demo_on() ? seed_demo($pdo) : seed($pdo); }
}

// Small query helpers
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function all(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function one(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function val(string $sql, array $p = []) { return q($sql, $p)->fetchColumn(); }
function insert(string $table, array $row): int {
    $cols = array_keys($row);
    q("INSERT INTO $table (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($row));
    return (int)db()->lastInsertId();
}
function update(string $table, int $id, array $row): void {
    if (!$row) return;
    $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($row)));
    q("UPDATE $table SET $set WHERE id = ?", [...array_values($row), $id]);
}
function delete_row(string $table, int $id): void { q("DELETE FROM $table WHERE id = ?", [$id]); }
function now(): string { return date('Y-m-d H:i:s'); }
function log_activity(?int $trip_id, string $what): void {
    insert('activity', ['trip_id' => $trip_id, 'who' => current_actor_name(), 'what' => $what, 'created_at' => now()]);
}
