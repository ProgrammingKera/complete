<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Process book operations
$message = '';
$messageType = '';

// Add new book
if (isset($_POST['add_book'])) {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $isbn = trim($_POST['isbn']);
    $publisher = trim($_POST['publisher']);
    $year = trim($_POST['year']);
    $category = trim($_POST['category']);
    $quantity = (int)$_POST['quantity'];
    $shelf = trim($_POST['shelf']);
    $description = trim($_POST['description']);
    
    // Basic validation
    if (empty($title) || empty($author) || empty($quantity)) {
        $message = "Title, author, and quantity are required fields.";
        $messageType = "danger";
    } else {
        // Check if ISBN already exists
        if (!empty($isbn)) {
            $stmt = $conn->prepare("SELECT id FROM books WHERE isbn = ?");
            $stmt->bind_param("s", $isbn);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = "A book with this ISBN already exists.";
                $messageType = "danger";
            }
        }
        
        if (empty($message)) {
            // Process cover image if uploaded
            $coverImage = "";
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
                        $coverImage = $uploadFile;
                    }
                }
            }
            
            // Insert book
            $stmt = $conn->prepare("
                INSERT INTO books (title, author, isbn, publisher, publication_year, category, 
                                  total_quantity, available_quantity, shelf_location, description, cover_image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "ssssissssss",
                $title, $author, $isbn, $publisher, $year, $category,
                $quantity, $quantity, $shelf, $description, $coverImage
            );
            
            if ($stmt->execute()) {
                $message = "Book added successfully.";
                $messageType = "success";
            } else {
                $message = "Error adding book: " . $stmt->error;
                $messageType = "danger";
            }
        }
    }
}

// Delete book
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if book is currently issued
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM issued_books 
        WHERE book_id = ? AND (status = 'issued' OR status = 'overdue')
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $message = "Cannot delete book. It is currently issued to users.";
        $messageType = "danger";
    } else {
        $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "Book deleted successfully.";
            $messageType = "success";
        } else {
            $message = "Error deleting book: " . $stmt->error;
            $messageType = "danger";
        }
    }
}

// Get all categories for filter
$categories = [];
$result = $conn->query("SELECT DISTINCT category FROM books WHERE category != '' ORDER BY category");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

// Handle search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

// Build the query
$sql = "SELECT * FROM books WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

$sql .= " ORDER BY title";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$books = [];
while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}
?>

<h1 class="page-title">Manage Books</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="d-flex justify-between align-center mb-4">
    <button class="btn btn-primary" data-modal-target="addBookModal">
        <i class="fas fa-plus"></i> Add New Book
    </button>
    
    <div class="d-flex">
        <form action="" method="GET" class="d-flex">
            <div class="form-group mr-2" style="margin-bottom: 0; margin-right: 10px;">
                <input type="text" name="search" placeholder="Search books..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
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

<div class="view-options" style="margin-top: 20px;">
    <button class="view-option active" data-view="books-grid">
        <i class="fas fa-th"></i>
    </button>
    <button class="view-option" data-view="books-list">
        <i class="fas fa-list"></i>
    </button>
</div>

<div class="books-container books-grid">
    <?php if (count($books) > 0): ?>
        <?php foreach ($books as $book): ?>
            <div class="book-card" data-category="<?php echo htmlspecialchars($book['category']); ?>">
                <div class="book-cover">
                    <?php if (!empty($book['cover_image'])): ?>
                        <img src="<?php echo htmlspecialchars($book['cover_image']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
                    <?php else: ?>
                        <i class="fas fa-book fa-3x"></i>
                    <?php endif; ?>
                </div>
                <div class="book-info">
                    <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                    <p class="book-author">By <?php echo htmlspecialchars($book['author']); ?></p>
                    <div class="book-details">
                        <span><?php echo htmlspecialchars($book['category']); ?></span>
                        <span>
                            <?php echo $book['available_quantity']; ?> / <?php echo $book['total_quantity']; ?> available
                        </span>
                    </div>
                </div>
                <div class="book-actions">
                    <a href="book_details.php?id=<?php echo $book['id']; ?>" class="btn btn-sm btn-secondary">
                        <i class="fas fa-info-circle"></i> Edit Info
                    </a>
                    
                    <a href="?delete=<?php echo $book['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete('Are you sure you want to delete this book?')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-warning">No books found.</div>
    <?php endif; ?>
</div>

<!-- Add Book Modal -->
<div class="modal-overlay" id="addBookModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Add New Book</h3>
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
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="isbn">ISBN</label>
                            <input type="text" id="isbn" name="isbn" class="form-control">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="publisher">Publisher</label>
                            <input type="text" id="publisher" name="publisher" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="year">Publication Year</label>
                            <input type="number" id="year" name="year" class="form-control" min="1800" max="<?php echo date('Y'); ?>">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="category">Category</label>
                            <input type="text" id="category" name="category" class="form-control" list="categories">
                            <datalist id="categories">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="quantity">Quantity <span class="text-danger">*</span></label>
                            <input type="number" id="quantity" name="quantity" class="form-control" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="shelf">Shelf Location</label>
                            <input type="text" id="shelf" name="shelf" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="cover">Cover Image</label>
                    <input type="file" id="cover" name="cover" class="form-control" accept="image/*">
                    <small class="text-muted">Supported formats: JPG, PNG, GIF. Max size: 2MB</small>
                </div>
                
                <div class="form-group text-right">
                    <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                    <button type="submit" name="add_book" class="btn btn-primary">Add Book</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>