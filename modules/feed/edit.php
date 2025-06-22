<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !check_permission('admin') && !check_permission('manager')) {
    header("Location: /login.php");
    exit();
}

// Check if feed ID is provided
if (!isset($_GET['id'])) {
    set_error_message("No feed ID provided");
    header("Location: list.php");
    exit();
}

$feed_id = (int)$_GET['id'];

$db = new Database();
$conn = $db->connect();

// Get feed details
$stmt = $conn->prepare("SELECT * FROM feed_inventory WHERE feed_id = ?");
$stmt->bind_param("i", $feed_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_error_message("Feed not found");
    header("Location: list.php");
    exit();
}

$feed = $result->fetch_assoc();
$page_title = "Edit Feed: " . $feed['feed_name'];
require_once '../../includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $feed_name = sanitize_input($_POST['feed_name']);
    $feed_type = sanitize_input($_POST['feed_type']);
    $description = sanitize_input($_POST['description']);
    $supplier = sanitize_input($_POST['supplier']);
    $unit_of_measure = sanitize_input($_POST['unit_of_measure']);
    $unit_cost = sanitize_input($_POST['unit_cost']);
    $current_quantity = sanitize_input($_POST['current_quantity']);
    $reorder_level = sanitize_input($_POST['reorder_level']);
    $expiry_date = sanitize_input($_POST['expiry_date']);
    $storage_location = sanitize_input($_POST['storage_location']);
    $notes = sanitize_input($_POST['notes']);

    // Validate inputs
    $errors = [];
    
    if (empty($feed_name)) {
        $errors[] = "Feed name is required";
    }
    
    if (empty($feed_type)) {
        $errors[] = "Feed type is required";
    }
    
    if (empty($unit_of_measure)) {
        $errors[] = "Unit of measure is required";
    }
    
    if (!is_numeric($unit_cost) || $unit_cost < 0) {
        $errors[] = "Valid unit cost is required";
    }
    
    if (!is_numeric($current_quantity) || $current_quantity < 0) {
        $errors[] = "Valid quantity is required";
    }
    
    if (!is_numeric($reorder_level) || $reorder_level < 0) {
        $errors[] = "Valid reorder level is required";
    }
    
    if (empty($expiry_date) || !validate_date($expiry_date)) {
        $errors[] = "Valid expiry date is required";
    }

    // Determine status based on quantity
    $status = 'in_stock';
    if ($current_quantity <= 0) {
        $status = 'out_of_stock';
    } elseif ($current_quantity <= $reorder_level) {
        $status = 'low_stock';
    }

    // Check if expiry date has passed
    $today = new DateTime();
    $expiry = new DateTime($expiry_date);
    if ($expiry < $today) {
        $status = 'expired';
    }

    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->begin_transaction();

            // Store old values for audit log
            $old_values = [
                'feed_name' => $feed['feed_name'],
                'feed_type' => $feed['feed_type'],
                'current_quantity' => $feed['current_quantity'],
                'unit_cost' => $feed['unit_cost'],
                'status' => $feed['status']
            ];

            // Update feed record
            $stmt = $conn->prepare("
                UPDATE feed_inventory SET 
                    feed_name = ?,
                    feed_type = ?,
                    description = ?,
                    supplier = ?,
                    unit_of_measure = ?,
                    unit_cost = ?,
                    current_quantity = ?,
                    reorder_level = ?,
                    expiry_date = ?,
                    storage_location = ?,
                    status = ?,
                    notes = ?,
                    last_updated = CURRENT_TIMESTAMP
                WHERE feed_id = ?
            ");

            $stmt->bind_param(
                "sssssdddssssi",
                $feed_name,
                $feed_type,
                $description,
                $supplier,
                $unit_of_measure,
                $unit_cost,
                $current_quantity,
                $reorder_level,
                $expiry_date,
                $storage_location,
                $status,
                $notes,
                $feed_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Error updating feed record: " . $stmt->error);
            }

            // If quantity changed, log the adjustment
            if ($current_quantity != $feed['current_quantity']) {
                $quantity_difference = $current_quantity - $feed['current_quantity'];
                $transaction_type = $quantity_difference > 0 ? 'adjustment_add' : 'adjustment_subtract';
                $adjustment_quantity = abs($quantity_difference);
                
                $stmt = $conn->prepare("
                    INSERT INTO feed_transactions (
                        feed_id, transaction_type, quantity,
                        unit_cost, total_cost, notes
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");

                $total_cost = $unit_cost * $adjustment_quantity;
                $adjustment_note = "Quantity adjusted during edit";
                
                $stmt->bind_param(
                    "isddds",
                    $feed_id,
                    $transaction_type,
                    $adjustment_quantity,
                    $unit_cost,
                    $total_cost,
                    $adjustment_note
                );

                if (!$stmt->execute()) {
                    throw new Exception("Error logging quantity adjustment: " . $stmt->error);
                }
            }

            // Log the action
            log_action(
                $conn,
                'UPDATE',
                'feed_inventory',
                $feed_id,
                $old_values,
                [
                    'feed_name' => $feed_name,
                    'feed_type' => $feed_type,
                    'current_quantity' => $current_quantity,
                    'unit_cost' => $unit_cost,
                    'status' => $status
                ]
            );

            // Commit transaction
            $conn->commit();

            set_success_message("Feed updated successfully");
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
        <h2>Edit Feed</h2>
        <div>
            <a href="view.php?id=<?php echo $feed_id; ?>" class="btn btn-secondary">View Details</a>
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

    <form method="POST" onsubmit="return validateForm('editFeedForm')" id="editFeedForm">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
            <!-- Basic Information -->
            <div class="form-section">
                <h3>Basic Information</h3>
                
                <div class="form-group">
                    <label for="feed_name">Feed Name *</label>
                    <input type="text" 
                           id="feed_name" 
                           name="feed_name" 
                           class="form-control" 
                           required 
                           value="<?php echo htmlspecialchars($feed['feed_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="feed_type">Feed Type *</label>
                    <select id="feed_type" name="feed_type" class="form-control" required>
                        <option value="hay" <?php echo $feed['feed_type'] === 'hay' ? 'selected' : ''; ?>>Hay</option>
                        <option value="silage" <?php echo $feed['feed_type'] === 'silage' ? 'selected' : ''; ?>>Silage</option>
                        <option value="concentrate" <?php echo $feed['feed_type'] === 'concentrate' ? 'selected' : ''; ?>>Concentrate</option>
                        <option value="mineral" <?php echo $feed['feed_type'] === 'mineral' ? 'selected' : ''; ?>>Mineral</option>
                        <option value="supplement" <?php echo $feed['feed_type'] === 'supplement' ? 'selected' : ''; ?>>Supplement</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" 
                              name="description" 
                              class="form-control" 
                              rows="3"><?php echo htmlspecialchars($feed['description']); ?></textarea>
                </div>
            </div>

            <!-- Supplier Information -->
            <div class="form-section">
                <h3>Supplier Information</h3>
                
                <div class="form-group">
                    <label for="supplier">Supplier Name</label>
                    <input type="text" 
                           id="supplier" 
                           name="supplier" 
                           class="form-control"
                           value="<?php echo htmlspecialchars($feed['supplier']); ?>">
                </div>

                <div class="form-group">
                    <label for="unit_of_measure">Unit of Measure *</label>
                    <select id="unit_of_measure" name="unit_of_measure" class="form-control" required>
                        <option value="kg" <?php echo $feed['unit_of_measure'] === 'kg' ? 'selected' : ''; ?>>Kilograms (kg)</option>
                        <option value="bale" <?php echo $feed['unit_of_measure'] === 'bale' ? 'selected' : ''; ?>>Bales</option>
                        <option value="bag" <?php echo $feed['unit_of_measure'] === 'bag' ? 'selected' : ''; ?>>Bags</option>
                        <option value="ton" <?php echo $feed['unit_of_measure'] === 'ton' ? 'selected' : ''; ?>>Tons</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="unit_cost">Unit Cost (KSH) *</label>
                    <input type="number" 
                           id="unit_cost" 
                           name="unit_cost" 
                           class="form-control" 
                           step="0.01" 
                           min="0" 
                           required
                           value="<?php echo $feed['unit_cost']; ?>">
                </div>
            </div>

            <!-- Stock Information -->
            <div class="form-section">
                <h3>Stock Information</h3>
                
                <div class="form-group">
                    <label for="current_quantity">Current Quantity *</label>
                    <input type="number" 
                           id="current_quantity" 
                           name="current_quantity" 
                           class="form-control" 
                           step="0.01" 
                           min="0" 
                           required
                           value="<?php echo $feed['current_quantity']; ?>">
                </div>

                <div class="form-group">
                    <label for="reorder_level">Reorder Level *</label>
                    <input type="number" 
                           id="reorder_level" 
                           name="reorder_level" 
                           class="form-control" 
                           step="0.01" 
                           min="0" 
                           required
                           value="<?php echo $feed['reorder_level']; ?>">
                </div>

                <div class="form-group">
                    <label for="expiry_date">Expiry Date *</label>
                    <input type="date" 
                           id="expiry_date" 
                           name="expiry_date" 
                           class="form-control" 
                           required
                           value="<?php echo $feed['expiry_date']; ?>">
                </div>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="form-section" style="margin-top: 1rem;">
            <h3>Additional Information</h3>
            
            <div class="form-group">
                <label for="storage_location">Storage Location</label>
                <input type="text" 
                       id="storage_location" 
                       name="storage_location" 
                       class="form-control"
                       value="<?php echo htmlspecialchars($feed['storage_location']); ?>">
            </div>

            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" 
                          name="notes" 
                          class="form-control" 
                          rows="4"><?php echo htmlspecialchars($feed['notes']); ?></textarea>
            </div>
        </div>

        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary">Update Feed</button>
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
