<?php
/**
 * NestSync — Helper Functions
 * Shared utilities used across all pages.
 */

// ================================================================
// Flash Messages
// ================================================================

function setFlash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash_type']    = $type;
    $_SESSION['flash_message'] = $message;
}

function getFlash(): array|null
{
    if (!isset($_SESSION['flash_message'])) return null;
    $flash = [
        'type'    => $_SESSION['flash_type']    ?? 'info',
        'message' => $_SESSION['flash_message'],
    ];
    unset($_SESSION['flash_type'], $_SESSION['flash_message']);
    return $flash;
}

// ================================================================
// CSRF Protection
// ================================================================

function generateCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

// ================================================================
// Input Sanitization & Validation
// ================================================================

function sanitize(mixed $input): string
{
    return htmlspecialchars(strip_tags(trim((string)$input)), ENT_QUOTES, 'UTF-8');
}

function sanitizeEmail(string $email): string
{
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function isValidEmail(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

// ================================================================
// Redirect
// ================================================================

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit();
}

// ================================================================
// Formatting
// ================================================================

function formatDate(string|null $date, string $format = 'd M Y, h:i A'): string
{
    if (!$date || $date === '0000-00-00 00:00:00') return 'N/A';
    return date($format, strtotime($date));
}

function formatDateShort(string|null $date): string
{
    return formatDate($date, 'd M Y');
}

function formatCurrency(float|string $amount): string
{
    return '৳ ' . number_format((float)$amount, 2);
}

function timeAgo(string $datetime): string
{
    $time = time() - strtotime($datetime);
    if ($time < 60)    return 'Just now';
    if ($time < 3600)  return floor($time / 60) . 'm ago';
    if ($time < 86400) return floor($time / 3600) . 'h ago';
    if ($time < 604800) return floor($time / 86400) . 'd ago';
    return formatDateShort($datetime);
}

function truncate(string $text, int $length = 60): string
{
    return strlen($text) > $length ? substr($text, 0, $length) . '…' : $text;
}

// ================================================================
// Status Badges
// ================================================================

function statusBadge(string $status): string
{
    $map = [
        'PENDING'     => ['warning',   'fas fa-clock'],
        'APPROVED'    => ['success',   'fas fa-check-circle'],
        'REJECTED'    => ['danger',    'fas fa-times-circle'],
        'CANCELLED'   => ['secondary', 'fas fa-ban'],
        'ACTIVE'      => ['success',   'fas fa-check'],
        'INACTIVE'    => ['secondary', 'fas fa-pause'],
        'SUSPENDED'   => ['danger',    'fas fa-lock'],
        'AVAILABLE'   => ['success',   'fas fa-check'],
        'BOOKED'      => ['primary',   'fas fa-bed'],
        'RESERVED'    => ['warning',   'fas fa-hourglass-half'],
        'FULL'        => ['danger',    'fas fa-times'],
        'MAINTENANCE' => ['warning',   'fas fa-tools'],
        'SUGGESTED'   => ['info',      'fas fa-lightbulb'],
        'ACCEPTED'    => ['success',   'fas fa-handshake'],
        'DECLINED'    => ['danger',    'fas fa-thumbs-down'],
        'MALE'        => ['primary',   'fas fa-mars'],
        'FEMALE'      => ['pink',      'fas fa-venus'],
        'MIXED'       => ['info',      'fas fa-transgender'],
    ];
    [$cls, $icon] = $map[$status] ?? ['secondary', 'fas fa-circle'];
    $label = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
    return "<span class=\"badge bg-{$cls}\"><i class=\"{$icon} me-1\"></i>{$label}</span>";
}

function roleBadge(string $role): string
{
    $map = [
        'SYSTEM_ADMIN' => ['danger',  'fas fa-shield-alt', 'System Admin'],
        'HALL_ADMIN'   => ['warning', 'fas fa-user-tie',   'Hall Admin'],
        'STUDENT'      => ['primary', 'fas fa-graduation-cap', 'Student'],
    ];
    [$cls, $icon, $label] = $map[$role] ?? ['secondary', 'fas fa-user', $role];
    return "<span class=\"badge bg-{$cls}\"><i class=\"{$icon} me-1\"></i>{$label}</span>";
}

// ================================================================
// Pagination Helper
// ================================================================

function getPaginationVars(int $total, string $queryString = ''): array
{
    $perPage = PER_PAGE;
    $currentPage = max(1, (int)($_GET['page'] ?? 1));
    $totalPages  = (int)ceil($total / $perPage);
    $offset      = ($currentPage - 1) * $perPage;
    return compact('perPage', 'currentPage', 'totalPages', 'offset');
}

function renderPagination(int $total, int $currentPage, int $perPage, string $baseUrl): string
{
    $totalPages = (int)ceil($total / $perPage);
    if ($totalPages <= 1) return '';

    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    $html  = '<nav aria-label="Pagination"><ul class="pagination justify-content-center mb-0">';

    // Previous
    $prevDisabled = $currentPage <= 1 ? 'disabled' : '';
    $prevHref     = $currentPage <= 1 ? '#' : $baseUrl . $sep . 'page=' . ($currentPage - 1);
    $html .= "<li class=\"page-item {$prevDisabled}\"><a class=\"page-link\" href=\"{$prevHref}\">‹</a></li>";

    // Page numbers (show window of 5)
    $start = max(1, $currentPage - 2);
    $end   = min($totalPages, $currentPage + 2);
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $currentPage ? 'active' : '';
        $href   = $baseUrl . $sep . 'page=' . $i;
        $html  .= "<li class=\"page-item {$active}\"><a class=\"page-link\" href=\"{$href}\">{$i}</a></li>";
    }

    // Next
    $nextDisabled = $currentPage >= $totalPages ? 'disabled' : '';
    $nextHref     = $currentPage >= $totalPages ? '#' : $baseUrl . $sep . 'page=' . ($currentPage + 1);
    $html .= "<li class=\"page-item {$nextDisabled}\"><a class=\"page-link\" href=\"{$nextHref}\">›</a></li>";

    $html .= '</ul></nav>';

    $from  = min(($currentPage - 1) * $perPage + 1, $total);
    $to    = min($currentPage * $perPage, $total);
    $info  = "<p class=\"text-muted small text-center mt-2\">Showing {$from}–{$to} of {$total} records</p>";

    return $html . $info;
}

// ================================================================
// Notifications
// ================================================================

function getUnreadNotifCount(mysqli $conn): int
{
    if (!isset($_SESSION['user_id'])) return 0;
    $uid  = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();
    return (int)$cnt;
}

// ================================================================
// Roommate Matching Engine (PHP implementation)
// ================================================================

/**
 * Calculate a compatibility score between two students.
 * Returns ['score'=>float, 'reasons'=>string[], 'dept_match'=>bool, 'budget_match'=>bool, 'pref_overlap'=>int]
 */
function calculateMatchScore(array $s1, array $s2): array
{
    $score       = 0.0;
    $reasons     = [];
    $deptMatch   = false;
    $budgetMatch = false;
    $prefOverlap = 0;

    // Department (40 pts)
    if (!empty($s1['department']) && $s1['department'] === $s2['department']) {
        $score     += MATCH_DEPT_SCORE;
        $deptMatch  = true;
        $reasons[]  = 'Same department (' . $s1['department'] . ')';
    }

    // Budget (30 pts – within 25%)
    $b1 = (float)($s1['monthly_budget'] ?? 0);
    $b2 = (float)($s2['monthly_budget'] ?? 0);
    if ($b1 > 0 && $b2 > 0) {
        $range = abs($b1 - $b2) / max($b1, $b2);
        if ($range <= MATCH_BUDGET_RANGE) {
            $score      += MATCH_BUDGET_SCORE;
            $budgetMatch = true;
            $reasons[]   = 'Compatible budget range';
        }
    }

    // Preferences keyword overlap (10 pts each)
    $p1 = strtolower((string)($s1['preferences'] ?? ''));
    $p2 = strtolower((string)($s2['preferences'] ?? ''));
    $keywords = ['quiet', 'study', 'clean', 'early', 'non-smoker', 'smoker'];
    foreach ($keywords as $kw) {
        if (str_contains($p1, $kw) && str_contains($p2, $kw)) {
            $score      += MATCH_PREF_SCORE;
            $prefOverlap++;
            $reasons[]  = ucfirst($kw) . ' lifestyle match';
            if ($score >= 100) break;
        }
    }

    return [
        'score'        => min(100, round($score, 2)),
        'reasons'      => $reasons,
        'dept_match'   => $deptMatch,
        'budget_match' => $budgetMatch,
        'pref_overlap' => $prefOverlap,
        'reason_text'  => implode('; ', $reasons),
    ];
}

/**
 * Run matching engine for a single student against all other active students
 * and store results in roommate_matches table.
 */
function runMatchingForStudent(mysqli $conn, int $studentId): int
{
    // Get student profile
    $stmt = $conn->prepare(
        'SELECT user_id, department, monthly_budget, preferences
         FROM users WHERE user_id = ? AND role = ? AND account_status = ?'
    );
    $r = 'STUDENT'; $a = 'ACTIVE';
    $stmt->bind_param('iss', $studentId, $r, $a);
    $stmt->execute();
    $s1 = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$s1) return 0;

    // Get all other active students
    $stmt2 = $conn->prepare(
        'SELECT user_id, department, monthly_budget, preferences
         FROM users WHERE role = ? AND account_status = ? AND user_id != ?'
    );
    $stmt2->bind_param('ssi', $r, $a, $studentId);
    $stmt2->execute();
    $others = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();

    $count = 0;
    foreach ($others as $s2) {
        $result = calculateMatchScore($s1, $s2);
        $sid2   = (int)$s2['user_id'];
        $score  = $result['score'];
        $dm     = (int)$result['dept_match'];
        $bm     = (int)$result['budget_match'];
        $po     = $result['pref_overlap'];
        $reason = $result['reason_text'];

        $ins = $conn->prepare(
            'INSERT INTO roommate_matches
             (student_id, matched_student_id, match_score, dept_match, budget_match, pref_overlap, match_reason)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
             match_score=VALUES(match_score), dept_match=VALUES(dept_match),
             budget_match=VALUES(budget_match), pref_overlap=VALUES(pref_overlap),
             match_reason=VALUES(match_reason), matched_at=NOW()'
        );
        $ins->bind_param('iidiiis', $studentId, $sid2, $score, $dm, $bm, $po, $reason);
        $ins->execute();
        $ins->close();
        $count++;
    }
    return $count;
}
