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

    <!-- External CSS and JS-->
     <!-- For Ngrok support -->
    @if(request()->isSecure() || str_contains(request()->getHttpHost(), 'ngrok'))
        <!-- Force HTTPS for assets -->
        <link rel="stylesheet" href="{{ str_replace('http://', 'https://', asset('css/smart-dashboard.css')) }}">
        <script src="{{ str_replace('http://', 'https://', asset('js/smart-dashboard.js')) }}"></script>
    @else
        <!-- Normal assets for local development -->
        <link rel="stylesheet" href="{{ asset('css/smart-dashboard.css') }}">
        <script src="{{ asset('js/smart-dashboard.js') }}"></script>
    @endif
    
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
    


</body>
</html>