<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>500 · Server Error</title>
<link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\App::instance()->baseUrl('/assets/css/app.css')) ?>">
</head>
<body class="error-page">
  <div class="error-page__box">
    <div class="error-page__code">500</div>
    <h1>Server error</h1>
    <p><?= htmlspecialchars($msg ?? 'An internal error occurred.') ?></p>
    <a class="btn btn--primary" href="<?= htmlspecialchars(\App\Core\App::instance()->baseUrl('/dashboard')) ?>">Go to dashboard</a>
  </div>
</body>
</html>
