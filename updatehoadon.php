<?php
include 'config.php'; // Include your database connection

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['id']) && isset($data['invoice_status'])) {
        $order_id = intval($data['id']);
        $invoice_status = $data['invoice_status'];

        // Note: Your table name in the HTML is "oder" not "orders"
        $stmt = $conn->prepare("UPDATE oder SET invoice_status = ? WHERE id = ?");
        $stmt->bind_param("si", $invoice_status, $order_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Invoice status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update invoice status: ' . $conn->error]);
        }

        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>