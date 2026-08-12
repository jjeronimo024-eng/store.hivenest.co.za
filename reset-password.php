<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - HiveNest</title>
</head>
<body>
    <h1>Reset Password</h1>
    <form id="reset-password-form">
        <label for="password">New Password:</label>
        <input type="password" id="password" name="password" required>
        <br>
        <button type="submit">Reset Password</button>
    </form>
    <p id="error-message" style="color: red;"></p>
    <p id="success-message" style="color: green;"></p>

    <script>
        document.getElementById('reset-password-form').addEventListener('submit', async (event) => {
            event.preventDefault();

            const password = document.getElementById('password').value;
            const errorMessage = document.getElementById('error-message');
            const successMessage = document.getElementById('success-message');

            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('token');

            const response = await fetch('/api/customer-auth.php?action=reset-password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token, password }),
            });

            const result = await response.json();

            if (result.success) {
                successMessage.textContent = result.message;
                errorMessage.textContent = '';
            } else {
                errorMessage.textContent = result.error;
                successMessage.textContent = '';
            }
        });
    </script>
</body>
</html>