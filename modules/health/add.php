<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !check_permission('admin') && !check_permission('vet')) {
    header("Location: /login.php");
    exit();
}

$page_title = "Add Health Record";
require_once '../../includes/header.php';

$db = new Database();
$conn = $db->connect();

// Get list of active cattle
$cattle_query = "
    SELECT c.cattle_id, c.tag_number, c.cattle_name, c.health_status 
    FROM cattle c 
    WHERE c.status = 'active' 
    ORDER BY c.tag_number
";
$cattle_result = $conn->query($cattle_query);
$cattle_list = $cattle_result->fetch_all(MYSQLI_ASSOC);

// Get list of veterinarians
$vet_query = "
    SELECT user_id, full_name 
    FROM users 
    WHERE role = 'vet' AND status = 'active' 
    ORDER BY full_name
";
$vet_result = $conn->query($vet_query);
$vet_list = $vet_result->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $cattle_id = (int)$_POST['cattle_id'];
    $date_of_checkup = sanitize_input($_POST['date_of_checkup']);
    $health_issue = sanitize_input($_POST['health_issue']);
    $symptoms = sanitize_input($_POST['symptoms']);
    $diagnosis = sanitize_input($_POST['diagnosis']);
    $treatment_given = sanitize_input($_POST['treatment_given']);
    $treatment_cost = sanitize_input($_POST['treatment_cost']);
    $medications = sanitize_input($_POST['medications']);
    $next_checkup_date = empty($_POST['next_checkup_date']) ? null : sanitize_input($_POST['next_checkup_date']);
    $attended_by = (int)$_POST['attended_by'];
    $notes = sanitize_input($_POST['notes']);
    $status = sanitize_input($_POST['status']);

    // Validate inputs
    $errors = [];
    
    if (empty($cattle_id)) {
        $errors[] = "Cattle selection is required";
    }
    
    if (empty($date_of_checkup) || !validate_date($date_of_checkup)) {
        $errors[] = "Valid checkup date is required";
    }
    
    if (empty($health_issue)) {
        $errors[] = "Health issue is required";
    }
    
    if (empty($treatment_given)) {
        $errors[] = "Treatment information is required";
    }
    
    if (!is_numeric($treatment_cost) || $treatment_cost < 0) {
        $errors[] = "Valid treatment cost is required";
    }
    
    if ($next_checkup_date && !validate_date($next_checkup_date)) {
        $errors[] = "Invalid next checkup date";
    }
    
    if (empty($attended_by)) {
        $errors[] = "Attending veterinarian is required";
    }

    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->begin_transaction();

            // Insert health record
            $stmt = $conn->prepare("
                INSERT INTO health_records (
                    cattle_id, date_of_checkup, health_issue,
                    symptoms, diagnosis, treatment_given,
                    treatment_cost, medications, next_checkup_date,
                    attended_by, notes, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "isssssdssiis",
                $cattle_id,
                $date_of_checkup,
                $health_issue,
                $symptoms,
                $diagnosis,
                $treatment_given,
                $treatment_cost,
                $medications,
                $next_checkup_date,
                $attended_by,
                $notes,
                $status
            );

            if (!$stmt->execute()) {
                throw new Exception("Error adding health record: " . $stmt->error);
            }

            $record_id = $conn->insert_id;

            // Update cattle health status
            $stmt = $conn->prepare("
                UPDATE cattle 
                SET health_status = ?, 
                    last_checkup = ?,
                    next_checkup = ?
                WHERE cattle_id = ?
            ");

            // Determine health status based on record status
            $health_status = 'healthy';
            if ($status === 'ongoing') {
                $health_status = 'sick';
            } elseif ($status === 'follow_up') {
                $health_status = 'under_treatment';
            }

            $stmt->bind_param(
                "sssi",
                $health_status,
                $date_of_checkup,
                $next_checkup_date,
                $cattle_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Error updating cattle status: " . $stmt->error);
            }

            // Log the action
            log_action(
                $conn,
                'CREATE',
                'health_records',
                $record_id,
                null,
                [
                    'cattle_id' => $cattle_id,
                    'health_issue' => $health_issue,
                    'status' => $status
                ]
            );

            // Commit transaction
            $conn->commit();

            set_success_message("Health record added successfully");
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
        <h2>Add Health Record</h2>
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

    <form method="POST" onsubmit="return validateForm('addHealthRecordForm')" id="addHealthRecordForm">
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
                                - <?php echo ucwords(str_replace('_', ' ', $cattle['health_status'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_of_checkup">Date of Checkup *</label>
                    <input type="date" 
                           id="date_of_checkup" 
                           name="date_of_checkup" 
                           class="form-control" 
                           required
                           max="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo $_POST['date_of_checkup'] ?? date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label for="attended_by">Attending Veterinarian *</label>
                    <select id="attended_by" name="attended_by" class="form-control" required>
                        <option value="">Select Veterinarian</option>
                        <?php foreach ($vet_list as $vet): ?>
                            <option value="<?php echo $vet['user_id']; ?>"
                                    <?php echo (isset($_POST['attended_by']) && $_POST['attended_by'] == $vet['user_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($vet['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Health Issue Details -->
            <div class="form-section">
                <h3>Health Issue Details</h3>
                
                <div class="form-group">
                    <label for="health_issue">Health Issue *</label>
                    <input type="text" 
                           id="health_issue" 
                           name="health_issue" 
                           class="form-control" 
                           required
                           value="<?php echo $_POST['health_issue'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label for="symptoms">Symptoms</label>
                    <textarea id="symptoms" 
                              name="symptoms" 
                              class="form-control" 
                              rows="3"><?php echo $_POST['symptoms'] ?? ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="diagnosis">Diagnosis</label>
                    <textarea id="diagnosis" 
                              name="diagnosis" 
                              class="form-control" 
                              rows="3"><?php echo $_POST['diagnosis'] ?? ''; ?></textarea>
                </div>
            </div>

            <!-- Treatment Information -->
            <div class="form-section">
                <h3>Treatment Information</h3>
                
                <div class="form-group">
                    <label for="treatment_given">Treatment Given *</label>
                    <textarea id="treatment_given" 
                              name="treatment_given" 
                              class="form-control" 
                              required
                              rows="3"><?php echo $_POST['treatment_given'] ?? ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="medications">Medications</label>
                    <textarea id="medications" 
                              name="medications" 
                              class="form-control" 
                              rows="3"><?php echo $_POST['medications'] ?? ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="treatment_cost">Treatment Cost (KSH) *</label>
                    <input type="number" 
                           id="treatment_cost" 
                           name="treatment_cost" 
                           class="form-control" 
                           step="0.01" 
                           min="0" 
                           required
                           value="<?php echo $_POST['treatment_cost'] ?? '0.00'; ?>">
                </div>
            </div>

            <!-- Follow-up Information -->
            <div class="form-section">
                <h3>Follow-up Information</h3>
                
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="ongoing" <?php echo (isset($_POST['status']) && $_POST['status'] === 'ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                        <option value="completed" <?php echo (isset($_POST['status']) && $_POST['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="follow_up" <?php echo (isset($_POST['status']) && $_POST['status'] === 'follow_up') ? 'selected' : ''; ?>>Follow Up Required</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="next_checkup_date">Next Checkup Date</label>
                    <input type="date" 
                           id="next_checkup_date" 
                           name="next_checkup_date" 
                           class="form-control"
                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                           value="<?php echo $_POST['next_checkup_date'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label for="notes">Additional Notes</label>
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
