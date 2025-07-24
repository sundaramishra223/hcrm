<?php
class ImageUploadHandler {
    
    private $base_upload_dir = 'uploads/';
    private $max_file_size = 5 * 1024 * 1024; // 5MB
    private $allowed_types = [
        'image' => ['jpg', 'jpeg', 'png', 'gif'],
        'document' => ['pdf', 'doc', 'docx'],
        'all' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']
    ];
    
    public function __construct() {
        $this->createDirectories();
        $this->createSecurityFiles();
    }
    
    /**
     * Create upload directories if they don't exist
     */
    private function createDirectories() {
        $directories = [
            'uploads',
            'uploads/patients',
            'uploads/doctors',
            'uploads/staff',
            'uploads/reports',
            'uploads/prescriptions',
            'uploads/lab-reports',
            'uploads/equipment',
            'uploads/hospital',
            'uploads/temp'
        ];
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Create security files for uploads directory
     */
    private function createSecurityFiles() {
        // Create .htaccess file
        $htaccess_content = '# Prevent PHP execution in uploads
<Files "*.php">
    Order Deny,Allow
    Deny from all
</Files>

# Allow only specific file types
<FilesMatch "\.(jpg|jpeg|png|gif|pdf|doc|docx)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Prevent directory browsing
Options -Indexes';
        
        file_put_contents($this->base_upload_dir . '.htaccess', $htaccess_content);
        
        // Create index.php in each directory
        $index_content = '<?php
// Prevent directory browsing
header("HTTP/1.0 403 Forbidden");
exit("Access denied");
?>';
        
        $directories = ['patients', 'doctors', 'staff', 'reports', 'prescriptions', 'lab-reports', 'equipment', 'hospital', 'temp'];
        foreach ($directories as $dir) {
            file_put_contents($this->base_upload_dir . $dir . '/index.php', $index_content);
        }
    }
    
    /**
     * Upload file to specified directory
     */
    public function uploadFile($file, $directory, $file_type = 'image', $custom_name = null) {
        try {
            // Validate file
            $validation = $this->validateFile($file, $file_type);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            
            // Get file extension
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Generate filename
            if ($custom_name) {
                $filename = $this->sanitizeFilename($custom_name) . '.' . $file_extension;
            } else {
                $filename = uniqid() . '_' . time() . '.' . $file_extension;
            }
            
            // Create full path
            $upload_dir = $this->base_upload_dir . $directory . '/';
            $filepath = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // If it's an image, create thumbnail
                if ($file_type === 'image') {
                    $this->createThumbnail($filepath, $upload_dir . 'thumb_' . $filename);
                }
                
                return [
                    'success' => true,
                    'filename' => $filename,
                    'path' => $filepath,
                    'url' => $filepath,
                    'thumbnail' => $file_type === 'image' ? $upload_dir . 'thumb_' . $filename : null
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Upload error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file, $file_type) {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['valid' => false, 'message' => 'No file uploaded'];
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'Upload error: ' . $this->getUploadErrorMessage($file['error'])];
        }
        
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return ['valid' => false, 'message' => 'File too large. Maximum size: ' . ($this->max_file_size / 1024 / 1024) . 'MB'];
        }
        
        // Check file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $this->allowed_types[$file_type])) {
            return ['valid' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $this->allowed_types[$file_type])];
        }
        
        // Check MIME type for images
        if ($file_type === 'image') {
            $allowed_mime = ['image/jpeg', 'image/png', 'image/gif'];
            $file_mime = mime_content_type($file['tmp_name']);
            if (!in_array($file_mime, $allowed_mime)) {
                return ['valid' => false, 'message' => 'Invalid image file'];
            }
        }
        
        return ['valid' => true, 'message' => 'File is valid'];
    }
    
    /**
     * Create thumbnail for images
     */
    private function createThumbnail($source_path, $thumbnail_path, $max_width = 150, $max_height = 150) {
        try {
            // Get image info
            $image_info = getimagesize($source_path);
            if (!$image_info) return false;
            
            $source_width = $image_info[0];
            $source_height = $image_info[1];
            $mime_type = $image_info['mime'];
            
            // Calculate thumbnail dimensions
            $ratio = min($max_width / $source_width, $max_height / $source_height);
            $thumb_width = intval($source_width * $ratio);
            $thumb_height = intval($source_height * $ratio);
            
            // Create source image resource
            switch ($mime_type) {
                case 'image/jpeg':
                    $source_image = imagecreatefromjpeg($source_path);
                    break;
                case 'image/png':
                    $source_image = imagecreatefrompng($source_path);
                    break;
                case 'image/gif':
                    $source_image = imagecreatefromgif($source_path);
                    break;
                default:
                    return false;
            }
            
            // Create thumbnail image
            $thumbnail = imagecreatetruecolor($thumb_width, $thumb_height);
            
            // Preserve transparency for PNG and GIF
            if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
                imagefilledrectangle($thumbnail, 0, 0, $thumb_width, $thumb_height, $transparent);
            }
            
            // Copy and resize
            imagecopyresampled($thumbnail, $source_image, 0, 0, 0, 0, $thumb_width, $thumb_height, $source_width, $source_height);
            
            // Save thumbnail
            switch ($mime_type) {
                case 'image/jpeg':
                    imagejpeg($thumbnail, $thumbnail_path, 85);
                    break;
                case 'image/png':
                    imagepng($thumbnail, $thumbnail_path, 8);
                    break;
                case 'image/gif':
                    imagegif($thumbnail, $thumbnail_path);
                    break;
            }
            
            // Clean up memory
            imagedestroy($source_image);
            imagedestroy($thumbnail);
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Delete uploaded file and its thumbnail
     */
    public function deleteFile($filepath) {
        try {
            if (file_exists($filepath)) {
                unlink($filepath);
                
                // Delete thumbnail if exists
                $dir = dirname($filepath);
                $filename = basename($filepath);
                $thumbnail_path = $dir . '/thumb_' . $filename;
                if (file_exists($thumbnail_path)) {
                    unlink($thumbnail_path);
                }
                
                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            default:
                return 'Unknown upload error';
        }
    }
    
    /**
     * Sanitize filename
     */
    private function sanitizeFilename($filename) {
        // Remove special characters
        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename);
        // Remove multiple underscores
        $filename = preg_replace('/_+/', '_', $filename);
        // Trim underscores from start and end
        return trim($filename, '_');
    }
    
    /**
     * Get file URL for display
     */
    public static function getFileUrl($filename, $directory) {
        if (empty($filename)) {
            return 'assets/images/default-avatar.png'; // Default image
        }
        return "uploads/$directory/$filename";
    }
    
    /**
     * Get thumbnail URL
     */
    public static function getThumbnailUrl($filename, $directory) {
        if (empty($filename)) {
            return 'assets/images/default-avatar.png';
        }
        $thumbnail_path = "uploads/$directory/thumb_$filename";
        if (file_exists($thumbnail_path)) {
            return $thumbnail_path;
        }
        return "uploads/$directory/$filename";
    }
}

// Helper functions for easy use
function uploadPatientPhoto($file, $custom_name = null) {
    $uploader = new ImageUploadHandler();
    return $uploader->uploadFile($file, 'patients', 'image', $custom_name);
}

function uploadDoctorPhoto($file, $custom_name = null) {
    $uploader = new ImageUploadHandler();
    return $uploader->uploadFile($file, 'doctors', 'image', $custom_name);
}

function uploadStaffPhoto($file, $custom_name = null) {
    $uploader = new ImageUploadHandler();
    return $uploader->uploadFile($file, 'staff', 'image', $custom_name);
}

function uploadDocument($file, $directory, $custom_name = null) {
    $uploader = new ImageUploadHandler();
    return $uploader->uploadFile($file, $directory, 'document', $custom_name);
}

function deleteUploadedFile($filepath) {
    $uploader = new ImageUploadHandler();
    return $uploader->deleteFile($filepath);
}
?>