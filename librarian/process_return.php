<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Get issued book ID from URL
$issuedBookId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$message = '';
$messageType = '';

// Get issued book details
$stmt = $conn->prepare("
    SELECT ib.*, b.title, b.author, u.id as user_id, u.name as user_name
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN users u ON ib.user_id = u.id
    WHERE ib.id = ? AND (ib.status = 'issued' OR ib.status = 'overdue')
");
$stmt->bind_param("i", $issuedBookId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: dashboard.php');
    exit();
}

$issuedBook = $result->fetch_assoc();

// Calculate days overdue and suggested fine
$today = new DateTime();
$dueDate = new DateTime($issuedBook['return_date']);
$daysOverdue = 0;
$suggestedFine = 0;

if ($today > $dueDate) {
    $diff = $today->diff($dueDate);
    $daysOverdue = $diff->days;
    $suggestedFine = $daysOverdue * 1.00; // $1 per day overdue
}

// Process return
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fineAmount = isset($_POST['fine_amount']) ? (float)$_POST['fine_amount'] : 0;
    $returnDate = date('Y-m-d');
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update issued book record
        $stmt = $conn->prepare("
            UPDATE issued_books 
            SET actual_return_date = ?, status = 'returned', fine_amount = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sdi", $returnDate, $fineAmount, $issuedBookId);
        $stmt->execute();
        
        // Update book availability
        updateBookAvailability($conn, $issuedBook['book_id'], 'return');
        
        // Create fine record if there's a fine
        if ($fineAmount > 0) {
            $stmt = $conn->prepare("
                INSERT INTO fines (issued_book_id, user_id, amount, reason)
                VALUES (?, ?, ?, ?)
            ");
            $reason = "Late return of book '{$issuedBook['title']}'";
            $stmt->bind_param("idds", $issuedBookId, $issuedBook['user_id'], $fineAmount, $reason);
            $stmt->execute();
            
            // Send notification to user about fine
            $notificationMsg = "You have been charged a fine of $" . number_format($fineAmount, 2) . " for late return of '{$issuedBook['title']}'. Please settle the payment at the library.";
            sendNotification($conn, $issuedBook['user_id'], $notificationMsg);
        }
        
        // Send notification to user about return
        $notificationMsg = "Your book '{$issuedBook['title']}' has been returned successfully.";
        sendNotification($conn, $issuedBook['user_id'], $notificationMsg);
        
        $conn->commit();
        
        $message = "Book returned successfully.";
        if ($fineAmount > 0) {
            $message .= " A fine of $" . number_format($fineAmount, 2) . " has been issued.";
        }
        $messageType = "success";
        
        // Redirect back to dashboard after 2 seconds
        echo "<script>setTimeout(function(){ window.location.href='dashboard.php'; }, 1000);</script>";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error processing return: " . $e->getMessage();
        $messageType = "danger";
    }
}
?>

<div class="container">
    <div class="d-flex justify-between align-center mb-4">
        <h1 class="page-title">Process Book Return</h1>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>Return Details</h3>
        </div>
        <div class="card-body">
            <div class="return-info">
                <div class="book-details">
                    <h4><?php echo htmlspecialchars($issuedBook['title']); ?></h4>
                    <p class="text-muted">by <?php echo htmlspecialchars($issuedBook['author']); ?></p>
                    <p><strong>Issued to:</strong> <?php echo htmlspecialchars($issuedBook['user_name']); ?></p>
                    <p><strong>Issue Date:</strong> <?php echo date('M d, Y', strtotime($issuedBook['issue_date'])); ?></p>
                    <p><strong>Due Date:</strong> <?php echo date('M d, Y', strtotime($issuedBook['return_date'])); ?></p>
                    <p><strong>Return Date:</strong> <?php echo date('M d, Y'); ?></p>
                </div>

                <?php if ($daysOverdue > 0): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Overdue Notice:</strong> This book is <strong><?php echo $daysOverdue; ?> days</strong> overdue.
                        Suggested fine: $<?php echo number_format($suggestedFine, 2); ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong>On Time Return:</strong> This book is being returned on time. No fine required.
                    </div>
                <?php endif; ?>

                <form action="" method="POST">
                    <div class="form-group">
                        <label for="return_date">Return Date</label>
                        <input type="date" id="return_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="fine_amount">Fine Amount ($)</label>
                        <input type="number" id="fine_amount" name="fine_amount" class="form-control" 
                               step="0.01" min="0" value="<?php echo $suggestedFine; ?>">
                        <small class="text-muted">Enter 0 if no fine is applicable</small>
                    </div>
                    
                    <div class="form-group text-right">
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Confirm Return
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.return-info {
    max-width: 600px;
    margin: 0 auto;
}

.book-details {
    background: var(--gray-100);
    padding: 20px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
}

.book-details h4 {
    color: var(--primary-color);
    margin-bottom: 10px;
}

.book-details p {
    margin-bottom: 8px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
}

.form-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--gray-300);
    border-radius: var(--border-radius);
    font-size: 1em;
    box-sizing: border-box;
}

.form-group input:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.1);
}

.text-right {
    text-align: right;
}

.btn {
    margin-left: 10px;
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>