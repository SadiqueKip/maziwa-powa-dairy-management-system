<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// Check if cattle ID is provided
if (!isset($_GET['id'])) {
    set_error_message("No cattle ID provided");
    header("Location: list.php");
    exit();
}

$cattle_id = (int)$_GET['id'];

$db = new Database();
$conn = $db->connect();

// Get cattle details with worker name
$stmt = $conn->prepare("
    SELECT c.*, u.full_name as worker_name 
    FROM cattle c 
    LEFT JOIN users u ON c.assigned_worker = u.user_id 
    WHERE c.cattle_id = ?
");
$stmt->bind_param("i", $cattle_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_error_message("Cattle not found");
    header("Location: list.php");
    exit();
}

$cattle = $result->fetch_assoc();
$page_title = "Cattle Details: " . $cattle['tag_number'];
require_once '../../includes/header.php';

// Get health records
$health_stmt = $conn->prepare("
    SELECT * FROM health_records 
    WHERE cattle_id = ? 
    ORDER BY date_of_checkup DESC 
    LIMIT 5
");
$health_stmt->bind_param("i", $cattle_id);
$health_stmt->execute();
$health_records = $health_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get breeding records
$breeding_stmt = $conn->prepare("
    SELECT * FROM breeding_records 
    WHERE cow_id = ? 
    ORDER BY breeding_date DESC 
    LIMIT 5
");
$breeding_stmt->bind_param("i", $cattle_id);
$breeding_stmt->execute();
$breeding_records = $breeding_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get milk production records
$milk_stmt = $conn->prepare("
    SELECT * FROM milk_production 
    WHERE cattle_id = ? 
    ORDER BY production_date DESC 
    LIMIT 7
");
$milk_stmt->bind_param("i", $cattle_id);
$milk_stmt->execute();
$milk_records = $milk_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate age
$dob = new DateTime($cattle['date_of_birth']);
$now = new DateTime();
$age = $dob->diff($now);
?>

<div class="content-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>Cattle Details</h2>
        <div>
            <?php if (check_permission('admin') || check_permission('manager')): ?>
                <a href="edit.php?id=<?php echo $cattle_id; ?>" class="btn btn-primary">Edit Cattle</a>
            <?php endif; ?>
            <a href="list.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <div class="details-grid">
        <!-- Basic Information -->
        <div class="info-section">
            <h3>Basic Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Tag Number:</label>
                    <span><?php echo htmlspecialchars($cattle['tag_number']); ?></span>
                </div>
                <div class="info-item">
                    <label>Name:</label>
                    <span><?php echo htmlspecialchars($cattle['cattle_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <label>Breed:</label>
                    <span><?php echo htmlspecialchars($cattle['breed']); ?></span>
                </div>
                <div class="info-item">
                    <label>Gender:</label>
                    <span><?php echo ucfirst($cattle['gender']); ?></span>
                </div>
                <div class="info-item">
                    <label>Age:</label>
                    <span>
                        <?php 
                        if ($age->y > 0) {
                            echo $age->y . ' year' . ($age->y > 1 ? 's' : '');
                            if ($age->m > 0) {
                                echo ', ' . $age->m . ' month' . ($age->m > 1 ? 's' : '');
                            }
                        } else {
                            echo $age->m . ' month' . ($age->m > 1 ? 's' : '');
                            if ($age->d > 0) {
                                echo ', ' . $age->d . ' day' . ($age->d > 1 ? 's' : '');
                            }
                        }
                        ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Date of Birth:</label>
                    <span><?php echo format_date($cattle['date_of_birth']); ?></span>
                </div>
            </div>
        </div>

        <!-- Status Information -->
        <div class="info-section">
            <h3>Status Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Status:</label>
                    <span class="status-badge <?php echo $cattle['status']; ?>">
                        <?php echo ucfirst($cattle['status']); ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Health Status:</label>
                    <span class="status-badge <?php echo $cattle['health_status']; ?>">
                        <?php echo ucwords(str_replace('_', ' ', $cattle['health_status'])); ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Current Weight:</label>
                    <span><?php echo $cattle['current_weight'] ? htmlspecialchars($cattle['current_weight']) . ' kg' : 'N/A'; ?></span>
                </div>
                <div class="info-item">
                    <label>Assigned Worker:</label>
                    <span><?php echo htmlspecialchars($cattle['worker_name'] ?? 'Unassigned'); ?></span>
                </div>
                <div class="info-item">
                    <label>Date Registered:</label>
                    <span><?php echo format_date($cattle['date_registered']); ?></span>
                </div>
            </div>
        </div>

        <!-- Recent Health Records -->
        <div class="info-section">
            <h3>Recent Health Records</h3>
            <?php if (!empty($health_records)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Issue</th>
                                <th>Treatment</th>
                                <th>Next Checkup</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($health_records as $record): ?>
                                <tr>
                                    <td><?php echo format_date($record['date_of_checkup']); ?></td>
                                    <td><?php echo htmlspecialchars($record['health_issue']); ?></td>
                                    <td><?php echo htmlspecialchars($record['treatment_given']); ?></td>
                                    <td><?php echo $record['next_checkup_date'] ? format_date($record['next_checkup_date']) : 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="no-records">No health records found.</p>
            <?php endif; ?>
        </div>

        <!-- Recent Breeding Records -->
        <?php if ($cattle['gender'] === 'female'): ?>
            <div class="info-section">
                <h3>Recent Breeding Records</h3>
                <?php if (!empty($breeding_records)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Expected Calving</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($breeding_records as $record): ?>
                                    <tr>
                                        <td><?php echo format_date($record['breeding_date']); ?></td>
                                        <td><?php echo ucwords(str_replace('_', ' ', $record['breeding_type'])); ?></td>
                                        <td><?php echo format_date($record['expected_calving_date']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $record['status']; ?>">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="no-records">No breeding records found.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Recent Milk Production -->
        <?php if ($cattle['gender'] === 'female'): ?>
            <div class="info-section">
                <h3>Recent Milk Production</h3>
                <?php if (!empty($milk_records)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Morning</th>
                                    <th>Afternoon</th>
                                    <th>Evening</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($milk_records as $record): ?>
                                    <tr>
                                        <td><?php echo format_date($record['production_date']); ?></td>
                                        <td><?php echo number_format($record['morning_yield'], 2); ?> L</td>
                                        <td><?php echo number_format($record['afternoon_yield'], 2); ?> L</td>
                                        <td><?php echo number_format($record['evening_yield'], 2); ?> L</td>
                                        <td><strong><?php echo number_format($record['total_yield'], 2); ?> L</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="no-records">No milk production records found.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Notes -->
        <?php if (!empty($cattle['notes'])): ?>
            <div class="info-section">
                <h3>Notes</h3>
                <p class="notes"><?php echo nl2br(htmlspecialchars($cattle['notes'])); ?></p>
            </div>
        <?php endif; ?>
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

/* Status Colors */
.active { background: #dbeafe; color: #1e40af; }
.dead { background: #f3f4f6; color: #374151; }
.sold { background: #f0fdf4; color: #166534; }
.transferred { background: #ede9fe; color: #5b21b6; }

/* Health Status Colors */
.healthy { background: #dcfce7; color: #166534; }
.sick { background: #fee2e2; color: #991b1b; }
.under_treatment { background: #fff7ed; color: #9a3412; }
.quarantine { background: #fef9c3; color: #854d0e; }

/* Breeding Status Colors */
.bred { background: #dbeafe; color: #1e40af; }
.pregnant { background: #f0fdf4; color: #166534; }
.failed { background: #fee2e2; color: #991b1b; }
.delivered { background: #dcfce7; color: #166534; }

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

.no-records {
    color: #666;
    font-style: italic;
    text-align: center;
    padding: 1rem;
}

.notes {
    white-space: pre-line;
    line-height: 1.5;
    color: #374151;
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
