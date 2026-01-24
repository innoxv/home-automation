<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SmartBulbController extends Controller
{
    private $apiUrl = 'http://localhost:5000';
    
    public function dashboard()
    {
        return view('smart-dashboard');
    }
    
    // Individual bulb control
    public function bulbControl(Request $request)
    {
        $request->validate([
            'bulb' => 'required|integer|in:1,2,3',  // Updated for 3 bulbs
            'action' => 'required|string|in:on,off,brightness',
            'value' => 'nullable|integer|min:0|max:100'
        ]);
        
        try {
            $response = Http::post("{$this->apiUrl}/api/bulb", [
                'bulb' => $request->bulb,
                'action' => $request->action,
                'value' => $request->value
            ]);
            
            return $response->json();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // Effects control
    public function effectControl(Request $request)
    {
        $request->validate([
            'effect' => 'required|string|in:strobe,fade,pulse,rainbow,alternate,stop',
            'speed' => 'nullable|integer|min:1|max:5'
        ]);
        
        try {
            $response = Http::post("{$this->apiUrl}/api/effect", [
                'effect' => $request->effect,
                'speed' => $request->speed
            ]);
            
            return $response->json();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // Group control
    public function groupControl(Request $request)
    {
        $request->validate([
            'action' => 'required|string|in:on,off,brightness',
            'brightness' => 'nullable|integer|min:0|max:100'
        ]);
        
        try {
            $response = Http::post("{$this->apiUrl}/api/group", [
                'action' => $request->action,
                'brightness' => $request->brightness
            ]);
            
            return $response->json();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // Get status
    public function getStatus()
    {
        try {
            $response = Http::get("{$this->apiUrl}/api/status");
            return $response->json();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // Send custom command
    public function sendCommand(Request $request)
    {
        $request->validate([
            'command' => 'required|string'
        ]);
        
        try {
            $response = Http::post("{$this->apiUrl}/api/command", [
                'cmd' => $request->command
            ]);
            
            return $response->json();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Voice command
    public function voiceCommand(Request $request)
    {
        $request->validate([
            'command' => 'required|string',
            'bulb' => 'nullable|integer|in:1,2,3',
            'action' => 'nullable|string',
            'value' => 'nullable|integer|min:0|max:100',
            'effect' => 'nullable|string|in:strobe,fade,pulse,alternate,rainbow'
        ]);
        
        try {
            $response = Http::post("{$this->apiUrl}/api/voice", [
                'command' => $request->command,
                'bulb' => $request->bulb,
                'action' => $request->action,
                'value' => $request->value,
                'effect' => $request->effect
            ]);
            
            return $response->json();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}