<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Process request approval/rejection
if (isset($_GET['request_id']) && isset($_GET['action'])) {
    $requestId = (int)$_GET['request_id'];
    $action = $_GET['action'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        if ($action === 'approve') {
            // Get request details
            $stmt = $conn->prepare("
                SELECT br.*, b.title, b.available_quantity, u.id as user_id, u.name as user_name 
                FROM book_requests br
                JOIN books b ON br.book_id = b.id
                JOIN users u ON br.user_id = u.id
                WHERE br.id = ?
            ");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $request = $result->fetch_assoc();
                
                if ($request['available_quantity'] > 0) {
                    // Update request status
                    $stmt = $conn->prepare("UPDATE book_requests SET status = 'approved' WHERE id = ?");
                    $stmt->bind_param("i", $requestId);
                    $stmt->execute();
                    
                    // Generate return date (14 days from now)
                    $returnDate = generateDueDate();
                    
                    // Create issued book record
                    $stmt = $conn->prepare("
                        INSERT INTO issued_books (book_id, user_id, return_date)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->bind_param("iis", $request['book_id'], $request['user_id'], $returnDate);
                    $stmt->execute();
                    
                    // Update book availability
                    updateBookAvailability($conn, $request['book_id'], 'issue');
                    
                    // Send notification to user
                    $notificationMsg = "Your request for '{$request['title']}' has been approved. Please collect the book from the library.";
                    sendNotification($conn, $request['user_id'], $notificationMsg);
                    
                    $message = "Request approved successfully.";
                    $messageType = "success";
                }
            }
        } elseif ($action === 'reject') {
            // Get request details for notification
            $stmt = $conn->prepare("
                SELECT br.*, b.title, u.id as user_id
                FROM book_requests br
                JOIN books b ON br.book_id = b.id
                JOIN users u ON br.user_id = u.id
                WHERE br.id = ?
            ");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $request = $result->fetch_assoc();
                
                // Update request status
                $stmt = $conn->prepare("UPDATE book_requests SET status = 'rejected' WHERE id = ?");
                $stmt->bind_param("i", $requestId);
                $stmt->execute();
                
                // Send notification to user
                $notificationMsg = "Your request for '{$request['title']}' has been rejected. Please contact the librarian for more information.";
                sendNotification($conn, $request['user_id'], $notificationMsg);
                
                $message = "Request rejected successfully.";
                $messageType = "success";
            }
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error processing request: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Get dashboard statistics
$totalBooks = getTotalBooks($conn);
$issuedBooks = getIssuedBooks($conn);
$totalUsers = getTotalUsers($conn);
$pendingRequests = getPendingRequests($conn);
$totalFines = getTotalUnpaidFines($conn);

// Get books due today
$today = date('Y-m-d');
$dueTodayBooks = [];
$sql = "
    SELECT ib.id, b.title, u.name as user_name, ib.issue_date, ib.return_date
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN users u ON ib.user_id = u.id
    WHERE DATE(ib.return_date) = CURRENT_DATE
    AND ib.status = 'issued'
    ORDER BY ib.return_date ASC
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dueTodayBooks[] = $row;
    }
}

// Get overdue books with correct calculation
$overdueBooks = [];
$sql = "
    SELECT ib.id, b.title, u.name AS user_name, ib.issue_date, ib.return_date,
           DATEDIFF(CURDATE(), ib.return_date) AS days_overdue,
           (DATEDIFF(CURDATE(), ib.return_date) * 1.00) AS fine_amount
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN users u ON ib.user_id = u.id
    WHERE ib.return_date < CURDATE()
      AND ib.status = 'issued'
      AND ib.actual_return_date IS NULL
    ORDER BY ib.return_date ASC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $overdueBooks[] = $row;
    }
} else {
    die("Query failed: " . $conn->error);
}

// Get recent activities
$recentActivities = [];
$sql = "
    (SELECT 'book_issued' as type, b.title, u.name as user_name, ib.issue_date as date
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN users u ON ib.user_id = u.id
    ORDER BY ib.issue_date DESC
    LIMIT 5)
    
    UNION
    
    (SELECT 'book_returned' as type, b.title, u.name as user_name, ib.actual_return_date as date
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN users u ON ib.user_id = u.id
    WHERE ib.status = 'returned'
    ORDER BY ib.actual_return_date DESC
    LIMIT 5)
    
    UNION
    
    (SELECT 'fine_paid' as type, CONCAT('Fine for ', b.title) as title, u.name as user_name, p.payment_date as date
    FROM payments p
    JOIN fines f ON p.fine_id = f.id
    JOIN issued_books ib ON f.issued_book_id = ib.id
    JOIN books b ON ib.book_id = b.id
    JOIN users u ON p.user_id = u.id
    ORDER BY p.payment_date DESC
    LIMIT 5)
    
    ORDER BY date DESC
    LIMIT 10
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentActivities[] = $row;
    }
}
?>

<h1 class="page-title">Librarian Dashboard</h1>

<?php if (isset($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-container">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-info">
            <div class="stat-number"><?php echo $totalBooks; ?></div>
            <div class="stat-label">Total Books</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-book-reader"></i>
        </div>
        <div class="stat-info">
            <div class="stat-number"><?php echo $issuedBooks; ?></div>
            <div class="stat-label">Issued Books</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <div class="stat-number"><?php echo $totalUsers; ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-info">
            <div class="stat-number">$<?php echo number_format($totalFines, 2); ?></div>
            <div class="stat-label">Pending Fines</div>
        </div>
    </div>
</div>

<div class="dashboard-row">
    <!-- Recent Activity -->
    <div class="dashboard-col">
        <div class="recent-activity">
            <div class="activity-header">
                <h3>Recent Activity</h3>
                <a href="#" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="activity-body">
                <ul class="activity-list">
                    <?php if (count($recentActivities) > 0): ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <?php if ($activity['type'] == 'book_issued'): ?>
                                        <i class="fas fa-hand-holding"></i>
                                    <?php elseif ($activity['type'] == 'book_returned'): ?>
                                        <i class="fas fa-undo"></i>
                                    <?php elseif ($activity['type'] == 'fine_paid'): ?>
                                        <i class="fas fa-money-bill-wave"></i>
                                    <?php else: ?>
                                        <i class="fas fa-info-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-info">
                                    <h4 class="activity-title">
                                        <?php 
                                        if ($activity['type'] == 'book_issued') {
                                            echo 'Book Issued: ' . htmlspecialchars($activity['title']);
                                        } elseif ($activity['type'] == 'book_returned') {
                                            echo 'Book Returned: ' . htmlspecialchars($activity['title']);
                                        } elseif ($activity['type'] == 'fine_paid') {
                                            echo htmlspecialchars($activity['title']);
                                        }
                                        ?>
                                    </h4>
                                    <div class="activity-meta">
                                        <span class="activity-time"><?php echo date('M d, Y', strtotime($activity['date'])); ?></span>
                                        <span class="activity-user"><?php echo htmlspecialchars($activity['user_name']); ?></span>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="activity-item">
                            <div class="activity-info">
                                <h4 class="activity-title">No recent activity</h4>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Pending Requests -->
    <div class="dashboard-col">
        <div class="card">
            <div class="card-header d-flex justify-between align-center">
                <h3>Pending Book Requests</h3>
                <span class="badge badge-warning"><?php echo $pendingRequests; ?></span>
            </div>
            <div class="card-body">
                <?php
                $sql = "
                    SELECT br.id, b.title, u.name as user_name, br.request_date
                    FROM book_requests br
                    JOIN books b ON br.book_id = b.id
                    JOIN users u ON br.user_id = u.id
                    WHERE br.status = 'pending'
                    ORDER BY br.request_date ASC
                    LIMIT 5
                ";
                $result = $conn->query($sql);
                ?>
                
                <?php if ($result && $result->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>User</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                                        <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['request_date'])); ?></td>
                                        <td>
                                            <a href="?request_id=<?php echo $row['id']; ?>&action=approve" style="margin-bottom:10px"; class="btn btn-sm btn-success">Approve</a>
                                            <a href="?request_id=<?php echo $row['id']; ?>&action=reject" class="btn btn-sm btn-danger">Reject</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($pendingRequests > 5): ?>
                        <div class="text-center mt-3">
                            <a href="requests.php" class="btn btn-primary">View All Requests</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-center">No pending book requests</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="dashboard-row">
    <!-- Books Due Today -->
    <div class="dashboard-col">
        <div class="card">
            <div class="card-header">
                <h3>Books Due Today</h3>
            </div>
            <div class="card-body">
                <?php if (count($dueTodayBooks) > 0): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>User</th>
                                    <th>Issue Date</th>
                                    <th>Due Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dueTodayBooks as $book): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($book['title']); ?></td>
                                        <td><?php echo htmlspecialchars($book['user_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($book['issue_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($book['return_date'])); ?></td>
                                        <td>
                                            <a href="process_return.php?id=<?php echo $book['id']; ?>" class="btn btn-sm btn-primary">Process Return</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center">No books due today</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Overdue Books -->
    <div class="dashboard-col">
        <div class="card">
            <div class="card-header">
                <h3>Overdue Books</h3>
            </div>
            <div class="card-body">
                <?php if (count($overdueBooks) > 0): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>User</th>
                                    <th>Due Date</th>
                                    <th>Days Overdue</th>
                                    <th>Fine Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overdueBooks as $book): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($book['title']); ?></td>
                                        <td><?php echo htmlspecialchars($book['user_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($book['return_date'])); ?></td>
                                        <td class="text-danger"><?php echo $book['days_overdue']; ?> days</td>
                                        <td class="text-danger">$<?php echo number_format($book['fine_amount'], 2); ?></td>
                                        <td>
                                            <a href="process_return.php?id=<?php echo $book['id']; ?>" class="btn btn-sm btn-primary">Process Return</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (count($overdueBooks) > 5): ?>
                        <div class="text-center mt-3">
                            <a href="overdue_books.php" class="btn btn-primary">View All Overdue Books</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-center">No overdue books</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>