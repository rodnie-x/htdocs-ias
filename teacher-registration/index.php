<?php
// >>> PLACE AT: teacher-registration/index.php (replace the old file)  [batch 6: limits, failed-login log, no account probing]
// This file REPLACES patch 3 from batch6/PATCHES.md.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/login_log.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$tab    = strIn($_GET, 'tab') === 'signup' ? 'signup' : 'login';
$errors = ['login' => [], 'signup' => []];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = strIn($_POST, 'action');

    if ($action === 'signup') {
        $tab = 'signup';
        $old = $_POST;

        if (!rateLimitAllow('treg_signup', 5, 3600)) {
            $errors['signup'][] = 'Too many sign-ups from this network. Try again later.';
        } else {
            $result = registerAccount($_POST);

            if ($result['ok']) {
                $account = $result['account'];     // null when the Gmail already had an account (not revealed)
                $debug   = null;
                if ($account !== null) {
                    $sent = issueLoginCode($account);
                    $debug = $sent['debug_code'];
                    if (!$sent['sent']) {
                        error_log('Sign-up code email failed for account #' . $account['id']);
                    }
                }
                // The same message for a new and an existing Gmail, so sign-up can't be used to find out who is registered.
                $msg = 'Thanks. If this Gmail can be registered, a 6-digit login code is on its way. Enter it on the Log in tab with your password. '
                     . 'If you already have an account, select "Send code" instead.';
                if ($debug !== null) {
                    $msg .= ' (Dev mode: your code is ' . $debug . '.)';
                }
                flash('info', $msg);
                $_SESSION['prefill_email'] = strtolower(trim(strIn($_POST, 'email')));
                redirect('index.php?tab=login');
            }
            $errors['signup'] = $result['errors'];
        }

    } elseif ($action === 'login') {
        $tab   = 'login';
        $tried = strtolower(trim(strIn($_POST, 'email')));
        $old   = ['email' => strIn($_POST, 'email')];

        if (!rateLimitAllow('treg_login', 30, 900)) {
            $errors['login'][] = 'Too many log-in attempts from this network. Try again in a few minutes.';
        } else {
            $res = attemptLogin(strIn($_POST, 'email'), trim(strIn($_POST, 'code')), strIn($_POST, 'password'));
            if ($res['success']) {
                redirect('dashboard.php');
            }
            $acc = isGmail($tried) ? findAccountByEmail($tried) : null;
            logTeacherSignIn('failed', $acc ? (int)$acc['id'] : null, $tried);
            $errors['login'][] = $res['message'];
        }
    }
}

$prefillEmail = $_SESSION['prefill_email'] ?? '';
unset($_SESSION['prefill_email']);
$loginEmail = strIn($old, 'email') !== '' ? strIn($old, 'email') : (is_string($prefillEmail) ? $prefillEmail : '');

$hasLogo = is_file(__DIR__ . '/assets/images/logo.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in or sign up | <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;800&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<style>
/* Login: the campus photo is the whole page; the login form floats on it as frosted glass */
body.auth-body{
  background:
    linear-gradient(180deg, rgba(10,28,100,.62) 0%, rgba(10,28,100,.84) 100%),
    url("/css-system/assets/images/login-bg.png") 62% center / cover no-repeat fixed,
    #1f3a9d;
  margin: 0;
  font-family: 'Figtree', 'Inter', sans-serif;
}
.auth-brand,.auth-panel{background:transparent !important}
.auth-brand-inner{position:relative;z-index:1;text-shadow:0 2px 14px rgba(0,10,60,.45)}
.auth-brand .brand-mark{text-shadow:none; font-size: 1.5rem; font-weight: 800; background: #fff; color: #1f3a9d; padding: 4px 10px; border-radius: 6px;}
.auth-brand h1 { margin-top: 20px; margin-bottom: 10px; }
.auth-brand .auth-steps {color:#fff; text-shadow:0 1px 8px rgba(0,10,60,.45);}
.auth-brand .auth-steps strong { color:#ffd54d; }

/* card: overflow must stay visible so the native select popup renders outside it */
.auth-card{
  background:rgba(255,255,255,.12) !important;
  border:1px solid rgba(255,255,255,.30) !important;
  -webkit-backdrop-filter:blur(16px) saturate(1.2);
          backdrop-filter:blur(16px) saturate(1.2);
  box-shadow:0 20px 60px rgba(0,10,60,.35) !important;
  color:#fff !important;
  overflow: visible !important;
  position: relative;
  z-index: 10;
}
.auth-card h1,.auth-card h2,.auth-card label{color:#fff !important;}
.auth-card .auth-sub,.auth-card .hint,.auth-card .muted,.auth-card .switch-note,.auth-card .optional{color:rgba(255,255,255,.85) !important;}
.auth-card input[type=text],
.auth-card input[type=email],
.auth-card input[type=password]{
  background:rgba(255,255,255,.14) !important;
  border:1px solid rgba(255,255,255,.42) !important;
  color:#fff !important;
}
/* select styled to match inputs; custom arrow so it looks good on the frosted card */
.auth-card select{
  background:rgba(30,55,160,.85) !important;
  border:1px solid rgba(255,255,255,.42) !important;
  color:#fff !important;
  -webkit-appearance: none;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23fff' stroke-width='2' fill='none' stroke-linecap='round'/%3E%3C/svg%3E") !important;
  background-repeat: no-repeat !important;
  background-position: right 12px center !important;
  padding-right: 36px !important;
}
.auth-card select option{
  background: #1a3490;
  color: #fff;
}
.auth-card input:focus,
.auth-card select:focus{
  background:rgba(255,255,255,.22) !important;
  border-color:#fff !important;
  outline:3px solid rgba(255,255,255,.28) !important;
}
.auth-card input:-webkit-autofill{-webkit-text-fill-color:#fff !important;transition:background-color 9999s ease-out 0s;}
.auth-card .alert,.auth-card .alert-error{background:rgba(255,240,240,.96) !important;color:#8e1c16 !important;border:1px solid #d64545 !important; padding: 10px; border-radius: 6px; margin-bottom: 15px;}
.auth-card .alert-info{background:rgba(220,240,255,.96) !important;color:#0a4480 !important;border:1px solid #5aacff !important;}
.auth-card a{color:#ffd54d !important;}
.tabs{display:flex;gap:8px;margin:0 0 18px}
.tabs a{flex:1;text-align:center;padding:9px 8px;border-radius:9px;border:1px solid rgba(255,255,255,.3);color:#fff;text-decoration:none;font-weight:600;font-size:.9rem}
.tabs a.is-active{background:rgba(255,255,255,.28)}
.inline-field{display:flex;gap:8px}
.inline-field input{flex:1;min-width:0;letter-spacing:.12em}
.inline-field .btn{white-space:nowrap}
.auth-card .btn-outline{background:rgba(255,255,255,.9) !important;color:#1f3a9d !important;}
.btn-primary,.auth-card .btn-primary{background-color:#ffd54d !important; color:#1f3a9d !important; font-weight:bold !important;}
.btn-primary:hover{background-color:#ffca28 !important;}
.pw-field{position:relative;}.pw-field input{padding-right:60px}
.pw-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:transparent;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:.85rem;}
@media(max-width:900px){
  .auth{grid-template-columns:1fr !important;}.auth-brand{padding:32px !important;}
}
</style>
</head>
<body class="auth-body">
<main class="auth">

    <section class="auth-brand">
        <div class="auth-brand-inner">
            <?php if ($hasLogo): ?>
                <img class="auth-logo" src="assets/images/logo.png" alt="Bestlink College of the Philippines logo">
            <?php else: ?>
                <span class="brand-mark brand-mark-lg">BCP</span>
            <?php endif; ?>
            <h1>Teacher registration</h1>
            <p class="auth-lede">Tell the school when you can teach and which subjects you can handle. The Registrar builds your timetable from it.</p>

            <ol class="auth-steps">
                <li><strong>Sign up</strong> with your Gmail.</li>
                <li><strong>Fill in</strong> your available days and times, and the subjects you can teach.</li>
                <li><strong>Get scheduled.</strong> Once the admin approves you, your sections and classes appear under My schedules.</li>
            </ol>
            <p class="auth-school">Bestlink College of the Philippines</p>
        </div>
    </section>

    <section class="auth-panel">
        <div class="auth-card">

            <?php foreach (takeFlashes() as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div>
            <?php endforeach; ?>

            <div class="tabs" role="tablist" aria-label="Log in or sign up">
                <a href="index.php?tab=login"  data-tab-link="login"  role="tab" class="<?= $tab === 'login'  ? 'is-active' : '' ?>" aria-selected="<?= $tab === 'login'  ? 'true' : 'false' ?>">Log in</a>
                <a href="index.php?tab=signup" data-tab-link="signup" role="tab" class="<?= $tab === 'signup' ? 'is-active' : '' ?>" aria-selected="<?= $tab === 'signup' ? 'true' : 'false' ?>">Sign up</a>
            </div>

            <!-- ============ LOG IN ============ -->
            <form method="POST" action="index.php" id="login-form" data-panel="login" <?= $tab === 'login' ? '' : 'hidden' ?>>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="login">

                <?php if ($errors['login']): ?>
                    <div class="alert alert-error" role="alert"><?= e(implode(' ', $errors['login'])) ?></div>
                <?php endif; ?>

                <div class="field">
                    <label for="l-email">Gmail account</label>
                    <input type="email" id="l-email" name="email" required autocomplete="email" placeholder="name@gmail.com" value="<?= e($loginEmail) ?>">
                </div>

                <div class="field">
                    <label for="l-code">Login code</label>
                    <div class="inline-field">
                        <input type="text" id="l-code" name="code" required inputmode="numeric" autocomplete="one-time-code"
                               maxlength="6" pattern="[0-9]{6}" placeholder="6 digits" aria-describedby="code-status">
                        <button type="button" class="btn btn-outline" id="send-code">Send code</button>
                    </div>
                    <p class="hint" id="code-status" role="status" aria-live="polite">We email the code to your Gmail. It expires in <?= (int)CODE_TTL_MINUTES ?> minutes.</p>
                </div>

                <div class="field">
                    <label for="l-password">Password</label>
                    <div class="pw-field">
                        <input type="password" id="l-password" name="password" required autocomplete="current-password">
                        <button type="button" class="pw-toggle" data-toggle-pw="l-password" aria-label="Show password">Show</button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Log in</button>
                <p class="switch-note">No account yet? <a href="index.php?tab=signup" data-tab-link="signup">Sign up</a></p>
            </form>

            <!-- ============ SIGN UP ============ -->
            <form method="POST" action="index.php" id="signup-form" data-panel="signup" <?= $tab === 'signup' ? '' : 'hidden' ?>>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="signup">

                <?php if ($errors['signup']): ?>
                    <div class="alert alert-error" role="alert">
                        <strong>Fix the following and try again:</strong>
                        <ul><?php foreach ($errors['signup'] as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <div class="field-row field-row-3">
                    <div class="field">
                        <label for="s-first">First name</label>
                        <input type="text" id="s-first" name="first_name" required maxlength="60" autocomplete="given-name" value="<?= e(strIn($old, 'first_name')) ?>">
                    </div>
                    <div class="field">
                        <label for="s-middle">Middle name <span class="optional">(optional)</span></label>
                        <input type="text" id="s-middle" name="middle_name" maxlength="60" autocomplete="additional-name" value="<?= e(strIn($old, 'middle_name')) ?>">
                    </div>
                    <div class="field">
                        <label for="s-last">Last name</label>
                        <input type="text" id="s-last" name="last_name" required maxlength="60" autocomplete="family-name" value="<?= e(strIn($old, 'last_name')) ?>">
                    </div>
                </div>

                <div class="field">
                    <label for="s-email">Gmail account</label>
                    <input type="email" id="s-email" name="email" required autocomplete="email" placeholder="name@gmail.com" value="<?= e(strIn($old, 'email')) ?>">
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="s-program">Program</label>
                        <select id="s-program" name="program" required>
                            <option value="" disabled <?= strIn($old, 'program') === '' ? 'selected' : '' ?>>Choose program</option>
                            <?php foreach (PROGRAMS as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= strIn($old, 'program') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="s-year">Year level</label>
                        <select id="s-year" name="year_level" required>
                            <option value="" disabled <?= strIn($old, 'year_level') === '' ? 'selected' : '' ?>>Choose year level</option>
                            <?php foreach (YEAR_LEVELS as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= strIn($old, 'year_level') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="s-password">Password</label>
                        <div class="pw-field">
                            <input type="password" id="s-password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
                            <button type="button" class="pw-toggle" data-toggle-pw="s-password" aria-label="Show password">Show</button>
                        </div>
                    </div>
                    <div class="field">
                        <label for="s-confirm">Confirm password</label>
                        <div class="pw-field">
                            <input type="password" id="s-confirm" name="confirm_password" required minlength="8" maxlength="72" autocomplete="new-password">
                            <button type="button" class="pw-toggle" data-toggle-pw="s-confirm" aria-label="Show confirm password">Show</button>
                        </div>
                    </div>
                </div>
                <p class="hint">At least 8 characters, with a letter and a number.</p>

                <button type="submit" class="btn btn-primary btn-block">Create account</button>
                <p class="switch-note">Already registered? <a href="index.php?tab=login" data-tab-link="login">Log in</a></p>
            </form>

        </div>
    </section>
</main>
<script src="assets/js/app.js" defer></script>
</body>
</html>

