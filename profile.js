document.addEventListener('DOMContentLoaded', () => {
  updateCartCount();
  updateAuthLink();
  displayProfile();
});

function displayProfile() {
  const user = getCurrentUser();
  if (!user) {
    window.location.href = 'login.html';
    return;
  }
  document.getElementById('profileInfo').innerHTML = `
    <p>Email: ${user.email}</p>
    <p>Address: ${user.address || 'Not set'}</p>
    <p>Phone: ${user.phone || 'Not set'}</p>
  `;
  const orders = getOrders().filter(o => o.userEmail === user.email);
  document.getElementById('orderHistory').innerHTML = orders.map(o => `
    <div class="order-item">
      <h3>Order ID: ${o.id}</h3>
      <p>Total: $${o.total}</p>
      <p>Status: ${o.status}</p>
      <p>Address: ${o.address}</p>
    </div>
  `).join('');
}