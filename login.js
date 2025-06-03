document.addEventListener('DOMContentLoaded', () => {
  updateCartCount();
  updateAuthLink();
  document.getElementById('loginForm').addEventListener('submit', (e) => {
    e.preventDefault();
    login();
  });
});

function login() {
  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;
  const users = getUsers();
  const user = users.find(u => u.email === email && u.password === password);

  if (user) {
    localStorage.setItem('currentUser', JSON.stringify(user));
    window.location.href = user.isAdmin ? 'admin.html' : 'index.html';
  } else {
    alert('Invalid credentials.');
  }
}