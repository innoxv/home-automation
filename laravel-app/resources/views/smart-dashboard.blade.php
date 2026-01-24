<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Bulb Control - 3 Bulbs</title>
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Bootstrap & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Voice Control Panel CSS -->
    <style>
        /* Voice Control Panel */
        .voice-panel {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 15px;
            padding: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            z-index: 1000;
            width: 300px;
            border: 1px solid #e0e0e0;
        }
        
        .voice-status {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .voice-status i {
            font-size: 1.2rem;
            margin-right: 10px;
            color: #666;
        }
        
        .voice-feedback {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-left: auto;
            background: #ddd;
            transition: all 0.3s ease;
        }
        
        .voice-feedback.listening {
            background: #FF9800;
            animation: pulse 1s infinite;
        }
        
        .voice-feedback.ready {
            background: #4CAF50;
        }
        
        .voice-toggle-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        
        .voice-toggle-btn:hover {
            transform: scale(1.1);
        }
        
        .voice-toggle-btn.listening {
            background: #FF9800;
            animation: pulse 1s infinite;
        }
        
        .voice-commands-list {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: none;
        }
        
        .voice-commands-list.show {
            display: block;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Original Styles */
        :root {
            --bulb1-color: #ff6b6b;
            --bulb2-color: #4ecdc4;
            --bulb3-color: #45b7d1;
        }
        
        body {
            background: #f5f7fb;
            font-family: 'Segoe UI', sans-serif;
        }
        
        .glass-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .bulb-icon {
            font-size: 3rem;
            color: #ddd;
        }
        
        .bulb-on .bulb-icon {
            color: currentColor;
            filter: drop-shadow(0 0 15px currentColor);
        }
        
        .bulb1-color { color: var(--bulb1-color); }
        .bulb2-color { color: var(--bulb2-color); }
        .bulb3-color { color: var(--bulb3-color); }
        
        .control-btn {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            margin: 0 5px;
        }
        
        .btn-on { background: #4CAF50; color: white; }
        .btn-off { background: #f44336; color: white; }
        .btn-voice { background: #2196F3; color: white; }
        
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .connected { background: #4CAF50; }
        .disconnected { background: #f44336; }
        
        .effect-btn {
            padding: 10px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            background: white;
            color: #666;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .effect-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .effect-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #667eea;
        }
        
        /* Voice Toast */
        .voice-toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
            z-index: 9999;
            width: 300px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .voice-toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .voice-toast-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 12px 15px;
            border-radius: 10px 10px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .voice-toast-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .voice-toast-body {
            padding: 15px;
        }
    </style>
</head>
<body>
    <!-- Voice Control Panel -->
    <div class="voice-panel">
        <div class="voice-status" onclick="toggleCommandList()">
            <i class="fas fa-microphone"></i>
            <span id="voice-status-text">Voice Ready</span>
            <div id="voice-feedback" class="voice-feedback ready"></div>
        </div>
        <button id="voice-toggle" class="voice-toggle-btn" onclick="toggleVoiceControl()">
            <i class="fas fa-microphone"></i>
        </button>
        <div id="voice-commands-list" class="voice-commands-list">
            <h6><i class="fas fa-list-alt me-2"></i>Voice Commands</h6>
            <ul class="mb-0">
                <li>"Turn on bulb one/two/three"</li>
                <li>"Turn off bulb one/two/three"</li>
                <li>"Set bulb one to 50 percent"</li>
                <li>"All lights on/off"</li>
                <li>"Start strobe/fade/pulse effect"</li>
                <li>"Stop all effects"</li>
                <li>"Brightness fifty percent"</li>
            </ul>
        </div>
    </div>

    <!-- Voice Text Fallback -->
    <div id="voice-text-fallback" class="voice-panel" style="top: 220px; display: none;">
        <h6><i class="fas fa-keyboard me-2"></i>Text Commands</h6>
        <div class="input-group mb-2">
            <input type="text" id="voice-text-input" class="form-control" placeholder="Type command...">
            <button class="btn btn-primary" onclick="processTextCommand()">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
        <small class="text-muted">Try: "turn on bulb one", "all lights off", "brightness 50"</small>
    </div>

    <div class="container py-4">
        <!-- Header -->
        <div class="text-center mb-4">
            <h1 class="fw-bold">
                <i class="fas fa-lightbulb me-2"></i>Smart Bulb Control
                </span>
            </h1>
            <p class="text-muted">Control 3 Arduino-connected bulbs with voice commands</p>
        </div>
        
        <!-- Connection Status -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="glass-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="status-dot" id="connection-dot"></span>
                            <span class="fw-semibold" id="connection-status">Disconnected</span>
                            <span class="text-muted ms-3" id="active-mode">• Manual Mode</span>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary me-2" onclick="updateStatus()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                            <button class="btn btn-sm btn-outline-success me-2" onclick="toggleVoiceControl()">
                                <i class="fas fa-microphone"></i> Voice
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleTextInput()">
                                <i class="fas fa-keyboard"></i> Text
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bulbs Row -->
        <div class="row mb-4">
            <!-- Bulb 1 -->
            <div class="col-md-4">
                <div class="glass-card">
                    <h5 class="fw-semibold">
                        <i class="fas fa-lightbulb me-2 bulb1-color"></i>Bulb 1
                        <span class="badge bg-light text-dark float-end">Pin 9</span>
                    </h5>
                    
                    <div class="text-center my-3">
                        <div class="bulb1-color" id="bulb1-visual">
                            <i class="fas fa-lightbulb bulb-icon"></i>
                        </div>
                        <div class="h4 fw-bold bulb1-color mt-2" id="bulb1-percentage">0%</div>
                    </div>
                    
                    <input type="range" class="form-range mb-3" id="bulb1-slider" min="0" max="100" value="0">
                    
                    <div class="text-center">
                        <button class="control-btn btn-on" onclick="controlBulb(1, 'on')" title="Turn On">
                            <i class="fas fa-power-off"></i>
                        </button>
                        <button class="control-btn btn-off" onclick="controlBulb(1, 'off')" title="Turn Off">
                            <i class="fas fa-power-off"></i>
                        </button>
                        <button class="control-btn btn-voice" onclick="startVoiceForBulb(1)" title="Voice Control">
                            <i class="fas fa-microphone"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Bulb 2 -->
            <div class="col-md-4">
                <div class="glass-card">
                    <h5 class="fw-semibold">
                        <i class="fas fa-lightbulb me-2 bulb2-color"></i>Bulb 2
                        <span class="badge bg-light text-dark float-end">Pin 10</span>
                    </h5>
                    
                    <div class="text-center my-3">
                        <div class="bulb2-color" id="bulb2-visual">
                            <i class="fas fa-lightbulb bulb-icon"></i>
                        </div>
                        <div class="h4 fw-bold bulb2-color mt-2" id="bulb2-percentage">0%</div>
                    </div>
                    
                    <input type="range" class="form-range mb-3" id="bulb2-slider" min="0" max="100" value="0">
                    
                    <div class="text-center">
                        <button class="control-btn btn-on" onclick="controlBulb(2, 'on')" title="Turn On">
                            <i class="fas fa-power-off"></i>
                        </button>
                        <button class="control-btn btn-off" onclick="controlBulb(2, 'off')" title="Turn Off">
                            <i class="fas fa-power-off"></i>
                        </button>
                        <button class="control-btn btn-voice" onclick="startVoiceForBulb(2)" title="Voice Control">
                            <i class="fas fa-microphone"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Bulb 3 -->
            <div class="col-md-4">
                <div class="glass-card">
                    <h5 class="fw-semibold">
                        <i class="fas fa-lightbulb me-2 bulb3-color"></i>Bulb 3
                        <span class="badge bg-light text-dark float-end">Pin 11</span>
                    </h5>
                    
                    <div class="text-center my-3">
                        <div class="bulb3-color" id="bulb3-visual">
                            <i class="fas fa-lightbulb bulb-icon"></i>
                        </div>
                        <div class="h4 fw-bold bulb3-color mt-2" id="bulb3-percentage">0%</div>
                    </div>
                    
                    <input type="range" class="form-range mb-3" id="bulb3-slider" min="0" max="100" value="0">
                    
                    <div class="text-center">
                        <button class="control-btn btn-on" onclick="controlBulb(3, 'on')" title="Turn On">
                            <i class="fas fa-power-off"></i>
                        </button>
                        <button class="control-btn btn-off" onclick="controlBulb(3, 'off')" title="Turn Off">
                            <i class="fas fa-power-off"></i>
                        </button>
                        <button class="control-btn btn-voice" onclick="startVoiceForBulb(3)" title="Voice Control">
                            <i class="fas fa-microphone"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Effects Panel -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="glass-card">
                    <h5 class="fw-semibold mb-3">
                        <i class="fas fa-magic me-2"></i>Lighting Effects
                    </h5>
                    
                    <div class="row g-2">
                        <div class="col-md-2 col-4">
                            <button class="effect-btn w-100" onclick="startEffect('alternate')" id="effect-alt">
                                <div class="text-center">
                                    <i class="fas fa-exchange-alt mb-1"></i><br>
                                    <small>Alternate</small>
                                </div>
                            </button>
                        </div>
                        <div class="col-md-2 col-4">
                            <button class="effect-btn w-100" onclick="startEffect('strobe')" id="effect-strobe">
                                <div class="text-center">
                                    <i class="fas fa-bolt mb-1"></i><br>
                                    <small>Strobe</small>
                                </div>
                            </button>
                        </div>
                        <div class="col-md-2 col-4">
                            <button class="effect-btn w-100" onclick="startEffect('fade')" id="effect-fade">
                                <div class="text-center">
                                    <i class="fas fa-wave-square mb-1"></i><br>
                                    <small>Fade</small>
                                </div>
                            </button>
                        </div>
                        <div class="col-md-2 col-4">
                            <button class="effect-btn w-100" onclick="startEffect('pulse')" id="effect-pulse">
                                <div class="text-center">
                                    <i class="fas fa-heartbeat mb-1"></i><br>
                                    <small>Pulse</small>
                                </div>
                            </button>
                        </div>
                        <div class="col-md-2 col-4">
                            <button class="effect-btn w-100" onclick="startEffect('rainbow')" id="effect-rainbow">
                                <div class="text-center">
                                    <i class="fas fa-rainbow mb-1"></i><br>
                                    <small>Rainbow</small>
                                </div>
                            </button>
                        </div>
                        <div class="col-md-2 col-4">
                            <button class="effect-btn w-100" onclick="stopEffects()" style="border-color: #f44336; color: #f44336;">
                                <div class="text-center">
                                    <i class="fas fa-stop mb-1"></i><br>
                                    <small>Stop All</small>
                                </div>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Strobe Speed Control -->
                    <div class="row align-items-center mt-3" id="strobe-controls" style="display: none;">
                        <div class="col-md-3">
                            <label class="form-label">Strobe Speed</label>
                        </div>
                        <div class="col-md-9">
                            <input type="range" class="form-range" id="strobe-speed" min="1" max="5" value="3">
                            <div class="d-flex justify-content-between">
                                <small>Slow</small>
                                <small>Medium</small>
                                <small>Fast</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Group Controls -->
        <div class="row">
            <div class="col-12">
                <div class="glass-card">
                    <h5 class="fw-semibold mb-3">
                        <i class="fas fa-object-group me-2"></i>Group Controls
                    </h5>
                    
                    <div class="row g-2">
                        <div class="col-md-3">
                            <button class="btn btn-success w-100" onclick="groupControl('on')">
                                <i class="fas fa-lightbulb me-2"></i>All ON
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-secondary w-100" onclick="groupControl('off')">
                                <i class="fas fa-lightbulb me-2"></i>All OFF
                            </button>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="number" class="form-control" id="group-brightness" 
                                       min="0" max="100" placeholder="Brightness %">
                                <button class="btn btn-primary" onclick="setGroupBrightness()">
                                    <i class="fas fa-sliders-h"></i> Set
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-info w-100" onclick="startVoiceForAll()">
                                <i class="fas fa-microphone me-2"></i>Voice Control
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Voice Command Toast -->
    <div id="voice-command-toast" class="voice-toast">
        <div class="voice-toast-content">
            <div class="voice-toast-header">
                <i class="fas fa-microphone me-2"></i>
                <span>Voice Command</span>
                <button class="voice-toast-close" onclick="closeVoiceToast()">&times;</button>
            </div>
            <div class="voice-toast-body">
                <div id="voice-command-text">Listening...</div>
                <div id="voice-command-action" class="text-muted small"></div>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div id="toast-container" style="position: fixed; top: 20px; left: 20px; z-index: 9999;"></div>
    
    <!-- Main JavaScript -->
    <script>
        // Core Application State
        let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        let currentState = {
            bulb1: { state: 'off', brightness: 0 },
            bulb2: { state: 'off', brightness: 0 },
            bulb3: { state: 'off', brightness: 0 },
            mode: 'manual',
            connected: false
        };
        
        // Voice Control State
        let voiceControl = {
            isListening: false,
            recognition: null,
            isSupported: false,
            currentBulb: null
        };
        
        // Initialize application
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Smart Bulb Control Initializing...');
            
            // Setup sliders
            setupSliders();
            
            // Initialize voice control
            initializeVoiceControl();
            
            // Initial status check
            updateStatus();
            
            // Auto-refresh every 5 seconds
            setInterval(updateStatus, 5000);
            
            console.log('System Ready!');
            showToast('Smart Bulb Control Ready', 'success');
        });
        
        // Setup slider event listeners
        function setupSliders() {
            for (let i = 1; i <= 3; i++) {
                const slider = document.getElementById(`bulb${i}-slider`);
                if (!slider) continue;
                
                slider.addEventListener('input', function() {
                    const value = parseInt(this.value);
                    const percentage = document.getElementById(`bulb${i}-percentage`);
                    if (percentage) percentage.textContent = `${value}%`;
                });
                
                slider.addEventListener('change', function() {
                    const value = parseInt(this.value);
                    controlBulb(i, 'brightness', value);
                });
            }
            
            // Strobe speed slider
            const strobeSpeedSlider = document.getElementById('strobe-speed');
            if (strobeSpeedSlider) {
                strobeSpeedSlider.addEventListener('change', function() {
                    updateStrobeSpeed(parseInt(this.value));
                });
            }
        }
        
        // ==================== CORE CONTROL FUNCTIONS ====================
        
        // Control individual bulb
        async function controlBulb(bulb, action, value = null) {
            console.log(`Controlling bulb ${bulb}: ${action} ${value || ''}`);
            
            try {
                const response = await fetch('/api/bulb/control', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        bulb: bulb,
                        action: action,
                        value: value
                    })
                });
                
                if (!response.ok) throw new Error(`HTTP error: ${response.status}`);
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(`Bulb ${bulb} ${getActionText(action, value)}`, 'success');
                    updateLocalState(data);
                    updateBulbUI(bulb, data.state);
                } else {
                    showToast(`Error: ${data.error || 'Unknown error'}`, 'danger');
                }
            } catch (error) {
                console.error('Control error:', error);
                showToast(`Network error: ${error.message}`, 'danger');
            }
        }
        
        // Start effect
        async function startEffect(effect, speed = null) {
            console.log(`Starting effect: ${effect}`);
            
            // Update UI
            document.querySelectorAll('.effect-btn').forEach(btn => btn.classList.remove('active'));
            const effectBtn = document.getElementById(`effect-${effect}`);
            if (effectBtn) effectBtn.classList.add('active');
            
            // Show/hide strobe controls
            const strobeControls = document.getElementById('strobe-controls');
            if (strobeControls) {
                strobeControls.style.display = effect === 'strobe' ? 'flex' : 'none';
            }
            
            try {
                const response = await fetch('/api/effect/control', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        effect: effect,
                        speed: speed || (effect === 'strobe' ? parseInt(document.getElementById('strobe-speed')?.value || 3) : null)
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(`${effect.charAt(0).toUpperCase() + effect.slice(1)} effect started`, 'success');
                    updateLocalState(data);
                    currentState.mode = effect;
                    document.getElementById('active-mode').textContent = `• ${effect.charAt(0).toUpperCase() + effect.slice(1)} Mode`;
                } else {
                    showToast(`Error: ${data.error || 'Unknown error'}`, 'danger');
                    if (effectBtn) effectBtn.classList.remove('active');
                }
            } catch (error) {
                console.error('Effect error:', error);
                showToast(`Network error: ${error.message}`, 'danger');
                if (effectBtn) effectBtn.classList.remove('active');
            }
        }
        
        // Stop effects
        async function stopEffects() {
            console.log('Stopping all effects');
            
            try {
                const response = await fetch('/api/effect/control', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ effect: 'stop' })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('All effects stopped', 'info');
                    document.querySelectorAll('.effect-btn').forEach(btn => btn.classList.remove('active'));
                    document.getElementById('strobe-controls').style.display = 'none';
                    updateLocalState(data);
                    currentState.mode = 'manual';
                    document.getElementById('active-mode').textContent = '• Manual Mode';
                } else {
                    showToast(`Error: ${data.error || 'Unknown error'}`, 'danger');
                }
            } catch (error) {
                console.error('Stop effects error:', error);
                showToast(`Network error: ${error.message}`, 'danger');
            }
        }
        
        // Group control
        async function groupControl(action, brightness = null) {
            console.log(`Group control: ${action} ${brightness || ''}`);
            
            try {
                const data = { action: action };
                if (brightness !== null) data.brightness = brightness;
                
                const response = await fetch('/api/group/control', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(`All bulbs ${getActionText(action, brightness)}`, 'success');
                    updateLocalState(result);
                } else {
                    showToast(`Error: ${result.error || 'Unknown error'}`, 'danger');
                }
            } catch (error) {
                console.error('Group control error:', error);
                showToast(`Network error: ${error.message}`, 'danger');
            }
        }
        
        // Set group brightness
        function setGroupBrightness() {
            const input = document.getElementById('group-brightness');
            if (!input) return;
            
            const brightness = parseInt(input.value);
            
            if (!isNaN(brightness) && brightness >= 0 && brightness <= 100) {
                groupControl('brightness', brightness);
                input.value = '';
            } else {
                showToast('Please enter a brightness between 0-100', 'warning');
            }
        }
        
        // Update strobe speed
        function updateStrobeSpeed(speed) {
            if (currentState.mode === 'strobe') {
                startEffect('strobe', speed);
            }
        }
        
        // Update status
        async function updateStatus() {
            try {
                const response = await fetch('/api/status');
                const data = await response.json();
                
                if (data.success) {
                    updateConnectionStatus(data.connected || false);
                    
                    if (data.state) {
                        currentState = {
                            ...currentState,
                            ...data.state,
                            connected: data.connected || false
                        };
                        
                        // Update UI
                        updateBulbUI(1, data.state.bulb1);
                        updateBulbUI(2, data.state.bulb2);
                        updateBulbUI(3, data.state.bulb3);
                        
                        // Update mode
                        if (data.state.mode) {
                            document.getElementById('active-mode').textContent = 
                                `• ${data.state.mode.charAt(0).toUpperCase() + data.state.mode.slice(1)} Mode`;
                        }
                    }
                }
            } catch (error) {
                console.error('Status error:', error);
                updateConnectionStatus(false);
            }
        }
        
        // Update bulb UI
        function updateBulbUI(bulbNum, bulbState) {
            if (!bulbState) return;
            
            const visual = document.getElementById(`bulb${bulbNum}-visual`);
            const percentage = document.getElementById(`bulb${bulbNum}-percentage`);
            const slider = document.getElementById(`bulb${bulbNum}-slider`);
            
            if (!visual || !percentage || !slider) return;
            
            if (bulbState.state === 'on') {
                visual.classList.add('bulb-on');
                percentage.textContent = `${bulbState.brightness || 0}%`;
                slider.value = bulbState.brightness || 0;
            } else {
                visual.classList.remove('bulb-on');
                percentage.textContent = '0%';
                slider.value = 0;
            }
        }
        
        // Update connection status
        function updateConnectionStatus(isConnected) {
            const dot = document.getElementById('connection-dot');
            const statusText = document.getElementById('connection-status');
            
            if (dot) dot.className = isConnected ? 'status-dot connected' : 'status-dot disconnected';
            if (statusText) {
                statusText.textContent = isConnected ? 'Connected' : 'Disconnected';
                statusText.style.color = isConnected ? '#4CAF50' : '#f44336';
            }
            
            currentState.connected = isConnected;
        }
        
        // Update local state
        function updateLocalState(data) {
            if (data.state) {
                if (data.state.bulb1) {
                    currentState = {
                        ...currentState,
                        ...data.state,
                        connected: data.connected || false
                    };
                }
                currentState.connected = data.connected || false;
            }
            updateConnectionStatus(data.connected || false);
        }
        
        // ==================== VOICE CONTROL FUNCTIONS ====================
        
        // Initialize voice control
        function initializeVoiceControl() {
            // Check if Web Speech API is supported
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            
            if (!SpeechRecognition) {
                console.warn('Web Speech API not supported in this browser');
                updateVoiceStatus('unsupported');
                return;
            }
            
            try {
                voiceControl.recognition = new SpeechRecognition();
                voiceControl.recognition.continuous = false;
                voiceControl.recognition.interimResults = false;
                voiceControl.recognition.lang = 'en-US';
                voiceControl.recognition.maxAlternatives = 1;
                
                voiceControl.recognition.onstart = () => {
                    voiceControl.isListening = true;
                    updateVoiceStatus('listening');
                    showVoiceToast('Listening...', 'Speak your command');
                };
                
                voiceControl.recognition.onresult = (event) => {
                    const transcript = event.results[0][0].transcript.toLowerCase();
                    console.log('Voice command:', transcript);
                    processVoiceCommand(transcript);
                };
                
                voiceControl.recognition.onerror = (event) => {
                    console.error('Voice recognition error:', event.error);
                    voiceControl.isListening = false;
                    updateVoiceStatus('ready');
                    showToast(`Voice error: ${event.error}`, 'warning');
                };
                
                voiceControl.recognition.onend = () => {
                    voiceControl.isListening = false;
                    voiceControl.currentBulb = null;
                    updateVoiceStatus('ready');
                };
                
                voiceControl.isSupported = true;
                updateVoiceStatus('ready');
                console.log('Voice control initialized');
                
            } catch (error) {
                console.error('Failed to initialize voice control:', error);
                updateVoiceStatus('unsupported');
            }
        }
        
        // Toggle voice control
        function toggleVoiceControl() {
            if (!voiceControl.isSupported) {
                showToast('Voice control not supported in this browser', 'warning');
                toggleTextInput();
                return;
            }
            
            if (voiceControl.isListening) {
                voiceControl.recognition.stop();
                voiceControl.isListening = false;
                updateVoiceStatus('ready');
            } else {
                startVoiceListening();
            }
        }
        
        // Start voice listening
        function startVoiceListening(targetBulb = null) {
            if (!voiceControl.isSupported || !voiceControl.recognition) {
                showToast('Voice control not available', 'warning');
                return;
            }
            
            voiceControl.currentBulb = targetBulb;
            
            try {
                voiceControl.recognition.start();
                updateVoiceStatus('listening');
                
                if (targetBulb) {
                    showVoiceToast(`Listening for bulb ${targetBulb}...`, 'Speak your command');
                } else {
                    showVoiceToast('Listening...', 'Speak your command');
                }
            } catch (error) {
                console.error('Error starting voice recognition:', error);
                showToast('Error starting microphone. Please check permissions.', 'danger');
                updateVoiceStatus('ready');
            }
        }
        
        // Start voice for specific bulb
        function startVoiceForBulb(bulbNum) {
            startVoiceListening(bulbNum);
        }
        
        // Start voice for all
        function startVoiceForAll() {
            startVoiceListening();
        }
        
        // Process voice command
        function processVoiceCommand(transcript) {
            console.log('Processing voice command:', transcript);
            
            // Show voice feedback
            showVoiceToast('Command received', transcript);
            
            // Parse command
            const command = parseVoiceCommand(transcript);
            
            if (command) {
                // Execute command
                executeParsedCommand(command);
                
                // Show success feedback
                showToast('Voice command executed', 'success');
            } else {
                showToast('Command not understood. Try again.', 'warning');
            }
            
            // Reset voice status
            setTimeout(() => updateVoiceStatus('ready'), 1000);
        }
        
        // Parse voice command
        function parseVoiceCommand(transcript) {
            const lower = transcript.toLowerCase();
            
            // Check for bulb-specific commands if targeting a bulb
            const targetBulb = voiceControl.currentBulb;
            
            // Turn on commands
            if (lower.includes('turn on') || lower.includes('switch on') || lower.includes('enable')) {
                if (targetBulb) {
                    return { type: 'turn_on', bulb: targetBulb };
                } else if (lower.includes('bulb one') || lower.includes('light one') || lower.includes('first')) {
                    return { type: 'turn_on', bulb: 1 };
                } else if (lower.includes('bulb two') || lower.includes('light two') || lower.includes('second')) {
                    return { type: 'turn_on', bulb: 2 };
                } else if (lower.includes('bulb three') || lower.includes('light three') || lower.includes('third')) {
                    return { type: 'turn_on', bulb: 3 };
                } else if (lower.includes('all') || lower.includes('every')) {
                    return { type: 'turn_on', all: true };
                }
            }
            
            // Turn off commands
            else if (lower.includes('turn off') || lower.includes('switch off') || lower.includes('disable')) {
                if (targetBulb) {
                    return { type: 'turn_off', bulb: targetBulb };
                } else if (lower.includes('bulb one') || lower.includes('light one') || lower.includes('first')) {
                    return { type: 'turn_off', bulb: 1 };
                } else if (lower.includes('bulb two') || lower.includes('light two') || lower.includes('second')) {
                    return { type: 'turn_off', bulb: 2 };
                } else if (lower.includes('bulb three') || lower.includes('light three') || lower.includes('third')) {
                    return { type: 'turn_off', bulb: 3 };
                } else if (lower.includes('all') || lower.includes('every')) {
                    return { type: 'turn_off', all: true };
                }
            }
            
            // Brightness commands
            else if (lower.includes('brightness') || lower.includes('percent') || /\d+\s*\%/.test(transcript)) {
                const percentMatch = transcript.match(/(\d+)\s*\%/);
                const numberMatch = transcript.match(/\b(\d+)\b/);
                const value = percentMatch ? parseInt(percentMatch[1]) : (numberMatch ? parseInt(numberMatch[1]) : null);
                
                if (value !== null && value >= 0 && value <= 100) {
                    if (targetBulb) {
                        return { type: 'set_brightness', bulb: targetBulb, value: value };
                    } else if (lower.includes('bulb one') || lower.includes('light one')) {
                        return { type: 'set_brightness', bulb: 1, value: value };
                    } else if (lower.includes('bulb two') || lower.includes('light two')) {
                        return { type: 'set_brightness', bulb: 2, value: value };
                    } else if (lower.includes('bulb three') || lower.includes('light three')) {
                        return { type: 'set_brightness', bulb: 3, value: value };
                    } else {
                        return { type: 'set_brightness', all: true, value: value };
                    }
                }
            }
            
            // Effect commands
            else if (lower.includes('strobe') || lower.includes('flash')) {
                return { type: 'start_effect', effect: 'strobe' };
            } else if (lower.includes('fade')) {
                return { type: 'start_effect', effect: 'fade' };
            } else if (lower.includes('pulse') || lower.includes('breathe')) {
                return { type: 'start_effect', effect: 'pulse' };
            } else if (lower.includes('alternate')) {
                return { type: 'start_effect', effect: 'alternate' };
            } else if (lower.includes('rainbow')) {
                return { type: 'start_effect', effect: 'rainbow' };
            }
            
            // Stop commands
            else if (lower.includes('stop') || lower.includes('off all')) {
                return { type: 'stop_effects' };
            }
            
            // Simple percentage commands (e.g., "fifty percent")
            else if (/(\d+)\s*percent/i.test(lower) || /(\w+)\s*percent/i.test(lower)) {
                const numbers = extractNumbers(lower);
                if (numbers.length > 0) {
                    const value = numbers[0];
                    if (targetBulb) {
                        return { type: 'set_brightness', bulb: targetBulb, value: value };
                    } else {
                        return { type: 'set_brightness', all: true, value: value };
                    }
                }
            }
            
            return null;
        }
        
        // Extract numbers from text
        function extractNumbers(text) {
            const numberMap = {
                'zero': 0, 'one': 1, 'two': 2, 'three': 3, 'four': 4, 'five': 5,
                'six': 6, 'seven': 7, 'eight': 8, 'nine': 9, 'ten': 10,
                'twenty': 20, 'thirty': 30, 'forty': 40, 'fifty': 50,
                'sixty': 60, 'seventy': 70, 'eighty': 80, 'ninety': 90,
                'hundred': 100, 'full': 100, 'maximum': 100, 'max': 100,
                'half': 50, 'quarter': 25
            };
            
            const numbers = [];
            const words = text.toLowerCase().split(/\s+/);
            
            for (const word of words) {
                if (numberMap[word] !== undefined) {
                    numbers.push(numberMap[word]);
                }
            }
            
            // Also look for numeric patterns
            const numericPattern = /(\d+)\s*\%?/g;
            let match;
            while ((match = numericPattern.exec(text)) !== null) {
                const num = parseInt(match[1]);
                if (!isNaN(num) && num >= 0 && num <= 100) {
                    numbers.push(num);
                }
            }
            
            return numbers;
        }
        
        // Execute parsed command
        function executeParsedCommand(command) {
            switch(command.type) {
                case 'turn_on':
                    if (command.bulb) {
                        controlBulb(command.bulb, 'on');
                    } else if (command.all) {
                        groupControl('on');
                    }
                    break;
                    
                case 'turn_off':
                    if (command.bulb) {
                        controlBulb(command.bulb, 'off');
                    } else if (command.all) {
                        groupControl('off');
                    }
                    break;
                    
                case 'set_brightness':
                    if (command.bulb && command.value !== undefined) {
                        controlBulb(command.bulb, 'brightness', command.value);
                    } else if (command.all && command.value !== undefined) {
                        groupControl('brightness', command.value);
                    }
                    break;
                    
                case 'start_effect':
                    if (command.effect) {
                        startEffect(command.effect);
                    }
                    break;
                    
                case 'stop_effects':
                    stopEffects();
                    break;
            }
        }
        
        // Update voice status
        function updateVoiceStatus(status) {
            const statusText = document.getElementById('voice-status-text');
            const feedback = document.getElementById('voice-feedback');
            const toggleBtn = document.getElementById('voice-toggle');
            const badge = document.getElementById('voice-status-badge');
            
            if (statusText) {
                const statusMessages = {
                    'ready': 'Voice Ready',
                    'listening': 'Listening...',
                    'unsupported': 'Not Supported'
                };
                statusText.textContent = statusMessages[status] || 'Voice Control';
            }
            
            if (feedback) {
                feedback.className = 'voice-feedback';
                if (status === 'listening') {
                    feedback.classList.add('listening');
                } else if (status === 'ready') {
                    feedback.classList.add('ready');
                }
            }
            
            if (toggleBtn) {
                toggleBtn.className = 'voice-toggle-btn';
                if (status === 'listening') {
                    toggleBtn.classList.add('listening');
                }
            }
            
            if (badge) {
                if (status === 'listening') {
                    badge.innerHTML = '<i class="fas fa-microphone"></i> Listening';
                    badge.className = 'badge bg-warning ms-2';
                } else if (status === 'ready') {
                    badge.innerHTML = '<i class="fas fa-microphone"></i> Voice Ready';
                    badge.className = 'badge bg-info ms-2';
                } else {
                    badge.innerHTML = '<i class="fas fa-microphone-slash"></i> No Voice';
                    badge.className = 'badge bg-secondary ms-2';
                }
            }
        }
        
        // ==================== TEXT COMMAND FUNCTIONS ====================
        
        // Toggle text input
        function toggleTextInput() {
            const fallback = document.getElementById('voice-text-fallback');
            if (fallback.style.display === 'none') {
                fallback.style.display = 'block';
                document.getElementById('voice-text-input').focus();
            } else {
                fallback.style.display = 'none';
            }
        }
        
        // Process text command
        function processTextCommand() {
            const input = document.getElementById('voice-text-input');
            const command = input.value.trim();
            
            if (!command) {
                showToast('Please enter a command', 'warning');
                return;
            }
            
            console.log('Processing text command:', command);
            
            // Parse and execute
            const parsed = parseVoiceCommand(command);
            
            if (parsed) {
                executeParsedCommand(parsed);
                showToast(`Command: "${command}"`, 'success');
                input.value = '';
                toggleTextInput();
            } else {
                showToast(`Could not understand: "${command}"`, 'danger');
            }
        }
        
        // Toggle command list
        function toggleCommandList() {
            const list = document.getElementById('voice-commands-list');
            list.classList.toggle('show');
        }
        
        // ==================== UTILITY FUNCTIONS ====================
        
        // Get action text
        function getActionText(action, value) {
            switch(action) {
                case 'on': return 'turned ON';
                case 'off': return 'turned OFF';
                case 'brightness': return `set to ${value}%`;
                default: return action;
            }
        }
        
        // Show toast notification
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            if (!container) return;
            
            const toast = document.createElement('div');
            toast.className = `toast show mb-2`;
            toast.innerHTML = `
                <div class="toast-header bg-${type} text-white">
                    <strong class="me-auto">
                        <i class="fas fa-lightbulb"></i> Smart Control
                    </strong>
                    <button type="button" class="btn-close btn-close-white" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
                <div class="toast-body bg-white">
                    ${message}
                </div>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentNode) toast.remove();
            }, 3000);
        }
        
        // Show voice toast
        function showVoiceToast(title, message) {
            const toast = document.getElementById('voice-command-toast');
            const titleEl = document.getElementById('voice-command-text');
            const messageEl = document.getElementById('voice-command-action');
            
            if (toast && titleEl && messageEl) {
                titleEl.textContent = title;
                messageEl.textContent = message;
                toast.classList.add('show');
                
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }
        }
        
        // Close voice toast
        function closeVoiceToast() {
            const toast = document.getElementById('voice-command-toast');
            if (toast) toast.classList.remove('show');
        }
        
        // Make functions globally available
        window.controlBulb = controlBulb;
        window.groupControl = groupControl;
        window.setGroupBrightness = setGroupBrightness;
        window.updateStatus = updateStatus;
        window.startEffect = startEffect;
        window.stopEffects = stopEffects;
        window.updateStrobeSpeed = updateStrobeSpeed;
        window.toggleVoiceControl = toggleVoiceControl;
        window.startVoiceForBulb = startVoiceForBulb;
        window.startVoiceForAll = startVoiceForAll;
        window.toggleTextInput = toggleTextInput;
        window.processTextCommand = processTextCommand;
        window.toggleCommandList = toggleCommandList;
        window.closeVoiceToast = closeVoiceToast;
        window.showToast = showToast;
    </script>
</body>
</html>