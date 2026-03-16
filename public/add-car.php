<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$user = currentUser();
$brandSet = [];

if (isset($pdo) && $pdo instanceof PDO && $user) {
    $stmt = $pdo->prepare('SELECT name FROM brands WHERE user_id = ? ORDER BY name ASC');
    $stmt->execute([$user['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $value = trim((string)$name);
        if ($value !== '') {
            $brandSet[strtolower($value)] = $value;
        }
    }

    $stmt = $pdo->prepare('SELECT DISTINCT brand FROM cars WHERE user_id = ? ORDER BY brand ASC');
    $stmt->execute([$user['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $value = trim((string)$name);
        if ($value !== '') {
            $brandSet[strtolower($value)] = $value;
        }
    }
}

$brandOptions = array_values($brandSet);
natcasesort($brandOptions);
$brandOptions = array_values($brandOptions);
if (!in_array('Real Car', $brandOptions, true)) {
    array_unshift($brandOptions, 'Real Car');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Car - Collection Checker</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="stylesheet" href="../assets/css/styles.css?v=20260313o">
</head>

<body>
    <div class="app add-vehicle-app">
        <aside class="app__sidebar">
            <img src="../assets/images/logo.png" class="sidebar-logo" alt="Collection Checker">
            <a href="dashboard.php" class="nav-button">Dashboard</a>
            <a href="overview.php" class="nav-button">Collection Overview</a>
            <a href="javascript:history.back()" class="nav-button">Back</a>
            <a href="#" class="nav-button logout" id="logoutBtn">Logout</a>
            <a href="settings.php" class="settings-button" aria-label="Settings">⚙</a>
        </aside>

        <main class="app__main add-vehicle-main">
            <section class="add-vehicle-card">
                <form method="POST" action="../controllers/car-control.php" id="add-car-form" class="add-vehicle-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <div id="form-error" class="form-error"></div>

                    <label class="add-vehicle-upload" for="car-image">
                        <input type="file" id="car-image" name="car_image" accept="image/*">
                        <img id="car-image-preview" class="car-image-preview hidden" src="" alt="Car image preview">
                        <span class="upload-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <rect x="2.5" y="5.5" width="14" height="14" rx="1.6"></rect>
                                <path d="M5.5 15.5 9 11l2.3 3 2.2-2.5 3 4"></path>
                                <path d="M18 3.5v5"></path>
                                <path d="M15.5 6h5"></path>
                            </svg>
                        </span>
                        <span class="upload-text">Add Image</span>
                    </label>

                    <div class="add-vehicle-grid add-vehicle-grid--top">
                        <div class="add-field">
                            <label for="brand">Collection Brand</label>
                            <select id="brand" name="brand" required>
                                <option value="">+</option>
                                <?php foreach ($brandOptions as $option): ?>
                                    <option value="<?php echo e($option); ?>"><?php echo e($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="brand-hint">Missing a brand? Add one on the <a href="add-brand.php">Add Brand</a> page.</p>
                        </div>

                        <div class="add-field">
                            <label for="car_brand">Car Brand</label>
                            <input type="text" id="car_brand" name="car_brand" placeholder="Add Car Brand" required>
                        </div>

                        <div class="add-field">
                            <label for="model">Model</label>
                            <input type="text" id="model" name="model" placeholder="Add Model" required>
                        </div>

                        <div class="add-field add-field--details">
                            <label for="extra_items">Extra Items (Optional)</label>
                            <textarea id="extra_items" name="extra_items" placeholder="Add extra items, notes, accessories..."></textarea>
                        </div>
                    </div>

                    <div class="add-vehicle-grid add-vehicle-grid--meta">
                        <div class="add-field">
                            <label for="model_year">Model Year (Optional)</label>
                            <input type="number" id="model_year" name="model_year" min="1886" max="2100" step="1" placeholder="Example: 2024">
                        </div>

                        <div class="add-field">
                            <label for="car_condition">Condition</label>
                            <select id="car_condition" name="car_condition">
                                <option value="">Not Set</option>
                                <option value="in case">In Case</option>
                                <option value="out of case">Out of Case</option>
                                <option value="in alt case">In Alt Case</option>
                                <option value="with blister">With Blister</option>
                                <option value="mint">Mint</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>

                        <div class="add-field">
                            <label for="car_status">Status</label>
                            <select id="car_status" name="car_status">
                                <option value="owned" selected>Owned</option>
                                <option value="preordered">Preordered</option>
                                <option value="sold">Sold</option>
                            </select>
                        </div>

                        <div class="add-field add-field--favorite">
                            <label for="is_favorite">Favorite</label>
                            <label class="favorite-toggle" for="is_favorite">
                                <input type="checkbox" id="is_favorite" name="is_favorite" value="1">
                                <span>Mark as favorite</span>
                            </label>
                        </div>
                    </div>

                    <div class="add-vehicle-grid add-vehicle-grid--bottom">
                        <div class="add-field add-field--scale">
                            <label for="scale-1">Scale</label>
                            <input type="hidden" id="scale" name="scale" required>
                            <div class="scale-picker" id="scale-picker">
                                <select id="scale-1" class="scale-slot" required>
                                    <option value="">+</option>
                                    <option value="1:1">1:1</option>
                                    <option value="1:8">1:8</option>
                                    <option value="1:10">1:10</option>
                                    <option value="1:12">1:12</option>
                                    <option value="1:14">1:14</option>
                                    <option value="1:16">1:16</option>
                                    <option value="1:18">1:18</option>
                                    <option value="1:24">1:24</option>
                                    <option value="1:32">1:32</option>
                                    <option value="1:43">1:43</option>
                                    <option value="1:64">1:64</option>
                                </select>
                                <select id="scale-2" class="scale-slot hidden" disabled>
                                    <option value="">+</option>
                                    <option value="1:1">1:1</option>
                                    <option value="1:8">1:8</option>
                                    <option value="1:10">1:10</option>
                                    <option value="1:12">1:12</option>
                                    <option value="1:14">1:14</option>
                                    <option value="1:16">1:16</option>
                                    <option value="1:18">1:18</option>
                                    <option value="1:24">1:24</option>
                                    <option value="1:32">1:32</option>
                                    <option value="1:43">1:43</option>
                                    <option value="1:64">1:64</option>
                                </select>
                            </div>
                        </div>

                        <div class="add-field add-field--price">
                            <label for="bought_price">Purchase Price</label>
                            <div class="input-prefix-wrap">
                                <span>€</span>
                                <input type="number" id="bought_price" name="bought_price" min="0" step="0.01" placeholder="Add Price" value="">
                            </div>
                        </div>

                        <div class="add-field add-field--price">
                            <label for="estimated_value">Estimated Value</label>
                            <div class="input-prefix-wrap">
                                <span>€</span>
                                <input type="number" id="estimated_value" name="estimated_value" min="0" step="0.01" placeholder="Add Price" value="">
                            </div>
                        </div>

                        <div class="add-vehicle-actions">
                            <a href="overview.php" class="add-btn add-btn--cancel">Cancel</a>
                            <button type="submit" class="add-btn add-btn--save">Save</button>
                        </div>
                    </div>
                </form>
            </section>
        </main>
    </div>

    <script>
        (function() {
            const scale1 = document.getElementById('scale-1');
            const scale2 = document.getElementById('scale-2');
            const hiddenScale = document.getElementById('scale');
            const brand = document.getElementById('brand');
            let syncing = false;

            function denominator(scaleValue) {
                const parts = String(scaleValue).split(':');
                return Number(parts[1] || 0);
            }

            function refreshScaleState() {
                if (syncing) {
                    return;
                }

                syncing = true;
                let first = (scale1.value || '').trim();
                let second = (scale2.value || '').trim();
                const isRealCarBrand = (brand.value || '').trim().toLowerCase() === 'real car';

                if (isRealCarBrand) {
                    scale1.value = '1:1';
                    first = '1:1';
                    scale2.value = '';
                    second = '';
                    scale1.disabled = true;
                    scale2.disabled = true;
                    scale2.classList.add('hidden');
                    hiddenScale.value = '1:1';
                    syncing = false;
                    return;
                }

                scale1.disabled = false;

                const activeFirst = (scale1.value || '').trim();

                if (activeFirst && activeFirst !== '1:1') {
                    scale2.disabled = false;
                    scale2.classList.remove('hidden');
                } else {
                    scale2.value = '';
                    scale2.disabled = true;
                    scale2.classList.add('hidden');
                }

                Array.from(scale2.options).forEach(opt => {
                    if (!opt.value) return;
                    opt.disabled = Boolean(activeFirst && opt.value === activeFirst);
                });

                if (second && activeFirst && second === activeFirst) {
                    scale2.value = '';
                }

                const selected = [];
                if (activeFirst) selected.push(activeFirst);
                if (scale2.value) selected.push(scale2.value);

                selected.sort((a, b) => denominator(b) - denominator(a));
                hiddenScale.value = selected.join('|');

                brand.required = true;

                syncing = false;
            }

            brand.addEventListener('change', function() {
                if ((brand.value || '') !== 'Real Car') {
                    refreshScaleState();
                    return;
                }

                scale1.value = '1:1';
                scale2.value = '';
                scale2.disabled = true;
                scale2.classList.add('hidden');
                refreshScaleState();
            });

            scale1.addEventListener('change', function() {
                if ((scale1.value || '').trim() === '1:1') {
                    brand.value = 'Real Car';
                }
                refreshScaleState();
            });

            scale2.addEventListener('change', function() {
                if ((scale2.value || '').trim() === '1:1') {
                    brand.value = 'Real Car';
                }
                refreshScaleState();
            });
            refreshScaleState();
        })();

        document.getElementById('add-car-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = this;
            const endpoint = form.getAttribute('action') || window.location.href;
            const error = document.getElementById('form-error');
            error.textContent = '';
            error.classList.remove('visible');

            const body = new FormData(form);

            try {
                const res = await fetch(endpoint, { method: 'POST', body });
                const data = await res.json();

                if (data.success) {
                    window.location.href = data.redirect || 'overview.php';
                    return;
                }

                error.textContent = data.message || 'Something went wrong.';
                error.classList.add('visible');
            } catch (err) {
                error.textContent = 'Could not save vehicle. Try again.';
                error.classList.add('visible');
            }
        });

        (function() {
            const imageInput = document.getElementById('car-image');
            const imagePreview = document.getElementById('car-image-preview');
            const uploadFrame = document.querySelector('.add-vehicle-upload');

            imageInput.addEventListener('change', () => {
                const file = imageInput.files && imageInput.files[0];
                if (!file) {
                    imagePreview.src = '';
                    imagePreview.classList.add('hidden');
                    uploadFrame.classList.remove('has-image');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(evt) {
                    imagePreview.src = evt.target.result;
                    imagePreview.classList.remove('hidden');
                    uploadFrame.classList.add('has-image');
                };
                reader.readAsDataURL(file);
            });
        })();

        document.getElementById('logoutBtn').addEventListener('click', function() {
            fetch('../controllers/auth-control.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'logout'
                })
            }).then(() => {
                window.location.href = 'login.php';
            });
        });
    </script>
</body>

</html>
