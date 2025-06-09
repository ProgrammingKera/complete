<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Process e-book operations
$message = '';
$messageType = '';

// Upload new e-book
if (isset($_POST['upload_ebook'])) {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    
    // Basic validation
    if (empty($title) || empty($author)) {
        $message = "Title and author are required fields.";
        $messageType = "danger";
    } else if (!isset($_FILES['ebook_file']) || $_FILES['ebook_file']['error'] != 0) {
        $message = "Please select a valid e-book file to upload.";
        $messageType = "danger";
    } else {
        // Process file upload
        $fileUpload = uploadFile($_FILES['ebook_file'], '../uploads/ebooks/');
        
        if ($fileUpload['success']) {
            // Insert e-book record
            $stmt = $conn->prepare("
                INSERT INTO ebooks (title, author, category, description, file_path, file_size, file_type, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $filePath = $fileUpload['file_path'];
            $fileSize = $fileUpload['file_size'];
            $fileType = $fileUpload['file_type'];
            $uploadedBy = $_SESSION['user_id'];
            
            $stmt->bind_param(
                "sssssssi",
                $title, $author, $category, $description, $filePath, $fileSize, $fileType, $uploadedBy
            );
            
            if ($stmt->execute()) {
                $message = "E-book uploaded successfully.";
                $messageType = "success";
            } else {
                $message = "Error uploading e-book: " . $stmt->error;
                $messageType = "danger";
            }
        } else {
            $message = "Error uploading file: " . $fileUpload['message'];
            $messageType = "danger";
        }
    }
}

// Delete e-book
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get file path before deleting record
    $stmt = $conn->prepare("SELECT file_path FROM ebooks WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $ebook = $result->fetch_assoc();
        $filePath = $ebook['file_path'];
        
        // Delete record from database
        $stmt = $conn->prepare("DELETE FROM ebooks WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // Delete file from server
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            $message = "E-book deleted successfully.";
            $messageType = "success";
        } else {
            $message = "Error deleting e-book: " . $stmt->error;
            $messageType = "danger";
        }
    } else {
        $message = "E-book not found.";
        $messageType = "warning";
    }
}

// Get all categories for filter
$categories = [];
$result = $conn->query("SELECT DISTINCT category FROM ebooks WHERE category != '' ORDER BY category");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

// Handle search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

// Build the query
$sql = "SELECT e.*, u.name as uploader_name 
        FROM ebooks e 
        LEFT JOIN users u ON e.uploaded_by = u.id 
        WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (e.title LIKE ? OR e.author LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

if (!empty($category)) {
    $sql .= " AND e.category = ?";
    $params[] = $category;
    $types .= "s";
}

$sql .= " ORDER BY e.created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$ebooks = [];
while ($row = $result->fetch_assoc()) {
    $ebooks[] = $row;
}
?>

<h1 class="page-title">Manage E-Books</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="d-flex justify-between align-center mb-4">
    <button class="btn btn-primary" data-modal-target="uploadEbookModal">
        <i class="fas fa-upload"></i> Upload New E-Book
    </button>
    
    <div class="d-flex">
        <form action="" method="GET" class="d-flex">
            <div class="form-group mr-2" style="margin-bottom: 0; margin-right: 10px;">
                <input type="text" name="search" placeholder="Search e-books..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group mr-2" style="margin-bottom: 0; margin-right: 10px;">
                <select name="category" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category == $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-search"></i> Search
            </button>
        </form>
    </div>
</div>

<div class="table-container" style="margin-top: 30px;">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Title</th>
                <th>Author</th>
                <th>Category</th>
                <th>File Type</th>
                <th>Size</th>
                <th>Uploaded By</th>
                <th>Upload Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($ebooks) > 0): ?>
                <?php foreach ($ebooks as $ebook): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ebook['title']); ?></td>
                        <td><?php echo htmlspecialchars($ebook['author']); ?></td>
                        <td><?php echo htmlspecialchars($ebook['category']); ?></td>
                        <td>
                            <?php 
                            $fileType = strtoupper($ebook['file_type']);
                            $iconClass = '';
                            
                            switch (strtolower($fileType)) {
                                case 'pdf':
                                    $iconClass = 'fas fa-file-pdf';
                                    break;
                                case 'doc':
                                case 'docx':
                                    $iconClass = 'fas fa-file-word';
                                    break;
                                case 'epub':
                                    $iconClass = 'fas fa-book';
                                    break;
                                default:
                                    $iconClass = 'fas fa-file';
                            }
                            ?>
                            <span><i class="<?php echo $iconClass; ?>"></i> <?php echo $fileType; ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($ebook['file_size']); ?></td>
                        <td><?php echo htmlspecialchars($ebook['uploader_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($ebook['created_at'])); ?></td>
                        <td>
                            <a href="<?php echo htmlspecialchars($ebook['file_path']); ?>" class="btn btn-sm btn-primary" target="_blank">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <a href="ebook_details.php?id=<?php echo $ebook['id']; ?>" style="margin-top:10px;" class="btn btn-sm btn-secondary">
                                <i class="fas fa-info-circle"></i> Details
                            </a>
                            <a href="?delete=<?php echo $ebook['id']; ?>" style="margin-top:10px;" class="btn btn-sm btn-danger" onclick="return confirmDelete('Are you sure you want to delete this e-book?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center">No e-books found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Upload E-Book Modal -->
<div class="modal-overlay" id="uploadEbookModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Upload New E-Book</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="title">Title <span class="text-danger">*</span></label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="author">Author <span class="text-danger">*</span></label>
                            <input type="text" id="author" name="author" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="category">Category</label>
                    <input type="text" id="category" name="category" class="form-control" list="categories">
                    <datalist id="categories">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="ebook_file">E-Book File <span class="text-danger">*</span></label>
                    <input type="file" id="ebook_file" name="ebook_file" class="form-control" accept=".pdf,.doc,.docx,.epub" required>
                    <small class="text-muted">Supported formats: PDF, DOC, DOCX, EPUB. Max size: 10MB</small>
                </div>
                
                <div class="form-group text-right">
                    <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                    <button type="submit" name="upload_ebook" class="btn btn-primary">Upload E-Book</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>