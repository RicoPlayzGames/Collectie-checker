<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

if (!isset($pdo) || !($pdo instanceof PDO)) {
	header('Location: overview.php');
	exit;
}

$user = currentUser();
$carId = (int)($_GET['id'] ?? 0);

if ($carId <= 0) {
	header('Location: overview.php');
	exit;
}

$stmt = $pdo->prepare('SELECT * FROM cars WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$carId, $user['id']]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$car) {
	header('Location: overview.php');
	exit;
}

function imageUrl(?string $relative): ?string
{
	if (!$relative) {
		return null;
	}

	$parts = explode('/', str_replace('\\', '/', $relative));
	$encoded = array_map('rawurlencode', $parts);
	return '../' . implode('/', $encoded);
}

$carImageUrl = imageUrl($car['image_path'] ?? null);
$scaleOptions = ['1:1', '1:8', '1:10', '1:12', '1:14', '1:16', '1:18', '1:24', '1:32', '1:43', '1:64'];
$selectedScales = array_values(array_filter(array_map('trim', preg_split('/[|,\/]+/', (string)$car['scale']) ?: [])));
if (count($selectedScales) === 0) {
	$selectedScales = [''];
}
foreach ($selectedScales as $value) {
	if ($value && !in_array($value, $scaleOptions, true)) {
		$scaleOptions[] = $value;
	}
}
$firstScale = $selectedScales[0] ?? '';
$secondScale = $selectedScales[1] ?? '';
$modelYearValue = isset($car['model_year']) ? (string)$car['model_year'] : '';
$conditionValue = strtolower(trim((string)($car['car_condition'] ?? '')));
$statusValue = strtolower(trim((string)($car['car_status'] ?? 'owned')));
if (!in_array($statusValue, ['owned', 'preordered', 'sold'], true)) {
	$statusValue = 'owned';
}
$favoriteValue = !empty($car['is_favorite']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Car Details - Collection Checker</title>
	<link rel="icon" type="image/png" href="../assets/images/logo.png">
	<link rel="stylesheet" href="../assets/css/styles.css?v=20260313m">
</head>

<body>
	<div class="app add-vehicle-app car-details-page">
		<aside class="app__sidebar">
			<img src="../assets/images/logo.png" class="sidebar-logo" alt="Collection Checker">
			<a href="dashboard.php" class="nav-button">Dashboard</a>
			<a href="overview.php" class="nav-button">Collection Overview</a>
			<a href="javascript:history.back()" class="nav-button">Back</a>
			<a href="#" class="nav-button logout" id="logoutBtn">Logout</a>
			<a href="settings.php" class="settings-button" aria-label="Settings">⚙</a>
		</aside>

		<main class="app__main add-vehicle-main">
			<section class="add-vehicle-card car-details-card">
				<form method="POST" action="../controllers/car-control.php" id="car-details-form" class="add-vehicle-form" enctype="multipart/form-data">
					<input type="hidden" name="action" value="update">
					<input type="hidden" name="car_id" value="<?php echo (int)$car['id']; ?>">
					<div id="form-error" class="form-error"></div>

					<label class="add-vehicle-upload car-image-upload <?php echo $carImageUrl ? 'has-image' : ''; ?>" for="car-image">
						<input type="file" id="car-image" name="car_image" accept="image/*" disabled>
						<img id="car-image-preview" class="car-image-preview <?php echo $carImageUrl ? '' : 'hidden'; ?>" src="<?php echo e((string)$carImageUrl); ?>" alt="<?php echo e($car['brand'] . ' ' . $car['model']); ?>">
						<span class="upload-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24">
								<rect x="2.5" y="5.5" width="14" height="14" rx="1.6"></rect>
								<path d="M5.5 15.5 9 11l2.3 3 2.2-2.5 3 4"></path>
								<path d="M18 3.5v5"></path>
								<path d="M15.5 6h5"></path>
							</svg>
						</span>
						<span class="upload-text">Add Image</span>
						<span class="upload-hint">Click image to replace while editing</span>
					</label>

					<div class="add-vehicle-grid add-vehicle-grid--top">
						<div class="add-field">
							<label for="brand">Collection Brand</label>
							<input type="text" id="brand" name="brand" value="<?php echo e((string)$car['brand']); ?>" required readonly>
						</div>

						<div class="add-field">
							<label for="car_brand">Car Brand</label>
							<input type="text" id="car_brand" name="car_brand" value="<?php echo e((string)$car['automerk']); ?>" required readonly>
						</div>

						<div class="add-field">
							<label for="model">Model</label>
							<input type="text" id="model" name="model" value="<?php echo e((string)$car['model']); ?>" required readonly>
						</div>

						<div class="add-field add-field--details">
							<label for="extra_items">Extra Items (Optional)</label>
							<textarea id="extra_items" name="extra_items" placeholder="Add extra items, notes, accessories..." readonly><?php echo e((string)($car['details'] ?? '')); ?></textarea>
						</div>
					</div>

					<div class="add-vehicle-grid add-vehicle-grid--meta">
						<div class="add-field">
							<label for="model_year">Model Year (Optional)</label>
							<input type="number" id="model_year" name="model_year" min="1886" max="2100" step="1" value="<?php echo e($modelYearValue); ?>" readonly>
						</div>

						<div class="add-field">
							<label for="car_condition">Condition</label>
							<select id="car_condition" name="car_condition" disabled>
								<option value="" <?php echo ($conditionValue === '') ? 'selected' : ''; ?>>Not Set</option>
								<option value="in case" <?php echo ($conditionValue === 'in case') ? 'selected' : ''; ?>>In Case</option>
								<option value="out of case" <?php echo ($conditionValue === 'out of case') ? 'selected' : ''; ?>>Out of Case</option>
								<option value="in alt case" <?php echo ($conditionValue === 'in alt case') ? 'selected' : ''; ?>>In Alt Case</option>
								<option value="with blister" <?php echo ($conditionValue === 'with blister') ? 'selected' : ''; ?>>With Blister</option>
								<option value="mint" <?php echo ($conditionValue === 'mint') ? 'selected' : ''; ?>>Mint</option>
								<option value="good" <?php echo ($conditionValue === 'good') ? 'selected' : ''; ?>>Good</option>
								<option value="fair" <?php echo ($conditionValue === 'fair') ? 'selected' : ''; ?>>Fair</option>
								<option value="poor" <?php echo ($conditionValue === 'poor') ? 'selected' : ''; ?>>Poor</option>
							</select>
						</div>

						<div class="add-field">
							<label for="car_status">Status</label>
							<select id="car_status" name="car_status" disabled>
								<option value="owned" <?php echo ($statusValue === 'owned') ? 'selected' : ''; ?>>Owned</option>
								<option value="preordered" <?php echo ($statusValue === 'preordered') ? 'selected' : ''; ?>>Preordered</option>
								<option value="sold" <?php echo ($statusValue === 'sold') ? 'selected' : ''; ?>>Sold</option>
							</select>
						</div>

						<div class="add-field add-field--favorite">
							<label for="is_favorite">Favorite</label>
							<label class="favorite-toggle" for="is_favorite">
								<input type="checkbox" id="is_favorite" name="is_favorite" value="1" <?php echo $favoriteValue ? 'checked' : ''; ?> disabled>
								<span>Mark as favorite</span>
							</label>
						</div>
					</div>

					<div class="add-vehicle-grid add-vehicle-grid--bottom">
						<div class="add-field add-field--scale">
							<label for="scale-1">Scale</label>
							<input type="hidden" id="scale" name="scale" value="<?php echo e((string)$car['scale']); ?>" required>
							<div class="scale-picker" id="scale-picker">
								<select id="scale-1" class="scale-slot" required disabled>
									<option value="">+</option>
									<?php foreach ($scaleOptions as $option): ?>
										<option value="<?php echo e($option); ?>" <?php echo ($firstScale === $option) ? 'selected' : ''; ?>><?php echo e($option); ?></option>
									<?php endforeach; ?>
								</select>
								<select id="scale-2" class="scale-slot <?php echo $firstScale ? '' : 'hidden'; ?>" <?php echo $firstScale ? 'disabled' : 'disabled'; ?>>
									<option value="">+</option>
									<?php foreach ($scaleOptions as $option): ?>
										<option value="<?php echo e($option); ?>" <?php echo ($secondScale === $option) ? 'selected' : ''; ?>><?php echo e($option); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<div class="add-field add-field--price">
							<label for="bought_price">Purchase Price</label>
							<div class="input-prefix-wrap">
								<span>€</span>
								<input type="number" id="bought_price" name="bought_price" min="0" step="0.01" value="<?php echo e((string)$car['bought_price']); ?>" required readonly>
							</div>
						</div>

						<div class="add-field add-field--price">
							<label for="estimated_value">Estimated Value</label>
							<div class="input-prefix-wrap">
								<span>€</span>
								<input type="number" id="estimated_value" name="estimated_value" min="0" step="0.01" value="<?php echo e((string)$car['estimated_value']); ?>" required readonly>
							</div>
						</div>

						<div class="add-vehicle-actions car-detail-actions">
							<button type="button" class="add-btn add-btn--cancel" id="deleteBtn">Delete</button>
							<button type="button" class="add-btn add-btn--edit" id="editBtn">Edit</button>
							<button type="submit" class="add-btn add-btn--save" id="saveBtn" disabled>Save</button>
						</div>
					</div>
				</form>
			</section>
		</main>
	</div>

	<script>
		(function() {
			const form = document.getElementById('car-details-form');
			const formError = document.getElementById('form-error');
			const editBtn = document.getElementById('editBtn');
			const saveBtn = document.getElementById('saveBtn');
			const deleteBtn = document.getElementById('deleteBtn');
			const imageInput = document.getElementById('car-image');
			const imagePreview = document.getElementById('car-image-preview');
			const imageUpload = document.querySelector('.car-image-upload');
			const scale1 = document.getElementById('scale-1');
			const scale2 = document.getElementById('scale-2');
			const hiddenScale = document.getElementById('scale');
			const brandInput = document.getElementById('brand');
			const carIdInput = form.querySelector('input[name="car_id"]');
			const initialImageSrc = imagePreview && !imagePreview.classList.contains('hidden') ? imagePreview.getAttribute('src') : '';

			let isEditing = false;

			function denominator(scaleValue) {
				const parts = String(scaleValue).split(':');
				return Number(parts[1] || 0);
			}

			function refreshScaleState() {
				let first = (scale1.value || '').trim();
				let second = (scale2.value || '').trim();
				const isRealCarBrand = String(brandInput.value || '').trim().toLowerCase() === 'real car';

				if (isRealCarBrand) {
					scale1.value = '1:1';
					first = '1:1';
					scale2.value = '';
					second = '';
					scale1.disabled = true;
					scale2.disabled = true;
					scale2.classList.add('hidden');
					hiddenScale.value = '1:1';
					return;
				}

				scale1.disabled = !isEditing;

				if (first && first !== '1:1') {
					scale2.disabled = !isEditing;
					scale2.classList.remove('hidden');
				} else {
					scale2.value = '';
					scale2.disabled = true;
					scale2.classList.add('hidden');
				}

				Array.from(scale2.options).forEach(opt => {
					if (!opt.value) return;
					opt.disabled = Boolean(first && opt.value === first);
				});

				if (second && first && second === first) {
					scale2.value = '';
				}

				const selected = [];
				if (first) selected.push(first);
				if (scale2.value) selected.push(scale2.value);
				selected.sort((a, b) => denominator(b) - denominator(a));
				hiddenScale.value = selected.join('|');

			}

			function setEditing(editing) {
				isEditing = editing;

				form.querySelectorAll('input[type="text"], input[type="number"], textarea').forEach(el => {
					el.readOnly = !editing;
				});

				form.querySelectorAll('select').forEach(el => {
					if (el.id === 'scale-1' || el.id === 'scale-2') {
						return;
					}
					el.disabled = !editing;
				});

				const favoriteInput = document.getElementById('is_favorite');
				if (favoriteInput) {
					favoriteInput.disabled = !editing;
				}

				scale1.disabled = !editing;

				imageInput.disabled = !editing;
				saveBtn.disabled = !editing;
				editBtn.textContent = editing ? 'Cancel Edit' : 'Edit';
				imageUpload.classList.toggle('is-editing', editing);
				refreshScaleState();
			}

			setEditing(false);

			brandInput.addEventListener('input', () => {
				if (!isEditing) {
					return;
				}

				if (String(brandInput.value || '').trim().toLowerCase() !== 'real car') {
					return;
				}

				scale1.value = '1:1';
				scale2.value = '';
				refreshScaleState();
			});

			scale1.addEventListener('change', function() {
				if ((scale1.value || '').trim() === '1:1') {
					brandInput.value = 'Real Car';
				}
				refreshScaleState();
			});

			scale2.addEventListener('change', function() {
				if ((scale2.value || '').trim() === '1:1') {
					brandInput.value = 'Real Car';
				}
				refreshScaleState();
			});
			refreshScaleState();

			editBtn.addEventListener('click', () => {
				if (isEditing) {
					form.reset();
					imageInput.value = '';

					if (imagePreview) {
						if (initialImageSrc) {
							imagePreview.src = initialImageSrc;
							imagePreview.classList.remove('hidden');
							imageUpload.classList.add('has-image');
						} else {
							imagePreview.src = '';
							imagePreview.classList.add('hidden');
							imageUpload.classList.remove('has-image');
						}
					}

					formError.textContent = '';
					formError.classList.remove('visible');
					setEditing(false);
					return;
				}

				setEditing(true);
			});

			imageInput.addEventListener('change', () => {
				const file = imageInput.files && imageInput.files[0];
				if (!file) {
					return;
				}

				const reader = new FileReader();
				reader.onload = function(e) {
					if (imagePreview) {
						imagePreview.src = e.target.result;
						imagePreview.classList.remove('hidden');
						imageUpload.classList.add('has-image');
					}
				};
				reader.readAsDataURL(file);
			});

			form.addEventListener('submit', async function(e) {
				e.preventDefault();
				if (!isEditing) {
					return;
				}

				formError.textContent = '';
				formError.classList.remove('visible');

				try {
					const response = await fetch(form.getAttribute('action'), {
						method: 'POST',
						body: new FormData(form)
					});
					const data = await response.json();

					if (data.success) {
						window.location.href = data.redirect || ('car-details.php?id=' + encodeURIComponent(carIdInput.value));
						return;
					}

					formError.textContent = data.message || 'Could not save changes.';
					formError.classList.add('visible');
				} catch (error) {
					formError.textContent = 'Could not save changes.';
					formError.classList.add('visible');
				}
			});

			deleteBtn.addEventListener('click', async function() {
				const confirmed = window.confirm('Are you sure you want to delete it?');
				if (!confirmed) {
					return;
				}

				try {
					const body = new URLSearchParams({
						action: 'delete',
						car_id: carIdInput.value
					});

					const response = await fetch('../controllers/car-control.php', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded'
						},
						body
					});

					const data = await response.json();
					if (data.success) {
						window.location.href = data.redirect || 'overview.php';
						return;
					}

					formError.textContent = data.message || 'Could not delete car.';
					formError.classList.add('visible');
				} catch (error) {
					formError.textContent = 'Could not delete car.';
					formError.classList.add('visible');
				}
			});

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
		})();
	</script>
</body>

</html>
