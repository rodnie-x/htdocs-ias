<?php
// >>> PLACE AT: teacher-registration/profile.php  (replace the old file)  [SQL column-name guard]
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/portal.php';
requireLogin();

$account   = currentAccount();
$accountId = (int)$account['id'];
$pdo       = getDB();
$uploadDir = __DIR__ . '/uploads';

$OPT = [
    'gender'            => ['Male', 'Female', 'Prefer not to say'],
    'civil_status'      => ['Single', 'Married', 'Widowed', 'Legally separated', 'Annulled'],
    'nationality'       => ['Filipino', 'American', 'Chinese', 'Japanese', 'Korean', 'Indian', 'Other'],
    'religion'          => ['Roman Catholic', 'Iglesia ni Cristo', 'Islam', 'Born Again Christian', 'Protestant', 'Aglipayan', 'Buddhist', 'Other'],
    'employment_status' => ['Full-time', 'Part-time', 'Contractual', 'Substitute'],
];
$TABS   = ['personal' => 'Personal Information', 'contact' => 'Contact Information', 'employment' => 'Employment Information', 'education' => 'Educational Background', 'documents' => 'Documents'];
$LEVELS = ['bachelor' => "Bachelor's degree", 'master' => "Master's degree", 'doctorate' => 'Doctorate'];
$DOCS   = ['resume' => 'Resume / CV', 'prc' => 'PRC license', 'diploma' => 'Diploma', 'tor' => 'Transcript of Records', 'nbi' => 'NBI clearance'];
$IMG    = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$DOCMIME = $IMG + ['application/pdf' => 'pdf'];
$TEXT   = ['nickname' => 40, 'birth_place' => 100, 'addr_street' => 120, 'barangay' => 80, 'city' => 80,
           'province' => 80, 'zip' => 10, 'phone' => 20, 'alt_phone' => 20, 'emergency_name' => 100, 'emergency_relation' => 40, 'emergency_phone' => 20,
           'position' => 80, 'department' => 100, 'specialization' => 150, 'prc_license_no' => 40];
$TAB_OF = ['gender' => 'personal', 'birthdate' => 'personal', 'civil_status' => 'personal'];

function pfDate(string $s): bool { $d = DateTime::createFromFormat('!Y-m-d', $s); return $d && $d->format('Y-m-d') === $s; }

function pfCheckUpload(string $field, array $allowed, int $maxBytes, string $label, string $tab, array &$errors): ?array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return null; }
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > $maxBytes) {
        $errors[] = [$tab, $label . ' is too large or failed to upload. The limit is ' . round($maxBytes / 1048576) . ' MB.'];
        return null;
    }
    if (!is_uploaded_file($f['tmp_name'])) { return null; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($allowed[$mime]) || (str_starts_with($mime, 'image/') && !@getimagesize($f['tmp_name']))) {
        $errors[] = [$tab, $label . ' must be ' . (count($allowed) > 3 ? 'a PDF, JPG or PNG' : 'a JPG, PNG or WebP image') . ' file.'];
        return null;
    }
    return ['tmp' => $f['tmp_name'], 'ext' => $allowed[$mime], 'size' => (int)$f['size'], 'name' => mb_substr(basename((string)$f['name']), 0, 150)];
}

function pfText(string $label, string $name, string $value, array $o = []): void
{
    echo '<div class="pf-field"><label for="f-' . e($name) . '">' . e($label) . '</label><input type="' . e($o['type'] ?? 'text') . '" id="f-' . e($name) . '" name="' . e($name) . '" value="' . e($value) . '"';
    foreach (['maxlength', 'placeholder', 'min', 'max', 'step', 'inputmode', 'readonly'] as $a) {
        if (isset($o[$a])) { echo ' ' . $a . '="' . e((string)$o[$a]) . '"'; }
    }
    echo '></div>';
}

function pfSelect(string $label, string $name, string $value, array $options): void
{
    if ($value !== '' && !in_array($value, $options, true)) { $options[] = $value; }
    echo '<div class="pf-field"><label for="f-' . e($name) . '">' . e($label) . '</label><select id="f-' . e($name) . '" name="' . e($name) . '"><option value="">Select</option>';
    foreach ($options as $o) { echo '<option value="' . e($o) . '"' . ($o === $value ? ' selected' : '') . '>' . e($o) . '</option>'; }
    echo '</select></div>';
}

/* ---------- Load ---------- */
$setupError = null; $p = []; $edu = []; $docs = [];
try {
    $st = $pdo->prepare('SELECT * FROM treg_profiles WHERE account_id = ?'); $st->execute([$accountId]); $p = $st->fetch() ?: [];
    $st = $pdo->prepare('SELECT * FROM treg_education WHERE account_id = ?'); $st->execute([$accountId]);
    foreach ($st->fetchAll() as $r) { $edu[$r['level']] = ['course' => $r['course'], 'school' => $r['school'], 'year' => $r['year_grad']]; }
    $st = $pdo->prepare('SELECT * FROM treg_documents WHERE account_id = ?'); $st->execute([$accountId]);
    foreach ($st->fetchAll() as $r) { $docs[$r['doc_type']] = $r; }
} catch (PDOException $ex) {
    $setupError = 'The profile tables were not found. Import sql/profile_schema.sql into the css_system database first.';
    error_log('profile.php: ' . $ex->getMessage());
}

/* Employee ID / Faculty ID are issued by the Registrar (Class Scheduling System), shown here read-only */
$ids = ['employee_id' => '', 'faculty_id' => ''];
try {
    $teacherRow = getLinkedTeacher($account);
    if ($teacherRow) {
        $st = $pdo->prepare('SELECT employee_id, faculty_id FROM teachers WHERE id = ?');
        $st->execute([(int)$teacherRow['id']]);
        $ids = array_map(fn($v) => (string)$v, $st->fetch() ?: $ids);
    }
} catch (Throwable $ex) {
    error_log('profile.php ids: ' . $ex->getMessage());   // columns not added yet: show "not assigned"
}

/* ---------- Save ---------- */
$errors = []; $old = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$setupError) {
    verifyCsrf();
    $in = [];
    foreach ($TEXT as $k => $len) { $in[$k] = mb_substr(trim(strIn($_POST, $k)), 0, $len); }
    foreach (['gender', 'civil_status', 'nationality', 'religion', 'employment_status'] as $k) { $in[$k] = mb_substr(trim(strIn($_POST, $k)), 0, 40); }
    foreach (['birthdate', 'date_hired', 'prc_license_expiry', 'height_cm', 'weight_kg', 'years_experience'] as $k) { $in[$k] = trim(strIn($_POST, $k)); }
    foreach (['phone', 'alt_phone', 'emergency_phone'] as $k) { $in[$k] = preg_replace('/[\s-]/', '', $in[$k]); }
    $today = date('Y-m-d');

    if ($in['birthdate'] !== '' && (!pfDate($in['birthdate']) || $in['birthdate'] > $today || $in['birthdate'] < '1930-01-01')) { $errors[] = ['personal', 'Birthdate is not a valid date.']; }
    if ($in['height_cm'] !== '' && (!ctype_digit($in['height_cm']) || $in['height_cm'] < 50 || $in['height_cm'] > 250)) { $errors[] = ['personal', 'Height must be between 50 and 250 cm.']; }
    if ($in['weight_kg'] !== '' && (!is_numeric($in['weight_kg']) || $in['weight_kg'] < 20 || $in['weight_kg'] > 300)) { $errors[] = ['personal', 'Weight must be between 20 and 300 kg.']; }
    if ($in['zip'] !== '' && !preg_match('/^\d{4}$/', $in['zip'])) { $errors[] = ['personal', 'Zip code must be 4 digits.']; }
    foreach (['phone' => 'Mobile number', 'alt_phone' => 'Alternate number', 'emergency_phone' => 'Emergency contact number'] as $k => $lb) {
        if ($in[$k] !== '' && !preg_match('/^(09\d{9}|\+639\d{9})$/', $in[$k])) { $errors[] = ['contact', $lb . ' should look like 09171234567.']; }
    }
    if ($in['date_hired'] !== '' && (!pfDate($in['date_hired']) || $in['date_hired'] > $today)) { $errors[] = ['employment', 'Date hired is not a valid date.']; }
    if ($in['prc_license_expiry'] !== '' && !pfDate($in['prc_license_expiry'])) { $errors[] = ['employment', 'PRC license expiry is not a valid date.']; }
    if ($in['years_experience'] !== '' && (!ctype_digit($in['years_experience']) || (int)$in['years_experience'] > 60)) { $errors[] = ['employment', 'Years of experience must be 0 to 60.']; }

    $eduIn = [];
    foreach ($LEVELS as $lv => $lb) {
        $r = $_POST['edu'][$lv] ?? [];
        $r = is_array($r) ? $r : [];
        $eduIn[$lv] = ['course' => mb_substr(trim((string)($r['course'] ?? '')), 0, 150), 'school' => mb_substr(trim((string)($r['school'] ?? '')), 0, 150), 'year' => trim((string)($r['year'] ?? ''))];
        $y = $eduIn[$lv]['year'];
        if ($y !== '' && (!ctype_digit($y) || $y < 1950 || $y > (int)date('Y') + 1)) { $errors[] = ['education', $lb . ': year graduated is not valid.']; }
    }

    $photoUp = pfCheckUpload('photo', $IMG, 2 * 1048576, 'The photo', 'personal', $errors);
    $docUp = [];
    foreach ($DOCS as $k => $lb) { $docUp[$k] = pfCheckUpload('doc_' . $k, $DOCMIME, 5 * 1048576, $lb, 'documents', $errors); }
    $remove = array_intersect((array)($_POST['remove_doc'] ?? []), array_keys($DOCS));

    if (!$errors) {
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
        if (!is_file($uploadDir . '/.htaccess')) { @file_put_contents($uploadDir . '/.htaccess', "Require all denied\nOptions -ExecCGI -Indexes\n"); }
        $newFiles = []; $oldFiles = [];
        try {
            $store = function (array $u) use ($uploadDir, &$newFiles): string {
                $name = bin2hex(random_bytes(16)) . '.' . $u['ext'];
                if (!move_uploaded_file($u['tmp'], $uploadDir . '/' . $name)) { throw new RuntimeException('Could not store the uploaded file.'); }
                $newFiles[] = $name;
                return $name;
            };
            $row = ['account_id' => $accountId];
            foreach ($in as $k => $v) { $row[$k] = $v === '' ? null : $v; }
            if ($photoUp) {
                $row['photo_file'] = $store($photoUp);
                if (!empty($p['photo_file'])) { $oldFiles[] = $p['photo_file']; }
            }
            // Fixed column order. The SQL below is one fixed literal; only the values change.
            // (If you ever add a profile column: add it to this list AND to the SQL text, in the same position.)
            $pfCols = ['account_id', 'photo_file', 'nickname', 'birth_place', 'addr_street', 'barangay', 'city', 'province', 'zip', 'phone', 'alt_phone', 'emergency_name', 'emergency_relation', 'emergency_phone', 'position', 'department', 'specialization', 'prc_license_no', 'gender', 'civil_status', 'nationality', 'religion', 'employment_status', 'birthdate', 'date_hired', 'prc_license_expiry', 'height_cm', 'weight_kg', 'years_experience'];
            $pfVals = [];
            foreach ($pfCols as $c) { $pfVals[] = $row[$c] ?? null; }

            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO treg_profiles (account_id, photo_file, nickname, birth_place, addr_street, barangay, city, province, zip, phone, alt_phone, emergency_name, emergency_relation, emergency_phone, position, department, specialization, prc_license_no, gender, civil_status, nationality, religion, employment_status, birthdate, date_hired, prc_license_expiry, height_cm, weight_kg, years_experience) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE photo_file=COALESCE(VALUES(photo_file), photo_file), nickname=VALUES(nickname), birth_place=VALUES(birth_place), addr_street=VALUES(addr_street), barangay=VALUES(barangay), city=VALUES(city), province=VALUES(province), zip=VALUES(zip), phone=VALUES(phone), alt_phone=VALUES(alt_phone), emergency_name=VALUES(emergency_name), emergency_relation=VALUES(emergency_relation), emergency_phone=VALUES(emergency_phone), position=VALUES(position), department=VALUES(department), specialization=VALUES(specialization), prc_license_no=VALUES(prc_license_no), gender=VALUES(gender), civil_status=VALUES(civil_status), nationality=VALUES(nationality), religion=VALUES(religion), employment_status=VALUES(employment_status), birthdate=VALUES(birthdate), date_hired=VALUES(date_hired), prc_license_expiry=VALUES(prc_license_expiry), height_cm=VALUES(height_cm), weight_kg=VALUES(weight_kg), years_experience=VALUES(years_experience)")->execute($pfVals);
            foreach ($eduIn as $lv => $r) {
                if ($r['course'] === '' && $r['school'] === '' && $r['year'] === '') {
                    $pdo->prepare('DELETE FROM treg_education WHERE account_id = ? AND level = ?')->execute([$accountId, $lv]);
                } else {
                    $pdo->prepare('INSERT INTO treg_education (account_id, level, course, school, year_grad) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE course=VALUES(course), school=VALUES(school), year_grad=VALUES(year_grad)')
                        ->execute([$accountId, $lv, $r['course'] ?: null, $r['school'] ?: null, $r['year'] ?: null]);
                }
            }
            foreach ($docUp as $k => $u) {
                if (!$u) { continue; }
                if (isset($docs[$k])) { $oldFiles[] = $docs[$k]['stored_name']; }
                $pdo->prepare('INSERT INTO treg_documents (account_id, doc_type, original_name, stored_name, size) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE original_name=VALUES(original_name), stored_name=VALUES(stored_name), size=VALUES(size), uploaded_at=CURRENT_TIMESTAMP')
                    ->execute([$accountId, $k, $u['name'], $store($u), $u['size']]);
            }
            foreach ($remove as $k) {
                if (isset($docs[$k]) && !$docUp[$k]) {
                    $oldFiles[] = $docs[$k]['stored_name'];
                    $pdo->prepare('DELETE FROM treg_documents WHERE account_id = ? AND doc_type = ?')->execute([$accountId, $k]);
                }
            }
            $pdo->commit();
            foreach ($oldFiles as $f) { @unlink($uploadDir . '/' . basename($f)); }
            flash('success', 'Your profile was saved.');
            redirect('profile.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            foreach ($newFiles as $f) { @unlink($uploadDir . '/' . $f); }
            error_log('profile.php save failed: ' . $ex->getMessage());
            $errors[] = ['personal', 'We could not save your profile. Try again, and contact the Registrar if it keeps happening.' . (defined('APP_DEBUG') && APP_DEBUG ? ' [' . $ex->getMessage() . ']' : '')];
        }
    }
    $old = $in;
    foreach ($eduIn as $lv => $r) { $edu[$lv] = $r; }
}

$val = fn(string $k): string => (string)(($old[$k] ?? null) ?? ($p[$k] ?? ''));
$errTabs = implode(',', array_unique(array_column($errors, 0)));
$hasPhoto = !empty($p['photo_file']);

$pageTitle = 'My profile';
$active    = 'profile';
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/profile.css">
<div class="page-head">
    <div>
        <h1>My profile</h1>
        <p class="lede">Keep your personal, contact, employment and education details up to date. Your name comes from your account.</p>
    </div>
</div>

<?php if ($setupError): ?><div class="alert alert-error"><?= e($setupError) ?></div><?php else: ?>

<form method="POST" action="profile.php" enctype="multipart/form-data" id="profile-form" class="pf" data-error-tabs="<?= e($errTabs) ?>" novalidate>
    <?= csrfField() ?>
    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert"><strong>Check the form:</strong>
            <ul><?php foreach ($errors as [$tab, $msg]): ?><li><?= e($TABS[$tab]) ?>: <?= e($msg) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="pf-tabs" role="tablist" aria-label="Profile sections">
        <?php foreach ($TABS as $id => $label): ?>
            <button type="button" role="tab" class="pf-tab" data-tab="<?= e($id) ?>" id="tab-<?= e($id) ?>" aria-controls="panel-<?= e($id) ?>" aria-selected="false"><?= e($label) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="pf-card">
        <!-- ===== Personal ===== -->
        <section class="pf-panel" role="tabpanel" id="panel-personal" aria-labelledby="tab-personal" hidden>
            <div class="pf-top">
                <div class="pf-photo">
                    <div class="pf-avatar" id="photo-preview">
                        <?php if ($hasPhoto): ?><img src="file.php?photo=1&amp;v=<?= e(substr((string)$p['photo_file'], 0, 8)) ?>" alt="Your profile photo">
                        <?php else: ?><svg viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="50" fill="#d5d9e2"/><circle cx="50" cy="38" r="17" fill="#fff"/><path d="M14 88c4-19 19-29 36-29s32 10 36 29a50 50 0 01-72 0z" fill="#fff"/></svg><?php endif; ?>
                    </div>
                    <label class="pf-camera" title="Change photo">
                        <input type="file" name="photo" id="photo-input" accept="image/jpeg,image/png,image/webp">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        <span class="sr">Change photo</span>
                    </label>
                    <p class="pf-hint">JPG, PNG or WebP, up to 2 MB.</p>
                </div>
                <div class="pf-grid pf-3">
                    <?php pfText('Employee ID', 'employee_id_display', $ids['employee_id'], ['readonly' => 'readonly', 'placeholder' => 'Assigned by the Registrar']);
                    pfText('Faculty ID', 'faculty_id_display', $ids['faculty_id'], ['readonly' => 'readonly', 'placeholder' => 'Assigned by the Registrar']);
                    pfText('Full name', 'full_name_display', accountFullName($account), ['readonly' => 'readonly']);
                    pfSelect('Gender', 'gender', $val('gender'), $OPT['gender']);
                    pfText('Birthdate', 'birthdate', $val('birthdate'), ['type' => 'date', 'max' => date('Y-m-d')]);
                    pfSelect('Civil status', 'civil_status', $val('civil_status'), $OPT['civil_status']); ?>
                </div>
            </div>

            <h3 class="pf-sec">Basic information</h3>
            <div class="pf-grid pf-3">
                <?php pfText('Nickname', 'nickname', $val('nickname'), ['maxlength' => 40]);
                pfSelect('Nationality', 'nationality', $val('nationality'), $OPT['nationality']);
                pfSelect('Religion', 'religion', $val('religion'), $OPT['religion']);
                pfText('Place of birth', 'birth_place', $val('birth_place'), ['maxlength' => 100]);
                pfText('Height (cm)', 'height_cm', $val('height_cm'), ['type' => 'number', 'min' => 50, 'max' => 250]);
                pfText('Weight (kg)', 'weight_kg', $val('weight_kg'), ['type' => 'number', 'min' => 20, 'max' => 300, 'step' => '0.1']); ?>
            </div>

            <h3 class="pf-sec">Address</h3>
            <div class="pf-grid pf-3">
                <?php pfText('House No. / Street', 'addr_street', $val('addr_street'), ['maxlength' => 120]);
                pfText('Barangay', 'barangay', $val('barangay'), ['maxlength' => 80]);
                pfText('City / Municipality', 'city', $val('city'), ['maxlength' => 80]);
                pfText('Province', 'province', $val('province'), ['maxlength' => 80]);
                pfText('Zip code', 'zip', $val('zip'), ['maxlength' => 4, 'inputmode' => 'numeric']); ?>
            </div>
        </section>

        <!-- ===== Contact ===== -->
        <section class="pf-panel" role="tabpanel" id="panel-contact" aria-labelledby="tab-contact" hidden>
            <h3 class="pf-sec first">How the school can reach you</h3>
            <div class="pf-grid pf-3">
                <?php pfText('Gmail account (login)', 'email_display', (string)$account['email'], ['readonly' => 'readonly']);
                pfText('Mobile number', 'phone', $val('phone'), ['maxlength' => 20, 'placeholder' => '09171234567', 'inputmode' => 'tel']);
                pfText('Alternate number', 'alt_phone', $val('alt_phone'), ['maxlength' => 20, 'inputmode' => 'tel']); ?>
            </div>
            <h3 class="pf-sec">Emergency contact</h3>
            <div class="pf-grid pf-3">
                <?php pfText('Full name', 'emergency_name', $val('emergency_name'), ['maxlength' => 100]);
                pfText('Relationship', 'emergency_relation', $val('emergency_relation'), ['maxlength' => 40, 'placeholder' => 'Spouse, parent, sibling…']);
                pfText('Contact number', 'emergency_phone', $val('emergency_phone'), ['maxlength' => 20, 'placeholder' => '09171234567', 'inputmode' => 'tel']); ?>
            </div>
        </section>

        <!-- ===== Employment ===== -->
        <section class="pf-panel" role="tabpanel" id="panel-employment" aria-labelledby="tab-employment" hidden>
            <h3 class="pf-sec first">Position</h3>
            <div class="pf-grid pf-3">
                <?php pfText('Position / rank', 'position', $val('position'), ['maxlength' => 80, 'placeholder' => 'Instructor I']);
                pfText('Department', 'department', $val('department'), ['maxlength' => 100]);
                pfSelect('Employment status', 'employment_status', $val('employment_status'), $OPT['employment_status']);
                pfText('Date hired', 'date_hired', $val('date_hired'), ['type' => 'date', 'max' => date('Y-m-d')]);
                pfText('Years of teaching experience', 'years_experience', $val('years_experience'), ['type' => 'number', 'min' => 0, 'max' => 60]);
                pfText('Specialization', 'specialization', $val('specialization'), ['maxlength' => 150]); ?>
            </div>
            <h3 class="pf-sec">Professional license</h3>
            <div class="pf-grid pf-3">
                <?php pfText('PRC license no.', 'prc_license_no', $val('prc_license_no'), ['maxlength' => 40]);
                pfText('License expiry date', 'prc_license_expiry', $val('prc_license_expiry'), ['type' => 'date']); ?>
            </div>
        </section>

        <!-- ===== Education ===== -->
        <section class="pf-panel" role="tabpanel" id="panel-education" aria-labelledby="tab-education" hidden>
            <?php $first = true; foreach ($LEVELS as $lv => $lb): $r = $edu[$lv] ?? ['course' => '', 'school' => '', 'year' => '']; ?>
                <h3 class="pf-sec<?= $first ? ' first' : '' ?>"><?= e($lb) ?></h3>
                <div class="pf-grid pf-3">
                    <?php pfText('Course / degree', "edu[$lv][course]", (string)$r['course'], ['maxlength' => 150]);
                    pfText('School / university', "edu[$lv][school]", (string)$r['school'], ['maxlength' => 150]);
                    pfText('Year graduated', "edu[$lv][year]", (string)$r['year'], ['type' => 'number', 'min' => 1950, 'max' => (int)date('Y') + 1]); ?>
                </div>
            <?php $first = false; endforeach; ?>
        </section>

        <!-- ===== Documents ===== -->
        <section class="pf-panel" role="tabpanel" id="panel-documents" aria-labelledby="tab-documents" hidden>
            <p class="pf-hint" style="margin-top:0">PDF, JPG or PNG, up to 5 MB each. Choosing a new file replaces the old one when you save. Only you can open these files.</p>
            <?php foreach ($DOCS as $k => $lb): $d = $docs[$k] ?? null; ?>
                <div class="pf-doc">
                    <div>
                        <strong><?= e($lb) ?></strong>
                        <?php if ($d): ?>
                            <span class="pf-ok">Uploaded</span>
                            <div class="pf-sub"><a href="file.php?doc=<?= (int)$d['id'] ?>" target="_blank" rel="noopener"><?= e($d['original_name']) ?></a> &middot; <?= e(number_format($d['size'] / 1024, 0)) ?> KB &middot; <?= e(date('M j, Y', strtotime($d['uploaded_at']))) ?></div>
                        <?php else: ?><span class="pf-missing">Not uploaded</span><?php endif; ?>
                    </div>
                    <div class="pf-doc-actions">
                        <label class="pf-file"><input type="file" name="doc_<?= e($k) ?>" accept=".pdf,.jpg,.jpeg,.png"><span>Choose file</span></label>
                        <?php if ($d): ?><label class="pf-remove"><input type="checkbox" name="remove_doc[]" value="<?= e($k) ?>"> Remove</label><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="pf-actions">
            <a class="btn btn-outline" href="profile.php">Cancel</a>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
    </div>
</form>
<script src="assets/js/profile.js" defer></script>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
