<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !check_permission('admin') && !check_permission('vet')) {
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
    SELECT br.*, c.tag_number, c.cattle_name, c.breed 
    FROM breeding_records br 
    JOIN cattle c ON br.cattle_id = c.cattle_id 
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
$page_title = "Edit Breeding Record: " . $record['tag_number'];
require_once '../../includes/header.php';

// Get list of technicians (vets)
$tech_query = "
    SELECT user_id, full_name 
    FROM users 
    WHERE role = 'vet' AND status = 'active' 
    ORDER BY full_name
";
$tech_result = $conn->query($tech_query);
$tech_list = $tech_result->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $breeding_date = sanitize_input($_POST['breeding_date']);
    $breeding_type = sanitize_input($_POST['breeding_type']);
    $sire_details = sanitize_input($_POST['sire_details']);
    $semen_batch = sanitize_input($_POST['semen_batch']);
    $technician_id = (int)$_POST['technician_id'];
    $breeding_cost = sanitize_input($_POST['breeding_cost']);
    $notes = sanitize_input($_POST['notes']);
    $status = sanitize_input($_POST['status']);
    $pregnancy_status = sanitize_input($_POST['pregnancy_status'] ?? '');
    $pregnancy_check_date = empty($_POST['pregnancy_check_date']) ? null : sanitize_input($_POST['pregnancy_check_date']);
    $calving_date = empty($_POST['calving_date']) ? null : sanitize_input($_POST['calving_date']);
    $calf_tag_number = sanitize_input($_POST['calf_tag_number'] ?? '');

    // Calculate expected date (285 days from breeding date for cattle)
    $expected_date = date('Y-m-d', strtotime($breeding_date . ' + 285 days'));

    // Validate inputs
    $errors = [];
    
    if (empty($breeding_date) || !validate_date($breeding_date)) {
        $errors[] = "Valid breeding date is required";
    }
    
    if (empty($breeding_type)) {
        $errors[] = "Breeding type is required";
    }
    
    if (empty($sire_details)) {
        $errors[] = "Sire details are required";
    }
    
    if ($breeding_type !== 'natural' && empty($technician_id)) {
        $errors[] = "Technician is required for artificial insemination or embryo transfer";
    }
    
    if (!is_numeric($breeding_cost) || $breeding_cost < 0) {
        $errors[] = "Valid breeding cost is required";
    }

    if ($pregnancy_check_date && !validate_date($pregnancy_check_date)) {
        $errors[] = "Invalid pregnancy check date";
    }

    if ($calving_date && !validate_date($calving_date)) {
        $errors[] = "Invalid calving date";
    }

    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->begin_transaction();

            // Store old values for audit log
            $old_values = [
                'breeding_type' => $record['breeding_type'],
                'status' => $record['status'],
                'breeding_cost' => $record['breeding_cost']
            ];

            // Update breeding record
            $stmt = $conn->prepare("
                UPDATE breeding_records SET 
                    breeding_date = ?,
                    breeding_type = ?,
                    sire_details = ?,
                    semen_batch = ?,
                    technician_id = ?,
                    breeding_cost = ?,
                    notes = ?,
                    status = ?,
                    expected_date = ?,
                    pregnancy_status = ?,
                    pregnancy_check_date = ?,
                    calving_date = ?,
                    calf_tag_number = ?,
                    last_updated = CURRENT_TIMESTAMP
                WHERE record_id = ?
            ");

            $stmt->bind_param(
                "ssssidssssssssi",
                $breeding_date,
                $breeding_type,
                $sire_details,
                $semen_batch,
                $technician_id,
                $breeding_cost,
                $notes,
                $status,
                $expected_date,
                $pregnancy_status,
                $pregnancy_check_date,
                $calving_date,
                $calf_tag_number,
                $record_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Error updating breeding record: " . $stmt->error);
            }

            // Update cattle breeding status
            $stmt = $conn->prepare("
                UPDATE cattle 
                SET breeding_status = ?,
                    last_breeding_date = ?,
                    expected_delivery_date = ?
                WHERE cattle_id = ?
            ");

            $breeding_status = 'open';
            if ($status === 'pregnant' || $pregnancy_status === 'confirmed') {
                $breeding_status = 'pregnant';
            } elseif ($status === 'calved') {
                $breeding_status = 'open';
            } elseif ($status === 'successful') {
                $breeding_status = 'bred';
            }

            $stmt->bind_param(
                "sssi",
                $breeding_status,
                $breeding_date,
                $expected_date,
                $record['cattle_id']
            );

            if (!$stmt->execute()) {
                throw new Exception("Error updating cattle status: " . $stmt->error);
            }

            // Log the action
            log_action(
                $conn,
                'UPDATE',
                'breeding_records',
                $record_id,
                $old_values,
                [
                    'breeding_type' => $breeding_type,
                    'status' => $status,
                    'breeding_cost' => $breeding_cost
                ]
            );

            // Commit transaction
            $conn->commit();

            set_success_message("Breeding record updated successfully");
            header("Location: list.php");
            exit();

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}
?>

<div class="content-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>Edit Breeding Record</h2>
        <div>
            <a href="view.php?id=<?php echo $record_id; ?>" class="btn btn-secondary">View Details</a>
            <a href="list.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul style="margin: 0; padding-left: 1.5rem;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" onsubmit="return validateForm('editBreedingForm')" id="editBreedingForm">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
            <!-- Basic Information -->
            <div class="form-section">
                <h3>Basic Information</h3>
                
                <div class="form-group">
                    <label>Cattle</label>
                    <div class="static-value">
                        <strong><?php echo htmlspecialchars($record['tag_number']); ?></strong>
                        <?php if ($record['cattle_name']): ?>
                            (<?php echo htmlspecialchars($record['cattle_name']); ?>)
                        <?php endif; ?>
                        - <?php echo htmlspecialchars($record['breed']); ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="breeding_date">Breeding Date *</label>
                    <input type="date" 
                           id="breeding_date" 
                           name="breeding_date" 
                           class="form-control" 
                           required
                           max="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo $record['breeding_date']; ?>">
                </div>

                <div class="form-group">
                    <label for="breeding_type">Breeding Type *</label>
                    <select id="breeding_type" name="breeding_type" class="form-control" required>
                        <option value="natural" <?php echo $record['breeding_type'] === 'natural' ? 'selected' : ''; ?>>Natural</option>
                        <option value="artificial" <?php echo $record['breeding_type'] === 'artificial' ? 'selected' : ''; ?>>Artificial Insemination</option>
                        <option value="embryo_transfer" <?php echo $record['breeding_type'] === 'embryo_transfer' ? 'selected' : ''; ?>>Embryo Transfer</option>
                    </select>
                </div>
            </div>

            <!-- Breeding Details -->
            <div class="form-section">
                <h3>Breeding Details</h3>
                
                <div class="form-group">
                    <label for="sire_details">Sire Details *</label>
                    <textarea id="sire_details" 
                              name="sire_details" 
                              class="form-control" 
                              required
                              rows="3"><?php echo htmlspecialchars($record['sire_details']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="semen_batch">Semen Batch Number</label>
                    <input type="text" 
                           id="semen_batch" 
                           name="semen_batch" 
                           class="form-control"
                           value="<?php echo htmlspecialchars($record['semen_batch']); ?>">
                </div>

                <div class="form-group">
                    <label for="technician_id">Technician</label>
                    <select id="technician_id" name="technician_id" class="form-control">
                        <option value="">Select Technician</option>
                        <?php foreach ($tech_list as $tech): ?>
                            <option value="<?php echo $tech['user_id']; ?>"
                                    <?php echo $record['technician_id'] == $tech['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tech['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="breeding_cost">Breeding Cost (KSH) *</label>
                    <input type="number" 
                           id="breeding_cost" 
                           name="breeding_cost" 
                           class="form-control" 
                           step="0.01" 
                           min="0" 
                           required
                           value="<?php echo $record['breeding_cost']; ?>">
                </div>
            </div>

            <!-- Status Information -->
            <div class="form-section">
                <h3>Status Information</h3>
                
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="pending" <?php echo $record['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="successful" <?php echo $record['status'] === 'successful' ? 'selected' : ''; ?>>Successful</option>
                        <option value="failed" <?php echo $record['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="pregnant" <?php echo $record['status'] === 'pregnant' ? 'selected' : ''; ?>>Pregnant</option>
                        <option value="calved" <?php echo $record['status'] === 'calved' ? 'selected' : ''; ?>>Calved</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="pregnancy_status">Pregnancy Status</label>
                    <select id="pregnancy_status" name="pregnancy_status" class="form-control">
                        <option value="">Not Checked</option>
                        <option value="pending" <?php echo $record['pregnancy_status'] === 'pending' ? 'selected' : ''; ?>>Pending Check</option>
                        <option value="confirmed" <?php echo $record['pregnancy_status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="negative" <?php echo $record['pregnancy_status'] === 'negative' ? 'selected' : ''; ?>>Negative</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="pregnancy_check_date">Pregnancy Check Date</label>
                    <input type="date" 
                           id="pregnancy_check_date" 
                           name="pregnancy_check_date" 
                           class="form-control"
                           value="<?php echo $record['pregnancy_check_date']; ?>">
                </div>
            </div>

            <!-- Calving Information -->
            <div class="form-section">
                <h3>Calving Information</h3>
                
                <div class="form-group">
                    <label for="calving_date">Calving Date</label>
                    <input type="date" 
                           id="calving_date" 
                           name="calving_date" 
                           class="form-control"
                           value="<?php echo $record['calving_date']; ?>">
                </div>

                <div class="form-group">
                    <label for="calf_tag_number">Calf Tag Number</label>
                    <input type="text" 
                           id="calf_tag_number" 
                           name="calf_tag_number" 
                           class="form-control"
                           value="<?php echo htmlspecialchars($record['calf_tag_number']); ?>">
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" 
                              name="notes" 
                              class="form-control" 
                              rows="3"><?php echo htmlspecialchars($record['notes']); ?></textarea>
                </div>
            </div>
        </div>

        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary">Update Record</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<style>
.form-section {
    background: #f8fafc;
    padding: 1.5rem;
    border-radius: 5px;
    margin-bottom: 1rem;
}

.form-section h3 {
    margin-bottom: 1rem;
    color: #1a1a1a;
    font-size: 1.1rem;
}

.alert {
    padding: 1rem;
    border-radius: 5px;
    margin-bottom: 1rem;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #991b1b;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.static-value {
    padding: 0.5rem;
    background: #f3f4f6;
    border-radius: 5px;
    color: #374151;
}

.form-control {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 0.9rem;
}

.form-control:focus {
    outline: none;
    border-color: #1a1a1a;
}

textarea.form-control {
    resize: vertical;
}

.btn {
    margin-right: 0.5rem;
}
</style>

<?php
require_once '../../includes/footer.php';
?>
