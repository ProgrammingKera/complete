<?php
session_start();

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

include_once '../includes/header.php';

// Get payment details
$stmt = $conn->prepare("
    SELECT p.*, f.amount as fine_amount, f.reason, b.title, b.author, u.name as user_name, u.email
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
        <div class="success-animation">
            <div class="checkmark-circle">
                <div class="checkmark"></div>
            </div>
        </div>
        
        <h1 class="success-title">Payment Successful!</h1>
        <p class="success-message">Your fine payment has been processed successfully via Stripe.</p>
        
        <div class="receipt-container">
            <div class="receipt-header">
                <div class="receipt-logo">
                    <i class="fas fa-university"></i>
                    <h2>Library Management System</h2>
                </div>
                <div class="receipt-number">
                    <h3>Receipt #<?php echo htmlspecialchars($receiptNumber); ?></h3>
                    <p><?php echo date('F d, Y H:i:s', strtotime($payment['payment_date'])); ?></p>
                </div>
            </div>
            
            <div class="receipt-body">
                <div class="receipt-section">
                    <h3><i class="fas fa-credit-card"></i> Payment Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="label">Transaction ID:</span>
                            <span class="value"><?php echo htmlspecialchars($transactionId); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Payment Method:</span>
                            <span class="value">
                                <i class="fab fa-stripe"></i> Stripe (Credit Card)
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Amount Paid:</span>
                            <span class="value amount">PKR <?php echo number_format($payment['amount'], 2); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Payment Status:</span>
                            <span class="value status-paid">
                                <i class="fas fa-check-circle"></i> PAID
                            </span>
                        </div>
                        <?php if ($paymentDetails && isset($paymentDetails['created'])): ?>
                        <div class="detail-item">
                            <span class="label">Processed At:</span>
                            <span class="value"><?php echo date('M d, Y H:i:s', $paymentDetails['created']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="receipt-section">
                    <h3><i class="fas fa-user"></i> Customer Details</h3>
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
                    <h3><i class="fas fa-book"></i> Fine Details</h3>
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
                        <div class="detail-item">
                            <span class="label">Original Fine Amount:</span>
                            <span class="value">PKR <?php echo number_format($payment['fine_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="receipt-footer">
                <div class="security-badge">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secured by Stripe</span>
                </div>
                <p class="footer-note">
                    This receipt serves as proof of payment. Your transaction has been processed securely 
                    through Stripe's industry-leading payment platform. Please keep this for your records.
                </p>
                <?php if ($paymentDetails && isset($paymentDetails['stripe_payment_intent_id'])): ?>
                <p class="stripe-reference">
                    <small>Stripe Payment Intent: <?php echo htmlspecialchars($paymentDetails['stripe_payment_intent_id']); ?></small>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="action-buttons">
            <button onclick="printReceipt()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print Receipt
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

.success-animation {
    margin-bottom: 30px;
}

.checkmark-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--success-color);
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: scaleIn 0.5s ease-out;
}

.checkmark {
    width: 30px;
    height: 15px;
    border: 3px solid var(--white);
    border-top: none;
    border-right: none;
    transform: rotate(-45deg);
    animation: checkmarkDraw 0.3s ease-out 0.2s both;
}

@keyframes scaleIn {
    0% {
        transform: scale(0);
    }
    100% {
        transform: scale(1);
    }
}

@keyframes checkmarkDraw {
    0% {
        width: 0;
        height: 0;
    }
    100% {
        width: 30px;
        height: 15px;
    }
}

.success-title {
    color: var(--success-color);
    margin-bottom: 10px;
    font-size: 2.5em;
}

.success-message {
    font-size: 1.2em;
    color: var(--text-light);
    margin-bottom: 40px;
}

.receipt-container {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 30px;
    text-align: left;
    overflow: hidden;
}

.receipt-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: var(--white);
    padding: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.receipt-logo {
    display: flex;
    align-items: center;
    gap: 15px;
}

.receipt-logo i {
    font-size: 2em;
}

.receipt-logo h2 {
    margin: 0;
    font-size: 1.5em;
}

.receipt-number {
    text-align: right;
}

.receipt-number h3 {
    margin: 0;
    font-size: 1.2em;
}

.receipt-number p {
    margin: 5px 0 0;
    opacity: 0.9;
}

.receipt-body {
    padding: 30px;
}

.receipt-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--gray-200);
}

.receipt-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.receipt-section h3 {
    color: var(--primary-color);
    margin-bottom: 20px;
    font-size: 1.2em;
    display: flex;
    align-items: center;
    gap: 10px;
}

.detail-grid {
    display: grid;
    gap: 15px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
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
    font-size: 1.3em;
    color: var(--success-color);
}

.status-paid {
    color: var(--success-color);
    background: rgba(40, 167, 69, 0.1);
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.9em;
}

.receipt-footer {
    background: var(--gray-100);
    padding: 25px 30px;
    text-align: center;
}

.security-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 15px;
    color: var(--success-color);
    font-weight: 600;
}

.security-badge i {
    font-size: 1.2em;
}

.footer-note {
    margin: 0 0 10px 0;
    font-size: 0.9em;
    color: var(--text-light);
    line-height: 1.6;
}

.stripe-reference {
    margin: 0;
    font-size: 0.8em;
    color: var(--text-light);
    opacity: 0.7;
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
        gap: 20px;
        text-align: center;
    }
    
    .receipt-number {
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
    
    .success-title {
        font-size: 2em;
    }
}

@media print {
    .action-buttons {
        display: none;
    }
    
    .success-animation,
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
    // In a real implementation, this would generate a PDF using libraries like jsPDF
    alert('PDF download functionality would be implemented here using libraries like jsPDF or server-side PDF generation.');
}

// Auto-redirect after 60 seconds
setTimeout(function() {
    if (confirm('Would you like to return to the dashboard?')) {
        window.location.href = 'dashboard.php';
    }
}, 60000);

// Show success animation on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add any additional success animations here
});
</script>

<?php include_once '../includes/footer.php'; ?>