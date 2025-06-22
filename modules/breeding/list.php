<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

$page_title = "Breeding Records";
require_once '../../includes/header.php';

$db = new Database();
$conn = $db->connect();

// Handle record deletion
if (isset($_POST['delete_record']) && isset($_POST['record_id'])) {
    $record_id = (int)$_POST['record_id'];
    
    if (check_permission('admin') || check_permission('vet')) {
        $stmt = $conn->prepare("SELECT * FROM breeding_records WHERE record_id = ?");
        $stmt->bind_param("i", $record_id);
        $stmt->execute();
        $old_values = $stmt->get_result()->fetch_assoc();
        
        $stmt = $conn->prepare("DELETE FROM breeding_records WHERE record_id = ?");
        $stmt->bind_param("i", $record_id);
        
        if ($stmt->execute()) {
            // Log the action
            log_action($conn, 'DELETE', 'breeding_records', $record_id, $old_values, null);
            set_success_message("Breeding record deleted successfully.");
        } else {
            set_error_message("Error deleting breeding record.");
        }
    } else {
        set_error_message("You don't have permission to delete breeding records.");
    }
    
    header("Location: list.php");
    exit();
}

// Get search parameters
$search = $_GET['search'] ?? '';
$breeding_type = $_GET['breeding_type'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Prepare base query
$query = "
    SELECT br.*, 
           c.tag_number, c.cattle_name,
           u.full_name as technician_name 
    FROM breeding_records br 
    JOIN cattle c ON br.cattle_id = c.cattle_id 
    LEFT JOIN users u ON br.technician_id = u.user_id 
    WHERE 1=1
";
$params = [];
$types = "";

// Add search conditions
if ($search) {
    $query .= " AND (c.tag_number LIKE ? OR c.cattle_name LIKE ? OR br.sire_details LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($breeding_type) {
    $query .= " AND br.breeding_type = ?";
    $params[] = $breeding_type;
    $types .= "s";
}

if ($status) {
    $query .= " AND br.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($date_from) {
    $query .= " AND br.breeding_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if ($date_to) {
    $query .= " AND br.breeding_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Add sorting
$query .= " ORDER BY br.breeding_date DESC";

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
        <h2>Breeding Records</h2>
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
                       placeholder="Search by tag number or sire"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group">
                <select name="breeding_type" class="form-control">
                    <option value="">All Types</option>
                    <option value="natural" <?php echo $breeding_type === 'natural' ? 'selected' : ''; ?>>Natural</option>
                    <option value="artificial" <?php echo $breeding_type === 'artificial' ? 'selected' : ''; ?>>Artificial Insemination</option>
                    <option value="embryo_transfer" <?php echo $breeding_type === 'embryo_transfer' ? 'selected' : ''; ?>>Embryo Transfer</option>
                </select>
            </div>
            
            <div class="form-group">
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="successful" <?php echo $status === 'successful' ? 'selected' : ''; ?>>Successful</option>
                    <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="pregnant" <?php echo $status === 'pregnant' ? 'selected' : ''; ?>>Pregnant</option>
                    <option value="calved" <?php echo $status === 'calved' ? 'selected' : ''; ?>>Calved</option>
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
                    <th>Type</th>
                    <th>Sire Details</th>
                    <th>Technician</th>
                    <th>Status</th>
                    <th>Expected Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($record = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo format_date($record['breeding_date']); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($record['tag_number']); ?></div>
                                <div class="text-muted"><?php echo htmlspecialchars($record['cattle_name'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <span class="type-badge <?php echo $record['breeding_type']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $record['breeding_type'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($record['sire_details']); ?></td>
                            <td><?php echo htmlspecialchars($record['technician_name'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="status-badge <?php echo $record['status']; ?>">
                                    <?php echo ucwords($record['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($record['expected_date']): ?>
                                    <?php 
                                    $expected_date = new DateTime($record['expected_date']);
                                    $today = new DateTime();
                                    $is_overdue = $expected_date < $today && $record['status'] !== 'calved';
                                    ?>
                                    <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                                        <?php echo format_date($record['expected_date']); ?>
                                    </span>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
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
                                              onsubmit="return confirmDelete('Are you sure you want to delete this breeding record?');">
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
                        <td colspan="8" style="text-align: center;">No breeding records found.</td>
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

.type-badge.natural { background: #dcfce7; color: #166534; }
.type-badge.artificial { background: #dbeafe; color: #1e40af; }
.type-badge.embryo_transfer { background: #f3e8ff; color: #6b21a8; }

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-badge.pending { background: #fef9c3; color: #854d0e; }
.status-badge.successful { background: #dcfce7; color: #166534; }
.status-badge.failed { background: #fee2e2; color: #991b1b; }
.status-badge.pregnant { background: #dbeafe; color: #1e40af; }
.status-badge.calved { background: #f3e8ff; color: #6b21a8; }

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
