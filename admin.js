document.addEventListener('DOMContentLoaded', () => {
  updateCartCount();
  updateAuthLink();
  const user = getCurrentUser();
  if (!user || !user.isAdmin) {
    alert('Access denied. Admins only.');
    window.location.href = 'index.html';
    return;
  }
  displayKPIs();
  displayProductsAdmin();
  displayOrdersAdmin();
  document.getElementById('productForm').addEventListener('submit', addProduct);
});

function displayKPIs() {
  const orders = getOrders();
  const users = getUsers();
  const totalRevenue = orders.reduce((sum, o) => sum + o.total, 0);
  document.getElementById('totalOrders').textContent = orders.length;
  document.getElementById('totalRevenue').textContent = totalRevenue.toFixed(2);
  document.getElementById('totalCustomers').textContent = users.length;
}

function displayProductsAdmin() {
  const products = getProducts();
  document.getElementById('productList').innerHTML = products.map(p => `
    <div class="product-list-item">
      <h3>${p.name}</h3>
      <p>Price: $${p.price}</p>
      <button onclick="deleteProduct(${p.id})">Delete</button>
    </div>
  `).join('');
}

function addProduct(e) {
  e.preventDefault();
  const products = getProducts();
  const newProduct = {
    id: products.length + 1,
    name: document.getElementById('productName').value,
    price: parseFloat(document.getElementById('productPrice').value),
    image: document.getElementById('productImage').value,
    category: document.getElementById('productCategory').value,
    description: document.getElementById('productDescription').value,
    rating: 0
  };
  products.push(newProduct);
  localStorage.setItem('products', JSON.stringify(products));
  document.getElementById('productForm').reset();
  displayProductsAdmin();
}

function deleteProduct(id) {
  let products = getProducts();
  products = products.filter(p => p.id !== id);
  localStorage.setItem('products', JSON.stringify(products));
  displayProductsAdmin();
}

function displayOrdersAdmin() {
  const orders = getOrders();
  document.getElementById('orderList').innerHTML = orders.map(o => `
    <div class="order-list-item">
      <h3>Order ID: ${o.id}</h3>
      <p>User: ${o.userEmail}</p>
      <p>Total: $${o.total}</p>
      <select onchange="updateOrderStatus(${o.id}, this.value)">
        <option value="pending" ${o.status === 'pending' ? 'selected' : ''}>Pending</option>
        <option value="shipped" ${o.status === 'shipped' ? 'selected' : ''}>Shipped</option>
        <option value="delivered" ${o.status === 'delivered' ? 'selected' : ''}>Delivered</option>
      </select>
    </div>
  `).join('');
}

function updateOrderStatus(id, status) {
  const orders = getOrders();
  const order = orders.find(o => o.id === id);
  order.status = status;
  localStorage.setItem('orders', JSON.stringify(orders));
  displayOrdersAdmin();
}