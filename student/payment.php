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

// Process Stripe payment
if (isset($_POST['process_stripe_payment']) && $fine) {
    $stripeToken = $_POST['stripeToken'];
    $stripeEmail = $_POST['stripeEmail'];
    
    if (empty($stripeToken)) {
        $message = "Payment failed. Please try again.";
        $messageType = "danger";
    } else {
        // Simulate Stripe payment processing
        // In a real implementation, you would use Stripe's PHP SDK here
        
        // Generate transaction ID
        $transactionId = 'stripe_' . date('YmdHis') . rand(1000, 9999);
        
        // Update fine status
        $stmt = $conn->prepare("UPDATE fines SET status = 'paid' WHERE id = ?");
        $stmt->bind_param("i", $fine['id']);
        
        if ($stmt->execute()) {
            // Record payment
            $receiptNumber = 'RCP' . date('Ymd') . str_pad($fine['id'], 4, '0', STR_PAD_LEFT);
            $paymentDetails = json_encode([
                'stripe_token' => substr($stripeToken, 0, 20) . '...',
                'stripe_email' => $stripeEmail,
                'transaction_id' => $transactionId,
                'card_last_four' => '****' // In real implementation, get from Stripe response
            ]);
            
            $stmt = $conn->prepare("
                INSERT INTO payments (fine_id, user_id, amount, payment_method, receipt_number, transaction_id, payment_details) 
                VALUES (?, ?, ?, 'stripe', ?, ?, ?)
            ");
            $stmt->bind_param("iidsss", $fine['id'], $userId, $fine['amount'], $receiptNumber, $transactionId, $paymentDetails);
            $stmt->execute();
            
            // Send notification
            $notificationMessage = "Fine payment of $" . number_format($fine['amount'], 2) . " processed successfully via Stripe. Transaction ID: " . $transactionId;
            sendNotification($conn, $userId, $notificationMessage);
            
            // Redirect to success page
            echo "<script>window.location.href='payment_success.php?receipt=$receiptNumber&transaction=$transactionId';</script>";
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
                        <small>Secured by Stripe - Industry leading payment security</small>
                    </div>
                </div>
            </div>

            <!-- Stripe Payment Form -->
            <div class="stripe-payment-card">
                <div class="card-header">
                    <h3><i class="fab fa-stripe"></i> Pay with Credit Card</h3>
                    <div class="accepted-cards">
                        <i class="fab fa-cc-visa"></i>
                        <i class="fab fa-cc-mastercard"></i>
                        <i class="fab fa-cc-amex"></i>
                        <i class="fab fa-cc-discover"></i>
                    </div>
                </div>
                <div class="card-body">
                    <form action="" method="POST" id="stripe-payment-form">
                        <div id="stripe-card-element" class="stripe-element">
                            <!-- Stripe Elements will create form elements here -->
                        </div>
                        
                        <div id="stripe-card-errors" role="alert" class="stripe-errors"></div>
                        
                        <div class="payment-actions">
                            <a href="fines.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Fines
                            </a>
                            <button type="submit" id="stripe-submit-btn" class="btn btn-primary btn-lg">
                                <i class="fas fa-lock"></i> Pay $<?php echo number_format($fine['amount'], 2); ?>
                            </button>
                        </div>
                        
                        <!-- Hidden fields for Stripe -->
                        <input type="hidden" name="process_stripe_payment" value="1">
                        <input type="hidden" name="stripeToken" id="stripeToken">
                        <input type="hidden" name="stripeEmail" id="stripeEmail">
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

<!-- Stripe Checkout Script -->
<script src="https://checkout.stripe.com/checkout.js"></script>

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

.stripe-element {
    background: var(--white);
    padding: 15px;
    border: 2px solid var(--gray-300);
    border-radius: var(--border-radius);
    margin-bottom: 20px;
    transition: var(--transition);
}

.stripe-element:focus-within {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.1);
}

.stripe-errors {
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

#stripe-submit-btn:disabled {
    background-color: var(--gray-400);
    cursor: not-allowed;
}

.payment-processing {
    display: none;
    text-align: center;
    padding: 20px;
}

.spinner {
    border: 3px solid var(--gray-300);
    border-top: 3px solid var(--primary-color);
    border-radius: 50%;
    width: 30px;
    height: 30px;
    animation: spin 1s linear infinite;
    margin: 0 auto 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
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
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Stripe configuration (use test key for demo)
    const stripe = Stripe('pk_test_51234567890abcdef'); // Replace with your actual Stripe publishable key
    const elements = stripe.elements();
    
    // Create card element
    const cardElement = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: '#424770',
                '::placeholder': {
                    color: '#aab7c4',
                },
                fontFamily: '"Segoe UI", Tahoma, Geneva, Verdana, sans-serif',
            },
            invalid: {
                color: '#9e2146',
            },
        },
    });
    
    cardElement.mount('#stripe-card-element');
    
    // Handle form submission
    const form = document.getElementById('stripe-payment-form');
    const submitBtn = document.getElementById('stripe-submit-btn');
    const errorElement = document.getElementById('stripe-card-errors');
    
    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        // Create token
        const {token, error} = await stripe.createToken(cardElement);
        
        if (error) {
            // Show error to customer
            errorElement.textContent = error.message;
            errorElement.style.display = 'block';
            
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-lock"></i> Pay $<?php echo number_format($fine['amount'], 2); ?>';
        } else {
            // Send token to server
            document.getElementById('stripeToken').value = token.id;
            document.getElementById('stripeEmail').value = token.card.name || 'customer@example.com';
            
            // Submit form
            form.submit();
        }
    });
    
    // Handle real-time validation errors from the card Element
    cardElement.on('change', function(event) {
        if (event.error) {
            errorElement.textContent = event.error.message;
            errorElement.style.display = 'block';
        } else {
            errorElement.style.display = 'none';
        }
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>