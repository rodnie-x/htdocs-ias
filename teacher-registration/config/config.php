<?php
// >>> PLACE AT: teacher-registration/config/config.php  (replace the old file ON THE LIVE SERVER only; keep your XAMPP copy separate)
/**
 * Teacher Registration portal - settings.
 */
date_default_timezone_set('Asia/Manila');

/* ------------------------------------------------------------------
 * Database - must be the SAME database the Class Scheduling System uses.
 * FILL IN the 4 values from InfinityFree: Client Area > MySQL Databases.
 * ------------------------------------------------------------------ */
define('DB_HOST', 'sql313.infinityfree.com');             // InfinityFree → sqlXXX.infinityfree.com
define('DB_NAME', 'if0_43074378_css_system');            // InfinityFree → if0_XXXXXXXX_css_system
define('DB_USER', 'if0_43074378');             // InfinityFree → if0_XXXXXXXX
define('DB_PASS', 'ni21uEmwihXstg9'); // InfinityFree → your MySQL password

/* ------------------------------------------------------------------
 * App
 * ------------------------------------------------------------------ */
define('APP_NAME', 'BCP Teacher Registration');

/* ------------------------------------------------------------------
 * APP_SECRET - signs every emailed login code.
 * A random value is generated on first run and saved in
 * config/app_secret.php (protected by config/.htaccess).
 * Never hard-code a value here; never commit app_secret.php to git.
 * ------------------------------------------------------------------ */
$_secretFile = __DIR__ . '/app_secret.php';
if (is_file($_secretFile)) {
    @chmod($_secretFile, 0644);   // keep it read-only for everyone but the owner
    require $_secretFile;   // defines APP_SECRET
} else {
    $secret = bin2hex(random_bytes(32));   // 64 hex characters
    $code   = "<?php\ndefine('APP_SECRET', '$secret');\n";
    if (file_put_contents($_secretFile, $code) === false) {
        http_response_code(500);
        exit(
            'Cannot write config/app_secret.php. ' .
            'Give the web server write permission to the config/ folder, ' .
            'then reload this page once. After the file is created you can ' .
            'remove write permission again.'
        );
    }
    define('APP_SECRET', $secret);
    unset($secret, $code);
}
unset($_secretFile);

// false = NEVER show login codes on screen. Keep false on any real server.
define('APP_DEBUG', false);

/* ------------------------------------------------------------------
 * Email (sends the login code to the teacher's Gmail)
 * MAIL_MODE 'smtp' : Gmail SMTP. Needs a Google "App Password".
 * ------------------------------------------------------------------ */
define('MAIL_MODE', 'smtp');
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);                                   // 465 = SSL, 587 = STARTTLS
define('MAIL_USERNAME', 'bcp.css.system@gmail.com');
define('MAIL_PASSWORD', 'luzfwhmkwgwifogl');
define('MAIL_FROM_ADDRESS', 'bcp.css.system@gmail.com');
define('MAIL_FROM_NAME', 'BCP Teacher Registration');

/* ------------------------------------------------------------------
 * Login code + lockout rules
 * ------------------------------------------------------------------ */
define('CODE_TTL_MINUTES', 10);
define('CODE_RESEND_SECONDS', 60);
define('MAX_FAILED_ATTEMPTS', 5);
define('LOCK_MINUTES', 15);
define('IDLE_TIMEOUT_SECONDS', 7200);     // auto log-out after 2 hours idle

/* ------------------------------------------------------------------
 * Dropdown lists
 * ------------------------------------------------------------------ */
define('PROGRAMS', [
    'BSIT'   => 'BSIT - Information Technology',
    'BSCS'   => 'BSCS - Computer Science',
    'BSBA'   => 'BSBA - Business Administration',
    'BSA'    => 'BSA - Accountancy',
    'BSOA'   => 'BSOA - Office Administration',
    'BSHM'   => 'BSHM - Hospitality Management',
    'BSTM'   => 'BSTM - Tourism Management',
    'BSCRIM' => 'BSCrim - Criminology',
    'BSED'   => 'BSEd - Secondary Education',
    'BEED'   => 'BEEd - Elementary Education',
]);

define('YEAR_LEVELS', [
    '1st Year' => '1st Year',
    '2nd Year' => '2nd Year',
    '3rd Year' => '3rd Year',
    '4th Year' => '4th Year',
]);

// Same day codes the scheduling system stores in schedules.day_of_week
define('DAYS', [
    'Mon' => 'Monday',
    'Tue' => 'Tuesday',
    'Wed' => 'Wednesday',
    'Thu' => 'Thursday',
    'Fri' => 'Friday',
    'Sat' => 'Saturday',
]);

// Earliest / latest time a teacher can mark as available
define('AVAIL_MIN', '06:00');
define('AVAIL_MAX', '21:00');