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

// Get breeding record details
$stmt = $conn->prepare("
    SELECT br.*, 
           c.tag_number, c.cattle_name, c.breed, c.breeding_status,
           u.full_name as technician_name 
    FROM breeding_records br 
    JOIN cattle c ON br.cattle_id = c.cattle_id 
    LEFT JOIN users u ON br.technician_id = u.user_id 
    WHERE br.record_id = ?
");
$stmt->bind_param("i", $record_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_error_message("Breeding record not found");
    header("Location: list.php");
    exit();
}

$record = $result->fetch_assoc();
$page_title = "Breeding Record Details: " . $record['tag_number'];
require_once '../../includes/header.php';

// Get related breeding records for this cattle
$related_stmt = $conn->prepare("
    SELECT br.*, u.full_name as technician_name 
    FROM breeding_records br 
    LEFT JOIN users u ON br.technician_id = u.user_id 
    WHERE br.cattle_id = ? AND br.record_id != ? 
    ORDER BY br.breeding_date DESC 
    LIMIT 5
");
$related_stmt->bind_param("ii", $record['cattle_id'], $record_id);
$related_stmt->execute();
$related_records = $related_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate days since breeding and days until expected date
$breeding_date = new DateTime($record['breeding_date']);
$today = new DateTime();
$days_since_breeding = $today->diff($breeding_date)->days;

$days_until_expected = null;
$is_overdue = false;
if ($record['expected_date']) {
    $expected_date = new DateTime($record['expected_date']);
    $days_until_expected = $today->diff($expected_date)->days;
    $is_overdue = $expected_date < $today && $record['status'] !== 'calved';
}
?>

<div class="content-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>Breeding Record Details</h2>
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
                    <label>Breeding Status:</label>
                    <span class="status-badge <?php echo $record['breeding_status']; ?>">
                        <?php echo ucwords(str_replace('_', ' ', $record['breeding_status'])); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Breeding Information -->
        <div class="info-section">
            <h3>Breeding Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Breeding Date:</label>
                    <span><?php echo format_date($record['breeding_date']); ?></span>
                </div>
                <div class="info-item">
                    <label>Days Since Breeding:</label>
                    <span><?php echo $days_since_breeding; ?> days</span>
                </div>
                <div class="info-item">
                    <label>Breeding Type:</label>
                    <span class="type-badge <?php echo $record['breeding_type']; ?>">
                        <?php echo ucwords(str_replace('_', ' ', $record['breeding_type'])); ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Status:</label>
                    <span class="status-badge <?php echo $record['status']; ?>">
                        <?php echo ucwords($record['status']); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Sire Details -->
        <div class="info-section">
            <h3>Sire Details</h3>
            <div class="details-content">
                <div class="detail-item">
                    <label>Sire Information:</label>
                    <div class="detail-text"><?php echo nl2br(htmlspecialchars($record['sire_details'])); ?></div>
                </div>
                
                <?php if ($record['breeding_type'] !== 'natural'): ?>
                    <?php if ($record['semen_batch']): ?>
                        <div class="detail-item">
                            <label>Semen Batch Number:</label>
                            <div class="detail-text"><?php echo htmlspecialchars($record['semen_batch']); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="detail-item">
                        <label>Technician:</label>
                        <div class="detail-text"><?php echo htmlspecialchars($record['technician_name'] ?? 'N/A'); ?></div>
                    </div>
                <?php endif; ?>
                
                <div class="detail-item">
                    <label>Breeding Cost:</label>
                    <div class="detail-text"><?php echo format_currency($record['breeding_cost']); ?></div>
                </div>
            </div>
        </div>

        <!-- Pregnancy Information -->
        <div class="info-section">
            <h3>Pregnancy Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Expected Date:</label>
                    <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                        <?php echo $record['expected_date'] ? format_date($record['expected_date']) : 'N/A'; ?>
                    </span>
                </div>
                <?php if ($days_until_expected !== null): ?>
                    <div class="info-item">
                        <label>Days Until Expected:</label>
                        <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                            <?php 
                            if ($is_overdue) {
                                echo "Overdue by " . $days_until_expected . " days";
                            } else {
                                echo $days_until_expected . " days remaining";
                            }
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <div class="info-item">
                    <label>Pregnancy Status:</label>
                    <?php if ($record['pregnancy_status']): ?>
                        <span class="status-badge <?php echo $record['pregnancy_status']; ?>">
                            <?php echo ucwords($record['pregnancy_status']); ?>
                        </span>
                    <?php else: ?>
                        <span>Not Checked</span>
                    <?php endif; ?>
                </div>
                
                <?php if ($record['pregnancy_check_date']): ?>
                    <div class="info-item">
                        <label>Check Date:</label>
                        <span><?php echo format_date($record['pregnancy_check_date']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Calving Information -->
        <?php if ($record['status'] === 'calved'): ?>
            <div class="info-section">
                <h3>Calving Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Calving Date:</label>
                        <span><?php echo format_date($record['calving_date']); ?></span>
                    </div>
                    <?php if ($record['calf_tag_number']): ?>
                        <div class="info-item">
                            <label>Calf Tag Number:</label>
                            <span><?php echo htmlspecialchars($record['calf_tag_number']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Additional Notes -->
        <?php if ($record['notes']): ?>
            <div class="info-section">
                <h3>Additional Notes</h3>
                <div class="detail-text">
                    <?php echo nl2br(htmlspecialchars($record['notes'])); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Related Breeding Records -->
        <div class="info-section">
            <h3>Related Breeding Records</h3>
            <?php if (!empty($related_records)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Sire</th>
                                <th>Status</th>
                                <th>Technician</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($related_records as $related): ?>
                                <tr>
                                    <td><?php echo format_date($related['breeding_date']); ?></td>
                                    <td>
                                        <span class="type-badge <?php echo $related['breeding_type']; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $related['breeding_type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($related['sire_details'], 0, 50)) . '...'; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $related['status']; ?>">
                                            <?php echo ucwords($related['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($related['technician_name'] ?? 'N/A'); ?></td>
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
                <p class="no-records">No related breeding records found</p>
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

.type-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 500;
    text-align: center;
}

.type-badge.natural { background: #dcfce7; color: #166534; }
.type-badge.artificial { background: #dbeafe; color: #1e40af; }
.type-badge.embryo_transfer { background: #f3e8ff; color: #6b21a8; }

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 500;
    text-align: center;
}

/* Breeding Status Colors */
.status-badge.open { background: #dcfce7; color: #166534; }
.status-badge.bred { background: #fef9c3; color: #854d0e; }
.status-badge.pregnant { background: #dbeafe; color: #1e40af; }

/* Record Status Colors */
.status-badge.pending { background: #fef9c3; color: #854d0e; }
.status-badge.successful { background: #dcfce7; color: #166534; }
.status-badge.failed { background: #fee2e2; color: #991b1b; }
.status-badge.calved { background: #f3e8ff; color: #6b21a8; }

/* Pregnancy Status Colors */
.status-badge.confirmed { background: #dcfce7; color: #166534; }
.status-badge.negative { background: #fee2e2; color: #991b1b; }

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
