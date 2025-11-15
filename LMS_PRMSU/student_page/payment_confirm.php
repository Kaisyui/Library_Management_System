<?php
session_start();
include '../include/db_connect.php';
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'student' || !isset($_SESSION['user_id'])){
    header('Location: ../index.php'); exit();
}
$student_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['payment_id'])) {
    header('Location: student_balance.php?error=bad_request'); exit();
}

$payment_id = (int)$_POST['payment_id'];

$stmt = $conn->prepare("SELECT payment_id, student_id, borrow_id, amount, method FROM payments WHERE payment_id = ?");
$stmt->bind_param('i', $payment_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment || (int)$payment['student_id'] !== (int)$student_id) {
    header('Location: student_balance.php?error=notfound'); exit();
}

$borrow_id = (int)$payment['borrow_id'];
$amount = (float)$payment['amount'];
$transaction_ref = isset($_POST['transaction_ref']) ? trim($_POST['transaction_ref']) : null;

// Mark the borrow record as paid by adding the payment amount and setting status to Paid.
// Also update the payments row with transaction_ref and paid_at when those columns exist.
$conn->begin_transaction();
try {
    // fetch current payment amount on borrow
    $stmt = $conn->prepare("SELECT payment FROM borrow_records WHERE borrow_id = ?");
    $stmt->bind_param('i', $borrow_id);
    $stmt->execute();
    $br = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $current = $br ? (float)$br['payment'] : 0.0;
    $new_total = $current + $amount;

    $new_status = ($new_total > 0) ? 'Paid' : 'Unpaid';
    $stmt = $conn->prepare("UPDATE borrow_records SET payment = ?, payment_status = ? WHERE borrow_id = ?");
    $stmt->bind_param('dsi', $new_total, $new_status, $borrow_id);
    $stmt->execute();
    $stmt->close();

    // Update payments table: set transaction_ref and paid_at if those columns exist
    $has_tx_ref = false;
    $has_paid_at = false;
    $has_status_col = false;

    $schemaStmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME IN ('transaction_ref','paid_at','status')");
    $schemaStmt->execute();
    $colsRes = $schemaStmt->get_result();
    while ($c = $colsRes->fetch_assoc()) {
        if ($c['COLUMN_NAME'] === 'transaction_ref') $has_tx_ref = true;
        if ($c['COLUMN_NAME'] === 'paid_at') $has_paid_at = true;
        if ($c['COLUMN_NAME'] === 'status') $has_status_col = true;
    }
    $schemaStmt->close();

    // build dynamic update depending on available columns
    $updateParts = [];
    $params = [];
    $types = '';
    if ($has_tx_ref && $transaction_ref !== null) {
        $updateParts[] = 'transaction_ref = ?';
        $params[] = $transaction_ref;
        $types .= 's';
    }
    if ($has_paid_at) {
        $updateParts[] = 'paid_at = NOW()';
        // no param for NOW()
    }
    if ($has_status_col) {
        $updateParts[] = "status = 'Completed'";
    }

    if (!empty($updateParts)) {
        $sql = 'UPDATE payments SET ' . implode(', ', $updateParts) . ' WHERE payment_id = ?';
        $params[] = $payment_id;
        $types .= 'i';
        $stmt = $conn->prepare($sql);
        // bind params dynamically
        if ($types !== '') {
            $bindNames = [];
            $bindNames[] = & $types;
            for ($i = 0; $i < count($params); $i++) {
                $bindNames[] = & $params[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $bindNames);
        }
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    header('Location: student_balance.php?success=paid_online');
    exit();
} catch (Exception $e) {
    $conn->rollback();
    header('Location: student_balance.php?error=fail');
    exit();
}
