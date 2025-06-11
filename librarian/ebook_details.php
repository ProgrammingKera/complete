<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Get e-book ID from URL
$ebookId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Process e-book update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    
    // Basic validation
    if (empty($title) || empty($author)) {
        $message = "Title and author are required fields.";
        $messageType = "danger";
    } else {
        // Update e-book record
        $stmt = $conn->prepare("
            UPDATE ebooks 
            SET title = ?, author = ?, category = ?, description = ?
            WHERE id = ?
        ");
        
        $stmt->bind_param("ssssi", $title, $author, $category, $description, $ebookId);
        
        if ($stmt->execute()) {
            $message = "E-book updated successfully.";
            $messageType = "success";
        } else {
            $message = "Error updating e-book: " . $stmt->error;
            $messageType = "danger";
        }
    }
}

// Get e-book details
$stmt = $conn->prepare("
    SELECT e.*, u.name as uploader_name 
    FROM ebooks e
    LEFT JOIN users u ON e.uploaded_by = u.id
    WHERE e.id = ?
");
$stmt->bind_param("i", $ebookId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: e-books.php');
    exit();
}

$ebook = $result->fetch_assoc();
?>

<div class="container">
    <div class="d-flex justify-between align-center mb-4">
        <h1 class="page-title">E-book Details</h1>
        <a href="e-books.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to E-books
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="ebook-details">
        <div class="ebook-header">
            <div>
                <h2 class="ebook-title"><?php echo htmlspecialchars($ebook['title']); ?></h2>
                <div class="ebook-meta">
                    <p>Uploaded by <?php echo htmlspecialchars($ebook['uploader_name']); ?> on 
                       <?php echo date('F j, Y', strtotime($ebook['created_at'])); ?></p>
                </div>
            </div>
            <div class="ebook-actions">
                <a href="<?php echo htmlspecialchars($ebook['file_path']); ?>" class="btn btn-primary" target="_blank">
                    <i class="fas fa-download"></i> Download
                </a>
            </div>
        </div>

        <div class="file-info">
            <div class="file-info-item">
                <span class="file-info-label">File Type</span>
                <span class="file-info-value">
                    <i class="fas fa-file-<?php echo strtolower($ebook['file_type']) == 'pdf' ? 'pdf' : 'alt'; ?>"></i>
                    <?php echo strtoupper($ebook['file_type']); ?>
                </span>
            </div>
            <div class="file-info-item">
                <span class="file-info-label">File Size</span>
                <span class="file-info-value"><?php echo htmlspecialchars($ebook['file_size']); ?></span>
            </div>
            <div class="file-info-item">
                <span class="file-info-label">Category</span>
                <span class="file-info-value"><?php echo htmlspecialchars($ebook['category']); ?></span>
            </div>
        </div>

        <form action="" method="POST">
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" class="form-control" 
                               value="<?php echo htmlspecialchars($ebook['title']); ?>" required>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="author">Author</label>
                        <input type="text" id="author" name="author" class="form-control"
                               value="<?php echo htmlspecialchars($ebook['author']); ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="category">Category</label>
                <input type="text" id="category" name="category" class="form-control"
                       value="<?php echo htmlspecialchars($ebook['category']); ?>">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($ebook['description']); ?></textarea>
            </div>

            <div class="form-group text-right">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update E-book
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>