<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// Check if worker ID is provided
if (!isset($_GET['id'])) {
    set_error_message("No worker ID provided");
    header("Location: list.php");
    exit();
}

$worker_id = (int)$_GET['id'];

$db = new Database();
$conn = $db->connect();

// Get worker details
$stmt = $conn->prepare("
    SELECT w.*, u.full_name, u.email, u.phone_number, u.role, u.status, u.last_login 
    FROM workers w 
    JOIN users u ON w.user_id = u.user_id 
    WHERE w.worker_id = ?
");
$stmt->bind_param("i", $worker_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_error_message("Worker not found");
    header("Location: list.php");
    exit();
}

$worker = $result->fetch_assoc();
$page_title = "Worker Details: " . $worker['full_name'];
require_once '../../includes/header.php';

// Get assigned cattle
$cattle_stmt = $conn->prepare("
    SELECT c.* FROM cattle c 
    WHERE c.assigned_worker = ? AND c.status = 'active'
    ORDER BY c.tag_number
");
$cattle_stmt->bind_param("i", $worker['user_id']);
$cattle_stmt->execute();
$assigned_cattle = $cattle_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent activities from audit logs
$audit_stmt = $conn->prepare("
    SELECT * FROM audit_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$audit_stmt->bind_param("i", $worker['user_id']);
$audit_stmt->execute();
$activities = $audit_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate employment duration
$hire_date = new DateTime($worker['date_hired']);
$now = new DateTime();
$duration = $hire_date->diff($now);
?>

<div class="content-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>Worker Details</h2>
        <div>
            <?php if (check_permission('admin')): ?>
                <a href="edit.php?id=<?php echo $worker_id; ?>" class="btn btn-primary">Edit Worker</a>
            <?php endif; ?>
            <a href="list.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <div class="details-grid">
        <!-- Personal Information -->
        <div class="info-section">
            <h3>Personal Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Full Name:</label>
                    <span><?php echo htmlspecialchars($worker['full_name']); ?></span>
                </div>
                <div class="info-item">
                    <label>ID Number:</label>
                    <span><?php echo htmlspecialchars($worker['id_number']); ?></span>
                </div>
                <div class="info-item">
                    <label>Email:</label>
                    <span><?php echo htmlspecialchars($worker['email']); ?></span>
                </div>
                <div class="info-item">
                    <label>Phone:</label>
                    <span><?php echo htmlspecialchars($worker['phone_number']); ?></span>
                </div>
            </div>
        </div>

        <!-- Employment Information -->
        <div class="info-section">
            <h3>Employment Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Role:</label>
                    <span class="role-badge <?php echo $worker['role']; ?>">
                        <?php echo ucfirst($worker['role']); ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Status:</label>
                    <span class="status-badge <?php echo $worker['status']; ?>">
                        <?php echo ucfirst($worker['status']); ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Date Hired:</label>
                    <span><?php echo format_date($worker['date_hired']); ?></span>
                </div>
                <div class="info-item">
                    <label>Employment Duration:</label>
                    <span>
                        <?php 
                        if ($duration->y > 0) {
                            echo $duration->y . ' year' . ($duration->y > 1 ? 's' : '');
                            if ($duration->m > 0) {
                                echo ', ' . $duration->m . ' month' . ($duration->m > 1 ? 's' : '');
                            }
                        } else {
                            echo $duration->m . ' month' . ($duration->m > 1 ? 's' : '');
                            if ($duration->d > 0) {
                                echo ', ' . $duration->d . ' day' . ($duration->d > 1 ? 's' : '');
                            }
                        }
                        ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Monthly Salary:</label>
                    <span><?php echo $worker['salary'] ? format_currency($worker['salary']) : 'N/A'; ?></span>
                </div>
                <div class="info-item">
                    <label>Last Login:</label>
                    <span><?php echo $worker['last_login'] ? format_datetime($worker['last_login']) : 'Never'; ?></span>
                </div>
            </div>
        </div>

        <!-- Assigned Duties -->
        <?php if (!empty($worker['assigned_duties'])): ?>
            <div class="info-section">
                <h3>Assigned Duties</h3>
                <p class="duties"><?php echo nl2br(htmlspecialchars($worker['assigned_duties'])); ?></p>
            </div>
        <?php endif; ?>

        <!-- Assigned Cattle -->
        <div class="info-section">
            <h3>Assigned Cattle</h3>
            <?php if (!empty($assigned_cattle)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Tag Number</th>
                                <th>Name</th>
                                <th>Breed</th>
                                <th>Health Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_cattle as $cattle): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cattle['tag_number']); ?></td>
                                    <td><?php echo htmlspecialchars($cattle['cattle_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($cattle['breed']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $cattle['health_status']; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $cattle['health_status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../cattle/view.php?id=<?php echo $cattle['cattle_id']; ?>" 
                                           class="btn btn-secondary btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="no-records">No cattle currently assigned to this worker.</p>
            <?php endif; ?>
        </div>

        <!-- Recent Activities -->
        <div class="info-section">
            <h3>Recent Activities</h3>
            <?php if (!empty($activities)): ?>
                <div class="activity-list">
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-time">
                                <?php echo format_datetime($activity['created_at']); ?>
                            </div>
                            <div class="activity-details">
                                <span class="activity-action"><?php echo htmlspecialchars($activity['action']); ?></span>
                                in <?php echo htmlspecialchars($activity['table_name']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-records">No recent activities found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.details-grid {
    display: grid;
    gap: 1.5rem;
}

.info-section {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.info-section h3 {
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #f3f4f6;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-item label {
    font-size: 0.875rem;
    color: #666;
}

.info-item span {
    font-size: 1rem;
    font-weight: 500;
}

.role-badge,
.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 500;
    text-align: center;
}

/* Role Colors */
.role-badge.admin { background: #fee2e2; color: #991b1b; }
.role-badge.manager { background: #dbeafe; color: #1e40af; }
.role-badge.worker { background: #dcfce7; color: #166534; }
.role-badge.vet { background: #fef9c3; color: #854d0e; }
.role-badge.milker { background: #f3e8ff; color: #6b21a8; }

/* Status Colors */
.status-badge.active { background: #dcfce7; color: #166534; }
.status-badge.inactive { background: #fee2e2; color: #991b1b; }

/* Health Status Colors */
.status-badge.healthy { background: #dcfce7; color: #166534; }
.status-badge.sick { background: #fee2e2; color: #991b1b; }
.status-badge.under_treatment { background: #fff7ed; color: #9a3412; }
.status-badge.quarantine { background: #fef9c3; color: #854d0e; }

.duties {
    white-space: pre-line;
    line-height: 1.5;
    color: #374151;
}

.table-responsive {
    overflow-x: auto;
    margin: 0 -1.5rem;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

th, td {
    padding: 0.75rem 1.5rem;
    text-align: left;
    border-bottom: 1px solid #f3f4f6;
}

th {
    background: #f8fafc;
    font-weight: 600;
    color: #475569;
}

tr:hover {
    background: #f8fafc;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.activity-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 5px;
}

.activity-time {
    font-size: 0.875rem;
    color: #666;
}

.activity-details {
    font-size: 0.9rem;
}

.activity-action {
    font-weight: 500;
    color: #1a1a1a;
}

.no-records {
    color: #666;
    font-style: italic;
    text-align: center;
    padding: 1rem;
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .table-responsive {
        margin: 0;
    }
    
    th, td {
        padding: 0.5rem;
    }
}
</style>

<?php
require_once '../../includes/footer.php';
?>
