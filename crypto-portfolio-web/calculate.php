<?php
declare(strict_types=1);

/**
 * Calculation helper endpoints and shared functions.
 *
 * If requested directly with ?action=summary, returns JSON metrics
 * for AJAX use. Dashboard currently includes this file directly.
 */

require_once __DIR__ . '/config.php';
require_login();

/**
 * Build user portfolio summary including P/L calculations.
 */
function calculate_portfolio_summary(PDO $pdo, int $userId): array
{
    $sql = <<<SQL
SELECT
    p.id AS portfolio_id,
    p.asset_id,
    a.name,
    a.symbol,
    a.category,
    a.decimals,
    p.allocation_percentage,
    p.initial_investment,
    p.dca_per_month,
    (
        SELECT ph.price
        FROM price_history ph
        WHERE ph.asset_id = p.asset_id
        ORDER BY ph.recorded_at DESC, ph.id DESC
        LIMIT 1
    ) AS latest_price,
    (
        SELECT COALESCE(SUM(CASE
            WHEN t.type IN ('BUY','DEPOSIT') THEN t.amount
            WHEN t.type IN ('SELL','WITHDRAW') THEN -t.amount
            ELSE 0 END), 0)
        FROM transactions t
        WHERE t.user_id = p.user_id AND t.asset_id = p.asset_id
    ) AS quantity_held
FROM portfolios p
INNER JOIN assets a ON a.id = p.asset_id
WHERE p.user_id = :user_id
ORDER BY a.category, a.symbol
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $rows = $stmt->fetchAll();

    $totalInitial = 0.0;
    $totalCurrent = 0.0;
    $totalDca48 = 0.0;

    foreach ($rows as &$row) {
        $initial = to_float($row['initial_investment']);
        $dcaMonthly = to_float($row['dca_per_month']);
        $dca48 = $dcaMonthly * 48;
        $price = to_float($row['latest_price']);
        $quantity = to_float($row['quantity_held']);

        // Fallback estimation if no transactions yet: convert planned invested amount to quantity.
        if ($quantity <= 0 && $price > 0) {
            $quantity = ($initial + $dca48) / $price;
        }

        $currentValue = $quantity * $price;
        $investedTotal = $initial + $dca48;
        $profitLoss = $currentValue - $investedTotal;

        $row['dca_4y_total'] = $dca48;
        $row['invested_total'] = $investedTotal;
        $row['current_value'] = $currentValue;
        $row['profit_loss'] = $profitLoss;
        $row['quantity_held'] = $quantity;
        $row['latest_price'] = $price;

        $totalInitial += $initial;
        $totalCurrent += $currentValue;
        $totalDca48 += $dca48;
    }
    unset($row);

    return [
        'items' => $rows,
        'totals' => [
            'initial_investment' => $totalInitial,
            'dca_4y_total' => $totalDca48,
            'invested_total' => $totalInitial + $totalDca48,
            'current_value' => $totalCurrent,
            'profit_loss' => $totalCurrent - ($totalInitial + $totalDca48),
        ],
    ];
}

/**
 * Generate month-by-month DCA projections for 4 years.
 */
function calculate_dca_projection(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT p.dca_per_month, p.initial_investment, a.symbol
         FROM portfolios p
         INNER JOIN assets a ON a.id = p.asset_id
         WHERE p.user_id = :user_id'
    );
    $stmt->execute(['user_id' => $userId]);
    $assets = $stmt->fetchAll();

    $labels = [];
    $values = [];

    $monthlyDca = 0.0;
    $initialTotal = 0.0;

    foreach ($assets as $asset) {
        $monthlyDca += to_float($asset['dca_per_month']);
        $initialTotal += to_float($asset['initial_investment']);
    }

    $running = $initialTotal;
    for ($month = 1; $month <= 48; $month++) {
        $running += $monthlyDca;
        $labels[] = 'M' . $month;
        $values[] = round($running, 2);
    }

    return ['labels' => $labels, 'values' => $values];
}

if (isset($_GET['action']) && $_GET['action'] === 'summary') {
    header('Content-Type: application/json');

    $userId = current_user_id();
    $summary = calculate_portfolio_summary($pdo, $userId);
    $projection = calculate_dca_projection($pdo, $userId);

    echo json_encode([
        'summary' => $summary,
        'projection' => $projection,
    ], JSON_PRETTY_PRINT);
    exit;
}
