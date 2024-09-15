<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function
function logMessage($message) {
    file_put_contents('debug.log', date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "lab_act2";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    logMessage("Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$debug_info = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    logMessage("Received POST request: " . print_r($_POST, true));
    $debug_info['post_data'] = $_POST;

    // Check if it's an add or update operation
    if (isset($_POST['name']) && isset($_POST['category']) && isset($_POST['price']) && isset($_POST['quantity'])) {
        $name = trim($_POST['name']);
        $category = trim($_POST['category']);
        $price = floatval($_POST['price']);
        $quantity = intval($_POST['quantity']);

        if (empty($name) || empty($category) || $price <= 0 || $quantity <= 0) {
            $message = "Invalid input. All fields are required, price and quantity must be positive.";
        } else {
            if (empty($_POST['id'])) {
                // Add new item
                $stmt = $conn->prepare("INSERT INTO stock (Name, Category, Price, Quantity) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssdi", $name, $category, $price, $quantity);
                if ($stmt->execute()) {
                    $message = "Item added successfully.";
                    logMessage("Item added: $name, $category, $price, $quantity");
                } else {
                    $message = "Error adding item: " . $conn->error;
                    logMessage("Error adding item: " . $conn->error);
                }
            } else {
                // Update existing item
                $id = intval($_POST['id']);
                $stmt = $conn->prepare("UPDATE stock SET Name=?, Category=?, Price=?, Quantity=? WHERE ID=?");
                $stmt->bind_param("ssdii", $name, $category, $price, $quantity, $id);
                if ($stmt->execute()) {
                    $message = "Item updated successfully.";
                    logMessage("Item updated: ID $id, $name, $category, $price, $quantity");
                } else {
                    $message = "Error updating item: " . $conn->error;
                    logMessage("Error updating item: " . $conn->error);
                }
            }
            $stmt->close();
        }
    } elseif (isset($_POST['delete'])) {
        // Delete operation
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM stock WHERE ID=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "Item deleted successfully.";
        } else {
            $message = "Error deleting item: " . $conn->error;
        }
        $stmt->close();
    } elseif (isset($_POST['search'])) {
        // Search operation
        $search_name = trim($_POST['search_name']);
        $stmt = $conn->prepare("SELECT * FROM stock WHERE Name LIKE ?");
        $search_term = "%$search_name%";
        $stmt->bind_param("s", $search_term);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $search_results = [];
            while ($row = $result->fetch_assoc()) {
                $search_results[] = $row;
            }
            $message = "Search results found.";
        } else {
            $message = "No products found.";
        }
        $stmt->close();
    }
}

// Generate inventory table HTML
$table_html = '<table class="inventory-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Item</th>
            <th>Category</th>
            <th>Price</th>
            <th>Quantity</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>';

$result = $conn->query("SELECT * FROM stock");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $table_html .= "<tr>
            <td>{$row['ID']}</td>
            <td>{$row['Name']}</td>
            <td>{$row['Category']}</td>
            <td>$" . number_format($row['Price'], 2) . "</td>
            <td>{$row['Quantity']}</td>
            <td>
                <button class='edit-btn' onclick='editItem({$row['ID']})'>Edit</button>
                <button class='delete-btn' onclick='deleteItem({$row['ID']})'>Delete</button>
            </td>
        </tr>";
    }
} else {
    $table_html .= "<tr><td colspan='6'>No items in inventory</td></tr>";
}

$table_html .= '</tbody></table>';

// Get low stock items
$lowStockItems = [];
$result = $conn->query("SELECT Name, Quantity FROM stock WHERE Quantity < 10");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $lowStockItems[] = [
            'name' => $row['Name'],
            'quantity' => $row['Quantity']
        ];
    }
}

// Close the database connection
$conn->close();

// Send JSON response
header('Content-Type: application/json');
$response = [
    'message' => $message,
    'inventory_table' => $table_html,
    'debug_info' => $debug_info
];
echo json_encode($response);
logMessage("Sent response: " . print_r($response, true));
exit;
