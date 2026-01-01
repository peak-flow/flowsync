import './bootstrap';
import Alpine from 'alpinejs';
import { io } from 'socket.io-client';

// Expose globally for inline scripts
window.io = io;
// SimplePeer loaded via CDN in layout (needs Node polyfills that Vite doesn't provide)
window.Alpine = Alpine;

Alpine.start();
