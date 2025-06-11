<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Get dashboard statistics
$totalBooks = getTotalBooks($conn);
$issuedBooks = getIssuedBooks($conn);
$totalUsers = getTotalUsers($conn);
$pendingRequests = getPendingRequests($conn);
$totalFines = getTotalUnpaidFines($conn);

// Get recent activities (limited to 5 with scroll)
$recentActivities = [];
$sql = "
    (SELECT 'book_issued' as type, b.title as title, u.name as user_name, ib.issue_date as date
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN users u ON ib.user_id = u.id
    ORDER BY ib.issue_date DESC
    LIMIT 10)
    
    UNION
    
    (SELECT 'book_returned' as type, b.title as title, u.name as user_name, ib.actual_return_date as date
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN users u ON ib.user_id = u.id
    WHERE ib.status = 'returned'
    ORDER BY ib.actual_return_date DESC
    LIMIT 10)
    
    UNION
    
    (SELECT 'fine_paid' as type, CONCAT('Fine for ', b.title) as title, u.name as user_name, p.payment_date as date
    FROM payments p
    JOIN fines f ON p.fine_id = f.id
    JOIN issued_books ib ON f.issued_book_id = ib.id
    JOIN books b ON ib.book_id = b.id
    JOIN users u ON p.user_id = u.id
    ORDER BY p.payment_date DESC
    LIMIT 10)
    
    ORDER BY date DESC
    LIMIT 20
";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentActivities[] = $row;
    }
}

// Get books due for return today
$today = date('Y-m-d');
$dueTodayBooks = [];
$sql = "
    SELECT ib.id, b.title, u.name as user_name, ib.issue_date, ib.return_date
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN users u ON ib.user_id = u.id
    WHERE ib.return_date = ? AND ib.status = 'issued'
    ORDER BY ib.return_date ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dueTodayBooks[] = $row;
    }
}

// Get overdue books
$overdueBooks = [];
$sql = "
    SELECT ib.id, b.title, u.name as user_name, ib.issue_date, ib.return_date, 
           DATEDIFF(CURRENT_DATE, ib.return_date) as days_overdue
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN users u ON ib.user_id = u.id
    WHERE ib.return_date < CURRENT_DATE AND ib.status = 'issued'
    ORDER BY ib.return_date ASC
    LIMIT 5
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $overdueBooks[] = $row;
    }
}
?>

<h1 class="page-title">Librarian Dashboard</h1>

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
            <div class="stat-number">Rs <?php echo number_format($totalFines, 2); ?></div>
            <div class="stat-label">Pending Fines</div>
        </div>
    </div>
</div>

<div class="dashboard-row">
    <!-- Recent Activity with Scroll -->
    <div class="dashboard-col">
        <div class="recent-activity">
            <div class="activity-header">
                <h3>Recent Activity</h3>
                <span class="activity-count"><?php echo count($recentActivities); ?> activities</span>
            </div>
            <div class="activity-body">
                <div class="activity-scroll-container">
                    <ul class="activity-list">
                        <?php if (count($recentActivities) > 0): ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <li class="activity-item">
                                    <div class="activity-icon">
                                        <?php if ($activity['type'] == 'book_issued'): ?>
                                            <i class="fas fa-hand-holding" style="color: #4caf50;"></i>
                                        <?php elseif ($activity['type'] == 'book_returned'): ?>
                                            <i class="fas fa-undo" style="color: #2196f3;"></i>
                                        <?php elseif ($activity['type'] == 'fine_paid'): ?>
                                            <i class="fas fa-money-bill-wave" style="color: #ff9800;"></i>
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
                                            <span class="activity-time">
                                                <i class="fas fa-clock"></i>
                                                <?php echo date('M d, Y H:i', strtotime($activity['date'])); ?>
                                            </span>
                                            <span class="activity-user">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($activity['user_name']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="activity-item">
                                <div class="activity-info">
                                    <h4 class="activity-title">No recent activity</h4>
                                    <p class="text-muted">Activities will appear here as they happen</p>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
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
                                            <a href="requests.php?id=<?php echo $row['id']; ?>&action=approve" class="btn btn-sm btn-success">Approve</a>
                                            <a href="requests.php?id=<?php echo $row['id']; ?>&action=reject" class="btn btn-sm btn-danger">Reject</a>
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
                    <div class="empty-state">
                        <i class="fas fa-bookmark fa-3x"></i>
                        <p>No pending book requests</p>
                    </div>
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
                <span class="badge badge-warning"><?php echo count($dueTodayBooks); ?></span>
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
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dueTodayBooks as $book): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($book['title']); ?></td>
                                        <td><?php echo htmlspecialchars($book['user_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($book['issue_date'])); ?></td>
                                        <td>
                                            <a href="returns.php?id=<?php echo $book['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-undo"></i> Process Return
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check fa-3x"></i>
                        <p>No books due today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Overdue Books -->
    <div class="dashboard-col">
        <div class="card">
            <div class="card-header">
                <h3>Overdue Books</h3>
                <span class="badge badge-danger"><?php echo count($overdueBooks); ?></span>
            </div>
            <div class="card-body">
                <?php if (count($overdueBooks) > 0): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>User</th>
                                    <th>Days Overdue</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overdueBooks as $book): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($book['title']); ?></td>
                                        <td><?php echo htmlspecialchars($book['user_name']); ?></td>
                                        <td>
                                            <span class="badge badge-danger"><?php echo $book['days_overdue']; ?> days</span>
                                        </td>
                                        <td>
                                            <a href="returns.php?id=<?php echo $book['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-undo"></i> Return
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (count($overdueBooks) >= 5): ?>
                        <div class="text-center mt-3">
                            <a href="returns.php?status=overdue" class="btn btn-primary">View All Overdue Books</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle fa-3x"></i>
                        <p>No overdue books</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.activity-scroll-container {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 5px;
}

.activity-scroll-container::-webkit-scrollbar {
    width: 6px;
}

.activity-scroll-container::-webkit-scrollbar-track {
    background: var(--gray-200);
    border-radius: 3px;
}

.activity-scroll-container::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 3px;
}

.activity-scroll-container::-webkit-scrollbar-thumb:hover {
    background: var(--primary-dark);
}

.activity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.activity-count {
    font-size: 0.9em;
    color: var(--text-light);
}

.activity-meta {
    display: flex;
    flex-direction: column;
    gap: 5px;
    font-size: 0.8em;
    color: var(--text-light);
}

.activity-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-light);
}

.empty-state i {
    margin-bottom: 15px;
    opacity: 0.5;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>