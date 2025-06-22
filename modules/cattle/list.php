<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

$page_title = "Cattle Management";
require_once '../../includes/header.php';

$db = new Database();
$conn = $db->connect();

// Handle cattle deletion
if (isset($_POST['delete_cattle']) && isset($_POST['cattle_id'])) {
    $cattle_id = (int)$_POST['cattle_id'];
    
    if (check_permission('admin') || check_permission('manager')) {
        $stmt = $conn->prepare("SELECT * FROM cattle WHERE cattle_id = ?");
        $stmt->bind_param("i", $cattle_id);
        $stmt->execute();
        $old_values = $stmt->get_result()->fetch_assoc();
        
        $stmt = $conn->prepare("DELETE FROM cattle WHERE cattle_id = ?");
        $stmt->bind_param("i", $cattle_id);
        
        if ($stmt->execute()) {
            // Log the action
            log_action($conn, 'DELETE', 'cattle', $cattle_id, $old_values, null);
            set_success_message("Cattle deleted successfully.");
        } else {
            set_error_message("Error deleting cattle.");
        }
    } else {
        set_error_message("You don't have permission to delete cattle.");
    }
    
    header("Location: list.php");
    exit();
}

// Get search parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$health_status = $_GET['health_status'] ?? '';

// Prepare base query
$query = "SELECT c.*, u.full_name as worker_name 
          FROM cattle c 
          LEFT JOIN users u ON c.assigned_worker = u.user_id 
          WHERE 1=1";
$params = [];
$types = "";

// Add search conditions
if ($search) {
    $query .= " AND (c.tag_number LIKE ? OR c.cattle_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($status) {
    $query .= " AND c.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($health_status) {
    $query .= " AND c.health_status = ?";
    $params[] = $health_status;
    $types .= "s";
}

// Add sorting
$query .= " ORDER BY c.date_registered DESC";

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
        <h2>Cattle List</h2>
        <?php if (check_permission('admin') || check_permission('manager')): ?>
            <a href="add.php" class="btn btn-primary">Add New Cattle</a>
        <?php endif; ?>
    </div>

    <!-- Search and Filter Form -->
    <form method="GET" class="filter-form" style="margin-bottom: 1.5rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div class="form-group">
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Search by tag number or name"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group">
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="dead" <?php echo $status === 'dead' ? 'selected' : ''; ?>>Dead</option>
                    <option value="sold" <?php echo $status === 'sold' ? 'selected' : ''; ?>>Sold</option>
                    <option value="transferred" <?php echo $status === 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                </select>
            </div>
            
            <div class="form-group">
                <select name="health_status" class="form-control">
                    <option value="">All Health Status</option>
                    <option value="healthy" <?php echo $health_status === 'healthy' ? 'selected' : ''; ?>>Healthy</option>
                    <option value="sick" <?php echo $health_status === 'sick' ? 'selected' : ''; ?>>Sick</option>
                    <option value="under_treatment" <?php echo $health_status === 'under_treatment' ? 'selected' : ''; ?>>Under Treatment</option>
                    <option value="quarantine" <?php echo $health_status === 'quarantine' ? 'selected' : ''; ?>>Quarantine</option>
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
                    <th>Tag Number</th>
                    <th>Name</th>
                    <th>Breed</th>
                    <th>Gender</th>
                    <th>Health Status</th>
                    <th>Current Weight</th>
                    <th>Assigned Worker</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($cattle = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cattle['tag_number']); ?></td>
                            <td><?php echo htmlspecialchars($cattle['cattle_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($cattle['breed']); ?></td>
                            <td><?php echo htmlspecialchars($cattle['gender']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $cattle['health_status']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $cattle['health_status'])); ?>
                                </span>
                            </td>
                            <td><?php echo $cattle['current_weight'] ? htmlspecialchars($cattle['current_weight']) . ' kg' : 'N/A'; ?></td>
                            <td><?php echo htmlspecialchars($cattle['worker_name'] ?? 'Unassigned'); ?></td>
                            <td>
                                <span class="status-badge <?php echo $cattle['status']; ?>">
                                    <?php echo ucfirst($cattle['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view.php?id=<?php echo $cattle['cattle_id']; ?>" 
                                       class="btn btn-secondary btn-sm">View</a>
                                    
                                    <?php if (check_permission('admin') || check_permission('manager')): ?>
                                        <a href="edit.php?id=<?php echo $cattle['cattle_id']; ?>" 
                                           class="btn btn-primary btn-sm">Edit</a>
                                           
                                        <form method="POST" 
                                              style="display: inline;" 
                                              onsubmit="return confirmDelete('Are you sure you want to delete this cattle record?');">
                                            <input type="hidden" name="cattle_id" value="<?php echo $cattle['cattle_id']; ?>">
                                            <button type="submit" 
                                                    name="delete_cattle" 
                                                    class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">No cattle records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: capitalize;
}

/* Health Status Colors */
.healthy { background: #dcfce7; color: #166534; }
.sick { background: #fee2e2; color: #991b1b; }
.under_treatment { background: #fff7ed; color: #9a3412; }
.quarantine { background: #fef9c3; color: #854d0e; }

/* Status Colors */
.active { background: #dbeafe; color: #1e40af; }
.dead { background: #f3f4f6; color: #374151; }
.sold { background: #f0fdf4; color: #166534; }
.transferred { background: #ede9fe; color: #5b21b6; }

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
