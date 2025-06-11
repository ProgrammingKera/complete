<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Process return operations
$message = '';
$messageType = '';

// Process a book return
if (isset($_POST['process_return'])) {
    $id = (int)$_POST['issued_book_id'];
    $fineAmount = isset($_POST['fine_amount']) ? (float)$_POST['fine_amount'] : 0;
    $returnDate = date('Y-m-d');
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get issued book details
        $stmt = $conn->prepare("
            SELECT ib.*, b.title, u.id as user_id, u.name as user_name
            FROM issued_books ib
            JOIN books b ON ib.book_id = b.id
            JOIN users u ON ib.user_id = u.id
            WHERE ib.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $issuedBook = $result->fetch_assoc();
            
            // Update issued book record
            $stmt = $conn->prepare("
                UPDATE issued_books 
                SET actual_return_date = ?, status = 'returned', fine_amount = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sdi", $returnDate, $fineAmount, $id);
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
                $stmt->bind_param("idds", $id, $issuedBook['user_id'], $fineAmount, $reason);
                $stmt->execute();
                
                // Send notification to user about fine
                $notificationMsg = "You have been charged a fine of PKR" . number_format($fineAmount, 2) . " for late return of '{$issuedBook['title']}'. Please settle the payment at the library.";
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
        } else {
            throw new Exception("Issued book record not found.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error processing return: " . $e->getMessage();
        $messageType = "danger";
    }
}

$today = date('Y-m-d');
$updateFineSql = "
    UPDATE issued_books
    SET fine_amount = DATEDIFF(?, return_date) * 100.00
    WHERE status = 'issued' AND ? > return_date
";
$stmt = $conn->prepare($updateFineSql);
$stmt->bind_param("ss", $today, $today);
$stmt->execute();

// Handle search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';



// Build the query
$sql = "
    SELECT ib.*, b.title as book_title, u.name as user_name
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN users u ON ib.user_id = u.id
    WHERE 1=1
";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (b.title LIKE ? OR u.name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

if (!empty($status)) {
    $sql .= " AND ib.status = ?";
    $params[] = $status;
    $types .= "s";
}

$sql .= " ORDER BY ib.issue_date DESC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$issuedBooks = [];
while ($row = $result->fetch_assoc()) {
    $issuedBooks[] = $row;
}
?>

<h1 class="page-title">Book Returns</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="d-flex justify-between align-center mb-4">
    <div class="status-summary">
        <div class="badge-container">
            <?php
            // Get issued books counts by status
            $countSql = "SELECT status, COUNT(*) as count FROM issued_books GROUP BY status";
            $countResult = $conn->query($countSql);
            $counts = [
                'issued' => 0,
                'returned' => 0,
                'overdue' => 0
            ];
            
            if ($countResult) {
                while ($row = $countResult->fetch_assoc()) {
                    $counts[$row['status']] = $row['count'];
                }
            }
            ?>
            <span class="badge badge-primary">Issued: <?php echo $counts['issued']; ?></span>
            <span class="badge badge-success">Returned: <?php echo $counts['returned']; ?></span>
            <span class="badge badge-danger">Overdue: <?php echo $counts['overdue']; ?></span>
        </div>
    </div>
    
    <div class="d-flex">
        <form action="" method="GET" class="d-flex">
            <div class="form-group mr-2" style="margin-bottom: 0; margin-right: 10px;">
                <input type="text" name="search" placeholder="Search..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group mr-2" style="margin-bottom: 0; margin-right: 10px;">
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="issued" <?php echo $status == 'issued' ? 'selected' : ''; ?>>Issued</option>
                    <option value="returned" <?php echo $status == 'returned' ? 'selected' : ''; ?>>Returned</option>
                    <option value="overdue" <?php echo $status == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-search"></i> Search
            </button>
        </form>
    </div>
</div>

<div class="table-container" style="margin-top:30px";>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Book Title</th>
                <th>Issued To</th>
                <th>Issue Date</th>
                <th>Due Date</th>
                <th>Return Date</th>
                <th>Status</th>
                <th>Fine</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($issuedBooks) > 0): ?>
                <?php foreach ($issuedBooks as $book): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($book['book_title']); ?></td>
                        <td><?php echo htmlspecialchars($book['user_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($book['issue_date'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($book['return_date'])); ?></td>
                        <td>
                            <?php 
                            if ($book['actual_return_date']) {
                                echo date('M d, Y', strtotime($book['actual_return_date']));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            switch ($book['status']) {
                                case 'issued':
                                    // Check if overdue
                                    $today = new DateTime();
                                    $dueDate = new DateTime($book['return_date']);
                                    if ($today > $dueDate) {
                                        echo '<span class="badge badge-danger">Overdue</span>';
                                    } else {
                                        echo '<span class="badge badge-primary">Issued</span>';
                                    }
                                    break;
                                case 'returned':
                                    echo '<span class="badge badge-success">Returned</span>';
                                    break;
                                case 'overdue':
                                    echo '<span class="badge badge-danger">Overdue</span>';
                                    break;
                                default:
                                    echo htmlspecialchars($book['status']);
                            }
                            ?>
                        </td>
                        <td>
    <?php 
    if ($book['fine_amount'] > 0) {
        echo 'PKR ' . number_format($book['fine_amount'], 2);
    } else {
        // Calculate fine for overdue but not yet returned
        if ($book['status'] == 'issued') {
    $today = new DateTime();
    $dueDate = new DateTime($book['return_date']);
    if ($today > $dueDate) {
        $diff = $today->diff($dueDate);
        $daysOverdue = $diff->days;
        // Fix: always positive overdue days
        if ($diff->invert == 0) {
            $daysOverdue = 0;
        }
        $suggestedFine = $daysOverdue * 100.00; // $1 per day overdue
        echo '<span class="text-danger">PKR' . number_format($suggestedFine, 2) . ' (pending)</span>';
    } else {
        echo '-';
    }
} else {
    echo '-';
}
    }
    ?>
</td>
                        <td>
                            <?php if ($book['status'] != 'returned'): ?>
                                <button class="btn btn-sm btn-primary" data-modal-target="returnModal<?php echo $book['id']; ?>">
                                    <i class="fas fa-undo"></i> Process Return
                                </button>
                                
                                <!-- Return Modal -->
                                <div class="modal-overlay" id="returnModal<?php echo $book['id']; ?>">
                                    <div class="modal">
                                        <div class="modal-header">
                                            <h3 class="modal-title">Process Book Return</h3>
                                            <button class="modal-close">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <p>You are processing the return of <strong><?php echo htmlspecialchars($book['book_title']); ?></strong>
                                                issued to <strong><?php echo htmlspecialchars($book['user_name']); ?></strong>.</p>
                                            
                                            <?php
                                            // Calculate days overdue and suggested fine
                                            $suggestedFine = 0;
                                            $daysOverdue = 0;
                                            $today = new DateTime();
                                            $dueDate = new DateTime($book['return_date']);
                                            
                                            if ($today > $dueDate) {
                                                $diff = $today->diff($dueDate);
                                                $daysOverdue = $diff->days;
                                                $suggestedFine = $daysOverdue * 100.00; // $1 per day overdue
                                            }
                                            ?>
                                            
                                            <form action="" method="POST">
                                                <input type="hidden" name="issued_book_id" value="<?php echo $book['id']; ?>">
                                                
                                                <div class="form-group">
                                                    <label for="return_date<?php echo $book['id']; ?>">Return Date</label>
                                                    <input type="date" id="return_date<?php echo $book['id']; ?>" class="form-control" value="<?php echo date('Y-m-d'); ?>" readonly>
                                                </div>
                                                
                                                <?php if ($daysOverdue > 0): ?>
                                                    <div class="alert alert-warning">
                                                        This book is <strong><?php echo $daysOverdue; ?> days</strong> overdue.
                                                        Suggested fine: PKR<?php echo number_format($suggestedFine, 2); ?>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label for="fine_amount<?php echo $book['id']; ?>">Fine Amount (PKR)</label>
                                                        <input type="number" id="fine_amount<?php echo $book['id']; ?>" name="fine_amount" class="form-control" step="0.01" min="0" value="<?php echo $suggestedFine; ?>">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-success">
                                                        This book is being returned on time. No fine required.
                                                    </div>
                                                    <input type="hidden" name="fine_amount" value="0">
                                                <?php endif; ?>
                                                
                                                <div class="form-group text-right">
                                                    <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                                                    <button type="submit" name="process_return" class="btn btn-primary">Confirm Return</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">Already returned</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center">No issued books found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>