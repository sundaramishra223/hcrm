<?php
// Hospital Site Configuration
// This file contains all site-wide settings

// Get database connection for dynamic settings
if (!isset($db)) {
    require_once __DIR__ . '/../config/database.php';
    $db = new Database();
}

// Default site configuration
$site_config = [
    'site_name' => 'MediCare Hospital',
    'site_title' => 'MediCare Hospital - Advanced Healthcare Management',
    'logo_url' => 'assets/images/logo.svg',
    'favicon_url' => 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232563eb"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zM12 17h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
    'primary_color' => '#2563eb',
    'secondary_color' => '#10b981',
    'accent_color' => '#f59e0b',
    'hospital_phone' => '+91-9876543210',
    'hospital_email' => 'info@medicare.com',
    'hospital_address' => '123 Medical Center, Healthcare City',
];

// Try to get settings from database
try {
    $db_settings = $db->query("SELECT * FROM hospitals WHERE id = 1")->fetch();
    if ($db_settings) {
        if (!empty($db_settings['name'])) $site_config['site_name'] = $db_settings['name'];
        if (!empty($db_settings['logo_url'])) $site_config['logo_url'] = $db_settings['logo_url'];
        if (!empty($db_settings['favicon_url'])) $site_config['favicon_url'] = $db_settings['favicon_url'];
        if (!empty($db_settings['primary_color'])) $site_config['primary_color'] = $db_settings['primary_color'];
        if (!empty($db_settings['secondary_color'])) $site_config['secondary_color'] = $db_settings['secondary_color'];
        if (!empty($db_settings['phone'])) $site_config['hospital_phone'] = $db_settings['phone'];
        if (!empty($db_settings['email'])) $site_config['hospital_email'] = $db_settings['email'];
        if (!empty($db_settings['address'])) $site_config['hospital_address'] = $db_settings['address'];
    }
} catch (Exception $e) {
    // Use default values if database query fails
}

// Function to get page-specific title
function getPageTitle($page_name = '') {
    global $site_config;
    if (empty($page_name)) {
        return $site_config['site_title'];
    }
    return $page_name . ' - ' . $site_config['site_name'];
}

// Function to render favicon and logo meta tags
function renderSiteHead($page_title = '') {
    global $site_config;
    
    $title = empty($page_title) ? $site_config['site_title'] : getPageTitle($page_title);
    
    echo '<title>' . htmlspecialchars($title) . '</title>' . "\n";
    echo '<link rel="icon" type="image/x-icon" href="' . htmlspecialchars($site_config['favicon_url']) . '">' . "\n";
    echo '<link rel="shortcut icon" href="' . htmlspecialchars($site_config['favicon_url']) . '">' . "\n";
    echo '<link rel="apple-touch-icon" href="' . htmlspecialchars($site_config['logo_url']) . '">' . "\n";
    
    // Add color meta tags
    echo '<meta name="theme-color" content="' . htmlspecialchars($site_config['primary_color']) . '">' . "\n";
    echo '<meta name="msapplication-TileColor" content="' . htmlspecialchars($site_config['primary_color']) . '">' . "\n";
}

// Function to render dynamic CSS for colors
function renderDynamicStyles() {
    global $site_config;
    ?>
    <style>
        :root {
            --site-primary-color: <?php echo $site_config['primary_color']; ?>;
            --site-secondary-color: <?php echo $site_config['secondary_color']; ?>;
            --site-accent-color: <?php echo $site_config['accent_color']; ?>;
            
            /* Override default colors with site colors */
            --primary-color: var(--site-primary-color);
            --primary-hover: color-mix(in srgb, var(--site-primary-color) 90%, black);
            --primary-light: color-mix(in srgb, var(--site-primary-color) 80%, white);
            --secondary-color: var(--site-secondary-color);
            --secondary-hover: color-mix(in srgb, var(--site-secondary-color) 90%, black);
            --accent-color: var(--site-accent-color);
            --accent-hover: color-mix(in srgb, var(--site-accent-color) 90%, black);
        }
        
        /* Ensure theme changes apply immediately */
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease !important;
        }
        
        /* Logo styling */
        .logo img {
            max-height: 40px;
            width: auto;
        }
        
        .sidebar-header .logo {
            max-height: 35px;
        }
    </style>
    <?php
}

// Function to render logo
function renderLogo($class = '', $show_text = true) {
    global $site_config;
    
    $logo_html = '';
    if (file_exists($site_config['logo_url'])) {
        $logo_html = '<img src="' . htmlspecialchars($site_config['logo_url']) . '" alt="' . htmlspecialchars($site_config['site_name']) . '" class="logo ' . htmlspecialchars($class) . '">';
    } else {
        // Fallback to icon + text if logo file doesn't exist
        $logo_html = '<i class="fas fa-hospital ' . htmlspecialchars($class) . '"></i>';
    }
    
    if ($show_text) {
        $logo_html .= ' <span class="site-name">' . htmlspecialchars($site_config['site_name']) . '</span>';
    }
    
    return $logo_html;
}
?>