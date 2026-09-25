<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';
$filter = $_GET['filter'] ?? 'all';
$pageTitle = 'Daftar Tugas';
$activeMenu = 'tasks';
ob_start();
?>
<div class="container-fluid p-0">
    <h4 class="fw-bold text-dark mb-1">Daftar Tugas</h4>
    <p class="text-secondary small mb-4">Tugas dari seluruh mata kuliahmu.</p>
    <div id="globalTasksAlert"></div>
    <div id="globalTasksList" class="d-flex flex-column gap-2"><div class="text-center text-muted py-5">Memuat tugas...</div></div>
</div>
<script>const BASE_URL = "<?= BASE_URL ?>"; const GLOBAL_TASK_FILTER = <?= json_encode($filter) ?>;</script>
<script src="<?= BASE_URL ?>public/js/modules/tasks.js"></script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
