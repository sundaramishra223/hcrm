<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';

if ($_POST) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        try {
            $db = new Database();
            $stmt = $db->query(
                "SELECT u.*, r.role_name, r.role_display_name 
                 FROM users u 
                 LEFT JOIN roles r ON u.role_id = r.id 
                 WHERE u.email = ? AND u.is_active = 1",
                [$email]
            );
            
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role_name'] ?? 'admin';
                $_SESSION['role_display'] = $user['role_display_name'] ?? 'Administrator';
                
                // Update last login
                try {
                    $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
                } catch (Exception $e) {
                    // Log error but don't fail login
                }
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error_message = 'Invalid email or password!';
            }
        } catch (Exception $e) {
            $error_message = 'Login failed. Please try again.';
        }
    } else {
        $error_message = 'Please fill all fields!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital CRM - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }
        
        .login-header {
            background: linear-gradient(135deg, #004685 0%, #0066cc 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .login-form {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 1rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #004685;
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 70, 133, 0.1);
        }
        
        .login-btn {
            width: 100%;
            background: linear-gradient(135deg, #004685 0%, #0066cc 100%);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 70, 133, 0.3);
        }
        
        .error-message {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
        }
        
        .demo-users {
            margin-top: 30px;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            font-size: 13px;
        }
        
        .demo-users h4 {
            margin-bottom: 15px;
            color: #004685;
            font-size: 1.1rem;
            text-align: center;
        }
        
        .demo-users p {
            margin: 8px 0;
            color: #495057;
            padding: 8px 12px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #004685;
        }
        
        .demo-users small {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #6c757d;
            font-style: italic;
        }
        
        @media (max-width: 480px) {
            .login-container {
                margin: 10px;
            }
            
            .login-header {
                padding: 30px 20px;
            }
            
            .login-form {
                padding: 30px 20px;
            }
            
            .demo-users {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-hospital"></i> Hospital CRM</h1>
            <p>Advanced Healthcare Management System</p>
        </div>
        
        <div class="login-form">
            <?php if ($error_message): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email Address
                    </label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="Enter your email address">
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input type="password" id="password" name="password" required
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            
            <div class="demo-users">
                <h4><i class="fas fa-users"></i> Demo Login Credentials</h4>
                <p><strong><i class="fas fa-user-shield"></i> Admin:</strong> admin@hospital.com / admin</p>
                <p><strong><i class="fas fa-user-md"></i> Doctor:</strong> doctor1@hospital.com / admin</p>
                <p><strong><i class="fas fa-user-nurse"></i> Nurse:</strong> nurse1@hospital.com / admin</p>
                <p><strong><i class="fas fa-user"></i> Patient:</strong> patient1@hospital.com / admin</p>
                <p><strong><i class="fas fa-user-tie"></i> Receptionist:</strong> reception@hospital.com / admin</p>
                <small>
                    <i class="fas fa-info-circle"></i> 
                    All demo accounts use password: <strong>admin</strong>
                </small>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-focus on email field
        document.getElementById('email').focus();
        
        // Add loading animation on form submit
        document.querySelector('form').addEventListener('submit', function() {
            const btn = document.querySelector('.login-btn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
            btn.disabled = true;
        });
    </script>
</body>
</html>