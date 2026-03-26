<?php
require_once '../config/database.php';
checkLogin();

$conn        = getDBConnection();
$_is_supplier = isSupplier();
$_my_sup_id  = getMySupplierID();

$auto_job_no = generateJobNo($conn);
$suppliers   = $conn->query("SELECT id, supplier_name, short_name FROM suppliers WHERE status='active' ORDER BY short_name");
$cost_codes  = $conn->query("SELECT id, code, description FROM cost_codes WHERE status='active' ORDER BY code");
$error       = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $job_no                 = $auto_job_no;
    $customer_id            = intval($_POST['customer_id']);
    $mawb                   = trim($_POST['mawb']);
    $hawb                   = trim($_POST['hawb']);
    $customs_declaration_no = trim($_POST['customs_declaration_no']);
    $shipper                = trim($_POST['shipper']);
    $cnee                   = trim($_POST['cnee']);
    $vessel_flight          = trim($_POST['vessel_flight']);
    $pol                    = strtoupper(trim($_POST['pol']));
    $pod                    = strtoupper(trim($_POST['pod']));
    $packages               = intval($_POST['packages']);
    $gw                     = floatval($_POST['gw']);
    $cw                     = floatval($_POST['cw']);
    $warehouse              = trim($_POST['warehouse']);
    $cont_seal              = trim($_POST['cont_seal']);
    $arrival_date           = $_POST['arrival_date'];
    $status                 = $_POST['status'];
    $notes                  = trim($_POST['notes']);
    $costs                  = isset($_POST['costs']) ? $_POST['costs'] : [];
    $sells                  = !$_is_supplier && isset($_POST['sells']) ? $_POST['sells'] : [];

    // Supplier: approval_status = pending_approval
    $approval_status = $_is_supplier ? 'pending_approval' : 'approved';

    if ($customer_id == 0) {
        $error = 'Vui lòng chọn Khách hàng!';
    } elseif (empty($hawb)) {
        $error = 'HAWB là bắt buộc!';
    } elseif (empty($mawb)) {
        $error = 'MAWB là bắt buộc!';
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                INSERT INTO shipments
                    (job_no, customer_id, mawb, hawb, customs_declaration_no,
                     shipper, cnee, vessel_flight, pol, pod,
                     packages, gw, cw, warehouse, cont_seal,
                     arrival_date, status, notes, is_locked, created_by, approval_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'no', ?, ?)
            ");
            $stmt->bind_param(
                "sissssssssiddsssssis",
                $job_no, $customer_id, $mawb, $hawb, $customs_declaration_no,
                $shipper, $cnee, $vessel_flight, $pol, $pod,
                $packages, $gw, $cw, $warehouse, $cont_seal,
                $arrival_date, $status, $notes,
                $_SESSION['user_id'], $approval_status
            );
            $stmt->execute();
            $shipment_id = $conn->insert_id;

            // Costs
            if (!empty($costs)) {
                $stmt_cost = $conn->prepare("
                    INSERT INTO shipment_costs
                        (shipment_id, cost_code_id, quantity, unit_price, vat, total_amount, supplier_id, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($costs as $cost) {
                    if (empty($cost['cost_code_id'])) continue;
                    $cc_id  = intval($cost['cost_code_id']);
                    $qty    = floatval($cost['quantity']);
                    $price  = floatval($cost['unit_price']);
                    $vat    = floatval($cost['vat']);
                    $total  = $qty * $price * (1 + $vat / 100);
                    // Supplier chỉ được nhập cost của chính mình
                    $sup_id = $_is_supplier ? $_my_sup_id : (!empty($cost['supplier_id']) ? intval($cost['supplier_id']) : null);
                    $note_c = trim($cost['notes'] ?? '');
                    $stmt_cost->bind_param("iiddddisi", $shipment_id, $cc_id, $qty, $price, $vat, $total, $sup_id, $note_c, $_SESSION['user_id']);
                    $stmt_cost->execute();
                }
            }

            // Sells — chỉ admin/staff
            if (!$_is_supplier && !empty($sells)) {
                $stmt_sell = $conn->prepare("
                    INSERT INTO shipment_sells
                        (shipment_id, cost_code_id, quantity, unit_price, vat, total_amount, is_pob, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($sells as $sell) {
                    if (empty($sell['cost_code_id'])) continue;
                    $cc_id  = intval($sell['cost_code_id']);
                    $qty    = floatval($sell['quantity']);
                    $price  = floatval($sell['unit_price']);
                    $vat    = floatval($sell['vat']);
                    $total  = $qty * $price * (1 + $vat / 100);
                    $is_pob = isset($sell['is_pob']) ? 1 : 0;
                    $note_s = trim($sell['notes'] ?? '');
                    $stmt_sell->bind_param("iidddidsi", $shipment_id, $cc_id, $qty, $price, $vat, $total, $is_pob, $note_s, $_SESSION['user_id']);
                    $stmt_sell->execute();
                }
            }

            // Tạo notification nếu supplier
            if ($_is_supplier) {
                $msg  = "NCC " . ($_SESSION['supplier_name'] ?? $_SESSION['full_name']) . " đã tạo lô hàng mới: $job_no";
                $stmt_notif = $conn->prepare("INSERT INTO notifications (type, message, ref_id) VALUES ('new_shipment_pending', ?, ?)");
                $stmt_notif->bind_param("si", $msg, $shipment_id);
                $stmt_notif->execute();
                $stmt_notif->close();
            }

            $conn->commit();
            header("Location: index.php?success=added");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Có lỗi xảy ra: ' . $e->getMessage();
        }
    }
}

$cost_codes_json = [];
$cost_codes->data_seek(0);
while ($cc = $cost_codes->fetch_assoc()) {
    $cost_codes_json[$cc['id']] = ['code' => $cc['code'], 'description' => $cc['description']];
}
$suppliers_json = [];
$suppliers->data_seek(0);
while ($s = $suppliers->fetch_assoc()) {
    $suppliers_json[$s['id']] = ['short_name' => $s['short_name'], 'supplier_name' => $s['supplier_name']];
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Lô hàng - Forwarder System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .section-header { background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:8px 15px; border-radius:5px; margin-bottom:15px; margin-top:20px; }
        .section-header.green  { background:linear-gradient(135deg,#11998e,#38ef7d); color:#222; }
        .section-header.orange { background:linear-gradient(135deg,#f7971e,#ffd200); color:#333; }
        .section-header.red    { background:linear-gradient(135deg,#eb3349,#f45c43); }
        .cost-row, .sell-row { background:#f8f9fa; border:1px solid #dee2e6; border-radius:6px; padding:10px; margin-bottom:8px; }
        .sell-row.is-pob { background:#fffbeb !important; border-color:#fcd34d !important; }
        .pob-check-wrap { background:#fff3cd; border:1px solid #ffc107; border-radius:6px; padding:4px 10px; display:inline-flex; align-items:center; gap:6px; cursor:pointer; }
    </style>
</head>
<body class="bg-light">

<?php include '../partials/navbar.php'; ?>

<div class="container-fluid mt-4 pb-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="bi bi-plus-circle"></i> Thêm Lô hàng mới
                <?php if ($_is_supplier): ?>
                    <span class="badge bg-warning text-dark ms-2">
                        <i class="bi bi-hourglass-split"></i> Sẽ chờ Admin duyệt
                    </span>
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body">

            <?php if ($_is_supplier): ?>
            <div class="alert alert-warning py-2">
                <i class="bi bi-info-circle-fill"></i>
                <strong>Lưu ý:</strong> Lô hàng bạn tạo sẽ vào trạng thái <strong>Chờ duyệt</strong>.
                Admin/Staff sẽ xem xét và duyệt để hiển thị chính thức.
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" id="shipmentForm">

                <div class="section-header"><i class="bi bi-info-circle"></i> Thông tin cơ bản</div>
                <div class="row">
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Job No <span class="text-muted small">(Tự động)</span></label>
                        <input type="text" class="form-control bg-light fw-bold text-primary"
                               value="<?php echo htmlspecialchars($auto_job_no); ?>" readonly>
                    </div>
                    <?php if (!$_is_supplier): ?>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Mã KH <span class="text-danger">*</span></label>
                        <input type="text" id="customerCode" class="form-control text-uppercase" placeholder="VD: ABC" autocomplete="off">
                        <input type="hidden" name="customer_id" id="customerId">
                        <small class="text-muted">Nhập mã để tự động điền</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Khách hàng</label>
                        <input type="text" id="customerName" class="form-control bg-light" readonly placeholder="Tên KH tự động...">
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="customer_id" id="customerId" value="0">
                    <?php endif; ?>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="pending">Chờ xử lý</option>
                            <option value="in_transit">Đang vận chuyển</option>
                            <option value="arrived">Đã đến</option>
                            <option value="cleared">Đã thông quan</option>
                            <option value="delivered">Đã giao</option>
                        </select>
                    </div>
                </div>

                <div class="section-header green"><i class="bi bi-file-earmark-text"></i> Thông tin vận đơn</div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">MAWB <span class="text-danger">*</span></label>
                        <input type="text" name="mawb" class="form-control" required placeholder="Nhập số MAWB">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">HAWB <span class="text-danger">*</span></label>
                        <input type="text" name="hawb" class="form-control" required placeholder="Nhập số HAWB">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Số tờ khai</label>
                        <input type="text" name="customs_declaration_no" class="form-control" placeholder="Số tờ khai hải quan">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Ngày hàng đến</label>
                        <input type="date" name="arrival_date" class="form-control">
                    </div>
                </div>

                <div class="section-header orange"><i class="bi bi-box-seam"></i> Thông tin hàng hóa</div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Shipper</label>
                        <input type="text" name="shipper" class="form-control" placeholder="Tên người gửi">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">CNEE</label>
                        <input type="text" name="cnee" class="form-control" placeholder="Tên người nhận">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">VSL / FLIGHT</label>
                        <input type="text" name="vessel_flight" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Kho hàng</label>
                        <input type="text" name="warehouse" class="form-control">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">POL</label>
                        <input type="text" name="pol" class="form-control text-uppercase">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">POD</label>
                        <input type="text" name="pod" class="form-control text-uppercase">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Số kiện</label>
                        <input type="number" name="packages" class="form-control" min="0" value="0">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">GW (kg)</label>
                        <input type="number" step="0.01" name="gw" class="form-control" min="0" value="0">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">CW / CBM</label>
                        <input type="number" step="0.01" name="cw" class="form-control" min="0" value="0">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Cont / Seal</label>
                        <input type="text" name="cont_seal" class="form-control">
                    </div>
                </div>

                <!-- COST — tất cả đều thấy -->
                <div class="section-header red">
                    <i class="bi bi-cash-stack"></i> Chi phí đầu vào (COST)
                    <button type="button" class="btn btn-sm btn-light float-end" onclick="addCostRow()">
                        <i class="bi bi-plus-circle"></i> Thêm dòng
                    </button>
                </div>
                <div id="costRows">
                    <p class="text-muted text-center py-2" id="noCostMsg">
                        <i class="bi bi-info-circle"></i> Chưa có chi phí. Click "Thêm dòng" để thêm.
                    </p>
                </div>

                <!-- SELL — chỉ admin/staff -->
                <?php if (!$_is_supplier): ?>
                <div class="section-header green">
                    <i class="bi bi-currency-dollar"></i> Doanh thu bán ra (SELL)
                    <button type="button" class="btn btn-sm btn-light float-end" onclick="addSellRow()">
                        <i class="bi bi-plus-circle"></i> Thêm dòng
                    </button>
                </div>
                <div id="sellRows">
                    <p class="text-muted text-center py-2" id="noSellMsg">
                        <i class="bi bi-info-circle"></i> Chưa có doanh thu. Click "Thêm dòng" để thêm.
                    </p>
                </div>
                <?php endif; ?>

                <div class="section-header"><i class="bi bi-chat-left-text"></i> Ghi chú</div>
                <div class="mb-3">
                    <textarea name="notes" class="form-control" rows="2" placeholder="Ghi chú thêm..."></textarea>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Quay lại</a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i>
                        <?php echo $_is_supplier ? 'Gửi để duyệt' : 'Lưu lô hàng'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const costCodes     = <?php echo json_encode($cost_codes_json); ?>;
const suppliers     = <?php echo json_encode($suppliers_json); ?>;
const IS_SUPPLIER   = <?php echo $_is_supplier ? 'true' : 'false'; ?>;
const MY_SUPPLIER_ID= <?php echo $_my_sup_id; ?>;
let costRowIndex = 0;
let sellRowIndex = 0;

<?php if (!$_is_supplier): ?>
document.getElementById('customerCode').addEventListener('blur', function () {
    const code = this.value.trim().toUpperCase();
    if (!code) return;
    fetch('../api/get_customer.php?short_name=' + encodeURIComponent(code))
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('customerId').value   = data.id;
                document.getElementById('customerName').value = data.company_name;
                this.value = data.short_name;
            } else {
                alert('Không tìm thấy khách hàng: ' + code);
                document.getElementById('customerId').value = '';
                document.getElementById('customerName').value = '';
                this.value = '';
                this.focus();
            }
        });
});
document.getElementById('customerCode').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
});
<?php endif; ?>

function addCostRow() {
    document.getElementById('noCostMsg')?.remove();
    // Nếu là supplier: chỉ hiện NCC của chính họ (readonly)
    const supField = IS_SUPPLIER
        ? `<input type="hidden" name="costs[${costRowIndex}][supplier_id]" value="${MY_SUPPLIER_ID}">
           <div class="form-control form-control-sm bg-light text-muted"><?php echo htmlspecialchars($_SESSION['supplier_name'] ?? 'NCC của tôi'); ?></div>`
        : `<select name="costs[${costRowIndex}][supplier_id]" class="form-select form-select-sm">
               <option value="">--</option>
               ${Object.keys(suppliers).map(id => `<option value="${id}">${suppliers[id].short_name}</option>`).join('')}
           </select>`;

    const ccOpts = Object.keys(costCodes).map(id => `<option value="${id}">${costCodes[id].code}</option>`).join('');
    const i = costRowIndex;

    document.getElementById('costRows').insertAdjacentHTML('beforeend', `
    <div class="cost-row" id="cost-row-${i}">
        <div class="row align-items-end g-2">
            <div class="col-md-2"><label class="form-label small mb-1">Mã chi phí</label>
                <select name="costs[${i}][cost_code_id]" class="form-select form-select-sm" onchange="updateCostDesc(${i})">
                    <option value="">-- Chọn --</option>${ccOpts}
                </select>
            </div>
            <div class="col-md-2"><label class="form-label small mb-1">Nội dung</label>
                <input type="text" id="cost-desc-${i}" class="form-control form-control-sm bg-light" readonly placeholder="Tự động">
            </div>
            <div class="col-md-1"><label class="form-label small mb-1">SL</label>
                <input type="number" name="costs[${i}][quantity]" class="form-control form-control-sm" value="1" step="0.01" min="0" oninput="calcCostTotal(${i})">
            </div>
            <div class="col-md-2"><label class="form-label small mb-1">Đơn giá</label>
                <input type="number" name="costs[${i}][unit_price]" class="form-control form-control-sm" value="0" step="0.01" min="0" oninput="calcCostTotal(${i})">
            </div>
            <div class="col-md-1"><label class="form-label small mb-1">VAT%</label>
                <input type="number" name="costs[${i}][vat]" class="form-control form-control-sm" value="0" step="0.1" min="0" oninput="calcCostTotal(${i})">
            </div>
            <div class="col-md-2"><label class="form-label small mb-1">Thành tiền</label>
                <input type="text" id="cost-total-${i}" class="form-control form-control-sm bg-light text-danger fw-bold" readonly value="0">
            </div>
            <div class="col-md-1"><label class="form-label small mb-1">NCC</label>${supField}</div>
            <div class="col-md-1"><label class="form-label small mb-1">Ghi chú</label>
                <input type="text" name="costs[${i}][notes]" class="form-control form-control-sm">
            </div>
            <div class="col-md-auto"><label class="form-label small mb-1 d-block">&nbsp;</label>
                <button type="button" class="btn btn-sm btn-danger" onclick="document.getElementById('cost-row-${i}').remove()">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    </div>`);
    costRowIndex++;
}

function updateCostDesc(i) {
    const sel = document.querySelector(`select[name="costs[${i}][cost_code_id]"]`);
    const desc = document.getElementById(`cost-desc-${i}`);
    desc.value = sel.value && costCodes[sel.value] ? costCodes[sel.value].description : '';
}

function calcCostTotal(i) {
    const qty   = parseFloat(document.querySelector(`input[name="costs[${i}][quantity]"]`).value)   || 0;
    const price = parseFloat(document.querySelector(`input[name="costs[${i}][unit_price]"]`).value) || 0;
    const vat   = parseFloat(document.querySelector(`input[name="costs[${i}][vat]"]`).value)        || 0;
    document.getElementById(`cost-total-${i}`).value = (qty * price * (1 + vat / 100)).toLocaleString('vi-VN');
}

<?php if (!$_is_supplier): ?>
function addSellRow() {
    document.getElementById('noSellMsg')?.remove();
    const ccOpts = Object.keys(costCodes).map(id => `<option value="${id}">${costCodes[id].code}</option>`).join('');
    const i = sellRowIndex;
    document.getElementById('sellRows').insertAdjacentHTML('beforeend', `
    <div class="sell-row" id="sell-row-${i}">
        <div class="row align-items-end g-2">
            <div class="col-md-2"><label class="form-label small mb-1">Mã chi phí</label>
                <select name="sells[${i}][cost_code_id]" class="form-select form-select-sm" onchange="updateSellDesc(${i})">
                    <option value="">-- Chọn --</option>${ccOpts}
                </select>
            </div>
            <div class="col-md-2"><label class="form-label small mb-1">Nội dung</label>
                <input type="text" id="sell-desc-${i}" class="form-control form-control-sm bg-light" readonly>
            </div>
            <div class="col-md-1"><label class="form-label small mb-1">SL</label>
                <input type="number" name="sells[${i}][quantity]" class="form-control form-control-sm" value="1" step="0.01" min="0" oninput="calcSellTotal(${i})">
            </div>
            <div class="col-md-2"><label class="form-label small mb-1">Đơn giá</label>
                <input type="number" name="sells[${i}][unit_price]" class="form-control form-control-sm" value="0" step="0.01" min="0" oninput="calcSellTotal(${i})">
            </div>
            <div class="col-md-1"><label class="form-label small mb-1">VAT%</label>
                <input type="number" name="sells[${i}][vat]" class="form-control form-control-sm" value="0" step="0.1" min="0" oninput="calcSellTotal(${i})">
            </div>
            <div class="col-md-2"><label class="form-label small mb-1">Thành tiền</label>
                <input type="text" id="sell-total-${i}" class="form-control form-control-sm bg-light text-success fw-bold" readonly value="0">
            </div>
            <div class="col-md-1"><label class="form-label small mb-1">Ghi chú</label>
                <input type="text" name="sells[${i}][notes]" class="form-control form-control-sm">
            </div>
            <div class="col-md-auto"><label class="form-label small mb-1 d-block">&nbsp;</label>
                <div class="pob-check-wrap">
                    <input type="checkbox" name="sells[${i}][is_pob]" value="1" id="sell-pob-${i}" class="form-check-input mt-0" onchange="togglePob(${i})">
                    <label for="sell-pob-${i}" class="small fw-bold mb-0" style="cursor:pointer;color:#92400e;"><i class="bi bi-arrow-left-right"></i> Chi hộ</label>
                </div>
            </div>
            <div class="col-md-auto"><label class="form-label small mb-1 d-block">&nbsp;</label>
                <button type="button" class="btn btn-sm btn-danger" onclick="document.getElementById('sell-row-${i}').remove()">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    </div>`);
    sellRowIndex++;
}
function updateSellDesc(i) {
    const sel = document.querySelector(`select[name="sells[${i}][cost_code_id]"]`);
    const desc = document.getElementById(`sell-desc-${i}`);
    desc.value = sel.value && costCodes[sel.value] ? costCodes[sel.value].description : '';
}
function calcSellTotal(i) {
    const qty   = parseFloat(document.querySelector(`input[name="sells[${i}][quantity]"]`).value)   || 0;
    const price = parseFloat(document.querySelector(`input[name="sells[${i}][unit_price]"]`).value) || 0;
    const vat   = parseFloat(document.querySelector(`input[name="sells[${i}][vat]"]`).value)        || 0;
    document.getElementById(`sell-total-${i}`).value = (qty * price * (1 + vat / 100)).toLocaleString('vi-VN');
}
function togglePob(i) {
    const cb  = document.getElementById(`sell-pob-${i}`);
    const row = document.getElementById(`sell-row-${i}`);
    if (cb && row) row.classList.toggle('is-pob', cb.checked);
}
<?php endif; ?>

document.getElementById('shipmentForm').addEventListener('submit', function(e) {
    <?php if (!$_is_supplier): ?>
    if (!document.getElementById('customerId').value) {
        e.preventDefault();
        alert('Vui lòng chọn khách hàng hợp lệ!');
        document.getElementById('customerCode').focus();
    }
    <?php endif; ?>
});
</script>
</body>
</html>