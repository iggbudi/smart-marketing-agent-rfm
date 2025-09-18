<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - Smart Marketing Agent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
        }
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        .error-code {
            font-size: 6rem;
            font-weight: bold;
            color: #667eea;
            line-height: 1;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <i class="fas fa-shield-alt error-icon"></i>
        <div class="error-code">403</div>
        <h2 class="mb-3">Access Denied</h2>
        <p class="text-muted mb-4">
            You don't have permission to access this resource. Please contact your administrator if you believe this is an error.
        </p>
        
        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Go Back
            </a>
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-2"></i> Login
            </a>
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fas fa-home me-2"></i> Home
            </a>
        </div>
        
        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="mt-4">
            <small class="text-muted">
                Logged in as: <?= htmlspecialchars($_SESSION['user_role'] ?? 'Unknown') ?>
            </small>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
