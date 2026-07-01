<style>
/* ══ TOAST WRAPPER & NOTIFICATIONS STYLE ══ */
.toast-wrap {
    position: fixed;
    bottom: 20px;
    right: 16px;
    z-index: 9000;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 290px;
}
.toast {
    background: var(--surface-s, #ffffff);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--border-s, #e2e8f0);
    border-radius: 10px;
    padding: 13px 14px;
    box-shadow: 0 6px 24px rgba(0,0,0,.15);
    border-left: 4px solid var(--brand, #7c2d12);
    position: relative;
    animation: fadeSlideUp .3s ease both;
}
.toast.success-toast {
    border-left-color: var(--success, #10b981);
}
.toast-close {
    position: absolute;
    top: 7px;
    right: 9px;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-m, #64748b);
    font-size: 15px;
    font-weight: 700;
    padding: 0;
    line-height: 1;
}
.toast-close:hover {
    color: var(--danger, #ef4444);
}
.toast a {
    text-decoration: none;
    color: var(--text, #1e293b);
    display: block;
}
.toast a:hover {
    color: var(--brand, #7c2d12);
}
.toast strong {
    font-size: 13px;
    color: var(--success, #10b981);
}
.toast span {
    font-size: 11.5px;
    color: var(--text-m, #64748b);
}

/* ══ ANIMATIONS ══ */
@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(14px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php
// 1. Safe Session Start
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// 2. Dependency Handler
if (isset($_SESSION['user_id']) && isset($pdo)) {
    $userId = $_SESSION['user_id'];

    // 3. BACKGROUND AJAX CLEAR HANDLER (Bina page load kiye chalega)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_clear_notif_id'])) {
        $clearId = intval($_POST['ajax_clear_notif_id']);
        
        if (function_exists('clearNotificationForUser')) {
            clearNotificationForUser($clearId, $userId);
        } else {
            $deleteStmt = $pdo->prepare("DELETE FROM user_notifications WHERE notification_id = ? AND user_id = ?");
            $deleteStmt->execute([$clearId, $userId]);
        }
        
        // JSON response bhej kar script ko yahin stop kar denge taake page load na ho
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit();
    }

    // 4. Fetch Active Live Notifications
    $notifications = [];
    if (function_exists('getActiveNotificationsForUser')) {
        $notifications = getActiveNotificationsForUser($userId);
    } else {
        $notifQuery = "SELECT n.id, n.message FROM notifications n 
                       JOIN user_notifications un ON n.id = un.notification_id 
                       WHERE un.user_id = ? 
                       ORDER BY n.created_at DESC";
        $notifStmt = $pdo->prepare($notifQuery);
        $notifStmt->execute([$userId]);
        $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<div class="toast-wrap">
    <?php if (!empty($notifications) && is_array($notifications)): ?>
        <?php foreach ($notifications as $notif): ?>
            <div class="toast" id="toast-notif-<?php echo $notif['id']; ?>">
                <button type="button" class="toast-close" onclick="dismissNotification(<?php echo $notif['id']; ?>)" aria-label="Close Toast">&times;</button>
                
                <a href="check.php?id=<?php echo $notif['id']; ?>">
                    <strong style="color: var(--brand, #7c2d12);">System Alert (ID: <?php echo $notif['id']; ?>)</strong><br>
                    <span><?php echo htmlspecialchars($notif['message']); ?></span>
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
// ══ NO-PAGE-LOAD DISMISS HANDLER (AJAX) ══
function dismissNotification(notifId) {
    const toastElement = document.getElementById('toast-notif-' + notifId);
    
    if (toastElement) {
        // 1. Screen par animation chalayein (Smooth disappear)
        toastElement.style.transition = "opacity 0.4s ease, transform 0.4s ease";
        toastElement.style.opacity = "0";
        toastElement.style.transform = "translateY(12px)";

        // 2. Background me silently (bina reload ke) database update karein
        const formData = new FormData();
        formData.append('ajax_clear_notif_id', notifId);

        // Jis page par hain usi page ke background connection par push karein
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Database se clear hone ke baad DOM se element delete karein
                setTimeout(() => { toastElement.remove(); }, 400);
            }
        })
        .catch(error => {
            console.error('Error clearing notification:', error);
            // Agar network error bhi aaye, to user experience ke liye screen se hatayein
            setTimeout(() => { toastElement.remove(); }, 400);
        });
    }
}

// ══ AUTOMATIC TIMER CLOSE (7 SECONDS) ══
document.addEventListener("DOMContentLoaded", function() {
    const toasts = document.querySelectorAll('.toast');
    toasts.forEach(function(toast) {
        setTimeout(function() {
            if(toast) {
                toast.style.transition = "opacity 0.5s ease, transform 0.5s ease";
                toast.style.opacity = "0";
                toast.style.transform = "translateY(10px)";
                setTimeout(function() { toast.remove(); }, 500);
            }
        }, 7000);
    });
});
</script>