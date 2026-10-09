<?php
// >>> PLACE AT: css-system/teachers.php  (replace the old file)  [teachers are archived, never deleted]
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireRole(['admin','registrar','program_head']);

$pdo = getDB();
// Only the Admin and Registrar can issue Employee / Faculty IDs
$canAssignIds = in_array($_SESSION['role'] ?? '', ['admin', 'registrar'], true);

// --- Handle Create / Update / Archive / Restore ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['full_name'] ?? '');
        $dept = trim($_POST['department'] ?? '');
        $maxUnits = (int)($_POST['max_units'] ?? 24);
        $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
        $empId = strtoupper(trim($_POST['employee_id'] ?? ''));
        $facId = strtoupper(trim($_POST['faculty_id'] ?? ''));

        if ($name === '') {
            flash('error', 'Teacher name is required.');
        } elseif ($canAssignIds && (!preg_match('/^[A-Z0-9-]{0,30}$/', $empId) || !preg_match('/^[A-Z0-9-]{0,30}$/', $facId))) {
            flash('error', 'Employee ID and Faculty ID may only contain letters, numbers and dashes.');
        } else {
            try {
                if ($id > 0) {
                    if ($canAssignIds) {
                        $pdo->prepare("UPDATE teachers SET full_name=?, department=?, max_units=?, status=?, employee_id=?, faculty_id=? WHERE id=?")
                            ->execute([$name, $dept, $maxUnits, $status, $empId ?: null, $facId ?: null, $id]);
                    } else {
                        $pdo->prepare("UPDATE teachers SET full_name=?, department=?, max_units=?, status=? WHERE id=?")
                            ->execute([$name, $dept, $maxUnits, $status, $id]);
                    }
                    logAudit($_SESSION['user_id'], 'UPDATE', 'teachers', $id, "Updated teacher $name");
                    flash('success', 'Teacher updated.');
                } else {
                    if ($canAssignIds) {
                        $pdo->prepare("INSERT INTO teachers (full_name, department, max_units, status, employee_id, faculty_id) VALUES (?,?,?,?,?,?)")
                            ->execute([$name, $dept, $maxUnits, $status, $empId ?: null, $facId ?: null]);
                    } else {
                        $pdo->prepare("INSERT INTO teachers (full_name, department, max_units, status) VALUES (?,?,?,?)")
                            ->execute([$name, $dept, $maxUnits, $status]);
                    }
                    logAudit($_SESSION['user_id'], 'CREATE', 'teachers', $pdo->lastInsertId(), "Created teacher $name");
                    flash('success', 'Teacher added.');
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    flash('error', 'That Employee ID or Faculty ID is already assigned to another teacher.');
                } else {
                    error_log('teachers.php: ' . $e->getMessage());
                    flash('error', 'Could not save. Import sql/teacher_ids.sql into the database first.');
                }
            }
        }
    } elseif ($action === 'archive' || $action === 'restore') {
        // Teachers are never deleted: archiving sets them inactive (hidden from the schedule builder,
        // existing classes and history are kept). Restoring makes them active again.
        $id  = (int)($_POST['id'] ?? 0);
        $new = $action === 'archive' ? 'inactive' : 'active';
        try {
            $pdo->prepare("UPDATE teachers SET status=? WHERE id=?")->execute([$new, $id]);
            logAudit($_SESSION['user_id'], 'UPDATE', 'teachers', $id, $action === 'archive' ? 'Archived teacher' : 'Restored teacher');
            flash('success', $action === 'archive' ? 'Teacher archived. Find them under the Archived tab.' : 'Teacher unarchived.');
        } catch (PDOException $e) {
            error_log('teachers.php archive: ' . $e->getMessage());
            flash('error', 'Could not update the teacher. Please try again.');
        }
    }
    redirect('teachers.php' . (($_POST['view'] ?? '') === 'archived' ? '?view=archived' : ''));
}

$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch();
}

$view = (($_GET['view'] ?? '') === 'archived') ? 'archived' : 'active';
$counts = ['active' => 0, 'archived' => 0];
foreach ($pdo->query("SELECT status, COUNT(*) c FROM teachers GROUP BY status")->fetchAll() as $r) {
    $counts[$r['status'] === 'inactive' ? 'archived' : 'active'] = (int)$r['c'];
}
$st = $pdo->prepare("SELECT * FROM teachers WHERE status = ? ORDER BY full_name");
$st->execute([$view === 'archived' ? 'inactive' : 'active']);
$teachers = $st->fetchAll();

$pageTitle = 'Teachers';
include __DIR__ . '/includes/header.php';
?>
<h1>👩‍🏫 Teachers</h1>

<div class="card">
    <h3><?= $editRow ? 'Edit Teacher' : 'Add Teacher' ?></h3>
    <form method="POST" action="teachers.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)($editRow['id'] ?? 0) ?>">
        <div class="form-grid">
            <div>
                <label>Full Name</label>
                <input type="text" name="full_name" required value="<?= e($editRow['full_name'] ?? '') ?>">
            </div>
            <?php if ($canAssignIds): ?>
            <div>
                <label>Employee ID</label>
                <input type="text" name="employee_id" maxlength="30" placeholder="EMP-2026-0001" value="<?= e($editRow['employee_id'] ?? '') ?>">
            </div>
            <div>
                <label>Faculty ID</label>
                <input type="text" name="faculty_id" maxlength="30" placeholder="FAC-2026-0001" value="<?= e($editRow['faculty_id'] ?? '') ?>">
            </div>
            <?php endif; ?>
            <div>
                <label>Department</label>
                <input type="text" name="department" value="<?= e($editRow['department'] ?? '') ?>">
            </div>
            <div>
                <label>Max Units</label>
                <input type="number" name="max_units" min="1" max="40" value="<?= e((string)($editRow['max_units'] ?? 24)) ?>">
            </div>
            <div>
                <label>Status</label>
                <select name="status">
                    <option value="active" <?= (($editRow['status'] ?? 'active')==='active')?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= (($editRow['status'] ?? '')==='inactive')?'selected':'' ?>>Archived</option>
                </select>
            </div>
        </div>
        <?php if ($canAssignIds): ?><p class="hint" style="margin-top:10px">The Employee ID and Faculty ID you enter here appear (locked) on the teacher's profile in the Teacher Registration portal.</p><?php endif; ?>
        <br>
        <button class="btn btn-gold" type="submit"><?= $editRow ? 'Update Teacher' : 'Add Teacher' ?></button>
        <?php if ($editRow): ?><a class="btn btn-secondary" href="teachers.php">Cancel</a><?php endif; ?>
    </form>
</div>

<div class="filters">
    <a class="<?= $view === 'active' ? 'on' : '' ?>" href="teachers.php">Active (<?= (int)$counts['active'] ?>)</a>
    <a class="<?= $view === 'archived' ? 'on' : '' ?>" href="teachers.php?view=archived">Archived (<?= (int)$counts['archived'] ?>)</a>
</div>

<div class="card">
    <h3><?= $view === 'archived' ? 'Archived Teachers' : 'Active Teachers' ?></h3>
    <table>
        <tr><th>Name</th><th>Employee ID</th><th>Faculty ID</th><th>Department</th><th>Max Units</th><th>Status</th><th>Actions</th></tr>
        <?php if (!$teachers): ?><tr><td colspan="7"><?= $view === 'archived' ? 'No archived teachers.' : 'No active teachers.' ?></td></tr><?php endif; ?>
        <?php foreach ($teachers as $t): $archived = $t['status'] === 'inactive'; ?>
        <tr>
            <td><?= e($t['full_name']) ?></td>
            <td><?= ($t['employee_id'] ?? '') !== '' ? e($t['employee_id']) : '<span class="hint">Not assigned</span>' ?></td>
            <td><?= ($t['faculty_id'] ?? '') !== '' ? e($t['faculty_id']) : '<span class="hint">Not assigned</span>' ?></td>
            <td><?= e($t['department']) ?></td>
            <td><?= e((string)$t['max_units']) ?></td>
            <td><span class="badge badge-<?= e($t['status']) ?>"><?= $archived ? 'Archived' : 'Active' ?></span></td>
            <td>
                <a class="btn btn-sm" href="teachers.php?edit=<?= (int)$t['id'] ?>">Edit</a>
                <?php if ($archived): ?>
                <form class="inline" method="POST" action="teachers.php" data-confirm="Unarchive this teacher? They will appear in the schedule builder again.">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="view" value="archived">
                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                    <button class="btn btn-sm btn-gold" type="submit">Unarchive</button>
                </form>
                <?php else: ?>
                <form class="inline" method="POST" action="teachers.php" data-confirm="Archive this teacher? They will no longer appear in the schedule builder, but their existing classes are kept.">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                    <button class="btn btn-sm btn-secondary" type="submit">Archive</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p class="hint" style="margin:12px 0 0">Teachers are never deleted. Archived teachers are hidden from the schedule builder and can be unarchived at any time from the Archived tab.</p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>