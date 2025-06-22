<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !check_permission('admin') && !check_permission('manager')) {
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

// Get cattle details
$stmt = $conn->prepare("SELECT * FROM cattle WHERE cattle_id = ?");
$stmt->bind_param("i", $cattle_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_error_message("Cattle not found");
    header("Location: list.php");
    exit();
}

$cattle = $result->fetch_assoc();
$page_title = "Edit Cattle: " . $cattle['tag_number'];
require_once '../../includes/header.php';

// Get list of workers for assignment
$workers_query = "SELECT user_id, full_name FROM users WHERE role IN ('worker', 'vet', 'milker') AND status = 'active'";
$workers_result = $conn->query($workers_query);
$workers = $workers_result->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tag_number = sanitize_input($_POST['tag_number']);
    $cattle_name = sanitize_input($_POST['cattle_name']);
    $breed = sanitize_input($_POST['breed']);
    $date_of_birth = sanitize_input($_POST['date_of_birth']);
    $gender = sanitize_input($_POST['gender']);
    $health_status = sanitize_input($_POST['health_status']);
    $current_weight = sanitize_input($_POST['current_weight']);
    $assigned_worker = empty($_POST['assigned_worker']) ? null : (int)$_POST['assigned_worker'];
    $status = sanitize_input($_POST['status']);
    $notes = sanitize_input($_POST['notes']);

    // Validate inputs
    $errors = [];
    
    if (empty($tag_number)) {
        $errors[] = "Tag number is required";
    }
    
    if (empty($breed)) {
        $errors[] = "Breed is required";
    }
    
    if (!validate_date($date_of_birth)) {
        $errors[] = "Invalid date of birth";
    }
    
    if (!in_array($gender, ['male', 'female'])) {
        $errors[] = "Invalid gender";
    }
    
    // Check if tag number is unique (excluding current cattle)
    $stmt = $conn->prepare("SELECT cattle_id FROM cattle WHERE tag_number = ? AND cattle_id != ?");
    $stmt->bind_param("si", $tag_number, $cattle_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Tag number already exists";
    }

    if (empty($errors)) {
        // Store old values for audit log
        $old_values = [
            'tag_number' => $cattle['tag_number'],
            'cattle_name' => $cattle['cattle_name'],
            'breed' => $cattle['breed'],
            'health_status' => $cattle['health_status'],
            'status' => $cattle['status']
        ];

        $stmt = $conn->prepare("
            UPDATE cattle SET 
                tag_number = ?,
                cattle_name = ?,
                breed = ?,
                date_of_birth = ?,
                gender = ?,
                health_status = ?,
                current_weight = ?,
                assigned_worker = ?,
                status = ?,
                notes = ?
            WHERE cattle_id = ?
        ");

        $stmt->bind_param(
            "ssssssdissi",
            $tag_number,
            $cattle_name,
            $breed,
            $date_of_birth,
            $gender,
            $health_status,
            $current_weight,
            $assigned_worker,
            $status,
            $notes,
            $cattle_id
        );

        if ($stmt->execute()) {
            // Log the action
            log_action(
                $conn,
                'UPDATE',
                'cattle',
                $cattle_id,
                $old_values,
                [
                    'tag_number' => $tag_number,
                    'cattle_name' => $cattle_name,
                    'breed' => $breed,
                    'health_status' => $health_status,
                    'status' => $status
                ]
            );
            
            set_success_message("Cattle updated successfully");
            header("Location: list.php");
            exit();
        } else {
            $errors[] = "Error updating cattle: " . $conn->error;
        }
    }
}
?>

<div class="content-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>Edit Cattle</h2>
        <div>
            <a href="view.php?id=<?php echo $cattle_id; ?>" class="btn btn-secondary">View Details</a>
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

    <form method="POST" onsubmit="return validateForm('editCattleForm')" id="editCattleForm">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
            <div class="form-group">
                <label for="tag_number">Tag Number *</label>
                <input type="text" 
                       id="tag_number" 
                       name="tag_number" 
                       class="form-control" 
                       required 
                       value="<?php echo htmlspecialchars($cattle['tag_number']); ?>">
            </div>

            <div class="form-group">
                <label for="cattle_name">Cattle Name</label>
                <input type="text" 
                       id="cattle_name" 
                       name="cattle_name" 
                       class="form-control"
                       value="<?php echo htmlspecialchars($cattle['cattle_name']); ?>">
            </div>

            <div class="form-group">
                <label for="breed">Breed *</label>
                <input type="text" 
                       id="breed" 
                       name="breed" 
                       class="form-control" 
                       required
                       value="<?php echo htmlspecialchars($cattle['breed']); ?>">
            </div>

            <div class="form-group">
                <label for="date_of_birth">Date of Birth *</label>
                <input type="date" 
                       id="date_of_birth" 
                       name="date_of_birth" 
                       class="form-control" 
                       required
                       max="<?php echo date('Y-m-d'); ?>"
                       value="<?php echo $cattle['date_of_birth']; ?>">
            </div>

            <div class="form-group">
                <label for="gender">Gender *</label>
                <select id="gender" name="gender" class="form-control" required>
                    <option value="female" <?php echo $cattle['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                    <option value="male" <?php echo $cattle['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                </select>
            </div>

            <div class="form-group">
                <label for="health_status">Health Status *</label>
                <select id="health_status" name="health_status" class="form-control" required>
                    <option value="healthy" <?php echo $cattle['health_status'] === 'healthy' ? 'selected' : ''; ?>>Healthy</option>
                    <option value="sick" <?php echo $cattle['health_status'] === 'sick' ? 'selected' : ''; ?>>Sick</option>
                    <option value="under_treatment" <?php echo $cattle['health_status'] === 'under_treatment' ? 'selected' : ''; ?>>Under Treatment</option>
                    <option value="quarantine" <?php echo $cattle['health_status'] === 'quarantine' ? 'selected' : ''; ?>>Quarantine</option>
                </select>
            </div>

            <div class="form-group">
                <label for="current_weight">Current Weight (kg)</label>
                <input type="number" 
                       id="current_weight" 
                       name="current_weight" 
                       class="form-control" 
                       step="0.01" 
                       min="0"
                       value="<?php echo $cattle['current_weight']; ?>">
            </div>

            <div class="form-group">
                <label for="assigned_worker">Assigned Worker</label>
                <select id="assigned_worker" name="assigned_worker" class="form-control">
                    <option value="">Select Worker</option>
                    <?php foreach ($workers as $worker): ?>
                        <option value="<?php echo $worker['user_id']; ?>"
                                <?php echo $cattle['assigned_worker'] == $worker['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($worker['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Status *</label>
                <select id="status" name="status" class="form-control" required>
                    <option value="active" <?php echo $cattle['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="dead" <?php echo $cattle['status'] === 'dead' ? 'selected' : ''; ?>>Dead</option>
                    <option value="sold" <?php echo $cattle['status'] === 'sold' ? 'selected' : ''; ?>>Sold</option>
                    <option value="transferred" <?php echo $cattle['status'] === 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                </select>
            </div>
        </div>

        <div class="form-group" style="margin-top: 1rem;">
            <label for="notes">Notes</label>
            <textarea id="notes" 
                      name="notes" 
                      class="form-control" 
                      rows="4"><?php echo htmlspecialchars($cattle['notes']); ?></textarea>
        </div>

        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary">Update Cattle</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<style>
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
