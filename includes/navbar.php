<nav class="top-navbar d-flex align-items-center justify-content-between px-4">
    <!-- Mobile toggle -->
    <button class="btn btn-link d-lg-none p-0 me-3 text-dark" id="sidebarToggle">
        <i class="bi bi-list fs-4"></i>
    </button>

    <!-- Spacer -->
    <div class="flex-grow-1"></div>

    <!-- Search -->
    <div class="search-wrapper me-3">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0">
                <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" class="form-control border-start-0 ps-0" placeholder="Search..." id="globalSearch">
        </div>
    </div>

    <!-- Notifications -->
    <div class="d-flex align-items-center gap-3">
        <div class="position-relative">
            <button class="btn btn-link text-dark p-0" id="notifBtn" data-bs-toggle="dropdown">
                <i class="bi bi-bell fs-5"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px;">5</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow" style="width:300px;">
                <li><h6 class="dropdown-header">Notifications</h6></li>
                <li><a class="dropdown-item small py-2" href="#">New announcement in ITEC 106</a></li>
                <li><a class="dropdown-item small py-2" href="#">Forum reply from Mark Santos</a></li>
                <li><a class="dropdown-item small py-2" href="#">Assignment due in 2 days</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-center small text-primary" href="#">View all notifications</a></li>
            </ul>
        </div>

        <!-- Account dropdown -->
        <div class="dropdown">
            <button class="btn btn-link p-0 text-dark" data-bs-toggle="dropdown">
                <div class="avatar-circle-sm d-flex align-items-center justify-content-center">
                    <i class="bi bi-person-fill"></i>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><h6 class="dropdown-header">
                    <?= isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Ryan Pol Malabanan' ?>
                </h6></li>
                <li><a class="dropdown-item small" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item small text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
