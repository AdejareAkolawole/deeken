document.addEventListener('DOMContentLoaded', () => {
  updateCartCount();
  updateAuthLink();
  const user = getCurrentUser();
  if (!user) {
    window.location.href = 'login.html';
  } else {
    displayProfile(user);
    displayOrders(user.email);
  }
});

function displayProfile(user) {
  const profileInfo = document.getElementById('profileInfo');
  profileInfo.innerHTML = `
    <h2><i class="fas fa-user"></i> Profile</h2>
    <p>Email: ${user.email}</p>
    <p>Role: ${user.isAdmin ? 'Admin' : 'User'}</p>
  `;
}

function displayOrders(userId) {
  const orders = getOrders().filter(o => o.userId === userId);
  const orderList = document.getElementById('orderList');
  orderList.innerHTML = orders.length ? orders.map(o => `
    <div class="order-item">
      <h3>Order #${o.id}</h3>
      <p>Date: ${new Date(o.date).toLocaleDateString()}</p>
      <p>Status: ${o.status}</p>
      <p>Total: $${o.items.reduce((sum, item) => {
        const product = getProducts().find(p => p.id === item.id);
        return sum + (product ? product.price * item.quantity : 0);
      }, 0).toFixed(2)}</p>
    </div>
  `).join('') : '<p>No orders found.</p>';
}