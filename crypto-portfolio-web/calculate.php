<?php
declare(strict_types=1);

/**
 * Calculation and transaction helper functions.
 */

require_once __DIR__ . '/config.php';
require_login();

/**
 * Ensure a user has a portfolio row for an asset.
 */
function ensure_portfolio_row(PDO $pdo, int $userId, int $assetId): void
{
    $stmt = $pdo->prepare('SELECT id FROM portfolios WHERE user_id = :user_id AND asset_id = :asset_id LIMIT 1');
    $stmt->execute(['user_id' => $userId, 'asset_id' => $assetId]);

    if (!$stmt->fetch()) {
        $insert = $pdo->prepare(
            'INSERT INTO portfolios (user_id, asset_id, allocation_percentage, initial_investment, dca_per_month, current_quantity, total_invested)
             VALUES (:user_id, :asset_id, 0, 0, 0, 0, 0)'
        );
        $insert->execute(['user_id' => $userId, 'asset_id' => $assetId]);
    }
}

/**
 * Recalculate allocation percentages based on current value (quantity * latest price).
 */
function recalculate_allocation_percentages(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare(
        'SELECT
            p.id,
            p.current_quantity,
            COALESCE((
                SELECT ph.price
                FROM price_history ph
                WHERE ph.asset_id = p.asset_id
                ORDER BY ph.recorded_at DESC, ph.id DESC
                LIMIT 1
            ), 0) AS latest_price
         FROM portfolios p
         WHERE p.user_id = :user_id'
    );
    $stmt->execute(['user_id' => $userId]);
    $rows = $stmt->fetchAll();

    $totalValue = 0.0;
    foreach ($rows as $row) {
        $totalValue += to_float($row['current_quantity']) * to_float($row['latest_price']);
    }

    $update = $pdo->prepare('UPDATE portfolios SET allocation_percentage = :allocation WHERE id = :id AND user_id = :user_id');
    foreach ($rows as $row) {
        $assetValue = to_float($row['current_quantity']) * to_float($row['latest_price']);
        $allocation = $totalValue > 0 ? ($assetValue / $totalValue) * 100 : 0;
        $update->execute([
            'allocation' => round($allocation, 2),
            'id' => (int) $row['id'],
            'user_id' => $userId,
        ]);
    }
}

/**
 * Insert a BUY/SELL transaction and update portfolio holdings atomically.
 */
function insert_transaction(PDO $pdo, int $userId, int $assetId, string $type, float $quantity, float $unitPrice, string $txDate, ?string $reason = null): array
{
    $type = strtoupper($type);
    if (!in_array($type, ['BUY', 'SELL'], true)) {
        return ['ok' => false, 'message' => 'Invalid transaction type.'];
    }
    if ($quantity <= 0 || $unitPrice <= 0) {
        return ['ok' => false, 'message' => 'Quantity and unit price must be greater than 0.'];
    }

    $txDate = trim($txDate);
    if ($txDate === '') {
        $txDate = date('Y-m-d H:i:s');
    }

    try {
        $pdo->beginTransaction();

        ensure_portfolio_row($pdo, $userId, $assetId);

        $portfolioStmt = $pdo->prepare(
            'SELECT id, current_quantity, total_invested
             FROM portfolios
             WHERE user_id = :user_id AND asset_id = :asset_id
             LIMIT 1
             FOR UPDATE'
        );
        $portfolioStmt->execute(['user_id' => $userId, 'asset_id' => $assetId]);
        $portfolio = $portfolioStmt->fetch();

        if (!$portfolio) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Portfolio entry could not be created.'];
        }

        $currentQuantity = to_float($portfolio['current_quantity']);
        $currentInvested = to_float($portfolio['total_invested']);

        if ($type === 'SELL' && $quantity > $currentQuantity) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Cannot sell more than current holding.'];
        }

        if ($type === 'BUY') {
            $newQuantity = $currentQuantity + $quantity;
            $newInvested = $currentInvested + ($quantity * $unitPrice);
        } else {
            $averageCost = $currentQuantity > 0 ? ($currentInvested / $currentQuantity) : 0;
            $newQuantity = $currentQuantity - $quantity;
            $newInvested = max(0, $currentInvested - ($averageCost * $quantity));
        }

        $insertTx = $pdo->prepare(
            'INSERT INTO transactions (user_id, asset_id, type, quantity, unit_price, `date`, reason)
             VALUES (:user_id, :asset_id, :type, :quantity, :unit_price, :tx_date, :reason)'
        );
        $insertTx->execute([
            'user_id' => $userId,
            'asset_id' => $assetId,
            'type' => $type,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tx_date' => $txDate,
            'reason' => $reason !== '' ? $reason : null,
        ]);

        $updatePortfolio = $pdo->prepare(
            'UPDATE portfolios
             SET current_quantity = :current_quantity, total_invested = :total_invested
             WHERE id = :id AND user_id = :user_id'
        );
        $updatePortfolio->execute([
            'current_quantity' => $newQuantity,
            'total_invested' => $newInvested,
            'id' => (int) $portfolio['id'],
            'user_id' => $userId,
        ]);

        recalculate_allocation_percentages($pdo, $userId);

        $pdo->commit();
        return ['ok' => true, 'message' => 'Transaction saved successfully.'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => 'Failed to save transaction.'];
    }
}

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
    p.current_quantity,
    p.total_invested,
    (
        SELECT ph.price
        FROM price_history ph
        WHERE ph.asset_id = p.asset_id
        ORDER BY ph.recorded_at DESC, ph.id DESC
        LIMIT 1
    ) AS latest_price
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
    $totalInvested = 0.0;

    foreach ($rows as &$row) {
        $initial = to_float($row['initial_investment']);
        $dcaMonthly = to_float($row['dca_per_month']);
        $dca48 = $dcaMonthly * 48;
        $price = to_float($row['latest_price']);
        $quantity = to_float($row['current_quantity']);
        $investedByTx = to_float($row['total_invested']);

        $currentValue = $quantity * $price;
        $profitLoss = $currentValue - $investedByTx;

        $row['dca_4y_total'] = $dca48;
        $row['current_value'] = $currentValue;
        $row['profit_loss'] = $profitLoss;
        $row['quantity_held'] = $quantity;
        $row['latest_price'] = $price;
        $row['invested_total'] = $investedByTx;

        $totalInitial += $initial;
        $totalCurrent += $currentValue;
        $totalDca48 += $dca48;
        $totalInvested += $investedByTx;
    }
    unset($row);

    return [
        'items' => $rows,
        'totals' => [
            'initial_investment' => $totalInitial,
            'dca_4y_total' => $totalDca48,
            'invested_total' => $totalInvested,
            'current_value' => $totalCurrent,
            'profit_loss' => $totalCurrent - $totalInvested,
        ],
    ];
}

/**
 * Generate month-by-month DCA projections for 4 years.
 */
function calculate_dca_projection(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT p.dca_per_month, p.initial_investment
         FROM portfolios p
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

/**
 * Get BUY and SELL transactions with optional filters.
 */
function get_transactions(PDO $pdo, int $userId, string $type, ?int $assetId = null, ?string $fromDate = null, ?string $toDate = null): array
{
    $conditions = ['t.user_id = :user_id', 't.type = :type'];
    $params = ['user_id' => $userId, 'type' => strtoupper($type)];

    if ($assetId !== null && $assetId > 0) {
        $conditions[] = 't.asset_id = :asset_id';
        $params['asset_id'] = $assetId;
    }

    if ($fromDate) {
        $conditions[] = 't.`date` >= :from_date';
        $params['from_date'] = $fromDate . ' 00:00:00';
    }

    if ($toDate) {
        $conditions[] = 't.`date` <= :to_date';
        $params['to_date'] = $toDate . ' 23:59:59';
    }

    $sql = 'SELECT t.id, t.asset_id, t.type, t.quantity, t.unit_price, t.`date`, t.reason, a.name, a.symbol
            FROM transactions t
            INNER JOIN assets a ON a.id = t.asset_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY t.`date` DESC, t.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function calculate_transaction_totals(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            COALESCE(SUM(CASE WHEN type = "BUY" THEN quantity * unit_price ELSE 0 END), 0) AS total_buy,
            COALESCE(SUM(CASE WHEN type = "SELL" THEN quantity * unit_price ELSE 0 END), 0) AS total_sell
         FROM transactions
         WHERE user_id = :user_id'
    );
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();

    return [
        'total_buy' => to_float($row['total_buy'] ?? 0),
        'total_sell' => to_float($row['total_sell'] ?? 0),
    ];
}

if (isset($_GET['action']) && $_GET['action'] === 'summary') {
    header('Content-Type: application/json');

    $userId = current_user_id();
    $summary = calculate_portfolio_summary($pdo, $userId);
    $projection = calculate_dca_projection($pdo, $userId);
    $txTotals = calculate_transaction_totals($pdo, $userId);

    echo json_encode([
        'summary' => $summary,
        'projection' => $projection,
        'transactions' => $txTotals,
    ], JSON_PRETTY_PRINT);
    exit;
}
