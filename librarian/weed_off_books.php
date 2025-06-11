<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Process weed-off operations
$message = '';
$messageType = '';

// Process book removal
if (isset($_POST['weed_off_books'])) {
    $bookIds = isset($_POST['book_ids']) ? $_POST['book_ids'] : [];
    $reason = trim($_POST['reason']);
    
    if (empty($bookIds)) {
        $message = "Please select at least one book to weed off.";
        $messageType = "danger";
    } elseif (empty($reason)) {
        $message = "Please provide a reason for weeding off the books.";
        $messageType = "danger";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create weed-off record
            foreach ($bookIds as $bookId) {
                // Check if book can be removed (not issued)
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM issued_books 
                    WHERE book_id = ? AND (status = 'issued' OR status = 'overdue')
                ");
                $stmt->bind_param("i", $bookId);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['count'] > 0) {
                    throw new Exception("Cannot remove book ID $bookId. It is currently issued to users.");
                }
                
                // Get book details for record
                $stmt = $conn->prepare("SELECT title FROM books WHERE id = ?");
                $stmt->bind_param("i", $bookId);
                $stmt->execute();
                $book = $stmt->get_result()->fetch_assoc();
                
                // Insert into weed_off_history
                $stmt = $conn->prepare("
                    INSERT INTO weed_off_history (book_id, book_title, reason, removed_by)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("issi", $bookId, $book['title'], $reason, $_SESSION['user_id']);
                $stmt->execute();
                
                // Delete book
                $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
                $stmt->bind_param("i", $bookId);
                $stmt->execute();
            }
            
            $conn->commit();
            
            $message = count($bookIds) . " book(s) have been successfully removed from the library.";
            $messageType = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error removing books: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Get books for weeding off consideration
$sql = "
    SELECT b.*, 
           COUNT(DISTINCT ib.id) as times_issued,
           MAX(ib.issue_date) as last_issued
    FROM books b
    LEFT JOIN issued_books ib ON b.id = ib.book_id
    GROUP BY b.id
    HAVING times_issued = 0 OR last_issued < DATE_SUB(NOW(), INTERVAL 2 YEAR)
    ORDER BY last_issued ASC, times_issued ASC
";
$result = $conn->query($sql);
$books = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
}

// Create weed_off_history table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS weed_off_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT,
    book_title VARCHAR(255) NOT NULL,
    reason TEXT NOT NULL,
    removed_by INT,
    removed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (removed_by) REFERENCES users(id)
)";
$conn->query($sql);
?>

<h1 class="page-title">Weed-Off Books</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h3>Books for Consideration</h3>
        <p class="text-muted">
            The following books are candidates for weeding off based on these criteria:
            <ul>
                <li>Never been issued</li>
                <li>Not been issued in the last 2 years</li>
            </ul>
        </p>
    </div>
    <div class="card-body">
        <form action="" method="POST" onsubmit="return confirmWeedOff()">
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Times Issued</th>
                            <th>Last Issued</th>
                            <th>Added to Library</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($books) > 0): ?>
                            <?php foreach ($books as $book): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="book_ids[]" value="<?php echo $book['id']; ?>" class="book-checkbox">
                                    </td>
                                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td><?php echo $book['times_issued']; ?></td>
                                    <td>
                                        <?php 
                                        if ($book['last_issued']) {
                                            echo date('M d, Y', strtotime($book['last_issued']));
                                        } else {
                                            echo 'Never';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($book['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No books found for weeding off consideration.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (count($books) > 0): ?>
                <div class="form-group mt-4">
                    <label for="reason">Reason for Removal <span class="text-danger">*</span></label>
                    <textarea id="reason" name="reason" class="form-control" rows="3" required></textarea>
                    <small class="text-muted">Please provide a detailed reason for removing these books from the library.</small>
                </div>
                
                <div class="form-group text-right">
                    <button type="submit" name="weed_off_books" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Remove Selected Books
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Weed-Off History</h3>
    </div>
    <div class="card-body">
        <?php
        $sql = "
            SELECT wh.*, u.name as librarian_name
            FROM weed_off_history wh
            JOIN users u ON wh.removed_by = u.id
            ORDER BY wh.removed_at DESC
        ";
        $result = $conn->query($sql);
        ?>
        
        <div class="table-container">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Book Title</th>
                        <th>Reason</th>
                        <th>Removed By</th>
                        <th>Removed On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['book_title']); ?></td>
                                <td><?php echo htmlspecialchars($row['reason']); ?></td>
                                <td><?php echo htmlspecialchars($row['librarian_name']); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($row['removed_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">No weed-off history found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Handle select all checkbox
document.getElementById('select-all').addEventListener('change', function() {
    const checkboxes = document.getElementsByClassName('book-checkbox');
    for (let checkbox of checkboxes) {
        checkbox.checked = this.checked;
    }
});

// Confirm weed-off action
function confirmWeedOff() {
    const checkboxes = document.getElementsByClassName('book-checkbox');
    let selectedCount = 0;
    for (let checkbox of checkboxes) {
        if (checkbox.checked) selectedCount++;
    }
    
    if (selectedCount === 0) {
        alert('Please select at least one book to remove.');
        return false;
    }
    
    return confirm(`Are you sure you want to permanently remove ${selectedCount} book(s) from the library? This action cannot be undone.`);
}
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>