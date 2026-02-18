<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_login();

$userId = current_user_id();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_portfolio') {
        $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
        $assetId = (int) ($_POST['asset_id'] ?? 0);
        $allocation = to_float($_POST['allocation_percentage'] ?? 0);
        $initial = to_float($_POST['initial_investment'] ?? 0);
        $dca = to_float($_POST['dca_per_month'] ?? 0);

        if ($assetId <= 0) {
            $errors[] = 'Please choose an asset.';
        }
        if ($allocation < 0 || $allocation > 100) {
            $errors[] = 'Allocation must be between 0 and 100.';
        }
        if ($initial < 0 || $dca < 0) {
            $errors[] = 'Initial investment and DCA must be non-negative.';
        }

        if (!$errors) {
            if ($portfolioId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE portfolios SET asset_id=:asset_id, allocation_percentage=:allocation, initial_investment=:initial, dca_per_month=:dca
                     WHERE id=:id AND user_id=:user_id'
                );
                $stmt->execute([
                    'asset_id' => $assetId,
                    'allocation' => $allocation,
                    'initial' => $initial,
                    'dca' => $dca,
                    'id' => $portfolioId,
                    'user_id' => $userId,
                ]);
                set_flash('success', 'Portfolio entry updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO portfolios (user_id, asset_id, allocation_percentage, initial_investment, dca_per_month)
                     VALUES (:user_id, :asset_id, :allocation, :initial, :dca)'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'asset_id' => $assetId,
                    'allocation' => $allocation,
                    'initial' => $initial,
                    'dca' => $dca,
                ]);
                set_flash('success', 'Portfolio entry added.');
            }
            header('Location: portfolio.php');
            exit;
        }
    }

    if ($action === 'delete_portfolio') {
        $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
        if ($portfolioId > 0) {
            $stmt = $pdo->prepare('DELETE FROM portfolios WHERE id = :id AND user_id = :user_id');
            $stmt->execute(['id' => $portfolioId, 'user_id' => $userId]);
            set_flash('success', 'Portfolio entry deleted.');
            header('Location: portfolio.php');
            exit;
        }
    }

    if ($action === 'create_asset') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $symbol = strtoupper(trim((string) ($_POST['symbol'] ?? '')));
        $category = strtoupper(trim((string) ($_POST['category'] ?? 'CRYPTO')));
        $decimals = (int) ($_POST['decimals'] ?? 8);

        if ($name === '' || $symbol === '') {
            $errors[] = 'Asset name and symbol are required.';
        }

        if (!in_array($category, ['CRYPTO', 'GOLD', 'FIAT'], true)) {
            $errors[] = 'Invalid category.';
        }

        if ($decimals < 0 || $decimals > 18) {
            $errors[] = 'Decimals must be between 0 and 18.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO assets (name, symbol, category, decimals) VALUES (:name, :symbol, :category, :decimals)');
            try {
                $stmt->execute([
                    'name' => $name,
                    'symbol' => $symbol,
                    'category' => $category,
                    'decimals' => $decimals,
                ]);
                set_flash('success', 'Asset created.');
                header('Location: portfolio.php');
                exit;
            } catch (PDOException $exception) {
                $errors[] = 'Asset symbol already exists.';
            }
        }
    }
}

$assets = $pdo->query('SELECT id, name, symbol, category FROM assets ORDER BY category, symbol')->fetchAll();

$listStmt = $pdo->prepare(
    'SELECT p.id, p.asset_id, p.allocation_percentage, p.initial_investment, p.dca_per_month, a.name, a.symbol, a.category
     FROM portfolios p
     INNER JOIN assets a ON a.id = p.asset_id
     WHERE p.user_id = :user_id
     ORDER BY a.category, a.symbol'
);
$listStmt->execute(['user_id' => $userId]);
$portfolioRows = $listStmt->fetchAll();

$editId = (int) ($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    foreach ($portfolioRows as $row) {
        if ((int) $row['id'] === $editId) {
            $editRow = $row;
            break;
        }
    }
}

render_header('Portfolio Management');
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h5"><?= $editRow ? 'Edit Portfolio Asset' : 'Add Portfolio Asset' ?></h1>
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
                    <input type="hidden" name="action" value="save_portfolio">
                    <input type="hidden" name="portfolio_id" value="<?= (int) ($editRow['id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label">Asset</label>
                        <select class="form-select" name="asset_id" required>
                            <option value="">Choose asset...</option>
                            <?php foreach ($assets as $asset): ?>
                                <option value="<?= (int) $asset['id'] ?>" <?= ((int) ($editRow['asset_id'] ?? 0) === (int) $asset['id']) ? 'selected' : '' ?>>
                                    <?= e($asset['symbol']) ?> - <?= e($asset['name']) ?> (<?= e($asset['category']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Allocation %</label>
                        <input class="form-control" type="number" step="0.01" min="0" max="100" name="allocation_percentage" required
                               value="<?= e((string) ($editRow['allocation_percentage'] ?? '0')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Initial Investment (USD)</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="initial_investment" required
                               value="<?= e((string) ($editRow['initial_investment'] ?? '0')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">DCA per Month (USD)</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="dca_per_month" required
                               value="<?= e((string) ($editRow['dca_per_month'] ?? '0')) ?>">
                    </div>
                    <button class="btn btn-primary" type="submit"><?= $editRow ? 'Update' : 'Add' ?> Portfolio Asset</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Create New Asset</h2>
                <form method="post">
                    <input type="hidden" name="action" value="create_asset">
                    <div class="mb-3">
                        <label class="form-label">Asset Name</label>
                        <input class="form-control" type="text" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Symbol</label>
                        <input class="form-control" type="text" name="symbol" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category">
                            <option value="CRYPTO">CRYPTO</option>
                            <option value="GOLD">GOLD</option>
                            <option value="FIAT">FIAT</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Decimals</label>
                        <input class="form-control" type="number" min="0" max="18" name="decimals" value="8">
                    </div>
                    <button class="btn btn-success" type="submit">Create Asset</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-body">
        <h2 class="h5">My Portfolio Assets</h2>
        <div class="table-responsive">
            <table class="table table-striped table-sm align-middle">
                <thead>
                <tr>
                    <th>Asset</th>
                    <th>Category</th>
                    <th>Allocation %</th>
                    <th>Initial Investment</th>
                    <th>DCA / Month</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$portfolioRows): ?>
                    <tr><td colspan="6" class="text-center text-muted">No entries yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($portfolioRows as $row): ?>
                        <tr>
                            <td><?= e($row['symbol']) ?> - <?= e($row['name']) ?></td>
                            <td><?= e($row['category']) ?></td>
                            <td><?= number_format((float) $row['allocation_percentage'], 2) ?>%</td>
                            <td>$<?= number_format((float) $row['initial_investment'], 2) ?></td>
                            <td>$<?= number_format((float) $row['dca_per_month'], 2) ?></td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" href="portfolio.php?edit=<?= (int) $row['id'] ?>">Edit</a>
                                <form class="d-inline" method="post" onsubmit="return confirm('Delete this portfolio entry?');">
                                    <input type="hidden" name="action" value="delete_portfolio">
                                    <input type="hidden" name="portfolio_id" value="<?= (int) $row['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php render_footer(); ?>
