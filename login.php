<?php
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT a.*, s.supplier_name 
            FROM accounts a
            LEFT JOIN suppliers s ON a.supplier_id = s.id
            WHERE a.username = ? AND a.status = 'active'
            LIMIT 1
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && $user['password'] === md5($password)) {
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['full_name']     = $user['full_name'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['supplier_id']   = $user['supplier_id'];   // null nếu không phải NCC
            $_SESSION['supplier_name'] = $user['supplier_name']; // null nếu không phải NCC

            // Redirect theo role
            if ($user['role'] === 'supplier') {
                header("Location: shipments/index.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng!';
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Forwarder System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg,#1e3c72,#2a5298); min-height:100vh; display:flex; align-items:center; }
        .login-card { border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.3); }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card login-card">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-box-seam text-primary" style="font-size:3rem;"></i>
                        <h4 class="mt-2 fw-bold">Forwarder System</h4>
                        <small class="text-muted">Đăng nhập để tiếp tục</small>
                    </div>
                    <?php if ($error): ?>
                    <div class="alert alert-danger py-2">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tên đăng nhập</label>
                            <input type="text" name="username" class="form-control" required autofocus
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Mật khẩu</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-box-arrow-in-right"></i> Đăng nhập
                        </button>
                    </form>
                </div>
            </div>
            <p class="text-center text-white-50 mt-3 small">&copy; <?php echo date('Y'); ?> Forwarder System</p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>