<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Password Reset - HiveNest</title>
</head>
<body>
    <h1>Request Password Reset</h1>
    <form id="request-password-reset-form">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <br>
        <button type="submit">Send Reset Link</button>
    </form>
    <p id="message" style="color: green;"></p>

    <script>
        document.getElementById('request-password-reset-form').addEventListener('submit', async (event) => {
            event.preventDefault();

            const email = document.getElementById('email').value;
            const message = document.getElementById('message');

            const response = await fetch('/api/customer-auth.php?action=request-password-reset', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email }),
            });

            const result = await response.json();

            message.textContent = result.message;
        });
    </script>
</body>
</html>