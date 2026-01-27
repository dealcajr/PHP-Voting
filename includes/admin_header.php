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
</head>
<body>
    <header class="admin-header">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><?php echo APP_NAME; ?> Admin</a>
            <div class="d-flex">
                <a href="../logout.php" class="btn btn-outline-light">Logout</a>
            </div>
        </div>
    </header>
    <div class="admin-wrapper">
