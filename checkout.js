document.addEventListener('DOMContentLoaded', () => {
  updateCartCount();
  updateAuthLink();
  displayOrderSummary();
  document.getElementById('checkoutForm').addEventListener('submit', placeOrder);
});

function displayOrderSummary() {
  const cart = getCart();
  const products = getProducts();
  const orderSummary = document.getElementById('orderSummary');
  const orderTotal = document.getElementById('orderTotal');
  const deliveryFee = 5;
  let total = 0;
  orderSummary.innerHTML = cart.map(item => {
    const product = products.find(p => p.id === item.id);
    total += product.price * item.quantity;
    return `
      <div class="cart-item">
        <h3>${product.name}</h3>
        <p>Price: $${product.price} x ${item.quantity}</p>
      </div>
    `;
  }).join('');
  orderTotal.textContent = (total + deliveryFee).toFixed(2);
}

function placeOrder(e) {
  e.preventDefault();
  const user = getCurrentUser();
  if (!user) {
    alert('Please log in to place an order');
    window.location.href = 'login.html';
    return;
  }
  const address = document.getElementById('address').value;
  const phone = document.getElementById('phone').value;
  const cart = getCart();
  const order = {
    id: Date.now(),
    userEmail: user.email,
    items: cart,
    address,
    phone,
    status: 'pending',
    total: parseFloat(document.getElementById('orderTotal').textContent)
  };
  const orders = getOrders();
  orders.push(order);
  localStorage.setItem('orders', JSON.stringify(orders));
  localStorage.setItem('cart', JSON.stringify([]));
  alert('Order placed successfully! Order ID: ' + order.id);
  window.location.href = 'profile.html';
}