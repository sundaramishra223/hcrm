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

// Get bill ID from URL
$bill_id = $_GET['id'] ?? null;

if (!$bill_id) {
    echo "<script>alert('Bill ID not provided!'); window.location.href='billing.php';</script>";
    exit;
}

try {
    // Get bill details with patient info
    $bill_sql = "SELECT b.*, 
                  CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                  p.patient_id as patient_code,
                  p.phone as patient_phone,
                  p.email as patient_email,
                  p.address as patient_address,
                  p.date_of_birth,
                  p.gender,
                  CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                  FROM bills b
                  JOIN patients p ON b.patient_id = p.id
                  LEFT JOIN users u ON b.created_by = u.id
                  WHERE b.id = ?";
    
    $bill = $db->query($bill_sql, [$bill_id])->fetch();
    
    if (!$bill) {
        echo "<script>alert('Bill not found!'); window.location.href='billing.php';</script>";
        exit;
    }
    
    // Get bill items
    $items_sql = "SELECT * FROM bill_items WHERE bill_id = ? ORDER BY id";
    $bill_items = $db->query($items_sql, [$bill_id])->fetchAll();
    
    // Get payment history
    try {
        $payments_sql = "SELECT * FROM bill_payments WHERE bill_id = ? ORDER BY payment_date DESC";
        $payments = $db->query($payments_sql, [$bill_id])->fetchAll();
    } catch (Exception $e) {
        // If bill_payments table doesn't exist, create empty array
        $payments = [];
    }
    
} catch (Exception $e) {
    echo "<script>alert('Error: " . $e->getMessage() . "'); window.location.href='billing.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Details - <?php echo $bill['bill_number']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #004685;
            font-size: 24px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #004685;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .bill-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card h3 {
            color: #004685;
            margin-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            font-weight: 500;
        }
        
        .amount {
            font-weight: bold;
            color: #004685;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-partial {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .items-table th,
        .items-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .items-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .items-table tr:hover {
            background: #f8f9fa;
        }
        
        .summary-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .summary-row.total {
            font-weight: bold;
            font-size: 18px;
            color: #004685;
            border-top: 2px solid #dee2e6;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .payments-section {
            margin-top: 20px;
        }
        
        .payment-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 4px solid #28a745;
        }
        
        .no-payments {
            text-align: center;
            color: #666;
            padding: 30px;
            font-style: italic;
        }
        
        .print-section {
            text-align: center;
            margin-top: 20px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        @media print {
            .header .btn,
            .print-section {
                display: none;
            }
            
            .container {
                max-width: none;
                padding: 0;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
        
        @media (max-width: 768px) {
            .bill-details {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bill Details - <?php echo htmlspecialchars($bill['bill_number']); ?></h1>
            <div>
                <button onclick="window.print()" class="btn btn-primary">Print Bill</button>
                <a href="billing.php" class="btn btn-secondary">Back to Billing</a>
            </div>
        </div>
        
        <div class="bill-details">
            <!-- Bill Information -->
            <div class="card">
                <h3>Bill Information</h3>
                <div class="info-row">
                    <span class="info-label">Bill Number:</span>
                    <span class="info-value"><?php echo htmlspecialchars($bill['bill_number']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Bill Date:</span>
                    <span class="info-value"><?php echo date('F d, Y', strtotime($bill['bill_date'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Bill Type:</span>
                    <span class="info-value"><?php echo ucfirst($bill['bill_type']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Visit ID:</span>
                    <span class="info-value"><?php echo $bill['visit_id'] ? htmlspecialchars($bill['visit_id']) : 'N/A'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value">
                        <span class="status-badge status-<?php echo $bill['payment_status']; ?>">
                            <?php echo ucfirst($bill['payment_status']); ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Created By:</span>
                    <span class="info-value"><?php echo htmlspecialchars($bill['created_by_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Created On:</span>
                    <span class="info-value"><?php echo date('F d, Y H:i', strtotime($bill['created_at'])); ?></span>
                </div>
            </div>
            
            <!-- Patient Information -->
            <div class="card">
                <h3>Patient Information</h3>
                <div class="info-row">
                    <span class="info-label">Patient Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($bill['patient_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient ID:</span>
                    <span class="info-value"><?php echo htmlspecialchars($bill['patient_code']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo htmlspecialchars($bill['patient_phone']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo $bill['patient_email'] ? htmlspecialchars($bill['patient_email']) : 'N/A'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date of Birth:</span>
                    <span class="info-value"><?php echo $bill['date_of_birth'] ? date('F d, Y', strtotime($bill['date_of_birth'])) : 'N/A'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gender:</span>
                    <span class="info-value"><?php echo ucfirst($bill['gender']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Address:</span>
                    <span class="info-value"><?php echo $bill['patient_address'] ? htmlspecialchars($bill['patient_address']) : 'N/A'; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Bill Items -->
        <div class="card">
            <h3>Bill Items</h3>
            <?php if (empty($bill_items)): ?>
                <p style="text-align: center; color: #666; padding: 20px;">No items found for this bill.</p>
            <?php else: ?>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Discount</th>
                            <th>Final Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bill_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo ucfirst($item['item_type']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>₹<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td>₹<?php echo number_format($item['total_price'], 2); ?></td>
                                <td>₹<?php echo number_format($item['discount_amount'], 2); ?></td>
                                <td><strong>₹<?php echo number_format($item['final_price'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="summary-section">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>₹<?php echo number_format($bill['subtotal'], 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Discount:</span>
                        <span>₹<?php echo number_format($bill['discount_amount'], 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Tax (18% GST):</span>
                        <span>₹<?php echo number_format($bill['tax_amount'], 2); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total Amount:</span>
                        <span>₹<?php echo number_format($bill['total_amount'], 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Paid Amount:</span>
                        <span>₹<?php echo number_format($bill['paid_amount'], 2); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Balance Amount:</span>
                        <span>₹<?php echo number_format($bill['balance_amount'], 2); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Payment History -->
        <div class="card">
            <h3>Payment History</h3>
            <?php if (empty($payments)): ?>
                <div class="no-payments">
                    <p>No payment records found for this bill.</p>
                </div>
            <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                    <div class="payment-item">
                        <div class="info-row">
                            <span class="info-label">Payment Date:</span>
                            <span class="info-value"><?php echo date('F d, Y H:i', strtotime($payment['payment_date'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Amount:</span>
                            <span class="info-value amount">₹<?php echo number_format($payment['payment_amount'], 2); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Payment Method:</span>
                            <span class="info-value"><?php echo ucfirst($payment['payment_method']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Reference:</span>
                            <span class="info-value"><?php echo $payment['payment_reference'] ? htmlspecialchars($payment['payment_reference']) : 'N/A'; ?></span>
                        </div>
                        <?php if ($payment['notes']): ?>
                            <div class="info-row">
                                <span class="info-label">Notes:</span>
                                <span class="info-value"><?php echo htmlspecialchars($payment['notes']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Notes -->
        <?php if ($bill['notes']): ?>
            <div class="card">
                <h3>Notes</h3>
                <p style="padding: 15px; background: #f8f9fa; border-radius: 5px; margin: 0;">
                    <?php echo nl2br(htmlspecialchars($bill['notes'])); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="print-section">
            <p><strong>Thank you for choosing our hospital!</strong></p>
            <p>For any queries, please contact our billing department.</p>
        </div>
    </div>
</body>
</html>