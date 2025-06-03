document.addEventListener('DOMContentLoaded', () => {
  updateCartCount();
  updateAuthLink();
  displayProducts(getProducts());

  document.getElementById('searchInput').addEventListener('input', () => searchProducts());
  document.getElementById('categoryFilter').addEventListener('change', () => searchProducts());
  document.getElementById('sortFilter').addEventListener('change', () => searchProducts());
});

function displayProducts(products) {
  const productGrid = document.getElementById('productGrid');
  productGrid.innerHTML = '';
  products.forEach(product => {
    const card = document.createElement('div');
    card.className = 'product-card';
    card.innerHTML = `
      <img src="${product.image}" alt="${product.name}">
      <h3>${product.name}</h3>
      <p>$${product.price.toFixed(2)}</p>
      <p><i class="fas fa-star"></i> ${product.rating}</p>
      <button onclick="addToCart(${product.id})"><i class="fas fa-cart-plus"></i> Add to Cart</button>
    `;
    productGrid.appendChild(card);
  });
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