document.addEventListener('DOMContentLoaded', () => {
  updateCartCount();
  updateAuthLink();
  const urlParams = new URLSearchParams(window.location.search);
  const productId = parseInt(urlParams.get('id'));
  displayProduct(productId);
  displayRelatedProducts(productId);
  displayReviews(productId);
  document.getElementById('reviewForm').addEventListener('submit', (e) => {
    e.preventDefault();
    submitReview(productId);
  });
});

function displayProduct(id) {
  const product = getProducts().find(p => p.id === id);
  if (!product) return;
  document.getElementById('productDetails').innerHTML = `
    <img src="${product.image}" alt="${product.name}">
    <div class="product-info">
      <h1>${product.name}</h1>
      <p>Price: $${product.price}</p>
      <p>Category: ${product.category}</p>
      <p>Rating: ${product.rating}/5</p>
      <p>${product.description}</p>
      <button onclick="addToCart(${product.id})">Add to Cart</button>
    </div>
  `;
}

function displayRelatedProducts(id) {
  const products = getProducts().filter(p => p.id !== id).slice(0, 4);
  document.getElementById('relatedProducts').innerHTML = products.map(p => `
    <div class="product-card">
      <img src="${p.image}" alt="${p.name}">
      <h3>${p.name}</h3>
      <p>$${p.price}</p>
      <a href="product.html?id=${p.id}">View Details</a>
    </div>
  `).join('');
}

function displayReviews(productId) {
  const reviews = getReviews().filter(r => r.productId === productId);
  document.getElementById('reviewList').innerHTML = reviews.map(r => `
    <div class="review">
      <p>Rating: ${r.rating}/5</p>
      <p>${r.text}</p>
      <p>By: ${r.userEmail}</p>
    </div>
  `).join('');
}

function submitReview(productId) {
  const user = getCurrentUser();
  if (!user) {
    alert('Please log in to submit a review');
    window.location.href = 'login.html';
    return;
  }
  const text = document.getElementById('reviewText').value;
  const rating = parseInt(document.getElementById('reviewRating').value);
  const reviews = getReviews();
  reviews.push({ productId, text, rating, userEmail: user.email });
  localStorage.setItem('reviews', JSON.stringify(reviews));
  document.getElementById('reviewForm').reset();
  displayReviews(productId);
}