<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/portal.php';
require_once __DIR__ . '/includes/login_log.php';

// Log out only via the POST form in the header (with CSRF token) so a stray link or image can't do it.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValid()) {
    $acct = currentAccount();                      // record who is signing out before the session is destroyed
    if ($acct) {
        logTeacherSignIn('logout', (int)$acct['id'], (string)$acct['email']);
    }
    doLogout();
    flash('success', 'You have been logged out.');
}
redirect('index.php');