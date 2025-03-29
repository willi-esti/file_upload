<?php

require_once __DIR__ . '/../utils/auth_check.php';

if (isLoggedIn()) {
    header('Location: flux.php');
    exit;
}

require_once __DIR__ . '/../utils/config.php';

$error = null;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $env_username = $_ENV['ADMIN_USERNAME'];
    $env_password = $_ENV['ADMIN_PASSWORD'];

    if ($username === $env_username && $password === $env_password) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        
        header('Location: flux.php');
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - File Manager</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        form {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            max-width: 500px;
            margin: 0 auto;
        }
        
        input[type="text"],
        input[type="password"] {
            border: 1px solid #ddd;
            padding: 10px;
            width: 100%;
            margin-bottom: 15px;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
        }
        
        button:hover {
            background-color: #2980b9;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin: 10px auto;
            border-radius: 4px;
            text-align: center;
            max-width: 500px;
        }
        
        .container {
            margin-top: 50px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="
            images/logo.svg
            " alt="Logo" style="max-width: 100px; margin-bottom: 10px;">
            <h1>Flux</h1>
        </div>

        
            
        <?php if ($error !== null): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        
        <form action="" method="POST">
            <div>
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
