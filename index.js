document.addEventListener('DOMContentLoaded', () => {
  updateCartCount();
  updateAuthLink();
  displayProducts();
  document.getElementById('categoryFilter').addEventListener('change', displayProducts);
  document.getElementById('sortFilter').addEventListener('change', displayProducts);
});

function searchProducts() {
  const query = document.getElementById('searchInput').value.toLowerCase();
  displayProducts(query);
}

function displayProducts(searchQuery = '') {
  const category = document.getElementById('categoryFilter').value;
  const sort = document.getElementById('sortFilter').value;
  let products = getProducts();
  
  if (searchQuery) {
    products = products.filter(p => p.name.toLowerCase().includes(searchQuery));
  }
  if (category) {
    products = products.filter(p => p.category === category);
  }
  if (sort === 'price-low') {
    products.sort((a, b) => a.price - b.price);
  } else if (sort === 'price-high') {
    products.sort((a, b) => b.price - a.price);
  } else if (sort === 'rating') {
    products.sort((a, b) => b.rating - a.rating);
  }

  const productGrid = document.getElementById('productGrid');
  productGrid.innerHTML = products.map(p => `
    <div class="product-card">
      <img src="${p.image}" alt="${p.name}">
      <h3>${p.name}</h3>
      <p>$${p.price}</p>
      <p>Rating: ${p.rating}/5</p>
      <button onclick="addToCart(${p.id})">Add to Cart</button>
      <a href="product.html?id=${p.id}">View Details</a>
    </div>
  `).join('');
}