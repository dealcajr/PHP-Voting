<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin_style.css" rel="stylesheet">
    <style>
        .admin-content .header {
            text-align: center;
            padding: 40px 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .admin-content .nav {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }

        .admin-content .nav a {
            color: #202124;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            background: white;
            border: 1px solid #dfe1e5;
            display: inline-block;
            transition: all 0.2s ease;
        }

        .admin-content .nav a:hover {
            background: #e8eaed;
            border-color: var(--theme-color);
        }

        .admin-content .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .admin-content .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #dfe1e5;
        }

        .admin-content .stat-value {
            font-size: 1.8em;
            font-weight: bold;
            color: #202124;
            display: block;
        }

        .admin-content .stat-label {
            font-size: 0.8em;
            color: #5f6368;
        }

        .admin-content .btn-group {
            margin: 15px 0;
        }

        .admin-content .btn {
            display: block;
            width: 100%;
            padding: 10px 15px;
            margin: 8px 0;
            border: 1px solid #dfe1e5;
            border-radius: 4px;
            text-decoration: none;
            text-align: center;
            font-size: 14px;
            color: #202124;
            background: white;
            transition: all 0.2s ease;
        }

        .admin-content .btn:hover {
            background: #f8f9fa;
            border-color: var(--theme-color);
        }

        .admin-content .btn-success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .admin-content .btn-success:hover {
            background: #c3e6cb;
        }

        .admin-content .btn-danger {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .admin-content .btn-danger:hover {
            background: #f5c6cb;
        }

        .admin-content table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .admin-content th, .admin-content td {
            border: 1px solid #dfe1e5;
            padding: 12px;
            text-align: left;
        }

        .admin-content th {
            background-color: #f8f9fa;
            font-weight: 500;
        }

        .admin-content .candidate-card {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 1px solid #dfe1e5;
            border-radius: 4px;
            margin: 5px 0;
        }

        .admin-content .candidate-photo-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            border: 1px solid #dfe1e5;
        }

        .admin-content .candidate-info-small {
            flex: 1;
        }

        .admin-content .footer {
            text-align: center;
            padding: 20px;
            margin-top: 20px;
            color: #70757a;
            font-size: 0.9em;
            border-top: 1px solid #e0e0e0;
        }

        .admin-content .footer a {
            color: var(--theme-color);
            text-decoration: none;
        }

        .admin-content .footer a:hover {
            text-decoration: underline;
        }

        .admin-content .logout-section {
            text-align: center;
            margin: 20px 0;
        }

        .admin-content .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }

        .admin-content .logout-btn:hover {
            background: #c82333;
        }

        .admin-content .status-indicator {
            background: #d4edda;
            color: #155724;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            margin-bottom: 10px;
            border: 1px solid #c3e6cb;
        }

        .admin-content .status-indicator.inactive {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .admin-content .status-indicator.closed {
            background: #e2e3e5;
            color: #383d41;
            border-color: #d6d8db;
        }

        @media (max-width: 768px) {
            .admin-content .content {
                flex-direction: column;
            }

            .admin-content .nav {
                flex-direction: column;
                align-items: center;
            }

            .admin-content .nav a {
                width: 100%;
                text-align: center;
                margin: 5px 0;
            }

            .admin-content .system-title {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><?php echo SCHOOL_NAME; ?></a>
            <h6>SSLG Voting System - Admin</h6>
            <div class="d-flex">
                <a href="../logout.php" class="btn btn-outline-light">Logout</a>
            </div>
        </div>
    </header>
    <div class="admin-wrapper">
