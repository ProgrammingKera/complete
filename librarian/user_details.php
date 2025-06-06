<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Get user ID from URL
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: users.php');
    exit();
}

$user = $result->fetch_assoc();

// Get user's issued books
$stmt = $conn->prepare("
    SELECT ib.*, b.title, b.author
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    WHERE ib.user_id = ?
    ORDER BY ib.issue_date DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$issuedBooks = $stmt->get_result();

// Get user's fines
$stmt = $conn->prepare("
    SELECT f.*, b.title as book_title
    FROM fines f
    JOIN issued_books ib ON f.issued_book_id = ib.id
    JOIN books b ON ib.book_id = b.id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$fines = $stmt->get_result();
?>

<div class="d-flex justify-between align-center mb-4">
    <h1 class="page-title">User Details</h1>
    <div>
        <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Edit User
        </a>
        <a href="users.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
    </div>
</div>

<div class="dashboard-row" style="margin-top:30px";>
    <div class="dashboard-col">
        <div class="card">
            <div class="card-header">
                <h3>Personal Information</h3>
            </div>
            <div class="card-body">
                <div class="user-info">
                    <div class="info-item">
                        <label>Full Name:</label>
                        <span><?php echo htmlspecialchars($user['name']); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label>Email:</label>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label>Role:</label>
                        <span>
                            <?php 
                            switch ($user['role']) {
                                case 'student':
                                    echo '<span class="badge badge-primary">Student</span>';
                                    break;
                                case 'faculty':
                                    echo '<span class="badge badge-success">Faculty</span>';
                                    break;
                                case 'librarian':
                                    echo '<span class="badge badge-warning">Librarian</span>';
                                    break;
                            }
                            ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <label>Department:</label>
                        <span><?php echo htmlspecialchars($user['department'] ?: 'Not specified'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label>Phone:</label>
                        <span><?php echo htmlspecialchars($user['phone'] ?: 'Not specified'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label>Address:</label>
                        <span><?php echo htmlspecialchars($user['address'] ?: 'Not specified'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label>Account Created:</label>
                        <span><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-col">
        <div class="card">
            <div class="card-header">
                <h3>Account Statistics</h3>
            </div>
            <div class="card-body">
                <?php
                // Get statistics
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM issued_books WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $totalIssued = $stmt->get_result()->fetch_assoc()['total'];
                
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM issued_books WHERE user_id = ? AND status = 'overdue'");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $totalOverdue = $stmt->get_result()->fetch_assoc()['total'];
                
                $stmt = $conn->prepare("SELECT SUM(amount) as total FROM fines WHERE user_id = ? AND status = 'pending'");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $pendingFines = $stmt->get_result()->fetch_assoc()['total'] ?: 0;
                ?>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-label">Total Books Issued</div>
                        <div class="stat-value"><?php echo $totalIssued; ?></div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-label">Currently Overdue</div>
                        <div class="stat-value"><?php echo $totalOverdue; ?></div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-label">Pending Fines</div>
                        <div class="stat-value">$<?php echo number_format($pendingFines, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Recent Activity</h3>
            </div>
            <div class="card-body">
                <div class="activity-timeline">
                    <?php
                    $stmt = $conn->prepare("
                        (SELECT 'issued' as type, issue_date as date, b.title
                        FROM issued_books ib
                        JOIN books b ON ib.book_id = b.id
                        WHERE user_id = ?)
                        UNION
                        (SELECT 'returned' as type, actual_return_date as date, b.title
                        FROM issued_books ib
                        JOIN books b ON ib.book_id = b.id
                        WHERE user_id = ? AND actual_return_date IS NOT NULL)
                        ORDER BY date DESC
                        LIMIT 5
                    ");
                    $stmt->bind_param("ii", $userId, $userId);
                    $stmt->execute();
                    $activities = $stmt->get_result();
                    
                    if ($activities->num_rows > 0):
                        while ($activity = $activities->fetch_assoc()):
                    ?>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="fas fa-<?php echo $activity['type'] == 'issued' ? 'arrow-right' : 'undo'; ?>"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-title">
                                    <?php echo $activity['type'] == 'issued' ? 'Borrowed' : 'Returned'; ?>:
                                    <?php echo htmlspecialchars($activity['title']); ?>
                                </div>
                                <div class="timeline-date">
                                    <?php echo date('M j, Y', strtotime($activity['date'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <p class="text-center">No recent activity</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h3>Issued Books History</h3>
    </div>
    <div class="card-body" >
        <div class="table-container">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Book Title</th>
                        <th>Author</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Fine</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($issuedBooks->num_rows > 0): ?>
                        <?php while ($book = $issuedBooks->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
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
                                            echo '<span class="badge badge-primary">Issued</span>';
                                            break;
                                        case 'returned':
                                            echo '<span class="badge badge-success">Returned</span>';
                                            break;
                                        case 'overdue':
                                            echo '<span class="badge badge-danger">Overdue</span>';
                                            break;
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($book['fine_amount'] > 0) {
                                        echo '$' . number_format($book['fine_amount'], 2);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No books issued yet</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.user-info {
    display: grid;
    gap: 15px;
}

.info-item {
    display: flex;
    border-bottom: 1px solid var(--gray-200);
    padding-bottom: 10px;
}

.info-item label {
    font-weight: 600;
    width: 150px;
    color: var(--text-light);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.stat-item {
    background: var(--gray-100);
    padding: 15px;
    border-radius: var(--border-radius);
    text-align: center;
}

.stat-label {
    color: var(--text-light);
    font-size: 0.9em;
    margin-bottom: 5px;
}

.stat-value {
    font-size: 1.5em;
    font-weight: 600;
    color: var(--primary-color);
}

.activity-timeline {
    display: grid;
    gap: 15px;
}

.timeline-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

.timeline-icon {
    width: 30px;
    height: 30px;
    background: var(--primary-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.timeline-content {
    flex: 1;
}

.timeline-title {
    font-weight: 500;
}

.timeline-date {
    font-size: 0.9em;
    color: var(--text-light);
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>