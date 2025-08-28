<?php 
session_start();







// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "homesync";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// signup handling storing the password using MD5 hashing on our db
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['signup'])) {
    $user = $_POST['username'];
    $pass = md5($_POST['password']); // Using MD5 for hashing
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $user, $pass);
    if ($stmt->execute()) {
        echo "Signup successful. You can now log in.";
    } else {
        echo "Error: " . $stmt->error;
    }
}
// Login handling
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = md5($_POST['password']); // Using MD5 for hashing
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $user, $pass);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];
        header("Location: dashboard.php");
        exit();
    } else {
        echo "Invalid credentials.";
    }
}
// Logout handling
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: autho.php");
    exit();
}
// Session validation
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) != 'autho.php') {
    header("Location: autho.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autho - HomeSync</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="favicon.ico" type="image/x-icon">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        input {
            display: block;
            margin-bottom: 10px;
            padding: 10px;
            width: 100%;
            box-sizing: border-box;
        }
        button {
            padding: 10px 20px;
            background: #007BFF;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
    </style>



</head>

<body>



    <div class="container">
        <?php if (!isset($_SESSION['user_id'])): ?>
        <h2>Sign Up for HomeSync</h2>
        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="password" name="confirm_password" placeholder="confirm password" id="">
            <button type="submit" name="signup">Sign Up</button>
        </form>
        <hr>
        <h2>Login to HomeSync</h2>
        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
        </form>
        <?php else: ?>
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
        <a href="?logout=true">Logout</a>
        <?php endif; ?>
    </div>
</body>
</html>


<?php $conn->close(); ?>