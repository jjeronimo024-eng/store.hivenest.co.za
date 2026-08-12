<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - HiveNest</title>
</head>
<body>
    <h1>Register</h1>
    <form id="register-form">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <br>
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
        <br>
        <label for="first_name">First Name:</label>
        <input type="text" id="first_name" name="first_name" required>
        <br>
        <label for="last_name">Last Name:</label>
        <input type="text" id="last_name" name="last_name" required>
        <br>
        <button type="submit">Register</button>
    </form>
    <p id="error-message" style="color: red;"></p>

    <script>
        document.getElementById('register-form').addEventListener('submit', async (event) => {
            event.preventDefault();

            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const first_name = document.getElementById('first_name').value;
            const last_name = document.getElementById('last_name').value;
            const errorMessage = document.getElementById('error-message');

            const response = await fetch('/api/customer-auth.php?action=register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password, first_name, last_name }),
            });

            const result = await response.json();

            if (result.authenticated) {
                window.location.href = '/account.php';
            } else {
                errorMessage.textContent = result.error;
            }
        });
    </script>
</body>
</html>