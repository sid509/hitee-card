<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hitee API Documentation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Public Sans', sans-serif;
            background-color: #f5f5f9;
            color: #566a7f;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .container {
            background: #fff;
            padding: 3rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 10px 0 rgba(67, 89, 113, 0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .logo {
            margin-bottom: 1.5rem;
        }
        .logo img {
            max-width: 150px;
        }
        h1 {
            color: #566a7f;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        p {
            margin-bottom: 2rem;
            color: #a1acb8;
        }
        .links {
            display: grid;
            gap: 1rem;
        }
        .btn {
            display: block;
            padding: 0.75rem 1.25rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
            border: 1px solid transparent;
        }
        .btn-customer {
            background-color: #696cff;
            color: #fff;
        }
        .btn-customer:hover {
            background-color: #5f61e6;
            transform: translateY(-1px);
        }
        .btn-merchant {
            background-color: #03c3ec;
            color: #fff;
        }
        .btn-merchant:hover {
            background-color: #03b1d5;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="/assets/img/hitee/logo_big.png" alt="Hitee Logo" onerror="this.src='https://hitee.ai/assets/img/logo.png'">
        </div>
        <h1>API Documentation</h1>
        <p>Select the application documentation you want to view</p>
        <div class="links">
            <a href="/docs/customer" class="btn btn-customer">Customer Application Docs</a>
            <a href="/docs/merchant" class="btn btn-merchant">Merchant Application Docs</a>
        </div>
    </div>
</body>
</html>
