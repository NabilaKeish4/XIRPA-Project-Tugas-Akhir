<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $result = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
    if (mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role']    = $row['role'];

            // Redirection berdasarkan Role Flowchart
            if ($row['role'] === 'admin') {
                header("Location: ../admin/index.php");
            } else {
                header("Location: ../pelanggan/index.php");
            }
            exit();
        }
    }
    $error = "Username atau Password salah!";
}
?>

<form method="POST">
    <h2>Login</h2>
    <?php if (isset($error)) echo "<p>$error</p>"; ?>
    <input type="text" name="username" placeholder="Username" required><br>
    <input type="password" name="password" placeholder="Password" required><br>
    <button type="submit">Login</button>
</form>