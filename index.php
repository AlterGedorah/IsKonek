<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IsKonek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="styles/index.css">
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <aside class="col-2 col-md-3 px-4">
                <!-- Brand -->
                .<div class="container-fluid d-flex flex-row justify-content-center align-items-center gap-3">
                    <a class="navbar-brand" href="#">
                        <img src="images/Icon.png" class="img-fluid rounded-top" alt="" />
                    </a>
                    <h2 class="poppins-regular">IsKonek</h2>
                </div>
                <br>
                <div class="d-flex flex-column ">
                    <!-- Links -->
                    <div class="links-sidebar d-flex flex-column gap-3 flex-grow-1">
                        <ul class="nav justify-content-center flex-column d-flex">
                            <span class="sidebar-span poppins-regular">MAIN</span>
                            <li class="nav-item nav-pills">
                                <a class="nav-link active" href="#" aria-current="page">Subject Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">Announcements</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">Forums</a>
                            </li>
                        </ul>
                        <ul class="nav justify-content-center flex-column d-flex">
                            <span class="sidebar-span poppins-regular">PROJECT</span>
                            <li class="nav-item">
                                <a class="nav-link" href="#">Kanban Board</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">Campus Calendar</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">File Repo</a>
                            </li>
                        </ul>
                    </div>
                    <!-- Profile -->
                    <div class="d-flex flex-row gap-2 align-items-center mb-2 profile-sidebar">
                        <a href="#">
                            <img src="images/logo.png" alt="">
                        </a>
                        <div class="d-flex flex-column gap-0 flex-grow-1 profile-details-sidebar">
                            <!-- Placeholders -->
                            <h5>Name</h5>
                            <p>Role</p>
                            <p>Section</p>
                        </div>
                    </div>
                </div>
            </aside>
            <main class="col px-0">
                <nav class="navbar navbar-expand-md navbar-light bg-light sticky-top">
                    <div class="container d-flex flex-row-reverse">
                        <!-- Search -->
                        <form class="d-flex my-2 my-lg-0">
                            <input class="form-control me-sm-2 search-input" type="text" placeholder="Search" />
                            <button class="btn btn-outline-success my-2 my-sm-0" type="submit">
                                Search
                            </button>
                        </form>
                    </div>
                </nav>

            </main>
        </div>
    </div>
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>

</html>