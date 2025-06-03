document.addEventListener('DOMContentLoaded', () => {
  updateCartCount();
  updateAuthLink();
  displayProducts(getProducts());

  document.getElementById('searchInput').addEventListener('input', () => searchProducts());
  document.getElementById('categoryFilter').addEventListener('change', () => searchProducts());
  document.getElementById('sortFilter').addEventListener('change', () => searchProducts());

  // Modal close event
  const modal = document.getElementById('productModal');
  const closeModal = document.querySelector('.close-modal');
  closeModal.addEventListener('click', () => {
    modal.classList.remove('show');
  });
  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      modal.classList.remove('show');
    }
  });
});

function displayProducts(products) {
  const productGrid = document.getElementById('productGrid');
  productGrid.innerHTML = '';
  products.forEach(product => {
    const card = document.createElement('div');
    card.className = 'product-card';
    card.innerHTML = `
      <a href="product.html?id=${product.id}" class="product-link">
        <img src="${product.image}" alt="${product.name}">
        <h3>${product.name}</h3>
        <p>$${product.price.toFixed(2)}</p>
        <p><i class="fas fa-star"></i> ${product.rating}</p>
      </a>
      <button onclick="addToCart(${product.id}, event)"><i class="fas fa-cart-plus"></i> Add to Cart</button>
      <button onclick="showProductDetails(${product.id}, event)"><i class="fas fa-eye"></i> View Details</button>
    `;
    productGrid.appendChild(card);
  });
}

function showProductDetails(id, event) {
  event.stopPropagation();
  const product = getProducts().find(p => p.id === id);
  if (product) {
    const modal = document.getElementById('productModal');
    document.getElementById('modalImage').src = product.image;
    document.getElementById('modalImage').alt = product.name;
    document.getElementById('modalName').textContent = product.name;
    document.getElementById('modalPrice').textContent = `$${product.price.toFixed(2)}`;
    document.getElementById('modalRating').innerHTML = `<i class="fas fa-star"></i> ${product.rating}`;
    document.getElementById('modalDescription').textContent = product.description;
    const addToCartBtn = document.getElementById('modalAddToCart');
    addToCartBtn.onclick = () => addToCart(product.id);
    modal.classList.add('show');
  }
}

function addToCart(id, event) {
  if (event) event.stopPropagation();
  const cart = getCart();
  const item = cart.find(i => i.id === id);
  if (item) {
    item.quantity += 1;
  } else {
    cart.push({ id, quantity: 1 });
  }
  localStorage.setItem('cart', JSON.stringify(cart));
  updateCartCount();
}

function searchProducts() {
  const search = document.getElementById('searchInput').value.toLowerCase();
  const category = document.getElementById('categoryFilter').value;
  const sort = document.getElementById('sortFilter').value;
  let products = getProducts();

  if (search) {
    products = products.filter(p => p.name.toLowerCase().includes(search));
  }
  if (category) {
    products = products.filter(p => p.category === category);
  }
  if (sort === 'price-asc') {
    products.sort((a, b) => a.price - b.price);
  } else if (sort === 'price-desc') {
    products.sort((a, b) => b.price - a.price);
  } else if (sort === 'rating-desc') {
    products.sort((a, b) => b.rating - a.rating);
  }

  displayProducts(products);
}