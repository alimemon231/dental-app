<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/store-style.css">
</head>
<body>
    <div class="app-shell">
        <?php require_once "includes/page-header.php" ?>

        <main class="main-content">
            <div class="page-wrapper">

                <!-- Header Section -->
                <div class="store-header">
                    <div>
                        <h1>Supply Store</h1>
                        <div class="page-header-sub">Request items for your department.</div>
                    </div>
                    <div class="header-actions">
                        <button id="toggle-filter-bar" class="btn btn-light">
                            <i class="fa-solid fa-filter"></i> Filters
                        </button>
                        <button id="cart-btn" class="btn btn-primary">
                            <i class="fa-solid fa-cart-shopping"></i> 
                            Cart (<span id="cart-count">0</span>)
                        </button>
                    </div>
                </div>

                <!-- TOP FILTER BAR -->
                <div id="filter-bar" class="filter-bar" style="display: none;">
                    <div class="filter-content">
                        <div class="filter-item search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="store-search" class="form-control" placeholder="Search items...">
                        </div>

                        <div class="filter-item categories">
                            <ul id="category-filters" class="category-pills">
                                <!-- Categories injected here via JS -->
                            </ul>
                        </div>

                        <div class="filter-actions">
                            <button id="clear-filters" class="btn btn-sm btn-danger">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- PRODUCT GRID -->
                <div class="store-body">
                    <div id="product-grid" class="product-grid"></div>
                </div>

                <!-- PRODUCT DETAIL MODAL (Using your framework structure) -->
                <div class="modal-backdrop" id="product-modal">
                    <div class="modal modal-lg">
                        <div class="modal-header">
                            <div class="modal-title">Product Details</div>
                            <button class="modal-close" data-close-modal="product-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body">
                            <div class="product-detail-flex">
                                <div class="detail-img-wrapper">
                                    <img id="modal-img" src="" style="width:100%; border-radius: 8px;">
                                </div>
                                <div class="detail-info">
                                    <h2 id="modal-name"></h2>
                                    <p id="modal-cats" class="text-muted small"></p>
                                     <p id="item-code" class="text-muted small"></p>
                                    <h3 id="modal-price" class="text-primary"></h3>
                                    <p id="modal-desc"></p>

                                    <div class="qty-input mt-3">
                                        <button class="qty-btn" onclick="adjustQty(-1)">-</button>
                                        <input type="text" id="purchase-qty" value="1" readonly>
                                        <button class="qty-btn" onclick="adjustQty(1)">+</button>
                                    </div>

                                    <button id="btn-confirm-add" class="btn btn-success w-100 mt-2">
                                        Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CART MODAL (Using your framework structure) -->
                <div class="modal-backdrop" id="cart-modal">
                    <div class="modal">
                        <div class="modal-header">
                            <div class="modal-title">Your Cart</div>
                            <button class="modal-close" data-close-modal="cart-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body">
                            <div id="cart-items"></div>
                            
                            <div class="order-form mt-3">
                                <div class="mb-2">
                                    <label class="form-label">Expected Receive Date</label>
                                    <input type="date" id="expected-date" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button id="place-order" class="btn btn-primary w-100">Place Order</button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/emp-store.js"></script>
    <script>

        
        // Toggle the top filter bar visibility
        $('#toggle-filter-bar').on('click', function() {
            $('#filter-bar').slideToggle(200);
        });

       
    </script>
</body>
</html>