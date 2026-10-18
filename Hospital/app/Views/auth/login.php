<?php
// app/Views/auth/login.php
?>
<div class="card shadow-sm">
  <div class="card-body">
    <h4 class="card-title mb-3">MediCare HMS — Sign in</h4>
    <div id="alert-placeholder"></div>

    <form id="loginForm" method="post" action="/auth/login" novalidate>
      <div class="mb-3">
        <label for="login" class="form-label">Username or Email</label>
        <input type="text" class="form-control" id="login" name="login" required>
      </div>

      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>

      <div class="d-grid">
        <button type="submit" class="btn btn-primary" id="submitBtn">Sign in</button>
      </div>
    </form>

  </div>
</div>

<script>
$(function () {
  $('#loginForm').on('submit', function (e) {
    e.preventDefault();
    $('#submitBtn').prop('disabled', true).text('Signing in...');
    $('#alert-placeholder').html('');

    $.ajax({
      url: '/auth/login',
      method: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
    }).done(function (resp) {
      if (resp.success) {
        // Redirect to dashboard
        window.location = resp.redirect || '/dashboard';
      } else {
        $('#alert-placeholder').html('<div class="alert alert-danger">' + (resp.message || 'Login failed') + '</div>');
      }
    }).fail(function (xhr) {
      let msg = 'An error occurred';
      if (xhr.responseJSON && xhr.responseJSON.message) {
        msg = xhr.responseJSON.message;
      } else if (xhr.status === 422) {
        msg = 'Please complete all fields';
      } else if (xhr.status === 401) {
        msg = 'Invalid credentials';
      }
      $('#alert-placeholder').html('<div class="alert alert-danger">' + msg + '</div>');
    }).always(function () {
      $('#submitBtn').prop('disabled', false).text('Sign in');
    });
  });
});
</script>
