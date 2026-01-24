// Main Application Controller
class SmartBulbControl {
    constructor() {
        // Get CSRF token from meta tag or cookies
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        // Fallback: get from cookies if meta tag not found
        if (!this.csrfToken) {
            this.csrfToken = this.getCookie('XSRF-TOKEN');
        }
        
        console.log('SmartBulbControl: CSRF Token', this.csrfToken ? 'Found' : 'Not found');
        
        // Current state
        this.currentState = {
            bulb1: { state: 'off', brightness: 0 },
            bulb2: { state: 'off', brightness: 0 },
            bulb3: { state: 'off', brightness: 0 },
            mode: 'manual',
            connected: false
        };
        
        // Slider tracking
        this.sliderIsChanging = { 1: false, 2: false, 3: false };
        this.sliderTimeout = { 1: null, 2: null, 3: null };
        this.lastSliderValue = { 1: 0, 2: 0, 3: 0 };
        this.isUpdatingStatus = false;
        this.lastStatusUpdate = 0;
        
        // Base URL
        this.baseUrl = window.location.origin;
        console.log('SmartBulbControl: Base URL', this.baseUrl);
        
        // API endpoints
        this.apiEndpoints = {
            bulbControl: '/api/bulb/control',
            effectControl: '/api/effect/control',
            groupControl: '/api/group/control',
            status: '/api/status',
            command: '/api/command',
            voice: '/api/voice'
        };
        
        this.voiceController = null;
    }
    
    // Get cookie value
    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }
    
    // Initialize
    init() {
        console.log('SmartBulbControl: Initializing...');
        
        this.setupSliders();
        this.setupEventListeners();
        this.updateStatus();
        
        // Auto-refresh every 5 seconds
        setInterval(() => this.updateStatus(), 5000);
        
        // Update time display every second
        setInterval(() => this.updateTimeDisplay(), 1000);
        
        console.log('SmartBulbControl: Initialized successfully');
        
        // Show welcome message
        setTimeout(() => {
            this.showToast('Smart Bulb Control Ready', 'success');
        }, 1000);
    }
    
    // Setup sliders
    setupSliders() {
        for (let i = 1; i <= 3; i++) {
            const slider = document.getElementById(`bulb${i}-slider`);
            if (!slider) {
                console.error(`Slider for bulb ${i} not found`);
                continue;
            }
            
            slider.addEventListener('input', () => this.handleSliderInput(i));
            
            // Add mouseup and touchend events
            ['mouseup', 'touchend'].forEach(eventType => {
                slider.addEventListener(eventType, () => this.handleSliderEnd(i));
            });
        }
    }
    
    // Setup event listeners
    setupEventListeners() {
        // Strobe speed slider
        const strobeSpeedSlider = document.getElementById('strobe-speed');
        if (strobeSpeedSlider) {
            strobeSpeedSlider.addEventListener('change', (e) => {
                this.updateStrobeSpeed(parseInt(e.target.value));
            });
        }
        
        // Group brightness input - handle Enter key
        const groupBrightnessInput = document.getElementById('group-brightness');
        if (groupBrightnessInput) {
            groupBrightnessInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.setGroupBrightness();
                }
            });
        }
        
        // Voice text input - handle Enter key
        const voiceTextInput = document.getElementById('voice-text-input');
        if (voiceTextInput) {
            voiceTextInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    processTextCommand();
                }
            });
        }
    }
    
    // Handle slider input
    handleSliderInput(bulbNum) {
        if (this.sliderIsChanging[bulbNum]) return;
        
        this.sliderIsChanging[bulbNum] = true;
        const slider = document.getElementById(`bulb${bulbNum}-slider`);
        const value = parseInt(slider.value);
        
        // Update display immediately
        const percentageElement = document.getElementById(`bulb${bulbNum}-percentage`);
        if (percentageElement) {
            percentageElement.textContent = `${value}%`;
        }
        
        // Debounce the API call
        clearTimeout(this.sliderTimeout[bulbNum]);
        this.sliderTimeout[bulbNum] = setTimeout(() => {
            if (value !== this.lastSliderValue[bulbNum]) {
                this.controlBulb(bulbNum, 'brightness', value);
                this.lastSliderValue[bulbNum] = value;
            }
            this.sliderIsChanging[bulbNum] = false;
        }, 300);
    }
    
    // Handle slider end
    handleSliderEnd(bulbNum) {
        clearTimeout(this.sliderTimeout[bulbNum]);
        const slider = document.getElementById(`bulb${bulbNum}-slider`);
        const value = parseInt(slider.value);
        
        if (value !== this.lastSliderValue[bulbNum]) {
            this.controlBulb(bulbNum, 'brightness', value);
            this.lastSliderValue[bulbNum] = value;
        }
        this.sliderIsChanging[bulbNum] = false;
    }
    
    // Control bulb
    async controlBulb(bulb, action, value = null) {
        if (this.isUpdatingStatus) return;
        
        console.log(`Control Bulb ${bulb}: ${action} ${value || ''}`);
        
        try {
            const endpoint = `${this.baseUrl}${this.apiEndpoints.bulbControl}`;
            console.log('Request URL:', endpoint);
            
            const requestData = {
                bulb: bulb,
                action: action
            };
            
            if (value !== null) {
                requestData.value = value;
            }
            
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestData)
            });
            
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error: ${response.status} ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('Response data:', data);
            
            if (data.success) {
                this.showToast(`Bulb ${bulb} ${this.getActionText(action, value)}`, 'success');
                
                // Update UI
                if (data.state) {
                    this.updateUIFromResponse(data);
                }
                
                // Update local state
                this.updateLocalState(data);
            } else {
                this.showToast(`Error: ${data.error || 'Unknown error'}`, 'danger');
            }
        } catch (error) {
            console.error('Control bulb error:', error);
            this.showToast(`Network error: ${error.message}`, 'danger');
        }
    }
    
    // Get action text
    getActionText(action, value) {
        switch(action) {
            case 'on': return 'turned ON';
            case 'off': return 'turned OFF';
            case 'brightness': return `set to ${value}%`;
            default: return action;
        }
    }
    
    // Update UI from response
    updateUIFromResponse(data) {
        if (!data.state) return;
        
        // Handle individual bulb state
        if (typeof data.state === 'object' && !data.state.state) {
            const bulbMatch = Object.keys(data.state).find(key => key.startsWith('bulb'));
            if (bulbMatch) {
                const bulbNum = bulbMatch.replace('bulb', '');
                this.updateBulbUI(parseInt(bulbNum), data.state);
            }
        } 
        // Handle full state object
        else if (data.state.state && data.state.state.bulb1) {
            this.updateUIFromState(data.state.state);
        }
    }
    
    // Update local state
    updateLocalState(data) {
        if (data.state) {
            if (data.state.bulb1) {
                // Full state object
                this.currentState = {
                    ...this.currentState,
                    ...data.state,
                    connected: data.connected || false
                };
            } else if (typeof data.state === 'object') {
                // Individual bulb state
                const bulbMatch = Object.keys(data.state).find(key => key.startsWith('bulb'));
                if (bulbMatch) {
                    const bulbNum = bulbMatch.replace('bulb', '');
                    this.currentState[`bulb${bulbNum}`] = data.state;
                }
            }
            this.currentState.connected = data.connected || false;
        }
        
        // Update connection status
        this.updateConnectionStatus(data.connected || false);
    }
    
    // Start effect
    async startEffect(effect, speed = null) {
        console.log(`Start Effect: ${effect}, Speed: ${speed}`);
        
        // Reset all effect buttons
        document.querySelectorAll('.effect-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Set active button
        const effectButton = document.getElementById(`effect-${effect}`);
        if (effectButton) {
            effectButton.classList.add('active');
        }
        
        // Show/hide strobe controls
        const strobeControls = document.getElementById('strobe-controls');
        if (strobeControls) {
            strobeControls.style.display = effect === 'strobe' ? 'flex' : 'none';
            if (effect === 'strobe' && speed) {
                const strobeSpeedSlider = document.getElementById('strobe-speed');
                if (strobeSpeedSlider) {
                    strobeSpeedSlider.value = speed;
                }
            }
        }
        
        try {
            const endpoint = `${this.baseUrl}${this.apiEndpoints.effectControl}`;
            const requestData = {
                effect: effect
            };
            
            if (speed !== null) {
                requestData.speed = speed;
            } else if (effect === 'strobe') {
                const strobeSpeedSlider = document.getElementById('strobe-speed');
                requestData.speed = strobeSpeedSlider ? parseInt(strobeSpeedSlider.value) : 3;
            }
            
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestData)
            });
            
            console.log('Effect response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error: ${response.status} ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('Effect response data:', data);
            
            if (data.success) {
                const effectName = effect.charAt(0).toUpperCase() + effect.slice(1);
                this.showToast(`${effectName} effect started`, 'success');
                this.updateLocalState(data);
                this.updateEffectVisuals(effect);
            } else {
                this.showToast(`Error: ${data.error || 'Unknown error'}`, 'danger');
                if (effectButton) {
                    effectButton.classList.remove('active');
                }
            }
        } catch (error) {
            console.error('Start effect error:', error);
            this.showToast(`Network error: ${error.message}`, 'danger');
            if (effectButton) {
                effectButton.classList.remove('active');
            }
        }
    }
    
    // Update effect visuals
    updateEffectVisuals(effect) {
        // Remove all effect animations
        for (let i = 1; i <= 3; i++) {
            const visual = document.getElementById(`bulb${i}-visual`);
            if (visual) {
                visual.classList.remove('strobe-active', 'fade-active', 'pulse-active');
            }
        }
        
        // Add new effect animation
        const animationClass = {
            'strobe': 'strobe-active',
            'fade': 'fade-active',
            'pulse': 'pulse-active',
            'alternate': 'strobe-active',
            'rainbow': 'fade-active'
        }[effect];
        
        if (animationClass) {
            for (let i = 1; i <= 3; i++) {
                const visual = document.getElementById(`bulb${i}-visual`);
                if (visual) {
                    visual.classList.add(animationClass);
                }
            }
        }
    }
    
    // Stop effects
    async stopEffects() {
        console.log('Stopping all effects');
        
        try {
            const endpoint = `${this.baseUrl}${this.apiEndpoints.effectControl}`;
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    effect: 'stop'
                })
            });
            
            console.log('Stop effects response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error: ${response.status} ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('Stop effects response data:', data);
            
            if (data.success) {
                this.showToast('All effects stopped', 'info');
                
                // Remove active class from all effect buttons
                document.querySelectorAll('.effect-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Hide strobe controls
                const strobeControls = document.getElementById('strobe-controls');
                if (strobeControls) {
                    strobeControls.style.display = 'none';
                }
                
                // Update state
                this.updateLocalState(data);
                
                // Remove effect animations
                for (let i = 1; i <= 3; i++) {
                    const visual = document.getElementById(`bulb${i}-visual`);
                    if (visual) {
                        visual.classList.remove('strobe-active', 'fade-active', 'pulse-active');
                    }
                }
            } else {
                this.showToast(`Error: ${data.error || 'Unknown error'}`, 'danger');
            }
        } catch (error) {
            console.error('Stop effects error:', error);
            this.showToast(`Network error: ${error.message}`, 'danger');
        }
    }
    
    // Update strobe speed
    updateStrobeSpeed(speed) {
        if (this.currentState.mode === 'strobe') {
            this.startEffect('strobe', speed);
        }
    }
    
    // Group control
    async groupControl(action, brightness = null) {
        console.log(`Group Control: ${action}, Brightness: ${brightness}`);
        
        try {
            const endpoint = `${this.baseUrl}${this.apiEndpoints.groupControl}`;
            const requestData = {
                action: action
            };
            
            if (brightness !== null) {
                requestData.brightness = brightness;
            }
            
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestData)
            });
            
            console.log('Group control response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error: ${response.status} ${response.statusText}`);
            }
            
            const result = await response.json();
            console.log('Group control response data:', result);
            
            if (result.success) {
                this.showToast(`All bulbs ${this.getActionText(action, brightness)}`, 'success');
                this.updateLocalState(result);
            } else {
                this.showToast(`Error: ${result.error || 'Unknown error'}`, 'danger');
            }
        } catch (error) {
            console.error('Group control error:', error);
            this.showToast(`Network error: ${error.message}`, 'danger');
        }
    }
    
    // Set group brightness
    setGroupBrightness() {
        const brightnessInput = document.getElementById('group-brightness');
        if (!brightnessInput) return;
        
        const brightness = parseInt(brightnessInput.value);
        
        if (!isNaN(brightness) && brightness >= 0 && brightness <= 100) {
            this.groupControl('brightness', brightness);
            brightnessInput.value = '';
        } else {
            this.showToast('Please enter a brightness between 0-100', 'warning');
        }
    }
    
    // Update status
    async updateStatus() {
        if (this.isUpdatingStatus) return;
        
        const now = Date.now();
        if (now - this.lastStatusUpdate < 1000) return;
        
        this.isUpdatingStatus = true;
        this.lastStatusUpdate = now;
        
        try {
            const endpoint = `${this.baseUrl}${this.apiEndpoints.status}`;
            const response = await fetch(endpoint, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            console.log('Status response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error: ${response.status} ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('Status data received');
            
            if (data.success) {
                // Update connection status
                this.updateConnectionStatus(data.connected || false);
                
                // Update current state
                if (data.state) {
                    this.currentState = {
                        ...this.currentState,
                        ...data.state,
                        connected: data.connected || false
                    };
                    
                    // Update UI
                    this.updateUIFromState(data.state);
                    
                    // Update status displays
                    const statusTextElement = document.getElementById('connection-status-text');
                    if (statusTextElement) {
                        statusTextElement.textContent = data.connected ? 'Connected' : 'Disconnected';
                        statusTextElement.style.color = data.connected ? '#4CAF50' : '#f44336';
                    }
                    
                    const modeTextElement = document.getElementById('active-mode-text');
                    if (modeTextElement && data.state.mode) {
                        modeTextElement.textContent = 
                            data.state.mode.charAt(0).toUpperCase() + data.state.mode.slice(1);
                    }
                    
                    // Update power usage
                    this.updatePowerUsage(data.state);
                }
            } else {
                this.updateConnectionStatus(false);
            }
        } catch (error) {
            console.error('Update status error:', error);
            this.updateConnectionStatus(false);
        } finally {
            this.isUpdatingStatus = false;
        }
    }
    
    // Update UI from state
    updateUIFromState(state) {
        if (!state || typeof state !== 'object') return;
        
        // Update bulb UIs if not currently being changed by user
        if (!this.sliderIsChanging[1] && state.bulb1) {
            this.updateBulbUI(1, state.bulb1);
        }
        
        if (!this.sliderIsChanging[2] && state.bulb2) {
            this.updateBulbUI(2, state.bulb2);
        }
        
        if (!this.sliderIsChanging[3] && state.bulb3) {
            this.updateBulbUI(3, state.bulb3);
        }
        
        // Update mode display
        const modeElement = document.getElementById('active-mode');
        if (modeElement && state.mode) {
            modeElement.textContent = 
                `• ${state.mode.charAt(0).toUpperCase() + state.mode.slice(1)} Mode`;
        }
        
        // Update effect buttons
        if (state.mode) {
            this.updateEffectButtons(state.mode);
        }
    }
    
    // Update bulb UI
    updateBulbUI(bulbNum, bulbState) {
        if (!bulbState || typeof bulbState !== 'object') return;
        
        const visual = document.getElementById(`bulb${bulbNum}-visual`);
        const percentage = document.getElementById(`bulb${bulbNum}-percentage`);
        const slider = document.getElementById(`bulb${bulbNum}-slider`);
        
        if (!visual || !percentage || !slider) {
            console.error(`UI elements for bulb ${bulbNum} not found`);
            return;
        }
        
        if (bulbState.state === 'on') {
            visual.classList.add('bulb-on');
            percentage.textContent = `${bulbState.brightness || 0}%`;
            slider.value = bulbState.brightness || 0;
            this.lastSliderValue[bulbNum] = bulbState.brightness || 0;
        } else {
            visual.classList.remove('bulb-on');
            percentage.textContent = '0%';
            slider.value = 0;
            this.lastSliderValue[bulbNum] = 0;
        }
    }
    
    // Update effect buttons
    updateEffectButtons(mode) {
        if (!mode) return;
        
        // Remove active class from all effect buttons
        document.querySelectorAll('.effect-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Add active class to current mode button
        if (mode !== 'manual' && mode !== 'stop') {
            const effectBtn = document.getElementById(`effect-${mode}`);
            if (effectBtn) {
                effectBtn.classList.add('active');
            }
            
            // Show/hide strobe controls
            const strobeControls = document.getElementById('strobe-controls');
            if (strobeControls) {
                strobeControls.style.display = mode === 'strobe' ? 'flex' : 'none';
            }
            
            // Update effect visuals
            this.updateEffectVisuals(mode);
        } else {
            const strobeControls = document.getElementById('strobe-controls');
            if (strobeControls) {
                strobeControls.style.display = 'none';
            }
        }
    }
    
    // Update connection status
    updateConnectionStatus(isConnected) {
        const dot = document.getElementById('connection-dot');
        const statusText = document.getElementById('connection-status');
        
        if (dot) {
            dot.className = isConnected ? 'status-dot connected' : 'status-dot disconnected';
        }
        
        if (statusText) {
            statusText.textContent = isConnected ? 'Connected' : 'Disconnected';
            statusText.style.color = isConnected ? '#4CAF50' : '#f44336';
        }
        
        this.currentState.connected = isConnected;
    }
    
    // Update power usage
    updatePowerUsage(state) {
        if (!state || typeof state !== 'object') return;
        
        let power = 0;
        
        // Calculate power for each bulb
        [1, 2, 3].forEach(bulbNum => {
            const bulb = state[`bulb${bulbNum}`];
            if (bulb && bulb.state === 'on') {
                power += 5 * ((bulb.brightness || 0) / 100);
            }
        });
        
        const powerElement = document.getElementById('power-usage');
        if (powerElement) {
            powerElement.textContent = `${power.toFixed(1)}W`;
        }
    }
    
    // Update time display
    updateTimeDisplay() {
        const now = new Date();
        const timeElement = document.getElementById('last-update');
        if (timeElement) {
            timeElement.textContent = 
                now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'});
        }
    }
    
    // Show toast
    showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            console.error('Toast container not found');
            return;
        }
        
        const toastId = 'toast-' + Date.now();
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        const icon = type === 'success' ? 'check-circle' : 
                     type === 'danger' ? 'exclamation-circle' : 
                     type === 'warning' ? 'exclamation-triangle' : 'info-circle';
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${icon} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        
        // Initialize Bootstrap toast
        const bsToast = new bootstrap.Toast(toast, {
            autohide: true,
            delay: 3000
        });
        bsToast.show();
        
        // Auto-remove after hiding
        toast.addEventListener('hidden.bs.toast', function() {
            if (toast.parentNode) {
                toast.remove();
            }
        });
    }
    
    // Voice control integration
    setVoiceController(voiceController) {
        this.voiceController = voiceController;
    }
    
    // Execute voice command
    executeVoiceCommand(command, parameters) {
        console.log('Executing voice command:', command, parameters);
        
        // Show voice command toast
        this.showVoiceCommandToast(command, parameters);
        
        // Execute command
        switch(command) {
            case 'turn_on':
                if (parameters.bulb) {
                    this.controlBulb(parameters.bulb, 'on');
                } else if (parameters.all) {
                    this.groupControl('on');
                }
                break;
                
            case 'turn_off':
                if (parameters.bulb) {
                    this.controlBulb(parameters.bulb, 'off');
                } else if (parameters.all) {
                    this.groupControl('off');
                }
                break;
                
            case 'set_brightness':
                if (parameters.bulb && parameters.value !== undefined) {
                    this.controlBulb(parameters.bulb, 'brightness', parameters.value);
                } else if (parameters.all && parameters.value !== undefined) {
                    this.groupControl('brightness', parameters.value);
                }
                break;
                
            case 'start_effect':
                if (parameters.effect) {
                    this.startEffect(parameters.effect, parameters.speed);
                }
                break;
                
            case 'stop_effects':
                this.stopEffects();
                break;
                
            default:
                console.warn('Unknown voice command:', command);
                this.showToast(`Unknown command: ${command}`, 'warning');
        }
    }
    
    // Show voice command toast
    showVoiceCommandToast(command, parameters) {
        const toast = document.getElementById('voice-command-toast');
        const commandText = document.getElementById('voice-command-text');
        const actionText = document.getElementById('voice-command-action');
        
        if (!toast || !commandText || !actionText) return;
        
        // Map command to readable text
        const commandMap = {
            'turn_on': 'Turn On',
            'turn_off': 'Turn Off',
            'set_brightness': 'Set Brightness',
            'start_effect': 'Start Effect',
            'stop_effects': 'Stop Effects'
        };
        
        // Generate action description
        let actionDescription = '';
        switch(command) {
            case 'turn_on':
            case 'turn_off':
                if (parameters.bulb) {
                    actionDescription = `Bulb ${parameters.bulb}`;
                } else if (parameters.all) {
                    actionDescription = 'All bulbs';
                }
                break;
                
            case 'set_brightness':
                if (parameters.bulb) {
                    actionDescription = `Bulb ${parameters.bulb} to ${parameters.value}%`;
                } else if (parameters.all) {
                    actionDescription = `All bulbs to ${parameters.value}%`;
                }
                break;
                
            case 'start_effect':
                actionDescription = `${parameters.effect.charAt(0).toUpperCase() + parameters.effect.slice(1)} effect`;
                if (parameters.speed) {
                    actionDescription += ` (speed: ${parameters.speed})`;
                }
                break;
                
            case 'stop_effects':
                actionDescription = 'All effects stopped';
                break;
        }
        
        commandText.textContent = commandMap[command] || 'Command';
        actionText.textContent = actionDescription;
        
        // Show toast
        toast.classList.add('show');
        
        // Auto-hide after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
}

// Initialize function
function initializeSmartControl() {
    window.smartControl = new SmartBulbControl();
    window.smartControl.init();
    return window.smartControl;
}

// Make available globally
window.SmartBulbControl = SmartBulbControl;
window.initializeSmartControl = initializeSmartControl;