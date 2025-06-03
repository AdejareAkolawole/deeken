document.addEventListener('DOMContentLoaded', () => {
  updateCartCount();
  updateAuthLink();
  displayCart();
});

function displayCart() {
  const cart = getCart();
  const cartItems = document.getElementById('cartItems');
  const cartSummary = document.getElementById('cartSummary');
  cartItems.innerHTML = '';
  let total = 0;

  cart.forEach(item => {
    const product = getProducts().find(p => p.id === item.id);
    if (product) {
      const itemTotal = product.price * item.quantity;
      total += itemTotal;
      const cartItem = document.createElement('div');
      cartItem.className = 'cart-item';
      cartItem.innerHTML = `
        <img src="${product.image}" alt="${product.name}">
        <div class="cart-item-details">
          <h3>${product.name}</h3>
          <p>$${product.price.toFixed(2)}</p>
          <input type="number" value="${item.quantity}" min="1" onchange="updateQuantity(${item.id}, this.value)">
          <p>$${itemTotal.toFixed(2)}</p>
          <button onclick="removeFromCart(${item.id})"><i class="fas fa-trash"></i> Remove</button>
        </div>
      `;
      cartItems.appendChild(cartItem);
    }
  });

  cartSummary.innerHTML = `<p>Total: $${total.toFixed(2)}</p>`;
}

function updateQuantity(id, quantity) {
  const cart = getCart();
  const item = cart.find(i => i.id === id);
  if (item) {
    item.quantity = parseInt(quantity);
    if (item.quantity < 1) {
      removeFromCart(id);
    } else {
      localStorage.setItem('cart', JSON.stringify(cart));
      displayCart();
      updateCartCount();
    }
  }
}

function removeFromCart(id) {
  let cart = getCart();
  cart = cart.filter(i => i.id !== id);
  localStorage.setItem('cart', JSON.stringify(cart));
  displayCart();
  updateCartCount();
}