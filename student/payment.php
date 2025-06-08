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
    $cardNumber = preg_replace('/\s+/', '', $_POST['card_number']);
    $cardExpiry = $_POST['card_expiry'];
    $cardCvc = $_POST['card_cvc'];
    $cardName = trim($_POST['card_name']);
    $billingEmail = trim($_POST['billing_email']);
    
    // Basic validation
    $errors = [];
    
    if (empty($cardNumber) || strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
        $errors[] = "Please enter a valid card number.";
    }
    
    if (empty($cardExpiry) || !preg_match('/^\d{2}\/\d{2}$/', $cardExpiry)) {
        $errors[] = "Please enter a valid expiry date (MM/YY).";
    } else {
        list($month, $year) = explode('/', $cardExpiry);
        $currentYear = date('y');
        $currentMonth = date('m');
        if ($month < 1 || $month > 12 || $year < $currentYear || ($year == $currentYear && $month < $currentMonth)) {
            $errors[] = "Card has expired or invalid expiry date.";
        }
    }
    
    if (empty($cardCvc) || strlen($cardCvc) < 3 || strlen($cardCvc) > 4) {
        $errors[] = "Please enter a valid CVC.";
    }
    
    if (empty($cardName)) {
        $errors[] = "Please enter the cardholder name.";
    }
    
    if (empty($billingEmail) || !filter_var($billingEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $messageType = "danger";
    } else {
        // Simulate payment processing
        $transactionId = 'stripe_' . date('YmdHis') . rand(1000, 9999);
        
        // Update fine status
        $stmt = $conn->prepare("UPDATE fines SET status = 'paid' WHERE id = ?");
        $stmt->bind_param("i", $fine['id']);
        
        if ($stmt->execute()) {
            // Record payment
            $receiptNumber = 'RCP' . date('Ymd') . str_pad($fine['id'], 4, '0', STR_PAD_LEFT);
            $paymentDetails = json_encode([
                'card_last_four' => substr($cardNumber, -4),
                'card_type' => 'Credit Card',
                'billing_email' => $billingEmail,
                'transaction_id' => $transactionId
            ]);
            
            $stmt = $conn->prepare("
                INSERT INTO payments (fine_id, user_id, amount, payment_method, receipt_number, transaction_id, payment_details) 
                VALUES (?, ?, ?, 'stripe', ?, ?, ?)
            ");
            $stmt->bind_param("iidsss", $fine['id'], $userId, $fine['amount'], $receiptNumber, $transactionId, $paymentDetails);
            $stmt->execute();
            
            // Send notification
            $notificationMessage = "Fine payment of $" . number_format($fine['amount'], 2) . " processed successfully. Transaction ID: " . $transactionId;
            sendNotification($conn, $userId, $notificationMessage);
            
            // Redirect to success page
            header("Location: payment_success.php?receipt=$receiptNumber&transaction=$transactionId");
            exit();
        } else {
            $message = "Payment processing failed. Please try again.";
            $messageType = "danger";
        }
    }
}

// Update payments table structure if needed
$sql = "ALTER TABLE payments 
        ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(50),
        ADD COLUMN IF NOT EXISTS payment_details TEXT";
$conn->query($sql);
?>

<div class="container">
    <h1 class="page-title">Secure Payment Gateway</h1>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($fine): ?>
        <div class="payment-container">
            <!-- Payment Summary -->
            <div class="payment-summary-card">
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
                        <small>Secured payment processing</small>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <div class="stripe-payment-card">
                <div class="card-header">
                    <h3><i class="fas fa-credit-card"></i> Pay with Credit Card</h3>
                    <div class="accepted-cards">
                        <i class="fab fa-cc-visa"></i>
                        <i class="fab fa-cc-mastercard"></i>
                        <i class="fab fa-cc-amex"></i>
                        <i class="fab fa-cc-discover"></i>
                    </div>
                </div>
                <div class="card-body">
                    <form action="" method="POST" id="payment-form">
                        <!-- Card Number -->
                        <div class="form-group">
                            <label for="card-number">Card Number</label>
                            <div class="card-input-container">
                                <input type="text" id="card-number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" class="form-control card-input" required>
                                <div class="card-type-icon" id="card-type-icon"></div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="card-expiry">Expiry Date</label>
                                    <input type="text" id="card-expiry" name="card_expiry" placeholder="MM/YY" maxlength="5" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="card-cvc">CVC</label>
                                    <input type="text" id="card-cvc" name="card_cvc" placeholder="123" maxlength="4" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="card-name">Cardholder Name</label>
                            <input type="text" id="card-name" name="card_name" placeholder="John Doe" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="billing-email">Email Address</label>
                            <input type="email" id="billing-email" name="billing_email" placeholder="john@example.com" class="form-control" value="<?php echo htmlspecialchars($_SESSION['email']); ?>" required>
                        </div>
                        
                        <div id="card-errors" class="card-errors"></div>
                        
                        <div class="payment-actions">
                            <a href="fines.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Fines
                            </a>
                            <button type="submit" name="process_payment" id="submit-payment" class="btn btn-primary btn-lg">
                                <i class="fas fa-lock"></i> Pay $<?php echo number_format($fine['amount'], 2); ?>
                            </button>
                        </div>
                    </form>
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
.payment-container {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}

.payment-summary-card, .stripe-payment-card {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    overflow: hidden;
}

.card-header {
    background: var(--primary-color);
    color: var(--white);
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    font-size: 1.2em;
}

.accepted-cards {
    display: flex;
    gap: 10px;
}

.accepted-cards i {
    font-size: 1.5em;
    opacity: 0.8;
}

.card-body {
    padding: 30px;
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
    padding: 15px;
    border-radius: var(--border-radius);
    text-align: center;
    margin-top: 20px;
}

.security-info i {
    color: var(--success-color);
    margin-right: 5px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-color);
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--gray-300);
    border-radius: var(--border-radius);
    font-size: 1em;
    transition: var(--transition);
    box-sizing: border-box;
}

.form-control:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.1);
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-col {
    flex: 1;
}

.card-input-container {
    position: relative;
}

.card-type-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.5em;
}

.card-input.valid {
    border-color: var(--success-color);
}

.card-input.invalid {
    border-color: var(--danger-color);
}

.card-errors {
    color: var(--danger-color);
    margin-bottom: 20px;
    padding: 10px;
    background: rgba(220, 53, 69, 0.1);
    border-radius: var(--border-radius);
    display: none;
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
    padding: 15px 30px;
    font-size: 1.1em;
    font-weight: 600;
}

#submit-payment:disabled {
    background-color: var(--gray-400);
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .payment-container {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .payment-actions {
        flex-direction: column;
        gap: 15px;
    }
    
    .payment-actions .btn {
        width: 100%;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cardNumberInput = document.getElementById('card-number');
    const cardExpiryInput = document.getElementById('card-expiry');
    const cardCvcInput = document.getElementById('card-cvc');
    const cardNameInput = document.getElementById('card-name');
    const billingEmailInput = document.getElementById('billing-email');
    const cardTypeIcon = document.getElementById('card-type-icon');
    const cardErrors = document.getElementById('card-errors');
    const submitButton = document.getElementById('submit-payment');
    const form = document.getElementById('payment-form');
    
    // Card number formatting and validation
    cardNumberInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
        let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
        
        if (formattedValue.length <= 19) {
            e.target.value = formattedValue;
        }
        
        // Detect card type
        detectCardType(value);
        validateForm();
    });
    
    // Expiry date formatting
    cardExpiryInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        e.target.value = value;
        validateForm();
    });
    
    // CVC validation
    cardCvcInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        e.target.value = value;
        validateForm();
    });
    
    // Name validation
    cardNameInput.addEventListener('input', validateForm);
    
    // Email validation
    billingEmailInput.addEventListener('input', validateForm);
    
    function detectCardType(number) {
        const cardTypes = {
            visa: /^4/,
            mastercard: /^5[1-5]/,
            amex: /^3[47]/,
            discover: /^6(?:011|5)/
        };
        
        let detectedType = '';
        for (let type in cardTypes) {
            if (cardTypes[type].test(number)) {
                detectedType = type;
                break;
            }
        }
        
        if (detectedType) {
            cardTypeIcon.innerHTML = `<i class="fab fa-cc-${detectedType}"></i>`;
            cardTypeIcon.style.color = '#28a745';
        } else {
            cardTypeIcon.innerHTML = '';
        }
    }
    
    function validateForm() {
        const cardNumber = cardNumberInput.value.replace(/\s/g, '');
        const cardExpiry = cardExpiryInput.value;
        const cardCvc = cardCvcInput.value;
        const cardName = cardNameInput.value.trim();
        const billingEmail = billingEmailInput.value.trim();
        
        let isValid = true;
        
        // Validate card number
        if (cardNumber.length < 13 || cardNumber.length > 19 || !luhnCheck(cardNumber)) {
            isValid = false;
        }
        
        // Validate expiry
        if (!cardExpiry.match(/^\d{2}\/\d{2}$/)) {
            isValid = false;
        } else {
            const [month, year] = cardExpiry.split('/');
            const currentDate = new Date();
            const currentYear = currentDate.getFullYear() % 100;
            const currentMonth = currentDate.getMonth() + 1;
            
            if (parseInt(month) < 1 || parseInt(month) > 12 ||
                parseInt(year) < currentYear || 
                (parseInt(year) === currentYear && parseInt(month) < currentMonth)) {
                isValid = false;
            }
        }
        
        // Validate CVC
        if (cardCvc.length < 3 || cardCvc.length > 4) {
            isValid = false;
        }
        
        // Validate name
        if (cardName.length < 2) {
            isValid = false;
        }
        
        // Validate email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(billingEmail)) {
            isValid = false;
        }
        
        submitButton.disabled = !isValid;
    }
    
    function luhnCheck(number) {
        let sum = 0;
        let alternate = false;
        
        for (let i = number.length - 1; i >= 0; i--) {
            let n = parseInt(number.charAt(i), 10);
            
            if (alternate) {
                n *= 2;
                if (n > 9) {
                    n = (n % 10) + 1;
                }
            }
            
            sum += n;
            alternate = !alternate;
        }
        
        return (sum % 10) === 0;
    }
    
    function showError(message) {
        cardErrors.textContent = message;
        cardErrors.style.display = 'block';
    }
    
    function hideError() {
        cardErrors.style.display = 'none';
    }
    
    // Form submission
    form.addEventListener('submit', function(e) {
        const cardNumber = cardNumberInput.value.replace(/\s/g, '');
        
        if (!luhnCheck(cardNumber)) {
            e.preventDefault();
            showError('Please enter a valid card number.');
            return;
        }
        
        hideError();
        
        // Show processing state
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Payment...';
    });
    
    // Initial validation
    validateForm();
});
</script>

<?php include_once '../includes/footer.php'; ?>