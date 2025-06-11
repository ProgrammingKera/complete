<?php
include_once '../includes/header.php';

// Check if user is student or faculty
if ($_SESSION['role'] != 'student' && $_SESSION['role'] != 'faculty') {
    header('Location: ../index.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Get all issued books for the user (current and returned)
$sql = "
    SELECT ib.*, b.title, b.author, b.isbn,
           DATEDIFF(CURRENT_DATE, ib.return_date) as days_overdue,
           CASE 
               WHEN ib.actual_return_date IS NULL AND CURRENT_DATE > ib.return_date THEN 'overdue'
               WHEN ib.actual_return_date IS NULL THEN 'issued'
               ELSE ib.status
           END as current_status,
           f.amount as fine_amount, f.status as fine_status
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    LEFT JOIN fines f ON ib.id = f.issued_book_id
    WHERE ib.user_id = ?
    ORDER BY 
        CASE WHEN ib.actual_return_date IS NULL THEN 0 ELSE 1 END,
        ib.return_date ASC,
        ib.issue_date DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$books = [];
while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}

// Separate books by status
$currentBooks = array_filter($books, function($book) { 
    return $book['actual_return_date'] === null; 
});
$returnedBooks = array_filter($books, function($book) { 
    return $book['actual_return_date'] !== null; 
});

// Count overdue books
$overdueBooks = array_filter($currentBooks, function($book) { 
    return $book['current_status'] == 'overdue'; 
});
?>

<div class="container">
    <h1 class="page-title">My Book Returns</h1>

    <!-- Quick Stats -->
    <div class="stats-container mb-4">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-book-open"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count($currentBooks); ?></div>
                <div class="stat-label">Currently Borrowed</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count($overdueBooks); ?></div>
                <div class="stat-label">Overdue Books</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count($returnedBooks); ?></div>
                <div class="stat-label">Returned Books</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-history"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count($books); ?></div>
                <div class="stat-label">Total Borrowed</div>
            </div>
        </div>
    </div>

    <!-- Currently Borrowed Books -->
    <?php if (count($currentBooks) > 0): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-book-open text-primary"></i> Currently Borrowed Books</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Book Details</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Days Left/Overdue</th>
                            <th>Status</th>
                            <th>Fine</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($currentBooks as $book): ?>
                            <tr class="<?php echo $book['current_status'] == 'overdue' ? 'table-danger' : ''; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($book['title']); ?></strong><br>
                                    <small class="text-muted">by <?php echo htmlspecialchars($book['author']); ?></small><br>
                                    <small class="text-muted">ISBN: <?php echo htmlspecialchars($book['isbn']); ?></small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($book['issue_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($book['return_date'])); ?></td>
                                <td>
                                    <?php 
                                    $today = new DateTime();
                                    $dueDate = new DateTime($book['return_date']);
                                    $diff = $today->diff($dueDate);
                                    
                                    if ($book['current_status'] == 'overdue') {
                                        echo '<span class="text-danger"><strong>' . $book['days_overdue'] . ' days overdue</strong></span>';
                                    } else {
                                        echo '<span class="text-success">' . $diff->days . ' days left</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($book['current_status'] == 'overdue'): ?>
                                        <span class="badge badge-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge badge-primary">Issued</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($book['fine_amount'] > 0) {
                                        $fineClass = ($book['fine_status'] == 'pending') ? 'text-danger' : 'text-success';
                                        echo '<span class="' . $fineClass . '">$' . number_format($book['fine_amount'], 2) . '</span><br>';
                                        echo '<small class="text-muted">(' . ucfirst($book['fine_status']) . ')</small>';
                                    } else {
                                        echo '<span class="text-muted">No fine</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (count($overdueBooks) > 0): ?>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Attention:</strong> You have <?php echo count($overdueBooks); ?> overdue book(s). 
                    Please return them as soon as possible to avoid additional fines.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Returned Books History -->
    <?php if (count($returnedBooks) > 0): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history text-success"></i> Return History</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Book Details</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Return Status</th>
                            <th>Fine</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($returnedBooks as $book): ?>
                            <?php 
                            $dueDate = new DateTime($book['return_date']);
                            $returnDate = new DateTime($book['actual_return_date']);
                            $wasLate = $returnDate > $dueDate;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($book['title']); ?></strong><br>
                                    <small class="text-muted">by <?php echo htmlspecialchars($book['author']); ?></small><br>
                                    <small class="text-muted">ISBN: <?php echo htmlspecialchars($book['isbn']); ?></small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($book['issue_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($book['return_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($book['actual_return_date'])); ?></td>
                                <td>
                                    <?php if ($wasLate): ?>
                                        <span class="badge badge-warning">Returned Late</span><br>
                                        <small class="text-muted">
                                            <?php 
                                            $lateDays = $returnDate->diff($dueDate)->days;
                                            echo $lateDays . ' day' . ($lateDays > 1 ? 's' : '') . ' late';
                                            ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="badge badge-success">On Time</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($book['fine_amount'] > 0) {
                                        $fineClass = $book['fine_status'] == 'pending' ? 'text-danger' : 'text-success';
                                        echo '<span class="' . $fineClass . '">$' . number_format($book['fine_amount'], 2) . '</span><br>';
                                        echo '<small class="text-muted">(' . ucfirst($book['fine_status']) . ')</small>';
                                    } else {
                                        echo '<span class="text-muted">No fine</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($books) == 0): ?>
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                <h3>No Borrowing History</h3>
                <p class="text-muted">You haven't borrowed any books yet.</p>
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

.table-danger {
    background-color: rgba(220, 53, 69, 0.1);
}

.table-danger:hover {
    background-color: rgba(220, 53, 69, 0.15);
}
</style>

<?php include_once '../includes/footer.php'; ?>