<?php
require_once '../config/database.php';
checkLogin();
requireNotSupplier();

$conn = getDBConnection();

// Xử lý duyệt / từ chối
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipment_id = intval($_POST['shipment_id'] ?? 0);
    $action      = $_POST['action'] ?? '';

    if ($shipment_id > 0) {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE shipments SET approval_status = 'approved' WHERE id = ?");
            $stmt->bind_param("i", $shipment_id);
            $stmt->execute();
            $stmt->close();
            // Đánh dấu notification đã đọc
            $conn->query("UPDATE notifications SET is_read = 1 WHERE ref_id = $shipment_id AND type = 'new_shipment_pending'");
            $msg = 'approved';
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE shipments SET approval_status = 'rejected', status = 'cancelled' WHERE id = ?");
            $stmt->bind_param("i", $shipment_id);
            $stmt->execute();
            $stmt->close();
            $conn->query("UPDATE notifications SET is_read = 1 WHERE ref_id = $shipment_id AND type = 'new_shipment_pending'");
            $msg = 'rejected';
        }
    }
    header("Location: pending.php?msg=" . ($msg ?? ''));
    exit();
}

// Lấy danh sách lô hàng chờ duyệt
$pending = $conn->query("
    SELECT s.*, c.company_name, c.short_name AS customer_short,
           a.full_name AS creator_name,
           sup.supplier_name,
           COALESCE(sc.total_cost, 0) AS total_cost
    FROM shipments s
    LEFT JOIN customers c  ON s.customer_id = c.id
    LEFT JOIN accounts a   ON s.created_by  = a.id
    LEFT JOIN suppliers sup ON sup.id = a.supplier_id
    LEFT JOIN (
        SELECT shipment_id, SUM(total_amount) AS total_cost 
        FROM shipment_costs GROUP BY shipment_id
    ) sc ON sc.shipment_id = s.id
    WHERE s.approval_status = 'pending_approval'
      AND s.deleted_at IS NULL
    ORDER BY s.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$unread = getUnreadNotificationCount($conn);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lô hàng chờ duyệt - Forwarder System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<?php include '../partials/navbar.php'; ?>

<div class="container-fluid mt-3 pb-5">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">
            <i class="bi bi-hourglass-split text-warning"></i> Lô hàng chờ duyệt
            <?php if (count($pending) > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?php echo count($pending); ?></span>
            <?php endif; ?>
        </h4>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Danh sách lô hàng
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-<?php echo $_GET['msg'] === 'approved' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="bi bi-<?php echo $_GET['msg'] === 'approved' ? 'check-circle' : 'x-circle'; ?>-fill"></i>
        <?php echo $_GET['msg'] === 'approved' ? 'Đã duyệt lô hàng thành công!' : 'Đã từ chối lô hàng!'; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (empty($pending)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>
                <p class="mt-2 text-muted fs-5">Không có lô hàng nào đang chờ duyệt!</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Job No</th>
                                <th>Nhà cung cấp</th>
                                <th>Khách hàng</th>
                                <th>HAWB / MAWB</th>
                                <th>Ngày đến</th>
                                <th class="text-end">Tổng Cost</th>
                                <th>Ngày tạo</th>
                                <th style="min-width:160px;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pending as $i => $row): ?>
                            <tr>
                                <td class="text-muted"><?php echo $i + 1; ?></td>
                                <td>
                                    <a href="view.php?id=<?php echo $row['id']; ?>" class="fw-bold text-primary text-decoration-none">
                                        <?php echo htmlspecialchars($row['job_no']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark">
                                        <?php echo htmlspecialchars($row['supplier_name'] ?? $row['creator_name'] ?? '—'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark"><?php echo htmlspecialchars($row['customer_short'] ?? ''); ?></span><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['company_name'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <small>
                                        <strong>H:</strong> <?php echo htmlspecialchars($row['hawb'] ?? '—'); ?><br>
                                        <strong>M:</strong> <?php echo htmlspecialchars($row['mawb'] ?? '—'); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo $row['arrival_date']
                                        ? date('d/m/Y', strtotime($row['arrival_date']))
                                        : '<span class="text-muted">—</span>'; ?>
                                </td>
                                <td class="text-end text-danger fw-bold">
                                    <?php echo number_format($row['total_cost'], 0, ',', '.'); ?>
                                </td>
                                <td>
                                    <small><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="view.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-info btn-sm" title="Xem chi tiết">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <form method="POST" class="d-inline" 
                                              onsubmit="return confirm('Duyệt lô hàng <?php echo htmlspecialchars($row['job_no']); ?>?')">
                                            <input type="hidden" name="shipment_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success btn-sm" title="Duyệt">
                                                <i class="bi bi-check-circle-fill"></i> Duyệt
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline"
                                              onsubmit="return confirm('Từ chối lô hàng <?php echo htmlspecialchars($row['job_no']); ?>?')">
                                            <input type="hidden" name="shipment_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Từ chối">
                                                <i class="bi bi-x-circle-fill"></i> Từ chối
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>