<?php
session_start();
include '../include/db_connect.php';
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'student' || !isset($_SESSION['user_id'])){
    header('Location: ../index.php'); exit();
}
$student_id = $_SESSION['user_id'];

$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
if ($payment_id <= 0) {
    header('Location: student_balance.php?error=bad_request'); exit();
}

$stmt = $conn->prepare("SELECT payment_id, student_id, borrow_id, amount, method FROM payments WHERE payment_id = ?");
$stmt->bind_param('i', $payment_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment || (int)$payment['student_id'] !== (int)$student_id) {
    header('Location: student_balance.php?error=notfound'); exit();
}

$provider = $payment['method'];
$amount = number_format((float)$payment['amount'], 2);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Checkout - <?php echo htmlspecialchars(strtoupper($provider)); ?></title>
    <link rel="stylesheet" href="../style.css">
    <style> .checkout { max-width:700px; margin:32px auto; padding:16px; border:1px solid #ddd; }</style>
</head>
<body>
<div class="checkout">
    <h2>Pay with <?php echo htmlspecialchars(strtoupper($provider)); ?></h2>
    <p>Amount: <strong><?php echo $amount; ?></strong></p>

    <?php if (in_array($provider, ['gcash','maya'])): ?>
        <h3>Instructions</h3>
        <p>Open your <?php echo htmlspecialchars(strtoupper($provider)); ?> app and send the amount to this number/account configured by your institution. Then click the button below and provide the transaction/reference code to complete the payment. (This is a local simulation — replace with real provider API integration later.)</p>

        <form method="POST" action="payment_confirm.php">
            <input type="hidden" name="payment_id" value="<?php echo (int)$payment['payment_id']; ?>">
            <label>Transaction Reference (from your wallet):<br>
                <input type="text" name="transaction_ref" required>
            </label>
            <br><br>
            <button type="submit" name="confirm">I have paid (Submit reference)</button>
        </form>

    <?php elseif ($provider === 'paypal'): ?>
        <h3>PayPal Checkout</h3>
        <p>To integrate real PayPal Checkout replace this page with the PayPal SDK flow that creates an order and redirects the student to PayPal. For now, you can simulate payment:</p>
        <form method="POST" action="payment_confirm.php">
            <input type="hidden" name="payment_id" value="<?php echo (int)$payment['payment_id']; ?>">
            <button type="submit" name="confirm">Simulate PayPal Payment (for testing)</button>
        </form>

    <?php else: ?>
        <p>Unknown provider. Please contact admin.</p>
    <?php endif; ?>

    <p><a href="student_balance.php">Back to Statement</a></p>
</div>
</body>
</html>
