/**
 * PeroniksCameraScanner — Shared Camera Scanner Engine v1.0
 * ============================================================
 * Single source of truth for Html5Qrcode camera scanning
 * across all WMS pages (Opname, Stock In, Stock Out/Scan).
 *
 * Usage:
 *   PeroniksCameraScanner.start({
 *       readerId:    'reader',            // id of the Html5Qrcode mount div
 *       containerId: 'scanner-container', // id of the parent container to show/hide
 *       onSuccess: function(decodedText) { ... },
 *       onError:   function(err) { ... }  // optional — called only on startup failure
 *   });
 *
 *   PeroniksCameraScanner.stop();
 *
 * Dependency: window.Html5Qrcode (registered in app.js from html5-qrcode npm package)
 */
(function (global) {
    'use strict';

    var _instance   = null;   // Html5Qrcode instance (reused across calls)
    var _containerId = null;  // current container id

    var CONFIG = {
        fps: 20,
        qrbox: { width: 280, height: 180 },
        aspectRatio: 1.0,
        experimentalFeatures: {
            useBarCodeDetectorIfSupported: true
        },
        videoConstraints: {
            facingMode: 'environment',
            width:  { min: 640,  ideal: 1280 },
            height: { min: 480,  ideal: 720  }
        }
    };

    // ── private helpers ────────────────────────────────────────────────────

    function _showContainer(containerId) {
        var el = document.getElementById(containerId);
        if (el) el.classList.remove('hidden');
    }

    function _hideContainer(containerId) {
        var el = document.getElementById(containerId);
        if (el) el.classList.add('hidden');
    }

    function _getOrCreateInstance(readerId) {
        if (!_instance) {
            if (typeof Html5Qrcode === 'undefined') {
                console.error('[PeroniksCameraScanner] Html5Qrcode is not available on window. Ensure app.js is loaded.');
                return null;
            }
            _instance = new Html5Qrcode(readerId);
        }
        return _instance;
    }

    // ── public API ─────────────────────────────────────────────────────────

    var Scanner = {
        /**
         * Start camera scanning.
         *
         * @param {object} opts
         * @param {string}   opts.readerId     - id of the Html5Qrcode mount <div>
         * @param {string}   opts.containerId  - id of the wrapper to show/hide
         * @param {function} opts.onSuccess    - called with (decodedText) on successful scan
         * @param {function} [opts.onError]    - called with (err) if camera fails to start
         */
        start: function (opts) {
            var readerId    = opts.readerId    || 'reader';
            var containerId = opts.containerId || 'scanner-container';
            var onSuccess   = opts.onSuccess   || function () {};
            var onError     = opts.onError     || null;

            _containerId = containerId;

            // If already scanning, stop first then restart
            if (_instance && _instance.isScanning) {
                _instance.stop().catch(function () {}).finally(function () {
                    Scanner._launch(readerId, containerId, onSuccess, onError);
                });
                return;
            }

            Scanner._launch(readerId, containerId, onSuccess, onError);
        },

        /** @private */
        _launch: function (readerId, containerId, onSuccess, onError) {
            _showContainer(containerId);

            var qr = _getOrCreateInstance(readerId);
            if (!qr) return;

            qr.start(
                { facingMode: 'environment' },
                CONFIG,
                function (decodedText) {
                    // Auto-stop on success, then hand off to caller
                    Scanner.stop();
                    onSuccess(decodedText);
                },
                function () {
                    // Per-frame failure — normal during camera search, suppress
                }
            ).catch(function (err) {
                console.error('[PeroniksCameraScanner] Camera startup failed:', err);
                Scanner.stop();

                var msg = 'Could not start camera. Please ensure camera permissions are granted.';
                if (onError) {
                    onError(err);
                } else {
                    alert(msg);
                }
            });
        },

        /**
         * Stop camera scanning and hide the container.
         * Safe to call even when not scanning.
         */
        stop: function () {
            var containerId = _containerId || 'scanner-container';

            if (_instance && _instance.isScanning) {
                _instance.stop().then(function () {
                    _hideContainer(containerId);
                }).catch(function (err) {
                    console.error('[PeroniksCameraScanner] Error stopping scanner:', err);
                    _hideContainer(containerId);
                });
            } else {
                _hideContainer(containerId);
            }
        },

        /**
         * Fully destroy the Html5Qrcode instance.
         * Call on Livewire component destroy / page navigation to guarantee
         * the camera LED is turned off.
         */
        destroy: function () {
            if (_instance) {
                if (_instance.isScanning) {
                    _instance.stop().catch(function () {}).finally(function () {
                        _instance.clear().catch(function () {});
                        _instance = null;
                    });
                } else {
                    _instance.clear().catch(function () {});
                    _instance = null;
                }
            }
        }
    };

    global.PeroniksCameraScanner = Scanner;

    // ── Livewire lifecycle integration ─────────────────────────────────────
    // Stop camera on Livewire navigate-away to prevent zombie sessions.
    document.addEventListener('livewire:navigating', function () {
        Scanner.stop();
    });

}(window));
