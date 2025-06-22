<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MaziwaPowa Dairy Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            overflow: hidden;
        }

        .loader-container {
            text-align: center;
        }

        .logo {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            opacity: 0;
            animation: fadeIn 1s ease-in forwards;
        }

        .loader {
            width: 150px;
            height: 150px;
            border: 4px solid #ffffff;
            border-top: 4px solid transparent;
            border-radius: 50%;
            margin: 0 auto 2rem;
            animation: spin 1s linear infinite;
        }

        .progress-bar {
            width: 300px;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            margin: 0 auto;
            overflow: hidden;
        }

        .progress {
            width: 0%;
            height: 100%;
            background: white;
            border-radius: 4px;
            animation: progress 7s linear forwards;
        }

        .loading-text {
            margin-top: 1rem;
            font-size: 1rem;
            opacity: 0.8;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes progress {
            0% { width: 0%; }
            100% { width: 100%; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="loader-container">
        <div class="logo">MaziwaPowa</div>
        <div class="loader"></div>
        <div class="progress-bar">
            <div class="progress"></div>
        </div>
        <div class="loading-text">Loading System...</div>
    </div>

    <script>
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 7000);
    </script>
</body>
</html>
