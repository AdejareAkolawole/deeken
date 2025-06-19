document.addEventListener('DOMContentLoaded', () => {
  updateCartCount();
  updateAuthLink();
  if (!getCurrentUser()) {
    window.location.href = 'login.html';
  }
  displayCheckout();
  document.getElementById('checkoutForm').addEventListener('submit', (e) => {
    e.preventDefault();
    submitOrder();
  });
});

function displayCheckout() {
  const cart = getCart();
  const checkoutItems = document.getElementById('checkoutItems');
  let total = 0;

  cart.forEach(item => {
    const product = getProducts().find(p => p.id === item.id);
    if (product) {
      const itemTotal = product.price * item.quantity;
      total += itemTotal;
      checkoutItems.innerHTML += `
        <div class="cart-item">
          <h3>${product.name}</h3>
          <p>$${product.price.toFixed(2)} x ${item.quantity} = $${itemTotal.toFixed(2)}</p>
        </div>
      `;
    }
  });

  checkoutItems.innerHTML += `<p>Total: $${total.toFixed(2)}</p>`;
}

function submitOrder() {
  const cart = getCart();
  if (cart.length === 0) {
    alert('Your cart is empty.');
    return;
  }
  const user = getCurrentUser();
  const orders = getOrders();
  const order = {
    id: orders.length + 1,
    userId: user.email,
    items: cart,
    status: 'Pending',
    date: new Date().toISOString()
  };
  orders.push(order);
  localStorage.setItem('orders', JSON.stringify(orders));
  localStorage.setItem('cart', JSON.stringify([]));
  alert('Order placed successfully!');
  window.location.href = 'profile.html';
}