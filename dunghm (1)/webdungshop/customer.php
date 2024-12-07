<?php
include('databaseconnect.php');

// Start session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get the search term if it exists
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Fetch products from the database
$query = "
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
";

// Modify the query if there's a search term
if (!empty($searchTerm)) {
    $query .= " WHERE p.name LIKE :searchTerm OR p.description LIKE :searchTerm";
}

$stmt = $conn->prepare($query);

// Bind search term parameter if it's set
if (!empty($searchTerm)) {
    $stmt->execute([':searchTerm' => '%' . $searchTerm . '%']);
} else {
    $stmt->execute();
}

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add product to cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];

    // Initialize cart if not already set
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Add or update product in the cart
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }

    // Redirect to the customer page (same page)
    header('Location: customer.php');
    exit;
}

// Handle checkout 
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout'])) {
    // No need for user_id anymore
    $total = 0;

    // Calculate the total cost of the cart
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $stmt = $conn->prepare("SELECT price FROM products WHERE id = :product_id");
        $stmt->execute([':product_id' => $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        $total += $product['price'] * $quantity;
    }

    // Insert the order into the orders table 
    $query = "INSERT INTO orders (created_at, total, status) 
              VALUES (NOW(), :total, 'Pending')";
    $stmt = $conn->prepare($query);
    $stmt->execute([':total' => $total]);
    $order_id = $conn->lastInsertId();

    // Insert order details into the order_detail table
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $stmt = $conn->prepare("SELECT price FROM products WHERE id = :product_id");
        $stmt->execute([':product_id' => $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        $query = "INSERT INTO order_detail (order_id, product_id, price, amount) 
                  VALUES (:order_id, :product_id, :price, :amount)";
        $stmt = $conn->prepare($query);
        $stmt->execute([ 
            ':order_id' => $order_id,
            ':product_id' => $product_id,
            ':price' => $product['price'],
            ':amount' => $quantity
        ]);
    }

    // Clear the cart after checkout
    unset($_SESSION['cart']);
    header('Location: customer.php?success=1');
    exit;
}
// Get the search term or reset if "Show All" is clicked
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Check if the "Show All" button was clicked
if (isset($_GET['show_all']) && $_GET['show_all'] == '1') {
    $searchTerm = ''; // Reset search term to show all products
}

// Fetch products from the database
$query = "
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
";

// Modify the query if there's a search term
if (!empty($searchTerm)) {
    $query .= " WHERE p.name LIKE :searchTerm OR p.description LIKE :searchTerm";
}

$stmt = $conn->prepare($query);

// Bind search term parameter if it's set
if (!empty($searchTerm)) {
    $stmt->execute([':searchTerm' => '%' . $searchTerm . '%']);
} else {
    $stmt->execute();
}

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Remove product from cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_from_cart'])) {
    $product_id = $_POST['product_id'];

    // Remove product from cart
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }

    // Redirect back to shopping cart page (same page)
    header('Location: customer.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Page</title>
    <link rel="stylesheet" href="customer.css">
    <style>
        form {
            margin: 20px auto;
            text-align: center; 
        }

        input[type="text"] {
            width: 300px; 
            padding: 10px; 
            font-size: 16px; 
            border: 2px solid #ccc; 
            border-radius: 5px; 
            margin-right: 10px; 
        }

        input[type="text"]:focus {
            border-color: #ff6600; 
            outline: none; 
        }

        button[type="submit"] {
            padding: 10px 20px; 
            font-size: 16px; 
            background-color: green; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            transition: background-color 0.3s ease;
        }

        button[type="submit"]:hover {
            background-color: darkgreen;
        }

        .purchase-history-btn {
        display: inline-block;
        padding: 10px 20px;
        font-size: 16px;
        background-color: green;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        margin-left: 10px;
        transition: background-color 0.3s ease;
    }

    .purchase-history-btn:hover {
        background-color: darkgreen;
    }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Dung Shop Fashion</h1>
            <nav class="nav">
                <ul>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>

            <form method="GET" action="customer.php">
            <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" required>
            <button type="submit">Search</button>
            <button type="submit" name="show_all" value="1">Show All Products</button>
        </form>
        </header>

        <main class="main-content">
            <!-- Product List Section -->
            <section class="product-list">
                <h2>Our items have</h2>
                <div class="product-cards">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars($product['image'] ?? 'default.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'No Category'); ?></p>
                            <p class="product-price">$<?php echo number_format($product['price'], 2); ?></p>
                            <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                            <p class="product-quantity"><?php echo $product['quantity']; ?> available</p>

                            <form method="POST" action="customer.php" class="add-to-cart-form">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="number" name="quantity" min="1" max="<?php echo $product['quantity']; ?>" value="1" required class="quantity-input">
                                <button type="submit" name="add_to_cart" class="add-to-cart-btn">Add to Cart</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Success message when checkout is complete -->
            <?php if (isset($_GET['success'])): ?>
                <p class="success-message">Thank you for shopping!</p>
            <?php endif; ?>

            <!-- Shopping Cart Section -->
            <section class="shopping-cart">
                <h2>Shopping Cart</h2>
                <?php if (!empty($_SESSION['cart'])): ?>
                    <div class="cart-items">
                        <?php 
                        $total = 0;
                        foreach ($_SESSION['cart'] as $product_id => $quantity):
                            $stmt = $conn->prepare("SELECT * FROM products WHERE id = :id");
                            $stmt->execute([':id' => $product_id]);
                            $product = $stmt->fetch(PDO::FETCH_ASSOC);
                            $subtotal = $product['price'] * $quantity;
                            $total += $subtotal;
                        ?>
                            <div class="cart-item-card">
                                <img src="<?php echo htmlspecialchars($product['image'] ?? 'default.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" class="cart-item-image">
                                <h3 class="cart-item-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="cart-item-price">$<?php echo number_format($product['price'], 2); ?></p>
                                <p class="cart-item-quantity">Quantity: <?php echo $quantity; ?></p>
                                <p class="cart-item-subtotal">Subtotal: $<?php echo number_format($subtotal, 2); ?></p>
                                <form method="POST" action="customer.php" class="remove-from-cart-form">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" name="remove_from_cart" class="remove-btn">Remove</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="cart-total">
                        <strong>Total: $<?php echo number_format($total, 2); ?></strong>
                    </div>

                    <!-- Checkout Form -->
                    <form method="POST" action="customer.php" class="checkout-form">
                        <button type="submit" name="checkout" class="checkout-btn">Checkout</button>
                    </form>
                <?php else: ?>
                    <p>Your cart is empty.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
