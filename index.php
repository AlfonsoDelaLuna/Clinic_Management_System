<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Management System</title>
    <link rel="stylesheet" href="login.css">
    <link rel="icon" href="images/sti_logo.png" type="image/png">
</head>

<body class="login-page">
    <div class="login-container">
        <!-- Logo -->
        <div class="logo">
            <img src="images/STI_symbol.png" alt="STI College Logo">
        </div>

        <!-- Login Form -->
        <div class="login-box">
            <h2>Clinic Management System</h2>
            <form action="login.php" method="POST">
                <!-- Display error message if it exists -->
                <?php
                session_start();
                if (isset($_SESSION['error'])) {
                    echo '<p class="error-message">' . htmlspecialchars($_SESSION['error']) . '</p>';
                    unset($_SESSION['error']); // Clear the error message after displaying
                }
                ?>
                <div class="form-group">
                    <label for="role">User Type</label>
                    <select id="role" name="role" required>
                        <option value="admin">Administrator</option>
                        <option value="guest">Guest</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" required>
                        <div class="icon-container">
                            <img src="images/password_hide_icon.png" alt="Toggle Password Visibility"
                                class="password-toggle" onclick="togglePassword()">
                        </div>
                    </div>
                </div>
                <button type="submit" class="login-btn">Sign In</button>
            </form>
        </div>

        <!-- Intern Credits -->
        <div class="intern-credits">
            <div class="credits-wrapper">
                <p>Developed by MIS Department Interns 2025</p>
                <div class="credits-group">
                    <div class="credit-item">
                        <img src="images/Mamerto.jpg" alt="Mamerto" class="credit-image">
                        <span>Mamerto</span>
                    </div>
                    <div class="credit-item">
                        <img src="images/Dela_Luna.jpg" alt="Dela Luna" class="credit-image">
                        <span>Dela Luna</span>
                    </div>
                    <div class="credit-item">
                        <img src="images/Ramos.jpg" alt="Ramos" class="credit-image">
                        <span>Ramos</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.src = 'images/password_show_icon.png'; // Show icon (eye without slash)
            } else {
                passwordInput.type = 'password';
                toggleIcon.src = 'images/password_hide_icon.png'; // Hide icon (eye with slash)
            }
        }
    </script>
</body>

</html>