<?php
/**
 * Image Upload Handler Class
 * Handles file uploads, validation, and management
 */
class ImageUploadHandler {
    private static $uploadDir = 'uploads/';
    private static $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
    private static $maxFileSize = 5242880; // 5MB
    
    /**
     * Upload file to specific directory
     */
    public static function uploadFile($file, $directory, $customName = null) {
        try {
            // Create directory if it doesn't exist
            self::createDirectory($directory);
            
            // Validate file
            $validation = self::validateFile($file);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Generate filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = $customName ? $customName . '.' . $extension : self::generateUniqueFilename($file['name']);
            $filepath = self::$uploadDir . $directory . '/' . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Create thumbnail for images
                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    self::createThumbnail($filepath, $directory);
                }
                
                return [
                    'success' => true,
                    'message' => 'File uploaded successfully',
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'url' => self::getFileUrl($filename, $directory)
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to move uploaded file'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Upload error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private static function validateFile($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => 'File upload error: ' . self::getUploadErrorMessage($file['error'])
            ];
        }
        
        // Check file size
        if ($file['size'] > self::$maxFileSize) {
            return [
                'success' => false,
                'message' => 'File size exceeds maximum limit of ' . (self::$maxFileSize / 1048576) . 'MB'
            ];
        }
        
        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::$allowedTypes)) {
            return [
                'success' => false,
                'message' => 'File type not allowed. Allowed types: ' . implode(', ', self::$allowedTypes)
            ];
        }
        
        // Check if file is actually an image (for image files)
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                return [
                    'success' => false,
                    'message' => 'Invalid image file'
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Create directory with security files
     */
    private static function createDirectory($directory) {
        $fullPath = self::$uploadDir . $directory;
        
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
            
            // Create .htaccess file for security
            $htaccessContent = "Options -Indexes\n";
            $htaccessContent .= "Options -ExecCGI\n";
            $htaccessContent .= "AddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n";
            file_put_contents($fullPath . '/.htaccess', $htaccessContent);
            
            // Create index.php file to prevent directory listing
            $indexContent = "<?php\n// Directory access denied\nheader('HTTP/1.0 403 Forbidden');\nexit('Access denied');\n?>";
            file_put_contents($fullPath . '/index.php', $indexContent);
        }
    }
    
    /**
     * Generate unique filename
     */
    private static function generateUniqueFilename($originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);
        return $basename . '_' . uniqid() . '.' . $extension;
    }
    
    /**
     * Create thumbnail for images
     */
    private static function createThumbnail($filepath, $directory) {
        try {
            $thumbnailDir = self::$uploadDir . $directory . '/thumbnails/';
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }
            
            $filename = basename($filepath);
            $thumbnailPath = $thumbnailDir . $filename;
            
            // Get image info
            $imageInfo = getimagesize($filepath);
            if ($imageInfo === false) return false;
            
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $type = $imageInfo[2];
            
            // Calculate thumbnail dimensions
            $thumbWidth = 150;
            $thumbHeight = 150;
            
            if ($width > $height) {
                $thumbHeight = ($height / $width) * $thumbWidth;
            } else {
                $thumbWidth = ($width / $height) * $thumbHeight;
            }
            
            // Create image resource
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $source = imagecreatefromjpeg($filepath);
                    break;
                case IMAGETYPE_PNG:
                    $source = imagecreatefrompng($filepath);
                    break;
                case IMAGETYPE_GIF:
                    $source = imagecreatefromgif($filepath);
                    break;
                default:
                    return false;
            }
            
            // Create thumbnail
            $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
            
            // Preserve transparency for PNG and GIF
            if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
                imagefilledrectangle($thumbnail, 0, 0, $thumbWidth, $thumbHeight, $transparent);
            }
            
            imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
            
            // Save thumbnail
            switch ($type) {
                case IMAGETYPE_JPEG:
                    imagejpeg($thumbnail, $thumbnailPath, 85);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($thumbnail, $thumbnailPath);
                    break;
                case IMAGETYPE_GIF:
                    imagegif($thumbnail, $thumbnailPath);
                    break;
            }
            
            imagedestroy($source);
            imagedestroy($thumbnail);
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get file URL
     */
    public static function getFileUrl($filename, $directory) {
        return self::$uploadDir . $directory . '/' . $filename;
    }
    
    /**
     * Get thumbnail URL
     */
    public static function getThumbnailUrl($filename, $directory) {
        $thumbnailPath = self::$uploadDir . $directory . '/thumbnails/' . $filename;
        if (file_exists($thumbnailPath)) {
            return $thumbnailPath;
        }
        return self::getFileUrl($filename, $directory);
    }
    
    /**
     * Delete file
     */
    public static function deleteFile($filename, $directory) {
        $filepath = self::$uploadDir . $directory . '/' . $filename;
        $thumbnailPath = self::$uploadDir . $directory . '/thumbnails/' . $filename;
        
        $success = true;
        if (file_exists($filepath)) {
            $success = unlink($filepath);
        }
        
        if (file_exists($thumbnailPath)) {
            unlink($thumbnailPath);
        }
        
        return $success;
    }
    
    /**
     * Get upload error message
     */
    private static function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize directive';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE directive';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
    
    /**
     * Get file info
     */
    public static function getFileInfo($filename, $directory) {
        $filepath = self::$uploadDir . $directory . '/' . $filename;
        
        if (!file_exists($filepath)) {
            return null;
        }
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => self::getFileUrl($filename, $directory),
            'size' => filesize($filepath),
            'modified' => filemtime($filepath),
            'extension' => strtolower(pathinfo($filename, PATHINFO_EXTENSION))
        ];
    }
}

// Helper functions for easy use
function uploadPatientPhoto($file, $custom_name = null) {
    return ImageUploadHandler::uploadFile($file, 'patients', $custom_name);
}

function uploadDoctorPhoto($file, $custom_name = null) {
    return ImageUploadHandler::uploadFile($file, 'doctors', $custom_name);
}

function uploadStaffPhoto($file, $custom_name = null) {
    return ImageUploadHandler::uploadFile($file, 'staff', $custom_name);
}

function uploadDocument($file, $directory, $custom_name = null) {
    return ImageUploadHandler::uploadFile($file, $directory, $custom_name);
}

function deleteUploadedFile($filename, $directory) {
    return ImageUploadHandler::deleteFile($filename, $directory);
}
?>