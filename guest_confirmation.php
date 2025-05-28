<?php
session_start();

// Check if the user is a guest and came from a form submission
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guest' || !isset($_SESSION['form_submitted'])) {
    header("Location: index.php");
    exit();
}

// Clear the form submission flag to prevent reuse
unset($_SESSION['form_submitted']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Confirmation</title>
    <link rel="stylesheet" href="style.css"> <!-- Adjust to your stylesheet -->
    <style>
        :root {
            --bg-color: #f4f7fa;
            --text-color: #333;
            --card-bg: #ffffff;
            --border-color: #d0d4d8;
            --primary-color: #012365;
            --hover-color: #003087;
            --sidebar-bg: #1b263b;
            --sidebar-text: #ffffff;
        }

        .dark-mode {
            --bg-color: #1e1e1e;
            --text-color: #e0e0e0;
            --card-bg: #1e1e1e;
            --border-color: #444;
            --primary-color: #3a6ab7;
            --hover-color: #4a7ac7;
            --sidebar-bg: #1b263b;
            --sidebar-text: #e0e0e0;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
        }

        .main-content {
            background-color: var(--bgcolor);
            color: var(--text-color);
        }

        /* Main Content Styles */
        .main-content h1 {
            font-size: 28px;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .main-content p {
            font-size: 18px;
            color: var(--text-color);
            margin-bottom: 30px;
            line-height: 1.5;
        }

        /* Button Styles */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: var(--hover-color);
        }

        .sidebar {
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
        }

        /* Toggle Switch Styles */
        .theme-switch-wrapper {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            margin-top: auto;
            border-top: 1px solid #34495e;
            gap: 12px;
        }

        .theme-switch-wrapper em {
            font-size: 0.9rem;
            color: var(--sidebar-text);
            white-space: nowrap;
            font-style: normal;
        }

        .theme-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
            flex-shrink: 0;
        }

        .theme-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            transition: .4s;
        }

        .slider:before {
            position: absolute;
            content: '☀️';
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            color: #333;
        }

        input:checked+.slider {
            background-color: #0056b3;
        }

        input:focus+.slider {
            box-shadow: 0 0 1px #0056b3;
        }

        input:checked+.slider:before {
            transform: translateX(26px);
            content: '🌙';
            background-color: #333;
            color: #eee;
        }

        .slider.round {
            border-radius: 24px;
        }

        .slider.round:before {
            border-radius: 50%;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                position: static;
                height: auto;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>
</head>

<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <h2>Clinic Management</h2>
            <ul class="menu">
                <li><a href="guest_loginsheet.php">Login Sheet</a></li>
                <li><a href="logout.php" class="logout">Logout</a></li>
            </ul>
            <div class="theme-switch-wrapper">
                <label class="theme-switch" for="checkbox">
                    <input type="checkbox" id="checkbox" />
                    <div class="slider round"></div>
                </label>
                <em>Switch Mode</em>
            </div>
        </div>
        <div class="main-content">
            <h1>Confirmation</h1>
            <p>Your login sheet entry has been successfully submitted.</p>
            <a href="guest_loginsheet.php" class="btn">Back to Login Sheet</a>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const themeToggle = document.getElementById('checkbox');
            const currentTheme = localStorage.getItem('theme') || '<?php echo isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light'; ?>';
            if (currentTheme === 'dark') {
                document.documentElement.classList.add('dark-mode');
                themeToggle.checked = true;
            } else {
                document.documentElement.classList.remove('dark-mode');
                themeToggle.checked = false;
            }

            themeToggle.addEventListener('change', function () {
                if (this.checked) {
                    document.documentElement.classList.add('dark-mode');
                    localStorage.setItem('theme', 'dark');
                    fetch('update_theme.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'theme=dark'
                    });
                } else {
                    document.documentElement.classList.remove('dark-mode');
                    localStorage.setItem('theme', 'light');
                    fetch('update_theme.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'theme=light'
                    });
                }
            });

            // Logout confirmation
            document.querySelector('.logout').addEventListener('click', function (event) {
                event.preventDefault();
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = this.href;
                }
            });
        });
    </script>
</body>

</html>