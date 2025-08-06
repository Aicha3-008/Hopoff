<?php
session_start();
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
$active_form = $_SESSION['active_form'] ?? 'login-form';
unset($_SESSION['error'], $_SESSION['success'], $_SESSION['active_form']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <link rel="stylesheet" href="login.css">
</head>
<body>
   <?php if ($error): ?>
    <div class="error" style="color: white; text-align: center;background:red;">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="success" style="color: white; text-align: center;background:green;">
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>
  <div class="container">
    <div class="form-box <?= $active_form === 'login-form' ? 'active' : '' ?>" id="login-form">
      <form action="../controller/login-register.php" method="post"> 
        <h2>Login</h2>
        <div class="input-box">
          <span class="icon"><ion-icon name="mail"></ion-icon></span>
          <input type="email" name="email" required>
          <label>Email</label>
        </div>
        <div class="input-box">
          <span class="icon"><ion-icon name="lock-closed"></ion-icon></span>
          <input type="password" name="password" required>
          <label>Password</label>
        </div>
        <div class="remember-forgot">
          <label><input type="checkbox">Remember me</label>
          <a href="forgot_password.php">Forgot password?</a>
        </div>
        <button type="submit" name="login">Login</button>
        <div class="register-link">
          <p>Don't have an account?<a href="#" onclick="showForm('registration-form')"> Register</a></p>
        </div>
      </form>
    </div>
    
    <div class="form-box <?= $active_form === 'registration-form' ? 'active' : '' ?>" id="registration-form">
      <form action="../controller/login-register.php" method="post"> 
        <h2>Registration</h2>
        <div class="input-box">
          <span class="icon"><ion-icon name="mail"></ion-icon></span>
          <input type="email" name="email" required>
          <label>Email</label>
        </div>
        <div class="input-box">
          <span class="icon"><ion-icon name="lock-closed"></ion-icon></span>
          <input type="password" name="password" required>
          <label>Password</label>
        </div>
        <button type="submit" name="register">Register</button>
        <div class="register-link">
          <p>Already have an account?<a href="#" onclick="showForm('login-form')"> Login</a></p>
        </div>
      </form>
    </div>
  </div>

  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
  <script>
  function showForm(formId){
    document.querySelectorAll(".form-box").forEach(form=>form.classList.remove("active"));
    document.getElementById(formId).classList.add("active");
  }
  </script> 
</body>
</html>