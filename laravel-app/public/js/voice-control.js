// Voice Control Module with Mobile Support
class VoiceControl {
    constructor() {
        // Check device type
        this.isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        this.isHttps = window.location.protocol === 'https:';
        this.isChrome = /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor);
        this.isEdge = /Edg/.test(navigator.userAgent);
        
        console.log('VoiceControl: Device Info', {
            isMobile: this.isMobile,
            isHttps: this.isHttps,
            isChrome: this.isChrome,
            isEdge: this.isEdge,
            userAgent: navigator.userAgent
        });
        
        // Speech recognition
        this.recognition = null;
        this.isListening = false;
        this.speechSynthesis = window.speechSynthesis;
        this.currentBulb = null;
        this.isSupported = false;
        
        // Check browser support
        this.checkBrowserSupport();
        
        // Initialize if supported
        if (this.isSupported) {
            this.initializeRecognition();
        }
        
        // Commands mapping
        this.commands = {
            'turn on': { type: 'turn_on', patterns: [/turn on/i, /switch on/i, /enable/i, /power on/i] },
            'turn off': { type: 'turn_off', patterns: [/turn off/i, /switch off/i, /disable/i, /power off/i] },
            
            'bulb one': { type: 'bulb', value: 1, patterns: [/bulb one/i, /light one/i, /first bulb/i, /number one/i, /bulb 1/i] },
            'bulb two': { type: 'bulb', value: 2, patterns: [/bulb two/i, /light two/i, /second bulb/i, /number two/i, /bulb 2/i] },
            'bulb three': { type: 'bulb', value: 3, patterns: [/bulb three/i, /light three/i, /third bulb/i, /number three/i, /bulb 3/i] },
            'all bulbs': { type: 'all', patterns: [/all bulbs/i, /all lights/i, /every bulb/i, /everything/i] },
            
            'brightness': { type: 'brightness', patterns: [/brightness/i, /intensity/i, /level/i] },
            'percent': { type: 'percent', patterns: [/percent/i, /percentage/i, /\%/i] },
            
            'strobe': { type: 'effect', value: 'strobe', patterns: [/strobe/i, /flash/i, /blink/i] },
            'fade': { type: 'effect', value: 'fade', patterns: [/fade/i, /dim/i, /smooth/i] },
            'pulse': { type: 'effect', value: 'pulse', patterns: [/pulse/i, /breathe/i, /throb/i] },
            'alternate': { type: 'effect', value: 'alternate', patterns: [/alternate/i, /alternating/i, /sequence/i] },
            'rainbow': { type: 'effect', value: 'rainbow', patterns: [/rainbow/i, /color/i, /cycle/i] },
            
            'stop': { type: 'stop', patterns: [/stop/i, /halt/i, /end/i, /cancel/i] },
            
            'numbers': { 
                type: 'number', 
                patterns: [
                    /zero|0/i, /one|1/i, /two|2/i, /three|3/i, /four|4/i, 
                    /five|5/i, /six|6/i, /seven|7/i, /eight|8/i, /nine|9/i,
                    /ten|10/i, /twenty|20/i, /thirty|30/i, /forty|40/i, /fifty|50/i,
                    /sixty|60/i, /seventy|70/i, /eighty|80/i, /ninety|90/i, /hundred|100/i,
                    /full|maximum/i, /half|50/i, /quarter|25/i
                ]
            }
        };
    }
    
    // Check browser support
    checkBrowserSupport() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        
        // Check for Web Speech API
        if (!SpeechRecognition) {
            console.warn('Web Speech API not supported');
            this.updateVoiceStatus('unsupported');
            this.showFeedback('Voice control not supported in this browser.');
            this.isSupported = false;
            return;
        }
        
        // Check for microphone access
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            console.warn('Microphone access not available');
            this.updateVoiceStatus('unsupported');
            this.showFeedback('Microphone access not available.');
            this.isSupported = false;
            return;
        }
        
        // Check mobile HTTPS requirement
        if (this.isMobile && !this.isHttps) {
            console.warn('Mobile requires HTTPS for voice');
            this.updateVoiceStatus('disabled');
            this.showFeedback('Voice requires HTTPS on mobile.');
            this.isSupported = false;
            return;
        }
        
        this.isSupported = true;
        console.log('VoiceControl: Web Speech API supported');
    }
    
    // Initialize recognition
    initializeRecognition() {
        try {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();
            
            // Configure for better mobile compatibility
            this.recognition.continuous = false;
            this.recognition.interimResults = false;
            this.recognition.lang = 'en-US';
            this.recognition.maxAlternatives = 3;
            
            // Set event handlers
            this.recognition.onstart = () => this.onRecognitionStart();
            this.recognition.onresult = (event) => this.onRecognitionResult(event);
            this.recognition.onerror = (event) => this.onRecognitionError(event);
            this.recognition.onend = () => this.onRecognitionEnd();
            
            console.log('VoiceControl: Speech recognition initialized');
            this.updateVoiceStatus('ready');
            
        } catch (error) {
            console.error('VoiceControl: Failed to initialize recognition:', error);
            this.updateVoiceStatus('error');
            this.showFeedback('Failed to initialize voice recognition.');
            this.isSupported = false;
        }
    }
    
    // Start listening
    async startListening(targetBulb = null) {
        if (!this.isSupported) {
            this.showFeedback('Voice control not available.');
            return;
        }
        
        if (!this.recognition) {
            this.showFeedback('Voice recognition not initialized.');
            return;
        }
        
        // Check mobile compatibility
        if (this.isMobile && !this.isHttps) {
            this.showFeedback('Voice requires HTTPS on mobile.');
            return;
        }
        
        // Request microphone permission
        const hasPermission = await this.requestMicrophonePermission();
        if (!hasPermission) {
            return;
        }
        
        this.currentBulb = targetBulb;
        
        try {
            this.recognition.start();
            this.isListening = true;
            this.updateVoiceStatus('listening');
            
            // Feedback
            if (targetBulb) {
                this.showFeedback(`Listening for bulb ${targetBulb}...`);
            } else {
                this.showFeedback('Listening... Speak now!');
            }
            
        } catch (error) {
            console.error('VoiceControl: Error starting recognition:', error);
            this.updateVoiceStatus('error');
            this.showFeedback('Error starting microphone.');
            this.isListening = false;
        }
    }
    
    // Start listening for specific bulb
    startListeningForBulb(bulbNum) {
        this.startListening(bulbNum);
    }
    
    // Stop listening
    stopListening() {
        if (this.recognition && this.isListening) {
            this.recognition.stop();
            this.isListening = false;
            this.updateVoiceStatus('ready');
        }
    }
    
    // Toggle listening
    toggleListening() {
        if (this.isListening) {
            this.stopListening();
        } else {
            this.startListening();
        }
    }
    
    // Request microphone permission
    async requestMicrophonePermission() {
        try {
            // For mobile, we need to handle permissions carefully
            if (this.isMobile) {
                // Check if we already have permission
                const devices = await navigator.mediaDevices.enumerateDevices();
                const hasMicrophone = devices.some(device => device.kind === 'audioinput' && device.label);
                
                if (hasMicrophone) {
                    return true;
                }
            }
            
            // Request permission
            const stream = await navigator.mediaDevices.getUserMedia({ 
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                }
            });
            
            // Stop tracks immediately
            stream.getTracks().forEach(track => track.stop());
            
            console.log('VoiceControl: Microphone permission granted');
            return true;
            
        } catch (error) {
            console.error('VoiceControl: Microphone permission error:', error);
            
            let message = 'Microphone permission denied.';
            if (error.name === 'NotAllowedError') {
                message = 'Please allow microphone access in browser settings.';
            } else if (error.name === 'NotFoundError') {
                message = 'No microphone found.';
            }
            
            this.showFeedback(message);
            return false;
        }
    }
    
    // On recognition start
    onRecognitionStart() {
        console.log('VoiceControl: Recognition started');
        this.updateVoiceStatus('listening');
        this.showFeedback('Listening...');
    }
    
    // On recognition result
    onRecognitionResult(event) {
        const result = event.results[0];
        const transcript = result[0].transcript.toLowerCase();
        const confidence = result[0].confidence;
        
        console.log('VoiceControl: Recognized:', transcript, 'Confidence:', confidence);
        
        this.showFeedback(`Heard: "${transcript}"`);
        this.updateVoiceStatus('processing');
        
        // Process command
        this.processCommand(transcript);
    }
    
    // On recognition error
    onRecognitionError(event) {
        console.error('VoiceControl: Recognition error:', event.error);
        
        let message = 'Voice recognition error.';
        switch(event.error) {
            case 'no-speech':
                message = 'No speech detected.';
                break;
            case 'audio-capture':
                message = 'No microphone found.';
                break;
            case 'not-allowed':
                message = 'Microphone access denied.';
                break;
            case 'network':
                message = 'Network error occurred.';
                break;
            default:
                message = `Error: ${event.error}`;
        }
        
        this.showFeedback(message);
        this.updateVoiceStatus('error');
        this.isListening = false;
    }
    
    // On recognition end
    onRecognitionEnd() {
        console.log('VoiceControl: Recognition ended');
        this.isListening = false;
        this.updateVoiceStatus('ready');
        
        // Reset current bulb
        setTimeout(() => {
            this.currentBulb = null;
        }, 1000);
    }
    
    // Process command
    processCommand(transcript) {
        const parsedCommand = this.parseTranscript(transcript);
        
        if (parsedCommand) {
            this.executeParsedCommand(parsedCommand);
        } else {
            this.showFeedback('Command not understood. Try again.');
            this.speak('I did not understand that command. Please try again.');
        }
    }
    
    // Parse transcript
    parseTranscript(transcript) {
        console.log('VoiceControl: Parsing transcript:', transcript);
        
        let command = null;
        let parameters = {};
        
        // Check for bulb-specific commands
        if (this.currentBulb) {
            parameters.bulb = this.currentBulb;
        }
        
        // Extract numbers
        const numbers = this.extractNumbers(transcript);
        
        // Check for "all" commands
        if (transcript.match(/all|every|both/i)) {
            parameters.all = true;
        }
        
        // Check for specific bulb mentions
        for (let i = 1; i <= 3; i++) {
            if (transcript.match(new RegExp(`bulb ${i}|light ${i}|number ${i}|bulb ${['one', 'two', 'three'][i-1]}`, 'i'))) {
                parameters.bulb = i;
                break;
            }
        }
        
        // Check for brightness commands
        if ((transcript.match(/brightness|intensity|level|set to/i) && numbers.length > 0) ||
            (numbers.length > 0 && transcript.match(/percent|percentage|\%/i))) {
            command = 'set_brightness';
            parameters.value = numbers[0];
        }
        // Check for turn on commands
        else if (transcript.match(/turn on|switch on|enable|power on/i)) {
            command = 'turn_on';
        }
        // Check for turn off commands
        else if (transcript.match(/turn off|switch off|disable|power off/i)) {
            command = 'turn_off';
        }
        // Check for effect commands
        else if (transcript.match(/strobe|flash|blink/i)) {
            command = 'start_effect';
            parameters.effect = 'strobe';
        }
        else if (transcript.match(/fade|dim|smooth/i)) {
            command = 'start_effect';
            parameters.effect = 'fade';
        }
        else if (transcript.match(/pulse|breathe|throb/i)) {
            command = 'start_effect';
            parameters.effect = 'pulse';
        }
        else if (transcript.match(/alternate|alternating|sequence/i)) {
            command = 'start_effect';
            parameters.effect = 'alternate';
        }
        else if (transcript.match(/rainbow|color|cycle/i)) {
            command = 'start_effect';
            parameters.effect = 'rainbow';
        }
        // Check for stop commands
        else if (transcript.match(/stop|halt|end|cancel/i)) {
            command = 'stop_effects';
        }
        
        // If no specific bulb and not targeting all, assume all for group commands
        if (command && !parameters.bulb && !parameters.all && 
            ['turn_on', 'turn_off', 'set_brightness'].includes(command)) {
            parameters.all = true;
        }
        
        return command ? { command, parameters } : null;
    }
    
    // Extract numbers from transcript
    extractNumbers(transcript) {
        const numberMap = {
            'zero': 0, '0': 0,
            'one': 1, '1': 1, 'first': 1,
            'two': 2, '2': 2, 'second': 2, 'to': 2, 'too': 2,
            'three': 3, '3': 3, 'third': 3,
            'four': 4, '4': 4, 'for': 4,
            'five': 5, '5': 5,
            'six': 6, '6': 6,
            'seven': 7, '7': 7,
            'eight': 8, '8': 8,
            'nine': 9, '9': 9,
            'ten': 10, '10': 10,
            'twenty': 20, '20': 20,
            'thirty': 30, '30': 30,
            'forty': 40, '40': 40,
            'fifty': 50, '50': 50,
            'sixty': 60, '60': 60,
            'seventy': 70, '70': 70,
            'eighty': 80, '80': 80,
            'ninety': 90, '90': 90,
            'hundred': 100, '100': 100,
            'full': 100, 'maximum': 100, 'max': 100,
            'half': 50, 'quarter': 25
        };
        
        const words = transcript.toLowerCase().split(/\s+/);
        const numbers = [];
        
        for (const word of words) {
            if (numberMap[word] !== undefined) {
                numbers.push(numberMap[word]);
            }
        }
        
        // Also look for numeric patterns
        const numericPattern = /(\d+)\s*\%?/g;
        let match;
        while ((match = numericPattern.exec(transcript)) !== null) {
            const num = parseInt(match[1]);
            if (!isNaN(num) && num >= 0 && num <= 100) {
                numbers.push(num);
            }
        }
        
        return numbers;
    }
    
    // Execute parsed command
    executeParsedCommand(parsedCommand) {
        const { command, parameters } = parsedCommand;
        
        console.log('VoiceControl: Executing command:', command, parameters);
        
        // Give audio feedback
        this.giveAudioFeedback(command, parameters);
        
        // Update visual feedback
        this.updateVoiceStatus('success');
        this.showFeedback('Command executed!');
        
        // Execute command through smart control
        if (window.smartControl && typeof window.smartControl.executeVoiceCommand === 'function') {
            window.smartControl.executeVoiceCommand(command, parameters);
        } else {
            console.error('SmartControl not available');
            this.showFeedback('Error: Control system not available');
        }
    }
    
    // Give audio feedback
    giveAudioFeedback(command, parameters) {
        let feedbackText = '';
        
        switch(command) {
            case 'turn_on':
                if (parameters.bulb) {
                    feedbackText = `Turning on bulb ${parameters.bulb}`;
                } else if (parameters.all) {
                    feedbackText = 'Turning on all bulbs';
                }
                break;
                
            case 'turn_off':
                if (parameters.bulb) {
                    feedbackText = `Turning off bulb ${parameters.bulb}`;
                } else if (parameters.all) {
                    feedbackText = 'Turning off all bulbs';
                }
                break;
                
            case 'set_brightness':
                if (parameters.bulb) {
                    feedbackText = `Setting bulb ${parameters.bulb} to ${parameters.value} percent`;
                } else if (parameters.all) {
                    feedbackText = `Setting all bulbs to ${parameters.value} percent`;
                }
                break;
                
            case 'start_effect':
                feedbackText = `Starting ${parameters.effect} effect`;
                break;
                
            case 'stop_effects':
                feedbackText = 'Stopping all effects';
                break;
                
            default:
                feedbackText = 'Command executed';
        }
        
        if (feedbackText) {
            this.speak(feedbackText);
        }
    }
    
    // Speak text
    speak(text) {
        if (!this.speechSynthesis) return;
        
        // Cancel any ongoing speech
        this.speechSynthesis.cancel();
        
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.rate = 1.0;
        utterance.pitch = 1.0;
        utterance.volume = 0.8;
        utterance.lang = 'en-US';
        
        this.speechSynthesis.speak(utterance);
    }
    
    // Update voice status
    updateVoiceStatus(status) {
        const statusElement = document.getElementById('voice-status-text');
        const feedbackElement = document.getElementById('voice-feedback');
        const toggleButton = document.getElementById('voice-toggle');
        const statusDisplay = document.getElementById('voice-status-display');
        const badgeText = document.getElementById('voice-badge-text');
        
        // Status text
        if (statusElement) {
            const statusText = {
                'ready': 'Voice Ready',
                'listening': 'Listening...',
                'processing': 'Processing...',
                'success': 'Success',
                'error': 'Error',
                'unsupported': 'Not Supported',
                'disabled': 'Disabled'
            }[status] || 'Voice Control';
            
            statusElement.textContent = statusText;
        }
        
        // Feedback indicator
        if (feedbackElement) {
            feedbackElement.className = 'voice-feedback';
            if (status === 'listening' || status === 'processing') {
                feedbackElement.classList.add(status);
            } else if (status === 'success') {
                feedbackElement.classList.add('success');
            } else if (status === 'error') {
                feedbackElement.classList.add('error');
            }
        }
        
        // Toggle button
        if (toggleButton) {
            toggleButton.className = 'voice-toggle-btn';
            if (status === 'listening') {
                toggleButton.classList.add('listening');
                toggleButton.classList.add('voice-loading');
            } else {
                toggleButton.classList.remove('voice-loading');
            }
            
            // Disable if not supported
            if (status === 'unsupported' || status === 'disabled') {
                toggleButton.classList.add('voice-disabled');
                toggleButton.disabled = true;
            } else {
                toggleButton.classList.remove('voice-disabled');
                toggleButton.disabled = false;
            }
        }
        
        // Status display
        if (statusDisplay) {
            const displayText = {
                'ready': 'Ready',
                'listening': 'Listening',
                'processing': 'Processing',
                'success': 'Success',
                'error': 'Error',
                'unsupported': 'Not Supported',
                'disabled': 'Disabled'
            }[status] || 'Unknown';
            
            statusDisplay.textContent = displayText;
            
            // Color coding
            if (status === 'ready' || status === 'success') {
                statusDisplay.style.color = '#4CAF50';
            } else if (status === 'listening' || status === 'processing') {
                statusDisplay.style.color = '#FF9800';
            } else {
                statusDisplay.style.color = '#f44336';
            }
        }
        
        // Badge text
        if (badgeText) {
            if (status === 'unsupported' || status === 'disabled') {
                badgeText.textContent = 'No Voice';
            } else if (status === 'listening' || status === 'processing') {
                badgeText.textContent = 'Listening';
            } else {
                badgeText.textContent = 'Voice Ready';
            }
        }
    }
    
    // Show feedback
    showFeedback(message) {
        const feedbackElement = document.getElementById('voice-feedback');
        if (feedbackElement) {
            // Set title for tooltip
            feedbackElement.title = message;
            
            // Also update command list if visible
            const commandList = document.getElementById('voice-commands-list');
            if (commandList && commandList.classList.contains('show')) {
                let feedbackDisplay = commandList.querySelector('.voice-feedback-display');
                
                if (!feedbackDisplay) {
                    feedbackDisplay = document.createElement('div');
                    feedbackDisplay.className = 'voice-feedback-display';
                    feedbackDisplay.style.cssText = `
                        font-size: 0.8rem;
                        color: #666;
                        margin-top: 5px;
                        padding: 5px;
                        background-color: #f5f5f5;
                        border-radius: 5px;
                        border-left: 3px solid #667eea;
                    `;
                    commandList.appendChild(feedbackDisplay);
                }
                
                feedbackDisplay.textContent = message;
                
                // Auto-remove after 3 seconds
                setTimeout(() => {
                    if (feedbackDisplay.parentNode) {
                        feedbackDisplay.remove();
                    }
                }, 3000);
            }
        }
    }
    
    // Toggle command list
    toggleCommandList() {
        const commandList = document.getElementById('voice-commands-list');
        if (commandList) {
            commandList.classList.toggle('show');
        }
    }
}

// Initialize voice control
function initializeVoiceControl() {
    window.voiceControl = new VoiceControl();
    
    // Connect to smart control
    if (window.smartControl) {
        window.smartControl.setVoiceController(window.voiceControl);
    }
    
    // Set up event listeners
    const voiceToggleBtn = document.getElementById('voice-toggle');
    if (voiceToggleBtn) {
        voiceToggleBtn.addEventListener('click', () => {
            window.voiceControl.toggleListening();
        });
    }
    
    const voiceStatus = document.querySelector('.voice-status');
    if (voiceStatus) {
        voiceStatus.addEventListener('click', () => {
            window.voiceControl.toggleCommandList();
        });
    }
    
    console.log('VoiceControl: Initialized');
    return window.voiceControl;
}

// Make available globally
window.VoiceControl = VoiceControl;
window.initializeVoiceControl = initializeVoiceControl;
window.toggleVoiceControl = () => {
    if (window.voiceControl) {
        window.voiceControl.toggleListening();
    }
};