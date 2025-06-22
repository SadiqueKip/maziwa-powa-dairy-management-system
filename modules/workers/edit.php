<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin permission
if (!isset($_SESSION['user_id']) || !check_permission('admin')) {
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
    SELECT w.*, u.full_name, u.email, u.phone_number, u.role, u.status 
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
$page_title = "Edit Worker: " . $worker['full_name'];
require_once '../../includes/header.php';

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
    $status = sanitize_input($_POST['status']);
    $new_password = sanitize_input($_POST['new_password']);

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

    // Check if email is unique (excluding current user)
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->bind_param("si", $email, $worker['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already exists";
    }

    // Check if ID number is unique (excluding current worker)
    $stmt = $conn->prepare("SELECT worker_id FROM workers WHERE id_number = ? AND worker_id != ?");
    $stmt->bind_param("si", $id_number, $worker_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "ID number already exists";
    }

    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->begin_transaction();

            // Store old values for audit log
            $old_values = [
                'full_name' => $worker['full_name'],
                'role' => $worker['role'],
                'status' => $worker['status'],
                'id_number' => $worker['id_number']
            ];

            // Update user account
            $stmt = $conn->prepare("
                UPDATE users SET 
                    full_name = ?,
                    email = ?,
                    phone_number = ?,
                    role = ?,
                    status = ?
                WHERE user_id = ?
            ");

            $stmt->bind_param("sssssi",
                $full_name,
                $email,
                $phone_number,
                $role,
                $status,
                $worker['user_id']
            );

            if (!$stmt->execute()) {
                throw new Exception("Error updating user account");
            }

            // Update password if provided
            if (!empty($new_password)) {
                if (strlen($new_password) < 8) {
                    throw new Exception("Password must be at least 8 characters long");
                }
                
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->bind_param("si", $password_hash, $worker['user_id']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error updating password");
                }
            }

            // Update worker record
            $stmt = $conn->prepare("
                UPDATE workers SET 
                    id_number = ?,
                    date_hired = ?,
                    assigned_duties = ?,
                    salary = ?
                WHERE worker_id = ?
            ");

            $stmt->bind_param("sssdi",
                $id_number,
                $date_hired,
                $assigned_duties,
                $salary,
                $worker_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Error updating worker record");
            }

            // Log the action
            log_action(
                $conn,
                'UPDATE',
                'workers',
                $worker_id,
                $old_values,
                [
                    'full_name' => $full_name,
                    'role' => $role,
                    'status' => $status,
                    'id_number' => $id_number
                ]
            );

            // Commit transaction
            $conn->commit();

            set_success_message("Worker updated successfully");
            header("Location: list.php");
            exit();

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = "Error updating worker: " . $e->getMessage();
        }
    }
}
?>

<div class="content-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>Edit Worker</h2>
        <div>
            <a href="view.php?id=<?php echo $worker_id; ?>" class="btn btn-secondary">View Details</a>
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

    <form method="POST" onsubmit="return validateForm('editWorkerForm')" id="editWorkerForm">
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
                           value="<?php echo htmlspecialchars($worker['full_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-control" 
                           required
                           value="<?php echo htmlspecialchars($worker['email']); ?>">
                </div>

                <div class="form-group">
                    <label for="phone_number">Phone Number *</label>
                    <input type="tel" 
                           id="phone_number" 
                           name="phone_number" 
                           class="form-control" 
                           required
                           placeholder="+254XXXXXXXXX"
                           value="<?php echo htmlspecialchars($worker['phone_number']); ?>">
                </div>

                <div class="form-group">
                    <label for="id_number">ID Number *</label>
                    <input type="text" 
                           id="id_number" 
                           name="id_number" 
                           class="form-control" 
                           required
                           value="<?php echo htmlspecialchars($worker['id_number']); ?>">
                </div>
            </div>

            <!-- Employment Information -->
            <div class="form-section">
                <h3>Employment Information</h3>
                
                <div class="form-group">
                    <label for="role">Role *</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="manager" <?php echo $worker['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                        <option value="worker" <?php echo $worker['role'] === 'worker' ? 'selected' : ''; ?>>Worker</option>
                        <option value="vet" <?php echo $worker['role'] === 'vet' ? 'selected' : ''; ?>>Veterinarian</option>
                        <option value="milker" <?php echo $worker['role'] === 'milker' ? 'selected' : ''; ?>>Milker</option>
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
                           value="<?php echo $worker['date_hired']; ?>">
                </div>

                <div class="form-group">
                    <label for="salary">Monthly Salary (KSH)</label>
                    <input type="number" 
                           id="salary" 
                           name="salary" 
                           class="form-control" 
                           step="0.01" 
                           min="0"
                           value="<?php echo $worker['salary']; ?>">
                </div>

                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="active" <?php echo $worker['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $worker['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Account Information -->
            <div class="form-section">
                <h3>Account Information</h3>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" 
                           id="new_password" 
                           name="new_password" 
                           class="form-control" 
                           minlength="8">
                    <small class="form-text">Leave blank to keep current password. Minimum 8 characters if changing.</small>
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-top: 1rem;">
            <label for="assigned_duties">Assigned Duties</label>
            <textarea id="assigned_duties" 
                      name="assigned_duties" 
                      class="form-control" 
                      rows="4"><?php echo htmlspecialchars($worker['assigned_duties']); ?></textarea>
        </div>

        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary">Update Worker</button>
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
