<?php
/**
 * Helper functions cho phân quyền Role-Based Access Control
 * Roles: admin | staff | supplier
 */

function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isStaff(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
}

function isSupplier(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'supplier';
}

function isAdminOrStaff(): bool {
    return isAdmin() || isStaff();
}

/**
 * Chặn supplier truy cập trang — redirect về shipments/index.php
 */
function requireNotSupplier(): void {
    if (isSupplier()) {
        header("Location: /forwarder/shipments/index.php?error=no_permission");
        exit();
    }
}

/**
 * Lấy supplier_id của user đang login (chỉ dùng khi role=supplier)
 */
function getMySupplierID(): int {
    return intval($_SESSION['supplier_id'] ?? 0);
}

/**
 * Lấy số thông báo chưa đọc (dùng trong navbar)
 */
function getUnreadNotificationCount($conn): int {
    if (isSupplier()) return 0;
    $r = $conn->query("SELECT COUNT(*) c FROM notifications WHERE is_read = 0");
    return intval($r->fetch_assoc()['c'] ?? 0);
}