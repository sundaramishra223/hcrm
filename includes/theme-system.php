    <!-- Global Theme System CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Site Configuration -->
    <?php 
    if (!isset($site_config)) {
        include_once __DIR__ . '/site-config.php'; 
    }
    renderDynamicStyles();
    ?>
    
    <!-- Theme Toggle UI -->
    <div class="theme-toggle" id="themeToggle">
        <div class="theme-option" data-theme="light" onclick="setTheme('light')" title="Light Theme">☀️</div>
        <div class="theme-option" data-theme="dark" onclick="setTheme('dark')" title="Dark Theme">🌙</div>
        <div class="theme-option" data-theme="medical" onclick="setTheme('medical')" title="Medical Theme">🏥</div>
    </div>
    
    <script>
        // Universal Theme Management System - Instant switching
        const savedTheme = localStorage.getItem('theme') || 'light';
        
        // Apply theme immediately (before DOM loads)
        document.documentElement.setAttribute('data-theme', savedTheme);
        document.documentElement.style.setProperty('--transition-duration', '0s');
        
        function setTheme(theme) {
            // Temporarily disable transitions for instant change
            document.documentElement.style.setProperty('--transition-duration', '0s');
            
            // Apply theme
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            updateThemeToggle(theme);
            
            // Force style recalculation
            document.body.offsetHeight;
            
            // Re-enable transitions after a short delay
            setTimeout(() => {
                document.documentElement.style.removeProperty('--transition-duration');
            }, 50);
        }
        
        function updateThemeToggle(activeTheme) {
            document.querySelectorAll('.theme-option').forEach(option => {
                option.classList.remove('active');
                if (option.dataset.theme === activeTheme) {
                    option.classList.add('active');
                }
            });
        }
        
        // Initialize theme on page load
        document.addEventListener('DOMContentLoaded', function() {
            setTheme(savedTheme);
            // Re-enable transitions
            setTimeout(() => {
                document.documentElement.style.removeProperty('--transition-duration');
            }, 100);
        });
        
        // Sidebar Functions (if sidebar exists)
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('open');
            }
        }
        
        // Close sidebar when clicking outside (mobile)
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle');
            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
            
            if (sidebar && !sidebar.contains(event.target) && 
                event.target !== toggleBtn && event.target !== mobileMenuBtn) {
                sidebar.classList.remove('open');
            }
        });
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            // Close any modal when clicking outside
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // ESC to close modals
            if (event.key === 'Escape') {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (modal.style.display === 'block') {
                        modal.style.display = 'none';
                    }
                });
            }
            
            // Ctrl/Cmd + / to toggle sidebar
            if ((event.ctrlKey || event.metaKey) && event.key === '/') {
                event.preventDefault();
                toggleSidebar();
            }
        });
    </script>
    
    <style>
        /* Theme Toggle Styling */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 5px;
            background: var(--bg-card, rgba(255, 255, 255, 0.9));
            padding: 8px;
            border-radius: 25px;
            box-shadow: var(--shadow-md, 0 4px 6px rgba(0,0,0,0.1));
            z-index: 1000;
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color, #e2e8f0);
        }
        
        .theme-option {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .theme-option:hover {
            transform: scale(1.1);
            background: var(--bg-secondary, #f8fafc);
        }
        
        .theme-option.active {
            border-color: var(--primary-color, #2563eb);
            background: var(--primary-color, #2563eb);
            color: white;
            transform: scale(1.1);
        }
        
        .theme-option[data-theme="light"] {
            background: #fff3cd;
        }
        
        .theme-option[data-theme="dark"] {
            background: #2a2a2a;
            color: white;
        }
        
        .theme-option[data-theme="medical"] {
            background: #d4f3e8;
        }
        
        /* Responsive theme toggle */
        @media (max-width: 768px) {
            .theme-toggle {
                top: 10px;
                right: 10px;
                padding: 6px;
            }
            
            .theme-option {
                width: 30px;
                height: 30px;
                font-size: 14px;
            }
        }
        
        /* Animation for theme changes - respects --transition-duration */
        * {
            transition: 
                background-color var(--transition-duration, 0.3s) ease, 
                color var(--transition-duration, 0.3s) ease, 
                border-color var(--transition-duration, 0.3s) ease,
                box-shadow var(--transition-duration, 0.3s) ease;
        }
        
        /* Instant theme switching support */
        html[style*="--transition-duration: 0s"] * {
            transition: none !important;
        }
    </style>