document.addEventListener('DOMContentLoaded', () => {
  updateCartCount();
  updateAuthLink();
  document.getElementById('loginForm').addEventListener('submit', login);
  document.getElementById('registerForm').addEventListener('submit', register);
});

function showRegister() {
  document.getElementById('loginForm').style.display = 'none';
  document.getElementById('registerForm').style.display = 'block';
}

function login(e) {
  e.preventDefault();
  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;
  const users = getUsers();
  const user = users.find(u => u.email === email && u.password === password);
  if (user) {
    localStorage.setItem('currentUser', JSON.stringify(user));
    window.location.href = 'profile.html';
  } else {
    alert('Invalid credentials');
  }
}

function register(e) {
  e.preventDefault();
  const email = document.getElementById('regEmail').value;
  const password = document.getElementById('regPassword').value;
  const address = document.getElementById('regAddress').value;
  const phone = document.getElementById('regPhone').value;
  const users = getUsers();
  if (users.find(u => u.email === email)) {
    alert('Email already registered');
    return;
  }
  users.push({ email, password, address, phone, isAdmin: false });
  localStorage.setItem('users', JSON.stringify(users));
  localStorage.setItem('currentUser', JSON.stringify({ email, address, phone, isAdmin: false }));
  window.location.href = 'profile.html';
}