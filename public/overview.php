<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    header('Location: login.php');
    exit;
}

$user = currentUser();

// Fetch all cars for this user
$stmt = $pdo->prepare('SELECT * FROM cars WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$cars = $stmt->fetchAll();

// Fetch standalone brand entries so filter options include newly created brands.
$brands = [];
try {
    $brandStmt = $pdo->prepare('SELECT name FROM brands WHERE user_id = ? ORDER BY name ASC');
    $brandStmt->execute([$user['id']]);
    $brands = array_values(array_filter(array_map(static fn($row) => trim((string)($row['name'] ?? '')), $brandStmt->fetchAll(PDO::FETCH_ASSOC))));
} catch (Throwable $e) {
    // Keep overview usable even if brands table is not available yet.
    $brands = [];
}

// Helper stats for initial render
$distinctBrands = count(array_unique(array_map(fn($c) => $c['brand'], $cars)));
$activeCars = array_values(array_filter($cars, static function ($car) {
    $status = strtolower(trim((string)($car['car_status'] ?? 'owned')));
    return $status !== 'sold';
}));
$collectionSize = count($activeCars);
$preorderedCount = count(array_values(array_filter($cars, static function ($car) {
    return strtolower(trim((string)($car['car_status'] ?? 'owned'))) === 'preordered';
})));
$soldCount = count(array_values(array_filter($cars, static function ($car) {
    return strtolower(trim((string)($car['car_status'] ?? 'owned'))) === 'sold';
})));
$totalEstimated = array_reduce($activeCars, fn($sum, $car) => $sum + (float)($car['estimated_value'] ?? 0), 0);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overview - Collection Checker</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="stylesheet" href="../assets/css/styles.css?v=20260313p">
</head>

<body>
    <div class="app">
        <aside class="app__sidebar">
            <img src="../assets/images/logo.png" class="sidebar-logo" alt="Collection Checker">
            <a href="dashboard.php" class="nav-button">Dashboard</a>
            <a href="overview.php" class="nav-button">Collection Overview</a>
            <a href="javascript:history.back()" class="nav-button">Back</a>
            <a href="lists.php" class="nav-button">Lists</a>
            <a href="#" class="nav-button logout" id="logoutBtn">Logout</a>
            <a href="settings.php" class="settings-button" aria-label="Settings">⚙</a>
        </aside>

        <main class="app__main">
            <div class="overview-toolbar">
                <div class="overview-search">
                    <input id="search-input" type="search" placeholder="Search “brand” “model” “size” “value”" autocomplete="off">
                </div>
                <div class="overview-actions">
                    <a href="add-car.php" class="overview-action-btn">Add Vehicle</a>
                    <a href="add-brand.php" class="overview-action-btn">Add Brand</a>
                </div>
            </div>

            <div class="overview-row">
                <div class="overview-filters">
                    <div class="overview-filters__primary">
                        <select id="filter-brand">
                            <option value="">Brand</option>
                        </select>

                        <select id="filter-scale">
                            <option value="">Scale</option>
                        </select>

                        <select id="filter-car-brand">
                            <option value="">Car Brand</option>
                        </select>
                    </div>

                    <details class="overview-filters__advanced">
                        <summary>More Filters</summary>
                        <div class="overview-filters__advanced-grid">
                            <select id="filter-status">
                                <option value="">Status</option>
                                <option value="owned">Owned</option>
                                <option value="preordered">Preordered</option>
                                <option value="sold">Sold</option>
                            </select>

                            <select id="filter-condition">
                                <option value="">Condition</option>
                            </select>

                            <select id="filter-added-sort">
                                <option value="created_at:group_desc">Added Date</option>
                                <option value="created_at:group_desc">Newest Added</option>
                                <option value="created_at:group_asc">Oldest Added</option>
                            </select>

                            <select id="filter-price-sort">
                                <option value="">Price</option>
                                <option value="bought_price:desc">Purchase Price (high-low)</option>
                                <option value="bought_price:asc">Purchase Price (low-high)</option>
                                <option value="estimated_value:desc">Estimated Value (high-low)</option>
                                <option value="estimated_value:asc">Estimated Value (low-high)</option>
                            </select>
                        </div>
                    </details>
                </div>

                <div class="overview-stats-text">
                    <p>Amount of Brands: <span id="stat-brands"><?php echo $distinctBrands; ?></span></p>
                    <p>Collection size: <span id="stat-size"><?php echo $collectionSize; ?></span></p>
                    <p>Estimated Value: <span id="stat-value"><?php echo formatPrice($totalEstimated); ?></span></p>
                    <p>Preordered: <span id="stat-preordered"><?php echo $preorderedCount; ?></span></p>
                    <p>Sold: <span id="stat-sold"><?php echo $soldCount; ?></span></p>
                </div>
            </div>

            <div id="no-results-message" class="no-results"></div>

            <div id="cards-container" class="cards-grid"></div>
        </main>
    </div>

    <div id="brand-success-popup" class="custom-popup" role="alert" aria-live="polite">
        <div class="custom-popup__panel">
            <h3>Brand Created</h3>
            <p id="brand-success-popup-message">Your brand was created successfully.</p>
            <button type="button" id="brand-success-popup-close" class="custom-popup__close">Close</button>
        </div>
    </div>

    <div id="welcome-popup" class="custom-popup" role="alert" aria-live="polite">
        <div class="custom-popup__panel">
            <h3>Welcome to Collection Checker</h3>
            <p>Start by adding a brand with the <strong>Add Brand</strong> button, then add your first vehicle with <strong>Add Vehicle</strong>.</p>
            <button type="button" id="welcome-popup-close" class="custom-popup__close">Let&apos;s Start</button>
        </div>
    </div>

    <script>
        window.COLLATION_CARS = <?php echo json_encode($cars, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        window.COLLATION_BRANDS = <?php echo json_encode($brands, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
    <script src="../assets/js/overview.js?v=20260313m"></script>
    <script>
        (function () {
            const params = new URLSearchParams(window.location.search);
            if (params.get('brand_created') !== '1') {
                return;
            }

            const popup = document.getElementById('brand-success-popup');
            const message = document.getElementById('brand-success-popup-message');
            const closeButton = document.getElementById('brand-success-popup-close');
            const brandName = (params.get('brand_name') || '').trim();

            if (!popup || !message || !closeButton) {
                return;
            }

            message.textContent = brandName
                ? 'Brand "' + brandName + '" was created successfully.'
                : 'Your brand was created successfully.';

            const closePopup = function () {
                popup.classList.remove('is-visible');
                const url = new URL(window.location.href);
                url.searchParams.delete('brand_created');
                url.searchParams.delete('brand_name');
                window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
            };

            closeButton.addEventListener('click', closePopup);
            popup.addEventListener('click', function (event) {
                if (event.target === popup) {
                    closePopup();
                }
            });

            popup.classList.add('is-visible');
        })();

        (function () {
            const params = new URLSearchParams(window.location.search);
            const hasBrandCreatedPopup = params.get('brand_created') === '1';
            const cars = Array.isArray(window.COLLATION_CARS) ? window.COLLATION_CARS : [];
            if (hasBrandCreatedPopup || cars.length > 0) {
                return;
            }

            const popup = document.getElementById('welcome-popup');
            const closeButton = document.getElementById('welcome-popup-close');
            if (!popup || !closeButton) {
                return;
            }

            const closePopup = function () {
                popup.classList.remove('is-visible');
            };

            closeButton.addEventListener('click', closePopup);
            popup.addEventListener('click', function (event) {
                if (event.target === popup) {
                    closePopup();
                }
            });

            popup.classList.add('is-visible');
        })();

        document.getElementById('logoutBtn').addEventListener('click', function () {
            fetch('../controllers/auth-control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'logout' })
            }).then(() => {
                window.location.href = 'login.php';
            });
        });
    </script>
</body>

</html>
