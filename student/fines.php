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
    <div class="fines-header">
        <div class="header-content">
            <h1 class="page-title">
                <i class="fas fa-money-bill-wave"></i> My Fines
            </h1>
            <p class="page-subtitle">Manage your library fines and payment history</p>
        </div>
        <?php if ($totalPending > 0): ?>
            <div class="urgent-notice">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Action Required</strong>
                    <p>You have $<?php echo number_format($totalPending, 2); ?> in pending fines</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Enhanced Stats -->
    <div class="fine-stats-grid">
        <div class="fine-stat-card pending">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number">$<?php echo number_format($totalPending, 2); ?></div>
                <div class="stat-label">Pending Fines</div>
                <div class="stat-count"><?php echo count($pendingFines); ?> fine<?php echo count($pendingFines) != 1 ? 's' : ''; ?></div>
            </div>
        </div>

        <div class="fine-stat-card paid">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number">$<?php echo number_format($totalPaid, 2); ?></div>
                <div class="stat-label">Paid Fines</div>
                <div class="stat-count"><?php echo count($paidFines); ?> payment<?php echo count($paidFines) != 1 ? 's' : ''; ?></div>
            </div>
        </div>

        <div class="fine-stat-card total">
            <div class="stat-icon">
                <i class="fas fa-calculator"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number">$<?php echo number_format($totalFines, 2); ?></div>
                <div class="stat-label">Total Fines</div>
                <div class="stat-count"><?php echo count($fines); ?> record<?php echo count($fines) != 1 ? 's' : ''; ?></div>
            </div>
        </div>

        <div class="fine-stat-card average">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number">$<?php echo count($fines) > 0 ? number_format($totalFines / count($fines), 2) : '0.00'; ?></div>
                <div class="stat-label">Average Fine</div>
                <div class="stat-count">Per incident</div>
            </div>
        </div>
    </div>

    <!-- Pending Fines -->
    <?php if (count($pendingFines) > 0): ?>
    <div class="fines-section pending-section">
        <div class="section-header">
            <div class="section-title">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Pending Fines</h3>
                <span class="section-badge pending">$<?php echo number_format($totalPending, 2); ?></span>
            </div>
            <div class="section-actions">
                <button class="btn btn-primary" onclick="payAllFines()">
                    <i class="fas fa-credit-card"></i> Pay All Fines
                </button>
            </div>
        </div>
        
        <div class="section-body">
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Important Notice:</strong> Please pay your pending fines to continue borrowing books from the library. 
                    Late payments may result in additional charges.
                </div>
            </div>
            
            <div class="fines-grid">
                <?php foreach ($pendingFines as $fine): ?>
                    <div class="fine-card pending">
                        <div class="fine-card-header">
                            <div class="book-title"><?php echo htmlspecialchars($fine['title']); ?></div>
                            <div class="fine-amount">$<?php echo number_format($fine['amount'], 2); ?></div>
                        </div>
                        
                        <div class="fine-card-body">
                            <div class="book-author">by <?php echo htmlspecialchars($fine['author']); ?></div>
                            
                            <div class="fine-details">
                                <div class="detail-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <div>
                                        <span class="detail-label">Issue Period</span>
                                        <span class="detail-value">
                                            <?php echo date('M d', strtotime($fine['issue_date'])); ?> - 
                                            <?php echo date('M d, Y', strtotime($fine['return_date'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <div>
                                        <span class="detail-label">Returned Late</span>
                                        <span class="detail-value">
                                            <?php echo date('M d, Y', strtotime($fine['actual_return_date'])); ?>
                                            <?php 
                                            $dueDate = new DateTime($fine['return_date']);
                                            $returnDate = new DateTime($fine['actual_return_date']);
                                            $lateDays = $returnDate->diff($dueDate)->days;
                                            ?>
                                            <span class="late-days">(<?php echo $lateDays; ?> day<?php echo $lateDays > 1 ? 's' : ''; ?> late)</span>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <i class="fas fa-info-circle"></i>
                                    <div>
                                        <span class="detail-label">Reason</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($fine['reason']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="fine-card-footer">
                            <a href="payment.php?fine_id=<?php echo $fine['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-credit-card"></i> Pay Now
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Payment History -->
    <?php if (count($paidFines) > 0): ?>
    <div class="fines-section paid-section">
        <div class="section-header">
            <div class="section-title">
                <i class="fas fa-check-circle"></i>
                <h3>Payment History</h3>
                <span class="section-badge paid">$<?php echo number_format($totalPaid, 2); ?></span>
            </div>
        </div>
        
        <div class="section-body">
            <div class="fines-grid">
                <?php foreach ($paidFines as $fine): ?>
                    <div class="fine-card paid">
                        <div class="fine-card-header">
                            <div class="book-title"><?php echo htmlspecialchars($fine['title']); ?></div>
                            <div class="fine-amount paid">$<?php echo number_format($fine['amount'], 2); ?></div>
                        </div>
                        
                        <div class="fine-card-body">
                            <div class="book-author">by <?php echo htmlspecialchars($fine['author']); ?></div>
                            
                            <div class="fine-details">
                                <div class="detail-item">
                                    <i class="fas fa-calendar-check"></i>
                                    <div>
                                        <span class="detail-label">Paid On</span>
                                        <span class="detail-value"><?php echo date('M d, Y H:i', strtotime($fine['payment_date'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <i class="fas fa-credit-card"></i>
                                    <div>
                                        <span class="detail-label">Payment Method</span>
                                        <span class="detail-value">
                                            <i class="fab fa-stripe"></i> 
                                            <?php echo ucwords(str_replace('_', ' ', $fine['payment_method'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if ($fine['transaction_id']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-receipt"></i>
                                    <div>
                                        <span class="detail-label">Transaction ID</span>
                                        <span class="detail-value transaction-id"><?php echo $fine['transaction_id']; ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="fine-card-footer">
                            <span class="status-badge paid">
                                <i class="fas fa-check"></i> Paid
                            </span>
                            <a href="payment_success.php?receipt=<?php echo $fine['receipt_number']; ?>&transaction=<?php echo $fine['transaction_id']; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-receipt"></i> View Receipt
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($fines) == 0): ?>
        <div class="no-fines-state">
            <div class="no-fines-icon">
                <i class="fas fa-smile"></i>
            </div>
            <h3>No Fines!</h3>
            <p>You have no fines. Keep up the good work by returning books on time!</p>
            <a href="books.php" class="btn btn-primary">
                <i class="fas fa-search"></i> Browse Books
            </a>
        </div>
    <?php endif; ?>
</div>

<style>
.fines-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: var(--white);
    padding: 40px 30px;
    border-radius: 15px;
    margin-bottom: 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.header-content .page-title {
    margin: 0 0 10px 0;
    font-size: 2.5em;
    font-weight: 700;
}

.page-subtitle {
    margin: 0;
    font-size: 1.1em;
    opacity: 0.9;
}

.urgent-notice {
    background: rgba(255, 193, 7, 0.2);
    border: 2px solid rgba(255, 193, 7, 0.5);
    border-radius: 10px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    color: var(--white);
}

.urgent-notice i {
    font-size: 2em;
    color: #ffc107;
}

.urgent-notice strong {
    display: block;
    font-size: 1.1em;
    margin-bottom: 5px;
}

.fine-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.fine-stat-card {
    background: var(--white);
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.fine-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.fine-stat-card.pending::before {
    background: linear-gradient(135deg, var(--warning-color), #ffb74d);
}

.fine-stat-card.paid::before {
    background: linear-gradient(135deg, var(--success-color), #66bb6a);
}

.fine-stat-card.total::before {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
}

.fine-stat-card.average::before {
    background: linear-gradient(135deg, #9c27b0, #ba68c8);
}

.fine-stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8em;
    color: var(--white);
}

.fine-stat-card.pending .stat-icon {
    background: linear-gradient(135deg, var(--warning-color), #ffb74d);
}

.fine-stat-card.paid .stat-icon {
    background: linear-gradient(135deg, var(--success-color), #66bb6a);
}

.fine-stat-card.total .stat-icon {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
}

.fine-stat-card.average .stat-icon {
    background: linear-gradient(135deg, #9c27b0, #ba68c8);
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 2.2em;
    font-weight: 700;
    color: var(--text-color);
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    color: var(--text-light);
    font-size: 1em;
    font-weight: 600;
    margin-bottom: 3px;
}

.stat-count {
    color: var(--text-light);
    font-size: 0.85em;
}

.fines-section {
    background: var(--white);
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    margin-bottom: 30px;
    overflow: hidden;
}

.section-header {
    background: var(--gray-100);
    padding: 25px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--gray-200);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 15px;
}

.section-title h3 {
    margin: 0;
    font-size: 1.4em;
    font-weight: 600;
    color: var(--text-color);
}

.section-title i {
    font-size: 1.3em;
    color: var(--primary-color);
}

.section-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9em;
}

.section-badge.pending {
    background: var(--warning-color);
    color: var(--white);
}

.section-badge.paid {
    background: var(--success-color);
    color: var(--white);
}

.section-body {
    padding: 30px;
}

.fines-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 25px;
}

.fine-card {
    background: var(--white);
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: var(--transition);
    border-left: 4px solid transparent;
}

.fine-card.pending {
    border-left-color: var(--warning-color);
}

.fine-card.paid {
    border-left-color: var(--success-color);
}

.fine-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.fine-card-header {
    padding: 20px 25px;
    background: var(--gray-100);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
}

.book-title {
    font-size: 1.1em;
    font-weight: 600;
    color: var(--primary-color);
    line-height: 1.3;
    flex: 1;
}

.fine-amount {
    font-size: 1.5em;
    font-weight: 700;
    color: var(--danger-color);
}

.fine-amount.paid {
    color: var(--success-color);
}

.fine-card-body {
    padding: 25px;
}

.book-author {
    color: var(--text-light);
    margin-bottom: 20px;
    font-style: italic;
}

.fine-details {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.detail-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.detail-item i {
    color: var(--primary-color);
    margin-top: 2px;
    width: 16px;
}

.detail-label {
    display: block;
    font-weight: 600;
    color: var(--text-color);
    font-size: 0.9em;
    margin-bottom: 2px;
}

.detail-value {
    display: block;
    color: var(--text-light);
    font-size: 0.9em;
}

.late-days {
    color: var(--danger-color);
    font-weight: 600;
}

.transaction-id {
    font-family: monospace;
    background: var(--gray-100);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.8em;
}

.fine-card-footer {
    padding: 20px 25px;
    background: var(--gray-100);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 0.85em;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
}

.status-badge.paid {
    background: var(--success-color);
    color: var(--white);
}

.no-fines-state {
    text-align: center;
    padding: 80px 20px;
    background: var(--white);
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.no-fines-icon {
    font-size: 4em;
    color: var(--success-color);
    margin-bottom: 20px;
}

.no-fines-state h3 {
    color: var(--text-color);
    margin-bottom: 15px;
    font-size: 1.8em;
}

.no-fines-state p {
    color: var(--text-light);
    margin-bottom: 30px;
    font-size: 1.1em;
}

@media (max-width: 768px) {
    .fines-header {
        flex-direction: column;
        text-align: center;
        padding: 30px 20px;
    }
    
    .header-content .page-title {
        font-size: 2em;
    }
    
    .urgent-notice {
        flex-direction: column;
        text-align: center;
    }
    
    .fine-stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }
    
    .fine-stat-card {
        padding: 20px;
        flex-direction: column;
        text-align: center;
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        font-size: 1.5em;
    }
    
    .section-header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
        padding: 20px;
    }
    
    .section-body {
        padding: 20px;
    }
    
    .fines-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .fine-card-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .fine-card-footer {
        flex-direction: column;
        gap: 15px;
    }
    
    .fine-card-footer .btn {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .fine-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-number {
        font-size: 1.8em;
    }
    
    .fine-card-header,
    .fine-card-body,
    .fine-card-footer {
        padding: 15px 20px;
    }
}
</style>

<script>
function payAllFines() {
    // In a real implementation, this would handle multiple fine payments
    alert('Multiple fine payment functionality would be implemented here.');
}

// Add smooth scrolling for better UX
document.addEventListener('DOMContentLoaded', function() {
    // Animate stat cards on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe all stat cards
    document.querySelectorAll('.fine-stat-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>