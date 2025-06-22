<?php
// Security functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validate_date($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function validate_phone($phone) {
    // Validate Kenyan phone numbers
    return preg_match('/^\+254[17][0-9]{8}$/', $phone);
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function check_permission($required_role) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    // Define role hierarchy
    $role_hierarchy = [
        'admin' => ['admin', 'manager', 'worker', 'vet', 'milker'],
        'manager' => ['manager', 'worker', 'vet', 'milker'],
        'vet' => ['vet'],
        'worker' => ['worker'],
        'milker' => ['milker']
    ];
    
    return in_array($_SESSION['role'], $role_hierarchy[$required_role] ?? []);
}

// Audit logging
function log_action($conn, $action, $table_name, $record_id = null, $old_values = null, $new_values = null) {
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (
            user_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $old_values_json = $old_values ? json_encode($old_values) : null;
    $new_values_json = $new_values ? json_encode($new_values) : null;
    
    $stmt->bind_param(
        "ississss",
        $user_id,
        $action,
        $table_name,
        $record_id,
        $old_values_json,
        $new_values_json,
        $ip_address,
        $user_agent
    );
    
    return $stmt->execute();
}

// Notification functions
function create_notification($conn, $user_id, $title, $message, $type = 'info', $action_url = null) {
    $stmt = $conn->prepare("
        INSERT INTO notifications (
            user_id, title, message, type, action_url
        ) VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("issss", $user_id, $title, $message, $type, $action_url);
    return $stmt->execute();
}

function get_unread_notifications($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = 0 
        ORDER BY created_at DESC
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Format functions
function format_currency($amount) {
    return 'KSH ' . number_format($amount, 2);
}

function format_date($date) {
    return date('j M Y', strtotime($date));
}

function format_datetime($datetime) {
    return date('j M Y, g:i A', strtotime($datetime));
}

// Pagination function
function paginate($total_records, $records_per_page, $current_page) {
    $total_pages = ceil($total_records / $records_per_page);
    $pagination = [];
    
    // Calculate start and end page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    // Previous page
    if ($current_page > 1) {
        $pagination['prev'] = $current_page - 1;
    }
    
    // Page numbers
    for ($i = $start_page; $i <= $end_page; $i++) {
        $pagination['pages'][] = [
            'number' => $i,
            'current' => ($i == $current_page)
        ];
    }
    
    // Next page
    if ($current_page < $total_pages) {
        $pagination['next'] = $current_page + 1;
    }
    
    return [
        'pagination' => $pagination,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'records_per_page' => $records_per_page,
        'total_records' => $total_records
    ];
}

// File upload function
function handle_file_upload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'], $max_size = 5242880) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload failed'];
    }
    
    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension']);
    
    // Validate file type
    if (!in_array($extension, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    // Validate file size (default 5MB)
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File size exceeds limit'];
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '.' . $extension;
    $upload_path = __DIR__ . '/../uploads/' . $new_filename;
    
    // Create uploads directory if it doesn't exist
    if (!file_exists(__DIR__ . '/../uploads')) {
        mkdir(__DIR__ . '/../uploads', 0755, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return [
            'success' => true,
            'filename' => $new_filename,
            'path' => $upload_path
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to save file'];
}

// Generate report function
function generate_report($conn, $report_type, $start_date, $end_date) {
    $result = [];
    
    switch ($report_type) {
        case 'milk_production':
            $sql = "
                SELECT 
                    mp.production_date,
                    SUM(mp.total_yield) as total_production,
                    COUNT(DISTINCT mp.cattle_id) as cattle_count,
                    AVG(mp.total_yield) as average_yield
                FROM milk_production mp
                WHERE mp.production_date BETWEEN ? AND ?
                GROUP BY mp.production_date
                ORDER BY mp.production_date
            ";
            break;
            
        case 'sales':
            $sql = "
                SELECT 
                    ms.sale_date,
                    SUM(ms.quantity_litres) as total_litres,
                    SUM(ms.total_amount) as total_revenue,
                    COUNT(*) as transaction_count
                FROM milk_sales ms
                WHERE ms.sale_date BETWEEN ? AND ?
                GROUP BY ms.sale_date
                ORDER BY ms.sale_date
            ";
            break;
            
        case 'expenses':
            $sql = "
                SELECT 
                    expense_category,
                    SUM(amount_ksh) as total_amount,
                    COUNT(*) as transaction_count
                FROM expenses
                WHERE expense_date BETWEEN ? AND ?
                GROUP BY expense_category
                ORDER BY total_amount DESC
            ";
            break;
            
        default:
            return ['error' => 'Invalid report type'];
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    return $result;
}

// Error handling function
function handle_error($error_message, $log_error = true) {
    if ($log_error) {
        error_log($error_message);
    }
    
    return [
        'success' => false,
        'error' => 'An error occurred. Please try again later.'
    ];
}

// Success message function
function set_success_message($message) {
    $_SESSION['success_message'] = $message;
}

// Error message function
function set_error_message($message) {
    $_SESSION['error_message'] = $message;
}

// Get and clear messages
function get_messages() {
    $messages = [
        'success' => $_SESSION['success_message'] ?? null,
        'error' => $_SESSION['error_message'] ?? null
    ];
    
    unset($_SESSION['success_message']);
    unset($_SESSION['error_message']);
    
    return $messages;
}
?>
