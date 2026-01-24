<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Bulb Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bulb1-color: #ff6b6b;
            --bulb2-color: #4ecdc4;
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body {
            background: #f5f7fb;
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        .glass-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
        }
        
        .bulb-container {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto;
        }
        
        .bulb-icon {
            font-size: 4rem;
            color: #ddd;
            transition: all 0.3s ease;
            filter: drop-shadow(0 5px 15px rgba(0,0,0,0.1));
        }
        
        .bulb-on .bulb-icon {
            color: currentColor;
            filter: drop-shadow(0 0 30px currentColor);
            animation: gentleGlow 2s infinite alternate;
        }
        
        @keyframes gentleGlow {
            0% { opacity: 0.9; }
            100% { opacity: 1; }
        }
        
        .bulb1-color { color: var(--bulb1-color); }
        .bulb2-color { color: var(--bulb2-color); }
        
        .control-btn {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.2s ease;
        }
        
        .control-btn:hover {
            transform: scale(1.1);
        }
        
        .control-btn:active {
            transform: scale(0.95);
        }
        
        .btn-on { background: linear-gradient(135deg, #4CAF50, #2E7D32); color: white; }
        .btn-off { background: linear-gradient(135deg, #f44336, #c62828); color: white; }
        
        .brightness-control {
            padding: 20px 0;
        }
        
        .brightness-slider {
            -webkit-appearance: none;
            width: 100%;
            height: 12px;
            border-radius: 6px;
            background: linear-gradient(to right, #000, #fff);
            outline: none;
            transition: background 0.3s;
        }
        
        .brightness-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: white;
            border: 3px solid #667eea;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            transition: all 0.2s;
        }
        
        .brightness-slider::-webkit-slider-thumb:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .effect-icon {
            font-size: 1.8rem;
            margin-bottom: 8px;
        }
        
        .effect-btn {
            padding: 15px 10px;
            border-radius: 15px;
            border: 2px solid #e0e0e0;
            background: white;
            color: #666;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .effect-btn:hover {
            border-color: #667eea;
            background: #f0f4ff;
            color: #667eea;
        }
        
        .effect-btn.active {
            border-color: #667eea;
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }
        
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .connected { background: #4CAF50; animation: pulse 2s infinite; }
        .disconnected { background: #f44336; }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="text-center mb-4">
            <h1 class="fw-bold" style="color: #333;">
                <i class="fas fa-lightbulb me-2"></i>Smart Bulb Control
            </h1>
            <p class="text-muted">Control your Arduino-connected bulbs</p>
        </div>
        
        <!-- Connection Status -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="glass-card p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="status-dot" id="connection-dot"></span>
                            <span class="fw-semibold" id="connection-status">Disconnected</span>
                            <span class="text-muted ms-3" id="active-mode">• Manual Mode</span>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="updateStatus()" id="refresh-btn">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Bulb 1 Control -->
            <div class="col-lg-6">
                <div class="glass-card p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bulb1-color me-2">
                            <i class="fas fa-lightbulb fa-lg"></i>
                        </div>
                        <h4 class="mb-0 fw-semibold">Bulb 1</h4>
                        <span class="badge bg-light text-dark ms-auto">Pin 9</span>
                    </div>
                    
                    <!-- Bulb Visual -->
                    <div class="bulb-container">
                        <div class="bulb1-color" id="bulb1-visual">
                            <i class="fas fa-lightbulb bulb-icon"></i>
                        </div>
                    </div>
                    
                    <!-- Brightness Indicator -->
                    <div class="text-center mb-3">
                        <div class="display-6 fw-bold bulb1-color" id="bulb1-percentage">0%</div>
                        <div class="text-muted small">Brightness</div>
                    </div>
                    
                    <!-- Brightness Slider -->
                    <div class="brightness-control">
                        <input type="range" class="brightness-slider" id="bulb1-slider" 
                               min="0" max="100" value="0">
                    </div>
                    
                    <!-- Power Controls -->
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <button class="control-btn btn-on" onclick="controlBulb(1, 'on')" 
                                title="Turn On">
                            <i class="fas fa-power-off"></i>
                        </button>
                        <button class="control-btn btn-off" onclick="controlBulb(1, 'off')"
                                title="Turn Off">
                            <i class="fas fa-power-off"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Bulb 2 Control -->
            <div class="col-lg-6">
                <div class="glass-card p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bulb2-color me-2">
                            <i class="fas fa-lightbulb fa-lg"></i>
                        </div>
                        <h4 class="mb-0 fw-semibold">Bulb 2</h4>
                        <span class="badge bg-light text-dark ms-auto">Pin 10</span>
                    </div>
                    
                    <!-- Bulb Visual -->
                    <div class="bulb-container">
                        <div class="bulb2-color" id="bulb2-visual">
                            <i class="fas fa-lightbulb bulb-icon"></i>
                        </div>
                    </div>
                    
                    <!-- Brightness Indicator -->
                    <div class="text-center mb-3">
                        <div class="display-6 fw-bold bulb2-color" id="bulb2-percentage">0%</div>
                        <div class="text-muted small">Brightness</div>
                    </div>
                    
                    <!-- Brightness Slider -->
                    <div class="brightness-control">
                        <input type="range" class="brightness-slider" id="bulb2-slider" 
                               min="0" max="100" value="0">
                    </div>
                    
                    <!-- Power Controls -->
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <button class="control-btn btn-on" onclick="controlBulb(2, 'on')"
                                title="Turn On">
                            <i class="fas fa-power-off"></i>
                        </button>
                        <button class="control-btn btn-off" onclick="controlBulb(2, 'off')"
                                title="Turn Off">
                            <i class="fas fa-power-off"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Effects Panel -->
            <div class="col-12">
                <div class="glass-card p-4">
                    <h5 class="fw-semibold mb-4">
                        <i class="fas fa-magic me-2"></i>Lighting Effects
                    </h5>
                    
                    <div class="row g-3">
                        <div class="col-md-2 col-4">
                            <button class="effect-btn w-100" onclick="startEffect('alternate')" id="effect-alt">
                                <div class="effect-icon">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <small>Alternate</small>
                            </button>
                        </div>
                        <div class="col-md-2 col-4">
                            <button class="effect-btn w-100" onclick="startEffect('strobe')" id="effect-strobe">
                                <div class="effect-icon">
                                    <i class="fas fa-bolt"></i>
                                </div>
                                <small>Strobe</small>
                            </button>
                        </div>
                        <div class="col-md-2 col-4">
                            <button class="effect-btn w-100" onclick="startEffect('fade')" id="effect-fade">
                                <div class="effect-icon">
                                    <i class="fas fa-wave-square"></i>
                                </div>
                                <small>Fade</small>
                            </button>
                        </div>
                        <div class="col-md-2 col-4">
                            <button class="effect-btn w-100" onclick="startEffect('pulse')" id="effect-pulse">
                                <div class="effect-icon">
                                    <i class="fas fa-heartbeat"></i>
                                </div>
                                <small>Pulse</small>
                            </button>
                        </div>
                        <div class="col-md-2 col-4">
                            <button class="effect-btn w-100" onclick="startEffect('rainbow')" id="effect-rainbow">
                                <div class="effect-icon">
                                    <i class="fas fa-rainbow"></i>
                                </div>
                                <small>Rainbow</small>
                            </button>
                        </div>
                        <div class="col-md-2 col-4">
                            <button class="effect-btn w-100" onclick="stopEffects()"
                                    style="border-color: #f44336; color: #f44336;">
                                <div class="effect-icon">
                                    <i class="fas fa-stop"></i>
                                </div>
                                <small>Stop All</small>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Strobe Speed Control (Hidden by default) -->
                    <div class="row align-items-center mt-3" id="strobe-controls" style="display: none;">
                        <div class="col-md-3">
                            <label class="form-label">Strobe Speed</label>
                        </div>
                        <div class="col-md-9">
                            <input type="range" class="form-range" id="strobe-speed" 
                                   min="1" max="5" value="3">
                            <div class="d-flex justify-content-between">
                                <small>Slow</small>
                                <small>Medium</small>
                                <small>Fast</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Group Controls -->
            <div class="col-12">
                <div class="glass-card p-4">
                    <h5 class="fw-semibold mb-4">
                        <i class="fas fa-object-group me-2"></i>Group Controls
                    </h5>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <button class="btn btn-success w-100 py-3" onclick="groupControl('on')">
                                <i class="fas fa-lightbulb me-2"></i>Both ON
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-secondary w-100 py-3" onclick="groupControl('off')">
                                <i class="fas fa-lightbulb me-2"></i>Both OFF
                            </button>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="number" class="form-control" id="group-brightness" 
                                       min="0" max="100" placeholder="Brightness %">
                                <button class="btn btn-primary" onclick="setGroupBrightness()">
                                    <i class="fas fa-sliders-h"></i> Set
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Status Info -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="glass-card p-3">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="p-2">
                                <div class="text-muted small">Power Usage</div>
                                <div class="fw-bold" id="power-usage">0W</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2">
                                <div class="text-muted small">Last Update</div>
                                <div class="fw-bold" id="last-update">--:--:--</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2">
                                <div class="text-muted small">Bulb 1 State</div>
                                <div class="fw-bold" id="bulb1-state">OFF</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2">
                                <div class="text-muted small">Bulb 2 State</div>
                                <div class="fw-bold" id="bulb2-state">OFF</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container"></div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        const csrfToken = '{{ csrf_token() }}';
        let currentState = {
            bulb1: { state: 'off', brightness: 0 },
            bulb2: { state: 'off', brightness: 0 },
            mode: 'manual',
            connected: false
        };
        
        // Slider state tracking
        let sliderIsChanging = { 1: false, 2: false };
        let sliderTimeout = { 1: null, 2: null };
        let lastSliderValue = { 1: 0, 2: 0 };
        let isUpdatingStatus = false;
        let lastStatusUpdate = 0;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing Smart Bulb Control...');
            
            // Setup slider event listeners
            setupSliders();
            
            // Setup strobe speed slider
            document.getElementById('strobe-speed').addEventListener('change', function() {
                updateStrobeSpeed(this.value);
            });
            
            // Initial status update
            updateStatus();
            
            // Auto-refresh every 5 seconds (not too frequent)
            setInterval(updateStatus, 5000);
            
            // Update time display every minute
            setInterval(() => {
                document.getElementById('last-update').textContent = 
                    new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            }, 60000);
        });
        
        // Setup slider event listeners
        function setupSliders() {
            const slider1 = document.getElementById('bulb1-slider');
            const slider2 = document.getElementById('bulb2-slider');
            
            // Bulb 1 slider
            slider1.addEventListener('input', function() {
                if (!sliderIsChanging[1]) {
                    sliderIsChanging[1] = true;
                    const value = parseInt(this.value);
                    
                    // Update display immediately
                    document.getElementById('bulb1-percentage').textContent = `${value}%`;
                    
                    // Debounce the API call
                    clearTimeout(sliderTimeout[1]);
                    sliderTimeout[1] = setTimeout(() => {
                        if (value !== lastSliderValue[1]) {
                            controlBulb(1, 'brightness', value);
                            lastSliderValue[1] = value;
                        }
                        sliderIsChanging[1] = false;
                    }, 300); // 300ms debounce
                }
            });
            
            // Bulb 2 slider
            slider2.addEventListener('input', function() {
                if (!sliderIsChanging[2]) {
                    sliderIsChanging[2] = true;
                    const value = parseInt(this.value);
                    
                    // Update display immediately
                    document.getElementById('bulb2-percentage').textContent = `${value}%`;
                    
                    // Debounce the API call
                    clearTimeout(sliderTimeout[2]);
                    sliderTimeout[2] = setTimeout(() => {
                        if (value !== lastSliderValue[2]) {
                            controlBulb(2, 'brightness', value);
                            lastSliderValue[2] = value;
                        }
                        sliderIsChanging[2] = false;
                    }, 300); // 300ms debounce
                }
            });
            
            // Add mouseup event to send immediately when user stops dragging
            slider1.addEventListener('mouseup', function() {
                clearTimeout(sliderTimeout[1]);
                const value = parseInt(this.value);
                if (value !== lastSliderValue[1]) {
                    controlBulb(1, 'brightness', value);
                    lastSliderValue[1] = value;
                }
                sliderIsChanging[1] = false;
            });
            
            slider2.addEventListener('mouseup', function() {
                clearTimeout(sliderTimeout[2]);
                const value = parseInt(this.value);
                if (value !== lastSliderValue[2]) {
                    controlBulb(2, 'brightness', value);
                    lastSliderValue[2] = value;
                }
                sliderIsChanging[2] = false;
            });
            
            // Add touchend for mobile
            slider1.addEventListener('touchend', function() {
                clearTimeout(sliderTimeout[1]);
                const value = parseInt(this.value);
                if (value !== lastSliderValue[1]) {
                    controlBulb(1, 'brightness', value);
                    lastSliderValue[1] = value;
                }
                sliderIsChanging[1] = false;
            });
            
            slider2.addEventListener('touchend', function() {
                clearTimeout(sliderTimeout[2]);
                const value = parseInt(this.value);
                if (value !== lastSliderValue[2]) {
                    controlBulb(2, 'brightness', value);
                    lastSliderValue[2] = value;
                }
                sliderIsChanging[2] = false;
            });
        }
        
        // Control individual bulb
        async function controlBulb(bulb, action, value = null) {
            if (isUpdatingStatus) return; // Prevent conflicts
            
            try {
                const response = await fetch('/api/bulb/control', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        bulb: bulb,
                        action: action,
                        value: value
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(`Bulb ${bulb} ${getActionText(action, value)}`, 'success');
                    
                    // Update UI from response
                    if (data.state) {
                        updateUIFromState(data.state);
                    }
                    
                    // Update local state
                    updateLocalState(data);
                } else {
                    showToast(`Error: ${data.error || 'Unknown error'}`, 'danger');
                }
            } catch (error) {
                console.error('Control bulb error:', error);
                showToast('Network error', 'danger');
            }
        }
        
        // Get readable action text
        function getActionText(action, value) {
            switch(action) {
                case 'on': return 'turned ON';
                case 'off': return 'turned OFF';
                case 'brightness': return `set to ${value}%`;
                default: return action;
            }
        }
        
        // Update local state from response
        function updateLocalState(data) {
            if (data.state) {
                currentState = {
                    ...currentState,
                    ...data.state,
                    connected: data.connected || false
                };
            }
            
            // Update connection status
            updateConnectionStatus(data.connected || false);
        }
        
        // Start effect
        async function startEffect(effect, speed = null) {
            // Reset all effect buttons
            document.querySelectorAll('.effect-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Set active button
            document.getElementById(`effect-${effect}`).classList.add('active');
            
            // Show/hide strobe controls
            if (effect === 'strobe') {
                document.getElementById('strobe-controls').style.display = 'flex';
                if (speed) {
                    document.getElementById('strobe-speed').value = speed;
                }
            } else {
                document.getElementById('strobe-controls').style.display = 'none';
            }
            
            try {
                const response = await fetch('/api/effect/control', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        effect: effect,
                        speed: speed || document.getElementById('strobe-speed').value
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(`${effect.charAt(0).toUpperCase() + effect.slice(1)} effect started`, 'success');
                    updateLocalState(data);
                } else {
                    showToast(`Error: ${data.error || 'Unknown error'}`, 'danger');
                    // Reset button if failed
                    document.getElementById(`effect-${effect}`).classList.remove('active');
                }
            } catch (error) {
                console.error('Start effect error:', error);
                showToast('Network error', 'danger');
                document.getElementById(`effect-${effect}`).classList.remove('active');
            }
        }
        
        // Stop all effects
        async function stopEffects() {
            try {
                const response = await fetch('/api/effect/control', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        effect: 'stop'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('All effects stopped', 'info');
                    document.querySelectorAll('.effect-btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    document.getElementById('strobe-controls').style.display = 'none';
                    updateLocalState(data);
                } else {
                    showToast(`Error: ${data.error || 'Unknown error'}`, 'danger');
                }
            } catch (error) {
                console.error('Stop effects error:', error);
                showToast('Network error', 'danger');
            }
        }
        
        // Update strobe speed
        function updateStrobeSpeed(speed) {
            if (currentState.mode === 'strobe') {
                startEffect('strobe', parseInt(speed));
            }
        }
        
        // Group control
        async function groupControl(action, brightness = null) {
            try {
                const data = {
                    action: action
                };
                
                if (brightness !== null) {
                    data.brightness = brightness;
                }
                
                const response = await fetch('/api/group/control', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(`Both bulbs ${getActionText(action, brightness)}`, 'success');
                    updateLocalState(result);
                } else {
                    showToast(`Error: ${result.error || 'Unknown error'}`, 'danger');
                }
            } catch (error) {
                console.error('Group control error:', error);
                showToast('Network error', 'danger');
            }
        }
        
        // Set group brightness
        function setGroupBrightness() {
            const brightnessInput = document.getElementById('group-brightness');
            const brightness = parseInt(brightnessInput.value);
            
            if (!isNaN(brightness) && brightness >= 0 && brightness <= 100) {
                groupControl('brightness', brightness);
                brightnessInput.value = '';
            } else {
                showToast('Please enter a brightness between 0-100', 'warning');
            }
        }
        
        // Update system status
        async function updateStatus() {
            // Prevent multiple simultaneous updates
            if (isUpdatingStatus) return;
            
            // Throttle updates (minimum 1 second between updates)
            const now = Date.now();
            if (now - lastStatusUpdate < 1000) return;
            
            isUpdatingStatus = true;
            lastStatusUpdate = now;
            
            try {
                const response = await fetch('/api/status');
                const data = await response.json();
                
                if (data.success) {
                    // Update connection status
                    updateConnectionStatus(data.connected || false);
                    
                    // Update current state
                    currentState = {
                        ...currentState,
                        ...data.state,
                        connected: data.connected || false
                    };
                    
                    // Update UI (but don't interfere with user dragging sliders)
                    updateUIFromState(data.state);
                    
                    // Update timestamp
                    document.getElementById('last-update').textContent = 
                        new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    
                    // Update power usage
                    updatePowerUsage(data.state);
                    
                } else {
                    updateConnectionStatus(false);
                    showToast(`Status error: ${data.error || 'Unknown error'}`, 'warning');
                }
            } catch (error) {
                console.error('Update status error:', error);
                updateConnectionStatus(false);
            } finally {
                isUpdatingStatus = false;
            }
        }
        
        // Update UI from state (without interfering with user interactions)
        function updateUIFromState(state) {
            if (!state) return;
            
            // Only update sliders if user is not currently interacting with them
            if (!sliderIsChanging[1]) {
                updateBulbUI(1, state.bulb1);
            }
            
            if (!sliderIsChanging[2]) {
                updateBulbUI(2, state.bulb2);
            }
            
            // Update mode display
            document.getElementById('active-mode').textContent = 
                `• ${state.mode.charAt(0).toUpperCase() + state.mode.slice(1)} Mode`;
            
            // Update effect buttons based on mode
            updateEffectButtons(state.mode);
        }
        
        // Update individual bulb UI
        function updateBulbUI(bulbNum, bulbState) {
            const visual = document.getElementById(`bulb${bulbNum}-visual`);
            const percentage = document.getElementById(`bulb${bulbNum}-percentage`);
            const slider = document.getElementById(`bulb${bulbNum}-slider`);
            const stateText = document.getElementById(`bulb${bulbNum}-state`);
            
            if (bulbState.state === 'on') {
                visual.classList.add('bulb-on');
                percentage.textContent = `${bulbState.brightness}%`;
                slider.value = bulbState.brightness;
                lastSliderValue[bulbNum] = bulbState.brightness;
                stateText.textContent = 'ON';
                stateText.style.color = bulbNum === 1 ? 'var(--bulb1-color)' : 'var(--bulb2-color)';
            } else {
                visual.classList.remove('bulb-on');
                percentage.textContent = '0%';
                slider.value = 0;
                lastSliderValue[bulbNum] = 0;
                stateText.textContent = 'OFF';
                stateText.style.color = '#666';
            }
        }
        
        // Update effect buttons based on current mode
        function updateEffectButtons(mode) {
            // Remove active class from all effect buttons
            document.querySelectorAll('.effect-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Add active class to current mode button (if not manual)
            if (mode !== 'manual' && mode !== 'stop') {
                const effectBtn = document.getElementById(`effect-${mode}`);
                if (effectBtn) {
                    effectBtn.classList.add('active');
                }
                
                // Show/hide strobe controls
                if (mode === 'strobe') {
                    document.getElementById('strobe-controls').style.display = 'flex';
                } else {
                    document.getElementById('strobe-controls').style.display = 'none';
                }
            } else {
                document.getElementById('strobe-controls').style.display = 'none';
            }
        }
        
        // Update connection status
        function updateConnectionStatus(isConnected) {
            const dot = document.getElementById('connection-dot');
            const statusText = document.getElementById('connection-status');
            
            if (isConnected) {
                dot.className = 'status-dot connected';
                statusText.textContent = 'Connected';
                statusText.style.color = '#4CAF50';
            } else {
                dot.className = 'status-dot disconnected';
                statusText.textContent = 'Disconnected';
                statusText.style.color = '#f44336';
            }
            
            currentState.connected = isConnected;
        }
        
        // Update power usage
        function updatePowerUsage(state) {
            if (!state) return;
            
            let power = 0;
            if (state.bulb1 && state.bulb1.state === 'on') {
                power += 5 * (state.bulb1.brightness / 100);
            }
            if (state.bulb2 && state.bulb2.state === 'on') {
                power += 5 * (state.bulb2.brightness / 100);
            }
            
            document.getElementById('power-usage').textContent = `${power.toFixed(1)}W`;
        }
        
        // Show toast notification
        function showToast(message, type = 'info') {
            const toastId = 'toast-' + Date.now();
            const toast = document.createElement('div');
            toast.className = `toast show`;
            toast.innerHTML = `
                <div class="toast-header bg-${type} text-white border-0">
                    <strong class="me-auto">
                        <i class="fas fa-lightbulb"></i> Smart Control
                    </strong>
                    <button type="button" class="btn-close btn-close-white" onclick="this.closest('.toast').remove()"></button>
                </div>
                <div class="toast-body bg-white">${message}</div>
            `;
            
            document.querySelector('.toast-container').appendChild(toast);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 3000);
        }
    </script>
</body>
</html>