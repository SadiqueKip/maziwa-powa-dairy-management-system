<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// Check if record ID is provided
if (!isset($_GET['id'])) {
    set_error_message("No record ID provided");
    header("Location: list.php");
    exit();
}

$record_id = (int)$_GET['id'];

$db = new Database();
$conn = $db->connect();

// Get health record details
$stmt = $conn->prepare("
    SELECT hr.*, 
           c.tag_number, c.cattle_name, c.breed, c.health_status,
           u.full_name as vet_name 
    FROM health_records hr 
    JOIN cattle c ON hr.cattle_id = c.cattle_id 
    LEFT JOIN users u ON hr.attended_by = u.user_id 
    WHERE hr.record_id = ?
");
$stmt->bind_param("i", $record_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_error_message("Health record not found");
    header("Location: list.php");
    exit();
}

$record = $result->fetch_assoc();
$page_title = "Health Record Details: " . $record['tag_number'];
require_once '../../includes/header.php';

// Get related health records for this cattle
$related_stmt = $conn->prepare("
    SELECT hr.*, u.full_name as vet_name 
    FROM health_records hr 
    LEFT JOIN users u ON hr.attended_by = u.user_id 
    WHERE hr.cattle_id = ? AND hr.record_id != ? 
    ORDER BY hr.date_of_checkup DESC 
    LIMIT 5
");
$related_stmt->bind_param("ii", $record['cattle_id'], $record_id);
$related_stmt->execute();
$related_records = $related_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate days since checkup and days until next checkup
$checkup_date = new DateTime($record['date_of_checkup']);
$today = new DateTime();
$days_since_checkup = $today->diff($checkup_date)->days;

$days_until_next_checkup = null;
if ($record['next_checkup_date']) {
    $next_checkup = new DateTime($record['next_checkup_date']);
    $days_until_next_checkup = $today->diff($next_checkup)->days;
    $next_checkup_overdue = $next_checkup < $today;
}
?>

<div class="content-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>Health Record Details</h2>
        <div>
            <?php if (check_permission('admin') || check_permission('vet')): ?>
                <a href="edit.php?id=<?php echo $record_id; ?>" class="btn btn-primary">Edit Record</a>
            <?php endif; ?>
            <a href="list.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <div class="details-grid">
        <!-- Cattle Information -->
        <div class="info-section">
            <h3>Cattle Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Tag Number:</label>
                    <span><?php echo htmlspecialchars($record['tag_number']); ?></span>
                </div>
                <div class="info-item">
                    <label>Name:</label>
                    <span><?php echo htmlspecialchars($record['cattle_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <label>Breed:</label>
                    <span><?php echo htmlspecialchars($record['breed']); ?></span>
                </div>
                <div class="info-item">
                    <label>Current Health Status:</label>
                    <span class="status-badge <?php echo $record['health_status']; ?>">
                        <?php echo ucwords(str_replace('_', ' ', $record['health_status'])); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Checkup Information -->
        <div class="info-section">
            <h3>Checkup Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Date of Checkup:</label>
                    <span><?php echo format_date($record['date_of_checkup']); ?></span>
                </div>
                <div class="info-item">
                    <label>Days Since Checkup:</label>
                    <span><?php echo $days_since_checkup; ?> days</span>
                </div>
                <div class="info-item">
                    <label>Attended By:</label>
                    <span><?php echo htmlspecialchars($record['vet_name']); ?></span>
                </div>
                <div class="info-item">
                    <label>Status:</label>
                    <span class="status-badge <?php echo $record['status']; ?>">
                        <?php echo ucwords(str_replace('_', ' ', $record['status'])); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Health Issue Details -->
        <div class="info-section">
            <h3>Health Issue Details</h3>
            <div class="details-content">
                <div class="detail-item">
                    <label>Health Issue:</label>
                    <div class="detail-text"><?php echo htmlspecialchars($record['health_issue']); ?></div>
                </div>
                
                <?php if ($record['symptoms']): ?>
                    <div class="detail-item">
                        <label>Symptoms:</label>
                        <div class="detail-text"><?php echo nl2br(htmlspecialchars($record['symptoms'])); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($record['diagnosis']): ?>
                    <div class="detail-item">
                        <label>Diagnosis:</label>
                        <div class="detail-text"><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Treatment Information -->
        <div class="info-section">
            <h3>Treatment Information</h3>
            <div class="details-content">
                <div class="detail-item">
                    <label>Treatment Given:</label>
                    <div class="detail-text"><?php echo nl2br(htmlspecialchars($record['treatment_given'])); ?></div>
                </div>
                
                <?php if ($record['medications']): ?>
                    <div class="detail-item">
                        <label>Medications:</label>
                        <div class="detail-text"><?php echo nl2br(htmlspecialchars($record['medications'])); ?></div>
                    </div>
                <?php endif; ?>
                
                <div class="detail-item">
                    <label>Treatment Cost:</label>
                    <div class="detail-text"><?php echo format_currency($record['treatment_cost']); ?></div>
                </div>
            </div>
        </div>

        <!-- Follow-up Information -->
        <div class="info-section">
            <h3>Follow-up Information</h3>
            <?php if ($record['next_checkup_date']): ?>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Next Checkup Date:</label>
                        <span class="<?php echo $next_checkup_overdue ? 'text-danger' : ''; ?>">
                            <?php echo format_date($record['next_checkup_date']); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Days Until Next Checkup:</label>
                        <span class="<?php echo $next_checkup_overdue ? 'text-danger' : ''; ?>">
                            <?php 
                            if ($next_checkup_overdue) {
                                echo "Overdue by " . $days_until_next_checkup . " days";
                            } else {
                                echo $days_until_next_checkup . " days remaining";
                            }
                            ?>
                        </span>
                    </div>
                </div>
            <?php else: ?>
                <p class="no-records">No follow-up checkup scheduled</p>
            <?php endif; ?>

            <?php if ($record['notes']): ?>
                <div class="detail-item" style="margin-top: 1rem;">
                    <label>Additional Notes:</label>
                    <div class="detail-text"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Related Health Records -->
        <div class="info-section">
            <h3>Related Health Records</h3>
            <?php if (!empty($related_records)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Health Issue</th>
                                <th>Treatment</th>
                                <th>Status</th>
                                <th>Vet</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($related_records as $related): ?>
                                <tr>
                                    <td><?php echo format_date($related['date_of_checkup']); ?></td>
                                    <td><?php echo htmlspecialchars($related['health_issue']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($related['treatment_given'], 0, 50)) . '...'; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $related['status']; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $related['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($related['vet_name']); ?></td>
                                    <td>
                                        <a href="view.php?id=<?php echo $related['record_id']; ?>" 
                                           class="btn btn-secondary btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="no-records">No related health records found</p>
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

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 500;
    text-align: center;
}

/* Health Status Colors */
.status-badge.healthy { background: #dcfce7; color: #166534; }
.status-badge.sick { background: #fee2e2; color: #991b1b; }
.status-badge.under_treatment { background: #fef9c3; color: #854d0e; }
.status-badge.quarantine { background: #f3e8ff; color: #6b21a8; }

/* Record Status Colors */
.status-badge.ongoing { background: #fef9c3; color: #854d0e; }
.status-badge.completed { background: #dcfce7; color: #166534; }
.status-badge.follow_up { background: #dbeafe; color: #1e40af; }

.details-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.detail-item label {
    font-size: 0.875rem;
    color: #666;
    font-weight: 500;
}

.detail-text {
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 5px;
    line-height: 1.5;
}

.text-danger {
    color: #991b1b;
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
