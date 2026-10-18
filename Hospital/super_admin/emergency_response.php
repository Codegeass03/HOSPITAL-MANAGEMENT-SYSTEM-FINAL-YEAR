<!-- Emergency Sound Alert -->
<audio id="sosAlertSound" loop>
    <source src="assets/sounds/emergency_alert.mp3" type="audio/mpeg">
    <source src="assets/sounds/emergency_alert.ogg" type="audio/ogg">
    Your browser does not support the audio element.
</audio>

<!-- Sound Toggle Button -->
<div class="sound-control" id="soundControl" onclick="toggleSOSSound()">
    <i class="fas fa-volume-up"></i>
</div>

<style>
    .sound-control {
        position: fixed;
        bottom: 100px;
        right: 30px;
        background: #f44336;
        color: white;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(244, 67, 54, 0.4);
        transition: all 0.3s ease;
        z-index: 1000;
    }
    
    .sound-control:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(244, 67, 54, 0.6);
    }
    
    .sound-control.muted {
        background: #666;
    }
    
    .sound-control i {
        font-size: 20px;
    }
    
    /* Animation for urgent SOS card */
    .stat-card.sos-alert {
        position: relative;
        overflow: hidden;
    }
    
    .stat-card.sos-alert::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, 
            transparent 25%, 
            rgba(255, 255, 255, 0.1) 25%, 
            rgba(255, 255, 255, 0.1) 50%, 
            transparent 50%, 
            transparent 75%, 
            rgba(255, 255, 255, 0.1) 75%);
        background-size: 20px 20px;
        animation: sosStripe 2s linear infinite;
        pointer-events: none;
    }
    
    @keyframes sosStripe {
        0% { background-position: 0 0; }
        100% { background-position: 20px 20px; }
    }
</style>

<script>
    // Emergency sound functionality
    let isSOSSoundMuted = false;
    const sosAlertSound = document.getElementById('sosAlertSound');
    const soundControl = document.getElementById('soundControl');
    
    // Check if there are urgent SOS alerts
    let urgentSOSCount = <?php echo $stats['urgent_sos']; ?>;
    
    function toggleSOSSound() {
        isSOSSoundMuted = !isSOSSoundMuted;
        
        if (isSOSSoundMuted) {
            sosAlertSound.pause();
            soundControl.classList.add('muted');
            soundControl.innerHTML = '<i class="fas fa-volume-mute"></i>';
        } else {
            if (urgentSOSCount > 0) {
                playSOSAlertSound();
            }
            soundControl.classList.remove('muted');
            soundControl.innerHTML = '<i class="fas fa-volume-up"></i>';
        }
    }
    
    function playSOSAlertSound() {
        if (!isSOSSoundMuted && urgentSOSCount > 0) {
            // Set volume to a reasonable level
            sosAlertSound.volume = 0.5;
            
            // Play with user interaction or after a delay
            setTimeout(() => {
                sosAlertSound.play().catch(e => {
                    console.log('Audio play failed, will try with user interaction:', e);
                });
            }, 1000);
        }
    }
    
    // Auto-play if there are urgent alerts
    document.addEventListener('DOMContentLoaded', function() {
        if (urgentSOSCount > 0) {
            // Visual alert
            const sosCard = document.querySelector('.stat-card.sos-alert');
            if (sosCard) {
                sosCard.style.animation = 'sosPulse 1s infinite';
                
                // Add click to play sound
                sosCard.addEventListener('click', function() {
                    if (!isSOSSoundMuted) {
                        playSOSAlertSound();
                    }
                });
            }
            
            // Try to play sound after a short delay
            setTimeout(playSOSAlertSound, 2000);
        }
    });
    
    // Monitor for new SOS alerts
    setInterval(() => {
        fetch('api/check_sos_alerts.php')
            .then(response => response.json())
            .then(data => {
                if (data.highPriority > urgentSOSCount) {
                    urgentSOSCount = data.highPriority;
                    
                    // Update the counter
                    const sosValue = document.querySelector('.stat-card.sos-alert .stat-value');
                    if (sosValue) {
                        sosValue.textContent = data.highPriority;
                    }
                    
                    // Play alert sound if not muted
                    if (!isSOSSoundMuted) {
                        playSOSAlertSound();
                        showNotification(`🚨 ${data.highPriority - urgentSOSCount} new urgent SOS alert(s)!`, 'error');
                    }
                }
            })
            .catch(error => console.error('Error checking SOS alerts:', error));
    }, 30000);
</script>