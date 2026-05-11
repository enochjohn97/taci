// public/js/pwa-init.js - PWA initialization and updates

(function initPWA() {
  "use strict";

  const ADMIN_ONLY = true; // PWA only for admin and subadmin

  // Check if user has admin/subadmin role (you can pass this from template)
  const userRoles = window.userRoles || [];
  const isAdminOrSubAdmin =
    userRoles.includes("ROLE_SUPER_ADMIN") ||
    userRoles.includes("ROLE_SUB_ADMIN");

  if (!isAdminOrSubAdmin && ADMIN_ONLY) {
    console.log("[PWA] PWA disabled for non-admin users");
    return;
  }

  // Check if service workers are supported
  if (!("serviceWorker" in navigator)) {
    console.warn("[PWA] Service Workers not supported");
    return;
  }

  // Register service worker
  window.addEventListener("load", () => {
    navigator.serviceWorker
      .register("/sw.js")
      .then((registration) => {
        console.log(
          "[PWA] Service Worker registered successfully:",
          registration,
        );

        // Check for updates
        registration.addEventListener("updatefound", () => {
          const newWorker = registration.installing;
          newWorker.addEventListener("statechange", () => {
            if (
              newWorker.state === "installed" &&
              navigator.serviceWorker.controller
            ) {
              // New service worker available, show update prompt
              showUpdatePrompt(registration);
            }
          });
        });

        // Check for updates regularly
        setInterval(() => {
          registration.update().catch(() => {
            // Ignore update check failures
          });
        }, 60000); // Check every minute
      })
      .catch((error) => {
        console.error("[PWA] Service Worker registration failed:", error);
      });

    // Listen for service worker messages
    navigator.serviceWorker.addEventListener("message", (event) => {
      if (event.data && event.data.type === "UPDATE_AVAILABLE") {
        showUpdatePrompt();
      }
    });
  });

  // Prompt user for update
  function showUpdatePrompt(registration) {
    const updatePrompt = document.createElement("div");
    updatePrompt.className = "pwa-update-prompt";
    updatePrompt.innerHTML = `
      <div class="pwa-update-content">
        <p>A new version of TACI Petroleum is available</p>
        <div class="pwa-update-actions">
          <button id="pwa-update-dismiss" class="pwa-btn-secondary">Later</button>
          <button id="pwa-update-apply" class="pwa-btn-primary">Update Now</button>
        </div>
      </div>
    `;

    document.body.appendChild(updatePrompt);

    document
      .getElementById("pwa-update-dismiss")
      .addEventListener("click", () => {
        updatePrompt.remove();
      });

    document
      .getElementById("pwa-update-apply")
      .addEventListener("click", () => {
        if (registration && registration.waiting) {
          registration.waiting.postMessage({ type: "SKIP_WAITING" });
          window.location.reload();
        }
        updatePrompt.remove();
      });
  }

  // Add install prompt listener (for "Add to Home Screen")
  let deferredPrompt;
  window.addEventListener("beforeinstallprompt", (e) => {
    // Prevent the mini-infobar from appearing on mobile
    e.preventDefault();
    deferredPrompt = e;

    const installButton = document.getElementById("pwa-install-button");
    if (installButton) {
      installButton.style.display = "block";
      installButton.addEventListener("click", () => {
        installButton.style.display = "none";
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
          if (choiceResult.outcome === "accepted") {
            console.log("[PWA] User accepted the install prompt");
          } else {
            console.log("[PWA] User dismissed the install prompt");
          }
          deferredPrompt = null;
        });
      });
    }
  });

  window.addEventListener("appinstalled", () => {
    console.log("[PWA] TACI Petroleum installed as PWA");
  });

  // Network status monitoring
  window.addEventListener("online", () => {
    document.body.classList.remove("offline");
    console.log("[PWA] Back online");
    // Optionally sync data here
  });

  window.addEventListener("offline", () => {
    document.body.classList.add("offline");
    console.log("[PWA] Went offline");
  });

  // Request notification permission
  function requestNotificationPermission() {
    if (!("Notification" in window)) {
      return;
    }

    if (Notification.permission === "granted") {
      return;
    }

    if (Notification.permission !== "denied") {
      Notification.requestPermission().then((permission) => {
        if (permission === "granted") {
          console.log("[PWA] Notification permission granted");
        }
      });
    }
  }

  // Call after user interacts
  document.addEventListener(
    "click",
    () => {
      requestNotificationPermission();
    },
    { once: true },
  );

  console.log("[PWA] Initialization complete");
})();

// CSS for PWA update prompt
const style = document.createElement("style");
style.textContent = `
  .pwa-update-prompt {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #1f2937;
    border-top: 4px solid #667eea;
    color: white;
    padding: 1rem;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.2);
  }

  .pwa-update-content {
    display: flex;
    align-items: center;
    gap: 2rem;
    flex-grow: 1;
  }

  .pwa-update-content p {
    margin: 0;
  }

  .pwa-update-actions {
    display: flex;
    gap: 0.5rem;
  }

  .pwa-btn-primary,
  .pwa-btn-secondary {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
  }

  .pwa-btn-primary {
    background: #667eea;
    color: white;
  }

  .pwa-btn-primary:hover {
    background: #764ba2;
  }

  .pwa-btn-secondary {
    background: #4b5563;
    color: white;
  }

  .pwa-btn-secondary:hover {
    background: #6b7280;
  }

  body.offline .pwa-offline-indicator {
    display: block;
  }

  @media (max-width: 768px) {
    .pwa-update-prompt {
      flex-direction: column;
      align-items: flex-start;
    }

    .pwa-update-content {
      flex-direction: column;
      gap: 1rem;
      width: 100%;
    }

    .pwa-update-actions {
      width: 100%;
    }

    .pwa-update-actions button {
      flex: 1;
    }
  }
`;
document.head.appendChild(style);
