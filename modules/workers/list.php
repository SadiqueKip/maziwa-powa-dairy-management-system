<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

$page_title = "Worker Management";
require_once '../../includes/header.php';

$db = new Database();
$conn = $db->connect();

// Handle worker deletion
if (isset($_POST['delete_worker']) && isset($_POST['worker_id'])) {
    $worker_id = (int)$_POST['worker_id'];
    
    if (check_permission('admin')) {
        $stmt = $conn->prepare("SELECT * FROM workers WHERE worker_id = ?");
        $stmt->bind_param("i", $worker_id);
        $stmt->execute();
        $old_values = $stmt->get_result()->fetch_assoc();
        
        $stmt = $conn->prepare("DELETE FROM workers WHERE worker_id = ?");
        $stmt->bind_param("i", $worker_id);
        
        if ($stmt->execute()) {
            // Log the action
            log_action($conn, 'DELETE', 'workers', $worker_id, $old_values, null);
            set_success_message("Worker deleted successfully.");
        } else {
            set_error_message("Error deleting worker.");
        }
    } else {
        set_error_message("You don't have permission to delete workers.");
    }
    
    header("Location: list.php");
    exit();
}

// Get search parameters
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';

// Prepare base query
$query = "SELECT w.*, u.full_name, u.email, u.phone_number, u.role, u.status 
          FROM workers w 
          JOIN users u ON w.user_id = u.user_id 
          WHERE 1=1";
$params = [];
$types = "";

// Add search conditions
if ($search) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR w.id_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($role) {
    $query .= " AND u.role = ?";
    $params[] = $role;
    $types .= "s";
}

if ($status) {
    $query .= " AND u.status = ?";
    $params[] = $status;
    $types .= "s";
}

// Add sorting
$query .= " ORDER BY u.full_name ASC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get messages
$messages = get_messages();
?>

<div class="content-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>Workers List</h2>
        <?php if (check_permission('admin')): ?>
            <a href="add.php" class="btn btn-primary">Add New Worker</a>
        <?php endif; ?>
    </div>

    <!-- Search and Filter Form -->
    <form method="GET" class="filter-form" style="margin-bottom: 1.5rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div class="form-group">
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Search by name, email, or ID"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group">
                <select name="role" class="form-control">
                    <option value="">All Roles</option>
                    <option value="manager" <?php echo $role === 'manager' ? 'selected' : ''; ?>>Manager</option>
                    <option value="worker" <?php echo $role === 'worker' ? 'selected' : ''; ?>>Worker</option>
                    <option value="vet" <?php echo $role === 'vet' ? 'selected' : ''; ?>>Veterinarian</option>
                    <option value="milker" <?php echo $role === 'milker' ? 'selected' : ''; ?>>Milker</option>
                </select>
            </div>
            
            <div class="form-group">
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            
            <div class="form-group" style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="list.php" class="btn btn-secondary" style="margin-left: 0.5rem;">Reset</a>
            </div>
        </div>
    </form>

    <?php if ($messages['success']): ?>
        <div class="alert alert-success">
            <?php echo $messages['success']; ?>
        </div>
    <?php endif; ?>

    <?php if ($messages['error']): ?>
        <div class="alert alert-error">
            <?php echo $messages['error']; ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>ID Number</th>
                    <th>Role</th>
                    <th>Contact</th>
                    <th>Date Hired</th>
                    <th>Salary</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($worker = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($worker['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($worker['id_number'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="role-badge <?php echo $worker['role']; ?>">
                                    <?php echo ucfirst($worker['role']); ?>
                                </span>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($worker['phone_number']); ?></div>
                                <div class="text-muted"><?php echo htmlspecialchars($worker['email']); ?></div>
                            </td>
                            <td><?php echo format_date($worker['date_hired']); ?></td>
                            <td><?php echo $worker['salary'] ? format_currency($worker['salary']) : 'N/A'; ?></td>
                            <td>
                                <span class="status-badge <?php echo $worker['status']; ?>">
                                    <?php echo ucfirst($worker['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view.php?id=<?php echo $worker['worker_id']; ?>" 
                                       class="btn btn-secondary btn-sm">View</a>
                                    
                                    <?php if (check_permission('admin')): ?>
                                        <a href="edit.php?id=<?php echo $worker['worker_id']; ?>" 
                                           class="btn btn-primary btn-sm">Edit</a>
                                           
                                        <form method="POST" 
                                              style="display: inline;" 
                                              onsubmit="return confirmDelete('Are you sure you want to delete this worker?');">
                                            <input type="hidden" name="worker_id" value="<?php echo $worker['worker_id']; ?>">
                                            <button type="submit" 
                                                    name="delete_worker" 
                                                    class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">No workers found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.role-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.role-badge.admin { background: #fee2e2; color: #991b1b; }
.role-badge.manager { background: #dbeafe; color: #1e40af; }
.role-badge.worker { background: #dcfce7; color: #166534; }
.role-badge.vet { background: #fef9c3; color: #854d0e; }
.role-badge.milker { background: #f3e8ff; color: #6b21a8; }

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-badge.active { background: #dcfce7; color: #166534; }
.status-badge.inactive { background: #fee2e2; color: #991b1b; }

.text-muted {
    color: #666;
    font-size: 0.875rem;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.btn-danger {
    background: #fee2e2;
    color: #991b1b;
}

.btn-danger:hover {
    background: #fecaca;
}

.alert {
    padding: 1rem;
    border-radius: 5px;
    margin-bottom: 1rem;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #166534;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #991b1b;
}
</style>

<?php
require_once '../../includes/footer.php';
?>
