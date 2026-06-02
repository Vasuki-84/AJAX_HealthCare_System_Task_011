        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const btn = e.target.querySelector('button');
            const loader = document.getElementById('loader');
            const alert = document.getElementById('loginAlert');

            // Show UI loading state
            btn.disabled = true;
            loader.style.display = 'inline-block';
            alert.style.display = 'none';

            try {
                const response = await fetch('auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'login', username, password })
                });
                
                const result = await response.json();

                if (result.status === 'success') {
                    window.location.href = 'index.php';
                } else {
                    alert.textContent = result.message;
                    alert.style.display = 'block';
                }
            } catch (error) {
                alert.textContent = 'Something went wrong. Please try again.';
                alert.style.display = 'block';
            } finally {
                btn.disabled = false;
                loader.style.display = 'none';
            }
        });