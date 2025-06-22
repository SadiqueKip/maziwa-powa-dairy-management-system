<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !check_permission('admin') && !check_permission('manager')) {
    header("Location: /login.php");
    exit();
}

$page_title = "Add New Cattle";
require_once '../../includes/header.php';

$db = new Database();
$conn = $db->connect();

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
    
    // Check if tag number is unique
    $stmt = $conn->prepare("SELECT cattle_id FROM cattle WHERE tag_number = ?");
    $stmt->bind_param("s", $tag_number);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Tag number already exists";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO cattle (
                tag_number, cattle_name, breed, date_of_birth, 
                gender, health_status, current_weight, assigned_worker, 
                status, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)
        ");

        $stmt->bind_param(
            "ssssssdis",
            $tag_number,
            $cattle_name,
            $breed,
            $date_of_birth,
            $gender,
            $health_status,
            $current_weight,
            $assigned_worker,
            $notes
        );

        if ($stmt->execute()) {
            $cattle_id = $conn->insert_id;
            
            // Log the action
            log_action(
                $conn,
                'CREATE',
                'cattle',
                $cattle_id,
                null,
                [
                    'tag_number' => $tag_number,
                    'cattle_name' => $cattle_name,
                    'breed' => $breed,
                    'date_of_birth' => $date_of_birth,
                    'gender' => $gender,
                    'health_status' => $health_status
                ]
            );
            
            set_success_message("Cattle added successfully");
            header("Location: list.php");
            exit();
        } else {
            $errors[] = "Error adding cattle: " . $conn->error;
        }
    }
}
?>

<div class="content-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>Add New Cattle</h2>
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

    <form method="POST" onsubmit="return validateForm('addCattleForm')" id="addCattleForm">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
            <div class="form-group">
                <label for="tag_number">Tag Number *</label>
                <input type="text" 
                       id="tag_number" 
                       name="tag_number" 
                       class="form-control" 
                       required 
                       value="<?php echo $_POST['tag_number'] ?? ''; ?>">
            </div>

            <div class="form-group">
                <label for="cattle_name">Cattle Name</label>
                <input type="text" 
                       id="cattle_name" 
                       name="cattle_name" 
                       class="form-control"
                       value="<?php echo $_POST['cattle_name'] ?? ''; ?>">
            </div>

            <div class="form-group">
                <label for="breed">Breed *</label>
                <input type="text" 
                       id="breed" 
                       name="breed" 
                       class="form-control" 
                       required
                       value="<?php echo $_POST['breed'] ?? ''; ?>">
            </div>

            <div class="form-group">
                <label for="date_of_birth">Date of Birth *</label>
                <input type="date" 
                       id="date_of_birth" 
                       name="date_of_birth" 
                       class="form-control" 
                       required
                       max="<?php echo date('Y-m-d'); ?>"
                       value="<?php echo $_POST['date_of_birth'] ?? ''; ?>">
            </div>

            <div class="form-group">
                <label for="gender">Gender *</label>
                <select id="gender" name="gender" class="form-control" required>
                    <option value="">Select Gender</option>
                    <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                    <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                </select>
            </div>

            <div class="form-group">
                <label for="health_status">Health Status *</label>
                <select id="health_status" name="health_status" class="form-control" required>
                    <option value="healthy" <?php echo (isset($_POST['health_status']) && $_POST['health_status'] === 'healthy') ? 'selected' : ''; ?>>Healthy</option>
                    <option value="sick" <?php echo (isset($_POST['health_status']) && $_POST['health_status'] === 'sick') ? 'selected' : ''; ?>>Sick</option>
                    <option value="under_treatment" <?php echo (isset($_POST['health_status']) && $_POST['health_status'] === 'under_treatment') ? 'selected' : ''; ?>>Under Treatment</option>
                    <option value="quarantine" <?php echo (isset($_POST['health_status']) && $_POST['health_status'] === 'quarantine') ? 'selected' : ''; ?>>Quarantine</option>
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
                       value="<?php echo $_POST['current_weight'] ?? ''; ?>">
            </div>

            <div class="form-group">
                <label for="assigned_worker">Assigned Worker</label>
                <select id="assigned_worker" name="assigned_worker" class="form-control">
                    <option value="">Select Worker</option>
                    <?php foreach ($workers as $worker): ?>
                        <option value="<?php echo $worker['user_id']; ?>"
                                <?php echo (isset($_POST['assigned_worker']) && $_POST['assigned_worker'] == $worker['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($worker['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group" style="margin-top: 1rem;">
            <label for="notes">Notes</label>
            <textarea id="notes" 
                      name="notes" 
                      class="form-control" 
                      rows="4"><?php echo $_POST['notes'] ?? ''; ?></textarea>
        </div>

        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary">Add Cattle</button>
            <button type="reset" class="btn btn-secondary">Reset Form</button>
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
