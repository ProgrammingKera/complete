<?php
include_once '../includes/header.php';

// Check if user is student or faculty
if ($_SESSION['role'] != 'student' && $_SESSION['role'] != 'faculty') {
    header('Location: ../index.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Get all books (current and history)
$sql = "
    SELECT ib.*, b.title, b.author,
           DATEDIFF(CURRENT_DATE, ib.return_date) as days_overdue,
           f.amount as fine_amount, f.status as fine_status
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    LEFT JOIN fines f ON ib.id = f.issued_book_id
    WHERE ib.user_id = ?
    ORDER BY ib.issue_date DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$books = [];
while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}

// Get pending fines
$sql = "
    SELECT f.*, b.title, ib.return_date, ib.actual_return_date
    FROM fines f
    JOIN issued_books ib ON f.issued_book_id = ib.id
    JOIN books b ON ib.book_id = b.id
    WHERE f.user_id = ? AND f.status = 'pending'
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

// Calculate total pending fines
$totalFines = 0;
foreach ($fines as $fine) {
    $totalFines += $fine['amount'];
}
?>

<div class="container">
    <h1 class="page-title">My Books</h1>

    <?php if ($totalFines > 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            You have pending fines totaling $<?php echo number_format($totalFines, 2); ?>. 
            Please visit the library to settle your fines.
        </div>
    <?php endif; ?>

    <!-- Current Books -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Currently Borrowed Books</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Fine</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $hasCurrentBooks = false;
                        foreach ($books as $book):
                            if ($book['status'] != 'returned'):
                                $hasCurrentBooks = true;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($book['issue_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($book['return_date'])); ?></td>
                                <td>
                                    <?php if ($book['days_overdue'] > 0): ?>
                                        <span class="badge badge-danger">Overdue by <?php echo $book['days_overdue']; ?> days</span>
                                    <?php else: ?>
                                        <span class="badge badge-primary">Issued</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($book['fine_amount'] > 0) {
                                        echo '<span class="text-danger">$' . number_format($book['fine_amount'], 2) . '</span>';
                                        if ($book['fine_status'] == 'pending') {
                                            echo ' (Pending)';
                                        } else {
                                            echo ' (Paid)';
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php 
                            endif;
                        endforeach;
                        if (!$hasCurrentBooks):
                        ?>
                            <tr>
                                <td colspan="6" class="text-center">No books currently borrowed.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pending Fines -->
    <?php if (count($fines) > 0): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3>Pending Fines</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Days Late</th>
                            <th>Fine Amount</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fines as $fine): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fine['title']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($fine['return_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($fine['actual_return_date'])); ?></td>
                                <td>
                                    <?php
                                    $dueDate = new DateTime($fine['return_date']);
                                    $returnDate = new DateTime($fine['actual_return_date']);
                                    $diff = $returnDate->diff($dueDate);
                                    echo $diff->days . ' days';
                                    ?>
                                </td>
                                <td class="text-danger">$<?php echo number_format($fine['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($fine['reason']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Borrowing History -->
    <div class="card">
        <div class="card-header">
            <h3>Borrowing History</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Fine</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $hasHistory = false;
                        foreach ($books as $book):
                            if ($book['status'] == 'returned'):
                                $hasHistory = true;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($book['issue_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($book['return_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($book['actual_return_date'])); ?></td>
                                <td>
                                    <?php 
                                    if ($book['fine_amount'] > 0) {
                                        echo '<span class="text-danger">$' . number_format($book['fine_amount'], 2) . '</span>';
                                        if ($book['fine_status'] == 'pending') {
                                            echo ' (Pending)';
                                        } else {
                                            echo ' (Paid)';
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php 
                            endif;
                        endforeach;
                        if (!$hasHistory):
                        ?>
                            <tr>
                                <td colspan="6" class="text-center">No borrowing history found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>