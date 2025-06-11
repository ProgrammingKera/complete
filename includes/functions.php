<?php
// Check if user is logged in and has the right role
function checkUserRole($requiredRole) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: ../index.php');
        exit();
    }
    
    if ($_SESSION['role'] != $requiredRole) {
        header('Location: ../index.php');
        exit();
    }
}

// Get total number of books in library
function getTotalBooks($conn) {
    $sql = "SELECT SUM(total_quantity) as total FROM books";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'] ? $row['total'] : 0;
}

// Get total number of issued books
function getIssuedBooks($conn) {
    $sql = "SELECT COUNT(*) as total FROM issued_books WHERE status = 'issued' OR status = 'overdue'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'] ? $row['total'] : 0;
}

// Get total number of users
function getTotalUsers($conn) {
    $sql = "SELECT COUNT(*) as total FROM users WHERE role != 'librarian'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'] ? $row['total'] : 0;
}

// Get total number of pending requests
function getPendingRequests($conn) {
    $sql = "SELECT COUNT(*) as total FROM book_requests WHERE status = 'pending'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'] ? $row['total'] : 0;
}

// Get total unpaid fines
function getTotalUnpaidFines($conn) {
    $sql = "SELECT SUM(amount) as total FROM fines WHERE status = 'pending'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'] ? $row['total'] : 0;
}

// Generate due date for book issue (14 days from now by default)
function generateDueDate($days = 14) {
    $date = new DateTime();
    $date->add(new DateInterval("P{$days}D"));
    return $date->format('Y-m-d');
}

// Calculate fine amount based on days overdue
function calculateFine($dueDate, $returnDate, $finePerDay = 1.00) {
    $due = new DateTime($dueDate);
    $return = new DateTime($returnDate);
    $diff = $return->diff($due);
    
    if ($return > $due) {
        return $diff->days * $finePerDay;
    }
    
    return 0;
}

// Upload file and return path
function uploadFile($file, $targetDir = '../uploads/ebooks/') {
    // Create directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = basename($file['name']);
    $targetFilePath = $targetDir . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
    
    // Generate unique file name to prevent overwriting
    $fileName = uniqid() . '_' . $fileName;
    $targetFilePath = $targetDir . $fileName;
    
    // Allow only certain file formats
    $allowedTypes = array('pdf', 'doc', 'docx', 'epub');
    if (!in_array(strtolower($fileType), $allowedTypes)) {
        return array('success' => false, 'message' => 'Only PDF, DOC, DOCX & EPUB files are allowed.');
    }
    
    // Check file size (limit to 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return array('success' => false, 'message' => 'File size should be less than 10MB.');
    }
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        return array(
            'success' => true,
            'file_path' => $targetFilePath,
            'file_name' => $fileName,
            'file_size' => formatFileSize($file['size']),
            'file_type' => $fileType
        );
    } else {
        return array('success' => false, 'message' => 'There was an error uploading your file.');
    }
}

// Format file size
function formatFileSize($size) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = 0;
    while ($size >= 1024 && $i < 4) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

// Send notification to user
function sendNotification($conn, $userId, $message) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->bind_param("is", $userId, $message);
    return $stmt->execute();
}

// Format date for display
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

// Check if a book can be issued (available quantity > 0)
function canIssueBook($conn, $bookId) {
    $stmt = $conn->prepare("SELECT available_quantity FROM books WHERE id = ?");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $book = $result->fetch_assoc();
        return $book['available_quantity'] > 0;
    }
    
    return false;
}

// Update book availability when issued or returned
function updateBookAvailability($conn, $bookId, $action = 'issue') {
    if ($action == 'issue') {
        $sql = "UPDATE books SET available_quantity = available_quantity - 1 WHERE id = ? AND available_quantity > 0";
    } else {
        $sql = "UPDATE books SET available_quantity = available_quantity + 1 WHERE id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bookId);
    return $stmt->execute();
}

// Get user name by ID
function getUserName($conn, $userId) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        return $user['name'];
    }
    
    return 'Unknown User';
}

// Get book title by ID
function getBookTitle($conn, $bookId) {
    $stmt = $conn->prepare("SELECT title FROM books WHERE id = ?");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $book = $result->fetch_assoc();
        return $book['title'];
    }
    
    return 'Unknown Book';
}
?>