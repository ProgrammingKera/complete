<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Process user operations
$message = '';
$messageType = '';

// Add new user
if (isset($_POST['add_user'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];
    $department = trim($_POST['department']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Basic validation
    if (empty($name) || empty($email) || empty($password)) {
        $message = "Name, email, and password are required fields.";
        $messageType = "danger";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = "A user with this email already exists.";
            $messageType = "danger";
        } else {
            // Generate unique ID
            $uniqueId = generateUniqueId($conn, $role);
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $conn->prepare("
                INSERT INTO users (unique_id, name, email, password, role, department, phone, address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "ssssssss",
                $uniqueId, $name, $email, $hashedPassword, $role, $department, $phone, $address
            );
            
            if ($stmt->execute()) {
                $message = "User added successfully. Unique ID: <strong>$uniqueId</strong>";
                $messageType = "success";
            } else {
                $message = "Error adding user: " . $stmt->error;
                $messageType = "danger";
            }
        }
    }
}

// Delete user
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if user has any issued books
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM issued_books 
        WHERE user_id = ? AND (status = 'issued' OR status = 'overdue')
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $message = "Cannot delete user. They have books currently issued.";
        $messageType = "danger";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "User deleted successfully.";
            $messageType = "success";
        } else {
            $message = "Error deleting user: " . $stmt->error;
            $messageType = "danger";
        }
    }
}

// Handle search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role = isset($_GET['role']) ? trim($_GET['role']) : '';

// Build the query
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR unique_id LIKE ? OR department LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ssss";
}

if (!empty($role)) {
    $sql .= " AND role = ?";
    $params[] = $role;
    $types .= "s";
}

$sql .= " ORDER BY name";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>

<h1 class="page-title">Manage Users</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="d-flex justify-between align-center mb-4">
    <button class="btn btn-primary" data-modal-target="addUserModal">
        <i class="fas fa-user-plus"></i> Add New User
    </button>
    
    <div class="d-flex">
        <form action="" method="GET" class="d-flex">
            <div class="form-group mr-2" style="margin-bottom: 0; margin-right: 10px;">
                <input type="text" name="search" placeholder="Search users..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group mr-2" style="margin-bottom: 0; margin-right: 10px;">
                <select name="role" class="form-control">
                    <option value="">All Roles</option>
                    <option value="student" <?php echo $role == 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="faculty" <?php echo $role == 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                    <option value="librarian" <?php echo $role == 'librarian' ? 'selected' : ''; ?>>Librarian</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-search"></i> Search
            </button>
        </form>
    </div>
</div>

<div class="table-container" style="margin-top:30px";>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Unique ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Department</th>
                <th>Phone</th>
                <th>Registered On</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($users) > 0): ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <span class="unique-id-badge"><?php echo htmlspecialchars($user['unique_id']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
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
                                default:
                                    echo htmlspecialchars($user['role']);
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($user['department']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <a href="user_details.php?id=<?php echo $user['id']; ?>" style="margin-bottom:10px"; class="btn btn-sm btn-primary">
                                <i class="fas fa-info-circle"></i> Details
                            </a>
                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" style="margin-bottom:10px"; class="btn btn-sm btn-secondary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete('Are you sure you want to delete this user?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center">No users found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Add New User</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form action="" method="POST">
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="name">Full Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="password">Password <span class="text-danger">*</span></label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="role">Role <span class="text-danger">*</span></label>
                            <select id="role" name="role" class="form-control" required>
                                <option value="student">Student</option>
                                <option value="faculty">Faculty</option>
                                <option value="librarian">Librarian</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="department">Department</label>
                            <input type="text" id="department" name="department" class="form-control">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="unique-id-info">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> A unique ID will be automatically generated based on the selected role:
                        <ul style="margin: 10px 0 0 20px;">
                            <li>Students: STU##### (e.g., STU12345)</li>
                            <li>Faculty: FAC##### (e.g., FAC12345)</li>
                            <li>Librarians: LIB##### (e.g., LIB12345)</li>
                        </ul>
                    </div>
                </div>
                
                <div class="form-group text-right">
                    <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.unique-id-badge {
    background: #e3f2fd;
    color: #1976d2;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 600;
    font-family: monospace;
    font-size: 0.9em;
}

.unique-id-info {
    margin-top: 15px;
}

.alert-info {
    background-color: #e3f2fd;
    color: #1976d2;
    border: 1px solid #bbdefb;
    padding: 15px;
    border-radius: 8px;
}

.alert-info ul {
    margin: 10px 0 0 20px;
    padding: 0;
}

.alert-info li {
    margin-bottom: 5px;
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>