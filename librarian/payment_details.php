<?php
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Get fine ID from URL
$fineId = isset($_GET['fine_id']) ? (int)$_GET['fine_id'] : 0;

// Get payment details
$stmt = $conn->prepare("
    SELECT p.*, f.amount as fine_amount, u.name as user_name, b.title as book_title
    FROM payments p
    JOIN fines f ON p.fine_id = f.id
    JOIN users u ON p.user_id = u.id
    JOIN issued_books ib ON f.issued_book_id = ib.id
    JOIN books b ON ib.book_id = b.id
    WHERE p.fine_id = ?
    ORDER BY p.payment_date DESC
");
$stmt->bind_param("i", $fineId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: fines.php');
    exit();
}

$payment = $result->fetch_assoc();
?>

<div class="container">
    <div class="d-flex justify-between align-center mb-4">
        <h1 class="page-title">Payment Details</h1>
        <a href="fines.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Fines
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Payment Information</h3>
        </div>
        <div class="card-body">
            <div class="payment-receipt">
                <div class="receipt-header">
                    <div class="library-info">
                        <h2>Library Management System</h2>
                        <p>Payment Receipt</p>
                    </div>
                    <div class="receipt-number">
                        <h3>Receipt #<?php echo htmlspecialchars($payment['receipt_number']); ?></h3>
                        <p>Date: <?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></p>
                    </div>
                </div>

                <div class="receipt-body">
                    <div class="info-section">
                        <h4>Payment Details</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Paid By:</label>
                                <span><?php echo htmlspecialchars($payment['user_name']); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <label>Book Title:</label>
                                <span><?php echo htmlspecialchars($payment['book_title']); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <label>Fine Amount:</label>
<<<<<<< HEAD
                                <span>Rs<?php echo number_format($payment['fine_amount'], 2); ?></span>
=======
                                <span>$<?php echo number_format($payment['fine_amount'], 2); ?></span>
>>>>>>> 7c39a1d92c5527ecd186ad9dfb2b75bcfdcd349c
                            </div>
                            
                            <div class="info-item">
                                <label>Amount Paid:</label>
<<<<<<< HEAD
                                <span>Rs <?php echo number_format($payment['amount'], 2); ?></span>
=======
                                <span>$<?php echo number_format($payment['amount'], 2); ?></span>
>>>>>>> 7c39a1d92c5527ecd186ad9dfb2b75bcfdcd349c
                            </div>
                            
                            <div class="info-item">
                                <label>Payment Method:</label>
                                <span><?php echo ucfirst($payment['payment_method']); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <label>Payment Date:</label>
                                <span><?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="receipt-footer">
                        <p>Thank you for your payment</p>
                        <div class="actions">
                            <button onclick="window.print()" class="btn btn-primary">
                                <i class="fas fa-print"></i> Print Receipt
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.payment-receipt {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.receipt-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--gray-300);
}

.library-info h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.5em;
}

.library-info p {
    margin: 5px 0 0;
    color: var(--text-light);
}

.receipt-number {
    text-align: right;
}

.receipt-number h3 {
    margin: 0;
    color: var(--primary-color);
}

.receipt-number p {
    margin: 5px 0 0;
    color: var(--text-light);
}

.info-section {
    margin-bottom: 30px;
}

.info-section h4 {
    margin-bottom: 20px;
    color: var(--primary-color);
    border-bottom: 1px solid var(--gray-200);
    padding-bottom: 10px;
}

.info-grid {
    display: grid;
    gap: 15px;
}

.info-item {
    display: flex;
    align-items: center;
}

.info-item label {
    font-weight: 600;
    width: 150px;
    color: var(--text-light);
}

.info-item span {
    flex: 1;
}

.receipt-footer {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 2px solid var(--gray-300);
    text-align: center;
}

.receipt-footer p {
    margin-bottom: 20px;
    font-style: italic;
    color: var(--text-light);
}

.actions {
    display: flex;
    justify-content: center;
    gap: 10px;
}

@media print {
    .btn,
    .header,
    .sidebar {
        display: none !important;
    }
    
    .content-wrapper {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .card {
        box-shadow: none !important;
        border: none !important;
    }
}
</style>

<?php include_once '../includes/footer.php'; ?>