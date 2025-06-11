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

// Get all fines for the user
$sql = "
    SELECT f.*, b.title, b.author, ib.return_date, ib.actual_return_date, ib.issue_date,
           p.payment_date, p.payment_method, p.receipt_number, p.transaction_id
    FROM fines f
    JOIN issued_books ib ON f.issued_book_id = ib.id
    JOIN books b ON ib.book_id = b.id
    LEFT JOIN payments p ON f.id = p.fine_id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$fines = [];
while ($row = $result->fetch_assoc()) {
    $fines[] = $row;
}

// Separate fines by status
$pendingFines = array_filter($fines, function($fine) { return $fine['status'] == 'pending'; });
$paidFines = array_filter($fines, function($fine) { return $fine['status'] == 'paid'; });

// Calculate totals
$totalPending = array_sum(array_column($pendingFines, 'amount'));
$totalPaid = array_sum(array_column($paidFines, 'amount'));
$totalFines = $totalPending + $totalPaid;
?>

<div class="container">
    <h1 class="page-title">My Fines</h1>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="stats-container mb-4">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number">PKR <?php echo number_format($totalPending, 2); ?></div>
                <div class="stat-label">Pending Fines</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number">PKR <?php echo number_format($totalPaid, 2); ?></div>
                <div class="stat-label">Paid Fines</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calculator"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number">PKR <?php echo number_format($totalFines, 2); ?></div>
                <div class="stat-label">Total Fines</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-list"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count($fines); ?></div>
                <div class="stat-label">Total Records</div>
            </div>
        </div>
    </div>

    <!-- Pending Fines -->
    <?php if (count($pendingFines) > 0): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle text-danger"></i> Pending Fines</h3>
            <div class="card-header-actions">
                <span class="badge badge-danger">Total: $<?php echo number_format($totalPending, 2); ?></span>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                <strong>Important:</strong> Please pay your pending fines to continue borrowing books from the library.
            </div>
            
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Book Details</th>
                            <th>Issue Period</th>
                            <th>Late Return</th>
                            <th>Fine Amount</th>
                            <th>Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingFines as $fine): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($fine['title']); ?></strong><br>
                                    <small class="text-muted">by <?php echo htmlspecialchars($fine['author']); ?></small>
                                </td>
                                <td>
                                    <strong>Issued:</strong> <?php echo date('M d, Y', strtotime($fine['issue_date'])); ?><br>
                                    <strong>Due:</strong> <?php echo date('M d, Y', strtotime($fine['return_date'])); ?>
                                </td>
                                <td>
                                    <strong>Returned:</strong> <?php echo date('M d, Y', strtotime($fine['actual_return_date'])); ?><br>
                                    <?php 
                                    $dueDate = new DateTime($fine['return_date']);
                                    $returnDate = new DateTime($fine['actual_return_date']);
                                    $lateDays = $returnDate->diff($dueDate)->days;
                                    ?>
                                    <span class="text-danger"><?php echo $lateDays; ?> day(s) late</span>
                                </td>
                                <td>
                                    <span class="text-danger font-weight-bold">$<?php echo number_format($fine['amount'], 2); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($fine['reason']); ?></td>
                                <td>
                                    <a href="payment.php?fine_id=<?php echo $fine['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-credit-card"></i> Pay Now
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Paid Fines -->
    <?php if (count($paidFines) > 0): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-check-circle text-success"></i> Payment History</h3>
            <div class="card-header-actions">
                <span class="badge badge-success">Total Paid: PKR <?php echo number_format($totalPaid, 2); ?></span>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Book Details</th>
                            <th>Fine Details</th>
                            <th>Payment Info</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paidFines as $fine): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($fine['title']); ?></strong><br>
                                    <small class="text-muted">by <?php echo htmlspecialchars($fine['author']); ?></small>
                                </td>
                                <td>
                                    <strong>Amount:</strong> $<?php echo number_format($fine['amount'], 2); ?><br>
                                    <strong>Reason:</strong> <?php echo htmlspecialchars($fine['reason']); ?><br>
                                    <small class="text-muted">Fine Date: <?php echo date('M d, Y', strtotime($fine['created_at'])); ?></small>
                                </td>
                                <td>
                                    <strong>Paid:</strong> <?php echo date('M d, Y H:i', strtotime($fine['payment_date'])); ?><br>
                                    <strong>Method:</strong> <?php echo ucwords(str_replace('_', ' ', $fine['payment_method'])); ?><br>
                                    <?php if ($fine['transaction_id']): ?>
                                        <strong>Transaction:</strong> <?php echo $fine['transaction_id']; ?><br>
                                    <?php endif; ?>
                                    <span class="badge badge-success">Paid</span>
                                </td>
                                <td>
                                    <strong><?php echo $fine['receipt_number']; ?></strong><br>
                                    <a href="payment_success.php?receipt=<?php echo $fine['receipt_number']; ?>&transaction=<?php echo $fine['transaction_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-receipt"></i> View Receipt
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($fines) == 0): ?>
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-smile fa-3x text-success mb-3"></i>
                <h3>No Fines!</h3>
                <p class="text-muted">You have no fines. Keep up the good work by returning books on time!</p>
                <a href="books.php" class="btn btn-primary">
                    <i class="fas fa-search"></i> Browse Books
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-card {
    background: var(--white);
    padding: 20px;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    display: flex;
    align-items: center;
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(13, 71, 161, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 1.5em;
    color: var(--primary-color);
}

.stat-info {
    flex: 1;
}

.stat-number {
    font-size: 1.8em;
    font-weight: 700;
    color: var(--primary-color);
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    color: var(--text-light);
    font-size: 0.9em;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header-actions {
    display: flex;
    gap: 10px;
}

.font-weight-bold {
    font-weight: 700;
}
</style>

<?php include_once '../includes/footer.php'; ?>