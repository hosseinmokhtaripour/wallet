<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assetId = (int) ($_POST['asset_id'] ?? 0);
    $price = to_float($_POST['price'] ?? 0);
    $recordedAt = trim((string) ($_POST['recorded_at'] ?? ''));

    if ($assetId <= 0) {
        $errors[] = 'Please select an asset.';
    }
    if ($price <= 0) {
        $errors[] = 'Price must be greater than 0.';
    }
    if ($recordedAt === '') {
        $recordedAt = date('Y-m-d H:i:s');
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO price_history (asset_id, price, recorded_at) VALUES (:asset_id, :price, :recorded_at)'
        );
        $stmt->execute([
            'asset_id' => $assetId,
            'price' => $price,
            'recorded_at' => $recordedAt,
        ]);
        set_flash('success', 'Price updated successfully.');
        header('Location: update_prices.php');
        exit;
    }
}

$assets = $pdo->query('SELECT id, name, symbol, category FROM assets ORDER BY category, symbol')->fetchAll();

$latestPrices = $pdo->query(
    'SELECT a.name, a.symbol, a.category, ph.price, ph.recorded_at
     FROM assets a
     LEFT JOIN price_history ph ON ph.id = (
         SELECT id FROM price_history px WHERE px.asset_id = a.id ORDER BY px.recorded_at DESC, px.id DESC LIMIT 1
     )
     ORDER BY a.category, a.symbol'
)->fetchAll();

render_header('Update Prices');
?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h5">Update Asset Price</h1>
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Asset</label>
                        <select class="form-select" name="asset_id" required>
                            <option value="">Choose...</option>
                            <?php foreach ($assets as $asset): ?>
                                <option value="<?= (int) $asset['id'] ?>"><?= e($asset['symbol']) ?> - <?= e($asset['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (USD)</label>
                        <input class="form-control" type="number" min="0" step="0.00000001" name="price" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Recorded At</label>
                        <input class="form-control" type="datetime-local" name="recorded_at">
                        <small class="text-muted">Optional. Defaults to current timestamp.</small>
                    </div>
                    <button class="btn btn-primary" type="submit">Save Price</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Latest Prices</h2>
                <div class="table-responsive">
                    <table class="table table-striped table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Category</th>
                            <th>Latest Price</th>
                            <th>Recorded At</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($latestPrices as $row): ?>
                            <tr>
                                <td><?= e($row['symbol']) ?> - <?= e($row['name']) ?></td>
                                <td><?= e($row['category']) ?></td>
                                <td><?= $row['price'] !== null ? '$' . number_format((float) $row['price'], 8) : '-' ?></td>
                                <td><?= e((string) ($row['recorded_at'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
