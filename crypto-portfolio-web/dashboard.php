<?php
declare(strict_types=1);

require_once __DIR__ . '/calculate.php';

$userId = current_user_id();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'add_transaction') {
    $assetId = (int) ($_POST['asset_id'] ?? 0);
    $type = strtoupper(trim((string) ($_POST['type'] ?? 'BUY')));
    $quantity = to_float($_POST['quantity'] ?? 0);
    $unitPrice = to_float($_POST['unit_price'] ?? 0);
    $txDate = trim((string) ($_POST['tx_date'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));

    if ($assetId <= 0) {
        $errors[] = 'Please select an asset.';
    }
    if (!in_array($type, ['BUY', 'SELL'], true)) {
        $errors[] = 'Transaction type must be BUY or SELL.';
    }
    if ($quantity <= 0) {
        $errors[] = 'Quantity must be greater than 0.';
    }
    if ($unitPrice <= 0) {
        $errors[] = 'Unit price must be greater than 0.';
    }

    if (!$errors) {
        $result = insert_transaction($pdo, $userId, $assetId, $type, $quantity, $unitPrice, $txDate, $reason);
        if ($result['ok']) {
            set_flash('success', $result['message']);
            header('Location: dashboard.php');
            exit;
        }

        $errors[] = $result['message'];
    }
}

$summary = calculate_portfolio_summary($pdo, $userId);
$projection = calculate_dca_projection($pdo, $userId);
$transactionTotals = calculate_transaction_totals($pdo, $userId);

$items = $summary['items'];
$totals = $summary['totals'];

$assets = $pdo->query('SELECT id, name, symbol FROM assets ORDER BY symbol')->fetchAll();

$filterAssetId = (int) ($_GET['filter_asset_id'] ?? 0);
$filterFromDate = trim((string) ($_GET['from_date'] ?? ''));
$filterToDate = trim((string) ($_GET['to_date'] ?? ''));

$buyTransactions = get_transactions(
    $pdo,
    $userId,
    'BUY',
    $filterAssetId > 0 ? $filterAssetId : null,
    $filterFromDate !== '' ? $filterFromDate : null,
    $filterToDate !== '' ? $filterToDate : null
);

$sellTransactions = get_transactions(
    $pdo,
    $userId,
    'SELL',
    $filterAssetId > 0 ? $filterAssetId : null,
    $filterFromDate !== '' ? $filterFromDate : null,
    $filterToDate !== '' ? $filterToDate : null
);

$pieLabels = [];
$pieValues = [];
foreach ($items as $item) {
    $pieLabels[] = $item['symbol'];
    $pieValues[] = round(to_float($item['allocation_percentage']), 2);
}

render_header('Dashboard');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Portfolio Dashboard</h1>
    <span class="text-muted">User: <?= e((string) ($_SESSION['display_name'] ?? '')) ?></span>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Add Transaction (BUY/DCA or SELL)</h2>
        <form class="row g-3" method="post">
            <input type="hidden" name="action" value="add_transaction">
            <div class="col-md-3">
                <label class="form-label">Asset</label>
                <select class="form-select" name="asset_id" required>
                    <option value="">Choose...</option>
                    <?php foreach ($assets as $asset): ?>
                        <option value="<?= (int) $asset['id'] ?>"><?= e($asset['symbol']) ?> - <?= e($asset['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select class="form-select" name="type" required>
                    <option value="BUY">BUY / DCA</option>
                    <option value="SELL">SELL</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Quantity</label>
                <input class="form-control" type="number" min="0" step="0.00000001" name="quantity" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Unit Price (USD)</label>
                <input class="form-control" type="number" min="0" step="0.00000001" name="unit_price" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input class="form-control" type="datetime-local" name="tx_date">
            </div>
            <div class="col-md-9">
                <label class="form-label">Reason (optional for SELL)</label>
                <input class="form-control" type="text" name="reason" maxlength="255" placeholder="e.g., Profit taking">
            </div>
            <div class="col-md-3 d-grid">
                <label class="form-label d-none d-md-block">&nbsp;</label>
                <button class="btn btn-primary" type="submit">Save Transaction</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-muted">Total Amount Spent on Purchases</h2>
                <p class="h4 mb-0">$<?= number_format($transactionTotals['total_buy'], 2) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-muted">Total Revenue from Sales</h2>
                <p class="h4 mb-0">$<?= number_format($transactionTotals['total_sell'], 2) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-muted">Current Total Portfolio Value</h2>
                <p class="h4 mb-0">$<?= number_format($totals['current_value'], 2) ?></p>
                <small class="<?= $totals['profit_loss'] >= 0 ? 'text-success' : 'text-danger' ?>">
                    Net Unrealized P/L: $<?= number_format($totals['profit_loss'], 2) ?>
                </small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Asset Summary</h2>
        <div class="table-responsive">
            <table class="table table-striped table-sm align-middle">
                <thead>
                <tr>
                    <th>Asset</th>
                    <th>Category</th>
                    <th>Allocation %</th>
                    <th>Quantity</th>
                    <th>Total Invested</th>
                    <th>Latest Price</th>
                    <th>Current Value</th>
                    <th>P/L</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="8" class="text-center text-muted">No portfolio assets yet. Add entries in Portfolio page.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['name']) ?> (<?= e($item['symbol']) ?>)</td>
                            <td><?= e($item['category']) ?></td>
                            <td><?= number_format((float) $item['allocation_percentage'], 2) ?>%</td>
                            <td><?= number_format((float) $item['quantity_held'], (int) $item['decimals']) ?></td>
                            <td>$<?= number_format((float) $item['invested_total'], 2) ?></td>
                            <td>$<?= number_format((float) $item['latest_price'], 6) ?></td>
                            <td>$<?= number_format((float) $item['current_value'], 2) ?></td>
                            <td class="<?= ((float) $item['profit_loss']) >= 0 ? 'text-success' : 'text-danger' ?>">
                                $<?= number_format((float) $item['profit_loss'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Transaction Filters</h2>
        <form class="row g-3" method="get">
            <div class="col-md-4">
                <label class="form-label">Asset</label>
                <select class="form-select" name="filter_asset_id">
                    <option value="0">All assets</option>
                    <?php foreach ($assets as $asset): ?>
                        <option value="<?= (int) $asset['id'] ?>" <?= $filterAssetId === (int) $asset['id'] ? 'selected' : '' ?>>
                            <?= e($asset['symbol']) ?> - <?= e($asset['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">From date</label>
                <input class="form-control" type="date" name="from_date" value="<?= e($filterFromDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To date</label>
                <input class="form-control" type="date" name="to_date" value="<?= e($filterToDate) ?>">
            </div>
            <div class="col-md-2 d-grid">
                <label class="form-label d-none d-md-block">&nbsp;</label>
                <button class="btn btn-outline-primary" type="submit">Apply</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">BUY Transactions</h2>
                <div class="table-responsive">
                    <table class="table table-striped table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Date</th>
                            <th>Total Cost</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$buyTransactions): ?>
                            <tr><td colspan="5" class="text-center text-muted">No BUY transactions found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($buyTransactions as $tx): ?>
                                <tr>
                                    <td><?= e($tx['symbol']) ?> - <?= e($tx['name']) ?></td>
                                    <td><?= number_format((float) $tx['quantity'], 8) ?></td>
                                    <td>$<?= number_format((float) $tx['unit_price'], 8) ?></td>
                                    <td><?= e((string) $tx['date']) ?></td>
                                    <td>$<?= number_format(((float) $tx['quantity']) * ((float) $tx['unit_price']), 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">SELL Transactions</h2>
                <div class="table-responsive">
                    <table class="table table-striped table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Date</th>
                            <th>Reason</th>
                            <th>Total Revenue</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$sellTransactions): ?>
                            <tr><td colspan="6" class="text-center text-muted">No SELL transactions found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($sellTransactions as $tx): ?>
                                <tr>
                                    <td><?= e($tx['symbol']) ?> - <?= e($tx['name']) ?></td>
                                    <td><?= number_format((float) $tx['quantity'], 8) ?></td>
                                    <td>$<?= number_format((float) $tx['unit_price'], 8) ?></td>
                                    <td><?= e((string) $tx['date']) ?></td>
                                    <td><?= e((string) ($tx['reason'] ?? '-')) ?></td>
                                    <td>$<?= number_format(((float) $tx['quantity']) * ((float) $tx['unit_price']), 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Allocation Pie Chart</h2>
                <canvas id="allocationChart" height="240"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">4-Year DCA Growth Projection</h2>
                <canvas id="dcaProjectionChart" height="240"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/chart.min.js"></script>
<script>
const pieLabels = <?= json_encode($pieLabels) ?>;
const pieValues = <?= json_encode($pieValues) ?>;
const projectionLabels = <?= json_encode($projection['labels']) ?>;
const projectionValues = <?= json_encode($projection['values']) ?>;

const allocationCtx = document.getElementById('allocationChart');
if (allocationCtx) {
    new Chart(allocationCtx, {
        type: 'pie',
        data: {
            labels: pieLabels,
            datasets: [{
                label: 'Allocation %',
                data: pieValues,
                backgroundColor: [
                    '#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#20c997', '#fd7e14'
                ]
            }]
        }
    });
}

const projectionCtx = document.getElementById('dcaProjectionChart');
if (projectionCtx) {
    new Chart(projectionCtx, {
        type: 'line',
        data: {
            labels: projectionLabels,
            datasets: [{
                label: 'Projected Invested Capital',
                data: projectionValues,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.2)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}
</script>
<?php render_footer(); ?>
