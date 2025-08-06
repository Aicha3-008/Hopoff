<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="dash.css">
    <style>
        body {
            min-height: 100vh;
            background: var(--body-color);
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            transition: var(--tran-05);
        }
        .forgot-password-container {
            background: var(--sidebar-color);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .forgot-password-container h2 {
            color: var(--text-color);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }
        .forgot-password-container input[type="email"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 1rem;
            border: 1px solid var(--toggle-color);
            border-radius: 5px;
            background: var(--body-color);
            color: var(--text-color);
            font-size: 1em;
            transition: border-color 0.3s;
        }
        .forgot-password-container input[type="email"]:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        .forgot-password-container button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            background-color: var(--toggle-color);
            color: var(--text-color);
            font-size: 1em;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s, box-shadow 0.3s;
            width: 100%;
        }
        .forgot-password-container button:hover {
            background-color: var(--primary-color);
            color: var(--sidebar-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        @media (max-width: 768px) {
            .forgot-password-container {
                padding: 1.5rem;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <h2>Forgot Password</h2>
        <form action="../controller/send_reset.php" method="POST">
            <input type="email" name="email" placeholder="Enter your email" required />
            <button type="submit">Send Reset Link</button>
        </form>
    </div>
</body>
</html>