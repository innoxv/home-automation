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