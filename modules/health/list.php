<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

$page_title = "Health Records";
require_once '../../includes/header.php';

$db = new Database();
$conn = $db->connect();

// Handle record deletion
if (isset($_POST['delete_record']) && isset($_POST['record_id'])) {
    $record_id = (int)$_POST['record_id'];
    
    if (check_permission('admin') || check_permission('vet')) {
        $stmt = $conn->prepare("SELECT * FROM health_records WHERE record_id = ?");
        $stmt->bind_param("i", $record_id);
        $stmt->execute();
        $old_values = $stmt->get_result()->fetch_assoc();
        
        $stmt = $conn->prepare("DELETE FROM health_records WHERE record_id = ?");
        $stmt->bind_param("i", $record_id);
        
        if ($stmt->execute()) {
            // Log the action
            log_action($conn, 'DELETE', 'health_records', $record_id, $old_values, null);
            set_success_message("Health record deleted successfully.");
        } else {
            set_error_message("Error deleting health record.");
        }
    } else {
        set_error_message("You don't have permission to delete health records.");
    }
    
    header("Location: list.php");
    exit();
}

// Get search parameters
$search = $_GET['search'] ?? '';
$health_issue = $_GET['health_issue'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Prepare base query
$query = "
    SELECT hr.*, c.tag_number, c.cattle_name, u.full_name as vet_name 
    FROM health_records hr 
    JOIN cattle c ON hr.cattle_id = c.cattle_id 
    LEFT JOIN users u ON hr.attended_by = u.user_id 
    WHERE 1=1
";
$params = [];
$types = "";

// Add search conditions
if ($search) {
    $query .= " AND (c.tag_number LIKE ? OR c.cattle_name LIKE ? OR hr.health_issue LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($health_issue) {
    $query .= " AND hr.health_issue = ?";
    $params[] = $health_issue;
    $types .= "s";
}

if ($status) {
    $query .= " AND hr.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($date_from) {
    $query .= " AND hr.date_of_checkup >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if ($date_to) {
    $query .= " AND hr.date_of_checkup <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Add sorting
$query .= " ORDER BY hr.date_of_checkup DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get distinct health issues for filter
$issues_query = "SELECT DISTINCT health_issue FROM health_records ORDER BY health_issue";
$issues_result = $conn->query($issues_query);
$health_issues = $issues_result->fetch_all(MYSQLI_ASSOC);

// Get messages
$messages = get_messages();
?>

<div class="content-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>Health Records</h2>
        <?php if (check_permission('admin') || check_permission('vet')): ?>
            <a href="add.php" class="btn btn-primary">Add New Record</a>
        <?php endif; ?>
    </div>

    <!-- Search and Filter Form -->
    <form method="GET" class="filter-form" style="margin-bottom: 1.5rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div class="form-group">
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Search by tag number or issue"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group">
                <select name="health_issue" class="form-control">
                    <option value="">All Health Issues</option>
                    <?php foreach ($health_issues as $issue): ?>
                        <option value="<?php echo htmlspecialchars($issue['health_issue']); ?>"
                                <?php echo $health_issue === $issue['health_issue'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($issue['health_issue']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="ongoing" <?php echo $status === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="follow_up" <?php echo $status === 'follow_up' ? 'selected' : ''; ?>>Follow Up</option>
                </select>
            </div>
            
            <div class="form-group">
                <input type="date" 
                       name="date_from" 
                       class="form-control" 
                       placeholder="From Date"
                       value="<?php echo $date_from; ?>">
            </div>
            
            <div class="form-group">
                <input type="date" 
                       name="date_to" 
                       class="form-control" 
                       placeholder="To Date"
                       value="<?php echo $date_to; ?>">
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
                    <th>Date</th>
                    <th>Cattle</th>
                    <th>Health Issue</th>
                    <th>Treatment</th>
                    <th>Cost</th>
                    <th>Next Checkup</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($record = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo format_date($record['date_of_checkup']); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($record['tag_number']); ?></div>
                                <div class="text-muted"><?php echo htmlspecialchars($record['cattle_name'] ?? 'N/A'); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($record['health_issue']); ?></td>
                            <td><?php echo htmlspecialchars($record['treatment_given']); ?></td>
                            <td><?php echo format_currency($record['treatment_cost']); ?></td>
                            <td>
                                <?php if ($record['next_checkup_date']): ?>
                                    <span class="<?php echo strtotime($record['next_checkup_date']) < time() ? 'text-danger' : ''; ?>">
                                        <?php echo format_date($record['next_checkup_date']); ?>
                                    </span>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $record['status']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $record['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view.php?id=<?php echo $record['record_id']; ?>" 
                                       class="btn btn-secondary btn-sm">View</a>
                                    
                                    <?php if (check_permission('admin') || check_permission('vet')): ?>
                                        <a href="edit.php?id=<?php echo $record['record_id']; ?>" 
                                           class="btn btn-primary btn-sm">Edit</a>
                                           
                                        <form method="POST" 
                                              style="display: inline;" 
                                              onsubmit="return confirmDelete('Are you sure you want to delete this health record?');">
                                            <input type="hidden" name="record_id" value="<?php echo $record['record_id']; ?>">
                                            <button type="submit" 
                                                    name="delete_record" 
                                                    class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">No health records found.</td>
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
}

.status-badge.ongoing { background: #fef9c3; color: #854d0e; }
.status-badge.completed { background: #dcfce7; color: #166534; }
.status-badge.follow_up { background: #dbeafe; color: #1e40af; }

.text-muted {
    color: #666;
    font-size: 0.875rem;
}

.text-danger {
    color: #991b1b;
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
