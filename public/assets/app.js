let currentConversationId = null;

const BASE_PATH = window.APP_STATE?.basePath || "";

const conversationList = document.getElementById("conversationList");
const messagesEl = document.getElementById("messages");
const emptyState = document.getElementById("emptyState");
const chatForm = document.getElementById("chatForm");
const messageInput = document.getElementById("messageInput");
const newChatBtn = document.getElementById("newChatBtn");
const logoutBtn = document.getElementById("logoutBtn");

const loginUsername = document.getElementById("loginUsername");
const loginPassword = document.getElementById("loginPassword");
const registerUsername = document.getElementById("registerUsername");
const registerPassword = document.getElementById("registerPassword");

const loginBtn = document.getElementById("loginBtn");
const registerBtn = document.getElementById("registerBtn");

const loginError = document.getElementById("loginError");
const registerError = document.getElementById("registerError");

const loginModalEl = document.getElementById("loginModal");
const registerModalEl = document.getElementById("registerModal");

const loginModal = loginModalEl ? new bootstrap.Modal(loginModalEl) : null;
const registerModal = registerModalEl ? new bootstrap.Modal(registerModalEl) : null;

function setEmptyState(show, text = "") {
  if (!emptyState) return;
  emptyState.classList.toggle("d-none", !show);
  if (text) {
    emptyState.textContent = text;
  }
}

function scrollMessagesToBottom() {
  if (messagesEl) {
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }
}

function appendMessage(role, text) {
  setEmptyState(false);

  const row = document.createElement("div");
  row.className = `message-row ${role}`;

  const bubble = document.createElement("div");
  bubble.className = "message-bubble";
  bubble.textContent = text;

  row.appendChild(bubble);
  messagesEl.appendChild(row);
  scrollMessagesToBottom();
}

function clearMessages() {
  if (messagesEl) {
    messagesEl.innerHTML = "";
  }
}

function renderMessages(messages) {
  clearMessages();

  if (!messages.length) {
    setEmptyState(true, "No messages yet.");
    return;
  }

  setEmptyState(false);
  messages.forEach((msg) => appendMessage(msg.role, msg.content));
}

function setActiveConversation(conversationId) {
  document.querySelectorAll("#conversationList .list-group-item").forEach((item) => {
    item.classList.toggle("active-chat", Number(item.dataset.id) === Number(conversationId));
  });
}

async function api(url, options = {}) {
  const response = await fetch(url, options);

  let data = {};
  try {
    data = await response.json();
  } catch {
    throw new Error(`Request failed: ${response.status}`);
  }

  if (!response.ok) {
    throw new Error(data.error || `Request failed: ${response.status}`);
  }

  return data;
}

function showError(element, message) {
  if (!element) return;
  element.textContent = message;
  element.classList.remove("d-none");
}

function clearError(element) {
  if (!element) return;
  element.textContent = "";
  element.classList.add("d-none");
}

async function loadConversations() {
  if (!window.APP_STATE.isLoggedIn) {
    if (conversationList) {
      conversationList.innerHTML = "";
    }
    return;
  }

  const data = await api(`${BASE_PATH}/api/conversations.php`);
  conversationList.innerHTML = "";

  if (!data.conversations.length) {
    const li = document.createElement("li");
    li.className = "list-group-item text-muted";
    li.textContent = "No conversations yet.";
    conversationList.appendChild(li);
    return;
  }

  data.conversations.forEach((conv) => {
    const li = document.createElement("li");
    li.className = "list-group-item";
    li.textContent = conv.title || `Chat ${conv.id}`;
    li.dataset.id = conv.id;

    li.addEventListener("click", async () => {
      currentConversationId = conv.id;
      setActiveConversation(conv.id);
      await loadMessages(conv.id);
    });

    conversationList.appendChild(li);
  });

  if (currentConversationId) {
    setActiveConversation(currentConversationId);
  }
}

async function loadMessages(conversationId) {
  const data = await api(`${BASE_PATH}/api/messages.php?conversation_id=${conversationId}`);
  renderMessages(data.messages);
}

async function createConversation() {
  if (!window.APP_STATE.isLoggedIn) return;

  const data = await api(`${BASE_PATH}/api/conversations.php`, {
    method: "POST"
  });

  currentConversationId = data.conversation.id;
  clearMessages();
  setEmptyState(true, "Start the conversation.");
  await loadConversations();
  setActiveConversation(currentConversationId);
}

async function login() {
  clearError(loginError);

  const username = loginUsername.value.trim();
  const password = loginPassword.value.trim();

  try {
    await api(`${BASE_PATH}/api/login.php`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({ username, password })
    });

    loginModal?.hide();
    window.location.reload();
  } catch (err) {
    showError(loginError, err.message);
  }
}

async function register() {
  clearError(registerError);

  const username = registerUsername.value.trim();
  const password = registerPassword.value.trim();

  try {
    await api(`${BASE_PATH}/api/register.php`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({ username, password })
    });

    registerModal?.hide();
    window.location.reload();
  } catch (err) {
    showError(registerError, err.message);
  }
}

async function logout() {
  await api(`${BASE_PATH}/api/logout.php`, {
    method: "POST"
  });

  window.location.reload();
}

if (chatForm) {
  chatForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!window.APP_STATE.isLoggedIn) return;

    const text = messageInput.value.trim();
    if (!text) return;

    if (!currentConversationId) {
      const convoData = await api(`${BASE_PATH}/api/conversations.php`, {
        method: "POST"
      });
      currentConversationId = convoData.conversation.id;
      await loadConversations();
      setActiveConversation(currentConversationId);
    }

    appendMessage("user", text);
    messageInput.value = "";

    try {
      const data = await api(`${BASE_PATH}/api/chat.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          conversation_id: currentConversationId,
          message: text
        })
      });

      appendMessage("assistant", data.reply);
      await loadConversations();
      setActiveConversation(currentConversationId);
    } catch (err) {
      appendMessage("system", `Error: ${err.message}`);
    }
  });
}

if (newChatBtn) {
  newChatBtn.addEventListener("click", createConversation);
}

if (logoutBtn) {
  logoutBtn.addEventListener("click", logout);
}

if (loginBtn) {
  loginBtn.addEventListener("click", login);
}

if (registerBtn) {
  registerBtn.addEventListener("click", register);
}

(async function init() {
  if (window.APP_STATE.isLoggedIn) {
    await loadConversations();
  }
})();