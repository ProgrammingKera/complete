<?php
include_once '../includes/header.php';

// Check if user is student or faculty
if ($_SESSION['role'] != 'student' && $_SESSION['role'] != 'faculty') {
    header('Location: ../index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle request cancellation
if (isset($_POST['cancel_request'])) {
    $requestId = (int)$_POST['request_id'];
    
    // Verify the request belongs to the current user
    $stmt = $conn->prepare("SELECT id FROM book_requests WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $requestId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE book_requests SET status = 'cancelled' WHERE id = ?");
        $stmt->bind_param("i", $requestId);
        
        if ($stmt->execute()) {
            $message = "Request cancelled successfully.";
            $messageType = "success";
        } else {
            $message = "Error cancelling request.";
            $messageType = "danger";
        }
    } else {
        $message = "Request not found or cannot be cancelled.";
        $messageType = "danger";
    }
}

// Get all book requests for the user
$sql = "
    SELECT br.*, b.title, b.author, b.isbn, b.available_quantity
    FROM book_requests br
    JOIN books b ON br.book_id = b.id
    WHERE br.user_id = ?
    ORDER BY br.request_date DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

// Separate requests by status
$pendingRequests = array_filter($requests, function($req) { return $req['status'] == 'pending'; });
$approvedRequests = array_filter($requests, function($req) { return $req['status'] == 'approved'; });
$rejectedRequests = array_filter($requests, function($req) { return $req['status'] == 'rejected'; });
$cancelledRequests = array_filter($requests, function($req) { return $req['status'] == 'cancelled'; });
?>

<div class="container">
    <h1 class="page-title">My Book Requests</h1>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="stats-container mb-4">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count($pendingRequests); ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count($approvedRequests); ?></div>
                <div class="stat-label">Approved</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count($rejectedRequests); ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-ban"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count($cancelledRequests); ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>
    </div>

    <!-- Pending Requests -->
    <?php if (count($pendingRequests) > 0): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-clock text-warning"></i> Pending Requests</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Book Details</th>
                            <th>Request Date</th>
                            <th>Notes</th>
                            <th>Availability</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingRequests as $request): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['title']); ?></strong><br>
                                    <small class="text-muted">by <?php echo htmlspecialchars($request['author']); ?></small><br>
                                    <small class="text-muted">ISBN: <?php echo htmlspecialchars($request['isbn']); ?></small>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($request['request_date'])); ?></td>
                                <td>
                                    <?php if (!empty($request['notes'])): ?>
                                        <?php echo htmlspecialchars($request['notes']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No notes</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($request['available_quantity'] > 0): ?>
                                        <span class="badge badge-success"><?php echo $request['available_quantity']; ?> available</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Not available</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" name="cancel_request" class="btn btn-danger btn-sm" 
                                                onclick="return confirm('Are you sure you want to cancel this request?')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Approved Requests -->
    <?php if (count($approvedRequests) > 0): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-check-circle text-success"></i> Approved Requests</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Book Details</th>
                            <th>Request Date</th>
                            <th>Notes</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvedRequests as $request): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['title']); ?></strong><br>
                                    <small class="text-muted">by <?php echo htmlspecialchars($request['author']); ?></small><br>
                                    <small class="text-muted">ISBN: <?php echo htmlspecialchars($request['isbn']); ?></small>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($request['request_date'])); ?></td>
                                <td>
                                    <?php if (!empty($request['notes'])): ?>
                                        <?php echo htmlspecialchars($request['notes']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No notes</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-success">Approved</span><br>
                                    <small class="text-muted">Visit library to collect</small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Rejected/Cancelled Requests -->
    <?php if (count($rejectedRequests) > 0 || count($cancelledRequests) > 0): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-times-circle text-danger"></i> Rejected & Cancelled Requests</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Book Details</th>
                            <th>Request Date</th>
                            <th>Notes</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_merge($rejectedRequests, $cancelledRequests) as $request): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['title']); ?></strong><br>
                                    <small class="text-muted">by <?php echo htmlspecialchars($request['author']); ?></small><br>
                                    <small class="text-muted">ISBN: <?php echo htmlspecialchars($request['isbn']); ?></small>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($request['request_date'])); ?></td>
                                <td>
                                    <?php if (!empty($request['notes'])): ?>
                                        <?php echo htmlspecialchars($request['notes']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No notes</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($request['status'] == 'rejected'): ?>
                                        <span class="badge badge-danger">Rejected</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Cancelled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($requests) == 0): ?>
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-bookmark fa-3x text-muted mb-3"></i>
                <h3>No Book Requests</h3>
                <p class="text-muted">You haven't made any book requests yet.</p>
                <a href="books.php" class="btn btn-primary">
                    <i class="fas fa-search"></i> Browse Books
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
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