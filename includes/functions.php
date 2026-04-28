<?php
/**
 * Global Helper Functions
 */

// -------------------------------------------------------
// Format currency in PKR
// -------------------------------------------------------
function formatCurrency(float $amount): string {
    return CURRENCY . ' ' . number_format($amount, 2);
}

// -------------------------------------------------------
// Sanitize output to prevent XSS
// -------------------------------------------------------
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// -------------------------------------------------------
// Calculate lease totals from motorcycle price + plan
// -------------------------------------------------------
function calcLeaseTotals(float $price, float $markupPercent, int $months): array {
    $totalAmount    = $price * (1 + $markupPercent / 100);
    $monthlyInstall = $totalAmount / $months;
    return [
        'total_amount'    => round($totalAmount, 2),
        'monthly_install' => round($monthlyInstall, 2),
    ];
}

// -------------------------------------------------------
// Generate a unique receipt number
// -------------------------------------------------------
function generateReceiptNo(): string {
    return 'RCP-' . strtoupper(substr(md5(uniqid((string)rand(), true)), 0, 8));
}

// -------------------------------------------------------
// Calculate remaining balance
// -------------------------------------------------------
function remainingBalance(float $totalAmount, float $paidAmount): float {
    return max(0, $totalAmount - $paidAmount);
}

// -------------------------------------------------------
// Days until / since a date
// -------------------------------------------------------
function daysDiff(string $dateStr): int {
    $date = new DateTime($dateStr);
    $now  = new DateTime('today');
    $diff = $now->diff($date);
    return (int) ($diff->invert ? -$diff->days : $diff->days);
}

function isOverdue(string $dueDateStr): bool {
    return daysDiff($dueDateStr) < 0;
}

// -------------------------------------------------------
// Customer scoring: recalculate based on payment history
// -------------------------------------------------------
function calculateCustomerScore(int $customerId): array {
    $pdo = getDB();

    // Get all leases for this customer
    $stmt = $pdo->prepare("
        SELECT l.id, l.total_amount, l.paid_amount, l.monthly_install,
               l.next_due_date, l.status
        FROM leases l
        WHERE l.customer_id = ?
    ");
    $stmt->execute([$customerId]);
    $leases = $stmt->fetchAll();

    if (empty($leases)) {
        return ['score' => 'good', 'score_value' => 100];
    }

    $score = 100;

    foreach ($leases as $lease) {
        // Deduct points for overdue
        if (isOverdue($lease['next_due_date']) && $lease['status'] === 'active') {
            $overdueDays = abs(daysDiff($lease['next_due_date']));
            $score -= min(30, $overdueDays * 2);
        }
        // Deduct for defaulted leases
        if ($lease['status'] === 'defaulted') {
            $score -= 40;
        }
    }

    $score = max(0, min(100, $score));

    if ($score >= 75)      $label = 'good';
    elseif ($score >= 40)  $label = 'average';
    else                   $label = 'risky';

    return ['score' => $label, 'score_value' => $score];
}

// -------------------------------------------------------
// Update customer score in DB
// -------------------------------------------------------
function updateCustomerScore(int $customerId): void {
    $scoreData = calculateCustomerScore($customerId);
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE customers SET score=?, score_value=? WHERE id=?");
    $stmt->execute([$scoreData['score'], $scoreData['score_value'], $customerId]);
}

// -------------------------------------------------------
// Log an activity
// -------------------------------------------------------
function logActivity(string $action, string $targetType = '', int $targetId = 0): void {
    try {
        $pdo  = getDB();
        $user = currentUser();
        $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, user_name, action, target_type, target_id, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'] ?: null,
            $user['name'],
            $action,
            $targetType ?: null,
            $targetId ?: null,
            $ip,
        ]);
    } catch (Exception $e) {
        error_log('Activity log failed: ' . $e->getMessage());
    }
}

// -------------------------------------------------------
// Pagination helper
// -------------------------------------------------------
function paginate(int $totalRows, int $perPage, int $currentPage): array {
    $totalPages  = (int) ceil($totalRows / $perPage);
    $currentPage = max(1, min($totalPages, $currentPage));
    $offset      = ($currentPage - 1) * $perPage;
    return [
        'total_rows'   => $totalRows,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => $offset,
    ];
}

function renderPagination(array $pagInfo, string $baseUrl): string {
    if ($pagInfo['total_pages'] <= 1) return '';
    $html = '<nav class="pagination">';
    if ($pagInfo['current_page'] > 1) {
        $html .= '<a href="' . $baseUrl . '&page=' . ($pagInfo['current_page'] - 1) . '" class="page-btn">‹ Prev</a>';
    }
    for ($i = 1; $i <= $pagInfo['total_pages']; $i++) {
        $active = $i === $pagInfo['current_page'] ? ' active' : '';
        $html .= '<a href="' . $baseUrl . '&page=' . $i . '" class="page-btn' . $active . '">' . $i . '</a>';
    }
    if ($pagInfo['current_page'] < $pagInfo['total_pages']) {
        $html .= '<a href="' . $baseUrl . '&page=' . ($pagInfo['current_page'] + 1) . '" class="page-btn">Next ›</a>';
    }
    $html .= '</nav>';
    return $html;
}

// -------------------------------------------------------
// Update lease next_due_date based on last payment date
// -------------------------------------------------------
function advanceNextDueDate(int $leaseId): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT next_due_date, end_date, paid_amount, total_amount FROM leases WHERE id=?");
    $stmt->execute([$leaseId]);
    $lease = $stmt->fetch();
    if (!$lease) return;

    // If fully paid, mark completed
    if ($lease['paid_amount'] >= $lease['total_amount']) {
        $pdo->prepare("UPDATE leases SET status='completed' WHERE id=?")->execute([$leaseId]);
        return;
    }

    // Advance due date by 1 month
    $nextDue = new DateTime($lease['next_due_date']);
    $nextDue->modify('+1 month');
    $endDate = new DateTime($lease['end_date']);

    $newDue = $nextDue > $endDate ? $lease['end_date'] : $nextDue->format('Y-m-d');
    $pdo->prepare("UPDATE leases SET next_due_date=? WHERE id=?")->execute([$newDue, $leaseId]);
}
