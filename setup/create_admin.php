<?php
require_once '../config/database.php';

function createAdminUser() {
    $db = new Database();
    $conn = $db->connect();
    
    if (!$conn) {
        die("Database connection failed");
    }

    // Admin user details
    $fullName = 'Superuser';
    $username = 'Superuser';
    $email = 'sadiqkipkurui@gmail.com';
    $phoneNumber = '+254723348502';
    $password = 'Superuser!20251';
    $role = 'admin';
    
    try {
        // Check if admin user already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "Admin user already exists!";
            return;
        }
        
        // Begin transaction
        $conn->begin_transaction();
        
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
        
        // Create audit log entry
        $stmt = $conn->prepare("
            INSERT INTO audit_logs (
                user_id,
                action,
                table_name,
                record_id,
                new_values,
                ip_address
            ) VALUES (?, 'CREATE_ADMIN', 'users', ?, ?, ?)
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
        
        echo "Admin user created successfully!\n";
        echo "Username: " . $username . "\n";
        echo "Email: " . $email . "\n";
        echo "Role: " . $role . "\n";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo "Error: " . $e->getMessage();
        error_log("Admin creation error: " . $e->getMessage());
    } finally {
        $db->close();
    }
}

// Execute the function
createAdminUser();
?>
