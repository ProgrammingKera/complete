<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Get book ID from URL
$bookId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get book details
$stmt = $conn->prepare("
    SELECT b.*, 
           COUNT(DISTINCT ib.id) as times_issued,
           COUNT(DISTINCT CASE WHEN ib.status = 'issued' OR ib.status = 'overdue' THEN ib.id END) as currently_issued
    FROM books b
    LEFT JOIN issued_books ib ON b.id = ib.book_id
    WHERE b.id = ?
    GROUP BY b.id
");
$stmt->bind_param("i", $bookId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: books.php');
    exit();
}

$book = $result->fetch_assoc();

// Get issue history
$stmt = $conn->prepare("
    SELECT ib.*, u.name as user_name
    FROM issued_books ib
    JOIN users u ON ib.user_id = u.id
    WHERE ib.book_id = ?
    ORDER BY ib.issue_date DESC
");
$stmt->bind_param("i", $bookId);
$stmt->execute();
$issueHistory = $stmt->get_result();

// Process book update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_book'])) {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $isbn = trim($_POST['isbn']);
    $publisher = trim($_POST['publisher']);
    $year = trim($_POST['year']);
    $category = trim($_POST['category']);
    $quantity = (int)$_POST['quantity'];
    $shelf = trim($_POST['shelf']);
    $description = trim($_POST['description']);
    
    // Handle cover image upload
    $coverImage = $book['cover_image']; // Keep existing image by default
    
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] == 0) {
        $allowed = array('jpg', 'jpeg', 'png', 'gif');
        $filename = $_FILES['cover']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($ext), $allowed)) {
            $newFilename = uniqid() . '.' . $ext;
            $uploadDir = '../uploads/covers/';
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $uploadFile = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['cover']['tmp_name'], $uploadFile)) {
                // Delete old cover image if it exists
                if (!empty($book['cover_image']) && file_exists($book['cover_image'])) {
                    unlink($book['cover_image']);
                }
                $coverImage = $uploadFile;
            }
        }
    }
    
    // Update book
    $stmt = $conn->prepare("
        UPDATE books 
        SET title = ?, author = ?, isbn = ?, publisher = ?, 
            publication_year = ?, category = ?, total_quantity = ?,
            shelf_location = ?, description = ?, cover_image = ?
        WHERE id = ?
    ");
    
    $stmt->bind_param(
    "sssssissssi",
    $title, $author, $isbn, $publisher, $year, 
    $category, $quantity, $shelf, $description, $coverImage, $bookId
);
    
    if ($stmt->execute()) {
        $message = "Book updated successfully.";
        $messageType = "success";

        // Refresh book details with calculated fields
        $stmt = $conn->prepare("
            SELECT b.*, 
                   COUNT(DISTINCT ib.id) as times_issued,
                   COUNT(DISTINCT CASE WHEN ib.status = 'issued' OR ib.status = 'overdue' THEN ib.id END) as currently_issued
            FROM books b
            LEFT JOIN issued_books ib ON b.id = ib.book_id
            WHERE b.id = ?
            GROUP BY b.id
        ");
        $stmt->bind_param("i", $bookId);
        $stmt->execute();
        $result = $stmt->get_result();
        $book = $result->fetch_assoc();
    } else {
        $message = "Error updating book: " . $stmt->error;
        $messageType = "danger";
    }
}
?>

<div class="d-flex justify-between align-center mb-4">
    <h1 class="page-title">Book Details</h1>
    <a href="books.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Books
    </a>
</div>

<?php if (isset($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="book-details-container">
    <div class="book-info-card">
        <div class="book-cover">
            <?php if (!empty($book['cover_image'])): ?>
                <img src="<?php echo htmlspecialchars($book['cover_image']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
            <?php else: ?>
                <div class="no-cover">
                    <i class="fas fa-book fa-4x"></i>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="book-stats">
            <div class="stat-item">
                <span class="stat-label">Total Quantity</span>
                <span class="stat-value"><?php echo $book['total_quantity']; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Available</span>
                <span class="stat-value"><?php echo $book['available_quantity']; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Times Issued</span>
                <span class="stat-value"><?php echo $book['times_issued']; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Currently Issued</span>
                <span class="stat-value"><?php echo $book['currently_issued']; ?></span>
            </div>
        </div>
    </div>
    
    <div class="book-details-form">
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($book['title']); ?>" required>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="author">Author</label>
                        <input type="text" id="author" name="author" value="<?php echo htmlspecialchars($book['author']); ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="isbn">ISBN</label>
                        <input type="text" id="isbn" name="isbn" value="<?php echo htmlspecialchars($book['isbn']); ?>">
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="publisher">Publisher</label>
                        <input type="text" id="publisher" name="publisher" value="<?php echo htmlspecialchars($book['publisher']); ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="year">Publication Year</label>
                        <input type="number" id="year" name="year" value="<?php echo $book['publication_year']; ?>">
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" value="<?php echo htmlspecialchars($book['category']); ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="quantity">Total Quantity</label>
                        <input type="number" id="quantity" name="quantity" value="<?php echo $book['total_quantity']; ?>" required>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="shelf">Shelf Location</label>
                        <input type="text" id="shelf" name="shelf" value="<?php echo htmlspecialchars($book['shelf_location']); ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="cover">Book Cover Image</label>
                <input type="file" id="cover" name="cover" class="form-control" accept="image/*">
                <small class="text-muted">Leave empty to keep current cover. Supported formats: JPG, PNG, GIF. Max size: 2MB</small>
                <?php if (!empty($book['cover_image'])): ?>
                    <div class="current-cover-preview">
                        <p><strong>Current Cover:</strong></p>
                        <img src="<?php echo htmlspecialchars($book['cover_image']); ?>" alt="Current cover" style="max-width: 100px; height: auto; border-radius: 5px;">
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($book['description']); ?></textarea>
            </div>
            
            <div class="form-group text-right">
                <button type="submit" name="update_book" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Book
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h3>Issue History</h3>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Issued To</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Fine</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($issueHistory->num_rows > 0): ?>
                        <?php while ($issue = $issueHistory->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($issue['user_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($issue['issue_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($issue['return_date'])); ?></td>
                                <td>
                                    <?php 
                                    if ($issue['actual_return_date']) {
                                        echo date('M d, Y', strtotime($issue['actual_return_date']));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    switch ($issue['status']) {
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
                                    if ($issue['fine_amount'] > 0) {
                                        echo '$' . number_format($issue['fine_amount'], 2);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No issue history found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.book-details-container {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.book-info-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.book-cover {
    height: 300px;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.book-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-cover {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #999;
}

.book-stats {
    padding: 20px;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.stat-item {
    text-align: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
}

.stat-label {
    display: block;
    font-size: 0.9em;
    color: #666;
    margin-bottom: 5px;
}

.stat-value {
    display: block;
    font-size: 1.5em;
    font-weight: 600;
    color: #0d47a1;
}

.book-details-form {
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.book-details-form input,
.book-details-form textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 0.9em;
    box-sizing: border-box;
}

.current-cover-preview {
    margin-top: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
}

.current-cover-preview p {
    margin: 0 0 10px 0;
    font-size: 0.9em;
    color: #666;
}

@media (max-width: 992px) {
    .book-details-container {
        grid-template-columns: 1fr;
    }
    
    .book-info-card {
        max-width: 400px;
        margin: 0 auto;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>