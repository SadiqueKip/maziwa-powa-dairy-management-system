<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin permission
if (!isset($_SESSION['user_id']) || !check_permission('admin')) {
    header("Location: /login.php");
    exit();
}

$page_title = "Add New Worker";
require_once '../../includes/header.php';

$db = new Database();
$conn = $db->connect();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $full_name = sanitize_input($_POST['full_name']);
    $email = sanitize_input($_POST['email']);
    $phone_number = sanitize_input($_POST['phone_number']);
    $id_number = sanitize_input($_POST['id_number']);
    $role = sanitize_input($_POST['role']);
    $date_hired = sanitize_input($_POST['date_hired']);
    $salary = sanitize_input($_POST['salary']);
    $assigned_duties = sanitize_input($_POST['assigned_duties']);
    $password = sanitize_input($_POST['password']);
    $confirm_password = sanitize_input($_POST['confirm_password']);

    // Generate username from email
    $username = explode('@', $email)[0];

    // Validate inputs
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email) || !validate_email($email)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($phone_number) || !validate_phone($phone_number)) {
        $errors[] = "Valid phone number is required (format: +254XXXXXXXXX)";
    }
    
    if (empty($id_number)) {
        $errors[] = "ID number is required";
    }
    
    if (empty($role)) {
        $errors[] = "Role is required";
    }
    
    if (empty($date_hired) || !validate_date($date_hired)) {
        $errors[] = "Valid hire date is required";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }

    // Check if email is unique
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already exists";
    }

    // Check if ID number is unique
    $stmt = $conn->prepare("SELECT worker_id FROM workers WHERE id_number = ?");
    $stmt->bind_param("s", $id_number);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "ID number already exists";
    }

    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->begin_transaction();

            // Create user account
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO users (
                    full_name, username, email, phone_number, 
                    password_hash, role, status
                ) VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");

            $stmt->bind_param("ssssss",
                $full_name,
                $username,
                $email,
                $phone_number,
                $password_hash,
                $role
            );

            if (!$stmt->execute()) {
                throw new Exception("Error creating user account");
            }

            $user_id = $conn->insert_id;

            // Create worker record
            $stmt = $conn->prepare("
                INSERT INTO workers (
                    user_id, id_number, date_hired, 
                    assigned_duties, salary
                ) VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->bind_param("isssd",
                $user_id,
                $id_number,
                $date_hired,
                $assigned_duties,
                $salary
            );

            if (!$stmt->execute()) {
                throw new Exception("Error creating worker record");
            }

            $worker_id = $conn->insert_id;

            // Log the action
            log_action(
                $conn,
                'CREATE',
                'workers',
                $worker_id,
                null,
                [
                    'full_name' => $full_name,
                    'role' => $role,
                    'id_number' => $id_number
                ]
            );

            // Commit transaction
            $conn->commit();

            set_success_message("Worker added successfully");
            header("Location: list.php");
            exit();

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = "Error adding worker: " . $e->getMessage();
        }
    }
}
?>

<div class="content-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>Add New Worker</h2>
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

    <form method="POST" onsubmit="return validateForm('addWorkerForm')" id="addWorkerForm">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
            <!-- Personal Information -->
            <div class="form-section">
                <h3>Personal Information</h3>
                
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" 
                           id="full_name" 
                           name="full_name" 
                           class="form-control" 
                           required 
                           value="<?php echo $_POST['full_name'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-control" 
                           required
                           value="<?php echo $_POST['email'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label for="phone_number">Phone Number *</label>
                    <input type="tel" 
                           id="phone_number" 
                           name="phone_number" 
                           class="form-control" 
                           required
                           placeholder="+254XXXXXXXXX"
                           value="<?php echo $_POST['phone_number'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label for="id_number">ID Number *</label>
                    <input type="text" 
                           id="id_number" 
                           name="id_number" 
                           class="form-control" 
                           required
                           value="<?php echo $_POST['id_number'] ?? ''; ?>">
                </div>
            </div>

            <!-- Employment Information -->
            <div class="form-section">
                <h3>Employment Information</h3>
                
                <div class="form-group">
                    <label for="role">Role *</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="">Select Role</option>
                        <option value="manager" <?php echo (isset($_POST['role']) && $_POST['role'] === 'manager') ? 'selected' : ''; ?>>Manager</option>
                        <option value="worker" <?php echo (isset($_POST['role']) && $_POST['role'] === 'worker') ? 'selected' : ''; ?>>Worker</option>
                        <option value="vet" <?php echo (isset($_POST['role']) && $_POST['role'] === 'vet') ? 'selected' : ''; ?>>Veterinarian</option>
                        <option value="milker" <?php echo (isset($_POST['role']) && $_POST['role'] === 'milker') ? 'selected' : ''; ?>>Milker</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_hired">Date Hired *</label>
                    <input type="date" 
                           id="date_hired" 
                           name="date_hired" 
                           class="form-control" 
                           required
                           max="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo $_POST['date_hired'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label for="salary">Monthly Salary (KSH)</label>
                    <input type="number" 
                           id="salary" 
                           name="salary" 
                           class="form-control" 
                           step="0.01" 
                           min="0"
                           value="<?php echo $_POST['salary'] ?? ''; ?>">
                </div>
            </div>

            <!-- Account Information -->
            <div class="form-section">
                <h3>Account Information</h3>
                
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control" 
                           required 
                           minlength="8">
                    <small class="form-text">Minimum 8 characters</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="form-control" 
                           required 
                           minlength="8">
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-top: 1rem;">
            <label for="assigned_duties">Assigned Duties</label>
            <textarea id="assigned_duties" 
                      name="assigned_duties" 
                      class="form-control" 
                      rows="4"><?php echo $_POST['assigned_duties'] ?? ''; ?></textarea>
        </div>

        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary">Add Worker</button>
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
