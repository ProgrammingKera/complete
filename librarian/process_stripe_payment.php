<?php
session_start();
include_once '../includes/config.php';
include_once '../includes/functions.php';

// Check if user is a librarian
checkUserRole('librarian');

// Set content type to JSON
header('Content-Type: application/json');

// Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid input data']);
    exit;
}

$paymentMethodId = $input['payment_method_id'] ?? '';
$amount = $input['amount'] ?? 0; // Amount in cents
$fineId = $input['fine_id'] ?? 0;
$currency = $input['currency'] ?? 'usd';

// Validate input
if (empty($paymentMethodId) || $amount <= 0 || $fineId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing required payment information']);
    exit;
}

// Get fine details
$stmt = $conn->prepare("
    SELECT f.*, u.id as user_id, u.name as user_name, u.email as user_email, b.title as book_title
    FROM fines f
    JOIN issued_books ib ON f.issued_book_id = ib.id
    JOIN books b ON ib.book_id = b.id
    JOIN users u ON f.user_id = u.id
    WHERE f.id = ? AND f.status = 'pending'
");
$stmt->bind_param("i", $fineId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'error' => 'Fine not found or already paid']);
    exit;
}

$fine = $result->fetch_assoc();

// Verify amount matches fine amount (convert to cents)
$expectedAmount = round($fine['amount'] * 100);
if ($amount != $expectedAmount) {
    echo json_encode(['success' => false, 'error' => 'Payment amount mismatch']);
    exit;
}

try {
    // Initialize Stripe
    require_once '../vendor/autoload.php';
    
    // Load environment variables from .env file
    if (file_exists(__DIR__ . '/../.env')) {
        $envContent = file_get_contents(__DIR__ . '/../.env');
        $lines = explode("\n", $envContent);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }

    // Set Stripe API key from environment variable
    if (!isset($_ENV['STRIPE_SECRET_KEY']) || empty($_ENV['STRIPE_SECRET_KEY'])) {
        throw new Exception('Stripe secret key not set in .env file');
    }
    \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
    
    // Create a PaymentIntent
    $paymentIntent = \Stripe\PaymentIntent::create([
    'amount' => $amount,
    'currency' => $currency,
    'payment_method' => $paymentMethodId,
    'confirmation_method' => 'manual',
    'confirm' => true,
    'return_url' => 'http://localhost:3000/librarian/fines.php', 
    'description' => "Library Fine Payment - Fine ID: {$fineId}",
    'metadata' => [
        'fine_id' => $fineId,
        'user_id' => $fine['user_id'],
        'user_name' => $fine['user_name'],
        'book_title' => $fine['book_title']
    ],
    'receipt_email' => $fine['user_email']
]);
    
    if ($paymentIntent->status == 'succeeded') {
        // Payment successful, update database
        $conn->begin_transaction();
        
        try {
            // Create payment record with transaction ID and payment details
            $receiptNumber = 'STRIPE_' . $paymentIntent->id;
            $paymentAmount = $amount / 100; // Convert back to pkr
            $transactionId = $paymentIntent->id;
            
            // Prepare payment details
            $paymentDetails = json_encode([
                'stripe_payment_intent_id' => $paymentIntent->id,
                'payment_method_id' => $paymentMethodId,
                'amount_received' => $paymentIntent->amount_received,
                'currency' => $paymentIntent->currency,
                'status' => $paymentIntent->status,
                'created' => $paymentIntent->created,
                'payment_processor' => 'Stripe'
            ]);
            
            $stmt = $conn->prepare("
                INSERT INTO payments (fine_id, user_id, amount, payment_method, receipt_number, transaction_id, payment_details)
                VALUES (?, ?, ?, 'stripe', ?, ?, ?)
            ");
            $stmt->bind_param("iddsss", $fineId, $fine['user_id'], $paymentAmount, $receiptNumber, $transactionId, $paymentDetails);
            $stmt->execute();

            // Mark fine as paid
            $updateStmt = $conn->prepare("UPDATE fines SET status = 'paid' WHERE id = ?");
            $updateStmt->bind_param("i", $fineId);
            $updateStmt->execute();

            $conn->commit();

            echo json_encode(['success' => true, 'message' => 'Payment successful']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Payment not completed']);
    }
    
} catch (\Stripe\Exception\CardException $e) {
    // Card was declined
    echo json_encode(['success' => false, 'error' => $e->getError()->message]);
    
} catch (\Stripe\Exception\RateLimitException $e) {
    // Too many requests made to the API too quickly
    echo json_encode(['success' => false, 'error' => 'Rate limit exceeded']);
    
} catch (\Stripe\Exception\RequestException $e) {
    // Invalid parameters were supplied to Stripe's API
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    
} catch (\Stripe\Exception\AuthenticationException $e) {
    // Authentication with Stripe's API failed
    echo json_encode(['success' => false, 'error' => 'Authentication failed']);
    
} catch (\Stripe\Exception\ApiConnectionException $e) {
    // Network communication with Stripe failed
    echo json_encode(['success' => false, 'error' => 'Network error']);
    
} catch (\Stripe\Exception\ApiErrorException $e) {
    echo json_encode(['success' => false, 'error' => 'Payment processing error: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Something else happened
    echo json_encode(['success' => false, 'error' => 'Unexpected error: ' . $e->getMessage()]);
}
?>