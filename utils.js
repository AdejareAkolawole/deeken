function getProducts() {
  return JSON.parse(localStorage.getItem('products')) || [
    { id: 1, name: 'Smartphone X1', price: 299.99, image: 'images/iphone 16.jpg', category: 'electronics', description: 'High-performance smartphone with OLED display.', rating: 4.5 },
    { id: 2, name: 'T-Shirt Classic', price: 19.99, image: 'images/Tshirt.jpg', category: 'fashion', description: 'Comfortable cotton t-shirt.', rating: 4.0 },
    { id: 3, name: 'Wireless Earbuds', price: 59.99, image: 'images/earbud.jpg', category: 'electronics', description: 'True wireless earbuds with noise cancellation.', rating: 4.2 },
    { id: 4, name: 'Denim Jacket', price: 49.99, image: 'images/jacket.jpg', category: 'fashion', description: 'Stylish denim jacket for all seasons.', rating: 4.3 },
    { id: 5, name: 'Smartwatch Pro', price: 199.99, image: 'images/smartwatch_pro.jpg', category: 'electronics', description: 'Fitness tracker with heart rate monitor.', rating: 4.7 },
    { id: 6, name: 'Sneakers Air', price: 79.99, image: 'images/sneakers_air.jpg', category: 'fashion', description: 'Lightweight and breathable sneakers.', rating: 4.4 },
    { id: 7, name: 'Laptop Ultra', price: 999.99, image: 'images/laptop_ultra.jpg', category: 'electronics', description: 'High-performance laptop for professionals.', rating: 4.8 },
    { id: 8, name: 'Sunglasses Retro', price: 29.99, image: 'images/sunglasses_retro.jpg', category: 'fashion', description: 'Vintage-style sunglasses.', rating: 4.1 },
    { id: 9, name: 'Bluetooth Speaker', price: 39.99, image: 'images/bluetooth_speaker.jpg', category: 'electronics', description: 'Portable speaker with deep bass.', rating: 4.3 },
    { id: 10, name: 'Leather Belt', price: 24.99, image: 'images/leather_belt.jpg', category: 'fashion', description: 'Premium leather belt.', rating: 4.0 },
    { id: 11, name: 'Gaming Console', price: 399.99, image: 'images/gaming_console.jpg', category: 'electronics', description: 'Next-gen gaming console.', rating: 4.6 },
    { id: 12, name: 'Dress Elegant', price: 59.99, image: 'images/dress_elegant.jpg', category: 'fashion', description: 'Elegant dress for formal occasions.', rating: 4.5 },
    { id: 13, name: 'Smart TV 4K', price: 499.99, image: 'images/smart_tv_4k.jpg', category: 'electronics', description: '4K Ultra HD smart TV.', rating: 4.7 },
    { id: 14, name: 'Backpack Modern', price: 34.99, image: 'images/backpack_modern.jpg', category: 'fashion', description: 'Stylish and durable backpack.', rating: 4.2 },
    { id: 15, name: 'Headphones Over-Ear', price: 89.99, image: 'images/headphones_overear.jpg', category: 'electronics', description: 'Comfortable over-ear headphones.', rating: 4.4 },
    { id: 16, name: 'Casual Shirt', price: 29.99, image: 'images/casual_shirt.jpg', category: 'fashion', description: 'Casual shirt for everyday wear.', rating: 4.0 },
    { id: 17, name: 'Tablet Pro', price: 349.99, image: 'images/tablet_pro.jpg', category: 'electronics', description: 'High-performance tablet.', rating: 4.5 },
    { id: 18, name: 'Scarf Wool', price: 19.99, image: 'images/scarf_wool.jpg', category: 'fashion', description: 'Warm wool scarf.', rating: 4.1 },
    { id: 19, name: 'Smart Home Hub', price: 129.99, image: 'images/smart_home_hub.jpg', category: 'electronics', description: 'Control your smart home devices.', rating: 4.3 },
    { id: 20, name: 'Jeans Slim', price: 39.99, image: 'images/jeans_slim.jpg', category: 'fashion', description: 'Slim-fit jeans.', rating: 4.2 },
    { id: 21, name: 'Air Fryer', price: 79.99, image: 'images/air_fryer.jpg', category: 'home', description: 'Healthy cooking with air fryer.', rating: 4.4 },
    { id: 22, name: 'Coffee Maker', price: 49.99, image: 'images/coffee_maker.jpg', category: 'home', description: 'Automatic coffee maker.', rating: 4.3 },
    { id: 23, name: 'Vacuum Cleaner', price: 149.99, image: 'images/vacuum_cleaner.jpg', category: 'home', description: 'Powerful vacuum cleaner.', rating: 4.5 },
    { id: 24, name: 'Bedding Set', price: 69.99, image: 'images/bedding_set.jpg', category: 'home', description: 'Comfortable bedding set.', rating: 4.2 },
    { id: 25, name: 'Desk Lamp', price: 29.99, image: 'images/desk_lamp.jpg', category: 'home', description: 'Adjustable desk lamp.', rating: 4.0 },
    { id: 26, name: 'Fitness Tracker', price: 59.99, image: 'images/fitness_tracker.jpg', category: 'sports', description: 'Track your fitness goals.', rating: 4.3 },
    { id: 27, name: 'Yoga Mat', price: 24.99, image: 'images/yoga_mat.jpg', category: 'sports', description: 'Non-slip yoga mat.', rating: 4.1 },
    { id: 28, name: 'Dumbbell Set', price: 49.99, image: 'images/dumbbell_set.jpg', category: 'sports', description: 'Adjustable dumbbell set.', rating: 4.4 },
    { id: 29, name: 'Running Shoes', price: 69.99, image: 'images/running_shoes.jpg', category: 'sports', description: 'Lightweight running shoes.', rating: 4.5 },
    { id: 30, name: 'Sports Bottle', price: 14.99, image: 'images/sports_bottle.jpg', category: 'sports', description: 'Insulated sports bottle.', rating: 4.0 },
    { id: 31, name: 'Face Cream', price: 19.99, image: 'images/face_cream.jpg', category: 'beauty', description: 'Hydrating face cream.', rating: 4.2 },
    { id: 32, name: 'Lipstick Matte', price: 12.99, image: 'images/lipstick_matte.jpg', category: 'beauty', description: 'Long-lasting matte lipstick.', rating: 4.3 },
    { id: 33, name: 'Perfume Floral', price: 39.99, image: 'images/perfume_floral.jpg', category: 'beauty', description: 'Floral fragrance perfume.', rating: 4.5 },
    { id: 34, name: 'Hair Dryer', price: 29.99, image: 'images/hair_dryer.jpg', category: 'beauty', description: 'Professional hair dryer.', rating: 4.1 },
    { id: 35, name: 'Makeup Brush Set', price: 24.99, image: 'images/makeup_brush_set.jpg', category: 'beauty', description: 'Complete makeup brush set.', rating: 4.4 },
    { id: 36, name: 'Smart Speaker', price: 89.99, image: 'images/smart_speaker.jpg', category: 'electronics', description: 'Voice-activated smart speaker.', rating: 4.6 },
    { id: 37, name: 'Hoodie Cozy', price: 34.99, image: 'images/hoodie_cozy.jpg', category: 'fashion', description: 'Warm and cozy hoodie.', rating: 4.2 },
    { id: 38, name: 'Power Bank', price: 29.99, image: 'images/power_bank.jpg', category: 'electronics', description: 'High-capacity power bank.', rating: 4.3 },
    { id: 39, name: 'Watch Classic', price: 49.99, image: 'images/watch_classic.jpg', category: 'fashion', description: 'Classic analog watch.', rating: 4.1 },
    { id: 40, name: 'Router WiFi', price: 69.99, image: 'images/router_wifi.jpg', category: 'electronics', description: 'High-speed WiFi router.', rating: 4.4 },
    { id: 41, name: 'Blender', price: 39.99, image: 'images/blender.jpg', category: 'home', description: 'Powerful kitchen blender.', rating: 4.2 },
    { id: 42, name: 'Curtain Set', price: 29.99, image: 'images/curtain_set.jpg', category: 'home', description: 'Elegant curtain set.', rating: 4.0 },
    { id: 43, name: 'Kettle Electric', price: 24.99, image: 'images/kettle_electric.jpg', category: 'home', description: 'Fast-boiling electric kettle.', rating: 4.3 },
    { id: 44, name: 'Treadmill', price: 299.99, image: 'images/treadmill.jpg', category: 'sports', description: 'Home treadmill for fitness.', rating: 4.5 },
    { id: 45, name: 'Sports Bra', price: 19.99, image: 'images/sports_bra.jpg', category: 'sports', description: 'Supportive sports bra.', rating: 4.2 },
    { id: 46, name: 'Foundation', price: 14.99, image: 'images/foundation.jpg', category: 'beauty', description: 'Long-wear foundation.', rating: 4.3 },
    { id: 47, name: 'Mascara', price: 9.99, image: 'images/mascara.jpg', category: 'beauty', description: 'Volumizing mascara.', rating: 4.1 },
    { id: 48, name: 'Gaming Mouse', price: 29.99, image: 'images/gaming_mouse.jpg', category: 'electronics', description: 'Precision gaming mouse.', rating: 4.4 },
    { id: 49, name: 'Hat Trendy', price: 19.99, image: 'images/hat_trendy.jpg', category: 'fashion', description: 'Trendy bucket hat.', rating: 4.0 },
    { id: 50, name: 'Smart Light', price: 24.99, image: 'images/smart_light.jpg', category: 'home', description: 'Color-changing smart light.', rating: 4.3 }
  ];
}

function getCart() {
  return JSON.parse(localStorage.getItem('cart')) || [];
}

function addToCart(id) {
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

function updateCartCount() {
  const cart = getCart();
  const count = cart.reduce((sum, item) => sum + item.quantity, 0);
  document.querySelectorAll('#cartCount').forEach(el => el.textContent = count);
}

function getCurrentUser() {
  return JSON.parse(localStorage.getItem('currentUser'));
}

function updateAuthLink() {
  const user = getCurrentUser();
  document.querySelectorAll('#authLink').forEach(el => {
    el.innerHTML = user ? '<i class="fas fa-sign-out-alt"></i> Logout' : '<i class="fas fa-sign-in-alt"></i> Login';
    el.href = user ? '#' : 'login.html';
    if (user) {
      el.onclick = () => {
        localStorage.removeItem('currentUser');
        window.location.href = 'index.html';
      };
    }
  });
}

function getUsers() {
  return JSON.parse(localStorage.getItem('users')) || [{ email: 'admin@deeken.com', password: 'admin', isAdmin: true }];
}

function getOrders() {
  return JSON.parse(localStorage.getItem('orders')) || [];
}

function getReviews() {
  return JSON.parse(localStorage.getItem('reviews')) || [];
}