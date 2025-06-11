<?php
session_start();

if ($_SESSION['role'] != 'student' && $_SESSION['role'] != 'faculty') {
    header('Location: ../index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

include_once '../includes/header.php';

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

// Update payments table structure if needed
$sql = "ALTER TABLE payments 
        ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100),
        ADD COLUMN IF NOT EXISTS payment_details TEXT";
$conn->query($sql);
?>

<div class="container">
    <div class="payment-header">
        <div class="payment-breadcrumb">
            <a href="fines.php"><i class="fas fa-arrow-left"></i> Back to Fines</a>
        </div>
        <h1 class="page-title">
            <i class="fas fa-credit-card"></i> Secure Payment Gateway
        </h1>
        <p class="payment-subtitle">Complete your fine payment securely with Stripe</p>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>" id="payment-alert">
            <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($fine): ?>
        <div class="payment-container">
            <!-- Payment Summary -->
            <div class="payment-summary-card">
                <div class="summary-header">
                    <h3><i class="fas fa-receipt"></i> Payment Summary</h3>
                    <div class="security-badge">
                        <i class="fas fa-shield-alt"></i>
                        <span>SSL Secured</span>
                    </div>
                </div>
                <div class="summary-body">
                    <div class="fine-details">
                        <div class="book-info">
                            <h4><?php echo htmlspecialchars($fine['title']); ?></h4>
                            <p class="author">by <?php echo htmlspecialchars($fine['author']); ?></p>
                        </div>
                        
                        <div class="fine-breakdown">
                            <div class="detail-row">
                                <span class="label">Fine Reason:</span>
                                <span class="value"><?php echo htmlspecialchars($fine['reason']); ?></span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="label">Due Date:</span>
                                <span class="value"><?php echo date('M d, Y', strtotime($fine['return_date'])); ?></span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="label">Return Date:</span>
                                <span class="value"><?php echo date('M d, Y', strtotime($fine['actual_return_date'])); ?></span>
                            </div>
                            
                            <?php 
                            $dueDate = new DateTime($fine['return_date']);
                            $returnDate = new DateTime($fine['actual_return_date']);
                            $lateDays = $returnDate->diff($dueDate)->days;
                            ?>
                            
                            <div class="detail-row">
                                <span class="label">Days Late:</span>
                                <span class="value late-days"><?php echo $lateDays; ?> day<?php echo $lateDays > 1 ? 's' : ''; ?></span>
                            </div>
                        </div>
                        
                        <div class="total-section">
                            <div class="total-amount">
                                <span class="total-label">Total Amount:</span>
                                <span class="amount">PKR <?php echo number_format($fine['amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="payment-features">
                        <div class="feature">
                            <i class="fas fa-lock"></i>
                            <span>256-bit SSL Encryption</span>
                        </div>
                        <div class="feature">
                            <i class="fab fa-stripe"></i>
                            <span>Powered by Stripe</span>
                        </div>
                        <div class="feature">
                            <i class="fas fa-shield-alt"></i>
                            <span>PCI DSS Compliant</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stripe Payment Form -->
            <div class="stripe-payment-card">
                <div class="payment-form-header">
                    <h3><i class="fas fa-credit-card"></i> Payment Information</h3>
                    <div class="accepted-cards">
                        <i class="fab fa-cc-visa" title="Visa"></i>
                        <i class="fab fa-cc-mastercard" title="Mastercard"></i>
                        <i class="fab fa-cc-amex" title="American Express"></i>
                        <i class="fab fa-cc-discover" title="Discover"></i>
                    </div>
                </div>
                <div class="payment-form-body">
                    <form id="payment-form">
                        <!-- Stripe Elements will be mounted here -->
                        <div class="form-group">
                            <label for="card-element">
                                <i class="fas fa-credit-card"></i> Card Information
                            </label>
                            <div id="card-element" class="stripe-element">
                                <!-- Stripe Elements will create form elements here -->
                            </div>
                            <div id="card-errors" class="card-errors"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="billing-email">
                                <i class="fas fa-envelope"></i> Email Address
                            </label>
                            <input type="email" id="billing-email" name="billing_email" 
                                   placeholder="john@example.com" class="form-control" 
                                   value="<?php echo htmlspecialchars($_SESSION['email']); ?>" required>
                        </div>
                        
                        <!-- Processing Overlay -->
                        <div id="processing-overlay" class="processing-overlay">
                            <div class="processing-content">
                                <div class="spinner"></div>
                                <h3>Processing Payment...</h3>
                                <p>Please wait while we process your payment securely.</p>
                                <div class="processing-steps">
                                    <div class="step active" id="step-1">
                                        <i class="fas fa-credit-card"></i>
                                        <span>Validating Card</span>
                                    </div>
                                    <div class="step" id="step-2">
                                        <i class="fas fa-shield-alt"></i>
                                        <span>Securing Transaction</span>
                                    </div>
                                    <div class="step" id="step-3">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Completing Payment</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="payment-actions">
                            <a href="fines.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" id="submit-payment" class="btn btn-primary btn-lg">
                                <i class="fas fa-lock"></i> Pay PKR<?php echo number_format($fine['amount'], 2); ?>
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
.payment-header {
    text-align: center;
    margin-bottom: 40px;
    padding: 30px 0;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: var(--white);
    border-radius: var(--border-radius);
    margin: -20px -20px 40px -20px;
    padding: 40px 20px;
}

.payment-breadcrumb {
    margin-bottom: 20px;
}

.payment-breadcrumb a {
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
}

.payment-breadcrumb a:hover {
    color: var(--white);
}

.page-title {
    margin: 0 0 10px 0;
    font-size: 2.5em;
    font-weight: 700;
}

.payment-subtitle {
    margin: 0;
    font-size: 1.1em;
    opacity: 0.9;
}

.payment-container {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 40px;
    max-width: 1400px;
    margin: 0 auto;
}

.payment-summary-card, .stripe-payment-card {
    background: var(--white);
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    border: 1px solid rgba(13, 71, 161, 0.1);
}

.summary-header, .payment-form-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: var(--white);
    padding: 25px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.summary-header h3, .payment-form-header h3 {
    margin: 0;
    font-size: 1.3em;
    font-weight: 600;
}

.security-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.9em;
    font-weight: 500;
}

.accepted-cards {
    display: flex;
    gap: 12px;
}

.accepted-cards i {
    font-size: 1.8em;
    opacity: 0.9;
    transition: var(--transition);
}

.accepted-cards i:hover {
    opacity: 1;
    transform: scale(1.1);
}

.summary-body, .payment-form-body {
    padding: 30px;
    position: relative;
}

.book-info {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--gray-200);
}

.book-info h4 {
    color: var(--primary-color);
    margin-bottom: 8px;
    font-size: 1.4em;
    font-weight: 600;
}

.book-info .author {
    color: var(--text-light);
    font-size: 1.1em;
    margin: 0;
}

.fine-breakdown {
    margin-bottom: 25px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 12px 0;
    border-bottom: 1px solid var(--gray-200);
}

.detail-row:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.detail-row .label {
    font-weight: 500;
    color: var(--text-light);
}

.detail-row .value {
    font-weight: 600;
    color: var(--text-color);
}

.late-days {
    color: var(--danger-color) !important;
    font-weight: 700;
}

.total-section {
    background: var(--gray-100);
    padding: 20px;
    border-radius: var(--border-radius);
    margin-bottom: 25px;
}

.total-amount {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.total-label {
    font-size: 1.2em;
    font-weight: 600;
    color: var(--text-color);
}

.amount {
    font-size: 2em;
    font-weight: 700;
    color: var(--primary-color);
}

.payment-features {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.feature {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--success-color);
    font-size: 0.9em;
    font-weight: 500;
}

.feature i {
    width: 20px;
    text-align: center;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: var(--text-color);
    font-size: 1em;
}

.form-group label i {
    margin-right: 8px;
    color: var(--primary-color);
}

.form-control {
    width: 100%;
    padding: 15px 18px;
    border: 2px solid var(--gray-300);
    border-radius: 10px;
    font-size: 1em;
    transition: var(--transition);
    box-sizing: border-box;
    background: var(--white);
}

.form-control:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.1);
}

.stripe-element {
    padding: 15px 18px;
    border: 2px solid var(--gray-300);
    border-radius: 10px;
    background: var(--white);
    transition: var(--transition);
}

.stripe-element:focus-within {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.1);
}

.card-errors {
    color: var(--danger-color);
    margin-top: 10px;
    padding: 15px;
    background: rgba(220, 53, 69, 0.1);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--danger-color);
    display: none;
    font-weight: 500;
}

.processing-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.95);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    border-radius: 15px;
}

.processing-content {
    text-align: center;
    padding: 40px;
}

.spinner {
    width: 60px;
    height: 60px;
    border: 4px solid var(--gray-200);
    border-top: 4px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.processing-content h3 {
    color: var(--primary-color);
    margin-bottom: 10px;
    font-size: 1.5em;
}

.processing-content p {
    color: var(--text-light);
    margin-bottom: 30px;
}

.processing-steps {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    opacity: 0.5;
    transition: var(--transition);
}

.step.active {
    opacity: 1;
    color: var(--primary-color);
}

.step i {
    font-size: 1.5em;
    margin-bottom: 5px;
}

.step span {
    font-size: 0.9em;
    font-weight: 500;
}

.payment-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 40px;
    padding-top: 25px;
    border-top: 2px solid var(--gray-200);
    gap: 20px;
}

.btn-lg {
    padding: 18px 35px;
    font-size: 1.2em;
    font-weight: 700;
    border-radius: 10px;
    min-width: 200px;
}

#submit-payment {
    background: linear-gradient(135deg, var(--success-color), #34ce57);
    border: none;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    transition: var(--transition);
}

#submit-payment:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
}

#submit-payment:disabled {
    background: var(--gray-400);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

@media (max-width: 1024px) {
    .payment-container {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .payment-header {
        margin: -20px -15px 30px -15px;
        padding: 30px 15px;
    }
    
    .page-title {
        font-size: 2em;
    }
}

@media (max-width: 768px) {
    .summary-body, .payment-form-body {
        padding: 20px;
    }
    
    .summary-header, .payment-form-header {
        padding: 20px;
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .payment-actions {
        flex-direction: column;
        gap: 15px;
    }
    
    .payment-actions .btn {
        width: 100%;
    }
    
    .accepted-cards {
        justify-content: center;
    }
    
    .processing-steps {
        gap: 20px;
    }
}

@media (max-width: 480px) {
    .payment-header {
        margin: -20px -10px 20px -10px;
        padding: 25px 10px;
    }
    
    .page-title {
        font-size: 1.8em;
    }
    
    .amount {
        font-size: 1.6em;
    }
    
    .processing-steps {
        flex-direction: column;
        gap: 15px;
    }
}
</style>

<!-- Include Stripe.js -->
<script src="https://js.stripe.com/v3/"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Stripe
    const stripe = Stripe('pk_test_51RXrGJ4KfG2Zot2yqATlNthP1rmv44p2UxKkM4fgXUrBBzcCJaogNREypEto3QvO9D7dfuY2mqEBgPGX8c8LgfLD00nAS0nnVR');
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
            },
            invalid: {
                color: '#9e2146',
            },
        },
    });
    
    cardElement.mount('#card-element');
    
    // Handle real-time validation errors from the card Element
    cardElement.on('change', function(event) {
        const displayError = document.getElementById('card-errors');
        if (event.error) {
            displayError.textContent = event.error.message;
            displayError.style.display = 'block';
        } else {
            displayError.style.display = 'none';
        }
    });
    
    // Handle form submission
    const form = document.getElementById('payment-form');
    const submitButton = document.getElementById('submit-payment');
    const processingOverlay = document.getElementById('processing-overlay');
    
    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        
        submitButton.disabled = true;
        processingOverlay.style.display = 'flex';
        
        // Show processing steps
        setTimeout(() => {
            document.getElementById('step-1').classList.remove('active');
            document.getElementById('step-2').classList.add('active');
        }, 1000);
        
        setTimeout(() => {
            document.getElementById('step-2').classList.remove('active');
            document.getElementById('step-3').classList.add('active');
        }, 2000);
        
        try {
            // Create payment method
            const {error, paymentMethod} = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
                billing_details: {
                    email: document.getElementById('billing-email').value,
                },
            });
            
            if (error) {
                throw new Error(error.message);
            }
            
            // Send payment to server
            const response = await fetch('process_stripe_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_method_id: paymentMethod.id,
                    amount: Math.round(<?php echo $fine['amount']; ?> * 100), // Convert to cents
                    fine_id: <?php echo $fine['id']; ?>,
                    currency: 'pkr'
                }),
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Payment successful - redirect to success page
                window.location.href = `payment_success.php?receipt=${result.receipt_number}&transaction=${result.transaction_id}`;
            } else {
                throw new Error(result.error || 'Payment failed');
            }
            
        } catch (error) {
            const displayError = document.getElementById('card-errors');
            displayError.textContent = error.message;
            displayError.style.display = 'block';
            
            submitButton.disabled = false;
            processingOverlay.style.display = 'none';
            
            // Reset processing steps
            document.querySelectorAll('.step').forEach(step => step.classList.remove('active'));
            document.getElementById('step-1').classList.add('active');
        }
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>