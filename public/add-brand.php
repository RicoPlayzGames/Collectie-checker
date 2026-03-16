<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$user = currentUser();
$brands = [];
if (isset($pdo) && $pdo instanceof PDO && $user) {
	$stmt = $pdo->prepare('SELECT id, name FROM brands WHERE user_id = ? ORDER BY name ASC');
	$stmt->execute([(int)$user['id']]);
	$brands = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Add Brand - Collection Checker</title>
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
			<a href="#" class="nav-button logout" id="logoutBtn">Logout</a>
			<a href="settings.php" class="settings-button" aria-label="Settings">⚙</a>
		</aside>

		<main class="app__main add-vehicle-main">
			<section class="add-vehicle-card" style="max-width: 720px; width: 100%; min-height: auto;">
				<div class="brand-manage-grid">
					<div class="brand-manage-pane">
						<h2>Add Brand</h2>
						<form method="POST" action="../controllers/car-control.php" id="add-brand-form" class="add-vehicle-form">
							<input type="hidden" name="action" value="add_brand">
							<div id="form-error" class="form-error"></div>

							<div class="add-field">
								<label for="brand_name">Brand Name</label>
								<input type="text" id="brand_name" name="brand_name" placeholder="Example: Lego" required>
							</div>

							<div class="add-vehicle-actions" style="justify-content: flex-end;">
								<a href="add-car.php" class="add-btn add-btn--cancel">Cancel</a>
								<button type="submit" class="add-btn add-btn--save">Save</button>
							</div>
						</form>
					</div>

					<div class="brand-manage-divider" aria-hidden="true"></div>

					<div class="brand-manage-pane">
						<h2>Manage Brands</h2>
						<p class="brand-manage-note">Use Edit to rename a brand. Only brands not used by vehicles can be removed.</p>
						<div id="brand-manage-error" class="form-error"></div>
						<ul class="brand-list" id="brand-list">
							<?php if (count($brands) === 0): ?>
								<li class="brand-list-empty">No brands to remove yet.</li>
							<?php endif; ?>
							<?php foreach ($brands as $brand): ?>
								<li class="brand-list-item" data-brand-id="<?php echo (int)$brand['id']; ?>">
									<span class="brand-name"><?php echo e((string)$brand['name']); ?></span>
									<?php if (strtolower((string)$brand['name']) !== 'real car'): ?>
										<div class="brand-row-actions">
											<button type="button" class="add-btn add-btn--edit edit-brand-btn" data-brand-id="<?php echo (int)$brand['id']; ?>">Edit</button>
											<button type="button" class="add-btn add-btn--cancel delete-brand-btn" data-brand-id="<?php echo (int)$brand['id']; ?>">Delete</button>
										</div>
									<?php else: ?>
										<span class="brand-lock">Built-in</span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</section>
		</main>
	</div>

	<script>
		document.getElementById('add-brand-form').addEventListener('submit', async function(e) {
			e.preventDefault();
			const form = this;
			const error = document.getElementById('form-error');
			const brandInput = document.getElementById('brand_name');
			error.textContent = '';
			error.classList.remove('visible');

			const brand = (brandInput.value || '').trim();
			if (!brand) {
				error.textContent = 'Brand name is required.';
				error.classList.add('visible');
				return;
			}

			if (brand[0] !== brand[0].toUpperCase()) {
				error.textContent = 'Brand name must start with a capital letter.';
				error.classList.add('visible');
				return;
			}

			try {
				const response = await fetch(form.getAttribute('action'), {
					method: 'POST',
					body: new FormData(form)
				});
				const data = await response.json();

				if (data.success) {
					window.location.href = data.redirect || 'overview.php';
					return;
				}

				error.textContent = data.message || 'Could not add brand.';
				error.classList.add('visible');
			} catch (err) {
				error.textContent = 'Could not add brand.';
				error.classList.add('visible');
			}
		});

		document.querySelectorAll('.edit-brand-btn').forEach((btn) => {
			btn.addEventListener('click', async function() {
				const brandId = this.getAttribute('data-brand-id');
				const row = this.closest('.brand-list-item');
				const error = document.getElementById('brand-manage-error');
				error.textContent = '';
				error.classList.remove('visible');

				if (!brandId || !row) {
					return;
				}

				const brandNameEl = row.querySelector('.brand-name');
				const oldName = brandNameEl ? brandNameEl.textContent.trim() : '';
				const newName = window.prompt('Rename brand:', oldName);
				if (newName === null) {
					return;
				}

				const trimmed = (newName || '').trim();
				if (!trimmed) {
					error.textContent = 'Brand name is required.';
					error.classList.add('visible');
					return;
				}

				if (trimmed[0] !== trimmed[0].toUpperCase()) {
					error.textContent = 'Brand name must start with a capital letter.';
					error.classList.add('visible');
					return;
				}

				try {
					const body = new URLSearchParams({ action: 'rename_brand', brand_id: brandId, brand_name: trimmed });
					const response = await fetch('../controllers/car-control.php', {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body
					});

					const data = await response.json();
					if (data.success) {
						if (brandNameEl) {
							brandNameEl.textContent = trimmed;
						}
						return;
					}

					error.textContent = data.message || 'Could not rename brand.';
					error.classList.add('visible');
				} catch (err) {
					error.textContent = 'Could not rename brand.';
					error.classList.add('visible');
				}
			});
		});

		document.querySelectorAll('.delete-brand-btn').forEach((btn) => {
			btn.addEventListener('click', async function() {
				const brandId = this.getAttribute('data-brand-id');
				const row = this.closest('.brand-list-item');
				const error = document.getElementById('brand-manage-error');
				error.textContent = '';
				error.classList.remove('visible');

				if (!brandId || !row) {
					return;
				}

				const brandNameEl = row.querySelector('.brand-name');
				const brandName = brandNameEl ? brandNameEl.textContent.trim() : 'this brand';
				const confirmed = window.confirm('Delete "' + brandName + '"?');
				if (!confirmed) {
					return;
				}

				try {
					const body = new URLSearchParams({ action: 'delete_brand', brand_id: brandId });
					const response = await fetch('../controllers/car-control.php', {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body
					});

					const data = await response.json();
					if (data.success) {
						row.remove();
						if (!document.querySelector('.brand-list-item')) {
							document.getElementById('brand-list').innerHTML = '<li class="brand-list-empty">No brands to remove yet.</li>';
						}
						return;
					}

					error.textContent = data.message || 'Could not delete brand.';
					error.classList.add('visible');
				} catch (err) {
					error.textContent = 'Could not delete brand.';
					error.classList.add('visible');
				}
			});
		});

		document.getElementById('logoutBtn').addEventListener('click', function() {
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
