<?php
declare(strict_types=1);

require_once __DIR__ . '/calculate.php';

$userId = current_user_id();
$summary = calculate_portfolio_summary($pdo, $userId);
$projection = calculate_dca_projection($pdo, $userId);

$items = $summary['items'];
$totals = $summary['totals'];

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

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-muted">Total Initial Investment</h2>
                <p class="h4 mb-0">$<?= number_format($totals['initial_investment'], 2) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-muted">Total Invested (incl. 4Y DCA)</h2>
                <p class="h4 mb-0">$<?= number_format($totals['invested_total'], 2) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-muted">Current Portfolio Value</h2>
                <p class="h4 mb-0">$<?= number_format($totals['current_value'], 2) ?></p>
                <small class="<?= $totals['profit_loss'] >= 0 ? 'text-success' : 'text-danger' ?>">
                    P/L: $<?= number_format($totals['profit_loss'], 2) ?>
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
                    <th>Initial Investment</th>
                    <th>DCA / Month</th>
                    <th>DCA 4Y Total</th>
                    <th>Latest Price</th>
                    <th>Current Value</th>
                    <th>P/L</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="9" class="text-center text-muted">No portfolio assets yet. Add entries in Portfolio page.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['name']) ?> (<?= e($item['symbol']) ?>)</td>
                            <td><?= e($item['category']) ?></td>
                            <td><?= number_format((float) $item['allocation_percentage'], 2) ?>%</td>
                            <td>$<?= number_format((float) $item['initial_investment'], 2) ?></td>
                            <td>$<?= number_format((float) $item['dca_per_month'], 2) ?></td>
                            <td>$<?= number_format((float) $item['dca_4y_total'], 2) ?></td>
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
