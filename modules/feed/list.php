<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

$page_title = "Feed Management";
require_once '../../includes/header.php';

$db = new Database();
$conn = $db->connect();

// Handle feed deletion
if (isset($_POST['delete_feed']) && isset($_POST['feed_id'])) {
    $feed_id = (int)$_POST['feed_id'];
    
    if (check_permission('admin') || check_permission('manager')) {
        $stmt = $conn->prepare("SELECT * FROM feed_inventory WHERE feed_id = ?");
        $stmt->bind_param("i", $feed_id);
        $stmt->execute();
        $old_values = $stmt->get_result()->fetch_assoc();
        
        $stmt = $conn->prepare("DELETE FROM feed_inventory WHERE feed_id = ?");
        $stmt->bind_param("i", $feed_id);
        
        if ($stmt->execute()) {
            // Log the action
            log_action($conn, 'DELETE', 'feed_inventory', $feed_id, $old_values, null);
            set_success_message("Feed record deleted successfully.");
        } else {
            set_error_message("Error deleting feed record.");
        }
    } else {
        set_error_message("You don't have permission to delete feed records.");
    }
    
    header("Location: list.php");
    exit();
}

// Get search parameters
$search = $_GET['search'] ?? '';
$feed_type = $_GET['feed_type'] ?? '';
$status = $_GET['status'] ?? '';

// Prepare base query
$query = "SELECT * FROM feed_inventory WHERE 1=1";
$params = [];
$types = "";

// Add search conditions
if ($search) {
    $query .= " AND (feed_name LIKE ? OR supplier LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($feed_type) {
    $query .= " AND feed_type = ?";
    $params[] = $feed_type;
    $types .= "s";
}

if ($status) {
    $query .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}

// Add sorting
$query .= " ORDER BY expiry_date ASC, feed_name ASC";

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
        <h2>Feed Inventory</h2>
        <?php if (check_permission('admin') || check_permission('manager')): ?>
            <a href="add.php" class="btn btn-primary">Add New Feed</a>
        <?php endif; ?>
    </div>

    <!-- Search and Filter Form -->
    <form method="GET" class="filter-form" style="margin-bottom: 1.5rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div class="form-group">
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Search by name or supplier"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group">
                <select name="feed_type" class="form-control">
                    <option value="">All Feed Types</option>
                    <option value="hay" <?php echo $feed_type === 'hay' ? 'selected' : ''; ?>>Hay</option>
                    <option value="silage" <?php echo $feed_type === 'silage' ? 'selected' : ''; ?>>Silage</option>
                    <option value="concentrate" <?php echo $feed_type === 'concentrate' ? 'selected' : ''; ?>>Concentrate</option>
                    <option value="mineral" <?php echo $feed_type === 'mineral' ? 'selected' : ''; ?>>Mineral</option>
                    <option value="supplement" <?php echo $feed_type === 'supplement' ? 'selected' : ''; ?>>Supplement</option>
                </select>
            </div>
            
            <div class="form-group">
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="in_stock" <?php echo $status === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                    <option value="low_stock" <?php echo $status === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="out_of_stock" <?php echo $status === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                    <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expired</option>
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
                    <th>Feed Name</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Unit Cost</th>
                    <th>Supplier</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($feed = $result->fetch_assoc()): 
                        // Determine status based on quantity and expiry date
                        $today = new DateTime();
                        $expiry = new DateTime($feed['expiry_date']);
                        $status = $feed['status'];
                        
                        if ($expiry < $today) {
                            $status = 'expired';
                        } elseif ($feed['current_quantity'] <= 0) {
                            $status = 'out_of_stock';
                        } elseif ($feed['current_quantity'] <= $feed['reorder_level']) {
                            $status = 'low_stock';
                        }
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($feed['feed_name']); ?></td>
                            <td>
                                <span class="type-badge <?php echo $feed['feed_type']; ?>">
                                    <?php echo ucfirst($feed['feed_type']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo number_format($feed['current_quantity'], 2); ?> 
                                <?php echo htmlspecialchars($feed['unit_of_measure']); ?>
                            </td>
                            <td><?php echo format_currency($feed['unit_cost']); ?></td>
                            <td><?php echo htmlspecialchars($feed['supplier']); ?></td>
                            <td>
                                <span class="<?php echo $expiry < $today ? 'text-danger' : ''; ?>">
                                    <?php echo format_date($feed['expiry_date']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $status; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view.php?id=<?php echo $feed['feed_id']; ?>" 
                                       class="btn btn-secondary btn-sm">View</a>
                                    
                                    <?php if (check_permission('admin') || check_permission('manager')): ?>
                                        <a href="edit.php?id=<?php echo $feed['feed_id']; ?>" 
                                           class="btn btn-primary btn-sm">Edit</a>
                                           
                                        <form method="POST" 
                                              style="display: inline;" 
                                              onsubmit="return confirmDelete('Are you sure you want to delete this feed record?');">
                                            <input type="hidden" name="feed_id" value="<?php echo $feed['feed_id']; ?>">
                                            <button type="submit" 
                                                    name="delete_feed" 
                                                    class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">No feed records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.type-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.type-badge.hay { background: #dcfce7; color: #166534; }
.type-badge.silage { background: #dbeafe; color: #1e40af; }
.type-badge.concentrate { background: #fef9c3; color: #854d0e; }
.type-badge.mineral { background: #f3e8ff; color: #6b21a8; }
.type-badge.supplement { background: #fee2e2; color: #991b1b; }

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-badge.in_stock { background: #dcfce7; color: #166534; }
.status-badge.low_stock { background: #fef9c3; color: #854d0e; }
.status-badge.out_of_stock { background: #fee2e2; color: #991b1b; }
.status-badge.expired { background: #f3f4f6; color: #374151; }

.text-danger {
    color: #991b1b;
    font-weight: 500;
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
