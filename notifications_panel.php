<?php
if (!function_exists('getActiveNotificationsForUser')) {
    require_once 'expense_functions.php';
}
$current_user_id = $_SESSION['user_id'] ?? 0;
$all_active_notifications = getActiveNotificationsForUser($current_user_id); 
?>

<div class="notif-bell-container" style="display: inline-block; position: relative;">
    <button type="button" id="globalBellBtn" class="bell-trigger-btn">
        🔔 Bell <span class="badge" id="bellCountBadge"><?php echo count($all_active_notifications); ?></span>
    </button>

    <div id="sharedBellModal" class="shared-bell-modal">
        <h4 style="margin: 0 0 10px 0; padding-bottom: 8px; border-bottom: 2px solid #ddd; font-size: 14px; color: #111;">Your Active Notifications</h4>
        <div class="notif-scroll-area" id="notifListContainer">
            <?php if (count($all_active_notifications) > 0): ?>
                <?php foreach ($all_active_notifications as $notif): ?>
                    <div class="shared-notif-item">
                        <span class="notif-text-span">
                            <b>ID: <?php echo $notif['id']; ?></b><br>
                            <?php echo htmlspecialchars($notif['message']); ?>
                        </span>
                        <form method="POST" action="records.php" style="margin: 0; padding: 0;">
                            <input type="hidden" name="action_clear_notif_id" value="<?php echo $notif['id']; ?>">
                            <button type="submit" class="clear-btn-red">Clear</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p id="noNotifText" style="margin: 15px 0; font-size: 13px; color: #888; text-align: center;">Koi naye alerts nahi hain.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="realtimePopupAlert" class="realtime-popup-box">
    <div class="popup-header-bar">
        <span id="popupAlertTitle" style="font-weight: bold; font-size: 14px;">New Log Alert! (ID: 0)</span>
        <span class="popup-close-x" onclick="closePopupAlert()">&times;</span>
    </div>
    <div id="popupAlertBody" class="popup-body-text">
        User Owais Insert New Query.
    </div>
</div>

<style>
    /* Bell Styles */
    .bell-trigger-btn { background: #f8f9fa; border: 1px solid #ccc; padding: 6px 12px; font-weight: bold; border-radius: 4px; cursor: pointer; position: relative; }
    .bell-trigger-btn .badge { background: #dc3545; color: white; padding: 1px 6px; border-radius: 10px; font-size: 11px; margin-left: 5px; }
    
    .shared-bell-modal { display: none; position: absolute; top: 40px; right: 0; background: white; border: 1px solid #ccc; padding: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); border-radius: 4px; width: 300px; z-index: 1000; }
    .notif-scroll-area { max-height: 250px; overflow-y: auto; }
    .shared-notif-item { border-bottom: 1px solid #eee; padding: 8px 0; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
    
    .clear-btn-red { background: #ff0000; color: white; border: none; padding: 3px 8px; font-size: 11px; font-weight: bold; border-radius: 3px; cursor: pointer; }
    .clear-btn-red:hover { background: #cc0000; }

    /* EXACT IMAGE LOOK: Right Bottom Toast Notification Box UI Layout */
    .realtime-popup-box {
        position: fixed;
        bottom: 20px;
        right: -350px; /* Suru me hidden rhegi */
        width: 320px;
        background: #222222; /* Dark theme text panel as image */
        color: white;
        border-radius: 4px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        z-index: 99999;
        border-left: 5px solid #007bff; /* Blue line accent */
        transition: right 0.5s ease-in-out;
    }
    .realtime-popup-box.show { right: 20px; } /* Slide inside viewport smoothly */
    .popup-header-bar { padding: 10px 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; }
    .popup-body-text { padding: 12px 15px; font-size: 13px; color: #ccc; line-height: 1.4; }
    .popup-close-x { cursor: pointer; font-size: 16px; font-weight: bold; color: #aaa; }
    .popup-close-x:hover { color: white; }
</style>

<script>
    // Dropdown panel toggle
    document.getElementById('globalBellBtn').addEventListener('click', function(e) {
        e.stopPropagation();
        var modal = document.getElementById('sharedBellModal');
        modal.style.display = (modal.style.display === 'block') ? 'none' : 'block';
    });

    document.addEventListener('click', function() {
        document.getElementById('sharedBellModal').style.display = 'none';
    });
    document.getElementById('sharedBellModal').addEventListener('click', function(e){ e.stopPropagation(); });

    function closePopupAlert() {
        document.getElementById('realtimePopupAlert').classList.remove('show');
    }

    // REAL-TIME AJAX BACKGROUND ENGINE ENGINE
    function startNotificationPoller() {
        setInterval(function() {
            fetch('check_new_notifications.php')
                .then(response => response.json())
                .then(data => {
                    // 1. Badge par dynamic count automatic plus/update karna
                    const badge = document.getElementById('bellCountBadge');
                    badge.innerText = data.count;

                    // 2. Agar koi naya alert aya h to popup fire karein
                    if (data.new_alerts && data.new_alerts.length > 0) {
                        data.new_alerts.forEach(alertItem => {
                            // Elements me data feed karein
                            document.getElementById('popupAlertTitle').innerText = "New Log Alert! (ID: " + alertItem.id + ")";
                            document.getElementById('popupAlertBody').innerText = alertItem.message;
                            
                            // Popup active slide in karein
                            const popupBox = document.getElementById('realtimePopupAlert');
                            popupBox.classList.add('show');

                            // Dynamic Dropdown me instant prepend karein (bina page refresh)
                            const container = document.getElementById('notifListContainer');
                            const noNotifText = document.getElementById('noNotifText');
                            if(noNotifText) noNotifText.remove();

                            const newItemHTML = `
                                <div class="shared-notif-item">
                                    <span class="notif-text-span">
                                        <b>ID: ${alertItem.id}</b><br>
                                        ${alertItem.message}
                                    </span>
                                    <form method="POST" action="records.php" style="margin: 0; padding: 0;">
                                        <input type="hidden" name="action_clear_notif_id" value="${alertItem.id}">
                                        <button type="submit" class="clear-btn-red">Clear</button>
                                    </form>
                                </div>
                            `;
                            container.insertAdjacentHTML('afterbegin', newItemHTML);

                            // 5 Seconds baad auto-hide popup box
                            setTimeout(function() {
                                popupBox.classList.remove('show');
                            }, 6000);
                        });
                    }
                })
                .catch(error => console.log("Poller error status:", error));
        }, 4000); // Har 4 seconds me background check chalega
    }

    // Initialization trigger
    window.addEventListener('DOMContentLoaded', startNotificationPoller);
</script>