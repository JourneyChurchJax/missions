<?php
// Database: one SQLite file kept OUTSIDE public_html (missions.journeychurch.org/data/missions.sqlite).
// To move to MySQL later, add 'db' => ['dsn' => 'mysql:host=...;dbname=...', 'user' => ..., 'pass' => ...] to config.php.

const SCHEMA_VERSION = 6;

function data_dir(): string {
    $dir = dirname(__DIR__, 2) . '/data';
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    // Backstop: even if this folder ever ended up inside the website, the server refuses to serve it
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
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
// Demo data is per person: only the staff member who turns it on sees it. Public pages, applications,
// sign-in and the Stripe webhook define REAL_DB so they always use the real data.
function demo_on(): bool { return !defined('REAL_DB') && !empty($_SESSION['demo']) && is_staff_session(); }
function upload_dir(): string { $d = data_dir() . (demo_on() ? '/uploads-demo' : '/uploads'); if (!is_dir($d)) mkdir($d, 0750, true); return $d; }
function demo_db_path(): string { return data_dir() . '/demo.sqlite'; }

// Real data: journey.sqlite (or MySQL from config). Demo data: demo.sqlite, a separate file the Settings switch turns on.
function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo) return $pdo;
    if (demo_on()) {
        $pdo = new PDO('sqlite:' . demo_db_path());
        $pdo->exec('PRAGMA journal_mode = WAL;'); $pdo->exec('PRAGMA busy_timeout = 5000;');
    } elseif (!empty($config['db']['dsn'])) {
        $dsn = $config['db']['dsn']; if (str_starts_with($dsn, 'mysql:') && !str_contains($dsn, 'charset=')) $dsn .= ';charset=utf8mb4';
        $pdo = new PDO($dsn, $config['db']['user'] ?? null, $config['db']['pass'] ?? null);
    } else {
        $pdo = new PDO('sqlite:' . data_dir() . '/journey.sqlite');
        $pdo->exec('PRAGMA journal_mode = WAL;'); $pdo->exec('PRAGMA busy_timeout = 5000;');
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
            'passport_valid_through' => 'date', 'cal_token' => 'str', 'bg_required' => 'str', 'timezone' => 'str', 'created_at' => 'datetime'],
        'people' => ['id' => 'pk', 'first_name' => 'str', 'preferred_name' => 'str', 'last_name' => 'str', 'email' => 'str', 'phone' => 'str',
            'birth_date' => 'date', 'gender' => 'str', 'address' => 'str', 'city' => 'str', 'state' => 'str', 'zip' => 'str', 'shirt' => 'str',
            'passport_name' => 'str', 'passport_number' => 'str', 'passport_country' => 'str', 'passport_issued' => 'date', 'passport_expires' => 'date',
            'nationality' => 'str', 'ec1_name' => 'str', 'ec1_rel' => 'str', 'ec1_phone' => 'str', 'ec1_email' => 'str',
            'ec2_name' => 'str', 'ec2_rel' => 'str', 'ec2_phone' => 'str', 'health' => 'text', 'diet' => 'text', 'allergies' => 'text',
            'meds' => 'text', 'other' => 'text', 'notes' => 'text', 'pco_id' => 'str', 'tags' => 'str', 'verified_at' => 'datetime', 'cal_token' => 'str', 'pco_synced_at' => 'datetime', 'is_staff' => 'bool', 'sms_ok' => 'bool', 'created_at' => 'datetime'],
        'members' => ['id' => 'pk', 'trip_id' => 'int', 'person_id' => 'int', 'role' => 'str', 'traveling' => 'bool', 'goal' => 'money',
            'raised' => 'money', 'confirmation' => 'str', 'room' => 'str', 'seat' => 'str',
            'page_slug' => 'str', 'page_story' => 'text', 'page_status' => 'str', 'page_photo_id' => 'int', 'created_at' => 'datetime'],
        'tasks' => ['id' => 'pk', 'trip_id' => 'int', 'title' => 'str', 'description' => 'text', 'type' => 'str', 'due_date' => 'date',
            'minors_only' => 'bool', 'allow_self' => 'bool', 'file_id' => 'int', 'parent_sign' => 'bool', 'created_at' => 'datetime'],
        'task_done' => ['id' => 'pk', 'task_id' => 'int', 'person_id' => 'int', 'done_at' => 'datetime', 'file_id' => 'int'],
        'goals' => ['id' => 'pk', 'trip_id' => 'int', 'due_date' => 'date', 'kind' => 'str', 'amount' => 'money'],
        'meetings' => ['id' => 'pk', 'trip_id' => 'int', 'title' => 'str', 'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'location' => 'str', 'address' => 'str', 'notes' => 'text'],
        'attendance' => ['id' => 'pk', 'meeting_id' => 'int', 'person_id' => 'int', 'present' => 'bool'],
        'files' => ['id' => 'pk', 'trip_id' => 'int', 'person_id' => 'int', 'title' => 'str', 'note' => 'str', 'original' => 'str',
            'mime' => 'str', 'size' => 'int', 'path' => 'str', 'url' => 'str', 'visible' => 'bool', 'must_ack' => 'bool', 'kind' => 'str', 'created_at' => 'datetime', 'sort' => 'int', 'sha256' => 'str'],
        'file_acks' => ['id' => 'pk', 'file_id' => 'int', 'person_id' => 'int', 'opened_at' => 'datetime', 'acked_at' => 'datetime'],
        'guide' => ['id' => 'pk', 'trip_id' => 'int', 'section' => 'str', 'body' => 'text', 'updated_at' => 'datetime'],
        'itinerary' => ['id' => 'pk', 'trip_id' => 'int', 'day' => 'date', 'time' => 'str', 'title' => 'str', 'detail' => 'str'],
        'flights' => ['id' => 'pk', 'trip_id' => 'int', 'direction' => 'str', 'airline' => 'str', 'flight_no' => 'str', 'from_code' => 'str',
            'to_code' => 'str', 'departs_at' => 'datetime', 'arrives_at' => 'datetime', 'notes' => 'str'],
        'announcements' => ['id' => 'pk', 'trip_id' => 'int', 'title' => 'str', 'body' => 'text', 'author' => 'str', 'created_at' => 'datetime'],
        'budget' => ['id' => 'pk', 'trip_id' => 'int', 'description' => 'str', 'type' => 'str', 'vendor' => 'str', 'unit_cost' => 'money',
            'qty' => 'int', 'per_traveler' => 'bool', 'est_date' => 'date'],
        'activity' => ['id' => 'pk', 'trip_id' => 'int', 'who' => 'str', 'what' => 'text', 'created_at' => 'datetime'],
        // Phase 2: applications
        'app_forms' => ['id' => 'pk', 'name' => 'str', 'slug' => 'str', 'intro' => 'text', 'closes_on' => 'date', 'published' => 'bool',
            'trip_mode' => 'str', 'trip_ids' => 'str', 'choices' => 'int', 'refs_required' => 'int', 'ref_types' => 'text',
            'deposit' => 'money', 'deposit_tax' => 'bool', 'photo_required' => 'bool', 'submitted_message' => 'text', 'created_at' => 'datetime'],
        'app_questions' => ['id' => 'pk', 'form_id' => 'int', 'sort' => 'int', 'kind' => 'str', 'label' => 'str', 'help' => 'str', 'options' => 'text', 'required' => 'bool'],
        'app_discounts' => ['id' => 'pk', 'form_id' => 'int', 'code' => 'str', 'kind' => 'str', 'amount' => 'money', 'early_bird' => 'bool', 'expires_on' => 'date'],
        'applications' => ['id' => 'pk', 'form_id' => 'int', 'person_id' => 'int', 'status' => 'str', 'token' => 'str', 'step' => 'str',
            'choice1' => 'int', 'choice2' => 'int', 'choice3' => 'int', 'answers' => 'text', 'discount_code' => 'str',
            'deposit_due' => 'money', 'deposit_status' => 'str', 'assigned_trip_id' => 'int', 'decision_note' => 'text', 'decided_by' => 'str',
            'submitted_at' => 'datetime', 'decided_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime', 'stripe_pi' => 'str', 'guardian_consent_at' => 'datetime'],
        'app_refs' => ['id' => 'pk', 'application_id' => 'int', 'ref_type' => 'str', 'name' => 'str', 'email' => 'str', 'phone' => 'str',
            'token' => 'str', 'status' => 'str', 'answers' => 'text', 'requested_at' => 'datetime', 'received_at' => 'datetime'],
        // Phase 3: money
        'donors' => ['id' => 'pk', 'first_name' => 'str', 'last_name' => 'str', 'org' => 'str', 'email' => 'str', 'phone' => 'str',
            'address' => 'str', 'city' => 'str', 'state' => 'str', 'zip' => 'str', 'pco_id' => 'str', 'notes' => 'text', 'created_at' => 'datetime', 'stripe_customer' => 'str'],
        'batches' => ['id' => 'pk', 'name' => 'str', 'deposit_date' => 'date', 'status' => 'str', 'created_by' => 'str', 'created_at' => 'datetime', 'closed_at' => 'datetime'],
        'gifts' => ['id' => 'pk', 'donor_id' => 'int', 'trip_id' => 'int', 'person_id' => 'int', 'amount' => 'money', 'fee' => 'money',
            'method' => 'str', 'check_no' => 'str', 'batch_id' => 'int', 'gift_date' => 'date', 'anonymous' => 'bool', 'note' => 'text',
            'source' => 'str', 'status' => 'str', 'thanked_at' => 'datetime', 'created_by' => 'str', 'created_at' => 'datetime',
            'stripe_id' => 'str', 'message' => 'text', 'recurring_id' => 'int', 'stripe_pi' => 'str', 'refunded' => 'money', 'covered_fee' => 'money',
            'void_reason' => 'str', 'voided_by' => 'str', 'voided_at' => 'datetime'],
        'payments' => ['id' => 'pk', 'trip_id' => 'int', 'person_id' => 'int', 'amount' => 'money', 'method' => 'str', 'kind' => 'str',
            'paid_on' => 'date', 'note' => 'text', 'created_by' => 'str', 'created_at' => 'datetime', 'stripe_id' => 'str',
            'status' => 'str', 'application_id' => 'int', 'void_reason' => 'str', 'voided_by' => 'str', 'voided_at' => 'datetime'],
        'expenses' => ['id' => 'pk', 'trip_id' => 'int', 'type' => 'str', 'description' => 'str', 'vendor' => 'str', 'amount' => 'money',
            'currency' => 'str', 'rate' => 'money', 'usd' => 'money', 'spent_on' => 'date', 'paid_by' => 'str', 'receipt_file_id' => 'int',
            'reimburse' => 'bool', 'reimbursed_at' => 'datetime', 'created_by' => 'str', 'created_at' => 'datetime', 'status' => 'str', 'void_reason' => 'str'],
        // Phase 4: communication and on-the-trip tools
        'outbox' => ['id' => 'pk', 'trip_id' => 'int', 'person_id' => 'int', 'channel' => 'str', 'to_addr' => 'str', 'subject' => 'str',
            'body' => 'text', 'status' => 'str', 'error' => 'text', 'created_by' => 'str', 'created_at' => 'datetime', 'attempts' => 'int', 'send_after' => 'datetime', 'sent_at' => 'datetime'],
        'guardians' => ['id' => 'pk', 'person_id' => 'int', 'name' => 'str', 'rel' => 'str', 'email' => 'str', 'phone' => 'str', 'token' => 'str', 'created_at' => 'datetime',
            'otp_hash' => 'str', 'otp_expires' => 'int', 'otp_tries' => 'int', 'verified_at' => 'datetime', 'sms_ok' => 'bool'],
        'checkins' => ['id' => 'pk', 'trip_id' => 'int', 'label' => 'str', 'created_by' => 'str', 'created_at' => 'datetime'],
        'checkin_marks' => ['id' => 'pk', 'checkin_id' => 'int', 'person_id' => 'int', 'status' => 'str', 'marked_at' => 'datetime'],
        'incidents' => ['id' => 'pk', 'trip_id' => 'int', 'person_id' => 'int', 'happened_at' => 'datetime', 'kind' => 'str', 'severity' => 'str',
            'description' => 'text', 'action_taken' => 'text', 'parent_notified' => 'bool', 'followup' => 'text', 'resolved' => 'bool',
            'reported_by' => 'str', 'created_at' => 'datetime'],
        'chat' => ['id' => 'pk', 'trip_id' => 'int', 'thread' => 'str', 'person_id' => 'int', 'author' => 'str', 'staff' => 'bool', 'body' => 'text', 'created_at' => 'datetime'],
        // Phase 5: signatures, background checks, Stripe
        'signatures' => ['id' => 'pk', 'task_id' => 'int', 'person_id' => 'int', 'guardian_id' => 'int', 'signer_role' => 'str', 'signer_name' => 'str',
            'sig_image' => 'text', 'agreement' => 'text', 'doc_title' => 'str', 'ip' => 'str', 'user_agent' => 'str', 'signed_at' => 'datetime',
            'sig_mode' => 'str', 'doc_file_id' => 'int', 'doc_hash' => 'str', 'recorded_by' => 'str'],
        'background_checks' => ['id' => 'pk', 'person_id' => 'int', 'provider' => 'str', 'status' => 'str', 'requested_at' => 'date', 'completed_at' => 'date',
            'expires_on' => 'date', 'note' => 'text', 'pco_id' => 'str', 'created_by' => 'str', 'created_at' => 'datetime'],
        'recurring' => ['id' => 'pk', 'donor_id' => 'int', 'trip_id' => 'int', 'person_id' => 'int', 'amount' => 'money', 'stripe_sub_id' => 'str',
            'status' => 'str', 'created_at' => 'datetime', 'canceled_at' => 'datetime'],
        'stripe_events' => ['id' => 'key', 'type' => 'str', 'received_at' => 'datetime'],
        // Audit and safety (v6)
        'audit' => ['id' => 'pk', 'at' => 'datetime', 'actor' => 'str', 'actor_person_id' => 'int', 'via' => 'str', 'action' => 'str',
            'entity' => 'str', 'entity_id' => 'int', 'trip_id' => 'int', 'detail' => 'text', 'ip' => 'str'],
        'rate_hits' => ['id' => 'pk', 'k' => 'str', 'at' => 'int'],
        'sms_optout' => ['id' => 'key', 'at' => 'datetime'],
        'login_codes' => ['id' => 'pk', 'person_id' => 'int', 'pco_id' => 'str', 'code_hash' => 'str', 'expires' => 'int', 'tries' => 'int', 'created_at' => 'datetime'],
    ];
}

// Indexes: lookups by foreign key, and unique rules that stop duplicates (money, slugs, tokens)
function indexes(): array {
    return [
        ['ux_members_trip_person', 'members', 'trip_id, person_id', true], ['ux_task_done', 'task_done', 'task_id, person_id', true],
        ['ux_gifts_stripe', 'gifts', 'stripe_id', true], ['ux_payments_stripe', 'payments', 'stripe_id', true],
        ['ux_members_slug', 'members', 'page_slug', true], ['ux_app_token', 'applications', 'token', true], ['ux_ref_token', 'app_refs', 'token', true],
        ['ux_guardian_token', 'guardians', 'token', true], ['ux_forms_slug', 'app_forms', 'slug', true], ['ux_recurring_sub', 'recurring', 'stripe_sub_id', true],
        ['ix_members_person', 'members', 'person_id', false], ['ix_tasks_trip', 'tasks', 'trip_id', false], ['ix_files_trip', 'files', 'trip_id', false],
        ['ix_files_person', 'files', 'person_id', false], ['ix_gifts_trip', 'gifts', 'trip_id, person_id', false], ['ix_gifts_donor', 'gifts', 'donor_id', false],
        ['ix_payments_trip', 'payments', 'trip_id, person_id', false], ['ix_chat_thread', 'chat', 'trip_id, thread', false], ['ix_sig_task', 'signatures', 'task_id, person_id', false],
        ['ix_people_email', 'people', 'email', false], ['ix_people_pco', 'people', 'pco_id', false], ['ix_activity_trip', 'activity', 'trip_id', false],
        ['ix_audit_at', 'audit', 'at', false], ['ix_rate_k', 'rate_hits', 'k, at', false], ['ix_outbox_status', 'outbox', 'status', false],
        ['ix_acks_file', 'file_acks', 'file_id, person_id', false], ['ix_att_meeting', 'attendance', 'meeting_id', false], ['ix_guardians_person', 'guardians', 'person_id', false],
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
    $read = fn() => (int)($pdo->query("SELECT v FROM meta WHERE k = 'schema'")->fetchColumn() ?: 0);
    if ($read() >= SCHEMA_VERSION) return;
    // Only one request upgrades at a time; the others wait, then see it's done
    $lock = fopen(data_dir() . '/migrate.lock', 'c');
    flock($lock, LOCK_EX);
    try {
        $v = $read();
        if ($v >= SCHEMA_VERSION) return;
        if ($v > 0) backup_now('before-upgrade-v' . $v);
        foreach (schema() as $table => $cols) {
            $defs = [];
            foreach ($cols as $c => $t) $defs[] = "$c " . col_sql($t, $drv);
            $pdo->exec("CREATE TABLE IF NOT EXISTS $table (" . implode(', ', $defs) . ')' . ($drv === 'mysql' ? ' DEFAULT CHARSET=utf8mb4' : ''));
            $have = $drv === 'mysql'
                ? array_column($pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_ASSOC), 'Field')
                : array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC), 'name');
            foreach ($cols as $c => $t) {
                if (!in_array($c, $have, true) && $t !== 'pk' && $t !== 'key') $pdo->exec("ALTER TABLE $table ADD COLUMN $c " . col_sql($t, $drv));
            }
        }
        require_once __DIR__ . '/seed.php';
        $pdo->beginTransaction();
        try {
            if ($v === 0) { demo_on() ? seed_demo($pdo) : seed($pdo); upgrade_v6(); }
            else {
                if ($v < 2) { demo_on() ? seed_apps_demo() : seed_apps_real(); }
                if ($v < 4 && demo_on()) { seed_money_demo(); seed_comms_demo(); }
                if ($v < 5) seed_phase5(demo_on());
                if ($v < 6) upgrade_v6();
            }
            $pdo->prepare($drv === 'mysql' ? 'REPLACE INTO meta (k, v) VALUES (?, ?)' : 'INSERT OR REPLACE INTO meta (k, v) VALUES (?, ?)')->execute(['schema', (string)SCHEMA_VERSION]);
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        foreach (indexes() as [$name, $table, $cols, $unique]) {
            try { $pdo->exec('CREATE ' . ($unique ? 'UNIQUE ' : '') . "INDEX " . ($drv === 'mysql' ? '' : 'IF NOT EXISTS ') . "$name ON $table ($cols)"); }
            catch (Throwable $e) { if (!str_contains($e->getMessage(), 'Duplicate key name') && !str_contains($e->getMessage(), 'already exists')) app_log("Index $name: " . $e->getMessage(), 'errors'); }
        }
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

// A consistent copy of the database (safe while the site is running). Keeps the newest 30.
function backup_now(string $label = 'daily'): ?string {
    global $config;
    if (!empty($config['db']['dsn']) || demo_on()) return null;
    $src = data_dir() . '/journey.sqlite';
    if (!is_file($src)) return null;
    $dir = data_dir() . '/backups'; if (!is_dir($dir)) mkdir($dir, 0750, true);
    $dest = $dir . '/journey-' . date('Y-m-d-His') . '-' . preg_replace('/[^a-z0-9-]/', '', $label) . '.sqlite';
    try { $b = new PDO('sqlite:' . $src); $b->exec('VACUUM INTO ' . $b->quote($dest)); }
    catch (Throwable $e) { app_log('Backup failed: ' . $e->getMessage(), 'errors'); return null; }
    $all = glob($dir . '/journey-*.sqlite') ?: []; sort($all);
    foreach (array_slice($all, 0, max(0, count($all) - 30)) as $old) @unlink($old);
    return $dest;
}

// Small query helpers
function q(string $sql, array $p = []): PDOStatement {
    if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)/i', $sql)) $GLOBALS['_dbv'] = ($GLOBALS['_dbv'] ?? 0) + 1;
    $s = db()->prepare($sql); $s->execute($p); return $s;
}
function all(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function one(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function val(string $sql, array $p = []) { return q($sql, $p)->fetchColumn(); }
function insert(string $table, array $row): int {
    if ($table === 'people') $row = encrypt_person_fields($row);
    $cols = array_keys($row);
    q("INSERT INTO $table (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($row));
    return (int)db()->lastInsertId();
}
function update(string $table, int $id, array $row): void {
    if (!$row) return;
    if ($table === 'people') $row = encrypt_person_fields($row);
    $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($row)));
    q("UPDATE $table SET $set WHERE id = ?", [...array_values($row), $id]);
}
function delete_row(string $table, int $id): void { q("DELETE FROM $table WHERE id = ?", [$id]); }
// Run several writes as one: all succeed or none do
function tx(callable $fn) {
    $pdo = db(); $outer = !$pdo->inTransaction();
    if ($outer) $pdo->beginTransaction();
    try { $r = $fn(); if ($outer) $pdo->commit(); return $r; }
    catch (Throwable $e) { if ($outer && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
function now(): string { return date('Y-m-d H:i:s'); }
function log_activity(?int $trip_id, string $what): void {
    insert('activity', ['trip_id' => $trip_id, 'who' => current_actor_name(), 'what' => mb_substr($what, 0, 1000), 'created_at' => now()]);
}
