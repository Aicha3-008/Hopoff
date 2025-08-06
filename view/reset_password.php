<?php $token = $_GET['token']; ?>
<form action="../controller/update_password.php" method="POST">
  <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />
  <input type="password" name="new_password" placeholder="New Password" required />
  <button type="submit">Reset Password</button>
</form>
