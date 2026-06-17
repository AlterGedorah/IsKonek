<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
 
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>File Repository – Iskonek</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
</head>
 
<body>
  <div class="app-wrapper">
 
    <!-- Sidebar overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
 
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
 
    <!-- Main -->
    <div class="main-content">
 
      <!-- Navbar -->
      <?php include 'includes/navbar.php'; ?>
 
      <!-- Page body -->
      <div class="page-body">
 
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <h2 class="hero-greeting mb-1">File Repo 📂</h2>
            <p class="hero-sub text-muted mb-0">Track your coursework tasks and move them as you progress.</p>
          </div>
        </div>
 
        <!-- Dropzone -->
        <div id="dropzone" class="bg-white border border-secondary-subtle border-2 rounded-4 p-4 mb-4 text-center text-secondary d-flex align-items-center justify-content-center w-100" style="height: 160px; transition: background-color 0.2s, border-color 0.2s; cursor: pointer;">
          <div>
            <div class="fs-2 lh-1 mb-2 text-body-tertiary">
              <i class="bi bi-plus-circle"></i>
            </div>
            <p class="fs-6 mb-1 text-body-tertiary fw-semibold">Click to upload or drag and drop files here</p>
            <small class="text-body-tertiary d-block opacity-75" style="font-size: 0.75rem;">PDF, DOCX, PPTX, ZIP, PNG — Max 10MB per file</small>
            <input type="file" id="fileInput" multiple style="display: none;">
          </div>
        </div>
 
        <!-- File table -->
        <div class="bg-white border border-secondary-subtle border-2 rounded-4 overflow-hidden mb-4 w-100">
          <div class="table-responsive">
            <table class="table table-borderless align-middle mb-0 text-nowrap">
              <thead>
                <tr class="border-bottom border-secondary-subtle text-secondary fw-semibold" style="font-size: 0.95rem;">
                  <th scope="col" class="py-3 px-4" style="width: 25%;">Filename</th>
                  <th scope="col" class="py-3 px-4" style="width: 25%;">Uploaded By</th>
                  <th scope="col" class="py-3 px-4" style="width: 15%;">Size</th>
                  <th scope="col" class="py-3 px-4" style="width: 20%;">Date</th>
                  <th scope="col" class="py-3 px-4" style="width: 15%;">Action</th>
                </tr>
              </thead>
              <tbody id="tableBody">
                <tr id="emptyRow" class="border-bottom border-secondary-subtle">
                  <td colspan="5" class="text-center py-5 text-muted">No files uploaded yet.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
 
      </div><!-- /page-body -->
    </div><!-- /main-content -->
  </div><!-- /app-wrapper -->
 
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
 
  <script>
    // ── Sidebar toggle (mobile) ──────────────────────────────────
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');
 
    if (toggleBtn) {
      toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('show');
        overlay?.classList.toggle('show');
      });
    }
    if (overlay) {
      overlay.addEventListener('click', () => {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
      });
    }
 
    // ── Dropzone ─────────────────────────────────────────────────
    const dropzone  = document.getElementById('dropzone');
    const fileInput = document.getElementById('fileInput');
    const tableBody = document.getElementById('tableBody');
    const emptyRow  = document.getElementById('emptyRow');
 
    dropzone.addEventListener('click', () => fileInput.click());
 
    fileInput.addEventListener('change', (e) => handleFiles(e.target.files));
 
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
      }, false);
    });
 
    ['dragenter', 'dragover'].forEach(eventName => {
      dropzone.addEventListener(eventName, () => {
        dropzone.style.backgroundColor = '#e9ecef';
        dropzone.style.borderColor = '#0d6efd';
      }, false);
    });
 
    ['dragleave', 'drop'].forEach(eventName => {
      dropzone.addEventListener(eventName, () => {
        dropzone.style.backgroundColor = '';
        dropzone.style.borderColor = '';
      }, false);
    });
 
    dropzone.addEventListener('drop', (e) => handleFiles(e.dataTransfer.files));
 
    function handleFiles(files) {
      if (files.length > 0 && emptyRow) {
        emptyRow.style.display = 'none';
      }
 
      Array.from(files).forEach(file => {
        const row = document.createElement('tr');
        row.className = 'border-bottom border-secondary-subtle';
 
        const sizeInMB = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
        const currentDate = new Date().toLocaleDateString();
 
        row.innerHTML = `
          <td class="py-4 px-4 fw-medium text-dark">${file.name}</td>
          <td class="py-4 px-4 text-secondary">You</td>
          <td class="py-4 px-4 text-secondary">${sizeInMB}</td>
          <td class="py-4 px-4 text-secondary">${currentDate}</td>
          <td class="py-4 px-4"><button class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">Delete</button></td>
        `;
        tableBody.appendChild(row);
      });
    }
  </script>
 
</body>
</html>