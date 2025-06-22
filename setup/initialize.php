<?php
require_once '../config/database.php';

function initialize_system() {
    $db = new Database();
    $conn = $db->connect();
    
    if (!$conn) {
        die("Database connection failed");
    }

    try {
        // Begin transaction
        $conn->begin_transaction();

        // Create admin user
        $fullName = 'Superuser';
        $username = 'Superuser';
        $email = 'sadiqkipkurui@gmail.com';
        $phoneNumber = '+254723348502';
        $password = 'Superuser!20251';
        $role = 'admin';
        
        // Check if admin user already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception("Admin user already exists!");
        }
        
        // Create user
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO users (
                full_name, 
                username, 
                email, 
                phone_number, 
                password_hash, 
                role, 
                status
            ) VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");
        
        $stmt->bind_param("ssssss", 
            $fullName, 
            $username, 
            $email, 
            $phoneNumber, 
            $passwordHash, 
            $role
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating admin user: " . $stmt->error);
        }
        
        $userId = $conn->insert_id;
        
        // Create worker record for admin
        $stmt = $conn->prepare("
            INSERT INTO workers (
                user_id,
                date_hired,
                assigned_duties
            ) VALUES (?, CURRENT_DATE, 'System Administrator')
        ");
        
        $stmt->bind_param("i", $userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating worker record: " . $stmt->error);
        }
        
        // Insert default system settings
        $stmt = $conn->prepare("
            INSERT INTO system_settings (
                farm_name,
                farm_location,
                farm_phone,
                farm_email,
                default_milk_price,
                updated_by
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $farmName = 'Creamline Dairy Kapsaos';
        $farmLocation = 'Kapsaos, Kenya';
        $farmPhone = '+254723348502';
        $farmEmail = 'info@creamlinedairy.co.ke';
        $defaultMilkPrice = 55.00;
        
        $stmt->bind_param("ssssdi",
            $farmName,
            $farmLocation,
            $farmPhone,
            $farmEmail,
            $defaultMilkPrice,
            $userId
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating system settings: " . $stmt->error);
        }
        
        // Create audit log entry
        $stmt = $conn->prepare("
            INSERT INTO audit_logs (
                user_id,
                action,
                table_name,
                record_id,
                new_values,
                ip_address
            ) VALUES (?, 'SYSTEM_INIT', 'users', ?, ?, ?)
        ");
        
        $newValues = json_encode([
            'username' => $username,
            'email' => $email,
            'role' => $role
        ]);
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt->bind_param("iiss", 
            $userId, 
            $userId, 
            $newValues, 
            $ipAddress
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating audit log: " . $stmt->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        echo "System initialization completed successfully!\n";
        echo "Admin user created with following credentials:\n";
        echo "Username: " . $username . "\n";
        echo "Email: " . $email . "\n";
        echo "Password: " . $password . "\n";
        echo "\nPlease change the password after first login.\n";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo "Error: " . $e->getMessage() . "\n";
        error_log("System initialization error: " . $e->getMessage());
    } finally {
        $db->close();
    }
}

// Execute initialization
initialize_system();

// Delete this file after successful execution
if (isset($argv[0])) {
    // Only delete if running from command line
    @unlink($argv[0]);
}
?>
