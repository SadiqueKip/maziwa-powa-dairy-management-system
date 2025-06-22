<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !check_permission('admin') && !check_permission('vet')) {
    header("Location: /login.php");
    exit();
}

$page_title = "Add Breeding Record";
require_once '../../includes/header.php';

$db = new Database();
$conn = $db->connect();

// Get list of female cattle
$cattle_query = "
    SELECT c.cattle_id, c.tag_number, c.cattle_name, c.breed, c.age_months 
    FROM cattle c 
    WHERE c.status = 'active' 
    AND c.gender = 'female'
    AND c.age_months >= 15
    ORDER BY c.tag_number
";
$cattle_result = $conn->query($cattle_query);
$cattle_list = $cattle_result->fetch_all(MYSQLI_ASSOC);

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
    $cattle_id = (int)$_POST['cattle_id'];
    $breeding_date = sanitize_input($_POST['breeding_date']);
    $breeding_type = sanitize_input($_POST['breeding_type']);
    $sire_details = sanitize_input($_POST['sire_details']);
    $semen_batch = sanitize_input($_POST['semen_batch']);
    $technician_id = (int)$_POST['technician_id'];
    $breeding_cost = sanitize_input($_POST['breeding_cost']);
    $notes = sanitize_input($_POST['notes']);
    $status = sanitize_input($_POST['status']);

    // Calculate expected date (285 days from breeding date for cattle)
    $expected_date = date('Y-m-d', strtotime($breeding_date . ' + 285 days'));

    // Validate inputs
    $errors = [];
    
    if (empty($cattle_id)) {
        $errors[] = "Cattle selection is required";
    }
    
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

    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->begin_transaction();

            // Insert breeding record
            $stmt = $conn->prepare("
                INSERT INTO breeding_records (
                    cattle_id, breeding_date, breeding_type,
                    sire_details, semen_batch, technician_id,
                    breeding_cost, notes, status, expected_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "issssidsss",
                $cattle_id,
                $breeding_date,
                $breeding_type,
                $sire_details,
                $semen_batch,
                $technician_id,
                $breeding_cost,
                $notes,
                $status,
                $expected_date
            );

            if (!$stmt->execute()) {
                throw new Exception("Error adding breeding record: " . $stmt->error);
            }

            $record_id = $conn->insert_id;

            // Update cattle breeding status
            $stmt = $conn->prepare("
                UPDATE cattle 
                SET breeding_status = ?,
                    last_breeding_date = ?,
                    expected_delivery_date = ?
                WHERE cattle_id = ?
            ");

            $breeding_status = $status === 'pregnant' ? 'pregnant' : 'bred';

            $stmt->bind_param(
                "sssi",
                $breeding_status,
                $breeding_date,
                $expected_date,
                $cattle_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Error updating cattle status: " . $stmt->error);
            }

            // Log the action
            log_action(
                $conn,
                'CREATE',
                'breeding_records',
                $record_id,
                null,
                [
                    'cattle_id' => $cattle_id,
                    'breeding_type' => $breeding_type,
                    'status' => $status
                ]
            );

            // Commit transaction
            $conn->commit();

            set_success_message("Breeding record added successfully");
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
        <h2>Add Breeding Record</h2>
        <a href="list.php" class="btn btn-secondary">Back to List</a>
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

    <form method="POST" onsubmit="return validateForm('addBreedingForm')" id="addBreedingForm">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
            <!-- Basic Information -->
            <div class="form-section">
                <h3>Basic Information</h3>
                
                <div class="form-group">
                    <label for="cattle_id">Select Cattle *</label>
                    <select id="cattle_id" name="cattle_id" class="form-control" required>
                        <option value="">Select Cattle</option>
                        <?php foreach ($cattle_list as $cattle): ?>
                            <option value="<?php echo $cattle['cattle_id']; ?>"
                                    <?php echo (isset($_POST['cattle_id']) && $_POST['cattle_id'] == $cattle['cattle_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cattle['tag_number']); ?>
                                (<?php echo htmlspecialchars($cattle['cattle_name'] ?? 'Unnamed'); ?>)
                                - <?php echo htmlspecialchars($cattle['breed']); ?>
                                - <?php echo $cattle['age_months']; ?> months
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="breeding_date">Breeding Date *</label>
                    <input type="date" 
                           id="breeding_date" 
                           name="breeding_date" 
                           class="form-control" 
                           required
                           max="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo $_POST['breeding_date'] ?? date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label for="breeding_type">Breeding Type *</label>
                    <select id="breeding_type" name="breeding_type" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="natural" <?php echo (isset($_POST['breeding_type']) && $_POST['breeding_type'] === 'natural') ? 'selected' : ''; ?>>Natural</option>
                        <option value="artificial" <?php echo (isset($_POST['breeding_type']) && $_POST['breeding_type'] === 'artificial') ? 'selected' : ''; ?>>Artificial Insemination</option>
                        <option value="embryo_transfer" <?php echo (isset($_POST['breeding_type']) && $_POST['breeding_type'] === 'embryo_transfer') ? 'selected' : ''; ?>>Embryo Transfer</option>
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
                              rows="3"
                              placeholder="Enter breed, registration number, and other relevant details"><?php echo $_POST['sire_details'] ?? ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="semen_batch">Semen Batch Number</label>
                    <input type="text" 
                           id="semen_batch" 
                           name="semen_batch" 
                           class="form-control"
                           value="<?php echo $_POST['semen_batch'] ?? ''; ?>">
                    <small class="form-text">Required for artificial insemination</small>
                </div>

                <div class="form-group">
                    <label for="technician_id">Technician</label>
                    <select id="technician_id" name="technician_id" class="form-control">
                        <option value="">Select Technician</option>
                        <?php foreach ($tech_list as $tech): ?>
                            <option value="<?php echo $tech['user_id']; ?>"
                                    <?php echo (isset($_POST['technician_id']) && $_POST['technician_id'] == $tech['user_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tech['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text">Required for artificial insemination or embryo transfer</small>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="form-section">
                <h3>Additional Information</h3>
                
                <div class="form-group">
                    <label for="breeding_cost">Breeding Cost (KSH) *</label>
                    <input type="number" 
                           id="breeding_cost" 
                           name="breeding_cost" 
                           class="form-control" 
                           step="0.01" 
                           min="0" 
                           required
                           value="<?php echo $_POST['breeding_cost'] ?? '0.00'; ?>">
                </div>

                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="pending" <?php echo (isset($_POST['status']) && $_POST['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="successful" <?php echo (isset($_POST['status']) && $_POST['status'] === 'successful') ? 'selected' : ''; ?>>Successful</option>
                        <option value="failed" <?php echo (isset($_POST['status']) && $_POST['status'] === 'failed') ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" 
                              name="notes" 
                              class="form-control" 
                              rows="3"><?php echo $_POST['notes'] ?? ''; ?></textarea>
                </div>
            </div>
        </div>

        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary">Add Record</button>
            <button type="reset" class="btn btn-secondary">Reset Form</button>
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

.form-text {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: #666;
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
