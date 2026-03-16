<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    header('Location: login.php');
    exit;
}

$user = currentUser();
$stmt = $pdo->prepare('SELECT * FROM cars WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([(int)$user['id']]);
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lists - Collection Checker</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="stylesheet" href="../assets/css/styles.css?v=20260313p">
</head>

<body>
    <div class="app">
        <aside class="app__sidebar">
            <img src="../assets/images/logo.png" class="sidebar-logo" alt="Collection Checker">
            <a href="dashboard.php" class="nav-button">Dashboard</a>
            <a href="overview.php" class="nav-button">Collection Overview</a>
            <a href="overview.php" class="nav-button">Back</a>
            <a href="lists.php" class="nav-button nav-button--active">Lists</a>
            <a href="#" class="nav-button logout" id="logoutBtn">Logout</a>
            <a href="settings.php" class="settings-button" aria-label="Settings">⚙</a>
        </aside>

        <main class="app__main lists-main">
            <div class="overview-toolbar">
                <div class="overview-search">
                    <input id="lists-search" type="search" placeholder="Search in lists" autocomplete="off">
                </div>
            </div>

            <div class="lists-tabs">
                <button class="lists-tab is-active" data-list="favorites">Favorites <span id="count-favorites">0</span></button>
                <button class="lists-tab" data-list="preordered">Preordered <span id="count-preordered">0</span></button>
                <button class="lists-tab" data-list="sold">Sold Items <span id="count-sold">0</span></button>
            </div>

            <div id="lists-empty" class="no-results"></div>
            <div id="lists-cards" class="cards-grid"></div>
        </main>
    </div>

    <script>
        window.LISTS_CARS = <?php echo json_encode($cars, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
    <script src="../assets/js/lists.js?v=20260313p"></script>
    <script>
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
