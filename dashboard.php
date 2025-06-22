<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    die("Database connection failed");
}

// Fetch user data
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT full_name, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Fetch quick statistics
$stats = [
    'total_cattle' => 0,
    'total_workers' => 0,
    'milk_today' => 0,
    'revenue_today' => 0
];

// Get total active cattle
$result = $conn->query("SELECT COUNT(*) as count FROM cattle WHERE status = 'active'");
$stats['total_cattle'] = $result->fetch_assoc()['count'];

// Get total active workers
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active'");
$stats['total_workers'] = $result->fetch_assoc()['count'];

// Get today's milk production
$result = $conn->query("SELECT SUM(total_yield) as total FROM milk_production WHERE production_date = CURRENT_DATE");
$row = $result->fetch_assoc();
$stats['milk_today'] = $row['total'] ?? 0;

// Get today's revenue
$result = $conn->query("SELECT SUM(total_amount) as total FROM milk_sales WHERE sale_date = CURRENT_DATE");
$row = $result->fetch_assoc();
$stats['revenue_today'] = $row['total'] ?? 0;

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MaziwaPowa Dairy Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
            color: #333;
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: #1a1a1a;
            color: white;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
        }

        .sidebar-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .sidebar-logo h1 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }

        .sidebar-menu a {
            color: white;
            text-decoration: none;
            padding: 0.75rem 1rem;
            display: block;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            padding: 2rem;
            background: #f5f5f5;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .recent-activity {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .recent-activity h2 {
            margin-bottom: 1rem;
        }

        .activity-list {
            list-style: none;
        }

        .activity-list li {
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }

        .activity-list li:last-child {
            border-bottom: none;
        }

        .logout-btn {
            margin-top: auto;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                padding: 1rem;
            }

            .main-content {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-logo">
                <h1>MaziwaPowa</h1>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="modules/cattle/list.php">Cattle Management</a></li>
                <li><a href="modules/workers/list.php">Worker Management</a></li>
                <li><a href="modules/feed/list.php">Feed Management</a></li>
                <li><a href="modules/health/list.php">Health Records</a></li>
                <li><a href="modules/breeding/list.php">Breeding Records</a></li>
                <li><a href="modules/milk-sales/list.php">Milk Sales</a></li>
                <li><a href="modules/expenses/list.php">Expenses</a></li>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <li><a href="modules/reports/index.php">Reports</a></li>
                <li><a href="modules/settings/index.php">Settings</a></li>
                <?php endif; ?>
            </ul>

            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Dashboard</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                    <span>(<?php echo htmlspecialchars($user['role']); ?>)</span>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Active Cattle</h3>
                    <div class="value"><?php echo number_format($stats['total_cattle']); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Workers</h3>
                    <div class="value"><?php echo number_format($stats['total_workers']); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Today's Milk Production</h3>
                    <div class="value"><?php echo number_format($stats['milk_today'], 2); ?> L</div>
                </div>
                <div class="stat-card">
                    <h3>Today's Revenue</h3>
                    <div class="value">KSH <?php echo number_format($stats['revenue_today'], 2); ?></div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <h2>Recent Activity</h2>
                <ul class="activity-list">
                    <?php
                    // Fetch recent activities from audit_logs table
                    $db = new Database();
                    $conn = $db->connect();
                    
                    if ($conn) {
                        $result = $conn->query("
                            SELECT al.*, u.full_name 
                            FROM audit_logs al
                            LEFT JOIN users u ON al.user_id = u.user_id
                            ORDER BY al.created_at DESC
                            LIMIT 5
                        ");
                        
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<li>";
                                echo htmlspecialchars($row['full_name']) . " " . 
                                     htmlspecialchars($row['action']) . " in " . 
                                     htmlspecialchars($row['table_name']) . " at " . 
                                     date('M j, Y g:i A', strtotime($row['created_at']));
                                echo "</li>";
                            }
                        } else {
                            echo "<li>No recent activity</li>";
                        }
                        $db->close();
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
