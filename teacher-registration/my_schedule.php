<?php
// >>> PLACE AT: teacher-registration/my_schedule.php  (replace the old file)  [batch 8: no inline handlers]
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/portal.php';
requireLogin();

$account = currentAccount();
$teacher = getLinkedTeacher($account);
$status  = $account['reg_status'];

$terms  = [];
$rows   = [];
$selSem = '';
$selSy  = '';

if ($teacher) {
    $terms = getTeacherTerms((int)$teacher['id']);
    if ($terms) {
        // Default to the newest term; honour ?sem=&sy= only if it matches a real term.
        $selSem = $terms[0]['semester'];
        $selSy  = $terms[0]['school_year'];
        $reqSem = strIn($_GET, 'sem');
        $reqSy  = strIn($_GET, 'sy');
        foreach ($terms as $t) {
            if ($t['semester'] === $reqSem && $t['school_year'] === $reqSy) {
                $selSem = $reqSem;
                $selSy  = $reqSy;
                break;
            }
        }
        $rows = getTeacherSchedule((int)$teacher['id'], $selSem, $selSy);
    }
}

// Group by day (for the week board) and by section+subject (for the summary table)
$byDay  = array_fill_keys(array_keys(DAYS), []);
$groups = [];
foreach ($rows as $r) {
    if (isset($byDay[$r['day_of_week']])) {
        $byDay[$r['day_of_week']][] = $r;
    }
    $key = $r['section_id'] . '-' . $r['subject_id'];
    if (!isset($groups[$key])) {
        $groups[$key] = [
            'section'  => $r['section_name'],
            'program'  => $r['section_program'],
            'year'     => $r['section_year'],
            'code'     => $r['subj_code'],
            'title'    => $r['subj_title'],
            'units'    => (float)$r['subj_units'],
            'meetings' => [],
        ];
    }
    $groups[$key]['meetings'][] = $r;
}
$loadUnits  = array_sum(array_column($groups, 'units'));
$maxUnits   = $teacher ? (int)$teacher['max_units'] : 0;
$sectionCnt = count(array_unique(array_column($rows, 'section_id')));

$semLabel = ['1st' => '1st semester', '2nd' => '2nd semester', 'summer' => 'Summer'];

/* ---------------- Download a copy of the schedule (CSV, opens in Excel) ----------------
 * Read-only and scoped to the logged-in teacher's own schedule, so a plain GET is safe.
 * This must run before any HTML is sent (before header.php is included).
 */
if ($teacher && $rows && strIn($_GET, 'download') === 'csv') {
    // Stop spreadsheet apps from running a cell that starts with = + - @ as a formula.
    $safe = static function ($v): string {
        $v = (string)$v;
        return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
    };

    $teacherName = accountFullName($account);
    $termText    = ($semLabel[$selSem] ?? $selSem) . ', ' . $selSy;
    $slug        = trim(preg_replace('/[^A-Za-z0-9]+/', '_', $teacherName . '_' . $selSem . '_' . $selSy), '_');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="schedule_' . $slug . '.csv"');
    header('Cache-Control: no-store');

    echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel reads accented names correctly
    $out = fopen('php://output', 'w');
    $put = static function (array $row) use ($out): void {
        fputcsv($out, $row, ',', '"', '');
    };

    $put(['Bestlink College of the Philippines - Teaching Schedule']);
    $put(['Teacher', $safe($teacherName)]);
    $put(['Term', $safe($termText)]);
    $put(['Teaching load (units)', formatUnits($loadUnits) . ($maxUnits ? ' / ' . $maxUnits : '')]);
    $put([]);
    $put(['Day', 'Start', 'End', 'Section', 'Subject code', 'Subject title', 'Room']);

    foreach ($rows as $r) {
        $put([
            DAYS[$r['day_of_week']] ?? $r['day_of_week'],
            clock12($r['start_time']),
            clock12($r['end_time']),
            $safe($r['section_name']),
            $safe($r['subj_code']),
            $safe($r['subj_title']),
            $safe($r['room_name']),
        ]);
    }
    fclose($out);
    exit;
}

$downloadUrl = 'my_schedule.php?' . http_build_query(['download' => 'csv', 'sem' => $selSem, 'sy' => $selSy]);

$pageTitle = 'My schedules';
$active    = 'schedule';
include __DIR__ . '/includes/header.php';
?>
<style>
@media print {
    .no-print, header, nav, footer, .term-picker { display: none !important; }
}
</style>

<div class="page-head">
    <div>
        <h1>My schedules</h1>
        <p class="lede">The sections, subjects, rooms and times the admin has assigned to you in the Class Scheduling System.</p>
    </div>
    <?php if (count($terms) > 0): ?>
        <form method="GET" action="my_schedule.php" class="term-picker">
            <label for="term-select">Term</label>
            <select id="term-select">
                <?php foreach ($terms as $t): ?>
                    <option value="<?= e($t['semester'] . '|' . $t['school_year']) ?>" <?= ($t['semester'] === $selSem && $t['school_year'] === $selSy) ? 'selected' : '' ?>>
                        <?= e(($semLabel[$t['semester']] ?? $t['semester']) . ', ' . $t['school_year']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="sem" value="<?= e($selSem) ?>">
            <input type="hidden" name="sy" value="<?= e($selSy) ?>">
            <noscript><button type="submit" class="btn btn-outline btn-sm">Show</button></noscript>
        </form>
    <?php endif; ?>
</div>

<?php if (!$teacher): ?>
    <div class="empty empty-lg">
        <h2>Nothing to show yet</h2>
        <p>Your schedule appears here after you submit the registration form and the admin assigns you classes.</p>
        <a class="btn btn-primary" href="dashboard.php">Go to the registration form</a>
    </div>

<?php elseif (!$rows): ?>
    <div class="empty empty-lg">
        <h2>No classes assigned yet</h2>
        <?php if ($status === 'approved'): ?>
            <p>You are approved. As soon as the admin adds you to a section, it will show up on this page.</p>
        <?php else: ?>
            <p>Your registration is still <?= e(strtolower(statusLabel($status))) ?>. The admin can assign classes once it is approved.</p>
            <a class="btn btn-outline" href="dashboard.php">Review your registration</a>
        <?php endif; ?>
    </div>

<?php else: ?>
    <div class="btn-row no-print" style="margin-bottom:18px">
        <a class="btn btn-primary btn-sm" href="<?= e($downloadUrl) ?>">Download schedule (CSV)</a>
        <button type="button" class="btn btn-outline btn-sm" data-print>Print / Save as PDF</button>
    </div>

    <div class="facts">
        <div class="fact"><span class="fact-num"><?= count($rows) ?></span><span class="fact-label">class meetings a week</span></div>
        <div class="fact"><span class="fact-num"><?= $sectionCnt ?></span><span class="fact-label">sections</span></div>
        <div class="fact"><span class="fact-num"><?= count($groups) ?></span><span class="fact-label">section and subject pairs</span></div>
        <div class="fact"><span class="fact-num"><?= e(formatUnits($loadUnits)) ?><?= $maxUnits ? '<small> / ' . (int)$maxUnits . '</small>' : '' ?></span><span class="fact-label">teaching load in units</span></div>
    </div>

    <h2 class="section-title">This week</h2>
    <div class="board">
        <?php foreach (DAYS as $code => $label): ?>
            <section class="day-col" aria-label="<?= e($label) ?>">
                <h3><?= e($label) ?></h3>
                <?php if (!$byDay[$code]): ?>
                    <p class="day-empty">No classes</p>
                <?php else: foreach ($byDay[$code] as $r): ?>
                    <article class="class-chip">
                        <div class="chip-time"><?= e(clock12($r['start_time'])) ?> to <?= e(clock12($r['end_time'])) ?></div>
                        <div class="chip-subject"><?= e($r['subj_code']) ?></div>
                        <div class="chip-meta"><?= e($r['section_name']) ?></div>
                        <div class="chip-meta">Room <?= e($r['room_name']) ?></div>
                    </article>
                <?php endforeach; endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <h2 class="section-title">Sections and subjects</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Section</th><th>Subject</th><th class="num">Units</th><th>Meetings</th></tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $g): ?>
                    <tr>
                        <td>
                            <strong><?= e($g['section']) ?></strong>
                            <div class="muted"><?= e(trim($g['program'] . ' ' . $g['year'])) ?></div>
                        </td>
                        <td>
                            <strong><?= e($g['code']) ?></strong>
                            <div class="muted"><?= e($g['title']) ?></div>
                        </td>
                        <td class="num"><?= e(formatUnits($g['units'])) ?></td>
                        <td>
                            <?php foreach ($g['meetings'] as $m): ?>
                                <div><?= e(DAYS[$m['day_of_week']] ?? $m['day_of_week']) ?>, <?= e(clock12($m['start_time'])) ?> to <?= e(clock12($m['end_time'])) ?>, room <?= e($m['room_name']) ?></div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/includes/calendar_widget.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>