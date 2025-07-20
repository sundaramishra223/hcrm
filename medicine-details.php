<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$message = '';
$medicine = null;

// Get medicine ID from URL
$medicine_id = $_GET['id'] ?? 0;

if (!$medicine_id) {
    header('Location: pharmacy.php');
    exit;
}

// Get medicine details
try {
    $medicine = $db->query(
        "SELECT * FROM medicines WHERE id = ? AND hospital_id = 1",
        [$medicine_id]
    )->fetch();
    
    if (!$medicine) {
        $message = "Medicine not found!";
    }
} catch (Exception $e) {
    $message = "Error fetching medicine details: " . $e->getMessage();
}

// Get medicine usage history (prescriptions)
$usage_history = [];
if ($medicine) {
    try {
        $usage_history = $db->query(
            "SELECT pi.*, p.patient_id, CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    pr.id as prescription_id, pr.prescribed_date,
                    CONCAT(d.first_name, ' ', d.last_name) as doctor_name
             FROM prescription_items pi
             JOIN prescriptions pr ON pi.prescription_id = pr.id
             JOIN patients p ON pr.patient_id = p.id
             LEFT JOIN doctors d ON pr.doctor_id = d.id
             WHERE pi.medicine_name LIKE ? OR pi.medicine_id = ?
             ORDER BY pr.prescribed_date DESC
             LIMIT 20",
            ['%' . $medicine['name'] . '%', $medicine_id]
        )->fetchAll();
    } catch (Exception $e) {
        // Ignore if table doesn't exist or error occurs
    }
}

// Get stock movement history
$stock_movements = [];
if ($medicine) {
    try {
        $stock_movements = [
            ['date' => $medicine['created_at'], 'type' => 'Initial Stock', 'quantity' => $medicine['stock_quantity'], 'notes' => 'Medicine added to inventory'],
            ['date' => date('Y-m-d H:i:s'), 'type' => 'Current Stock', 'quantity' => $medicine['stock_quantity'], 'notes' => 'Current available stock']
        ];
    } catch (Exception $e) {
        // Handle error
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Details - <?php echo htmlspecialchars($medicine['name'] ?? 'Unknown'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .medicine-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .medicine-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 5px solid var(--primary-color);
        }
        
        .info-card h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .label {
            font-weight: 600;
            color: #666;
        }
        
        .value {
            color: #333;
            font-weight: 500;
        }
        
        .stock-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .stock-high { background: #d4edda; color: #155724; }
        .stock-medium { background: #fff3cd; color: #856404; }
        .stock-low { background: #f8d7da; color: #721c24; }
        .stock-out { background: #f5c6cb; color: #721c24; }
        
        .history-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        
        .history-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .history-table th {
            background: var(--primary-color);
            color: white;
            padding: 15px;
            text-align: left;
        }
        
        .history-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .history-table tr:hover {
            background: #f8f9fa;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .medicine-info {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .history-table {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1200px; margin: 20px auto; padding: 20px;">
        
        <?php if ($message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($medicine): ?>
            <div class="medicine-header">
                <h1><i class="fas fa-pills"></i> <?php echo htmlspecialchars($medicine['name']); ?></h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">
                    <strong>Generic:</strong> <?php echo htmlspecialchars($medicine['generic_name'] ?? 'N/A'); ?> | 
                    <strong>Category:</strong> <?php echo htmlspecialchars($medicine['category'] ?? 'General'); ?> |
                    <strong>Manufacturer:</strong> <?php echo htmlspecialchars($medicine['manufacturer'] ?? 'N/A'); ?>
                </p>
            </div>
            
            <div class="medicine-info">
                <!-- Basic Information -->
                <div class="info-card">
                    <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                    <div class="info-row">
                        <span class="label">Medicine Name:</span>
                        <span class="value"><?php echo htmlspecialchars($medicine['name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Generic Name:</span>
                        <span class="value"><?php echo htmlspecialchars($medicine['generic_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Category:</span>
                        <span class="value"><?php echo htmlspecialchars($medicine['category'] ?? 'General'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Type:</span>
                        <span class="value"><?php echo htmlspecialchars($medicine['type'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Strength:</span>
                        <span class="value"><?php echo htmlspecialchars($medicine['strength'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                
                <!-- Stock Information -->
                <div class="info-card">
                    <h3><i class="fas fa-boxes"></i> Stock Information</h3>
                    <div class="info-row">
                        <span class="label">Current Stock:</span>
                        <span class="value">
                            <?php echo number_format($medicine['stock_quantity']); ?> <?php echo htmlspecialchars($medicine['unit'] ?? 'units'); ?>
                            <?php 
                            $stock_status = 'stock-high';
                            $status_text = 'Good Stock';
                            if ($medicine['stock_quantity'] <= 0) {
                                $stock_status = 'stock-out';
                                $status_text = 'Out of Stock';
                            } elseif ($medicine['stock_quantity'] <= ($medicine['min_stock_level'] ?? 10)) {
                                $stock_status = 'stock-low';
                                $status_text = 'Low Stock';
                            } elseif ($medicine['stock_quantity'] <= ($medicine['min_stock_level'] ?? 10) * 2) {
                                $stock_status = 'stock-medium';
                                $status_text = 'Medium Stock';
                            }
                            ?>
                            <span class="stock-status <?php echo $stock_status; ?>"><?php echo $status_text; ?></span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="label">Minimum Level:</span>
                        <span class="value"><?php echo number_format($medicine['min_stock_level'] ?? 0); ?> units</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Unit Price:</span>
                        <span class="value">₹<?php echo number_format($medicine['unit_price'], 2); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Total Value:</span>
                        <span class="value">₹<?php echo number_format($medicine['stock_quantity'] * $medicine['unit_price'], 2); ?></span>
                    </div>
                </div>
                
                <!-- Manufacturing Details -->
                <div class="info-card">
                    <h3><i class="fas fa-industry"></i> Manufacturing Details</h3>
                    <div class="info-row">
                        <span class="label">Manufacturer:</span>
                        <span class="value"><?php echo htmlspecialchars($medicine['manufacturer'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Batch Number:</span>
                        <span class="value"><?php echo htmlspecialchars($medicine['batch_number'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Manufacturing Date:</span>
                        <span class="value"><?php echo $medicine['manufacture_date'] ? date('d M Y', strtotime($medicine['manufacture_date'])) : 'N/A'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Expiry Date:</span>
                        <span class="value">
                            <?php 
                            if ($medicine['expiry_date']) {
                                $expiry = date('d M Y', strtotime($medicine['expiry_date']));
                                $days_to_expiry = (strtotime($medicine['expiry_date']) - time()) / (60 * 60 * 24);
                                echo $expiry;
                                if ($days_to_expiry < 30) {
                                    echo ' <span class="stock-status stock-low">Expiring Soon</span>';
                                } elseif ($days_to_expiry < 0) {
                                    echo ' <span class="stock-status stock-out">Expired</span>';
                                }
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </span>
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="info-card">
                    <h3><i class="fas fa-clipboard-list"></i> Additional Information</h3>
                    <div class="info-row">
                        <span class="label">Storage:</span>
                        <span class="value"><?php echo htmlspecialchars($medicine['storage_instructions'] ?? 'Store in cool, dry place'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Side Effects:</span>
                        <span class="value"><?php echo htmlspecialchars($medicine['side_effects'] ?? 'Consult doctor'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Status:</span>
                        <span class="value">
                            <?php if ($medicine['is_active']): ?>
                                <span class="stock-status stock-high">Active</span>
                            <?php else: ?>
                                <span class="stock-status stock-out">Inactive</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="label">Added On:</span>
                        <span class="value"><?php echo date('d M Y', strtotime($medicine['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Usage History -->
            <?php if (!empty($usage_history)): ?>
            <div class="history-table">
                <h3 style="padding: 20px; margin: 0; background: var(--primary-color); color: white;">
                    <i class="fas fa-history"></i> Recent Prescriptions
                </h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Quantity</th>
                            <th>Dosage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usage_history as $usage): ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($usage['prescribed_date'])); ?></td>
                            <td><?php echo htmlspecialchars($usage['patient_name']); ?></td>
                            <td>Dr. <?php echo htmlspecialchars($usage['doctor_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($usage['quantity'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($usage['dosage'] ?? 'As directed'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="pharmacy.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Pharmacy
                </a>
                <a href="pharmacy.php?edit_id=<?php echo $medicine['id']; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit Medicine
                </a>
                <a href="prescriptions.php?medicine_id=<?php echo $medicine['id']; ?>" class="btn btn-success">
                    <i class="fas fa-prescription"></i> Prescribe Medicine
                </a>
                <button onclick="printMedicine()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Details
                </button>
            </div>
            
        <?php else: ?>
            <div class="alert alert-danger">
                <h3>Medicine Not Found</h3>
                <p>The requested medicine could not be found in the system.</p>
                <a href="pharmacy.php" class="btn btn-secondary">Back to Pharmacy</a>
            </div>
        <?php endif; ?>
        
    </div>
    
    <script>
        function printMedicine() {
            window.print();
        }
        
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight critical information
            const stockValue = <?php echo $medicine['stock_quantity'] ?? 0; ?>;
            const minLevel = <?php echo $medicine['min_stock_level'] ?? 10; ?>;
            
            if (stockValue <= 0) {
                document.querySelector('.medicine-header').style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
            } else if (stockValue <= minLevel) {
                document.querySelector('.medicine-header').style.background = 'linear-gradient(135deg, #ffc107 0%, #e0a800 100%)';
            }
        });
    </script>
</body>
</html>