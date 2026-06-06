<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>404 · Not Found</title>
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\App::instance()->baseUrl('/assets/css/app.css')) ?>">
</head>
<body class="error-page">
  <div class="error-page__box">
    <div class="error-page__code">404</div>
    <h1>Page not found</h1>
    <p>The page you requested does not exist.</p>
    <a class="btn btn--primary" href="<?= htmlspecialchars(\App\Core\App::instance()->baseUrl('/dashboard')) ?>">Go to dashboard</a>
  </div>
</body>
</html>
