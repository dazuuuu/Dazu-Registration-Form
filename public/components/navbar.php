<!-- public/components/navbar.php -->

<!-- Clients navbar with bottom navigation -->
<?php
// Check if user is logged in
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

// Where the cart icon links to. Change this if your cart page lives elsewhere.
$cartUrl = '/Realestate/public/store/cart.php';
?>
<nav class="navbar navbar-expand-lg navbar-modern fixed-top">
    <div class="container">
        <a class="navbar-brand" href="/Realestate/public/">
            <i class="fas fa-shield-alt me-2"></i>Realestate
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link" href="/Realestate/public/">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>

                <!-- Cart with live item-count badge -->
                <li class="nav-item">
                    <a class="nav-link cart-link" href="<?php echo htmlspecialchars($cartUrl); ?>" aria-label="Cart">
                        <span class="cart-icon-wrap">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count cart-badge" style="display:none;">0</span>
                        </span>
                        <span class="d-lg-none ms-2">Cart</span>
                    </a>
                </li>

                <?php if ($isLoggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/Realestate/public/profile/">
                            <i class="fas fa-user-circle"></i> Profile
                        </a>
                    </li>
                    <?php if ($role === 'admin' || $role === 'superadmin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/Realestate/public/admin/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/Realestate/public/profile/">
                                <i class="fas fa-id-card"></i> My Profile
                            </a></li>
                            <li><a class="dropdown-item" href="/Realestate/public/profile/#settings">
                                <i class="fas fa-cog"></i> Settings
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/Realestate/public/auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/Realestate/public/auth/login.php">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary-modern text-white px-4 ms-2" href="/Realestate/public/auth/register.php">
                            <i class="fas fa-user-plus"></i> Sign Up
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Add padding to body to account for fixed navbar -->
<style>
    body {
        padding-top: 76px;
    }

    /* Cart icon + count badge */
    .cart-link .cart-icon-wrap {
        position: relative;
        display: inline-block;
        line-height: 1;
    }
    .cart-link .cart-count {
        position: absolute;
        top: -8px;
        right: -10px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        background: #e74c3c;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 18px;
        text-align: center;
        border-radius: 9999px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.25);
    }
    /* On mobile the menu is stacked, so anchor the badge to the icon, not the row */
    @media (max-width: 991.98px) {
        .cart-link .cart-count {
            top: -6px;
            right: auto;
            left: 14px;
        }
    }
</style>

<script>
// Live cart count for the navbar. Self-contained (no globals) so it can't clash
// with the store page's own updateCartCount(); both update the same .cart-count
// elements, so adding an item refreshes this badge automatically.
(function () {
    function applyCount(count) {
        var n = parseInt(count, 10) || 0;
        document.querySelectorAll('.cart-count, .cart-badge').forEach(function (el) {
            el.textContent = n > 99 ? '99+' : n;
            el.style.display = n > 0 ? 'inline-block' : 'none';
        });
    }

    function refreshCart() {
        fetch('/Realestate/public/api/count.php', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success) applyCount(data.count);
            })
            .catch(function () { /* stay silent; badge just stays hidden */ });
    }

    if (document.readyState !== 'loading') {
        refreshCart();
    } else {
        document.addEventListener('DOMContentLoaded', refreshCart);
    }

    // Let other scripts trigger a refresh after add/remove without a page reload:
    //   window.dispatchEvent(new Event('cart:updated'));
    window.addEventListener('cart:updated', refreshCart);
})();
</script>