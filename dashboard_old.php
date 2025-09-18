<!DOCTYPE html>
<html lang="id">
<head>
    <    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-chart-line"></i> Smart Marketing Agent</a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link text-light">
                    <i class="fas fa-store"></i> <?= htmlspecialchars($business['name']) ?>
                </span>
                <a class="nav-link" href="budget.php">Budget Plan</a>
                <a class="nav-link" href="#upload">Upload Data</a>
                <a class="nav-link" href="analysis.php">RFM Analysis</a>
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($user['full_name']) ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit"></i> Profil</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog"></i> Pengaturan</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>et="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Marketing Agent - RFM Analysis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .alert-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <?php
    require_once 'config/database.php';
    require_once 'config/auth.php';
    
    // Require UMKM owner access
    requireAuth(['umkm_owner']);
    
    $user = getCurrentUser();
    $db = getDB();
    
    // Get user's business
    $business = auth()->getUserBusiness($user['id']);
    if (!$business) {
        die('Error: No business associated with your account. Please contact administrator.');
    }
    
    // Get statistics for this business only
    $stats = [];
    $stmt = $db->prepare("SELECT COUNT(*) as total_customers FROM customers WHERE business_id = ?");
    $stmt->execute([$business['id']]);
    $stats['customers'] = $stmt->fetch()['total_customers'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total_transactions FROM transactions WHERE business_id = ?");
    $stmt->execute([$business['id']]);
    $stats['transactions'] = $stmt->fetch()['total_transactions'];
    
    $stmt = $db->prepare("SELECT SUM(amount) as total_revenue FROM transactions WHERE business_id = ?");
    $stmt->execute([$business['id']]);
    $stats['revenue'] = $stmt->fetch()['total_revenue'] ?? 0;
    ?>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-chart-line"></i> Smart Marketing Agent</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="budget.php">Budget Plan</a>
                <a class="nav-link" href="#upload">Upload Data</a>
                <a class="nav-link" href="#analysis">RFM Analysis</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?= number_format($stats['customers']) ?></h4>
                                <p class="mb-0">Total Customers</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?= number_format($stats['transactions']) ?></h4>
                                <p class="mb-0">Total Transactions</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-shopping-cart fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4>Rp <?= number_format($stats['revenue']) ?></h4>
                                <p class="mb-0">Total Revenue</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-money-bill fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-upload"></i> Upload Data Excel</h5>
                    </div>
                    <div class="card-body">
                        <form id="uploadForm" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Pilih File Excel (.xlsx)</label>
                                <input type="file" class="form-control" name="excel_file" accept=".xlsx,.xls" required>
                                <div class="form-text">Format: Customer Name, Email, Transaction Date, Amount</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload & Process
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-robot"></i> AI Content Generator</h5>
                        <small class="text-muted">
                            <i class="fas fa-lightbulb"></i> 
                            Currently in demo mode. Add OpenAI API key to config/openai.php for AI-powered content.
                        </small>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Pilih Segment</label>
                            <select class="form-select" id="segmentSelect">
                                <option value="Champions">Champions</option>
                                <option value="Loyal Customers">Loyal Customers</option>
                                <option value="Potential Loyalists">Potential Loyalists</option>
                                <option value="At Risk">At Risk</option>
                                <option value="Lost Customers">Lost Customers</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-success" onclick="generateContent()">
                            <i class="fas fa-magic"></i> Generate Content
                        </button>
                        <small class="form-text text-muted mt-1">
                            <i class="fas fa-info-circle"></i> 
                            Will use AI if available, otherwise smart template
                        </small>
                        <div id="generatedContent" class="mt-3" style="display:none;">
                            <div class="alert alert-info">
                                <div class="d-flex justify-content-between align-items-start">
                                    <strong>Generated Content:</strong>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyContent()" title="Copy to clipboard">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                                <div id="contentText"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> RFM Segments Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="rfmChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // RFM Chart
        const ctx = document.getElementById('rfmChart').getContext('2d');
        const rfmChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Champions', 'Loyal Customers', 'Potential Loyalists', 'At Risk', 'Lost Customers'],
                datasets: [{
                    data: [30, 25, 20, 15, 10],
                    backgroundColor: ['#28a745', '#007bff', '#ffc107', '#fd7e14', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Copy content to clipboard
        function copyContent() {
            const contentText = document.getElementById('contentText');
            const textToCopy = contentText.innerText || contentText.textContent;
            
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    showCopySuccess();
                }).catch(err => {
                    fallbackCopyMethod(textToCopy);
                });
            } else {
                fallbackCopyMethod(textToCopy);
            }
        }
        
        function fallbackCopyMethod(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showCopySuccess();
            } catch (err) {
                alert('Could not copy text: ' + err);
            }
            
            document.body.removeChild(textArea);
        }
        
        function showCopySuccess() {
            const button = document.querySelector('[onclick="copyContent()"]');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check text-success"></i>';
            setTimeout(() => {
                button.innerHTML = originalHTML;
            }, 2000);
        }

        // Generate AI Content
        function generateContent() {
            const segment = document.getElementById('segmentSelect').value;
            const contentDiv = document.getElementById('generatedContent');
            const contentText = document.getElementById('contentText');
            
            contentText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating content...';
            contentDiv.style.display = 'block';
            
            fetch('api/generate-content.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({segment: segment})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let contentHtml = data.content;
                    
                    // Add source indicator
                    if (data.source === 'dummy') {
                        contentHtml = '<div class="alert alert-warning alert-sm mb-2">' +
                                    '<i class="fas fa-info-circle"></i> <strong>Demo Mode:</strong> ' +
                                    (data.note || 'Using sample content template') +
                                    '</div>' + contentHtml;
                    } else if (data.source === 'openai') {
                        contentHtml = '<div class="alert alert-success alert-sm mb-2">' +
                                    '<i class="fas fa-robot"></i> <strong>AI Generated:</strong> ' +
                                    'Content generated using OpenAI GPT' +
                                    '</div>' + contentHtml;
                    }
                    
                    contentText.innerHTML = contentHtml;
                } else {
                    contentText.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Error: ' + data.error + '</span>';
                }
            })
            .catch(error => {
                contentText.innerHTML = '<span class="text-danger"><i class="fas fa-wifi"></i> Network error occurred</span>';
            });
        }

        // Handle Upload Form
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const button = this.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            button.disabled = true;
            
            fetch('api/upload-excel.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Success: ' + data.message);
                    location.reload(); // Refresh to update statistics
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                alert('Network error occurred');
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        });
    </script>
</body>
</html>
