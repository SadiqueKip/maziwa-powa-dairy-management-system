<?php
// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// Get the current page name for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'MaziwaPowa Dairy Management System'; ?></title>
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

        .sidebar {
            width: 250px;
            background: #1a1a1a;
            color: white;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 2rem;
            background: #f5f5f5;
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
            margin-bottom: 2rem;
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

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
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
            width: 100%;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .content-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: #1a1a1a;
            color: white;
        }

        .btn-primary:hover {
            background: #333;
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .table-responsive {
            overflow-x: auto;
            margin-bottom: 1.5rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f8f8;
            font-weight: 600;
        }

        tr:hover {
            background: #f8f8f8;
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

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }

            .main-content {
                margin-left: 0;
            }

            .container {
                flex-direction: column;
            }

            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-logo">
                <h1>MaziwaPowa</h1>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="/dashboard.php" <?php echo $current_page == 'dashboard.php' ? 'class="active"' : ''; ?>>Dashboard</a></li>
                <li><a href="/modules/cattle/list.php" <?php echo strpos($current_page, 'cattle') !== false ? 'class="active"' : ''; ?>>Cattle Management</a></li>
                <li><a href="/modules/workers/list.php" <?php echo strpos($current_page, 'workers') !== false ? 'class="active"' : ''; ?>>Worker Management</a></li>
                <li><a href="/modules/feed/list.php" <?php echo strpos($current_page, 'feed') !== false ? 'class="active"' : ''; ?>>Feed Management</a></li>
                <li><a href="/modules/health/list.php" <?php echo strpos($current_page, 'health') !== false ? 'class="active"' : ''; ?>>Health Records</a></li>
                <li><a href="/modules/breeding/list.php" <?php echo strpos($current_page, 'breeding') !== false ? 'class="active"' : ''; ?>>Breeding Records</a></li>
                <li><a href="/modules/milk-sales/list.php" <?php echo strpos($current_page, 'milk-sales') !== false ? 'class="active"' : ''; ?>>Milk Sales</a></li>
                <li><a href="/modules/expenses/list.php" <?php echo strpos($current_page, 'expenses') !== false ? 'class="active"' : ''; ?>>Expenses</a></li>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <li><a href="/modules/reports/index.php" <?php echo strpos($current_page, 'reports') !== false ? 'class="active"' : ''; ?>>Reports</a></li>
                <li><a href="/modules/settings/index.php" <?php echo strpos($current_page, 'settings') !== false ? 'class="active"' : ''; ?>>Settings</a></li>
                <?php endif; ?>
            </ul>

            <form action="/logout.php" method="POST">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>

        <div class="main-content">
            <div class="header">
                <h1><?php echo $page_title ?? 'MaziwaPowa Dairy'; ?></h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <span>(<?php echo htmlspecialchars($_SESSION['role']); ?>)</span>
                </div>
            </div>
