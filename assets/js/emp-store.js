let selectedCategories = [];
let searchTimer;

// Filter state
const currentFilters = {
    search: '',
    category_id: 'all',
    page: 1,
    limit: 12
};


let currentCartItems = []; // To hold items for the order submission

/**
 * INITIAL LOAD
 */
$(document).ready(function () {
    // 1. Get today's date
    let targetDate = new Date();

    // 2. Add 7 days to it
    targetDate.setDate(targetDate.getDate() + 7);

    // 3. Format to YYYY-MM-DD
    let yyyy = targetDate.getFullYear();
    let mm = String(targetDate.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
    let dd = String(targetDate.getDate()).padStart(2, '0');

    let minDateString = `${yyyy}-${mm}-${dd}`; // e.g., "2026-06-10"

    // 4. Inject the min attribute into your input field
    $('#expected-date').attr('min', minDateString);
    loadProducts();
    loadCartItems()
    // Also load your categories list here if they aren't hardcoded
});

/**
 * SEARCH INPUT (With 300ms Debounce)
 */
$('#store-search').on('input', function () {
    clearTimeout(searchTimer);
    currentFilters.search = $(this).val();
    currentFilters.page = 1; // Reset to page 1 on new search

    searchTimer = setTimeout(function () {
        loadProducts();
    }, 300);
});

/**
 * MULTI CATEGORY SELECT[cite: 1]
 */
$(document).on('click', '#category-filters li', function () {
    const cat = $(this).data('cat');

    if (cat === 'all') {
        selectedCategories = [];
        $('#category-filters li').removeClass('active');
        $(this).addClass('active');
    } else {
        $('#category-filters li[data-cat="all"]').removeClass('active');
        $(this).toggleClass('active');

        if (selectedCategories.includes(cat)) {
            selectedCategories = selectedCategories.filter(c => c !== cat);
        } else {
            selectedCategories.push(cat);
        }
    }

    // If nothing selected, default back to 'all'
    currentFilters.category_id = selectedCategories.length > 0 ? selectedCategories : 'all';
    currentFilters.page = 1;
    loadProducts();
});

/**
 * LOAD PRODUCTS[cite: 4]
 */
function loadProducts() {
    // Optional: Show a loader in the grid while fetching
    $('#product-grid').html('<div class="text-center w-100">Loading items...</div>');

    App.ajax({
        url: '/items/get_store_items.php',
        method: 'GET',
        data: currentFilters,
        onSuccess: function (response) {
            let html = '';

            if (response.items.length === 0) {
                $('#product-grid').html('<p class="text-muted">No products found matching your criteria.</p>');
                return;
            }

            response.items.forEach(item => {
                html += `
                    <div class="product-card" onclick="openProductModal(${item.id})">
                        <img src="${item.image_path || 'assets/img/placeholder.png'}" class="pc-img">
                        <div class="pc-body">
                            <span class="pc-cat">${item.category_names || 'General'}</span>
                            <h4 class="pc-title">${item.name}</h4>
                            <div class="pc-price">$${parseFloat(item.price).toFixed(2)}</div>
                        </div>
                    </div>
                `;
            });

            $('#product-grid').html(html);
        }
    });
}

/**
 * OPEN CART[cite: 1]
 */
$('#cart-btn').on('click', function () {
    loadCartItems();
    App.modal.open('cart-modal');
});

/**
 * CLEAR FILTERS[cite: 1]
 */
$('#clear-filters').on('click', function () {
    selectedCategories = [];
    currentFilters.search = '';
    currentFilters.category_id = 'all';
    currentFilters.page = 1;

    $('#store-search').val('');
    $('#category-filters li').removeClass('active');
    $('#category-filters li[data-cat="all"]').addClass('active');

    loadProducts();
});

/**
 * LOAD CATEGORIES DYNAMICALLY
 * Fetches categories from the API and populates the filter bar.
 */
function loadCategories() {
    App.ajax({
        url: '/categories/list.php',
        method: 'GET',
        onSuccess: function (categories) {
            // 1. Start with the "All" button
            let html = `<li class="active" data-cat="all">All Items</li>`;

            // 2. Loop through each category from the database
            categories.forEach(cat => {
                html += `
                    <li data-cat="${cat.id}" title="${cat.description || ''}">
                        ${cat.name}
                    </li>
                `;
            });

            // 3. Inject into the list
            $('#category-filters').html(html);
        },
        onError: function () {
            $('#category-filters').html('<li class="text-danger">Error loading categories</li>');
        }
    });
}


loadCategories()
// Note: Ensure openProductModal() is defined to handle clicking a card
/**
 * OPEN PRODUCT MODAL
 * Fetches item details from the API and populates the modal fields.
 */
function openProductModal(id) {
    App.ajax({
        url: '/items/get.php', // Matches your provided PHP path
        method: 'GET',
        data: { id: id },
        onSuccess: function (item) {
            // 1. Populate Modal Fields
            $('#modal-img').attr('src', item.image_path || 'assets/img/placeholder.png');
            $('#modal-name').text(item.name);
            $('#modal-cats').text(item.category_names || 'General');
            $('#item-code').text(item.item_code || '-');
            $('#modal-price').text('$' + parseFloat(item.price).toFixed(2));
            $('#modal-desc').text(item.description || 'No description available.');

            // 2. Reset quantity to 1 every time modal opens
            $('#purchase-qty').val(1);

            // 3. Attach click event to the "Add to Cart" button
            // We use .off() first to prevent multiple event handlers if opened multiple times
            $('#btn-confirm-add').off('click').on('click', function () {
                const qty = $('#purchase-qty').val();
                addToCart(item.id, qty, item.name);
            });

            // 4. Show the modal using your framework's method
            App.modal.open('product-modal');
        }
    });
}

/**
 * ADJUST QUANTITY
 * Handles the + and - buttons in the modal.
 */
function adjustQty(val) {
    let currentQty = parseInt($('#purchase-qty').val()) || 1;
    currentQty += val;

    // Prevent quantity from going below 1
    if (currentQty < 1) currentQty = 1;

    $('#purchase-qty').val(currentQty);
}

/**
 * ADD TO CART
 * Sends the request to your cart API.
 */
function addToCart(itemId, quantity, itemName) {
    App.ajax({
        url: '/cart/add.php',
        method: 'POST',
        data: {
            item_id: itemId,
            quantity: quantity
        },
        onSuccess: function (response) {
            App.toast.success('success', itemName + ' added to cart!');
            App.modal.close('product-modal');

            // Update the cart count in the header[cite: 2]
            let currentCount = parseInt($('#cart-count').text()) || 0;
            $('#cart-count').text(currentCount + parseInt(quantity));
        }
    });
}

function loadCartItems() {
    $('#cart-items').html('<div class="text-center p-3">Loading your cart...</div>');

    App.ajax({
        url: '/cart/cart-items.php',
        method: 'GET',
        onSuccess: function (items) {
            currentCartItems = items; // Store for order placement
            let html = '';
            let grandTotal = 0;
            let totalBadgeCount = 0; // Tracks precise accumulated sum of pieces

            if (items.length === 0) {
                $('#cart-items').html('<p class="text-muted text-center">Your cart is empty.</p>');
                $('#place-order').prop('disabled', true);
                $('#cart-count').text('0');
                return;
            }

            $('#place-order').prop('disabled', false);

            items.forEach(item => {
                const subtotal = parseFloat(item.price) * parseInt(item.quantity);
                grandTotal += subtotal;
                totalBadgeCount += parseInt(item.quantity);

                html += `
                    <div class="cart-item-row d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <div>
                            <img src="${item.image_path || 'assets/img/placeholder.png'}" style="width:50px; height:50px; object-fit:cover; border-radius:4px;">
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold">${item.name}</div>
                            <div class="d-flex align-items-center mt-1">
                                <span class="text-muted me-2" style="font-size:0.85rem">$${parseFloat(item.price).toFixed(2)} × </span>
                                <input type="number" 
                                       class="form-control form-control-sm text-center cart-qty-input" 
                                       style="width: 70px; padding: 2px 5px;" 
                                       value="${item.quantity}" 
                                       min="1" 
                                       onchange="updateCartQty(${item.id}, this.value)">
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">$${subtotal.toFixed(2)}</div>
                            <button class="btn btn-sm text-danger p-0 mt-1" onclick="removeFromCart(${item.id})">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                `;
            });

            html += `
                <div class="d-flex justify-content-between mt-3 pt-2 border-top">
                    <h4 class="mb-0">Total:</h4>
                    <h4 class="text-primary mb-0">$${grandTotal.toFixed(2)}</h4>
                </div>
            `;

            $('#cart-items').html(html);
            $('#cart-count').text(totalBadgeCount); // Sync header badge to aggregate quantities
        }
    });
}


/**
 * UPDATE CART QUANTITY
 * Sends the dynamic adjusted count down mutations endpoints manually
 */
function updateCartQty(cartId, newQty) {
    var qtyParsed = parseInt(newQty);

    // Safeguard verification layer: ensure quantities stay above 0
    if (isNaN(qtyParsed) || qtyParsed < 1) {
        App.toast.warning('Invalid Value', 'Quantity must be 1 or greater.');
        loadCartItems(); // Revert display modification down state parameters safely
        return;
    }

    App.ajax({
        url: '/cart/update.php',
        method: 'POST',
        data: {
            id: cartId,
            quantity: qtyParsed
        },
        onSuccess: function (response) {
            // Re-fetch ledger configurations array to balance totals out live
            loadCartItems();
        },
        onError: function (err) {
            App.toast.danger('Sync Error', err.message || 'Failed to update item count.');
            loadCartItems(); // Revert field back to its server state baseline
        }
    });
}
function removeFromCart(cartId) {
    App.ajax({
        url: '/cart/remove.php',
        method: 'POST',
        data: { id: cartId },
        onSuccess: function () {
            loadCartItems(); // Refresh the list
        }
    });
}


/**
 * PLACE ORDER
 * Gathers dates and the item array to send to the order creation API.
 */
$('#place-order').on('click', function () {
    const orderDate = $('#order-date').val();
    const expectedDate = $('#expected-date').val();



    if (currentCartItems.length === 0) {
        App.toast.error('error', 'Cannot place an empty order.');
        return;
    }

    // 2. Format items to match the backend expectation
    const formattedItems = currentCartItems.map(item => ({
        id: item.item_id,
        price: item.price,
        qty: item.quantity
    }));

    // 3. Send Order Request[cite: 1]
    App.ajax({
        url: '/emp-order/create.php',
        method: 'POST',
        data: {
            o_date: orderDate,
            r_date: expectedDate,
            items: formattedItems
        },
        onSuccess: function (response) {
            App.toast.success('success', 'Order #' + response.order_id + ' placed successfully!');
            App.modal.close('cart-modal');

            // Reset UI
            $('#order-date, #expected-date').val('');
            loadCartItems()
            $('#cart-count').text('0');
        }
    });
});