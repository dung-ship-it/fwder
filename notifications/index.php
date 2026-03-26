<?php
require_once '../config/database.php';
checkLogin();
requireNotSupplier();

$conn = getDBConnection();

// Đánh dấu tất cả đã đọc khi vào trang
$conn->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");

// Lấy danh sách thông báo
$notifications = $conn->query("
    SELECT n.*, s.job_no, s.approval_status,
           a.full_name AS creator_name,
           sup.supplier_name
    FROM notifications n
    LEFT JOIN shipments s ON n.ref_id = s.id
    LEFT JOIN accounts a  ON s.created_by = a.id
    LEFT JOIN accounts sa ON sa.supplier_id = (
        SELECT supplier_id FROM accounts WHERE id = s.created_by LIMIT 1
    )
    LEFT JOIN suppliers sup ON sup.id = (
        SELECT supplier_id FROM accounts WHERE id = s.created_by LIMIT 1
    )
    ORDER BY n.created_at DESC
    LIMIT 100
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông báo - Forwarder System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<?php include '../partials/navbar.php'; ?>

<div class="container mt-4 pb-5">
    <h4 class="mb-3"><i class="bi bi-bell-fill text-warning"></i> Thông báo hệ thống</h4>

    <?php if (empty($notifications)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Không có thông báo nào.
        </div>
    <?php else: ?>
        <div class="list-group">
        <?php foreach ($notifications as $n): ?>
            <div class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <i class="bi bi-box text-warning me-1"></i>
                        <strong><?php echo htmlspecialchars($n['message']); ?></strong>
                        <?php if ($n['ref_id'] && $n['job_no']): ?>
                            — <a href="../shipments/view.php?id=<?php echo $n['ref_id']; ?>" class="fw-bold text-primary">
                                <?php echo htmlspecialchars($n['job_no']); ?>
                            </a>
                            <?php if ($n['approval_status'] === 'pending_approval'): ?>
                                <span class="badge bg-warning text-dark ms-1">Chờ duyệt</span>
                                <a href="../shipments/pending.php" class="btn btn-sm btn-warning ms-2">
                                    <i class="bi bi-check-circle"></i> Duyệt ngay
                                </a>
                            <?php else: ?>
                                <span class="badge bg-success ms-1">Đã duyệt</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted ms-3 text-nowrap">
                        <?php echo date('d/m/Y H:i', strtotime($n['created_at'])); ?>
                    </small>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>