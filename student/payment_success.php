<?php
include_once '../includes/header.php';

// Check if user is student or faculty
if ($_SESSION['role'] != 'student' && $_SESSION['role'] != 'faculty') {
    header('Location: ../index.php');
    exit();
}

$receiptNumber = isset($_GET['receipt']) ? $_GET['receipt'] : '';
$transactionId = isset($_GET['transaction']) ? $_GET['transaction'] : '';

if (empty($receiptNumber) || empty($transactionId)) {
    header('Location: fines.php');
    exit();
}

// Get payment details
$stmt = $conn->prepare("
    SELECT p.*, f.amount, f.reason, b.title, b.author, u.name as user_name, u.email
    FROM payments p
    JOIN fines f ON p.fine_id = f.id
    JOIN issued_books ib ON f.issued_book_id = ib.id
    JOIN books b ON ib.book_id = b.id
    JOIN users u ON p.user_id = u.id
    WHERE p.receipt_number = ? AND p.transaction_id = ? AND p.user_id = ?
");
$stmt->bind_param("ssi", $receiptNumber, $transactionId, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: fines.php');
    exit();
}

$payment = $result->fetch_assoc();
$paymentDetails = json_decode($payment['payment_details'], true);
?>

<div class="container">
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1 class="success-title">Payment Successful!</h1>
        <p class="success-message">Your fine payment has been processed successfully.</p>
        
        <div class="receipt-container">
            <div class="receipt-header">
                <h2><i class="fas fa-receipt"></i> Payment Receipt</h2>
                <div class="receipt-number">Receipt #<?php echo $receiptNumber; ?></div>
            </div>
            
            <div class="receipt-body">
                <div class="receipt-section">
                    <h3>Transaction Details</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="label">Transaction ID:</span>
                            <span class="value"><?php echo $transactionId; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Payment Date:</span>
                            <span class="value"><?php echo date('F d, Y H:i:s', strtotime($payment['payment_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Payment Method:</span>
                            <span class="value"><?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Amount Paid:</span>
                            <span class="value amount">$<?php echo number_format($payment['amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="receipt-section">
                    <h3>Customer Details</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="label">Name:</span>
                            <span class="value"><?php echo htmlspecialchars($payment['user_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Email:</span>
                            <span class="value"><?php echo htmlspecialchars($payment['email']); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="receipt-section">
                    <h3>Fine Details</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="label">Book:</span>
                            <span class="value"><?php echo htmlspecialchars($payment['title']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Author:</span>
                            <span class="value"><?php echo htmlspecialchars($payment['author']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Fine Reason:</span>
                            <span class="value"><?php echo htmlspecialchars($payment['reason']); ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if ($paymentDetails): ?>
                <div class="receipt-section">
                    <h3>Payment Information</h3>
                    <div class="detail-grid">
                        <?php if (isset($paymentDetails['card_last_four'])): ?>
                        <div class="detail-item">
                            <span class="label">Card ending in:</span>
                            <span class="value">****<?php echo $paymentDetails['card_last_four']; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($paymentDetails['bank_account'])): ?>
                        <div class="detail-item">
                            <span class="label">Account ending in:</span>
                            <span class="value">****<?php echo $paymentDetails['bank_account']; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($paymentDetails['upi_id'])): ?>
                        <div class="detail-item">
                            <span class="label">UPI ID:</span>
                            <span class="value"><?php echo htmlspecialchars($paymentDetails['upi_id']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="receipt-footer">
                <p><strong>Status:</strong> <span class="status-paid">PAID</span></p>
                <p class="footer-note">
                    Thank you for your payment. This receipt serves as proof of payment. 
                    Please keep this for your records.
                </p>
            </div>
        </div>
        
        <div class="action-buttons">
            <button onclick="printReceipt()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <button onclick="downloadReceipt()" class="btn btn-primary">
                <i class="fas fa-download"></i> Download PDF
            </button>
            <a href="fines.php" class="btn btn-success">
                <i class="fas fa-list"></i> View All Fines
            </a>
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-home"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<style>
.success-container {
    max-width: 800px;
    margin: 0 auto;
    text-align: center;
}

.success-icon {
    font-size: 4em;
    color: var(--success-color);
    margin-bottom: 20px;
}

.success-title {
    color: var(--success-color);
    margin-bottom: 10px;
}

.success-message {
    font-size: 1.2em;
    color: var(--text-light);
    margin-bottom: 30px;
}

.receipt-container {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 30px;
    text-align: left;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.receipt-header {
    background: var(--primary-color);
    color: var(--white);
    padding: 20px;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.receipt-header h2 {
    margin: 0;
    font-size: 1.5em;
}

.receipt-number {
    font-size: 1.1em;
    font-weight: bold;
}

.receipt-body {
    padding: 30px;
}

.receipt-section {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--gray-200);
}

.receipt-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.receipt-section h3 {
    color: var(--primary-color);
    margin-bottom: 15px;
    font-size: 1.2em;
}

.detail-grid {
    display: grid;
    gap: 10px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
}

.label {
    font-weight: 500;
    color: var(--text-light);
}

.value {
    font-weight: 600;
    color: var(--text-color);
}

.value.amount {
    font-size: 1.2em;
    color: var(--success-color);
}

.receipt-footer {
    background: var(--gray-100);
    padding: 20px;
    border-radius: 0 0 var(--border-radius) var(--border-radius);
    text-align: center;
}

.status-paid {
    color: var(--success-color);
    font-weight: bold;
    background: rgba(40, 167, 69, 0.1);
    padding: 4px 12px;
    border-radius: 20px;
}

.footer-note {
    margin-top: 15px;
    font-size: 0.9em;
    color: var(--text-light);
    line-height: 1.5;
}

.action-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.action-buttons .btn {
    min-width: 150px;
}

@media (max-width: 768px) {
    .receipt-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .action-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .action-buttons .btn {
        width: 100%;
        max-width: 300px;
    }
}

@media print {
    .action-buttons {
        display: none;
    }
    
    .success-icon,
    .success-title,
    .success-message {
        display: none;
    }
    
    .receipt-container {
        box-shadow: none;
        border: 1px solid #000;
    }
}
</style>

<script>
function printReceipt() {
    window.print();
}

function downloadReceipt() {
    // In a real system, this would generate a PDF
    alert('PDF download functionality would be implemented here using libraries like jsPDF or server-side PDF generation.');
}

// Auto-redirect after 30 seconds
setTimeout(function() {
    if (confirm('Would you like to return to the dashboard?')) {
        window.location.href = 'dashboard.php';
    }
}, 30000);
</script>

<?php include_once '../includes/footer.php'; ?>