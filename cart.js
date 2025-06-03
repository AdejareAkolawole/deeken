document.addEventListener('DOMContentLoaded', () => {
  updateCartCount();
  updateAuthLink();
  displayCart();
});

function displayCart() {
  const cart = getCart();
  const products = getProducts();
  const cartItems = document.getElementById('cartItems');
  const cartTotal = document.getElementById('cartTotal');
  if (cart.length === 0) {
    cartItems.innerHTML = '<p>Your cart is empty.</p>';
    cartTotal.textContent = '0';
    return;
  }
  let total = 0;
  cartItems.innerHTML = cart.map(item => {
    const product = products.find(p => p.id === item.id);
    total += product.price * item.quantity;
    return `
      <div class="cart-item">
        <img src="${product.image}" alt="${product.name}">
        <div class="cart-item-details">
          <h3>${product.name}</h3>
          <p>Price: $${product.price}</p>
          <input type="number" value="${item.quantity}" min="1" onchange="updateQuantity(${item.id}, this.value)">
          <button onclick="removeFromCart(${item.id})">Remove</button>
        </div>
      </div>
    `;
  }).join('');
  cartTotal.textContent = total.toFixed(2);
}

function updateQuantity(id, quantity) {
  const cart = getCart();
  const item = cart.find(i => i.id === id);
  item.quantity = parseInt(quantity);
  localStorage.setItem('cart', JSON.stringify(cart));
  displayCart();
}

function removeFromCart(id) {
  let cart = getCart();
  cart = cart.filter(i => i.id !== id);
  localStorage.setItem('cart', JSON.stringify(cart));
  displayCart();
  updateCartCount();
}