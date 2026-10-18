<?php
// app/Views/layouts/auth.php
// $content view will be included via renderPartial (we will include the view directly)
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>MediCare HMS - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-light">
  <div class="container">
    <div class="row justify-content-center mt-5">
      <div class="col-md-6">
        <?php
        // include requested view
        // $viewFile is set by BaseController->renderPartial
        require $viewFile;
        ?>
      </div>
    </div>
  </div>
</body>
</html>
