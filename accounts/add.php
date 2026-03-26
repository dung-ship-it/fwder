<?php
require_once '../config/database.php';
checkLogin();
checkAdmin();

$conn = getDBConnection();

$sup_list             = $conn->query("SELECT id, supplier_name, short_name FROM suppliers WHERE status='active' ORDER BY short_name");
$suppliers_for_select = $sup_list->fetch_all(MYSQLI_ASSOC);

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username         = trim($_POST['username']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name        = trim($_POST['full_name']);
    $email            = trim($_POST['email']);
    $role             = $_POST['role'];
    $status           = $_POST['status'];
    $supplier_id      = ($role === 'supplier' && !empty($_POST['supplier_id']))
                        ? intval($_POST['supplier_id'])
                        : 0;

    if (empty($username) || empty($password) || empty($full_name)) {
        $error = 'Vui lòng nhập đầy đủ thông tin bắt buộc!';
    } elseif ($password !== $confirm_password) {
        $error = 'Mật khẩu xác nhận không khớp!';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự!';
    } elseif ($role === 'supplier' && $supplier_id === 0) {
        $error = 'Vui lòng chọn Nhà cung cấp cho tài khoản Supplier!';
    } else {
        // Kiểm tra username trùng
        $stmt = $conn->prepare("SELECT id FROM accounts WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute(); // ✅ chỉ gọi 1 lần

        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Tên đăng nhập đã tồn tại!';
            $stmt->close();
        } else {
            $stmt->close();

            $created_by = intval($_SESSION['user_id']);

            $stmt = $conn->prepare("INSERT INTO accounts 
                (username, password, full_name, email, role, status, created_by, supplier_id) 
                VALUES (?, MD5(?), ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssii",
                $username, $password, $full_name, $email, $role, $status,
                $created_by, $supplier_id);

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: index.php?success=added");
                exit();
            } else {
                $error = 'Có lỗi xảy ra: ' . $stmt->error;
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Tài khoản - Forwarder System</title>
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
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-plus-circle"></i> Thêm Tài khoản mới
                        </h5>
                    </div>
                    <div class="card-body">

                        <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    Tên đăng nhập <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="username" class="form-control" required
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                                <small class="text-muted">Chỉ dùng chữ cái, số và dấu gạch dưới</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    Họ và tên <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="full_name" class="form-control" required
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Email</label>
                                <input type="email" name="email" class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    Mật khẩu <span class="text-danger">*</span>
                                </label>
                                <input type="password" name="password" class="form-control" required minlength="6">
                                <small class="text-muted">Tối thiểu 6 ký tự</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    Xác nhận mật khẩu <span class="text-danger">*</span>
                                </label>
                                <input type="password" name="confirm_password" class="form-control" required minlength="6">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Quyền</label>
                                <select name="role" id="roleSelect" class="form-select">
                                    <option value="user"
                                        <?php echo (($_POST['role'] ?? '') === 'user')     ? 'selected' : ''; ?>>
                                        User (Nhân viên)
                                    </option>
                                    <option value="admin"
                                        <?php echo (($_POST['role'] ?? '') === 'admin')    ? 'selected' : ''; ?>>
                                        Admin (Quản trị viên)
                                    </option>
                                    <option value="supplier"
                                        <?php echo (($_POST['role'] ?? '') === 'supplier') ? 'selected' : ''; ?>>
                                        🏭 Supplier (Nhà cung cấp)
                                    </option>
                                </select>
                            </div>

                            <div class="mb-3" id="supplierField" style="display:none;">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-building"></i> Nhà cung cấp
                                    <span class="text-danger">*</span>
                                </label>
                                <select name="supplier_id" id="supplierSelect" class="form-select">
                                    <option value="">-- Chọn nhà cung cấp --</option>
                                    <?php foreach ($suppliers_for_select as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"
                                        <?php echo (intval($_POST['supplier_id'] ?? 0) === $s['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['short_name'] . ' — ' . $s['supplier_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i>
                                    Tài khoản này sẽ đăng nhập thay mặt nhà cung cấp trên
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Trạng thái</label>
                                <select name="status" class="form-select">
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Khóa</option>
                                </select>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Quay lại
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Tạo tài khoản
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const roleSelect     = document.getElementById('roleSelect');
    const supplierField  = document.getElementById('supplierField');
    const supplierSelect = document.getElementById('supplierSelect');

    function toggleSupplierField() {
        if (roleSelect.value === 'supplier') {
            supplierField.style.display = 'block';
            supplierSelect.required     = true;
        } else {
            supplierField.style.display = 'none';
            supplierSelect.required     = false;
            supplierSelect.value        = '';
        }
    }

    roleSelect.addEventListener('change', toggleSupplierField);
    toggleSupplierField();
    </script>
</body>
</html>
