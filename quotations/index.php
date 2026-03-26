<?php
require_once '../config/database.php';
checkLogin();

if (isSupplier()) {
    header("Location: /forwarder/shipments/index.php?error=no_permission");
    exit();
}

$conn = getDBConnection();

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "1=1";
$params = [];
$types = '';

if ($search !== '') {
    $where .= " AND (q.quotation_no LIKE ? OR c.company_name LIKE ? OR c.short_name LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$sql = "SELECT q.*, c.company_name, c.short_name
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        WHERE $where
        ORDER BY q.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$statusBadge = [
    'draft'    => ['color' => 'secondary', 'text' => 'Nháp'],
    'sent'     => ['color' => 'primary',   'text' => 'Đã gửi'],
    'accepted' => ['color' => 'success',   'text' => 'Chấp nhận'],
    'rejected' => ['color' => 'danger',    'text' => 'Từ chối'],
    'expired'  => ['color' => 'warning',   'text' => 'Hết hạn'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Giá - Forwarder System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .table thead th { background:#343a40; color:white; font-size:.78rem; white-space:nowrap; padding:8px 6px; }
        .table tbody td { font-size:.85rem; vertical-align:middle; }
    </style>
</head>
<body class="bg-light">

<?php include '../partials/navbar.php'; ?>

<div class="container-fluid mt-3 pb-5">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-file-earmark-text text-primary"></i> Báo Giá</h4>
        <a href="add.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> + Tạo báo giá
        </a>
    </div>

    <!-- Thông báo -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php
                if ($_GET['success'] === 'added')   echo '<i class="bi bi-check-circle"></i> Tạo báo giá thành công!';
                if ($_GET['success'] === 'updated') echo '<i class="bi bi-check-circle"></i> Cập nhật báo giá thành công!';
                if ($_GET['success'] === 'deleted') echo '<i class="bi bi-check-circle"></i> Xóa báo giá thành công!';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tìm kiếm -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control"
                           placeholder="Tìm theo số báo giá, tên công ty..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Tìm kiếm
                    </button>
                </div>
                <?php if ($search): ?>
                <div class="col-md-2">
                    <a href="index.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-circle"></i> Xóa bộ lọc
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Bảng danh sách -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Số BG</th>
                            <th>Tên công ty</th>
                            <th>Ngày lập</th>
                            <th>Hiệu lực đến</th>
                            <th>Tiền tệ</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php $stt = 1; while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $stt++; ?></td>
                                <td><strong class="text-primary"><?php echo htmlspecialchars($row['quotation_no']); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['company_name'] ?? ''); ?></strong>
                                    <?php if ($row['short_name']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($row['short_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row['issue_date'] ? date('d/m/Y', strtotime($row['issue_date'])) : ''; ?></td>
                                <td><?php echo $row['valid_until'] ? date('d/m/Y', strtotime($row['valid_until'])) : '<span class="text-muted">—</span>'; ?></td>
                                <td><?php echo htmlspecialchars($row['currency']); ?></td>
                                <td>
                                    <?php
                                        $s = $row['status'];
                                        $badge = $statusBadge[$s] ?? ['color' => 'secondary', 'text' => $s];
                                    ?>
                                    <span class="badge bg-<?php echo $badge['color']; ?>"><?php echo $badge['text']; ?></span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-info" title="Xem chi tiết">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-warning" title="Sửa">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if (isAdmin()): ?>
                                        <a href="delete.php?id=<?php echo $row['id']; ?>"
                                           class="btn btn-danger"
                                           title="Xóa"
                                           onclick="return confirm('Bạn có chắc muốn xóa báo giá này?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-inbox" style="font-size:3rem; color:#ccc;"></i>
                                    <p class="text-muted mt-2">Chưa có báo giá nào<?php echo $search ? ' phù hợp' : ''; ?></p>
                                    <a href="add.php" class="btn btn-sm btn-success">
                                        <i class="bi bi-plus-circle"></i> Tạo báo giá đầu tiên
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<footer class="bg-light text-center py-3 mt-4">
    <p class="mb-0 text-muted">&copy; <?php echo date('Y'); ?> Forwarder System</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
