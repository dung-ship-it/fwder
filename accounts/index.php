<?php
require_once '../config/database.php';
checkLogin();
checkAdmin();

$conn = getDBConnection();

// ✅ SỬA - JOIN với suppliers để lấy tên NCC
$sql = "SELECT a.*, s.supplier_name, s.short_name AS supplier_short
        FROM accounts a
        LEFT JOIN suppliers s ON a.supplier_id = s.id
        ORDER BY a.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Tài khoản - Forwarder System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="bi bi-box-seam"></i> Forwarder System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="../dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="../customers/index.php">Khách hàng</a></li>
                    <li class="nav-item"><a class="nav-link" href="../shipments/index.php">Lô hàng</a></li>
                    <li class="nav-item"><a class="nav-link" href="../suppliers/index.php">Nhà cung cấp</a></li>
                    <li class="nav-item"><a class="nav-link active" href="index.php">Tài khoản</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../logout.php">Đăng xuất</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-person-badge"></i> Quản lý Tài khoản</h2>
            <a href="add.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Thêm tài khoản
            </a>
        </div>

        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php
                if ($_GET['success'] == 'added')   echo '<i class="bi bi-check-circle"></i> Thêm tài khoản thành công!';
                if ($_GET['success'] == 'updated') echo '<i class="bi bi-check-circle"></i> Cập nhật tài khoản thành công!';
                if ($_GET['success'] == 'deleted') echo '<i class="bi bi-check-circle"></i> Xóa tài khoản thành công!';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>STT</th>
                                <th>Tên đăng nhập</th>
                                <th>Họ tên</th>
                                <th>Email</th>
                                <th>Quyền</th>
                                <th>Nhà cung cấp</th> <!-- ✅ THÊM -->
                                <th>Trạng thái</th>
                                <th>Ngày tạo</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0):
                                $stt = 1;
                                while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $stt++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                                <td>
                                    <?php if ($row['role'] == 'admin'): ?>
                                        <span class="badge bg-danger">Admin</span>
                                    <?php elseif ($row['role'] == 'supplier'): ?>
                                        <span class="badge bg-warning text-dark">🏭 Supplier</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">User</span>
                                    <?php endif; ?>
                                </td>
                                <!-- ✅ THÊM - cột Nhà cung cấp -->
                                <td>
                                    <?php if ($row['role'] == 'supplier' && $row['supplier_name']): ?>
                                        <span class="badge bg-info text-dark">
                                            <?php echo htmlspecialchars($row['supplier_short']); ?>
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($row['supplier_name']); ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] == 'active'): ?>
                                        <span class="badge bg-success">Hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Khóa</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <a href="edit.php?id=<?php echo $row['id']; ?>"
                                       class="btn btn-sm btn-warning" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                    <a href="delete.php?id=<?php echo $row['id']; ?>"
                                       class="btn btn-sm btn-danger" title="Xóa"
                                       onclick="return confirm('Xóa tài khoản <?php echo htmlspecialchars($row['username']); ?>?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="bi bi-inbox" style="font-size:3rem;color:#ccc;"></i>
                                    <p class="text-muted mt-2">Chưa có tài khoản nào</p>
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
</body>
</html>
<?php $conn->close(); ?>