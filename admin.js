document.addEventListener('DOMContentLoaded', () => {
  updateCartCount();
  updateAuthLink();
  const user = getCurrentUser();
  if (!user || !user.isAdmin) {
    window.location.href = 'login.html';
  } else {
    displayKPIs();
    displayProducts();
    displayOrders();
    document.getElementById('productForm').addEventListener('submit', (e) => {
      e.preventDefault();
      addProduct();
    });
  }
});

function displayKPIs() {
  const orders = getOrders();
  const products = getProducts();
  const revenue = orders.reduce((sum, order) => {
    return sum + order.items.reduce((s, item) => {
      const product = products.find(p => p.id === item.id);
      return s + (product ? product.price * item.quantity : 0);
    }, 0);
  }, 0);
  document.getElementById('kpis').innerHTML = `
    <p><i class="fas fa-shopping-cart"></i> Total Orders: ${orders.length}</p>
    <p><i class="fas fa-dollar-sign"></i> Revenue: $${revenue.toFixed(2)}</p>
    <p><i class="fas fa-box"></i> Products: ${products.length}</p>
  `;
}

function displayProducts() {
  const products = getProducts();
  const productList = document.getElementById('productList');
  productList.innerHTML = products.map(p => `
    <div class="product-list-item">
      <h3>${p.name}</h3>
      <p>$${p.price.toFixed(2)}</p>
      <button onclick="deleteProduct(${p.id})"><i class="fas fa-trash"></i> Delete</button>
    </div>
  `).join('');
}

function addProduct() {
  const name = document.getElementById('productName').value;
  const price = parseFloat(document.getElementById('productPrice').value);
  const image = document.getElementById('productImage').value;
  const category = document.getElementById('productCategory').value;
  const description = document.getElementById('productDescription').value;

  if (name && price && image && category && description) {
    const products = getProducts();
    const newProduct = {
      id: products.length + 1,
      name,
      price,
      image,
      category,
      description,
      rating: 0
    };
    products.push(newProduct);
    localStorage.setItem('products', JSON.stringify(products));
    displayProducts();
    document.getElementById('productForm').reset();
  } else {
    alert('Please fill all fields.');
  }
}

function deleteProduct(id) {
  let products = getProducts();
  products = products.filter(p => p.id !== id);
  localStorage.setItem('products', JSON.stringify(products));
  displayProducts();
}

function displayOrders() {
  const orders = getOrders();
  const orderList = document.getElementById('orderList');
  orderList.innerHTML = orders.map(o => `
    <div class="order-list-item">
      <h3>Order #${o.id}</h3>
      <p>User: ${o.userId}</p>
      <p>Total: $${o.items.reduce((sum, item) => {
        const product = getProducts().find(p => p.id === item.id);
        return sum + (product ? product.price * item.quantity : 0);
      }, 0).toFixed(2)}</p>
      <select onchange="updateOrderStatus(${o.id}, this.value)">
        <option value="Pending" ${o.status === 'Pending' ? 'selected' : ''}>Pending</option>
        <option value="Shipped" ${o.status === 'Shipped' ? 'selected' : ''}>Shipped</option>
        <option value="Delivered" ${o.status === 'Delivered' ? 'selected' : ''}>Delivered</option>
      </select>
    </div>
  `).join('');
}

function updateOrderStatus(id, status) {
  const orders = getOrders();
  const order = orders.find(o => o.id === id);
  if (order) {
    order.status = status;
    localStorage.setItem('orders', JSON.stringify(orders));
    displayOrders();
  }
}