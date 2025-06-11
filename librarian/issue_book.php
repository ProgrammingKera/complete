<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Process book issue
$message = '';
$messageType = '';

// Issue book to user
if (isset($_POST['issue_book'])) {
    $bookId = (int)$_POST['book_id'];
    $userId = (int)$_POST['user_id'];
    $returnDate = $_POST['return_date'];
    
    // Basic validation
    if (empty($bookId) || empty($userId) || empty($returnDate)) {
        $message = "All fields are required.";
        $messageType = "danger";
    } else {
        // Check if book is available
        if (canIssueBook($conn, $bookId)) {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Create issued book record
                $stmt = $conn->prepare("
                    INSERT INTO issued_books (book_id, user_id, return_date)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("iis", $bookId, $userId, $returnDate);
                $stmt->execute();
                
                // Update book availability
                updateBookAvailability($conn, $bookId, 'issue');
                
                // Get book and user details for notification
                $bookTitle = getBookTitle($conn, $bookId);
                $userName = getUserName($conn, $userId);
                
                // Send notification to user
                $notificationMsg = "The book '{$bookTitle}' has been issued to you. Please return it by " . date('F j, Y', strtotime($returnDate)) . ".";
                sendNotification($conn, $userId, $notificationMsg);
                
                $conn->commit();
                
                $message = "Book issued successfully to {$userName}.";
                $messageType = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error issuing book: " . $e->getMessage();
                $messageType = "danger";
            }
        } else {
            $message = "This book is not available for issue.";
            $messageType = "danger";
        }
    }
}

// Get available books
$availableBooks = [];
$bookSql = "SELECT id, title, author, available_quantity FROM books WHERE available_quantity > 0 ORDER BY title";
$bookResult = $conn->query($bookSql);
if ($bookResult) {
    while ($row = $bookResult->fetch_assoc()) {
        $availableBooks[] = $row;
    }
}

// Get all users (except librarians)
$users = [];
$userSql = "SELECT id, name, email, role FROM users WHERE role != 'librarian' ORDER BY name";
$userResult = $conn->query($userSql);
if ($userResult) {
    while ($row = $userResult->fetch_assoc()) {
        $users[] = $row;
    }
}

// Get recently issued books for quick reference
$recentIssues = [];
$recentSql = "
    SELECT ib.*, b.title, b.author, u.name as user_name
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN users u ON ib.user_id = u.id
    WHERE ib.status = 'issued'
    ORDER BY ib.issue_date DESC
    LIMIT 10
";
$recentResult = $conn->query($recentSql);
if ($recentResult) {
    while ($row = $recentResult->fetch_assoc()) {
        $recentIssues[] = $row;
    }
}
?>

<h1 class="page-title">Issue Book</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="dashboard-row">
    <!-- Issue Book Form -->
    <div class="dashboard-col">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-hand-holding"></i> Issue New Book</h3>
            </div>
            <div class="card-body">
                <form action="" method="POST" id="issueBookForm">
                    <div class="form-group">
                        <label for="book_id">Select Book <span class="text-danger">*</span></label>
                        <select id="book_id" name="book_id" class="form-control" required>
                            <option value="">Choose a book...</option>
                            <?php foreach ($availableBooks as $book): ?>
                                <option value="<?php echo $book['id']; ?>" data-available="<?php echo $book['available_quantity']; ?>">
                                    <?php echo htmlspecialchars($book['title']); ?> by <?php echo htmlspecialchars($book['author']); ?>
                                    (<?php echo $book['available_quantity']; ?> available)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Only books with available copies are shown</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_id">Select User <span class="text-danger">*</span></label>
                        <select id="user_id" name="user_id" class="form-control" required>
                            <option value="">Choose a user...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" data-role="<?php echo $user['role']; ?>">
                                    <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>) - <?php echo ucfirst($user['role']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="issue_date">Issue Date</label>
                                <input type="date" id="issue_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="return_date">Return Date <span class="text-danger">*</span></label>
                                <input type="date" id="return_date" name="return_date" class="form-control" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="issue-summary" id="issueSummary" style="display: none;">
                        <div class="summary-card">
                            <h4>Issue Summary</h4>
                            <div class="summary-details">
                                <div class="summary-item">
                                    <span class="label">Book:</span>
                                    <span class="value" id="selectedBook">-</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">User:</span>
                                    <span class="value" id="selectedUser">-</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Return Date:</span>
                                    <span class="value" id="selectedReturnDate">-</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Days:</span>
                                    <span class="value" id="issueDays">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group text-right">
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                        <button type="submit" name="issue_book" class="btn btn-primary">
                            <i class="fas fa-hand-holding"></i> Issue Book
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Recently Issued Books -->
    <div class="dashboard-col">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recently Issued Books</h3>
                <span class="badge badge-primary"><?php echo count($recentIssues); ?></span>
            </div>
            <div class="card-body">
                <?php if (count($recentIssues) > 0): ?>
                    <div class="recent-issues-list">
                        <?php foreach ($recentIssues as $issue): ?>
                            <div class="recent-issue-item">
                                <div class="issue-book-info">
                                    <h4><?php echo htmlspecialchars($issue['title']); ?></h4>
                                    <p class="author">by <?php echo htmlspecialchars($issue['author']); ?></p>
                                </div>
                                <div class="issue-user-info">
                                    <p class="user-name">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($issue['user_name']); ?>
                                    </p>
                                    <p class="issue-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('M d, Y', strtotime($issue['issue_date'])); ?>
                                    </p>
                                    <p class="return-date">
                                        <i class="fas fa-calendar-alt"></i>
                                        Due: <?php echo date('M d, Y', strtotime($issue['return_date'])); ?>
                                    </p>
                                </div>
                                <div class="issue-status">
                                    <?php
                                    $today = new DateTime();
                                    $dueDate = new DateTime($issue['return_date']);
                                    $daysLeft = $today->diff($dueDate)->days;
                                    
                                    if ($today > $dueDate) {
                                        echo '<span class="badge badge-danger">Overdue</span>';
                                    } elseif ($daysLeft <= 3) {
                                        echo '<span class="badge badge-warning">Due Soon</span>';
                                    } else {
                                        echo '<span class="badge badge-success">Active</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="returns.php" class="btn btn-secondary">
                            <i class="fas fa-list"></i> View All Issued Books
                        </a>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book fa-3x"></i>
                        <p>No books issued recently</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bookSelect = document.getElementById('book_id');
    const userSelect = document.getElementById('user_id');
    const returnDateInput = document.getElementById('return_date');
    const issueSummary = document.getElementById('issueSummary');
    
    function updateSummary() {
        const selectedBook = bookSelect.options[bookSelect.selectedIndex];
        const selectedUser = userSelect.options[userSelect.selectedIndex];
        const returnDate = returnDateInput.value;
        
        if (bookSelect.value && userSelect.value && returnDate) {
            document.getElementById('selectedBook').textContent = selectedBook.text;
            document.getElementById('selectedUser').textContent = selectedUser.text;
            document.getElementById('selectedReturnDate').textContent = new Date(returnDate).toLocaleDateString();
            
            // Calculate days
            const today = new Date();
            const returnDateObj = new Date(returnDate);
            const diffTime = Math.abs(returnDateObj - today);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            document.getElementById('issueDays').textContent = diffDays + ' days';
            
            issueSummary.style.display = 'block';
        } else {
            issueSummary.style.display = 'none';
        }
    }
    
    bookSelect.addEventListener('change', updateSummary);
    userSelect.addEventListener('change', updateSummary);
    returnDateInput.addEventListener('change', updateSummary);
    
    // Add search functionality to selects
    function addSearchToSelect(selectElement) {
        const options = Array.from(selectElement.options);
        
        selectElement.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            options.forEach(option => {
                if (option.value === '') return; // Skip placeholder
                
                const text = option.text.toLowerCase();
                if (text.includes(searchTerm)) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
        });
    }
});
</script>

<style>
.issue-summary {
    margin-top: 20px;
    padding: 20px;
    background-color: var(--gray-100);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--primary-color);
}

.summary-card h4 {
    margin: 0 0 15px 0;
    color: var(--primary-color);
    font-weight: 600;
}

.summary-details {
    display: grid;
    gap: 10px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid var(--gray-300);
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-item .label {
    font-weight: 500;
    color: var(--text-light);
}

.summary-item .value {
    font-weight: 600;
    color: var(--text-color);
}

.recent-issues-list {
    max-height: 500px;
    overflow-y: auto;
}

.recent-issue-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    margin-bottom: 10px;
    background-color: var(--gray-100);
    border-radius: var(--border-radius);
    transition: var(--transition);
}

.recent-issue-item:hover {
    background-color: var(--gray-200);
}

.issue-book-info h4 {
    margin: 0 0 5px 0;
    font-size: 1em;
    font-weight: 600;
}

.issue-book-info .author {
    margin: 0;
    font-size: 0.9em;
    color: var(--text-light);
}

.issue-user-info p {
    margin: 0 0 5px 0;
    font-size: 0.8em;
    color: var(--text-light);
    display: flex;
    align-items: center;
    gap: 5px;
}

.issue-user-info .user-name {
    font-weight: 500;
    color: var(--text-color);
}

.issue-status {
    text-align: right;
}

.form-control {
    transition: var(--transition);
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.1);
}

@media (max-width: 768px) {
    .recent-issue-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .issue-status {
        text-align: left;
        width: 100%;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>