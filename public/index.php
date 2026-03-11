<?php
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StatChat</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="./assets/style.css">
</head>
<body class="bg-light">

  <div class="container-fluid vh-100">
    <div class="row h-100">

      <aside class="col-12 col-md-4 col-lg-3 border-end bg-white d-flex flex-column p-0">
        <div class="p-3 border-bottom">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="m-0 fw-bold">StatChat</h3>
            <?php if ($isLoggedIn): ?>
              <button id="logoutBtn" class="btn btn-outline-danger btn-sm">Logout</button>
            <?php endif; ?>
          </div>

          <?php if (!$isLoggedIn): ?>
            <div class="d-grid gap-2">
              <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">
                Login
              </button>
              <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#registerModal">
                Register
              </button>
            </div>
          <?php else: ?>
            <button id="newChatBtn" class="btn btn-primary w-100">+ New Chat</button>
          <?php endif; ?>
        </div>

        <div class="p-3 border-bottom">
          <h6 class="text-muted mb-2">Conversations</h6>
          <ul id="conversationList" class="list-group list-group-flush conversation-list"></ul>
        </div>
      </aside>

      <main class="col-12 col-md-8 col-lg-9 d-flex flex-column p-0">
        <div class="chat-header bg-white border-bottom px-4 py-3">
          <h5 class="mb-0">Chat</h5>
        </div>

        <div id="messages" class="flex-grow-1 p-4 chat-messages bg-body-tertiary">
          <div class="text-muted text-center mt-5" id="emptyState">
            <?php if ($isLoggedIn): ?>
              Select a conversation or start a new one.
            <?php else: ?>
              Please log in or register to start chatting.
            <?php endif; ?>
          </div>
        </div>

        <div class="bg-white border-top p-3">
          <form id="chatForm" class="d-flex gap-2 align-items-end">
            <div class="flex-grow-1">
              <textarea
                id="messageInput"
                class="form-control"
                rows="2"
                placeholder="Type your message..."
                <?php echo !$isLoggedIn ? 'disabled' : ''; ?>
              ></textarea>
            </div>
            <button
              type="submit"
              class="btn btn-primary px-4"
              <?php echo !$isLoggedIn ? 'disabled' : ''; ?>
            >
              Send
            </button>
          </form>
        </div>
      </main>
    </div>
  </div>

  <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content shadow">
        <div class="modal-header">
          <h5 class="modal-title">Login</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="loginError" class="alert alert-danger d-none"></div>
          <div class="mb-3">
            <label for="loginUsername" class="form-label">Username</label>
            <input id="loginUsername" type="text" class="form-control" placeholder="Enter username">
          </div>
          <div class="mb-3">
            <label for="loginPassword" class="form-label">Password</label>
            <input id="loginPassword" type="password" class="form-control" placeholder="Enter password">
          </div>
        </div>
        <div class="modal-footer">
          <button id="loginBtn" class="btn btn-primary">Login</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content shadow">
        <div class="modal-header">
          <h5 class="modal-title">Register</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="registerError" class="alert alert-danger d-none"></div>
          <div class="mb-3">
            <label for="registerUsername" class="form-label">Username</label>
            <input id="registerUsername" type="text" class="form-control" placeholder="Choose a username">
          </div>
          <div class="mb-3">
            <label for="registerPassword" class="form-label">Password</label>
            <input id="registerPassword" type="password" class="form-control" placeholder="Choose a password">
          </div>
        </div>
        <div class="modal-footer">
          <button id="registerBtn" class="btn btn-primary">Register</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    window.APP_STATE = {
      isLoggedIn: <?php echo $isLoggedIn ? 'true' : 'false'; ?>,
      basePath: "/StatChat"
    };
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="./assets/app.js"></script>
</body>
</html>