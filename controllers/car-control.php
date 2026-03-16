<?php

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo json_encode(['success' => false, 'message' => 'Database connection is not available.']);
    exit;
}

if (!currentUser()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user = currentUser();

$action = $_POST['action'] ?? null;
if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified.']);
    exit;
}

function uploadsDir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'upload img';
}

function removeImageIfPresent(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }

    $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function getOwnedCar(PDO $pdo, int $carId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM cars WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$carId, $userId]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);
    return $car ?: null;
}

function saveUploadedImage(array $file, int $userId): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }

    $tmp = $file['tmp_name'] ?? '';
    if (!$tmp || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $extByMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($extByMime[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP or GIF images are allowed.');
    }

    $dir = uploadsDir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create upload directory.');
    }

    $filename = sprintf('car_%d_%s.%s', $userId, bin2hex(random_bytes(8)), $extByMime[$mime]);
    $target = $dir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    return 'upload img/' . $filename;
}

function normalizeScaleToken(string $scale): ?string
{
    $scale = trim($scale);
    if (!preg_match('/^1\s*:\s*(\d+)$/', $scale, $m)) {
        return null;
    }

    return '1:' . (int)$m[1];
}

function scaleDenominator(string $scale): int
{
    $parts = explode(':', $scale, 2);
    return isset($parts[1]) ? (int)$parts[1] : 0;
}

function normalizeScaleInput(string $rawScale): ?string
{
    $tokens = preg_split('/[|,\/]+/', $rawScale) ?: [];
    $normalized = [];

    foreach ($tokens as $token) {
        $value = normalizeScaleToken($token);
        if (!$value) {
            continue;
        }
        if (!in_array($value, $normalized, true)) {
            $normalized[] = $value;
        }
    }

    if (count($normalized) === 0 || count($normalized) > 2) {
        return null;
    }

    usort($normalized, static function(string $a, string $b): int {
        return scaleDenominator($b) <=> scaleDenominator($a);
    });

    return implode('|', $normalized);
}

function scaleIncludesOneToOne(string $scale): bool
{
    $parts = preg_split('/[|,\/]+/', $scale) ?: [];
    foreach ($parts as $part) {
        if (normalizeScaleToken((string)$part) === '1:1') {
            return true;
        }
    }
    return false;
}

function controllerColumnExists(PDO $pdo, string $table, string $column): bool
{
    $escapedColumn = str_replace(['\\', "'"], ['\\\\', "\\'"], $column);
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$escapedColumn'");
    return $stmt ? (bool)$stmt->fetch() : false;
}

function carsHasDetailsColumn(PDO $pdo): bool
{
    static $cached = null;
    if ($cached === null) {
        $cached = controllerColumnExists($pdo, 'cars', 'details');
    }
    return $cached;
}

function carsHasColumn(PDO $pdo, string $column): bool
{
    static $cache = [];
    if (!array_key_exists($column, $cache)) {
        $cache[$column] = controllerColumnExists($pdo, 'cars', $column);
    }
    return (bool)$cache[$column];
}

function carsHasImagePathColumn(PDO $pdo): bool
{
    static $cached = null;
    if ($cached === null) {
        $cached = controllerColumnExists($pdo, 'cars', 'image_path');
    }
    return $cached;
}

function normalizeBrandName(string $brand): string
{
    $brand = trim(preg_replace('/\s+/', ' ', $brand));
    if ($brand === '') {
        return '';
    }

    $first = substr($brand, 0, 1);
    $rest = substr($brand, 1);
    return strtoupper($first) . $rest;
}

function normalizeStatus(string $status): string
{
    $status = strtolower(trim($status));
    $allowed = ['owned', 'preordered', 'sold'];
    return in_array($status, $allowed, true) ? $status : 'owned';
}

function normalizeCondition(string $condition): ?string
{
    $condition = strtolower(trim($condition));
    $allowed = ['in case', 'out of case', 'in alt case', 'with blister', 'mint', 'good', 'fair', 'poor'];
    if ($condition === '') {
        return null;
    }
    return in_array($condition, $allowed, true) ? $condition : null;
}

function normalizeModelYear($value): ?int
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }

    if (!preg_match('/^\d{4}$/', $raw)) {
        return null;
    }

    $year = (int)$raw;
    if ($year < 1886 || $year > 2100) {
        return null;
    }

    return $year;
}

function brandSearchKey(string $brand): string
{
    $normalized = strtolower($brand);
    $normalized = preg_replace('/[^a-z0-9]+/i', '', $normalized);
    return trim($normalized ?? '');
}

function upsertUserBrand(PDO $pdo, int $userId, string $brandName): void
{
    $searchKey = brandSearchKey($brandName);
    if ($searchKey === '') {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO brands (user_id, name, search_key) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE search_key = VALUES(search_key), name = VALUES(name)');
    $stmt->execute([$userId, $brandName, $searchKey]);
}

function respond($success, $message = null, $redirect = null)
{
    $payload = ['success' => $success];
    if ($message) $payload['message'] = $message;
    if ($redirect) $payload['redirect'] = $redirect;
    echo json_encode($payload);
    exit;
}

try {
switch ($action) {
    case 'add_brand':
        $brand = normalizeBrandName((string)($_POST['brand_name'] ?? ''));
        if ($brand === '') {
            respond(false, 'Brand name is required.');
        }

        $first = substr($brand, 0, 1);
        if (strtoupper($first) !== $first) {
            respond(false, 'Brand name must start with a capital letter.');
        }

        $searchKey = brandSearchKey($brand);
        if ($searchKey === '') {
            respond(false, 'Brand name contains no valid letters or numbers.');
        }

        $existsStmt = $pdo->prepare('SELECT id FROM brands WHERE user_id = ? AND search_key = ? LIMIT 1');
        $existsStmt->execute([(int)$user['id'], $searchKey]);
        if ($existsStmt->fetch(PDO::FETCH_ASSOC)) {
            respond(false, 'This brand already exists in your collection.');
        }

        upsertUserBrand($pdo, (int)$user['id'], $brand);
        respond(true, 'Brand added successfully.', 'overview.php?brand_created=1&brand_name=' . rawurlencode($brand));
        break;

    case 'rename_brand':
        $brandId = (int)($_POST['brand_id'] ?? 0);
        if ($brandId <= 0) {
            respond(false, 'Invalid brand id.');
        }

        $newBrand = normalizeBrandName((string)($_POST['brand_name'] ?? ''));
        if ($newBrand === '') {
            respond(false, 'Brand name is required.');
        }

        $first = substr($newBrand, 0, 1);
        if (strtoupper($first) !== $first) {
            respond(false, 'Brand name must start with a capital letter.');
        }

        $newSearchKey = brandSearchKey($newBrand);
        if ($newSearchKey === '') {
            respond(false, 'Brand name contains no valid letters or numbers.');
        }

        $brandStmt = $pdo->prepare('SELECT id, name FROM brands WHERE id = ? AND user_id = ? LIMIT 1');
        $brandStmt->execute([$brandId, (int)$user['id']]);
        $brandRow = $brandStmt->fetch(PDO::FETCH_ASSOC);
        if (!$brandRow) {
            respond(false, 'Brand not found.');
        }

        $oldName = (string)$brandRow['name'];
        if (strcasecmp($oldName, 'Real Car') === 0) {
            respond(false, 'The built-in Real Car brand cannot be renamed.');
        }

        if (strcasecmp($oldName, $newBrand) === 0) {
            respond(true, 'Brand updated successfully.');
        }

        $existsStmt = $pdo->prepare('SELECT id FROM brands WHERE user_id = ? AND search_key = ? AND id <> ? LIMIT 1');
        $existsStmt->execute([(int)$user['id'], $newSearchKey, $brandId]);
        if ($existsStmt->fetch(PDO::FETCH_ASSOC)) {
            respond(false, 'This brand already exists in your collection.');
        }

        $pdo->beginTransaction();
        try {
            $updateBrand = $pdo->prepare('UPDATE brands SET name = ?, search_key = ? WHERE id = ? AND user_id = ?');
            $updateBrand->execute([$newBrand, $newSearchKey, $brandId, (int)$user['id']]);

            $updateCars = $pdo->prepare('UPDATE cars SET brand = ? WHERE user_id = ? AND brand = ?');
            $updateCars->execute([$newBrand, (int)$user['id'], $oldName]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        respond(true, 'Brand updated successfully.');
        break;

    case 'delete_brand':
        $brandId = (int)($_POST['brand_id'] ?? 0);
        if ($brandId <= 0) {
            respond(false, 'Invalid brand id.');
        }

        $brandStmt = $pdo->prepare('SELECT id, name FROM brands WHERE id = ? AND user_id = ? LIMIT 1');
        $brandStmt->execute([$brandId, (int)$user['id']]);
        $brandRow = $brandStmt->fetch(PDO::FETCH_ASSOC);
        if (!$brandRow) {
            respond(false, 'Brand not found.');
        }

        $name = (string)$brandRow['name'];
        if (strcasecmp($name, 'Real Car') === 0) {
            respond(false, 'The built-in Real Car brand cannot be deleted.');
        }

        $usedStmt = $pdo->prepare('SELECT COUNT(*) FROM cars WHERE user_id = ? AND brand = ?');
        $usedStmt->execute([(int)$user['id'], $name]);
        $usedCount = (int)$usedStmt->fetchColumn();
        if ($usedCount > 0) {
            respond(false, 'This brand is currently used by one or more vehicles.');
        }

        $deleteStmt = $pdo->prepare('DELETE FROM brands WHERE id = ? AND user_id = ?');
        $deleteStmt->execute([$brandId, (int)$user['id']]);
        respond(true, 'Brand deleted successfully.');
        break;

    case 'add':
        $brand = trim($_POST['brand'] ?? '');
        $brand = normalizeBrandName($brand);
        $carBrand = trim((string)($_POST['car_brand'] ?? $_POST['automerk'] ?? ''));
        $model = trim($_POST['model'] ?? '');
        $scale = normalizeScaleInput((string)($_POST['scale'] ?? ''));
        $details = trim((string)($_POST['extra_items'] ?? $_POST['details'] ?? ''));
        $modelYear = normalizeModelYear($_POST['model_year'] ?? null);
        $carCondition = normalizeCondition((string)($_POST['car_condition'] ?? ''));
        $carStatus = normalizeStatus((string)($_POST['car_status'] ?? 'owned'));
        $isFavorite = !empty($_POST['is_favorite']) ? 1 : 0;
        $bought = floatval($_POST['bought_price'] ?? 0);
        $estimated = floatval($_POST['estimated_value'] ?? 0);
        $imagePath = null;

        if ($scale && scaleIncludesOneToOne($scale)) {
            $brand = 'Real Car';
            $scale = '1:1';
        }

        if (strcasecmp($brand, 'Real Car') === 0) {
            $brand = 'Real Car';
            $scale = '1:1';
        }

        if (!$brand || !$carBrand || !$model || !$scale) {
            respond(false, 'Please fill in all required fields and choose one or two unique scales.');
        }

        upsertUserBrand($pdo, (int)$user['id'], $brand);

        if (isset($_FILES['car_image']) && ($_FILES['car_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $imagePath = saveUploadedImage($_FILES['car_image'], (int)$user['id']);
            } catch (RuntimeException $e) {
                respond(false, $e->getMessage());
            }
        }

        $columns = ['user_id', 'brand', 'automerk', 'model', 'scale', 'bought_price', 'estimated_value'];
        $values = [(int)$user['id'], $brand, $carBrand, $model, $scale, $bought, $estimated];

        if (carsHasDetailsColumn($pdo)) {
            $columns[] = 'details';
            $values[] = $details ?: null;
        }
        if (carsHasImagePathColumn($pdo)) {
            $columns[] = 'image_path';
            $values[] = $imagePath;
        }
        if (carsHasColumn($pdo, 'model_year')) {
            $columns[] = 'model_year';
            $values[] = $modelYear;
        }
        if (carsHasColumn($pdo, 'car_condition')) {
            $columns[] = 'car_condition';
            $values[] = $carCondition;
        }
        if (carsHasColumn($pdo, 'car_status')) {
            $columns[] = 'car_status';
            $values[] = $carStatus;
        }
        if (carsHasColumn($pdo, 'is_favorite')) {
            $columns[] = 'is_favorite';
            $values[] = $isFavorite;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO cars (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        $newId = (int)$pdo->lastInsertId();

        respond(true, 'Car added successfully.', 'car-details.php?id=' . $newId);
        break;

    case 'update':
        $carId = (int)($_POST['car_id'] ?? 0);
        if ($carId <= 0) {
            respond(false, 'Invalid car id.');
        }

        $car = getOwnedCar($pdo, $carId, (int)$user['id']);
        if (!$car) {
            respond(false, 'Car not found.');
        }

        $brand = normalizeBrandName((string)($_POST['brand'] ?? ''));
        $carBrand = trim((string)($_POST['car_brand'] ?? $_POST['automerk'] ?? ''));
        $model = trim($_POST['model'] ?? '');
        $scale = normalizeScaleInput((string)($_POST['scale'] ?? ''));
        $details = trim((string)($_POST['extra_items'] ?? $_POST['details'] ?? ''));
        $modelYear = normalizeModelYear($_POST['model_year'] ?? null);
        $carCondition = normalizeCondition((string)($_POST['car_condition'] ?? ''));
        $carStatus = normalizeStatus((string)($_POST['car_status'] ?? 'owned'));
        $isFavorite = !empty($_POST['is_favorite']) ? 1 : 0;
        $bought = floatval($_POST['bought_price'] ?? 0);
        $estimated = floatval($_POST['estimated_value'] ?? 0);
        $imagePath = (carsHasImagePathColumn($pdo) ? ($car['image_path'] ?? null) : null);

        if ($scale && scaleIncludesOneToOne($scale)) {
            $brand = 'Real Car';
            $scale = '1:1';
        }

        if (strcasecmp($brand, 'Real Car') === 0) {
            $brand = 'Real Car';
            $scale = '1:1';
        }

        if (!$brand || !$carBrand || !$model || !$scale) {
            respond(false, 'Please fill in all required fields and choose one or two unique scales.');
        }

        upsertUserBrand($pdo, (int)$user['id'], $brand);

        if (isset($_FILES['car_image']) && ($_FILES['car_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newImagePath = saveUploadedImage($_FILES['car_image'], (int)$user['id']);
                removeImageIfPresent($imagePath);
                $imagePath = $newImagePath;
            } catch (RuntimeException $e) {
                respond(false, $e->getMessage());
            }
        }

        $setClauses = ['brand = ?', 'automerk = ?', 'model = ?', 'scale = ?', 'bought_price = ?', 'estimated_value = ?'];
        $values = [$brand, $carBrand, $model, $scale, $bought, $estimated];

        if (carsHasDetailsColumn($pdo)) {
            $setClauses[] = 'details = ?';
            $values[] = $details ?: null;
        }
        if (carsHasImagePathColumn($pdo)) {
            $setClauses[] = 'image_path = ?';
            $values[] = $imagePath;
        }
        if (carsHasColumn($pdo, 'model_year')) {
            $setClauses[] = 'model_year = ?';
            $values[] = $modelYear;
        }
        if (carsHasColumn($pdo, 'car_condition')) {
            $setClauses[] = 'car_condition = ?';
            $values[] = $carCondition;
        }
        if (carsHasColumn($pdo, 'car_status')) {
            $setClauses[] = 'car_status = ?';
            $values[] = $carStatus;
        }
        if (carsHasColumn($pdo, 'is_favorite')) {
            $setClauses[] = 'is_favorite = ?';
            $values[] = $isFavorite;
        }

        $values[] = $carId;
        $values[] = (int)$user['id'];
        $sql = 'UPDATE cars SET ' . implode(', ', $setClauses) . ' WHERE id = ? AND user_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        respond(true, 'Car updated successfully.', 'car-details.php?id=' . $carId);
        break;

    case 'delete':
        $carId = (int)($_POST['car_id'] ?? 0);
        if ($carId <= 0) {
            respond(false, 'Invalid car id.');
        }

        $car = getOwnedCar($pdo, $carId, (int)$user['id']);
        if (!$car) {
            respond(false, 'Car not found.');
        }

        $stmt = $pdo->prepare('DELETE FROM cars WHERE id = ? AND user_id = ?');
        $stmt->execute([$carId, $user['id']]);
        if (carsHasImagePathColumn($pdo)) {
            removeImageIfPresent($car['image_path'] ?? null);
        }

        respond(true, 'Car deleted successfully.', 'overview.php');
        break;

    default:
        respond(false, 'Unknown action.');
        break;
}
} catch (Throwable $e) {
    error_log($e->getMessage() . PHP_EOL, 3, __DIR__ . '/../config/db_errors.log');
    respond(false, 'Could not save vehicle right now. Please try again.');
}
