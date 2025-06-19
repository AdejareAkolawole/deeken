function getProducts() {
  return JSON.parse(localStorage.getItem('products')) || [
    { id: 1, name: 'Landon & Co', price: 299.99, image: 'https://cdn.rona.ca/images/41355073_L.jpg', category: 'electronics', description: 'Landon & Co. Bondi Patio Set with Steel Frame and Beige Cushions - 3-Piece', rating: 4.5 },
    { id: 2, name: 'Wood', price: 19.99, image: 'https://cdn.rona.ca/images/84895043_L.jpg', category: 'fashion', description: '4-in x 4-in x 8-ft Brown Pressure Treated Wood', rating: 4.0 },
    { id: 3, name: 'Pro Mix', price: 59.99, image: 'https://cdn.rona.ca/images/89465021_L.jpg', category: 'electronics', description: 'PRO-MIX Premium Organic Vegetable and Herb Mix - 112-L', rating: 4.2 },
    { id: 4, name: 'IRWIN', price: 49.99, image: 'https://cdn.rona.ca/images/02445557_M.jpg', category: 'fashion', description: 'IRWIN 3-oz Sledge Hammer with Fiberglass Short Handle and Forged Steel Head', rating: 4.3 },
    { id: 5, name: 'LOUISVILLE', price: 199.99, image: 'https://cdn.rona.ca/images/54515082_M.jpg', category: 'electronics', description: 'Louisville 6-ft Fiberglass 250-lb Capacity Yellow Step Ladder', rating: 4.7 },
    { id: 6, name: 'DEWALT', price: 79.99, image: 'https://cdn.rona.ca/images/45585006_M.jpg', category: 'fashion', description: 'DEWALT 4 HP 5-Gal Wet/Dry Portable Vacuum', rating: 4.4 },
    { id: 7, name: 'CRAFTSMAN', price: 999.99, image: 'https://cdn.rona.ca/images/45585051_M.jpg', category: 'electronics', description: 'CRAFTSMAN 3-gal 1/3 HP Motor Air Compressor and Brad Nailer Kit', rating: 4.8 },
    { id: 8, name: 'CRAFTSMAN', price: 29.99, image: 'https://cdn.rona.ca/images/00276990_M.jpg', category: 'fashion', description: 'CRAFTSMAN Double-Drawer Tool Unit - Black and Red', rating: 4.1 },
    { id: 9, name: 'VALU+', price: 39.99, image: 'https://cdn.rona.ca/images/90195093_M.jpg', category: 'electronics', description: 'VALU+ Plier and Wrench Set - 6 Piece', rating: 4.3 },
    { id: 10, name: 'FRIGIDAIRE GALLERY', price: 24.99, image: 'https://cdn.rona.ca/images/10355633_M.jpg', category: 'fashion', description: 'Frigidaire Gallery 30-in Wall Oven and Microwave Combination Steam and Self-', rating: 4.0 },
    { id: 11, name: 'Gaming Console', price: 399.99, image: 'https://via.placeholder.com/150?text=Gaming_Console', category: 'electronics', description: 'Next-gen gaming console.', rating: 4.6 },
    { id: 12, name: 'Dress Elegant', price: 59.99, image: 'https://via.placeholder.com/150?text=Dress_Elegant', category: 'fashion', description: 'Elegant dress for formal occasions.', rating: 4.5 },
    { id: 13, name: 'Smart TV 4K', price: 499.99, image: 'https://via.placeholder.com/150?text=Smart_TV_4K', category: 'electronics', description: '4K Ultra HD smart TV.', rating: 4.7 },
    { id: 14, name: 'Backpack Modern', price: 34.99, image: 'https://via.placeholder.com/150?text=Backpack_Modern', category: 'fashion', description: 'Stylish and durable backpack.', rating: 4.2 },
    { id: 15, name: 'Headphones Over-Ear', price: 89.99, image: 'https://via.placeholder.com/150?text=Headphones_OverEar', category: 'electronics', description: 'Comfortable over-ear headphones.', rating: 4.4 },
    { id: 16, name: 'Casual Shirt', price: 29.99, image: 'https://via.placeholder.com/150?text=Casual_Shirt', category: 'fashion', description: 'Casual shirt for everyday wear.', rating: 4.0 },
    { id: 17, name: 'Tablet Pro', price: 349.99, image: 'https://via.placeholder.com/150?text=Tablet_Pro', category: 'electronics', description: 'High-performance tablet.', rating: 4.5 },
    { id: 18, name: 'Scarf Wool', price: 19.99, image: 'https://via.placeholder.com/150?text=Scarf_Wool', category: 'fashion', description: 'Warm wool scarf.', rating: 4.1 },
    { id: 19, name: 'Smart Home Hub', price: 129.99, image: 'https://via.placeholder.com/150?text=Smart_Home_Hub', category: 'electronics', description: 'Control your smart home devices.', rating: 4.3 },
    { id: 20, name: 'Jeans Slim', price: 39.99, image: 'https://via.placeholder.com/150?text=Jeans_Slim', category: 'fashion', description: 'Slim-fit jeans.', rating: 4.2 },
    { id: 21, name: 'Air Fryer', price: 79.99, image: 'https://via.placeholder.com/150?text=Air_Fryer', category: 'home', description: 'Healthy cooking with air fryer.', rating: 4.4 },
    { id: 22, name: 'Coffee Maker', price: 49.99, image: 'https://via.placeholder.com/150?text=Coffee_Maker', category: 'home', description: 'Automatic coffee maker.', rating: 4.3 },
    { id: 23, name: 'Vacuum Cleaner', price: 149.99, image: 'https://via.placeholder.com/150?text=Vacuum_Cleaner', category: 'home', description: 'Powerful vacuum cleaner.', rating: 4.5 },
    { id: 24, name: 'Bedding Set', price: 69.99, image: 'https://via.placeholder.com/150?text=Bedding_Set', category: 'home', description: 'Comfortable bedding set.', rating: 4.2 },
    { id: 25, name: 'Desk Lamp', price: 29.99, image: 'https://via.placeholder.com/150?text=Desk_Lamp', category: 'home', description: 'Adjustable desk lamp.', rating: 4.0 },
    { id: 26, name: 'Fitness Tracker', price: 59.99, image: 'https://via.placeholder.com/150?text=Fitness_Tracker', category: 'sports', description: 'Track your fitness goals.', rating: 4.3 },
    { id: 27, name: 'Yoga Mat', price: 24.99, image: 'https://via.placeholder.com/150?text=Yoga_Mat', category: 'sports', description: 'Non-slip yoga mat.', rating: 4.1 },
    { id: 28, name: 'Dumbbell Set', price: 49.99, image: 'https://via.placeholder.com/150?text=Dumbbell_Set', category: 'sports', description: 'Adjustable dumbbell set.', rating: 4.4 },
    { id: 29, name: 'Running Shoes', price: 69.99, image: 'https://via.placeholder.com/150?text=Running_Shoes', category: 'sports', description: 'Lightweight running shoes.', rating: 4.5 },
    { id: 30, name: 'Sports Bottle', price: 14.99, image: 'https://via.placeholder.com/150?text=Sports_Bottle', category: 'sports', description: 'Insulated sports bottle.', rating: 4.0 },
    { id: 31, name: 'Face Cream', price: 19.99, image: 'https://via.placeholder.com/150?text=Face_Cream', category: 'beauty', description: 'Hydrating face cream.', rating: 4.2 },
    { id: 32, name: 'Lipstick Matte', price: 12.99, image: 'https://via.placeholder.com/150?text=Lipstick_Matte', category: 'beauty', description: 'Long-lasting matte lipstick.', rating: 4.3 },
    { id: 33, name: 'Perfume Floral', price: 39.99, image: 'https://via.placeholder.com/150?text=Perfume_Floral', category: 'beauty', description: 'Floral fragrance perfume.', rating: 4.5 },
    { id: 34, name: 'Hair Dryer', price: 29.99, image: 'https://via.placeholder.com/150?text=Hair_Dryer', category: 'beauty', description: 'Professional hair dryer.', rating: 4.1 },
    { id: 35, name: 'Makeup Brush Set', price: 24.99, image: 'https://via.placeholder.com/150?text=Makeup_Brush_Set', category: 'beauty', description: 'Complete makeup brush set.', rating: 4.4 },
    { id: 36, name: 'Smart Speaker', price: 89.99, image: 'https://via.placeholder.com/150?text=Smart_Speaker', category: 'electronics', description: 'Voice-activated smart speaker.', rating: 4.6 },
    { id: 37, name: 'Hoodie Cozy', price: 34.99, image: 'https://via.placeholder.com/150?text=Hoodie_Cozy', category: 'fashion', description: 'Warm and cozy hoodie.', rating: 4.2 },
    { id: 38, name: 'Power Bank', price: 29.99, image: 'https://via.placeholder.com/150?text=Power_Bank', category: 'electronics', description: 'High-capacity power bank.', rating: 4.3 },
    { id: 39, name: 'Watch Classic', price: 49.99, image: 'https://via.placeholder.com/150?text=Watch_Classic', category: 'fashion', description: 'Classic analog watch.', rating: 4.1 },
    { id: 40, name: 'Router WiFi', price: 69.99, image: 'https://via.placeholder.com/150?text=Router_WiFi', category: 'electronics', description: 'High-speed WiFi router.', rating: 4.4 },
    { id: 41, name: 'Blender', price: 39.99, image: 'https://via.placeholder.com/150?text=Blender', category: 'home', description: 'Powerful kitchen blender.', rating: 4.2 },
    { id: 42, name: 'Curtain Set', price: 29.99, image: 'https://via.placeholder.com/150?text=Curtain_Set', category: 'home', description: 'Elegant curtain set.', rating: 4.0 },
    { id: 43, name: 'Kettle Electric', price: 24.99, image: 'https://via.placeholder.com/150?text=Kettle_Electric', category: 'home', description: 'Fast-boiling electric kettle.', rating: 4.3 },
    { id: 44, name: 'Treadmill', price: 299.99, image: 'https://via.placeholder.com/150?text=Treadmill', category: 'sports', description: 'Home treadmill for fitness.', rating: 4.5 },
    { id: 45, name: 'Sports Bra', price: 19.99, image: 'https://via.placeholder.com/150?text=Sports_Bra', category: 'sports', description: 'Supportive sports bra.', rating: 4.2 },
    { id: 46, name: 'Foundation', price: 14.99, image: 'https://via.placeholder.com/150?text=Foundation', category: 'beauty', description: 'Long-wear foundation.', rating: 4.3 },
    { id: 47, name: 'Mascara', price: 9.99, image: 'https://via.placeholder.com/150?text=Mascara', category: 'beauty', description: 'Volumizing mascara.', rating: 4.1 },
    { id: 48, name: 'Gaming Mouse', price: 29.99, image: 'https://via.placeholder.com/150?text=Gaming_Mouse', category: 'electronics', description: 'Precision gaming mouse.', rating: 4.4 },
    { id: 49, name: 'Hat Trendy', price: 19.99, image: 'https://via.placeholder.com/150?text=Hat_Trendy', category: 'fashion', description: 'Trendy bucket hat.', rating: 4.0 },
    { id: 50, name: 'Smart Light', price: 24.99, image: 'https://via.placeholder.com/150?text=Smart_Light', category: 'home', description: 'Color-changing smart light.', rating: 4.3 }
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