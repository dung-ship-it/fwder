<?php
require_once '../config/database.php';
checkLogin();

$conn = getDBConnection();
$_is_supplier = isSupplier();
$_my_sup_id   = getMySupplierID();

// Xử lý tìm kiếm & lọc
$search          = isset($_GET['search'])     ? trim($_GET['search'])     : '';
$status_filter   = isset($_GET['status'])     ? $_GET['status']           : '';
$locked_filter   = isset($_GET['locked'])     ? $_GET['locked']           : '';
$customer_filter = isset($_GET['customer'])   ? intval($_GET['customer']) : 0;
$date_from       = isset($_GET['date_from'])  ? trim($_GET['date_from'])  : '';
$date_to         = isset($_GET['date_to'])    ? trim($_GET['date_to'])    : '';
$email_filter    = isset($_GET['email_sent']) ? $_GET['email_sent']       : '';

$where = ["s.deleted_at IS NULL"];

// Supplier chỉ thấy lô đã duyệt + lô pending của chính họ
if ($_is_supplier) {
    $where[] = "(s.approval_status = 'approved' OR (s.approval_status = 'pending_approval' AND s.created_by = " . intval($_SESSION['user_id']) . "))";
} else {
    // Admin/Staff: ẩn lô pending_approval khỏi danh sách chính (có trang riêng)
    // Bỏ comment dòng dưới nếu muốn hiện tất cả kể cả pending_approval
    // $where[] = "s.approval_status != 'pending_approval'";
}

if ($search) {
    $s = $conn->real_escape_string($search);
    $where[] = "(s.job_no LIKE '%$s%' OR s.mawb LIKE '%$s%' OR s.hawb LIKE '%$s%'
                 OR s.shipper LIKE '%$s%' OR s.cnee LIKE '%$s%'
                 OR s.customs_declaration_no LIKE '%$s%'
                 OR c.short_name LIKE '%$s%')";
}
if ($status_filter)   $where[] = "s.status = '$status_filter'";
if ($locked_filter)   $where[] = "s.is_locked = '$locked_filter'";
if ($customer_filter) $where[] = "s.customer_id = $customer_filter";
if ($date_from)       $where[] = "DATE(s.created_at) >= '$date_from'";
if ($date_to)         $where[] = "DATE(s.created_at) <= '$date_to'";
if ($email_filter)    $where[] = "s.email_sent = '$email_filter'";

$whereClause = 'WHERE ' . implode(' AND ', $where);

$sql = "SELECT s.*,
               c.company_name, c.short_name AS customer_short,
               COALESCE(sc.total_cost, 0) AS total_cost,
               COALESCE(ss.total_sell, 0) AS total_sell,
               COALESCE(ss.total_sell, 0) - COALESCE(sc.total_cost, 0) AS profit
        FROM shipments s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN (SELECT shipment_id, SUM(total_amount) AS total_cost FROM shipment_costs GROUP BY shipment_id) sc ON sc.shipment_id = s.id
        LEFT JOIN (SELECT shipment_id, SUM(total_amount) AS total_sell FROM shipment_sells GROUP BY shipment_id) ss ON ss.shipment_id = s.id
        $whereClause
        ORDER BY s.created_at DESC";

$result = $conn->query($sql);

// Stats (chỉ cho admin/staff)
$stats = [];
if (!$_is_supplier) {
    $stats = [
        'total'      => $conn->query("SELECT COUNT(*) c FROM shipments WHERE deleted_at IS NULL AND approval_status != 'pending_approval'")->fetch_assoc()['c'],
        'pending'    => $conn->query("SELECT COUNT(*) c FROM shipments WHERE status='pending' AND deleted_at IS NULL AND approval_status='approved'")->fetch_assoc()['c'],
        'in_transit' => $conn->query("SELECT COUNT(*) c FROM shipments WHERE status='in_transit' AND deleted_at IS NULL")->fetch_assoc()['c'],
        'arrived'    => $conn->query("SELECT COUNT(*) c FROM shipments WHERE status='arrived' AND deleted_at IS NULL")->fetch_assoc()['c'],
        'cleared'    => $conn->query("SELECT COUNT(*) c FROM shipments WHERE status='cleared' AND deleted_at IS NULL")->fetch_assoc()['c'],
        'delivered'  => $conn->query("SELECT COUNT(*) c FROM shipments WHERE status='delivered' AND deleted_at IS NULL")->fetch_assoc()['c'],
        'locked'     => $conn->query("SELECT COUNT(*) c FROM shipments WHERE is_locked='yes' AND deleted_at IS NULL")->fetch_assoc()['c'],
        'pending_approval' => $conn->query("SELECT COUNT(*) c FROM shipments WHERE approval_status='pending_approval' AND deleted_at IS NULL")->fetch_assoc()['c'],
        'trash'      => $conn->query("SELECT COUNT(*) c FROM shipments WHERE deleted_at IS NOT NULL")->fetch_assoc()['c'],
    ];
}

$customers = !$_is_supplier
    ? $conn->query("SELECT id, short_name, company_name FROM customers WHERE status='active' ORDER BY short_name")
    : null;

$statusBadge = [
    'pending'    => ['color' => 'warning',  'text' => 'Chờ xử lý',       'icon' => 'hourglass-split'],
    'in_transit' => ['color' => 'primary',  'text' => 'Đang vận chuyển', 'icon' => 'truck'],
    'arrived'    => ['color' => 'info',     'text' => 'Đã đến',          'icon' => 'geo-alt'],
    'cleared'    => ['color' => 'success',  'text' => 'Đã thông quan',   'icon' => 'check-circle'],
    'delivered'  => ['color' => 'dark',     'text' => 'Đã giao',         'icon' => 'box-seam'],
    'cancelled'  => ['color' => 'danger',   'text' => 'Đã hủy',          'icon' => 'x-circle'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Lô hàng - Forwarder System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .shipment-row { cursor:pointer; transition:background-color .15s; }
        .shipment-row:hover { background-color:#e8f4fd !important; }
        .shipment-row td { vertical-align:middle; }
        .stat-card { border-radius:10px; transition:transform .2s,box-shadow .2s; cursor:pointer; }
        .stat-card:hover { transform:translateY(-4px); box-shadow:0 6px 20px rgba(0,0,0,.15); }
        .stat-card .stat-num { font-size:1.9rem; font-weight:700; }
        .job-no { font-weight:700; color:#0d6efd; font-size:.9rem; }
        .profit-pos { color:#198754; font-weight:600; }
        .profit-neg { color:#dc3545; font-weight:600; }
        .action-btn { opacity:0; transition:opacity .2s; }
        .shipment-row:hover .action-btn { opacity:1; }
        .table thead th { background:#343a40; color:white; font-size:.78rem; white-space:nowrap; padding:8px 6px; }
        .table tbody td { font-size:.82rem; padding:6px; }
        .pending-approval-row { background:#fffbeb !important; }
    </style>
</head>
<body class="bg-light">

<?php include '../partials/navbar.php'; ?>

<div class="container-fluid mt-3 pb-5">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-box text-primary"></i> Quản lý Lô hàng</h4>
        <div class="d-flex gap-2">
            <?php if (!$_is_supplier): ?>
            <?php if (($stats['pending_approval'] ?? 0) > 0): ?>
            <a href="pending.php" class="btn btn-warning">
                <i class="bi bi-hourglass-split"></i> Chờ duyệt
                <span class="badge bg-dark ms-1"><?php echo $stats['pending_approval']; ?></span>
            </a>
            <?php endif; ?>
            <a href="trash.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-trash3"></i> Thùng rác
            </a>
            <?php endif; ?>
            <a href="add.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Thêm lô hàng mới
            </a>
        </div>
    </div>

    <!-- THỐNG KÊ (chỉ admin/staff) -->
    <?php if (!$_is_supplier): ?>
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-2">
            <div class="card stat-card border-0 shadow-sm bg-primary text-white" onclick="filterByStatus('')">
                <div class="card-body p-3">
                    <div class="stat-num"><?php echo $stats['total']; ?></div>
                    <div class="small">Tất cả</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card stat-card border-0 shadow-sm bg-warning" onclick="filterByStatus('pending')">
                <div class="card-body p-3">
                    <div class="stat-num"><?php echo $stats['pending']; ?></div>
                    <div class="small">Chờ xử lý</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card stat-card border-0 shadow-sm bg-primary text-white" onclick="filterByStatus('in_transit')">
                <div class="card-body p-3">
                    <div class="stat-num"><?php echo $stats['in_transit']; ?></div>
                    <div class="small">Đang vận chuyển</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card stat-card border-0 shadow-sm bg-info text-white" onclick="filterByStatus('arrived')">
                <div class="card-body p-3">
                    <div class="stat-num"><?php echo $stats['arrived']; ?></div>
                    <div class="small">Đã đến</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card stat-card border-0 shadow-sm bg-success text-white" onclick="filterByStatus('cleared')">
                <div class="card-body p-3">
                    <div class="stat-num"><?php echo $stats['cleared']; ?></div>
                    <div class="small">Đã thông quan</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card stat-card border-0 shadow-sm bg-dark text-white" onclick="filterByStatus('delivered')">
                <div class="card-body p-3">
                    <div class="stat-num"><?php echo $stats['delivered']; ?></div>
                    <div class="small">Đã giao</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- SUPPLIER: Banner thông báo -->
    <?php if ($_is_supplier): ?>
    <div class="alert alert-info py-2 mb-3">
        <i class="bi bi-building me-1"></i>
        Xin chào <strong><?php echo htmlspecialchars($_SESSION['supplier_name'] ?? ''); ?></strong>!
        Bạn chỉ thấy cột <strong>Chi phí (Cost)</strong>. Để thêm/sửa chi phí, click vào từng lô hàng.
    </div>
    <?php endif; ?>

    <!-- BỘ LỌC -->
    <div class="card shadow-sm mb-3" style="border-left:4px solid #0d6efd;">
        <div class="card-body py-2">
            <form method="GET" id="filterForm" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="🔍 Job No, MAWB, HAWB, Shipper..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <?php if (!$_is_supplier && $customers): ?>
                <div class="col-md-2">
                    <select name="customer" class="form-select form-select-sm">
                        <option value="">-- Khách hàng --</option>
                        <?php while ($c = $customers->fetch_assoc()): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $customer_filter == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['short_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm" id="statusSelect">
                        <option value="">-- Trạng thái --</option>
                        <option value="pending"    <?php echo $status_filter=='pending'    ?'selected':''; ?>>Chờ xử lý</option>
                        <option value="in_transit" <?php echo $status_filter=='in_transit' ?'selected':''; ?>>Đang vận chuyển</option>
                        <option value="arrived"    <?php echo $status_filter=='arrived'    ?'selected':''; ?>>Đã đến</option>
                        <option value="cleared"    <?php echo $status_filter=='cleared'    ?'selected':''; ?>>Đã thông quan</option>
                        <option value="delivered"  <?php echo $status_filter=='delivered'  ?'selected':''; ?>>Đã giao</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="<?php echo $date_from; ?>" placeholder="Từ ngày">
                </div>
                <div class="col-md-1">
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="<?php echo $date_to; ?>" placeholder="Đến ngày">
                </div>
                <div class="col-auto d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Lọc</button>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i> Xóa</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show py-2">
        <i class="bi bi-check-circle"></i>
        <?php
            if ($_GET['success'] == 'added')    echo 'Thêm lô hàng thành công!' . (isSupplier() ? ' <strong>Đang chờ Admin/Staff duyệt.</strong>' : '');
            if ($_GET['success'] == 'updated')  echo 'Cập nhật lô hàng thành công!';
            if ($_GET['success'] == 'approved') echo '<strong>Đã duyệt lô hàng!</strong>';
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- BẢNG -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between py-2">
            <span>
                <i class="bi bi-table"></i> Danh sách lô hàng
                <span class="badge bg-light text-dark ms-1"><?php echo $result->num_rows; ?> lô hàng</span>
            </span>
            <small class="text-white-50"><i class="bi bi-hand-index"></i> Click vào dòng để xem chi tiết</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Job No</th>
                            <?php if (!$_is_supplier): ?>
                            <th>Khách hàng</th>
                            <?php endif; ?>
                            <th>MAWB / HAWB</th>
                            <th>Số tờ khai</th>
                            <?php if (!$_is_supplier): ?>
                            <th>Shipper / CNEE</th>
                            <?php endif; ?>
                            <th>Ngày đến</th>
                            <th class="text-end">COST</th>
                            <?php if (!$_is_supplier): ?>
                            <th class="text-end">SELL</th>
                            <th class="text-end">Lợi nhuận</th>
                            <?php endif; ?>
                            <th>Trạng thái</th>
                            <?php if (!$_is_supplier): ?>
                            <th>Phê duyệt</th>
                            <?php endif; ?>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result->num_rows > 0):
                        $stt = 1;
                        while ($row = $result->fetch_assoc()):
                            $badge   = $statusBadge[$row['status']] ?? ['color'=>'secondary','text'=>$row['status'],'icon'=>'circle'];
                            $profit  = floatval($row['profit']);
                            $isPendingApproval = ($row['approval_status'] ?? '') === 'pending_approval';
                    ?>
                        <tr class="shipment-row <?php echo $isPendingApproval ? 'pending-approval-row' : ''; ?>"
                            onclick="goToDetail(<?php echo $row['id']; ?>, event)">
                            <td class="text-center text-muted"><?php echo $stt++; ?></td>
                            <td>
                                <span class="job-no"><?php echo htmlspecialchars($row['job_no']); ?></span>
                                <?php if ($row['is_locked'] == 'yes'): ?>
                                    <br><span class="badge bg-danger" style="font-size:.65rem;">
                                        <i class="bi bi-lock-fill"></i> Đã khóa
                                    </span>
                                <?php endif; ?>
                                <?php if ($isPendingApproval): ?>
                                    <br><span class="badge bg-warning text-dark" style="font-size:.65rem;">
                                        <i class="bi bi-hourglass-split"></i> Chờ duyệt
                                    </span>
                                <?php endif; ?>
                            </td>
                            <?php if (!$_is_supplier): ?>
                            <td>
                                <span class="badge bg-info text-dark"><?php echo htmlspecialchars($row['customer_short'] ?? ''); ?></span>
                                <br><small class="text-muted"><?php echo htmlspecialchars($row['company_name'] ?? ''); ?></small>
                            </td>
                            <?php endif; ?>
                            <td>
                                <small>
                                    <span class="text-muted">M:</span> <strong><?php echo htmlspecialchars($row['mawb']); ?></strong><br>
                                    <span class="text-muted">H:</span> <?php echo htmlspecialchars($row['hawb']); ?>
                                </small>
                            </td>
                            <td>
                                <?php if (!empty($row['customs_declaration_no'])): ?>
                                    <small class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['customs_declaration_no']); ?></small>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <?php if (!$_is_supplier): ?>
                            <td>
                                <small>
                                    <?php if ($row['shipper']): ?><i class="bi bi-box-arrow-up text-primary"></i> <?php echo htmlspecialchars($row['shipper']); ?><br><?php endif; ?>
                                    <?php if ($row['cnee']): ?><i class="bi bi-box-arrow-down text-success"></i> <?php echo htmlspecialchars($row['cnee']); ?><?php endif; ?>
                                </small>
                            </td>
                            <?php endif; ?>
                            <td>
                                <small>
                                    <?php echo $row['arrival_date']
                                        ? date('d/m/Y', strtotime($row['arrival_date']))
                                        : '<span class="text-muted">—</span>'; ?>
                                </small>
                            </td>
                            <!-- COST — tất cả đều thấy -->
                            <td class="text-end">
                                <small class="text-danger fw-bold">
                                    <?php echo $row['total_cost'] > 0 ? number_format($row['total_cost'], 0, ',', '.') : '—'; ?>
                                </small>
                            </td>
                            <!-- SELL + Profit — chỉ admin/staff -->
                            <?php if (!$_is_supplier): ?>
                            <td class="text-end">
                                <small class="text-success fw-bold">
                                    <?php echo $row['total_sell'] > 0 ? number_format($row['total_sell'], 0, ',', '.') : '—'; ?>
                                </small>
                            </td>
                            <td class="text-end">
                                <?php if ($row['total_cost'] > 0 || $row['total_sell'] > 0): ?>
                                    <small class="<?php echo $profit >= 0 ? 'profit-pos' : 'profit-neg'; ?>">
                                        <?php echo ($profit >= 0 ? '+' : '') . number_format($profit, 0, ',', '.'); ?>
                                    </small>
                                <?php else: ?>
                                    <small class="text-muted">—</small>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td>
                                <span class="badge bg-<?php echo $badge['color']; ?>">
                                    <i class="bi bi-<?php echo $badge['icon']; ?>"></i> <?php echo $badge['text']; ?>
                                </span>
                            </td>
                            <?php if (!$_is_supplier): ?>
                            <td>
                                <?php if ($isPendingApproval): ?>
                                    <a href="pending.php" class="badge bg-warning text-dark text-decoration-none">
                                        <i class="bi bi-hourglass-split"></i> Chờ duyệt
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-success"><i class="bi bi-check"></i> Đã duyệt</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td onclick="event.stopPropagation()">
                                <div class="d-flex gap-1 justify-content-center action-btn">
                                    <?php
                                    $canEdit = true;
                                    if ($_is_supplier && $row['is_locked'] == 'yes') $canEdit = false;
                                    if ($_is_supplier && !$isPendingApproval && $row['approval_status'] !== 'approved') $canEdit = false;
                                    ?>
                                    <?php if ($canEdit): ?>
                                    <a href="edit.php?id=<?php echo $row['id']; ?>"
                                       class="btn btn-warning btn-sm" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (isAdmin()): ?>
                                    <a href="delete.php?id=<?php echo $row['id']; ?>"
                                       class="btn btn-danger btn-sm" title="Xóa"
                                       onclick="return confirm('Xóa lô hàng <?php echo htmlspecialchars($row['job_no']); ?>?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $_is_supplier ? 8 : 13; ?>" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox" style="font-size:3rem;color:#ccc;"></i>
                                <p class="mt-2">Không tìm thấy lô hàng nào</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function goToDetail(id, event) {
    if (event.target.closest('.action-btn')) return;
    window.location.href = 'view.php?id=' + id;
}
function filterByStatus(status) {
    document.getElementById('statusSelect').value = status;
    document.getElementById('filterForm').submit();
}
document.querySelectorAll('#filterForm select').forEach(sel => {
    sel.addEventListener('change', () => document.getElementById('filterForm').submit());
});
</script>
</body>
</html>