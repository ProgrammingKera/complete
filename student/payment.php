<?php
include_once '../includes/header.php';

// Check if user is student or faculty
if ($_SESSION['role'] != 'student' && $_SESSION['role'] != 'faculty') {
    header('Location: ../index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Get fine details if fine_id is provided
$fine = null;
if (isset($_GET['fine_id'])) {
    $fineId = (int)$_GET['fine_id'];
    
    $stmt = $conn->prepare("
        SELECT f.*, b.title, b.author, ib.return_date, ib.actual_return_date
        FROM fines f
        JOIN issued_books ib ON f.issued_book_id = ib.id
        JOIN books b ON ib.book_id = b.id
        WHERE f.id = ? AND f.user_id = ? AND f.status = 'pending'
    ");
    $stmt->bind_param("ii", $fineId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $fine = $result->fetch_assoc();
    } else {
        header('Location: fines.php');
        exit();
    }
}

// Process payment
if (isset($_POST['process_payment']) && $fine) {
    $paymentMethod = $_POST['payment_method'];
    $cardNumber = isset($_POST['card_number']) ? $_POST['card_number'] : '';
    $cardExpiry = isset($_POST['card_expiry']) ? $_POST['card_expiry'] : '';
    $cardCvv = isset($_POST['card_cvv']) ? $_POST['card_cvv'] : '';
    $bankAccount = isset($_POST['bank_account']) ? $_POST['bank_account'] : '';
    $upiId = isset($_POST['upi_id']) ? $_POST['upi_id'] : '';
    
    // Validate payment method specific fields
    $validPayment = true;
    $errorMsg = '';
    
    switch ($paymentMethod) {
        case 'credit_card':
        case 'debit_card':
            if (empty($cardNumber) || empty($cardExpiry) || empty($cardCvv)) {
                $validPayment = false;
                $errorMsg = 'Please fill all card details.';
            } elseif (strlen($cardNumber) < 16) {
                $validPayment = false;
                $errorMsg = 'Invalid card number.';
            } elseif (strlen($cardCvv) < 3) {
                $validPayment = false;
                $errorMsg = 'Invalid CVV.';
            }
            break;
            
        case 'net_banking':
            if (empty($bankAccount)) {
                $validPayment = false;
                $errorMsg = 'Please enter bank account number.';
            }
            break;
            
        case 'upi':
            if (empty($upiId)) {
                $validPayment = false;
                $errorMsg = 'Please enter UPI ID.';
            } elseif (!filter_var($upiId, FILTER_VALIDATE_EMAIL) && !preg_match('/^[0-9]{10}@[a-zA-Z0-9]+$/', $upiId)) {
                $validPayment = false;
                $errorMsg = 'Invalid UPI ID format.';
            }
            break;
    }
    
    if ($validPayment) {
        // Simulate payment processing delay
        sleep(1);
        
        // Generate transaction ID
        $transactionId = 'TXN' . date('YmdHis') . rand(1000, 9999);
        
        // Update fine status
        $stmt = $conn->prepare("UPDATE fines SET status = 'paid' WHERE id = ?");
        $stmt->bind_param("i", $fine['id']);
        
        if ($stmt->execute()) {
            // Record payment with additional details
            $receiptNumber = 'RCP' . date('Ymd') . str_pad($fine['id'], 4, '0', STR_PAD_LEFT);
            $paymentDetails = json_encode([
                'transaction_id' => $transactionId,
                'card_last_four' => $paymentMethod == 'credit_card' || $paymentMethod == 'debit_card' ? substr($cardNumber, -4) : null,
                'bank_account' => $paymentMethod == 'net_banking' ? substr($bankAccount, -4) : null,
                'upi_id' => $paymentMethod == 'upi' ? $upiId : null
            ]);
            
            $stmt = $conn->prepare("
                INSERT INTO payments (fine_id, user_id, amount, payment_method, receipt_number, transaction_id, payment_details) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iidssss", $fine['id'], $userId, $fine['amount'], $paymentMethod, $receiptNumber, $transactionId, $paymentDetails);
            $stmt->execute();
            
            // Send notification
            $notificationMessage = "Fine payment of $" . number_format($fine['amount'], 2) . " processed successfully via " . ucwords(str_replace('_', ' ', $paymentMethod)) . ". Transaction ID: " . $transactionId;
            sendNotification($conn, $userId, $notificationMessage);
            
            // Redirect to success page
            
            echo "<script>window.location.href='payment_success.php?receipt=$receiptNumber&transaction=$transactionId';</script>";
exit();
        } else {
            $message = "Payment processing failed. Please try again.";
            $messageType = "danger";
        }
    } else {
        $message = $errorMsg;
        $messageType = "danger";
    }
}

// Update payments table structure if needed
$sql = "ALTER TABLE payments 
        ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(50),
        ADD COLUMN IF NOT EXISTS payment_details TEXT";
$conn->query($sql);
?>

<div class="container">
    <h1 class="page-title">Payment Gateway</h1>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($fine): ?>
        <div class="row">
            <!-- Payment Summary -->
            <div class="col-md-4">
                <div class="card payment-summary">
                    <div class="card-header">
                        <h3><i class="fas fa-receipt"></i> Payment Summary</h3>
                    </div>
                    <div class="card-body">
                        <div class="fine-details">
                            <h4><?php echo htmlspecialchars($fine['title']); ?></h4>
                            <p class="text-muted">by <?php echo htmlspecialchars($fine['author']); ?></p>
                            
                            <hr>
                            
                            <div class="detail-row">
                                <span>Fine Reason:</span>
                                <span><?php echo htmlspecialchars($fine['reason']); ?></span>
                            </div>
                            
                            <div class="detail-row">
                                <span>Due Date:</span>
                                <span><?php echo date('M d, Y', strtotime($fine['return_date'])); ?></span>
                            </div>
                            
                            <div class="detail-row">
                                <span>Return Date:</span>
                                <span><?php echo date('M d, Y', strtotime($fine['actual_return_date'])); ?></span>
                            </div>
                            
                            <hr>
                            
                            <div class="total-amount">
                                <span>Total Amount:</span>
                                <span class="amount">$<?php echo number_format($fine['amount'], 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="security-info">
                            <i class="fas fa-shield-alt"></i>
                            <small>Your payment is secured with 256-bit SSL encryption</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <div class="col-md-8">
                <div class="card payment-form">
                    <div class="card-header">
                        <h3><i class="fas fa-credit-card"></i> Select Payment Method</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="paymentForm">
                            <!-- Payment Method Selection -->
                            <div class="payment-methods">
                                <div class="method-option">
                                    <input type="radio" id="credit_card" name="payment_method" value="credit_card" required>
                                    <label for="credit_card">
                                        <i class="fab fa-cc-visa"></i>
                                        <i class="fab fa-cc-mastercard"></i>
                                        Credit Card
                                    </label>
                                </div>
                                
                                <div class="method-option">
                                    <input type="radio" id="debit_card" name="payment_method" value="debit_card" required>
                                    <label for="debit_card">
                                        <i class="fas fa-credit-card"></i>
                                        Debit Card
                                    </label>
                                </div>
                                
                                <div class="method-option">
                                    <input type="radio" id="net_banking" name="payment_method" value="net_banking" required>
                                    <label for="net_banking">
                                        <i class="fas fa-university"></i>
                                        Net Banking
                                    </label>
                                </div>
                                
                                <div class="method-option">
                                    <input type="radio" id="upi" name="payment_method" value="upi" required>
                                    <label for="upi">
                                        <i class="fas fa-mobile-alt"></i>
                                        UPI Payment
                                    </label>
                                </div>
                                
                                <div class="method-option">
                                    <input type="radio" id="wallet" name="payment_method" value="wallet" required>
                                    <label for="wallet">
                                        <i class="fas fa-wallet"></i>
                                        Digital Wallet
                                    </label>
                                </div>
                            </div>

                            <!-- Card Payment Details -->
                            <div id="card_details" class="payment-details" style="display: none;">
                                <h4>Card Details</h4>
                                <div class="form-row">
                                    <div class="form-group col-md-12">
                                        <label for="card_number">Card Number</label>
                                        <input type="text" id="card_number" name="card_number" class="form-control" 
                                               placeholder="1234 5678 9012 3456" maxlength="19">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="card_expiry">Expiry Date</label>
                                        <input type="text" id="card_expiry" name="card_expiry" class="form-control" 
                                               placeholder="MM/YY" maxlength="5">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="card_cvv">CVV</label>
                                        <input type="text" id="card_cvv" name="card_cvv" class="form-control" 
                                               placeholder="123" maxlength="4">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="card_name">Cardholder Name</label>
                                    <input type="text" id="card_name" name="card_name" class="form-control" 
                                           placeholder="John Doe">
                                </div>
                            </div>

                            <!-- Net Banking Details -->
                            <div id="banking_details" class="payment-details" style="display: none;">
                                <h4>Net Banking Details</h4>
                                <div class="form-group">
                                    <label for="bank_name">Select Bank</label>
                                    <select id="bank_name" name="bank_name" class="form-control">
                                        <option value="">Choose your bank</option>
                                        <option value="sbi">State Bank of India</option>
                                        <option value="hdfc">HDFC Bank</option>
                                        <option value="icici">ICICI Bank</option>
                                        <option value="axis">Axis Bank</option>
                                        <option value="pnb">Punjab National Bank</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="bank_account">Account Number</label>
                                    <input type="text" id="bank_account" name="bank_account" class="form-control" 
                                           placeholder="Enter account number">
                                </div>
                            </div>

                            <!-- UPI Details -->
                            <div id="upi_details" class="payment-details" style="display: none;">
                                <h4>UPI Payment</h4>
                                <div class="form-group">
                                    <label for="upi_id">UPI ID</label>
                                    <input type="text" id="upi_id" name="upi_id" class="form-control" 
                                           placeholder="yourname@paytm or 9876543210@ybl">
                                </div>
                                <div class="upi-apps">
                                    <p>Popular UPI Apps:</p>
                                    <div class="app-icons">
                                        <span class="app-icon">📱 PhonePe</span>
                                        <span class="app-icon">💰 Paytm</span>
                                        <span class="app-icon">🏦 Google Pay</span>
                                        <span class="app-icon">💳 BHIM</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Wallet Details -->
                            <div id="wallet_details" class="payment-details" style="display: none;">
                                <h4>Digital Wallet</h4>
                                <div class="form-group">
                                    <label for="wallet_type">Select Wallet</label>
                                    <select id="wallet_type" name="wallet_type" class="form-control">
                                        <option value="">Choose wallet</option>
                                        <option value="paytm">Paytm Wallet</option>
                                        <option value="phonepe">PhonePe Wallet</option>
                                        <option value="amazon">Amazon Pay</option>
                                        <option value="mobikwik">MobiKwik</option>
                                        <option value="freecharge">FreeCharge</option>
                                    </select>
                                </div>
                            </div>

                            <div class="payment-actions">
                                <a href="fines.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Fines
                                </a>
                                <button type="submit" name="process_payment" class="btn btn-primary btn-lg">
                                    <i class="fas fa-lock"></i> Pay $<?php echo number_format($fine['amount'], 2); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            Invalid fine ID or fine already paid.
            <a href="fines.php" class="btn btn-primary ml-3">View Fines</a>
        </div>
    <?php endif; ?>
</div>

<style>
.row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -15px;
}

.col-md-4, .col-md-6, .col-md-8, .col-md-12 {
    padding: 0 15px;
}

.col-md-4 { flex: 0 0 33.333333%; max-width: 33.333333%; }
.col-md-6 { flex: 0 0 50%; max-width: 50%; }
.col-md-8 { flex: 0 0 66.666667%; max-width: 66.666667%; }
.col-md-12 { flex: 0 0 100%; max-width: 100%; }

.payment-summary {
    position: sticky;
    top: 20px;
}

.fine-details h4 {
    color: var(--primary-color);
    margin-bottom: 5px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding: 5px 0;
}

.total-amount {
    display: flex;
    justify-content: space-between;
    font-size: 1.2em;
    font-weight: bold;
    color: var(--primary-color);
    padding: 10px 0;
}

.amount {
    font-size: 1.5em;
}

.security-info {
    background: var(--gray-100);
    padding: 10px;
    border-radius: var(--border-radius);
    text-align: center;
    margin-top: 20px;
}

.security-info i {
    color: var(--success-color);
    margin-right: 5px;
}

.payment-methods {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.method-option {
    position: relative;
}

.method-option input[type="radio"] {
    display: none;
}

.method-option label {
    display: block;
    padding: 20px;
    border: 2px solid var(--gray-300);
    border-radius: var(--border-radius);
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    background: var(--white);
}

.method-option label:hover {
    border-color: var(--primary-color);
    background: rgba(13, 71, 161, 0.05);
}

.method-option input[type="radio"]:checked + label {
    border-color: var(--primary-color);
    background: rgba(13, 71, 161, 0.1);
    color: var(--primary-color);
}

.method-option label i {
    display: block;
    font-size: 2em;
    margin-bottom: 10px;
    color: var(--primary-color);
}

.payment-details {
    background: var(--gray-100);
    padding: 20px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
}

.payment-details h4 {
    margin-bottom: 20px;
    color: var(--primary-color);
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -10px;
}

.form-row .form-group {
    padding: 0 10px;
}

.upi-apps {
    margin-top: 15px;
}

.app-icons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.app-icon {
    background: var(--white);
    padding: 5px 10px;
    border-radius: var(--border-radius);
    font-size: 0.9em;
    border: 1px solid var(--gray-300);
}

.payment-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-300);
}

.btn-lg {
    padding: 12px 30px;
    font-size: 1.1em;
}

@media (max-width: 768px) {
    .col-md-4, .col-md-8 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .payment-methods {
        grid-template-columns: 1fr;
    }
    
    .payment-actions {
        flex-direction: column;
        gap: 15px;
    }
    
    .payment-actions .btn {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const paymentDetails = document.querySelectorAll('.payment-details');
    
    paymentMethods.forEach(method => {
        method.addEventListener('change', function() {
            // Hide all payment details
            paymentDetails.forEach(detail => {
                detail.style.display = 'none';
            });
            
            // Show relevant payment details
            if (this.value === 'credit_card' || this.value === 'debit_card') {
                document.getElementById('card_details').style.display = 'block';
            } else if (this.value === 'net_banking') {
                document.getElementById('banking_details').style.display = 'block';
            } else if (this.value === 'upi') {
                document.getElementById('upi_details').style.display = 'block';
            } else if (this.value === 'wallet') {
                document.getElementById('wallet_details').style.display = 'block';
            }
        });
    });
    
    // Format card number
    const cardNumber = document.getElementById('card_number');
    if (cardNumber) {
        cardNumber.addEventListener('input', function() {
            let value = this.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            this.value = formattedValue;
        });
    }
    
    // Format expiry date
    const cardExpiry = document.getElementById('card_expiry');
    if (cardExpiry) {
        cardExpiry.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            this.value = value;
        });
    }
    
    // CVV validation
    const cardCvv = document.getElementById('card_cvv');
    if (cardCvv) {
        cardCvv.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>