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
                 JOIN roles r ON u.role_id = r.id 
                 WHERE u.email = ? AND u.is_active = 1", 
                [$email]
            );
            
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role_name'];
                $_SESSION['role_display'] = $user['role_display_name'];
                
                // Update last login
                $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
                
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
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #004685 0%, #0066cc 100%);
            padding: 20px;
        }
        
        .login-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #004685;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #333;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #004685;
        }
        
        .login-btn {
            width: 100%;
            background: #004685;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .login-btn:hover {
            background: #003366;
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #fcc;
        }
        
        .demo-users {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 12px;
        }
        
        .demo-users h4 {
            margin-bottom: 10px;
            color: #004685;
        }
        
        .demo-users p {
            margin: 5px 0;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Hospital CRM</h1>
                <p>Please sign in to your account</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="login-btn">Sign In</button>
            </form>
            
            <div class="demo-users">
                <h4>Demo Login Credentials (Click to Fill):</h4>
                <p><strong>Admin:</strong> admin@hospital.com / password</p>
                <p><strong>Doctor:</strong> dr.sharma@hospital.com / password</p>
                <p><strong>Patient:</strong> patient.demo@hospital.com / Patient123!</p>
                <p><strong>Nurse:</strong> priya.nurse@hospital.com / password</p>
                <p><strong>Reception:</strong> reception@hospital.com / password</p>
                <small style="color: #666; margin-top: 10px; display: block;">
                    💡 Click any credential above to auto-fill the form
                </small>
            </div>
        </div>
    </div>

    <!-- Password Validation Script -->
    <script src="includes/password-validation.js"></script>
    <script>
        // Theme support for login page
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        // Demo credential fill
        document.addEventListener('DOMContentLoaded', () => {
            const demoUsers = document.querySelectorAll('.demo-users p');
            demoUsers.forEach(user => {
                user.addEventListener('click', () => {
                    const text = user.textContent;
                    const emailMatch = text.match(/(\S+@\S+\.\S+)/);
                    
                    if (emailMatch) {
                        const email = emailMatch[1];
                        document.getElementById('email').value = email;
                        
                        // Extract password based on the credential type
                        if (email === 'patient.demo@hospital.com') {
                            document.getElementById('password').value = 'Patient123!';
                        } else if (text.includes('/ password')) {
                            document.getElementById('password').value = 'password';
                        }
                        
                        // Add visual feedback
                        user.style.background = '#d4edda';
                        setTimeout(() => {
                            user.style.background = '';
                        }, 1000);
                    }
                });
            });
        });
    </script>
</body>
</html>
