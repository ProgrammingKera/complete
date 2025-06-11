<?php
include_once '../includes/header.php';

// Check if user is student or faculty
if ($_SESSION['role'] != 'student' && $_SESSION['role'] != 'faculty') {
    header('Location: ../index.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Get current issued books
$currentlyIssued = [];
$sql = "
    SELECT ib.*, b.title, b.author
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    WHERE ib.user_id = ? AND (ib.status = 'issued' OR ib.status = 'overdue')
    ORDER BY ib.return_date ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $currentlyIssued[] = $row;
    }
}

// Get pending requests
$pendingRequests = [];
$sql = "
    SELECT br.*, b.title
    FROM book_requests br
    JOIN books b ON br.book_id = b.id
    WHERE br.user_id = ? AND br.status = 'pending'
    ORDER BY br.request_date DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pendingRequests[] = $row;
    }
}

// Get pending fines
$pendingFines = 0;
$sql = "SELECT SUM(amount) as total FROM fines WHERE user_id = ? AND status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $pendingFines = $row['total'] ?: 0;
}

// Get recent notifications
$notifications = [];
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}
?>

<div class="container">
    <h1 class="page-title">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>

    <!-- Stats Overview -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count($currentlyIssued); ?></div>
                <div class="stat-label">Books Borrowed</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count($pendingRequests); ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number">PKR <?php echo number_format($pendingFines, 2); ?></div>
                <div class="stat-label">Pending Fines</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-bell"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count($notifications); ?></div>
                <div class="stat-label">New Notifications</div>
            </div>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Currently Borrowed Books -->
        <div class="dashboard-col">
            <div class="card">
                <div class="card-header">
                    <h3>Currently Borrowed Books</h3>
                    <?php if (count($currentlyIssued) > 0): ?>
                        <a href="my_books.php" class="btn btn-sm btn-primary">View All</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (count($currentlyIssued) > 0): ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Book</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($currentlyIssued, 0, 3) as $book): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($book['return_date'])); ?></td>
                                            <td>
                                                <?php 
                                                $today = new DateTime();
                                                $dueDate = new DateTime($book['return_date']);
                                                if ($today > $dueDate) {
                                                    echo '<span class="badge badge-danger">Overdue</span>';
                                                } else {
                                                    echo '<span class="badge badge-primary">Issued</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">You haven't borrowed any books yet.</p>
                        <div class="text-center">
                            <a href="books.php" class="btn btn-primary">Browse Books</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Notifications -->
        <div class="dashboard-col">
            <div class="card">
                <div class="card-header">
                    <h3>Recent Notifications</h3>
                    <?php if (count($notifications) > 0): ?>
                        <a href="notifications.php" class="btn btn-sm btn-primary">View All</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (count($notifications) > 0): ?>
                        <div class="notification-list">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                    <div class="notification-message">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </div>
                                    <div class="notification-time">
                                        <?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No new notifications.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <div class="action-buttons">
            <a href="books.php" class="action-button">
                <i class="fas fa-search"></i>
                <span>Search Books</span>
            </a>
            <a href="ebooks.php" class="action-button">
                <i class="fas fa-file-pdf"></i>
                <span>E-Books</span>
            </a>
            <a href="my_books.php" class="action-button">
                <i class="fas fa-book-reader"></i>
                <span>My Books</span>
            </a>
            <a href="profile.php" class="action-button">
                <i class="fas fa-user-edit"></i>
                <span>Edit Profile</span>
            </a>
        </div>
    </div>
</div>

<style>
.quick-actions {
    margin-top: 30px;
}

.action-buttons {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.action-button {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    transition: var(--transition);
    color: var(--text-color);
    text-decoration: none;
}

.action-button:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    color: var(--primary-color);
}

.action-button i {
    font-size: 2em;
    margin-bottom: 10px;
    color: var(--primary-color);
}

.action-button span {
    font-weight: 500;
}

.notification-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.notification-item {
    padding: 10px;
    border-radius: var(--border-radius);
    background: var(--gray-100);
    transition: var(--transition);
}

.notification-item.unread {
    background: rgba(13, 71, 161, 0.1);
    border-left: 3px solid var(--primary-color);
}

.notification-message {
    margin-bottom: 5px;
}

.notification-time {
    font-size: 0.8em;
    color: var(--text-light);
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
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
</style>

<?php include_once '../includes/footer.php'; ?>