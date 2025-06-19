document.addEventListener('DOMContentLoaded', () => {
  updateCartCount();
  updateAuthLink();
  const urlParams = new URLSearchParams(window.location.search);
  const productId = parseInt(urlParams.get('id'));
  const product = getProducts().find(p => p.id === productId);
  if (product) {
    displayProduct(product);
    displayReviews(productId);
    displayRelatedProducts(product.category, productId);
  }

  const reviewForm = document.getElementById('reviewForm');
  if (reviewForm) {
    reviewForm.addEventListener('submit', (e) => {
      e.preventDefault();
      submitReview(productId);
    });
  }
});

function displayProduct(product) {
  const productDetails = document.getElementById('productDetails');
  productDetails.innerHTML = `
    <img src="${product.image}" alt="${product.name}">
    <div class="product-info">
      <h1>${product.name}</h1>
      <p>$${product.price.toFixed(2)}</p>
      <p><i class="fas fa-star"></i> ${product.rating}</p>
      <p>${product.description}</p>
      <button onclick="addToCart(${product.id})"><i class="fas fa-cart-plus"></i> Add to Cart</button>
    </div>
  `;
}

function displayReviews(productId) {
  const reviews = getReviews().filter(r => r.productId === productId);
  const reviewList = document.getElementById('reviewList');
  reviewList.innerHTML = reviews.length ? reviews.map(r => `
    <div class="review">
      <p><strong>${r.user}</strong></p>
      <p>${r.text}</p>
    </div>
  `).join('') : '<p>No reviews yet.</p>';
}

function submitReview(productId) {
  const user = getCurrentUser();
  if (!user) {
    alert('Please login to submit a review.');
    return;
  }
  const reviewText = document.getElementById('reviewText').value;
  if (reviewText) {
    const reviews = getReviews();
    reviews.push({ productId, user: user.email, text: reviewText });
    localStorage.setItem('reviews', JSON.stringify(reviews));
    displayReviews(productId);
    document.getElementById('reviewText').value = '';
  }
}

function displayRelatedProducts(category, productId) {
  const related = getProducts().filter(p => p.category === category && p.id !== productId).slice(0, 4);
  const relatedProducts = document.getElementById('relatedProducts');
  relatedProducts.innerHTML = related.map(p => `
    <div class="product-card">
      <img src="${p.image}" alt="${p.name}">
      <h3>${p.name}</h3>
      <p>$${p.price.toFixed(2)}</p>
      <button onclick="addToCart(${p.id})"><i class="fas fa-cart-plus"></i> Add to Cart</button>
    </div>
  `).join('');
}